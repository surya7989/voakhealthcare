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

            // TASK 2: Deterministic Canonical JSON Serialization Tests
            T::test('nested associative payloads with different key insertion order canonicalize deterministically', function () {
                $diff1 = [
                    'zeta'  => 100,
                    'alpha' => ['nested_z' => 'valueZ', 'nested_a' => 'valueA'],
                    'beta'  => 'test',
                ];
                $diff2 = [
                    'alpha' => ['nested_a' => 'valueA', 'nested_z' => 'valueZ'],
                    'beta'  => 'test',
                    'zeta'  => 100,
                ];

                $p1 = AuditLogger::buildCanonicalPayload('prevHash123', 'admin', 'MUTATION', 1, '127.0.0.1', 'UA', 'user', 1, $diff1, '2026-10-22 00:00:00');
                $p2 = AuditLogger::buildCanonicalPayload('prevHash123', 'admin', 'MUTATION', 1, '127.0.0.1', 'UA', 'user', 1, $diff2, '2026-10-22 00:00:00');

                T::assertEquals($p1, $p2, 'Payloads with differently ordered keys must produce identical canonical JSON');
                T::assertEquals(hash('sha256', $p1), hash('sha256', $p2), 'Hashes must be identical');
            });

            T::test('nested keys are recursively canonicalized across multiple depth levels', function () {
                $nestedUnsorted = [
                    'level1' => [
                        'd' => 4,
                        'b' => [
                            'z' => 26,
                            'a' => ['y' => 25, 'x' => 24]
                        ],
                        'c' => 3
                    ]
                ];
                $canonical = AuditLogger::canonicalizeData($nestedUnsorted);

                // Keys at level 1 must be sorted: b, c, d
                $l1Keys = array_keys($canonical['level1']);
                T::assertEquals(['b', 'c', 'd'], $l1Keys);

                // Keys at level 2 must be sorted: a, z
                $l2Keys = array_keys($canonical['level1']['b']);
                T::assertEquals(['a', 'z'], $l2Keys);

                // Keys at level 3 must be sorted: x, y
                $l3Keys = array_keys($canonical['level1']['b']['a']);
                T::assertEquals(['x', 'y'], $l3Keys);
            });

            T::test('list order remains significant in canonical array serialization', function () {
                $list1 = ['tags' => ['emergency', 'pediatric', 'icu']];
                $list2 = ['tags' => ['icu', 'pediatric', 'emergency']];

                $p1 = AuditLogger::buildCanonicalPayload('prev', 'domain', 'act', null, '127.0.0.1', 'UA', null, null, $list1, '2026-10-22 00:00:00');
                $p2 = AuditLogger::buildCanonicalPayload('prev', 'domain', 'act', null, '127.0.0.1', 'UA', null, null, $list2, '2026-10-22 00:00:00');

                T::assertTrue($p1 !== $p2, 'Different list order must produce different canonical output');
                T::assertTrue(hash('sha256', $p1) !== hash('sha256', $p2), 'Different list order must produce different hashes');
            });

            T::test('strings containing pipe delimiters do not cause serialization ambiguity', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                // Write rows where domain, action, and diff contain pipe characters
                $r1 = $logger->log($pdo, 'domain|with|pipe', 'action|with|pipe', 1, '127.0.0.1', 'Browser|UA|1.0', 'entity|type', 1, ['key|pipe' => 'value|pipe']);
                $r2 = $logger->log($pdo, 'normal_domain', 'normal_action', 1, '10.0.0.1', 'NormalUA', 'entity', 2, ['notes' => 'a|b|c|d']);

                T::assertTrue($logger->verifyChain($pdo), 'Ledger with pipe characters in values must verify successfully');
            });

            T::test('unicode and non-ASCII values verify correctly in ledger', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $unicodeDiff = [
                    'facility' => '🏥 Voak Corporate Hospital',
                    'city'     => '東京 (Tokyo)',
                    'message'  => 'Atención médica de calidad — αβγδε',
                ];

                $logger->log($pdo, 'corporate', 'ACCION_REGISTRO', 1, '127.0.0.1', 'EmojiBrowser/1.0 🌟', 'hospital', 42, $unicodeDiff);
                T::assertTrue($logger->verifyChain($pdo), 'Unicode and emoji characters must verify cleanly');
            });

            T::test('null, empty string, empty list, and empty object remain distinguishable', function () {
                $pNull = AuditLogger::buildCanonicalPayload('prev', 'dom', 'act', null, '127.0.0.1', 'UA', null, null, null, '2026-10-22 00:00:00');
                $pEmptyStr = AuditLogger::buildCanonicalPayload('prev', 'dom', 'act', null, '127.0.0.1', 'UA', '', null, null, '2026-10-22 00:00:00');
                $pEmptyList = AuditLogger::buildCanonicalPayload('prev', 'dom', 'act', null, '127.0.0.1', 'UA', null, null, [], '2026-10-22 00:00:00');
                $pZeroId = AuditLogger::buildCanonicalPayload('prev', 'dom', 'act', 0, '127.0.0.1', 'UA', null, 0, null, '2026-10-22 00:00:00');

                T::assertTrue($pNull !== $pEmptyStr, 'Null entity_type must differ from empty string entity_type');
                T::assertTrue($pNull !== $pEmptyList, 'Null diff must differ from empty list diff');
                T::assertTrue($pNull !== $pZeroId, 'Null user_id must differ from zero user_id');

                $hNull = hash('sha256', $pNull);
                $hEmptyStr = hash('sha256', $pEmptyStr);
                $hEmptyList = hash('sha256', $pEmptyList);
                $hZeroId = hash('sha256', $pZeroId);

                T::assertTrue($hNull !== $hEmptyStr);
                T::assertTrue($hNull !== $hEmptyList);
                T::assertTrue($hNull !== $hZeroId);
            });

            T::test('stored and read-back records verify successfully across database round-trip', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                // Round-trip test with various data types
                $logger->log($pdo, 'sec', 'LOGIN', 1, '127.0.0.1', 'CLI', 'user', 1, ['status' => 'ok', 'roles' => ['admin', 'super']]);
                $logger->log($pdo, 'billing', 'INVOICE_GENERATED', 2, '10.0.0.5', 'Browser/1', 'invoice', 99, ['amount' => 1250.50, 'tax' => null, 'meta' => []]);
                $logger->log($pdo, 'system', 'EMPTY_TEST', null, '127.0.0.1', 'System', null, null, null);

                T::assertTrue($logger->verifyChain($pdo), 'Database round-trip with varied types must verify cleanly');
            });
        });
    }
}
