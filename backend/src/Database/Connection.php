<?php

declare(strict_types=1);

namespace Voak\Health\Database;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\DatabaseException;

/**
 * Lazy PDO Database Connection manager for MySQL 8.x / InnoDB.
 * Connection is strictly deferred until getPdo() is explicitly requested.
 */
class Connection
{
    private string $host;
    private int $port;
    private string $database;
    private string $username;
    private string $password;
    private string $charset;

    private ?\PDO $pdo = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 3306,
        string $database = 'voak_healthcare',
        string $username = 'root',
        string $password = '',
        string $charset = 'utf8mb4'
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            (string)$config->get('DB_HOST', '127.0.0.1'),
            (int)$config->get('DB_PORT', 3306),
            (string)$config->get('DB_DATABASE', 'voak_healthcare'),
            (string)$config->get('DB_USERNAME', 'root'),
            (string)$config->get('DB_PASSWORD', ''),
            (string)$config->get('DB_CHARSET', 'utf8mb4')
        );
    }

    /**
     * Check if a live PDO connection has already been opened.
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Returns the PDO instance, lazily connecting on first call.
     *
     * @throws DatabaseException If connection fails without leaking credentials
     */
    public function getPdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new \PDO($dsn, $this->username, $this->password, $options);
            return $this->pdo;
        } catch (\PDOException $e) {
            // Mask raw credentials and host information in public message
            throw new DatabaseException(
                "Database connection could not be established. Ensure MySQL is accessible.",
                ['driver' => 'mysql', 'database' => $this->database],
                $e
            );
        }
    }

    /**
     * Injects a mock PDO instance for unit testing without live MySQL.
     */
    public function setMockPdo(\PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function getDatabaseName(): string
    {
        return $this->database;
    }
}
