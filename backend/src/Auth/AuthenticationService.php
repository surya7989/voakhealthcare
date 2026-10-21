<?php

declare(strict_types=1);

namespace Voak\Health\Auth;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\Crypto;
use Voak\Health\Security\PasswordHasher;

/**
 * Enterprise Authentication Service for Administrative Staff.
 * Implements timing-attack resistance, brute-force lockout, MFA handoff, and session provisioning.
 */
class AuthenticationService
{
    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOCKOUT_DURATION_SECONDS = 900; // 15 minutes
    public const PRE_AUTH_TTL_SECONDS = 300;     // 5 minutes

    private PasswordHasher $hasher;
    private SessionManager $sessionManager;
    private ?TotpService $totpService;
    private RbacManager $rbacManager;
    private AuditLogger $auditLogger;
    private ?Config $config;
    private ?string $signingKeyOverride = null;

    public function __construct(
        ?PasswordHasher $hasher = null,
        ?SessionManager $sessionManager = null,
        ?TotpService $totpService = null,
        ?RbacManager $rbacManager = null,
        ?AuditLogger $auditLogger = null,
        ?Config $config = null
    ) {
        $this->hasher = $hasher ?? new PasswordHasher();
        $this->sessionManager = $sessionManager ?? new SessionManager();
        $this->totpService = $totpService;
        $this->rbacManager = $rbacManager ?? new RbacManager();
        $this->auditLogger = $auditLogger ?? new AuditLogger();
        $this->config = $config;
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }

    public function setSigningKey(?string $key): void
    {
        $this->signingKeyOverride = $key;
    }

    private function getSigningKey(): string
    {
        if ($this->signingKeyOverride !== null && $this->signingKeyOverride !== '') {
            return $this->signingKeyOverride;
        }

        $key = '';
        if ($this->config !== null) {
            $key = (string)$this->config->get('AUTH_SIGNING_KEY', '');
        }

        if ($key === '') {
            $envKey = getenv('AUTH_SIGNING_KEY');
            if ($envKey) {
                $key = (string)$envKey;
            }
        }

        $env = $this->config ? (string)$this->config->get('APP_ENV', 'development') : (getenv('APP_ENV') ?: 'development');
        $isProdOrStaging = in_array(strtolower($env), ['production', 'staging'], true);

        if ($key === '' || strlen($key) < ($isProdOrStaging ? 32 : 16)) {
            throw new SecurityException("Authentication pre-auth signing key is not configured or insecure (min " . ($isProdOrStaging ? "32" : "16") . " chars). Set AUTH_SIGNING_KEY in configuration.");
        }

        if ($isProdOrStaging) {
            if (str_contains($key, 'PLACEHOLDER') || str_contains($key, 'CHANGE_ME') || str_contains($key, 'YOUR_')) {
                throw new SecurityException("AUTH_SIGNING_KEY cannot use known placeholder values in {$env}.");
            }
            $csrfKey = $this->config ? (string)$this->config->get('CSRF_SIGNING_KEY', '') : '';
            $mfaKey = $this->config ? (string)$this->config->get('MFA_ENCRYPTION_KEY', '') : '';
            if (($csrfKey !== '' && hash_equals($key, $csrfKey)) || ($mfaKey !== '' && hash_equals($key, $mfaKey))) {
                throw new SecurityException("AUTH_SIGNING_KEY must be distinct and independent from CSRF_SIGNING_KEY and MFA_ENCRYPTION_KEY.");
            }
        }

        return $key;
    }

    /**
     * Authenticates an administrative user by email and password.
     *
     * @return array{
     *   status: 'authenticated'|'requires_mfa',
     *   user?: array<string, mixed>,
     *   session?: array{session_id: string, csrf_token: string, expires_at: int},
     *   pre_auth_token?: string
     * }
     */
    public function authenticate(
        \PDO $pdo,
        string $email,
        string $password,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Unknown'
    ): array {
        $cleanEmail = strtolower(trim($email));

        // 1. Fetch user record
        $stmt = $pdo->prepare(
            "SELECT `id`, `name`, `email`, `password_hash`, `is_active`, `failed_login_attempts`, `lockout_until` 
             FROM `users` 
             WHERE `email` = :email AND `deleted_at` IS NULL"
        );
        $stmt->execute(['email' => $cleanEmail]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // 2. Dummy password verification on non-existent account to mitigate timing-based enumeration
        if (!$user) {
            $dummyHash = '$2y$12$DUMMY_HASH_FOR_TIMING_ATTACK_MITIGATION_ONLY_XXXXXXXXXXXXX';
            $this->hasher->verify($password, $dummyHash);
            $this->auditLogger->log($pdo, 'auth', 'AUTH_LOGIN_FAILURE_UNKNOWN_USER', null, $ipAddress, $userAgent, 'user', null, ['email' => $cleanEmail]);
            throw new AuthenticationException("Invalid credentials or account locked.");
        }

        $userId = (int)$user['id'];
        $now = time();

        // 3. Check account lockout state
        if ($user['lockout_until'] !== null) {
            $lockoutUntil = strtotime((string)$user['lockout_until']);
            if ($now < $lockoutUntil) {
                $this->auditLogger->log($pdo, 'auth', 'AUTH_LOGIN_REJECTED_LOCKED', $userId, $ipAddress, $userAgent, 'user', $userId);
                throw new AuthenticationException("Invalid credentials or account locked.");
            }
        }

        // 4. Check active account flag
        if ((int)$user['is_active'] !== 1) {
            $this->auditLogger->log($pdo, 'auth', 'AUTH_LOGIN_REJECTED_INACTIVE', $userId, $ipAddress, $userAgent, 'user', $userId);
            throw new AuthenticationException("Account is inactive or disabled.");
        }

        // 5. Verify password
        $passwordValid = $this->hasher->verify($password, (string)$user['password_hash']);

        if (!$passwordValid) {
            $this->handleFailedLogin($pdo, $userId, (int)$user['failed_login_attempts'], $ipAddress, $userAgent);
            throw new AuthenticationException("Invalid credentials or account locked.");
        }

        // 6. Reset failed attempts upon successful password match
        $resetStmt = $pdo->prepare(
            "UPDATE `users` SET `failed_login_attempts` = 0, `lockout_until` = NULL WHERE `id` = :uid"
        );
        $resetStmt->execute(['uid' => $userId]);
        $resetStmt->closeCursor();

        // 7. Check if password hash requires parameter upgrade / rehash
        if ($this->hasher->needsRehash((string)$user['password_hash'])) {
            $newHash = $this->hasher->hash($password);
            $upg = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `id` = :uid");
            $upg->execute(['hash' => $newHash, 'uid' => $userId]);
            $upg->closeCursor();
        }

        // 8. Check MFA status
        if ($this->isMfaConfigured($pdo, $userId)) {
            $preAuthToken = $this->createPreAuthToken($pdo, $userId, $ipAddress, $userAgent);
            $this->auditLogger->log($pdo, 'auth', 'AUTH_MFA_CHALLENGE_ISSUED', $userId, $ipAddress, $userAgent, 'user', $userId);

            return [
                'status'         => 'requires_mfa',
                'pre_auth_token' => $preAuthToken,
            ];
        }

        // 9. Provision server-side session
        $session = $this->sessionManager->createSession($pdo, $userId, $ipAddress, $userAgent);
        $roles = $this->rbacManager->getUserRoles($pdo, $userId);
        $permissions = $this->rbacManager->getUserPermissions($pdo, $userId);

        $this->auditLogger->log($pdo, 'auth', 'AUTH_LOGIN_SUCCESS', $userId, $ipAddress, $userAgent, 'user', $userId, ['roles' => $roles]);

        return [
            'status'  => 'authenticated',
            'user'    => [
                'id'          => $userId,
                'name'        => (string)$user['name'],
                'email'       => (string)$user['email'],
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'session' => $session,
        ];
    }

    /**
     * Completes authentication by verifying MFA challenge or emergency recovery code.
     */
    public function verifyMfaAndEstablishSession(
        \PDO $pdo,
        string $preAuthToken,
        string $mfaCode,
        bool $isRecoveryCode = false,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Unknown'
    ): array {
        $userId = $this->consumePreAuthToken($pdo, $preAuthToken);
        if ($userId === null) {
            throw new AuthenticationException("Pre-authentication challenge expired, invalid, or already consumed.");
        }

        if ($this->totpService === null) {
            throw new \RuntimeException("TotpService not injected.");
        }

        if ($isRecoveryCode) {
            $valid = $this->totpService->verifyAndConsumeRecoveryCode($pdo, $userId, $mfaCode);
            $action = 'AUTH_MFA_RECOVERY_CODE_VERIFIED';
        } else {
            $valid = $this->totpService->verifyLoginCode($pdo, $userId, $mfaCode);
            $action = 'AUTH_MFA_TOTP_VERIFIED';
        }

        if (!$valid) {
            $this->auditLogger->log($pdo, 'auth', 'AUTH_MFA_FAILED', $userId, $ipAddress, $userAgent, 'user', $userId);
            throw new AuthenticationException("Invalid verification code.");
        }

        // Provision session
        $session = $this->sessionManager->createSession($pdo, $userId, $ipAddress, $userAgent);

        $stmt = $pdo->prepare("SELECT `id`, `name`, `email` FROM `users` WHERE `id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $roles = $this->rbacManager->getUserRoles($pdo, $userId);
        $permissions = $this->rbacManager->getUserPermissions($pdo, $userId);

        $this->auditLogger->log($pdo, 'auth', $action, $userId, $ipAddress, $userAgent, 'user', $userId);

        return [
            'status'  => 'authenticated',
            'user'    => [
                'id'          => $userId,
                'name'        => (string)$user['name'],
                'email'       => (string)$user['email'],
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'session' => $session,
        ];
    }

    private function handleFailedLogin(\PDO $pdo, int $userId, int $currentAttempts, string $ip, string $ua): void
    {
        $stmt = $pdo->prepare(
            "UPDATE `users` SET `failed_login_attempts` = `failed_login_attempts` + 1 WHERE `id` = :uid"
        );
        $stmt->execute(['uid' => $userId]);
        $stmt->closeCursor();

        $check = $pdo->prepare("SELECT `failed_login_attempts` FROM `users` WHERE `id` = :uid");
        $check->execute(['uid' => $userId]);
        $newAttempts = (int)$check->fetchColumn();
        $check->closeCursor();

        $lockoutUntil = null;
        if ($newAttempts >= self::MAX_FAILED_ATTEMPTS) {
            $lockoutUntil = gmdate('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION_SECONDS);
            $lockStmt = $pdo->prepare("UPDATE `users` SET `lockout_until` = :lock WHERE `id` = :uid");
            $lockStmt->execute(['lock' => $lockoutUntil, 'uid' => $userId]);
            $lockStmt->closeCursor();

            $this->auditLogger->log($pdo, 'auth', 'AUTH_ACCOUNT_LOCKED', $userId, $ip, $ua, 'user', $userId, [
                'attempts'      => $newAttempts,
                'lockout_until' => $lockoutUntil,
            ]);
        } else {
            $this->auditLogger->log($pdo, 'auth', 'AUTH_LOGIN_FAILURE', $userId, $ip, $ua, 'user', $userId, [
                'attempt' => $newAttempts,
            ]);
        }
    }

    private function isMfaConfigured(\PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare("SELECT `is_confirmed` FROM `admin_mfa_secrets` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);
        $confirmed = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $confirmed !== false && (int)$confirmed === 1;
    }

    /**
     * Creates a cryptographically signed pre-authentication challenge token with durable single-use storage.
     */
    public function createPreAuthToken(\PDO|int $pdoOrUserId, ?int $maybeUserId = null, string $ipAddress = '127.0.0.1', string $userAgent = 'Unknown'): string
    {
        $pdo = $pdoOrUserId instanceof \PDO ? $pdoOrUserId : null;
        $userId = $pdoOrUserId instanceof \PDO ? (int)$maybeUserId : (int)$pdoOrUserId;

        $signingKey = $this->getSigningKey();
        $challengeId = 'mfa_' . bin2hex(random_bytes(26)); // 60 chars (fits VARCHAR(64))
        $expires = time() + self::PRE_AUTH_TTL_SECONDS;
        $expiresAt = gmdate('Y-m-d H:i:s', $expires);

        if ($pdo !== null) {
            $csrfPlaceholder = hash('sha256', $challengeId);
            $insert = $pdo->prepare(
                "INSERT INTO `admin_sessions` 
                 (`id`, `user_id`, `ip_address`, `user_agent`, `csrf_token_hash`, `expires_at`, `created_at`) 
                 VALUES 
                 (:id, :uid, :ip, :ua, :csrf, :expires, CURRENT_TIMESTAMP)"
            );
            $insert->execute([
                'id'      => $challengeId,
                'uid'     => $userId,
                'ip'      => substr($ipAddress, 0, 45),
                'ua'      => substr($userAgent, 0, 255),
                'csrf'    => $csrfPlaceholder,
                'expires' => $expiresAt,
            ]);
            $insert->closeCursor();
        }

        $payload = $challengeId . ':' . $userId . ':' . $expires;
        $signature = hash_hmac('sha256', $payload, $signingKey);

        return base64_encode($payload . ':' . $signature);
    }

    /**
     * Atomically validates signature, verifies expiration, and consumes challenge token to prevent replay attacks.
     */
    public function consumePreAuthToken(\PDO $pdo, string $token): ?int
    {
        $signingKey = $this->getSigningKey();
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        [$challengeId, $userIdStr, $expiresStr, $signature] = $parts;
        $userId = (int)$userIdStr;
        $expires = (int)$expiresStr;

        if (time() >= $expires) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $challengeId . ':' . $userIdStr . ':' . $expiresStr, $signingKey);
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        // Atomically consume challenge from admin_sessions to prevent replay
        $now = gmdate('Y-m-d H:i:s');
        $del = $pdo->prepare(
            "DELETE FROM `admin_sessions` 
             WHERE `id` = :id AND `user_id` = :uid AND `expires_at` > :now"
        );
        $del->execute([
            'id'  => $challengeId,
            'uid' => $userId,
            'now' => $now,
        ]);
        $affected = $del->rowCount();
        $del->closeCursor();

        if ($affected !== 1) {
            return null; // Expired or already consumed
        }

        return $userId;
    }

    /**
     * Inspects and validates token signature and expiration without consuming.
     */
    public function resolvePreAuthToken(string $token, ?\PDO $pdo = null): ?int
    {
        if ($pdo !== null) {
            return $this->consumePreAuthToken($pdo, $token);
        }

        $signingKey = $this->getSigningKey();
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        [$challengeId, $userIdStr, $expiresStr, $signature] = $parts;
        $userId = (int)$userIdStr;
        $expires = (int)$expiresStr;

        if (time() >= $expires) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $challengeId . ':' . $userIdStr . ':' . $expiresStr, $signingKey);
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        return $userId;
    }

    public function setTotpService(TotpService $totpService): void
    {
        $this->totpService = $totpService;
    }
}
