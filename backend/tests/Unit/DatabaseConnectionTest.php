<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Transaction;
use Voak\Health\Tests\TestFramework as T;

class DatabaseConnectionTest
{
    public static function run(): void
    {
        T::suite('Database Connection Abstraction & Transaction Tests', function () {
            T::test('Connection instance is strictly lazy and does not connect on instantiation', function () {
                $config = new Config([
                    'DB_HOST' => '127.0.0.1',
                    'DB_PORT' => 3306,
                    'DB_DATABASE' => 'voak_healthcare',
                    'DB_USERNAME' => 'test_user',
                    'DB_PASSWORD' => 'test_pass',
                ]);

                $conn = Connection::fromConfig($config);
                T::assertFalse($conn->isConnected(), 'Database connection must not be established on instantiation.');
                T::assertEquals('voak_healthcare', $conn->getDatabaseName());
            });

            T::test('supports mock PDO injection for unit testing without live MySQL', function () {
                $conn = new Connection();
                T::assertFalse($conn->isConnected());

                // Create a real in-memory SQLite PDO to verify PDO contract behavior
                try {
                    $mockPdo = new \PDO('sqlite::memory:');
                    $conn->setMockPdo($mockPdo);
                    T::assertTrue($conn->isConnected());
                    T::assertEquals($mockPdo, $conn->getPdo());
                } catch (\PDOException $e) {
                    // SQLite not enabled, skip in-memory verification
                    echo "    (Notice: sqlite in-memory skipped: " . $e->getMessage() . ")\n";
                }
            });

            T::test('Transaction::run commits on success and rolls back on exception', function () {
                try {
                    $pdo = new \PDO('sqlite::memory:');
                    $pdo->exec("CREATE TABLE test_tx (id INT PRIMARY KEY, val TEXT)");

                    // 1. Successful transaction
                    $result = Transaction::run($pdo, function (\PDO $db) {
                        $db->exec("INSERT INTO test_tx VALUES (1, 'commited_value')");
                        return 'success_val';
                    });
                    T::assertEquals('success_val', $result);

                    $count = (int)$pdo->query("SELECT COUNT(*) FROM test_tx")->fetchColumn();
                    T::assertEquals(1, $count);

                    // 2. Failed transaction with rollback
                    try {
                        Transaction::run($pdo, function (\PDO $db) {
                            $db->exec("INSERT INTO test_tx VALUES (2, 'rollback_value')");
                            throw new \RuntimeException('Simulated transaction failure');
                        });
                        T::assertTrue(false, 'Should have rethrown exception');
                    } catch (\RuntimeException $e) {
                        T::assertEquals('Simulated transaction failure', $e->getMessage());
                    }

                    // Count should still be 1 (row 2 rolled back)
                    $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM test_tx")->fetchColumn();
                    T::assertEquals(1, $countAfter);
                } catch (\PDOException $e) {
                    echo "    (Notice: sqlite in-memory skipped: " . $e->getMessage() . ")\n";
                }
            });

            T::test('fails gracefully with DatabaseException when connecting to unreachable host', function () {
                // Point to non-routable host with short timeout to verify error wrapping
                $conn = new Connection('192.0.2.1', 3306, 'dummy_db', 'user', 'secret_pwd');

                T::expectException(
                    DatabaseException::class,
                    function () use ($conn) {
                        $conn->getPdo();
                    },
                    'Database connection could not be established'
                );
            });
        });
    }
}
