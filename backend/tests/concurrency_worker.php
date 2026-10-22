<?php

declare(strict_types=1);

namespace Voak\Health\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
ini_set('memory_limit', '512M');

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

$options = getopt('', ['action:', 'db::', 'driver::', 'dsn::', 'db_user::', 'db_pass::', 'token::', 'password::', 'user_id::', 'code::', 'email::', 'action_name::', 'payload::']);

$action = $options['action'] ?? '';
if (!$action) {
    exit(2);
}

$driver = $options['driver'] ?? (isset($options['dsn']) && str_starts_with((string)$options['dsn'], 'mysql:') ? 'mysql' : 'sqlite');

if ($driver === 'mysql') {
    $dsn = $options['dsn'] ?? sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        (int)(getenv('DB_PORT') ?: 3306),
        getenv('DB_DATABASE') ?: 'voak_staging_ci'
    );
    $user = $options['db_user'] ?? (getenv('DB_USERNAME') ?: 'root');
    $pass = $options['db_pass'] ?? (getenv('DB_PASSWORD') ?: 'mysql-ci-password');

    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);
} else {
    $dbPath = $options['db'] ?? '';
    if (!$dbPath || !file_exists($dbPath)) {
        exit(2);
    }
    // Open shared SQLite database with busy timeout so concurrent transactions wait rather than crash
    $pdo = new \PDO("sqlite:{$dbPath}", null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_TIMEOUT => 15,
    ]);
    $pdo->exec("PRAGMA busy_timeout = 15000;");
}

$hasher = new PasswordHasher();
$sessionManager = new SessionManager();
$auditLogger = new AuditLogger();
$rbacManager = new RbacManager();
$config = new Config([
    'AUTH_SIGNING_KEY'   => 'concurrency-worker-auth-key-32-chars!',
    'CSRF_SIGNING_KEY'   => 'concurrency-worker-csrf-key-32-chars!',
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
        try {
            $success = $resetService->confirmReset($pdo, $token, $newPass);
            exit($success ? 0 : 1);
        } catch (\Throwable $e) {
            fwrite(STDERR, "consume_reset error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

    case 'consume_recovery':
        $userId = (int)($options['user_id'] ?? 1);
        $code = $options['code'] ?? '';
        try {
            $success = $totpService->verifyAndConsumeRecoveryCode($pdo, $userId, $code);
            exit($success ? 0 : 1);
        } catch (\Throwable $e) {
            fwrite(STDERR, "consume_recovery error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

    case 'failed_login':
        $email = $options['email'] ?? 'test@voak.test';
        $password = $options['password'] ?? 'WrongPassword123!';
        try {
            $authService->authenticate($pdo, $email, $password);
            exit(2); // Unexpected success
        } catch (AuthenticationException) {
            exit(0); // Expected failed login recorded
        } catch (\Throwable $e) {
            fwrite(STDERR, "failed_login error: " . $e->getMessage() . "\n");
            exit(1);
        }

    case 'audit_log':
        $actionName = $options['action_name'] ?? 'CONCURRENT_TEST_EVENT';
        $payload = $options['payload'] ?? 'test';
        $userId = isset($options['user_id']) && $options['user_id'] !== '' ? (int)$options['user_id'] : null;
        $maxAttempts = 10;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $auditLogger->log($pdo, 'concurrency', $actionName, $userId, '127.0.0.1', 'Worker/1.0', 'test', $userId, ['payload' => $payload]);
                exit(0);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if ((str_contains($msg, 'database is locked') || str_contains($msg, 'serialization lock')) && $attempt < $maxAttempts) {
                    usleep(random_int(20000, 80000)); // 20-80ms jitter
                    continue;
                }
                fwrite(STDERR, "audit_log error: " . $msg . "\n" . $e->getTraceAsString() . "\n");
                exit(1);
            }
        }
        exit(1);

    default:
        exit(2);
}
