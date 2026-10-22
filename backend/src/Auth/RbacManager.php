<?php

declare(strict_types=1);

namespace Voak\Health\Auth;

/**
 * Role-Based Access Control (RBAC) Management Engine.
 * Implements the 5 canonical roles and 45 granular permissions defined in
 * docs/phase-01/06_COMPLETE_RBAC_PERMISSION_MATRIX.md.
 */
class RbacManager
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_WEBSITE_ADMIN = 'website_admin';
    public const ROLE_CONTENT_EDITOR = 'content_editor';
    public const ROLE_BD_MANAGER = 'bd_manager';
    public const ROLE_HR_MANAGER = 'hr_manager';

    /**
     * Complete list of the 5 canonical administrative roles.
     */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN => [
            'name'        => 'Super Administrator',
            'description' => 'Unrestricted operational authority, audit access, and user administration.',
        ],
        self::ROLE_WEBSITE_ADMIN => [
            'name'        => 'Website Administrator',
            'description' => 'Content management, media optimization, general contact CRM, and SEO metadata.',
        ],
        self::ROLE_CONTENT_EDITOR => [
            'name'        => 'Content Editor',
            'description' => 'Drafting and editing editorial copy, articles, services, and media assets.',
        ],
        self::ROLE_BD_MANAGER => [
            'name'        => 'Business Development Manager',
            'description' => 'Triage of inbound commercial inquiries, partnerships, and client RFP tenders.',
        ],
        self::ROLE_HR_MANAGER => [
            'name'        => 'HR Manager',
            'description' => 'Careers management, job vacancies, candidate applications, and recruitment triage.',
        ],
    ];

    /**
     * Canonical 45 permissions taxonomy across all 16 administrative domains.
     *
     * @var array<string, array{name: string, module: string, description: string, roles: array<string>}>
     */
    public const PERMISSIONS = [
        // Authentication (3)
        'auth:login' => [
            'name' => 'Login to Admin Portal', 'module' => 'auth',
            'description' => 'Authenticate into administrative portal session',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR, self::ROLE_BD_MANAGER, self::ROLE_HR_MANAGER],
        ],
        'auth:mfa_verify' => [
            'name' => 'Verify Multi-Factor Challenge', 'module' => 'auth',
            'description' => 'Verify TOTP code or emergency recovery code during login',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR, self::ROLE_BD_MANAGER, self::ROLE_HR_MANAGER],
        ],
        'auth:password_reset' => [
            'name' => 'Execute Password Reset', 'module' => 'auth',
            'description' => 'Request and complete single-use password reset workflow',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR, self::ROLE_BD_MANAGER, self::ROLE_HR_MANAGER],
        ],

        // User Management (5)
        'user:list' => [
            'name' => 'View User Roster', 'module' => 'users',
            'description' => 'List all administrative user accounts',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'user:invite' => [
            'name' => 'Invite Administrator', 'module' => 'users',
            'description' => 'Provision and invite new administrator accounts',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'user:update_role' => [
            'name' => 'Update User Roles', 'module' => 'users',
            'description' => 'Modify role assignments for existing administrators',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'user:suspend' => [
            'name' => 'Suspend Administrator', 'module' => 'users',
            'description' => 'Deactivate or lock an administrative account',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'user:revoke_session' => [
            'name' => 'Revoke User Sessions', 'module' => 'users',
            'description' => 'Force termination of active administrator sessions',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],

        // Pages & Content (3)
        'page:read' => [
            'name' => 'View CMS Pages', 'module' => 'pages',
            'description' => 'Inspect page drafts and live content',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'page:update' => [
            'name' => 'Update CMS Pages', 'module' => 'pages',
            'description' => 'Edit page markdown content and metadata',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'page:rollback' => [
            'name' => 'Rollback Page Revision', 'module' => 'pages',
            'description' => 'Restore previous content revisions',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Homepage Layout (2)
        'hero:update' => [
            'name' => 'Update Hero Section', 'module' => 'homepage',
            'description' => 'Modify homepage hero copy and call to actions',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'bento:reorder' => [
            'name' => 'Reorder Bento Grid', 'module' => 'homepage',
            'description' => 'Modify order and prominence of modular bento cards',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],

        // Services Catalog (4)
        'service:create' => [
            'name' => 'Create Service', 'module' => 'services',
            'description' => 'Create new healthcare service offering',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'service:update' => [
            'name' => 'Update Service', 'module' => 'services',
            'description' => 'Edit service descriptions and pricing tiers',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'service:publish' => [
            'name' => 'Publish Service', 'module' => 'services',
            'description' => 'Publish or unpublish service offerings on public site',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],
        'service:delete' => [
            'name' => 'Delete Service', 'module' => 'services',
            'description' => 'Archive or delete service offerings',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Industries (3)
        'industry:create' => [
            'name' => 'Create Industry Focus', 'module' => 'industries',
            'description' => 'Create new industry sector vertical',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'industry:update' => [
            'name' => 'Update Industry Focus', 'module' => 'industries',
            'description' => 'Edit industry sector descriptions and case data',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'industry:delete' => [
            'name' => 'Delete Industry Focus', 'module' => 'industries',
            'description' => 'Archive or remove industry sector entry',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Media Library (3)
        'media:upload' => [
            'name' => 'Upload Media Assets', 'module' => 'media',
            'description' => 'Upload images and document attachments',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'media:update_alt' => [
            'name' => 'Update Media Alt Text', 'module' => 'media',
            'description' => 'Modify accessibility alt tags and titles',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'media:delete' => [
            'name' => 'Delete Media Asset', 'module' => 'media',
            'description' => 'Delete media items from repository storage',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Contact Enquiries (4)
        'contact:list' => [
            'name' => 'View Contact Inquiries', 'module' => 'contact',
            'description' => 'View submitted public contact forms',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'contact:update_status' => [
            'name' => 'Update Contact Status', 'module' => 'contact',
            'description' => 'Mark inquiry as reviewed, contacted, or closed',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'contact:add_note' => [
            'name' => 'Add Contact Notes', 'module' => 'contact',
            'description' => 'Add internal CRM notes to contact record',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'contact:export_csv' => [
            'name' => 'Export Contact Inquiries', 'module' => 'contact',
            'description' => 'Download CSV export of contact submissions',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_BD_MANAGER],
        ],

        // Partnership CRM (4)
        'partner:list' => [
            'name' => 'View Partnerships', 'module' => 'partners',
            'description' => 'View B2B healthcare partnership pipeline',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'partner:update_stage' => [
            'name' => 'Update Partner Stage', 'module' => 'partners',
            'description' => 'Progress enterprise partner through deal stages',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'partner:add_note' => [
            'name' => 'Add Partner Notes', 'module' => 'partners',
            'description' => 'Record commercial meeting notes and negotiations',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'partner:export_csv' => [
            'name' => 'Export Partner Pipeline', 'module' => 'partners',
            'description' => 'Export enterprise partnership pipeline to CSV',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],

        // Proposal Desk (4)
        'proposal:list' => [
            'name' => 'View Commercial Proposals', 'module' => 'proposals',
            'description' => 'View commercial RFP tender submissions',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'proposal:update_status' => [
            'name' => 'Update Proposal Status', 'module' => 'proposals',
            'description' => 'Update review status of enterprise tender',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'proposal:download_tender' => [
            'name' => 'Download RFP Tender File', 'module' => 'proposals',
            'description' => 'Download client-uploaded tender attachments',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],
        'proposal:export_csv' => [
            'name' => 'Export Proposals Data', 'module' => 'proposals',
            'description' => 'Download commercial proposal data in CSV format',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_BD_MANAGER],
        ],

        // Email Templates (3)
        'email:read' => [
            'name' => 'View Email Templates', 'module' => 'email',
            'description' => 'Inspect transactional email templates',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],
        'email:update' => [
            'name' => 'Update Email Templates', 'module' => 'email',
            'description' => 'Edit email template subject and HTML copy',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],
        'email:test_send' => [
            'name' => 'Send Test Email', 'module' => 'email',
            'description' => 'Trigger test dispatch of transactional email',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Careers & Jobs (3)
        'job:create' => [
            'name' => 'Create Job Opening', 'module' => 'careers',
            'description' => 'Create clinical or operational job vacancy',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_HR_MANAGER],
        ],
        'job:update' => [
            'name' => 'Update Job Opening', 'module' => 'careers',
            'description' => 'Edit job description, requirements, or department',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_HR_MANAGER],
        ],
        'job:close' => [
            'name' => 'Close Job Vacancy', 'module' => 'careers',
            'description' => 'Close or archive job posting',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_HR_MANAGER],
        ],

        // Recruitment Triage (4)
        'candidate:list' => [
            'name' => 'View Candidate Applications', 'module' => 'recruitment',
            'description' => 'View submitted job applications and candidate PII',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_HR_MANAGER],
        ],
        'candidate:update_stage' => [
            'name' => 'Update Candidate Stage', 'module' => 'recruitment',
            'description' => 'Update recruitment workflow stage (e.g. Interview, Offered)',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_HR_MANAGER],
        ],
        'candidate:download_cv' => [
            'name' => 'Download Candidate Resume', 'module' => 'recruitment',
            'description' => 'Download uploaded candidate resume/CV document',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_HR_MANAGER],
        ],
        'candidate:export_csv' => [
            'name' => 'Export Applicant Roster', 'module' => 'recruitment',
            'description' => 'Download CSV roster of job applicants',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_HR_MANAGER],
        ],

        // Case Studies (2)
        'casestudy:create' => [
            'name' => 'Create Case Study', 'module' => 'casestudies',
            'description' => 'Draft new healthcare partnership case study',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'casestudy:publish' => [
            'name' => 'Publish Case Study', 'module' => 'casestudies',
            'description' => 'Publish verified client case study to public site',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // Insights & Blog (3)
        'article:create' => [
            'name' => 'Draft Blog Article', 'module' => 'articles',
            'description' => 'Create healthcare industry insight article',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'article:update' => [
            'name' => 'Update Blog Article', 'module' => 'articles',
            'description' => 'Edit article content, summary, and author bio',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],
        'article:publish' => [
            'name' => 'Publish Blog Article', 'module' => 'articles',
            'description' => 'Publish article live on public platform',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN, self::ROLE_CONTENT_EDITOR],
        ],

        // SEO & Routing (2)
        'seo:update_meta' => [
            'name' => 'Update SEO Meta', 'module' => 'seo',
            'description' => 'Edit OpenGraph, JSON-LD, and page meta tags',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],
        'seo:rebuild_sitemap' => [
            'name' => 'Rebuild XML Sitemap', 'module' => 'seo',
            'description' => 'Regenerate sitemap.xml for search engines',
            'roles' => [self::ROLE_SUPER_ADMIN, self::ROLE_WEBSITE_ADMIN],
        ],

        // System Settings (3)
        'settings:read' => [
            'name' => 'View System Settings', 'module' => 'settings',
            'description' => 'View global system configuration parameters',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'settings:update' => [
            'name' => 'Update System Settings', 'module' => 'settings',
            'description' => 'Modify platform settings, SMTP, and corporate variables',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'settings:smtp_test' => [
            'name' => 'Test SMTP Transport', 'module' => 'settings',
            'description' => 'Test outbound SMTP relay connectivity',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],

        // Audit Logs (2)
        'audit:read' => [
            'name' => 'View Audit Ledger', 'module' => 'audit',
            'description' => 'Inspect immutable cryptographic audit trail',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
        'audit:export' => [
            'name' => 'Export Audit Ledger', 'module' => 'audit',
            'description' => 'Export cryptographically chained audit events to CSV/JSON',
            'roles' => [self::ROLE_SUPER_ADMIN],
        ],
    ];

    /**
     * Seeds all 5 canonical roles and 45 permissions into the database idempotently.
     */
    public function seedRbac(\PDO $pdo): void
    {
        // 1. Seed Roles
        $roleIds = [];
        $roleStmt = $pdo->prepare("SELECT `id`, `slug` FROM `roles`");
        $roleStmt->execute();
        while ($row = $roleStmt->fetch(\PDO::FETCH_ASSOC)) {
            $roleIds[$row['slug']] = (int)$row['id'];
        }
        $roleStmt->closeCursor();

        $insertRole = $pdo->prepare(
            "INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES (:name, :slug, :desc, 1)"
        );
        foreach (self::ROLES as $slug => $def) {
            if (!isset($roleIds[$slug])) {
                $insertRole->execute([
                    'name' => $def['name'],
                    'slug' => $slug,
                    'desc' => $def['description'],
                ]);
                $roleIds[$slug] = (int)$pdo->lastInsertId();
                $insertRole->closeCursor();
            }
        }

        // 2. Seed Permissions
        $permissionIds = [];
        $permStmt = $pdo->prepare("SELECT `id`, `slug` FROM `permissions`");
        $permStmt->execute();
        while ($row = $permStmt->fetch(\PDO::FETCH_ASSOC)) {
            $permissionIds[$row['slug']] = (int)$row['id'];
        }
        $permStmt->closeCursor();

        $insertPerm = $pdo->prepare(
            "INSERT INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES (:name, :slug, :module, :desc)"
        );
        foreach (self::PERMISSIONS as $slug => $def) {
            if (!isset($permissionIds[$slug])) {
                $insertPerm->execute([
                    'name'   => $def['name'],
                    'slug'   => $slug,
                    'module' => $def['module'],
                    'desc'   => $def['description'],
                ]);
                $permissionIds[$slug] = (int)$pdo->lastInsertId();
                $insertPerm->closeCursor();
            }
        }

        // 3. Seed Role Permissions
        $checkMapping = $pdo->prepare(
            "SELECT COUNT(*) FROM `role_permissions` WHERE `role_id` = :rid AND `permission_id` = :pid"
        );
        $insertMapping = $pdo->prepare(
            "INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (:rid, :pid)"
        );

        foreach (self::PERMISSIONS as $permSlug => $def) {
            $permId = $permissionIds[$permSlug];
            foreach ($def['roles'] as $roleSlug) {
                if (isset($roleIds[$roleSlug])) {
                    $roleId = $roleIds[$roleSlug];
                    $checkMapping->execute(['rid' => $roleId, 'pid' => $permId]);
                    $exists = (int)$checkMapping->fetchColumn() > 0;
                    $checkMapping->closeCursor();

                    if (!$exists) {
                        $insertMapping->execute(['rid' => $roleId, 'pid' => $permId]);
                        $insertMapping->closeCursor();
                    }
                }
            }
        }
    }

    /**
     * Resolves all active role slugs assigned to a specific user.
     *
     * @return array<string>
     */
    public function getUserRoles(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            "SELECT r.`slug` 
             FROM `roles` r
             INNER JOIN `user_roles` ur ON ur.`role_id` = r.`id`
             WHERE ur.`user_id` = :uid"
        );
        $stmt->execute(['uid' => $userId]);
        $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        return array_map('strval', $roles);
    }

    /**
     * Resolves all active permission slugs assigned to a specific user.
     * Super Admin inherently possesses all 45 permissions.
     *
     * @return array<string>
     */
    public function getUserPermissions(\PDO $pdo, int $userId): array
    {
        $roles = $this->getUserRoles($pdo, $userId);

        if (in_array(self::ROLE_SUPER_ADMIN, $roles, true)) {
            return array_keys(self::PERMISSIONS);
        }

        if (empty($roles)) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT DISTINCT p.`slug`
             FROM `permissions` p
             INNER JOIN `role_permissions` rp ON rp.`permission_id` = p.`id`
             INNER JOIN `user_roles` ur ON ur.`role_id` = rp.`role_id`
             WHERE ur.`user_id` = :uid"
        );
        $stmt->execute(['uid' => $userId]);
        $permissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        return array_map('strval', $permissions);
    }

    /**
     * Canonical aliases mapping dot notation to colon notation.
     */
    public const PERMISSION_ALIASES = [
        'pages.view'     => ['page:read', 'pages.view'],
        'pages.create'   => ['page:update', 'pages.create'],
        'pages.edit'     => ['page:update', 'pages.edit'],
        'pages.publish'  => ['page:rollback', 'pages.publish'],
        'pages.delete'   => ['page:rollback', 'pages.delete'],
        'settings.view'  => ['settings:read', 'settings.view'],
        'settings.edit'  => ['settings:update', 'settings.edit'],
        'services.view'    => ['service:update', 'service:create', 'services.view'],
        'services.create'  => ['service:create', 'services.create'],
        'services.edit'    => ['service:update', 'services.edit'],
        'services.publish' => ['service:publish', 'services.publish'],
        'services.delete'  => ['service:delete', 'services.delete'],
    ];

    /**
     * Checks if a user possesses a specific permission.
     */
    public function hasPermission(\PDO $pdo, int $userId, string $permissionSlug): bool
    {
        $permissions = $this->getUserPermissions($pdo, $userId);
        if (in_array($permissionSlug, $permissions, true)) {
            return true;
        }

        if (isset(self::PERMISSION_ALIASES[$permissionSlug])) {
            foreach (self::PERMISSION_ALIASES[$permissionSlug] as $alias) {
                if (in_array($alias, $permissions, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assigns a role to a user.
     */
    public function assignRole(\PDO $pdo, int $userId, string $roleSlug, ?int $assignedByUserId = null): void
    {
        $stmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `slug` = :slug");
        $stmt->execute(['slug' => $roleSlug]);
        $roleId = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($roleId === false) {
            throw new \InvalidArgumentException("Role [{$roleSlug}] does not exist.");
        }

        $check = $pdo->prepare("SELECT COUNT(*) FROM `user_roles` WHERE `user_id` = :uid AND `role_id` = :rid");
        $check->execute(['uid' => $userId, 'rid' => (int)$roleId]);
        $exists = (int)$check->fetchColumn() > 0;
        $check->closeCursor();

        if (!$exists) {
            $insert = $pdo->prepare(
                "INSERT INTO `user_roles` (`user_id`, `role_id`, `assigned_by_user_id`, `created_at`) 
                 VALUES (:uid, :rid, :assigner, CURRENT_TIMESTAMP)"
            );
            $insert->execute([
                'uid'      => $userId,
                'rid'      => (int)$roleId,
                'assigner' => $assignedByUserId,
            ]);
            $insert->closeCursor();
        }
    }
}
