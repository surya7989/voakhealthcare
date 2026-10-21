<?php

declare(strict_types=1);

namespace Voak\Health\Auth;

use Voak\Health\Security\Crypto;

/**
 * Enterprise Server-Side Session Manager for Administrative Users.
 * Tracks session records in admin_sessions table, enforces idle and absolute timeouts,
 * manages CSRF tokens, and constructs secure HTTP session cookies.
 */
class SessionManager
{
    public const COOKIE_NAME = 'voak_admin_session';
    public const IDLE_TIMEOUT_SECONDS = 900;       // 15 minutes
    public const ABSOLUTE_TIMEOUT_SECONDS = 28800; // 8 hours
    public const MAX_CONCURRENT_SESSIONS = 3;

    /**
     * Creates a new server-side session for an authenticated user.
     *
     * @return array{session_id: string, csrf_token: string, expires_at: int}
     */
    public function createSession(
        \PDO $pdo,
        int $userId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Unknown'
    ): array {
        // 1. Prune concurrent sessions exceeding limit
        $this->pruneConcurrentSessions($pdo, $userId);

        // 2. Generate cryptographically random 64-char hex tokens
        $sessionId = Crypto::randomToken(32);
        $csrfToken = Crypto::randomToken(32);
        $csrfHash = hash('sha256', $csrfToken);

        $now = time();
        $expiresAtTimestamp = $now + self::ABSOLUTE_TIMEOUT_SECONDS;
        $createdAt = gmdate('Y-m-d H:i:s', $now);
        $expiresAt = gmdate('Y-m-d H:i:s', $expiresAtTimestamp);

        // 3. Store in admin_sessions table
        $stmt = $pdo->prepare(
            "INSERT INTO `admin_sessions` 
             (`id`, `user_id`, `ip_address`, `user_agent`, `csrf_token_hash`, `last_activity_at`, `expires_at`, `created_at`) 
             VALUES 
             (:id, :uid, :ip, :ua, :csrf, :last_act, :expires_at, :created_at)"
        );

        $stmt->execute([
            'id'         => $sessionId,
            'uid'        => $userId,
            'ip'         => substr($ipAddress, 0, 45),
            'ua'         => substr($userAgent, 0, 255),
            'csrf'       => $csrfHash,
            'last_act'   => $createdAt,
            'expires_at' => $expiresAt,
            'created_at' => $createdAt,
        ]);
        $stmt->closeCursor();

        return [
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'expires_at' => $expiresAtTimestamp,
        ];
    }

    /**
     * Validates an active session against idle timeout, absolute expiry, and active account status.
     *
     * @return array<string, mixed>|null Returns session record or null if invalid/expired.
     */
    public function validateSession(\PDO $pdo, string $sessionId): ?array
    {
        if ($sessionId === '' || strlen($sessionId) !== 64 || !ctype_xdigit($sessionId)) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT s.*, u.`name` as user_name, u.`email` as user_email, u.`is_active` as user_active
             FROM `admin_sessions` s
             INNER JOIN `users` u ON u.`id` = s.`user_id`
             WHERE s.`id` = :id AND u.`deleted_at` IS NULL"
        );
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$session) {
            return null;
        }

        // Account must be active
        if ((int)$session['user_active'] !== 1) {
            $this->destroySession($pdo, $sessionId);
            return null;
        }

        $now = time();
        $lastActivity = strtotime((string)$session['last_activity_at']);
        $expiresAt = strtotime((string)$session['expires_at']);

        // Check absolute expiry
        if ($now >= $expiresAt) {
            $this->destroySession($pdo, $sessionId);
            return null;
        }

        // Check idle timeout (15 minutes)
        if (($now - $lastActivity) > self::IDLE_TIMEOUT_SECONDS) {
            $this->destroySession($pdo, $sessionId);
            return null;
        }

        // Update last activity timestamp
        $this->touchSession($pdo, $sessionId);

        return $session;
    }

    /**
     * Updates session last activity to current timestamp.
     */
    public function touchSession(\PDO $pdo, string $sessionId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE `admin_sessions` SET `last_activity_at` = :now WHERE `id` = :id");
        $stmt->execute(['now' => $now, 'id' => $sessionId]);
        $stmt->closeCursor();
    }

    /**
     * Destroys an active session.
     */
    public function destroySession(\PDO $pdo, string $sessionId): void
    {
        $stmt = $pdo->prepare("DELETE FROM `admin_sessions` WHERE `id` = :id");
        $stmt->execute(['id' => $sessionId]);
        $stmt->closeCursor();
    }

    /**
     * Destroys all active sessions for a specific user (e.g. after password reset).
     */
    public function destroyUserSessions(\PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare("DELETE FROM `admin_sessions` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $stmt->closeCursor();
    }

    /**
     * Prunes oldest sessions if user exceeds MAX_CONCURRENT_SESSIONS.
     */
    private function pruneConcurrentSessions(\PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare(
            "SELECT `id` FROM `admin_sessions` WHERE `user_id` = :uid ORDER BY `last_activity_at` DESC"
        );
        $stmt->execute(['uid' => $userId]);
        $sessions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        if (count($sessions) >= self::MAX_CONCURRENT_SESSIONS) {
            $toDelete = array_slice($sessions, self::MAX_CONCURRENT_SESSIONS - 1);
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $delStmt = $pdo->prepare("DELETE FROM `admin_sessions` WHERE `id` IN ({$placeholders})");
                $delStmt->execute(array_values($toDelete));
                $delStmt->closeCursor();
            }
        }
    }

    /**
     * Constructs the Set-Cookie header value conforming to enterprise security standards:
     * Path=/api/v1/admin, HttpOnly, SameSite=Lax, Secure.
     */
    public function buildSessionCookie(string $sessionId, bool $isSecure = true, int $maxAge = self::ABSOLUTE_TIMEOUT_SECONDS): string
    {
        $cookie = sprintf(
            '%s=%s; Path=/api/v1/admin; Max-Age=%d; HttpOnly; SameSite=Lax',
            self::COOKIE_NAME,
            $sessionId,
            $maxAge
        );

        if ($isSecure) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    /**
     * Constructs an expired Set-Cookie header value to clear the cookie on logout.
     */
    public function buildExpiredCookie(bool $isSecure = true): string
    {
        $cookie = sprintf(
            '%s=deleted; Path=/api/v1/admin; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; HttpOnly; SameSite=Lax',
            self::COOKIE_NAME
        );

        if ($isSecure) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }
}
