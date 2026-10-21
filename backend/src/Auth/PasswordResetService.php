<?php

declare(strict_types=1);

namespace Voak\Health\Auth;

use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\Crypto;
use Voak\Health\Security\PasswordHasher;

/**
 * Enterprise Password Reset Lifecycle Service.
 * Implements cryptographically secure, single-use, expiring recovery tokens.
 */
class PasswordResetService
{
    public const TOKEN_EXPIRY_SECONDS = 3600; // 1 hour

    private PasswordHasher $hasher;
    private SessionManager $sessionManager;
    private AuditLogger $auditLogger;

    public function __construct(
        ?PasswordHasher $hasher = null,
        ?SessionManager $sessionManager = null,
        ?AuditLogger $auditLogger = null
    ) {
        $this->hasher = $hasher ?? new PasswordHasher();
        $this->sessionManager = $sessionManager ?? new SessionManager();
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    /**
     * Initiates a password reset request. Returns plaintext token if user exists and is active.
     */
    public function requestReset(\PDO $pdo, string $email, string $ipAddress = '127.0.0.1', string $userAgent = 'Unknown'): ?string
    {
        $stmt = $pdo->prepare("SELECT `id`, `is_active` FROM `users` WHERE `email` = :email AND `deleted_at` IS NULL");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$user || (int)$user['is_active'] !== 1) {
            // Log attempt without revealing account non-existence
            $this->auditLogger->log($pdo, 'auth', 'AUTH_PASSWORD_RESET_REQUEST_UNKNOWN', null, $ipAddress, $userAgent, 'user', null, ['email' => $email]);
            return null;
        }

        $userId = (int)$user['id'];
        $rawToken = Crypto::randomToken(32);
        $tokenHash = hash('sha256', $rawToken);

        $now = time();
        $expiresAt = gmdate('Y-m-d H:i:s', $now + self::TOKEN_EXPIRY_SECONDS);
        $createdAt = gmdate('Y-m-d H:i:s', $now);

        // Invalidate prior unused tokens for this user
        $revoke = $pdo->prepare("UPDATE `password_resets` SET `used_at` = :now WHERE `user_id` = :uid AND `used_at` IS NULL");
        $revoke->execute(['now' => $createdAt, 'uid' => $userId]);
        $revoke->closeCursor();

        // Insert new token record
        $insert = $pdo->prepare(
            "INSERT INTO `password_resets` 
             (`user_id`, `token_hash`, `ip_address`, `expires_at`, `created_at`) 
             VALUES 
             (:uid, :hash, :ip, :expires, :created)"
        );
        $insert->execute([
            'uid'     => $userId,
            'hash'    => $tokenHash,
            'ip'      => substr($ipAddress, 0, 45),
            'expires' => $expiresAt,
            'created' => $createdAt,
        ]);
        $insert->closeCursor();

        $this->auditLogger->log($pdo, 'auth', 'AUTH_PASSWORD_RESET_REQUESTED', $userId, $ipAddress, $userAgent, 'user', $userId);

        return $rawToken;
    }

    /**
     * Confirms and applies a new password using a single-use token.
     */
    public function confirmReset(
        \PDO $pdo,
        string $rawToken,
        string $newPassword,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Unknown'
    ): bool {
        if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare(
            "SELECT r.*, u.`is_active`
             FROM `password_resets` r
             INNER JOIN `users` u ON u.`id` = r.`user_id`
             WHERE r.`token_hash` = :hash AND r.`used_at` IS NULL AND u.`deleted_at` IS NULL"
        );
        $stmt->execute(['hash' => $tokenHash]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$record) {
            return false;
        }

        // Check expiration
        $expiresAt = strtotime((string)$record['expires_at']);
        if (time() >= $expiresAt) {
            return false;
        }

        // Validate password complexity
        $this->hasher->enforceEntropy($newPassword);

        $userId = (int)$record['user_id'];
        $newHash = $this->hasher->hash($newPassword);
        $now = gmdate('Y-m-d H:i:s');

        // Update password and reset lockout counters
        $updateUser = $pdo->prepare(
            "UPDATE `users` 
             SET `password_hash` = :hash, 
                 `password_changed_at` = :now, 
                 `failed_login_attempts` = 0, 
                 `lockout_until` = NULL 
             WHERE `id` = :uid"
        );
        $updateUser->execute([
            'hash' => $newHash,
            'now'  => $now,
            'uid'  => $userId,
        ]);
        $updateUser->closeCursor();

        // Mark token as consumed
        $markUsed = $pdo->prepare("UPDATE `password_resets` SET `used_at` = :now WHERE `id` = :id");
        $markUsed->execute(['now' => $now, 'id' => (int)$record['id']]);
        $markUsed->closeCursor();

        // Revoke all existing sessions for this user (session fixation defense)
        $this->sessionManager->destroyUserSessions($pdo, $userId);

        // Audit logging
        $this->auditLogger->log($pdo, 'auth', 'AUTH_PASSWORD_RESET_COMPLETE', $userId, $ipAddress, $userAgent, 'user', $userId);

        return true;
    }
}
