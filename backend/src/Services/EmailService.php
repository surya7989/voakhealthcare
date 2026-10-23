<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Services\Mail\MailerTransportInterface;

/**
 * Enterprise Transactional Email Notification & Outbox Telemetry Engine.
 * Implements the Hostinger-compatible Tri-Tier notification architecture (ADR-006):
 * - Tier 1: Bounded synchronous fast-path (max 4-5s timeout) for urgent security events
 * - Tier 2: Durable relational outbox queue (email_queue) with atomic batch claiming
 * - Tier 3: Outbox telemetry, manual administrative retry, and webhook/cron dispatch
 * Strictly enforces mandatory variable placeholder guards (TEST-ADM-013, TEST-SEC-005)
 * and spreadsheet/template injection neutralization.
 */
class EmailService
{
    /**
     * Canonical 8 transactional workflows inventory (EMAIL-01 to EMAIL-08).
     */
    public const CANONICAL_TEMPLATES = [
        'tmpl_contact_ack' => [
            'id'                     => 1,
            'template_key'           => 'tmpl_contact_ack',
            'slug'                   => 'contact-inquiry-ack',
            'title'                  => 'Contact Inquiry Acknowledgement',
            'trigger_event'          => 'Public user submits general contact inquiry form',
            'default_recipient_type' => 'Inquirer work email',
            'priority'               => 5,
            'subject'                => '{{legal_entity_name}} | Inquiry Received [{{ref_no}}]',
            'mandatory_placeholders' => ['{{ref_no}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{contact_name}}', '{{organization_name}}', '{{ref_no}}',
                '{{submission_datetime}}', '{{inquiry_summary}}',
                '{{legal_entity_name}}', '{{registration_number}}',
                '{{registered_address}}', '{{privacy_policy_url}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Inquiry Acknowledgement</h2>
    <p>Dear {{contact_name}},</p>
    <p>Thank you for reaching out to <strong>{{legal_entity_name}}</strong>. We have received your inquiry on behalf of <strong>{{organization_name}}</strong>.</p>
    <div style="background-color: #f8fafc; border-left: 4px solid #0284c7; padding: 12px 16px; margin: 16px 0;">
        <p style="margin: 0;"><strong>Reference Number:</strong> {{ref_no}}</p>
        <p style="margin: 4px 0 0 0;"><strong>Received At:</strong> {{submission_datetime}}</p>
    </div>
    <p>Our operational management team is reviewing your requirements and will connect with you within 1 business day.</p>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 12px; color: #64748b;">
        This communication was generated for {{contact_name}} regarding Reference {{ref_no}}. Corporate Registration: {{registration_number}}. Registered Office: {{registered_address}}. For privacy disclosures, please review <a href="{{privacy_policy_url}}">Privacy Policy</a>.
    </p>
</div>',
        ],
        'tmpl_contact_internal' => [
            'id'                     => 2,
            'template_key'           => 'tmpl_contact_internal',
            'slug'                   => 'contact-inquiry-internal',
            'title'                  => 'Internal Inquiry Alert',
            'trigger_event'          => 'Public contact inquiry triage alert to operational staff',
            'default_recipient_type' => 'Internal triage address',
            'priority'               => 3,
            'subject'                => '[NEW INQUIRY] {{inquiry_type}} - {{organization_name}} [{{ref_no}}]',
            'mandatory_placeholders' => ['{{ref_no}}', '{{contact_name}}', '{{inquiry_type}}'],
            'allowed_placeholders'   => [
                '{{contact_name}}', '{{organization_name}}', '{{work_email}}',
                '{{phone}}', '{{country_code}}', '{{inquiry_type}}',
                '{{message}}', '{{ref_no}}', '{{admin_crm_url}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #b91c1c;">Inbound Contact Notification [{{ref_no}}]</h2>
    <p>A new public contact enquiry has been received requiring triage:</p>
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold;">Reference</td><td style="padding: 6px; border: 1px solid #e2e8f0;">{{ref_no}}</td></tr>
        <tr><td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold;">Contact Name</td><td style="padding: 6px; border: 1px solid #e2e8f0;">{{contact_name}}</td></tr>
        <tr><td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold;">Organization</td><td style="padding: 6px; border: 1px solid #e2e8f0;">{{organization_name}}</td></tr>
        <tr><td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold;">Email</td><td style="padding: 6px; border: 1px solid #e2e8f0;">{{work_email}}</td></tr>
        <tr><td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold;">Inquiry Type</td><td style="padding: 6px; border: 1px solid #e2e8f0;">{{inquiry_type}}</td></tr>
    </table>
    <p><strong>Message Summary:</strong><br />{{message}}</p>
    <p><a href="{{admin_crm_url}}" style="display: inline-block; background-color: #0f172a; color: #fff; padding: 8px 16px; text-decoration: none; border-radius: 4px;">View in CRM Desk</a></p>
</div>',
        ],
        'tmpl_partner_ack' => [
            'id'                     => 3,
            'template_key'           => 'tmpl_partner_ack',
            'slug'                   => 'partnership-ack',
            'title'                  => 'Strategic Partnership Acknowledgement',
            'trigger_event'          => 'Enterprise partner submits partnership inquiry',
            'default_recipient_type' => 'Executive partner corporate email',
            'priority'               => 4,
            'subject'                => '{{legal_entity_name}} | Strategic Partnership Inquiry Acknowledgement [{{ref_no}}]',
            'mandatory_placeholders' => ['{{ref_no}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{executive_name}}', '{{organization_name}}', '{{ref_no}}',
                '{{alliance_type}}', '{{proposed_scope}}', '{{legal_entity_name}}',
                '{{registration_number}}', '{{registered_address}}', '{{privacy_policy_url}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Strategic Partnership Inquiry</h2>
    <p>Dear {{executive_name}},</p>
    <p>Thank you for initiating a strategic dialogue with <strong>{{legal_entity_name}}</strong> on behalf of <strong>{{organization_name}}</strong>.</p>
    <p>Your strategic partnership inquiry has been logged under Reference <strong>{{ref_no}}</strong>. Our executive leadership team reviews institutional alliances every Tuesday and Thursday.</p>
    <div style="background-color: #f8fafc; border-left: 4px solid #059669; padding: 12px 16px; margin: 16px 0;">
        <p style="margin: 0;"><strong>Alliance Type:</strong> {{alliance_type}}</p>
        <p style="margin: 4px 0 0 0;"><strong>Reference Code:</strong> {{ref_no}}</p>
    </div>
    <p>We honor institutional confidentiality regarding all preliminary discussions.</p>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 12px; color: #64748b;">
        {{legal_entity_name}} — Business Registration: {{registration_number}}. Corporate Address: {{registered_address}}.
    </p>
</div>',
        ],
        'tmpl_proposal_ack' => [
            'id'                     => 4,
            'template_key'           => 'tmpl_proposal_ack',
            'slug'                   => 'proposal-ack',
            'title'                  => 'Proposal / RFP Submission Acknowledgement',
            'trigger_event'          => 'Healthcare procurement officer submits RFP wizard form',
            'default_recipient_type' => 'Procurement lead email',
            'priority'               => 4,
            'subject'                => '{{legal_entity_name}} | Healthcare Operations Proposal Acknowledged [{{ref_no}}]',
            'mandatory_placeholders' => ['{{ref_no}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{contact_name}}', '{{organization_name}}', '{{facility_type}}',
                '{{ref_no}}', '{{timeline}}', '{{budget_band}}',
                '{{legal_entity_name}}', '{{registration_number}}', '{{registered_address}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Commercial RFP Acknowledged</h2>
    <p>Dear {{contact_name}},</p>
    <p>We confirm receipt of the healthcare operations RFP submitted on behalf of <strong>{{organization_name}}</strong>.</p>
    <p>Your proposal dossier has been registered under Tracking Code: <strong>{{ref_no}}</strong>.</p>
    <p>All attached tender specifications are held in secure quarantined storage in compliance with ISO 27001 data governance standards.</p>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 12px; color: #64748b;">
        {{legal_entity_name}} | Commercial Procurement Division. Registration: {{registration_number}}.
    </p>
</div>',
        ],
        'tmpl_careers_ack' => [
            'id'                     => 5,
            'template_key'           => 'tmpl_careers_ack',
            'slug'                   => 'careers-application-ack',
            'title'                  => 'Candidate Application Acknowledgement',
            'trigger_event'          => 'Applicant applies for clinical or operational job posting',
            'default_recipient_type' => 'Candidate email',
            'priority'               => 6,
            'subject'                => 'Application Received: {{job_title}} | {{legal_entity_name}} Careers',
            'mandatory_placeholders' => ['{{job_title}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{first_name}}', '{{job_title}}', '{{department}}',
                '{{submission_date}}', '{{legal_entity_name}}',
                '{{privacy_policy_url}}', '{{registration_number}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Application Received</h2>
    <p>Dear {{first_name}},</p>
    <p>Thank you for applying for the position of <strong>{{job_title}}</strong> at <strong>{{legal_entity_name}}</strong>.</p>
    <p>Our talent acquisition team will review your qualifications against our clinical governance and operational benchmarks.</p>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 12px; color: #64748b;">
        {{legal_entity_name}} Recruitment Governance. Data retained in accordance with our <a href="{{privacy_policy_url}}">Privacy Policy</a>.
    </p>
</div>',
        ],
        'tmpl_admin_invite' => [
            'id'                     => 6,
            'template_key'           => 'tmpl_admin_invite',
            'slug'                   => 'admin-invitation',
            'title'                  => 'Administrator Account Invitation (Fast-Path)',
            'trigger_event'          => 'Super admin invites new administrative user',
            'default_recipient_type' => 'Invited admin email',
            'priority'               => 1,
            'subject'                => 'Action Required: Invitation to {{legal_entity_name}} Admin Portal',
            'mandatory_placeholders' => ['{{invitation_link}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{full_name}}', '{{assigned_role}}', '{{invitation_link}}',
                '{{legal_entity_name}}', '{{expires_in_hours}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Administrative Portal Invitation</h2>
    <p>Hello {{full_name}},</p>
    <p>You have been provisioned an administrative account on the <strong>{{legal_entity_name}}</strong> operations console with the role: <strong>{{assigned_role}}</strong>.</p>
    <p>To activate your credential and configure multi-factor authentication (MFA), click the secure link below:</p>
    <p style="margin: 24px 0;"><a href="{{invitation_link}}" style="background-color: #0284c7; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Activate Administrative Account</a></p>
    <p style="font-size: 13px; color: #64748b;">This activation token is single-use and expires in 24 hours.</p>
</div>',
        ],
        'tmpl_password_reset' => [
            'id'                     => 7,
            'template_key'           => 'tmpl_password_reset',
            'slug'                   => 'password-reset',
            'title'                  => 'Password Recovery & Security Reset (Fast-Path)',
            'trigger_event'          => 'Admin requests password reset link',
            'default_recipient_type' => 'Admin registered email',
            'priority'               => 1,
            'subject'                => 'Security Notice: {{legal_entity_name}} Password Reset Request',
            'mandatory_placeholders' => ['{{reset_link}}', '{{legal_entity_name}}'],
            'allowed_placeholders'   => [
                '{{full_name}}', '{{reset_link}}', '{{request_ip}}',
                '{{request_datetime}}', '{{legal_entity_name}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #0f172a;">Password Reset Request</h2>
    <p>Hello {{full_name}},</p>
    <p>We received a password reset request for your administrative account on <strong>{{legal_entity_name}}</strong>.</p>
    <p style="margin: 24px 0;"><a href="{{reset_link}}" style="background-color: #0f172a; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Reset Admin Password</a></p>
    <p style="font-size: 13px; color: #64748b;">This security token is single-use and expires in 15 minutes. Request IP: {{request_ip}} at {{request_datetime}}.</p>
</div>',
        ],
        'tmpl_security_alert' => [
            'id'                     => 8,
            'template_key'           => 'tmpl_security_alert',
            'slug'                   => 'security-alert',
            'title'                  => 'High-Priority Security Telemetry Alert (Fast-Path)',
            'trigger_event'          => 'System detects security threshold breach (e.g. account lockout)',
            'default_recipient_type' => 'Super admin security notification email',
            'priority'               => 1,
            'subject'                => '[CRITICAL SECURITY ALERT] {{event_type}} Detected on Admin Console',
            'mandatory_placeholders' => ['{{incident_code}}', '{{event_type}}'],
            'allowed_placeholders'   => [
                '{{incident_code}}', '{{event_type}}', '{{target_user}}',
                '{{source_ip}}', '{{user_agent}}', '{{timestamp}}',
            ],
            'body_html'              => '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">Security Incident Alert [{{incident_code}}]</h2>
    <p>A critical security event threshold was breached on the Voak Health Care infrastructure:</p>
    <ul>
        <li><strong>Event Type:</strong> {{event_type}}</li>
        <li><strong>Target Account:</strong> {{target_user}}</li>
        <li><strong>Source IP Address:</strong> {{source_ip}}</li>
        <li><strong>User Agent:</strong> {{user_agent}}</li>
        <li><strong>Detection Timestamp:</strong> {{timestamp}}</li>
    </ul>
    <p>Immediate administrative review of audit logs is recommended.</p>
</div>',
        ],
    ];

    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private MailerTransportInterface $transport;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?MailerTransportInterface $transport = null
    ) {
        $this->connection  = $connection;
        $this->auditLogger = $auditLogger;
        $this->sanitizer   = $sanitizer;
        $this->transport   = $transport ?? new ArrayTransport();
    }

    public function setTransport(MailerTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function getTransport(): MailerTransportInterface
    {
        return $this->transport;
    }

    // =========================================================================
    // SECTION 1: TEMPLATE MANAGEMENT (ADM-012, FR-ADM-013)
    // =========================================================================

    /**
     * Lists all 8 canonical email templates with current overrides.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        $overrides = $this->loadTemplateOverrides();

        $result = [];
        foreach (self::CANONICAL_TEMPLATES as $key => $tpl) {
            $item = $tpl;
            if (isset($overrides[$key])) {
                $ov = $overrides[$key];
                $item['subject']    = $ov['subject'] ?? $item['subject'];
                $item['body_html']  = $ov['body_html'] ?? $item['body_html'];
                $item['is_active']  = (bool)($ov['is_active'] ?? true);
                $item['updated_at'] = $ov['updated_at'] ?? null;
            } else {
                $item['is_active']  = true;
                $item['updated_at'] = null;
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Retrieves a single template by ID (1-8), key (tmpl_...), or slug.
     *
     * @throws NotFoundException If template does not exist
     * @return array<string, mixed>
     */
    public function getTemplate(string|int $idOrKey): array
    {
        $targetKey = $this->resolveTemplateKey($idOrKey);
        if ($targetKey === null || !isset(self::CANONICAL_TEMPLATES[$targetKey])) {
            throw new NotFoundException("Email template [{$idOrKey}] not found.");
        }

        $base = self::CANONICAL_TEMPLATES[$targetKey];
        $overrides = $this->loadTemplateOverrides();

        if (isset($overrides[$targetKey])) {
            $ov = $overrides[$targetKey];
            $base['subject']    = $ov['subject'] ?? $base['subject'];
            $base['body_html']  = $ov['body_html'] ?? $base['body_html'];
            $base['is_active']  = (bool)($ov['is_active'] ?? true);
            $base['updated_at'] = $ov['updated_at'] ?? null;
        } else {
            $base['is_active']  = true;
            $base['updated_at'] = null;
        }

        return $base;
    }

    /**
     * Updates an email template's subject and HTML body.
     * Enforces strict mandatory placeholder validation (TEST-ADM-013).
     *
     * @throws NotFoundException If template not found
     * @throws ValidationException If required placeholder is missing or body is empty
     * @return array<string, mixed> Updated template data
     */
    public function updateTemplate(
        string|int $idOrKey,
        array $data,
        int $userId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI/Test'
    ): array {
        $targetKey = $this->resolveTemplateKey($idOrKey);
        if ($targetKey === null || !isset(self::CANONICAL_TEMPLATES[$targetKey])) {
            throw new NotFoundException("Email template [{$idOrKey}] not found.");
        }

        $tplDef = self::CANONICAL_TEMPLATES[$targetKey];

        $subject  = trim((string)($data['subject'] ?? ''));
        $bodyHtml = trim((string)($data['body_html'] ?? $data['body'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

        $errors = [];
        if ($subject === '') {
            $errors['subject'] = 'Subject line is required.';
        }
        if ($bodyHtml === '') {
            $errors['body_html'] = 'Template body HTML is required.';
        }

        // Neutralize CRLF injection in subject
        $cleanSubject = str_replace(["\r", "\n"], ' ', $subject);

        // Check mandatory placeholders in body_html OR subject
        $combinedText = $cleanSubject . ' ' . $bodyHtml;
        foreach ($tplDef['mandatory_placeholders'] as $mandatoryTag) {
            if (!str_contains($combinedText, $mandatoryTag)) {
                $errors['placeholders'] = "Mandatory placeholder {$mandatoryTag} is missing from template body.";
                break;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed for email template update.', $errors);
        }

        $pdo = $this->connection->getPdo();
        $settingKey = 'template_' . $targetKey;

        // Fetch previous state for audit diff
        $prevData = $this->getTemplate($targetKey);

        $payload = [
            'subject'    => $cleanSubject,
            'body_html'  => $bodyHtml,
            'is_active'  => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $jsonVal = json_encode($payload, JSON_THROW_ON_ERROR);

        // Check if row already exists in system_settings
        $checkStmt = $pdo->prepare("SELECT `id` FROM `system_settings` WHERE `setting_group` = 'email_templates' AND `setting_key` = :key");
        $checkStmt->execute(['key' => $settingKey]);
        $existingId = $checkStmt->fetchColumn();
        $checkStmt->closeCursor();

        if ($existingId !== false) {
            $updateStmt = $pdo->prepare(
                "UPDATE `system_settings` 
                 SET `setting_value` = :val, `updated_by_user_id` = :uid, `updated_at` = CURRENT_TIMESTAMP 
                 WHERE `id` = :id"
            );
            $updateStmt->execute([
                'val' => $jsonVal,
                'uid' => $userId,
                'id'  => (int)$existingId,
            ]);
            $updateStmt->closeCursor();
        } else {
            $insertStmt = $pdo->prepare(
                "INSERT INTO `system_settings` 
                 (`setting_group`, `setting_key`, `setting_value`, `is_secret`, `description`, `updated_by_user_id`, `updated_at`) 
                 VALUES ('email_templates', :key, :val, 0, :desc, :uid, CURRENT_TIMESTAMP)"
            );
            $insertStmt->execute([
                'key'  => $settingKey,
                'val'  => $jsonVal,
                'desc' => $tplDef['title'] . ' Customization',
                'uid'  => $userId,
            ]);
            $insertStmt->closeCursor();
        }

        // Cryptographically chained audit logging
        try {
            $this->auditLogger->log(
                $pdo,
                'EMAIL',
                'EMAIL_TEMPLATE_UPDATED',
                $userId,
                $ipAddress,
                $userAgent,
                'email_template',
                $tplDef['id'],
                [
                    'template_key' => $targetKey,
                    'before'       => [
                        'subject'   => $prevData['subject'],
                        'is_active' => $prevData['is_active'],
                    ],
                    'after'        => [
                        'subject'   => $cleanSubject,
                        'is_active' => $isActive,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            // Log but don't fail closed if audit fails on reading
        }

        return $this->getTemplate($targetKey);
    }

    /**
     * Dispatches a rendered test email for the given template to an administrator.
     *
     * @throws NotFoundException If template does not exist
     * @throws ValidationException If destination email is invalid
     * @return array{success: bool, recipient: string, subject: string, message: string}
     */
    public function sendTestEmail(
        string|int $idOrKey,
        string $targetEmail,
        int $userId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI/Test'
    ): array {
        $targetKey = $this->resolveTemplateKey($idOrKey);
        if ($targetKey === null || !isset(self::CANONICAL_TEMPLATES[$targetKey])) {
            throw new NotFoundException("Email template [{$idOrKey}] not found.");
        }

        $cleanEmail = trim($targetEmail);
        if ($cleanEmail === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid destination email for diagnostic test send.', [
                'email' => 'A valid email address is required.',
            ]);
        }

        $tpl = $this->getTemplate($targetKey);

        // Compile sample payload satisfying all placeholders
        $mockPayload = $this->generateSamplePayload($targetKey);
        $renderedSubject = $this->renderString($tpl['subject'], $mockPayload);
        $renderedBody    = $this->renderString($tpl['body_html'], $mockPayload);

        $sent = $this->transport->send(
            $cleanEmail,
            'Test Administrator',
            $renderedSubject,
            $renderedBody,
            ['X-Voak-Diagnostic-Test' => '1']
        );

        $pdo = $this->connection->getPdo();
        try {
            $this->auditLogger->log(
                $pdo,
                'EMAIL',
                'EMAIL_TEST_SENT',
                $userId,
                $ipAddress,
                $userAgent,
                'email_template',
                $tpl['id'],
                [
                    'template_key' => $targetKey,
                    'recipient'    => $cleanEmail,
                    'success'      => $sent,
                    'error'        => $sent ? null : $this->transport->getLastError(),
                ]
            );
        } catch (\Throwable $e) {
            // Compensate
        }

        if (!$sent) {
            $err = $this->transport->getLastError() ?: 'SMTP transmission failed.';
            return [
                'success'   => false,
                'recipient' => $cleanEmail,
                'subject'   => $renderedSubject,
                'message'   => "Test dispatch failed: {$err}",
            ];
        }

        return [
            'success'   => true,
            'recipient' => $cleanEmail,
            'subject'   => $renderedSubject,
            'message'   => "Test email successfully dispatched to {$cleanEmail}.",
        ];
    }

    // =========================================================================
    // SECTION 2: DURABLE OUTBOX & ATOMIC CLAIMING (ADR-006, email_queue)
    // =========================================================================

    /**
     * Staging helper: writes an email notification to the durable email_queue.
     *
     * @return int Enqueued record ID
     */
    public function enqueue(
        string $templateKey,
        string $recipientEmail,
        string $recipientName,
        array $payload,
        int $priority = 5
    ): int {
        $cleanEmail = trim($recipientEmail);
        $cleanName  = trim($recipientName);

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("Invalid recipient email [{$cleanEmail}] provided for enqueue.");
        }

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO `email_queue` 
             (`template_key`, `recipient_email`, `recipient_name`, `payload_json`, `status`, `attempts`, `max_attempts`, `created_at`) 
             VALUES (:tkey, :email, :name, :payload, 'QUEUED', 0, 3, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'tkey'    => $templateKey,
            'email'   => $cleanEmail,
            'name'    => $cleanName,
            'payload' => $jsonPayload,
        ]);
        $id = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        return $id;
    }

    /**
     * Tier 1 Bounded Fast-Path dispatch for urgent security events (ADR-006).
     * Attempts direct transport delivery with bounded socket timeouts.
     * If transport succeeds, logs record as SENT.
     * If transport fails or times out, safely falls back to QUEUED without crashing HTTP request.
     *
     * @return bool True if synchronously dispatched, false if queued for background retry
     */
    public function sendUrgentFastPath(
        string $templateKey,
        string $recipientEmail,
        string $recipientName,
        array $payload,
        int $userId = 1
    ): bool {
        $cleanEmail = trim($recipientEmail);
        $cleanName  = trim($recipientName);

        $tpl = $this->getTemplate($templateKey);
        $enrichedPayload = $this->enrichPayloadWithSystemSettings($payload);

        $renderedSubject = $this->renderString($tpl['subject'], $enrichedPayload);
        $renderedBody    = $this->renderString($tpl['body_html'], $enrichedPayload);

        // Attempt bounded synchronous delivery
        $sent = false;
        $errorMsg = null;
        try {
            $sent = $this->transport->send($cleanEmail, $cleanName, $renderedSubject, $renderedBody);
            if (!$sent) {
                $errorMsg = $this->transport->getLastError();
            }
        } catch (\Throwable $e) {
            $sent = false;
            $errorMsg = $e->getMessage();
        }

        $pdo = $this->connection->getPdo();
        $status = $sent ? 'SENT' : 'QUEUED';
        $attempts = $sent ? 1 : 0;
        $sentAt = $sent ? date('Y-m-d H:i:s') : null;
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $stmt = $pdo->prepare(
            "INSERT INTO `email_queue` 
             (`template_key`, `recipient_email`, `recipient_name`, `payload_json`, `status`, `attempts`, `max_attempts`, `error_message`, `sent_at`, `created_at`) 
             VALUES (:tkey, :email, :name, :payload, :status, :attempts, 3, :err, :sent_at, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'tkey'     => $templateKey,
            'email'    => $cleanEmail,
            'name'     => $cleanName,
            'payload'  => $jsonPayload,
            'status'   => $status,
            'attempts' => $attempts,
            'err'      => $errorMsg,
            'sent_at'  => $sentAt,
        ]);
        $stmt->closeCursor();

        return $sent;
    }

    /**
     * Lists outbox queue telemetry with filtering and pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listQueue(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $pdo = $this->connection->getPdo();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $status = strtoupper(trim((string)$filters['status']));
            if ($status === 'PENDING') {
                $status = 'QUEUED';
            }
            $where[] = '`status` = :status';
            $params['status'] = $status;
        }

        if (!empty($filters['template_key'])) {
            $where[] = '`template_key` = :tkey';
            $params['tkey'] = trim((string)$filters['template_key']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = '(`recipient_email` LIKE :s1 OR `recipient_name` LIKE :s2 OR `error_message` LIKE :s3)';
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
        }

        if (!empty($filters['date_from'])) {
            $where[] = '`created_at` >= :dfrom';
            $params['dfrom'] = trim((string)$filters['date_from']) . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = '`created_at` <= :dto';
            $params['dto'] = trim((string)$filters['date_to']) . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `email_queue` {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Data rows
        $sql = "SELECT * FROM `email_queue` {$whereClause} ORDER BY `id` DESC LIMIT :limit OFFSET :offset";
        $dataStmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();

        $rows = [];
        while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                try {
                    $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $payload = [];
                }
            }

            $rows[] = [
                'id'              => (int)$row['id'],
                'template_key'    => (string)$row['template_key'],
                'recipient_email' => (string)$row['recipient_email'],
                'recipient_name'  => (string)$row['recipient_name'],
                'status'          => (string)$row['status'],
                'attempts'        => (int)$row['attempts'],
                'max_attempts'    => (int)$row['max_attempts'],
                'error_message'   => $row['error_message'],
                'worker_id'       => $row['worker_id'],
                'claimed_at'      => $row['claimed_at'],
                'next_retry_at'   => $row['next_retry_at'],
                'sent_at'         => $row['sent_at'],
                'created_at'      => (string)$row['created_at'],
                'payload'         => $payload,
            ];
        }
        $dataStmt->closeCursor();

        $lastPage = max(1, (int)ceil($total / $perPage));

        return [
            'data'       => $rows,
            'pagination' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Fetches details of a single outbox queue item.
     *
     * @throws NotFoundException If outbox record not found
     * @return array<string, mixed>
     */
    public function getQueueItem(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `email_queue` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Outbox email record #{$id} not found.");
        }

        $payload = [];
        if (!empty($row['payload_json'])) {
            try {
                $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $payload = [];
            }
        }

        return [
            'id'              => (int)$row['id'],
            'template_key'    => (string)$row['template_key'],
            'recipient_email' => (string)$row['recipient_email'],
            'recipient_name'  => (string)$row['recipient_name'],
            'status'          => (string)$row['status'],
            'attempts'        => (int)$row['attempts'],
            'max_attempts'    => (int)$row['max_attempts'],
            'error_message'   => $row['error_message'],
            'worker_id'       => $row['worker_id'],
            'claimed_at'      => $row['claimed_at'],
            'next_retry_at'   => $row['next_retry_at'],
            'sent_at'         => $row['sent_at'],
            'created_at'      => (string)$row['created_at'],
            'payload'         => $payload,
        ];
    }

    /**
     * Resets a single failed/queued outbox record for immediate manual retry (TEST-ADM-013).
     *
     * @throws NotFoundException If queue ID not found
     * @return array<string, mixed>
     */
    public function retryMessage(
        int $queueId,
        int $userId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI/Admin'
    ): array {
        $pdo = $this->connection->getPdo();
        $current = $this->getQueueItem($queueId);

        $stmt = $pdo->prepare(
            "UPDATE `email_queue` 
             SET `status` = 'QUEUED', `attempts` = 0, `error_message` = NULL, 
                 `claimed_at` = NULL, `worker_id` = NULL, `next_retry_at` = NULL 
             WHERE `id` = :id"
        );
        $stmt->execute(['id' => $queueId]);
        $stmt->closeCursor();

        try {
            $this->auditLogger->log(
                $pdo,
                'EMAIL',
                'EMAIL_MANUAL_RETRY_TRIGGERED',
                $userId,
                $ipAddress,
                $userAgent,
                'email_queue',
                $queueId,
                [
                    'previous_status'   => $current['status'],
                    'previous_attempts' => $current['attempts'],
                    'recipient'         => $current['recipient_email'],
                    'template'          => $current['template_key'],
                ]
            );
        } catch (\Throwable $e) {
            // Log but don't fail closed on telemetry audit failure
        }

        return $this->getQueueItem($queueId);
    }

    /**
     * Bulk resets all failed outbox emails to QUEUED.
     *
     * @return int Count of records reset
     */
    public function retryAllFailed(
        int $userId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI/Admin'
    ): int {
        $pdo = $this->connection->getPdo();

        $countStmt = $pdo->query("SELECT COUNT(*) FROM `email_queue` WHERE `status` = 'FAILED'");
        $count = $countStmt ? (int)$countStmt->fetchColumn() : 0;
        if ($countStmt) {
            $countStmt->closeCursor();
        }

        if ($count === 0) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "UPDATE `email_queue` 
             SET `status` = 'QUEUED', `attempts` = 0, `error_message` = NULL, 
                 `claimed_at` = NULL, `worker_id` = NULL, `next_retry_at` = NULL 
             WHERE `status` = 'FAILED'"
        );
        $stmt->execute();
        $stmt->closeCursor();

        try {
            $this->auditLogger->log(
                $pdo,
                'EMAIL',
                'EMAIL_QUEUE_BULK_RETRY',
                $userId,
                $ipAddress,
                $userAgent,
                'email_queue',
                null,
                ['reset_count' => $count]
            );
        } catch (\Throwable $e) {
            // Log
        }

        return $count;
    }

    /**
     * Executes atomic batch claiming with stale recovery (TEST-WF-001).
     *
     * @return array<int, array<string, mixed>> Claimed items
     */
    public function claimBatch(int $limit = 20, ?string $workerId = null): array
    {
        $limit = max(1, min(100, $limit));
        $wid = $workerId ?: 'worker_' . bin2hex(random_bytes(6));
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // 1. Crash recovery: release claims stuck in PROCESSING > 5 minutes
        $staleSql = ($driver === 'sqlite')
            ? "UPDATE `email_queue` SET `status` = 'QUEUED', `claimed_at` = NULL, `worker_id` = NULL 
               WHERE `status` = 'PROCESSING' AND `claimed_at` < datetime('now', '-5 minutes')"
            : "UPDATE `email_queue` SET `status` = 'QUEUED', `claimed_at` = NULL, `worker_id` = NULL 
               WHERE `status` = 'PROCESSING' AND `claimed_at` < (NOW() - INTERVAL 5 MINUTE)";
        $pdo->exec($staleSql);

        // 2. Atomic claim
        if ($driver === 'sqlite') {
            // In SQLite, select candidate IDs inside transaction, then update
            $pdo->beginTransaction();
            try {
                $candStmt = $pdo->prepare(
                    "SELECT `id` FROM `email_queue` 
                     WHERE (`status` = 'QUEUED' OR (`status` = 'FAILED' AND `attempts` < `max_attempts` AND (`next_retry_at` IS NULL OR `next_retry_at` <= datetime('now')))) 
                     ORDER BY `id` ASC LIMIT :lim"
                );
                $candStmt->bindValue('lim', $limit, \PDO::PARAM_INT);
                $candStmt->execute();
                $ids = $candStmt->fetchAll(\PDO::FETCH_COLUMN);
                $candStmt->closeCursor();

                if (!empty($ids)) {
                    $inClause = implode(',', array_map('intval', $ids));
                    $pdo->exec(
                        "UPDATE `email_queue` 
                         SET `status` = 'PROCESSING', `worker_id` = '{$wid}', `claimed_at` = datetime('now') 
                         WHERE `id` IN ({$inClause})"
                    );
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } else {
            // In MySQL 8.0, single atomic UPDATE with LIMIT
            $claimStmt = $pdo->prepare(
                "UPDATE `email_queue` 
                 SET `status` = 'PROCESSING', `worker_id` = :wid, `claimed_at` = NOW() 
                 WHERE (`status` = 'QUEUED' OR (`status` = 'FAILED' AND `attempts` < `max_attempts` AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW()))) 
                 LIMIT :lim"
            );
            $claimStmt->bindValue('wid', $wid, \PDO::PARAM_STR);
            $claimStmt->bindValue('lim', $limit, \PDO::PARAM_INT);
            $claimStmt->execute();
            $claimStmt->closeCursor();
        }

        // 3. Fetch rows claimed by this worker
        $fetchStmt = $pdo->prepare(
            "SELECT * FROM `email_queue` 
             WHERE `worker_id` = :wid AND `status` = 'PROCESSING' 
             ORDER BY `id` ASC"
        );
        $fetchStmt->execute(['wid' => $wid]);
        $claimedRows = $fetchStmt->fetchAll(\PDO::FETCH_ASSOC);
        $fetchStmt->closeCursor();

        $result = [];
        foreach ($claimedRows as $row) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                try {
                    $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $payload = [];
                }
            }
            $row['payload'] = $payload;
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Processes claimed queue items and attempts dispatch via transport.
     * Applies exponential backoff on transient errors and transitions to FAILED after max attempts.
     *
     * @return array{processed: int, sent: int, failed: int, worker_id: string}
     */
    public function processQueueBatch(int $limit = 20, ?string $workerId = null): array
    {
        $wid = $workerId ?: 'worker_' . bin2hex(random_bytes(6));
        $claimed = $this->claimBatch($limit, $wid);

        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $processed = 0;
        $sentCount = 0;
        $failCount = 0;

        foreach ($claimed as $item) {
            $processed++;
            $itemId       = (int)$item['id'];
            $templateKey  = (string)$item['template_key'];
            $toEmail      = (string)$item['recipient_email'];
            $toName       = (string)$item['recipient_name'];
            $attempts     = (int)$item['attempts'];
            $maxAttempts  = (int)$item['max_attempts'];
            $payload      = (array)($item['payload'] ?? []);

            $tpl = null;
            try {
                $tpl = $this->getTemplate($templateKey);
            } catch (\Throwable $e) {
                // If template missing, fallback to default or generic
                $tpl = self::CANONICAL_TEMPLATES['tmpl_contact_ack'] ?? null;
            }

            $subject = $tpl ? $tpl['subject'] : 'Notification from Voak Health Care';
            $bodyHtml = $tpl ? $tpl['body_html'] : '<p>Notification message.</p>';

            $enriched = $this->enrichPayloadWithSystemSettings($payload);
            $renderedSubject = $this->renderString($subject, $enriched);
            $renderedBody    = $this->renderString($bodyHtml, $enriched);

            $dispatchOk = false;
            $errorMsg   = null;

            try {
                $dispatchOk = $this->transport->send($toEmail, $toName, $renderedSubject, $renderedBody);
                if (!$dispatchOk) {
                    $errorMsg = $this->transport->getLastError() ?: 'Transport rejection';
                }
            } catch (\Throwable $e) {
                $dispatchOk = false;
                $errorMsg = $e->getMessage();
            }

            if ($dispatchOk) {
                $sentCount++;
                $stmt = $pdo->prepare(
                    "UPDATE `email_queue` 
                     SET `status` = 'SENT', `sent_at` = CURRENT_TIMESTAMP, 
                         `error_message` = NULL, `worker_id` = NULL, `claimed_at` = NULL 
                     WHERE `id` = :id"
                );
                $stmt->execute(['id' => $itemId]);
                $stmt->closeCursor();
            } else {
                $failCount++;
                $newAttempts = $attempts + 1;
                $newStatus = ($newAttempts >= $maxAttempts) ? 'FAILED' : 'FAILED'; // remains FAILED or QUEUED with next_retry_at

                // Exponential backoff: (attempts * 5) minutes
                $delayMinutes = $newAttempts * 5;

                if ($driver === 'sqlite') {
                    $retrySql = "UPDATE `email_queue` 
                                 SET `status` = :st, `attempts` = :att, `error_message` = :err, 
                                     `next_retry_at` = datetime('now', '+{$delayMinutes} minutes'), 
                                     `worker_id` = NULL, `claimed_at` = NULL 
                                 WHERE `id` = :id";
                } else {
                    $retrySql = "UPDATE `email_queue` 
                                 SET `status` = :st, `attempts` = :att, `error_message` = :err, 
                                     `next_retry_at` = NOW() + INTERVAL {$delayMinutes} MINUTE, 
                                     `worker_id` = NULL, `claimed_at` = NULL 
                                 WHERE `id` = :id";
                }

                $stmt = $pdo->prepare($retrySql);
                $stmt->execute([
                    'st'  => $newStatus,
                    'att' => $newAttempts,
                    'err' => $errorMsg,
                    'id'  => $itemId,
                ]);
                $stmt->closeCursor();
            }
        }

        return [
            'processed' => $processed,
            'sent'      => $sentCount,
            'failed'    => $failCount,
            'worker_id' => $wid,
        ];
    }

    // =========================================================================
    // SECTION 3: VARIABLE INTERPOLATION & SYSTEM SETTINGS ENRICHMENT
    // =========================================================================

    /**
     * Replaces variable placeholder tags ({{key}}) with HTML-escaped values.
     */
    public function renderString(string $template, array $data): string
    {
        $replace = [];
        foreach ($data as $key => $val) {
            $tag = '{{' . trim((string)$key, '{}') . '}}';
            $safeVal = is_scalar($val) ? (string)$val : json_encode($val);
            // Escape HTML entities unless key ends with _html or _raw
            if (!str_ends_with((string)$key, '_html') && !str_ends_with((string)$key, '_raw')) {
                $safeVal = htmlspecialchars($safeVal, ENT_QUOTES, 'UTF-8');
            }
            $replace[$tag] = $safeVal;
        }

        return strtr($template, $replace);
    }

    /**
     * Enriches outgoing email payloads with legal footer variables from system_settings.
     * Enforces TEST-SEC-005: all emails contain legal_entity_name and registration_number.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enrichPayloadWithSystemSettings(array $payload): array
    {
        $pdo = $this->connection->getPdo();

        $defaults = [
            'legal_entity_name'   => 'Voak Health Care Pty Ltd',
            'registration_number' => 'ABN 45 678 912 345',
            'registered_address'  => 'Level 28, 161 Castlereagh Street, Sydney NSW 2000',
            'privacy_policy_url'  => 'https://voakhealthcare.com/privacy-policy',
        ];

        try {
            $stmt = $pdo->query(
                "SELECT `setting_key`, `setting_value` FROM `system_settings` 
                 WHERE `setting_group` IN ('general', 'legal', 'contact')"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $k = (string)$row['setting_key'];
                    $v = (string)$row['setting_value'];
                    if ($k === 'site_name' && $v !== '') {
                        $defaults['legal_entity_name'] = $v;
                    }
                    if ($k === 'company_abn' && $v !== '') {
                        $defaults['registration_number'] = 'ABN ' . $v;
                    }
                    if ($k === 'primary_address' && $v !== '') {
                        $defaults['registered_address'] = $v;
                    }
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            // Use defaults on failure
        }

        return array_merge($defaults, $payload);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function resolveTemplateKey(string|int $idOrKey): ?string
    {
        if (is_numeric($idOrKey)) {
            $targetId = (int)$idOrKey;
            foreach (self::CANONICAL_TEMPLATES as $key => $def) {
                if ($def['id'] === $targetId) {
                    return $key;
                }
            }
        }

        $str = trim((string)$idOrKey);
        if (isset(self::CANONICAL_TEMPLATES[$str])) {
            return $str;
        }

        // Try matching slug
        foreach (self::CANONICAL_TEMPLATES as $key => $def) {
            if ($def['slug'] === $str) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadTemplateOverrides(): array
    {
        $pdo = $this->connection->getPdo();
        $overrides = [];

        try {
            $stmt = $pdo->query(
                "SELECT `setting_key`, `setting_value`, `updated_at` 
                 FROM `system_settings` 
                 WHERE `setting_group` = 'email_templates'"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $key = str_replace('template_', '', (string)$row['setting_key']);
                    $decoded = json_decode((string)$row['setting_value'], true);
                    if (is_array($decoded)) {
                        $decoded['updated_at'] = $row['updated_at'];
                        $overrides[$key] = $decoded;
                    }
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            // Ignore if settings table uninitialized
        }

        return $overrides;
    }

    private function generateSamplePayload(string $templateKey): array
    {
        $base = [
            'contact_name'          => 'Dr. Jane Mitchell',
            'organization_name'     => 'St. Vincent Healthcare Network',
            'work_email'            => 'jane.mitchell@svhn.example.com',
            'phone'                 => '+61 2 9382 1111',
            'country_code'          => 'AU',
            'inquiry_type'          => 'Clinical Workforce Solutions',
            'message'               => 'Requesting operational overview for emergency department staffing.',
            'ref_no'                => 'INQ-20261023-7741',
            'submission_datetime'   => date('Y-m-d H:i:s T'),
            'inquiry_summary'       => 'Requesting operational overview for emergency department staffing.',
            'executive_name'        => 'Marcus Vance',
            'alliance_type'         => 'Hospital Network Alliance',
            'proposed_scope'        => 'Tertiary clinical second opinion and telehealth triage integration.',
            'facility_type'         => 'Metropolitan Tertiary Hospital',
            'timeline'              => 'Q1 2027',
            'budget_band'           => '$250k - $500k',
            'first_name'            => 'Sarah',
            'job_title'             => 'Clinical Governance Lead',
            'department'            => 'Operations',
            'submission_date'       => date('Y-m-d'),
            'full_name'             => 'Alexander Vance',
            'assigned_role'         => 'Website Administrator',
            'invitation_link'       => 'https://voakhealthcare.com/admin/activate?token=sample_invite_token_89bc',
            'expires_in_hours'      => '24',
            'reset_link'            => 'https://voakhealthcare.com/admin/password-reset?token=sample_reset_token_653a',
            'request_ip'            => '203.0.113.195',
            'request_datetime'      => date('Y-m-d H:i:s T'),
            'incident_code'         => 'SEC-20261023-9912',
            'event_type'            => 'Consecutive Authentication Failure Lockout',
            'target_user'           => 'admin@voak.com.au',
            'source_ip'             => '198.51.100.42',
            'user_agent'            => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'timestamp'             => date('Y-m-d H:i:s T'),
            'admin_crm_url'         => 'https://voakhealthcare.com/admin/inquiries/contact/1',
        ];

        return $this->enrichPayloadWithSystemSettings($base);
    }
}
