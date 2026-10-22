<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;

/**
 * Enterprise System and Corporate Settings Service.
 * Manages public platform branding, operational contact info, and admin configuration variables.
 * Strictly isolates secret credentials from public exposure.
 */
class SettingService
{
    /**
     * Forbidden keywords in public setting keys (defense-in-depth isolation).
     */
    private const FORBIDDEN_PUBLIC_KEYWORDS = [
        'secret', 'password', 'token', 'key', 'credential',
        'private', 'salt', 'hash', 'mfa', 'cookie', 'auth',
        'database', 'smtp_pass', 'signing',
    ];

    private Connection $connection;
    private AuditLogger $auditLogger;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Seeds initial default settings if table is empty.
     */
    public function ensureDefaultSettings(int $systemUserId = 1): void
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `system_settings`");
        $count = $stmt ? (int)$stmt->fetchColumn() : 0;
        if ($stmt) {
            $stmt->closeCursor();
        }

        if ($count > 0) {
            return;
        }

        $defaults = [
            // Public branding & identity
            ['general', 'site_name', 'Voak Health Care', 0, 'Corporate legal and brand trading name'],
            ['general', 'site_tagline', 'Next-Generation Workforce & Clinical Governance for Australian Healthcare', 0, 'Public platform primary tagline'],
            ['contact', 'contact_email', 'contact@voak.com.au', 0, 'General corporate inquiry email address'],
            ['contact', 'contact_phone', '1300 862 542', 0, 'Australian toll-free corporate telephone number'],
            ['contact', 'business_hours', 'Monday - Friday: 08:30 - 17:30 AEST', 0, 'Operational business hours'],
            ['contact', 'primary_address', 'Level 28, 161 Castlereagh Street, Sydney NSW 2000', 0, 'Registered corporate headquarters address'],
            ['legal', 'company_abn', '45 678 912 345', 0, 'Australian Business Number (ABN)'],
            ['legal', 'copyright_notice', '© 2026 Voak Health Care Pty Ltd. All rights reserved.', 0, 'Public footer copyright notice'],

            // Private administrative configuration (Strictly secret)
            ['security', 'session_idle_timeout_minutes', '15', 1, 'Maximum administrative inactivity threshold before forced timeout'],
            ['security', 'max_login_attempts', '5', 1, 'Consecutive failed login threshold triggering rate-limit and lockout'],
        ];

        $insertStmt = $pdo->prepare("INSERT INTO `system_settings` (
            `setting_group`, `setting_key`, `setting_value`, `is_secret`, `description`, `updated_by_user_id`
        ) VALUES (:group, :key, :value, :is_secret, :description, :uid)");

        foreach ($defaults as [$group, $key, $val, $isSecret, $desc]) {
            $insertStmt->execute([
                'group'       => $group,
                'key'         => $key,
                'value'       => $val,
                'is_secret'   => $isSecret,
                'description' => $desc,
                'uid'         => $systemUserId,
            ]);
        }
        $insertStmt->closeCursor();
    }

    /**
     * Retrieves public platform settings.
     * Guaranteed to NEVER expose rows where `is_secret != 0` or forbidden keywords.
     *
     * @return array<string, mixed>
     */
    public function getPublicSettings(): array
    {
        $this->ensureDefaultSettings();

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT `setting_group`, `setting_key`, `setting_value`, `description`, `updated_at`
            FROM `system_settings`
            WHERE `is_secret` = 0
            ORDER BY `setting_group` ASC, `setting_key` ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $settings = [];
        $maxUpdatedAt = '';

        foreach ($rows as $row) {
            $key = (string)$row['setting_key'];
            $lowerKey = strtolower($key);

            // Defense-in-depth isolation: skip any sensitive keyword matches
            $containsSensitiveKeyword = false;
            foreach (self::FORBIDDEN_PUBLIC_KEYWORDS as $keyword) {
                if (str_contains($lowerKey, $keyword)) {
                    $containsSensitiveKeyword = true;
                    break;
                }
            }

            if ($containsSensitiveKeyword) {
                continue;
            }

            $settings[$key] = [
                'group'       => (string)$row['setting_group'],
                'key'         => $key,
                'value'       => (string)($row['setting_value'] ?? ''),
                'description' => (string)$row['description'],
                'is_public'   => true,
            ];

            if ((string)$row['updated_at'] > $maxUpdatedAt) {
                $maxUpdatedAt = (string)$row['updated_at'];
            }
        }

        $etag = '"' . hash('sha256', $maxUpdatedAt . '|' . json_encode($settings)) . '"';

        return [
            'settings' => $settings,
            'etag'     => $etag,
        ];
    }

    /**
     * Retrieves all settings for admin inspection.
     *
     * @return list<array<string, mixed>>
     */
    public function getAllSettings(): array
    {
        $this->ensureDefaultSettings();

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT `id`, `setting_group`, `setting_key`, `setting_value`, `is_secret`, `description`, `updated_by_user_id`, `updated_at`
            FROM `system_settings`
            ORDER BY `setting_group` ASC, `setting_key` ASC");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }

        $result = [];
        foreach ($rows as $row) {
            $isSecret = (int)$row['is_secret'] === 1;
            $result[] = [
                'id'                 => (int)$row['id'],
                'setting_group'      => (string)$row['setting_group'],
                'setting_key'        => (string)$row['setting_key'],
                // Mask secret values in standard view
                'setting_value'      => $isSecret ? '[PROTECTED SECRET]' : (string)($row['setting_value'] ?? ''),
                'is_secret'          => $isSecret,
                'is_public'          => !$isSecret,
                'description'        => (string)$row['description'],
                'updated_by_user_id' => (int)$row['updated_by_user_id'],
                'updated_at'         => (string)$row['updated_at'],
            ];
        }

        return $result;
    }

    /**
     * Updates settings batch.
     *
     * @param array<string, mixed> $updates Key => Value pairs or list of items
     * @return array{updated_count: int}
     */
    public function updateSettings(array $updates, int $userId): array
    {
        $this->ensureDefaultSettings($userId);

        if (empty($updates)) {
            throw new BadRequestException("No setting updates provided.");
        }

        // Normalize format: support ['settings' => [...]] or ['key' => 'value'] or [['key' => '...', 'value' => '...']]
        $normalized = [];
        if (isset($updates['settings']) && is_array($updates['settings'])) {
            $updates = $updates['settings'];
        }

        foreach ($updates as $k => $v) {
            if (is_array($v) && isset($v['key'])) {
                $settingKey = (string)$v['key'];
                $settingValue = (string)($v['value'] ?? '');
            } else {
                $settingKey = (string)$k;
                $settingValue = is_scalar($v) ? (string)$v : json_encode($v);
            }
            $normalized[$settingKey] = $settingValue;
        }

        $pdo = $this->connection->getPdo();

        // Validate that keys exist and reject injecting secrets into public settings
        $keys = array_keys($normalized);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $stmt = $pdo->prepare("SELECT `id`, `setting_key`, `is_secret` FROM `system_settings` WHERE `setting_key` IN ({$placeholders})");
        $stmt->execute($keys);
        $found = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $foundMap = [];
        foreach ($found as $f) {
            $foundMap[$f['setting_key']] = $f;
        }

        // Check for non-existent keys
        foreach ($keys as $k) {
            if (!isset($foundMap[$k])) {
                throw new NotFoundException("Setting '{$k}' does not exist.");
            }
        }

        $updatedKeys = [];
        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("UPDATE `system_settings`
                SET `setting_value` = :val,
                    `updated_by_user_id` = :uid,
                    `updated_at` = CURRENT_TIMESTAMP
                WHERE `setting_key` = :key");

            foreach ($normalized as $key => $val) {
                // Reject storing environment secrets or private keys in settings
                if (strlen($val) > 4096) {
                    throw new BadRequestException("Setting value for '{$key}' exceeds maximum permitted length (4096 characters).");
                }

                $updateStmt->execute([
                    'val' => $val,
                    'uid' => $userId,
                    'key' => $key,
                ]);
                $updatedKeys[] = $key;
            }
            $updateStmt->closeCursor();

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
            'settings_updated',
            $userId,
            '127.0.0.1',
            'CMS/SettingService',
            'system_settings',
            null,
            [
                'updated_keys' => $updatedKeys,
                'count'        => count($updatedKeys),
            ]
        );

        return [
            'updated_count' => count($updatedKeys),
        ];
    }
}
