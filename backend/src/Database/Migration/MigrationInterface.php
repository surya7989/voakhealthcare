<?php

declare(strict_types=1);

namespace Voak\Health\Database\Migration;

/**
 * Migration contract for database schema definition and lifecycle management.
 */
interface MigrationInterface
{
    public function getVersion(): string;

    public function getDescription(): string;

    public function up(\PDO $pdo): void;

    public function down(\PDO $pdo): void;
}
