<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Tests\TestFramework as T;

class AuditLoggerTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);
        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — AuditLogger Cryptographic Chaining Tests', function () {
            T::test('initial log row anchors to genesis hash', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $result = $logger->log(
                    $pdo,
                    'auth',
                    'AUTH_LOGIN_SUCCESS',
                    1,
                    '127.0.0.1',
                    'PHPUnit/TestBrowser',
                    'user',
                    1,
                    ['ip' => '127.0.0.1']
                );

                T::assertTrue($result['id'] > 0);
                T::assertEquals(AuditLogger::GENESIS_HASH, $result['prev_hash']);
                T::assertEquals(64, strlen($result['row_hash']));

                $stmt = $pdo->prepare('SELECT * FROM audit_logs WHERE id = ?');
                $stmt->execute([$result['id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                T::assertEquals(AuditLogger::GENESIS_HASH, $row['prev_row_hash']);
                T::assertEquals($result['row_hash'], $row['row_hash']);
            });

            T::test('subsequent log rows link cryptographically to previous row hash', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $r1 = $logger->log($pdo, 'domain', 'action.one', 1);
                $r2 = $logger->log($pdo, 'domain', 'action.two', 1);
                $r3 = $logger->log($pdo, 'domain', 'action.three', 1);

                $stmt = $pdo->query('SELECT id, prev_row_hash, row_hash FROM audit_logs ORDER BY id ASC');
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                T::assertEquals(3, count($rows));
                T::assertEquals(AuditLogger::GENESIS_HASH, $rows[0]['prev_row_hash']);
                T::assertEquals($rows[0]['row_hash'], $rows[1]['prev_row_hash']);
                T::assertEquals($rows[1]['row_hash'], $rows[2]['prev_row_hash']);
            });

            T::test('verifyChain validates untampered audit ledger', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $logger->log($pdo, 'users', 'action.1', 1);
                $logger->log($pdo, 'roles', 'action.2', 2);
                $logger->log($pdo, 'permissions', 'action.3', 5);

                $isValid = $logger->verifyChain($pdo);
                T::assertTrue($isValid);
            });

            T::test('verifyChain detects tampering if payload or hash is mutated', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $logger->log($pdo, 'users', 'action.1', 1);
                $r2 = $logger->log($pdo, 'roles', 'action.2', 2);
                $logger->log($pdo, 'permissions', 'action.3', 5);

                // Tamper with action of record 2 directly in database
                $id2 = $r2['id'];
                $pdo->exec("UPDATE audit_logs SET action = 'tampered.action' WHERE id = {$id2}");

                $isValid = $logger->verifyChain($pdo);
                T::assertFalse($isValid);
            });

            T::test('rejects audit logging inside active uncommitted transaction to prevent chain forks', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $pdo->beginTransaction();
                $exceptionThrown = false;
                try {
                    $logger->log($pdo, 'security', 'SHOULD_FAIL', 1);
                } catch (\Voak\Health\Core\Exceptions\SecurityException $e) {
                    $exceptionThrown = true;
                    T::assertTrue(str_contains($e->getMessage(), 'active uncommitted transaction'));
                } finally {
                    $pdo->rollBack();
                }

                T::assertTrue($exceptionThrown, 'Must throw SecurityException when invoked within active uncommitted transaction');
            });
        });
    }
}
