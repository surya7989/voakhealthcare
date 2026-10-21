<?php

declare(strict_types=1);

namespace Voak\Health\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;

$options = getopt('', ['action:', 'db:', 'token::', 'password::', 'user_id::', 'code::', 'email::', 'action_name::', 'payload::']);

$action = $options['action'] ?? '';
$dbPath = $options['db'] ?? '';

if (!$action || !$dbPath || !file_exists($dbPath)) {
    exit(2);
}

// Open shared SQLite database with busy timeout so concurrent transactions wait rather than crash
$pdo = new \PDO("sqlite:{$dbPath}", null, null, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_TIMEOUT => 15,
]);
$pdo->exec("PRAGMA busy_timeout = 15000;");

$hasher = new PasswordHasher();
$sessionManager = new SessionManager();
$auditLogger = new AuditLogger();
$rbacManager = new RbacManager();
$config = new Config([
    'AUTH_SIGNING_KEY'   => 'concurrency-worker-auth-key-32-chars!',
    'MFA_ENCRYPTION_KEY' => 'concurrency-worker-mfa-key-32-chars!',
    'APP_ENV'            => 'testing',
]);
$totpService = new TotpService($config, new TotpEngine());
$authService = new AuthenticationService($hasher, $sessionManager, $totpService, $rbacManager, $auditLogger, $config);
$resetService = new PasswordResetService($hasher, $sessionManager, $auditLogger);

switch ($action) {
    case 'consume_reset':
        $token = $options['token'] ?? '';
        $newPass = $options['password'] ?? 'Concurrent#2026!Pass';
        $success = $resetService->confirmReset($pdo, $token, $newPass);
        exit($success ? 0 : 1);

    case 'consume_recovery':
        $userId = (int)($options['user_id'] ?? 1);
        $code = $options['code'] ?? '';
        $success = $totpService->verifyAndConsumeRecoveryCode($pdo, $userId, $code);
        exit($success ? 0 : 1);

    case 'failed_login':
        $email = $options['email'] ?? 'test@voak.test';
        $password = $options['password'] ?? 'WrongPassword123!';
        try {
            $authService->authenticate($pdo, $email, $password);
            exit(2); // Unexpected success
        } catch (AuthenticationException) {
            exit(0); // Expected failed login recorded
        } catch (\Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(1);
        }

    case 'audit_log':
        $actionName = $options['action_name'] ?? 'CONCURRENT_TEST_EVENT';
        $payload = $options['payload'] ?? 'test';
        $maxAttempts = 10;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $auditLogger->log($pdo, 'concurrency', $actionName, 1, '127.0.0.1', 'Worker/1.0', 'test', 1, ['payload' => $payload]);
                exit(0);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'database is locked') && $attempt < $maxAttempts) {
                    usleep(random_int(20000, 80000)); // 20-80ms jitter
                    continue;
                }
                fwrite(STDERR, $e->getMessage() . "\n");
                exit(1);
            }
        }
        exit(1);

    default:
        exit(2);
}
