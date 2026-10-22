<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;

/**
 * Enterprise Homepage Configuration Service.
 * Manages hero section copy, validated call-to-actions, proof metrics, and bento layout.
 */
class HomepageService
{
    private const DISALLOWED_SCHEMES = ['javascript:', 'data:', 'vbscript:', 'file:'];

    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Validates and normalizes CTA URLs.
     *
     * @throws BadRequestException If URL uses unsafe protocol or invalid syntax
     */
    public function validateCtaUrl(string $url, string $fieldName = 'cta_url'): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            throw new BadRequestException("The {$fieldName} cannot be empty.");
        }

        if (strlen($trimmed) > 191) {
            throw new BadRequestException("The {$fieldName} cannot exceed 191 characters.");
        }

        $lower = strtolower($trimmed);

        // Reject dangerous URI schemes
        foreach (self::DISALLOWED_SCHEMES as $scheme) {
            if (str_starts_with($lower, $scheme) || str_contains($lower, $scheme)) {
                throw new BadRequestException("The {$fieldName} contains an unsafe or forbidden URI scheme.");
            }
        }

        // Reject control characters and newlines
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed)) {
            throw new BadRequestException("The {$fieldName} contains invalid control characters.");
        }

        // Relative internal URL (e.g., /services or /contact)
        if (str_starts_with($trimmed, '/')) {
            if (str_contains($trimmed, '..')) {
                throw new BadRequestException("The {$fieldName} cannot contain path traversal sequences.");
            }
            return $trimmed;
        }

        // Absolute URL
        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new BadRequestException("The {$fieldName} must be a valid relative path (/path) or absolute HTTPS URL.");
        }

        $scheme = strtolower((string)$parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new BadRequestException("The {$fieldName} must use HTTP or HTTPS protocol.");
        }

        return $trimmed;
    }

    /**
     * Retrieves the current active homepage configuration.
     *
     * @return array<string, mixed>
     */
    public function getActiveConfig(): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT * FROM `homepage_configs` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1");
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        if ($stmt) {
            $stmt->closeCursor();
        }

        if (!$row) {
            // Return safe canonical default configuration if none exists
            return [
                'id' => 0,
                'hero_headline' => 'Next-Generation Workforce & Clinical Governance for Australian Healthcare',
                'hero_subheadline' => 'Empowering acute hospitals, regional health services, and private healthcare networks with credentialed specialists, clinical workforce solutions, and automated compliance.',
                'hero_primary_cta_text' => 'Partner With Us',
                'hero_primary_cta_url' => '/contact',
                'hero_secondary_cta_text' => 'Explore Solutions',
                'hero_secondary_cta_url' => '/services',
                'bento_order' => [
                    ['id' => 'workforce', 'title' => 'Clinical Workforce Solutions', 'order' => 1],
                    ['id' => 'telehealth', 'title' => 'Specialist Telehealth Services', 'order' => 2],
                    ['id' => 'compliance', 'title' => 'Digital Clinical Governance', 'order' => 3],
                    ['id' => 'analytics', 'title' => 'Workforce Demand Analytics', 'order' => 4],
                ],
                'proof_metrics' => [
                    ['label' => 'Hospital Networks Supported', 'value' => '120+'],
                    ['label' => 'Credentialed Specialists', 'value' => '450+'],
                    ['label' => 'Clinical Hours Delivered', 'value' => '1.2M+'],
                    ['label' => 'SLA Fulfillment Rate', 'value' => '99.8%'],
                ],
                'is_active' => 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'etag' => '"' . hash('sha256', 'default-homepage-config') . '"',
            ];
        }

        $bento = is_string($row['bento_order_json']) ? json_decode($row['bento_order_json'], true) : $row['bento_order_json'];
        $metrics = is_string($row['proof_metrics_json']) ? json_decode($row['proof_metrics_json'], true) : $row['proof_metrics_json'];

        $config = [
            'id' => (int)$row['id'],
            'hero_headline' => (string)$row['hero_headline'],
            'hero_subheadline' => (string)$row['hero_subheadline'],
            'hero_primary_cta_text' => (string)$row['hero_primary_cta_text'],
            'hero_primary_cta_url' => (string)$row['hero_primary_cta_url'],
            'hero_secondary_cta_text' => (string)$row['hero_secondary_cta_text'],
            'hero_secondary_cta_url' => (string)$row['hero_secondary_cta_url'],
            'bento_order' => is_array($bento) ? $bento : [],
            'proof_metrics' => is_array($metrics) ? $metrics : [],
            'is_active' => (int)$row['is_active'],
            'updated_by_user_id' => (int)$row['updated_by_user_id'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];

        $config['etag'] = $this->calculateEtag($config);

        return $config;
    }

    /**
     * Updates or creates the homepage configuration.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed> Updated configuration
     */
    public function updateConfig(array $data, int $userId): array
    {
        $headline = trim((string)($data['hero_headline'] ?? ''));
        $subheadline = trim((string)($data['hero_subheadline'] ?? ''));
        $primaryCtaText = trim((string)($data['hero_primary_cta_text'] ?? ($data['primary_cta_text'] ?? '')));
        $primaryCtaUrl = trim((string)($data['hero_primary_cta_url'] ?? ($data['primary_cta_link'] ?? '')));
        $secondaryCtaText = trim((string)($data['hero_secondary_cta_text'] ?? ($data['secondary_cta_text'] ?? '')));
        $secondaryCtaUrl = trim((string)($data['hero_secondary_cta_url'] ?? ($data['secondary_cta_link'] ?? '')));

        $errors = [];
        if ($headline === '') {
            $errors['hero_headline'] = ['Hero headline is required.'];
        } elseif (strlen($headline) > 255) {
            $errors['hero_headline'] = ['Hero headline cannot exceed 255 characters.'];
        }

        if ($subheadline === '') {
            $errors['hero_subheadline'] = ['Hero subheadline is required.'];
        }

        if ($primaryCtaText === '') {
            $errors['hero_primary_cta_text'] = ['Primary CTA text is required.'];
        } elseif (strlen($primaryCtaText) > 50) {
            $errors['hero_primary_cta_text'] = ['Primary CTA text cannot exceed 50 characters.'];
        }

        if ($secondaryCtaText === '') {
            $errors['hero_secondary_cta_text'] = ['Secondary CTA text is required.'];
        } elseif (strlen($secondaryCtaText) > 50) {
            $errors['hero_secondary_cta_text'] = ['Secondary CTA text cannot exceed 50 characters.'];
        }

        if (!empty($errors)) {
            throw new ValidationException("Homepage configuration validation failed.", $errors);
        }

        $validatedPrimaryUrl = $this->validateCtaUrl($primaryCtaUrl, 'hero_primary_cta_url');
        $validatedSecondaryUrl = $this->validateCtaUrl($secondaryCtaUrl, 'hero_secondary_cta_url');

        // Sanitize copy
        $cleanHeadline = strip_tags($headline);
        $cleanSubheadline = strip_tags($subheadline);

        // Bento layout / Value propositions
        $bentoData = $data['bento_order'] ?? ($data['value_propositions'] ?? ($data['value_props_json'] ?? []));
        if (is_string($bentoData)) {
            $decoded = json_decode($bentoData, true);
            $bentoData = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($bentoData)) {
            $bentoData = [];
        }

        // Proof metrics / Statistics
        $metricsData = $data['proof_metrics'] ?? ($data['statistics'] ?? ($data['stats_json'] ?? []));
        if (is_string($metricsData)) {
            $decoded = json_decode($metricsData, true);
            $metricsData = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($metricsData)) {
            $metricsData = [];
        }

        $bentoJson = json_encode($bentoData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $metricsJson = json_encode($metricsData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $pdo = $this->connection->getPdo();

        $pdo->beginTransaction();
        try {
            // Check if active config exists
            $stmt = $pdo->query("SELECT `id` FROM `homepage_configs` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1");
            $existing = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
            if ($stmt) {
                $stmt->closeCursor();
            }

            if ($existing) {
                $id = (int)$existing['id'];
                $updateStmt = $pdo->prepare("UPDATE `homepage_configs` SET
                    `hero_headline` = :headline,
                    `hero_subheadline` = :subheadline,
                    `hero_primary_cta_text` = :p_text,
                    `hero_primary_cta_url` = :p_url,
                    `hero_secondary_cta_text` = :s_text,
                    `hero_secondary_cta_url` = :s_url,
                    `bento_order_json` = :bento,
                    `proof_metrics_json` = :metrics,
                    `updated_by_user_id` = :uid,
                    `updated_at` = CURRENT_TIMESTAMP
                    WHERE `id` = :id");
                $updateStmt->execute([
                    'headline'    => $cleanHeadline,
                    'subheadline' => $cleanSubheadline,
                    'p_text'      => $primaryCtaText,
                    'p_url'       => $validatedPrimaryUrl,
                    's_text'      => $secondaryCtaText,
                    's_url'       => $validatedSecondaryUrl,
                    'bento'       => $bentoJson,
                    'metrics'     => $metricsJson,
                    'uid'         => $userId,
                    'id'          => $id,
                ]);
                $updateStmt->closeCursor();
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO `homepage_configs` (
                    `hero_headline`, `hero_subheadline`,
                    `hero_primary_cta_text`, `hero_primary_cta_url`,
                    `hero_secondary_cta_text`, `hero_secondary_cta_url`,
                    `bento_order_json`, `proof_metrics_json`,
                    `is_active`, `updated_by_user_id`
                ) VALUES (
                    :headline, :subheadline,
                    :p_text, :p_url,
                    :s_text, :s_url,
                    :bento, :metrics,
                    1, :uid
                )");
                $insertStmt->execute([
                    'headline'    => $cleanHeadline,
                    'subheadline' => $cleanSubheadline,
                    'p_text'      => $primaryCtaText,
                    'p_url'       => $validatedPrimaryUrl,
                    's_text'      => $secondaryCtaText,
                    's_url'       => $validatedSecondaryUrl,
                    'bento'       => $bentoJson,
                    'metrics'     => $metricsJson,
                    'uid'         => $userId,
                ]);
                $id = (int)$pdo->lastInsertId();
                $insertStmt->closeCursor();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Post-commit audit logging
        $this->auditLogger->log(
            $pdo,
            'cms',
            'homepage_updated',
            $userId,
            '127.0.0.1',
            'CMS/HomepageService',
            'homepage_configs',
            $id,
            [
                'hero_headline' => $cleanHeadline,
                'primary_cta'   => $validatedPrimaryUrl,
                'secondary_cta' => $validatedSecondaryUrl,
            ]
        );

        return $this->getActiveConfig();
    }

    /**
     * Calculates an ETag representation for homepage configuration.
     *
     * @param array<string, mixed> $config
     */
    public function calculateEtag(array $config): string
    {
        $hashInput = ($config['id'] ?? 0) . '|'
            . ($config['updated_at'] ?? '') . '|'
            . ($config['hero_headline'] ?? '') . '|'
            . ($config['hero_primary_cta_url'] ?? '') . '|'
            . md5(json_encode($config['proof_metrics'] ?? [])) . '|'
            . md5(json_encode($config['bento_order'] ?? []));

        return '"' . hash('sha256', $hashInput) . '"';
    }
}
