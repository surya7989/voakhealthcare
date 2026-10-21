<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Tests\TestFramework as T;

class RbacManagerTest
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
        T::suite('Phase 6 — RBAC Manager & 45-Permission Matrix Tests', function () {
            T::test('seedRbac populates all 5 canonical roles and 45 permissions idempotently', function () {
                $pdo = self::createDatabase();
                $rbac = new RbacManager();

                $rbac->seedRbac($pdo);

                // Check 5 roles
                $roles = $pdo->query('SELECT slug FROM roles')->fetchAll(\PDO::FETCH_COLUMN);
                T::assertEquals(5, count($roles));
                T::assertTrue(in_array(RbacManager::ROLE_SUPER_ADMIN, $roles, true));
                T::assertTrue(in_array(RbacManager::ROLE_WEBSITE_ADMIN, $roles, true));
                T::assertTrue(in_array(RbacManager::ROLE_CONTENT_EDITOR, $roles, true));
                T::assertTrue(in_array(RbacManager::ROLE_BD_MANAGER, $roles, true));
                T::assertTrue(in_array(RbacManager::ROLE_HR_MANAGER, $roles, true));

                // Check permissions
                $perms = $pdo->query('SELECT slug FROM permissions')->fetchAll(\PDO::FETCH_COLUMN);
                T::assertEquals(count(RbacManager::PERMISSIONS), count($perms));
                T::assertTrue(count($perms) >= 45);

                // Run seed again to verify idempotency
                $rbac->seedRbac($pdo);
                $permsRepeat = $pdo->query('SELECT slug FROM permissions')->fetchAll(\PDO::FETCH_COLUMN);
                T::assertEquals(count(RbacManager::PERMISSIONS), count($permsRepeat));
            });

            T::test('Super Admin has unrestricted authority over all permissions', function () {
                $pdo = self::createDatabase();
                $rbac = new RbacManager();
                $rbac->seedRbac($pdo);

                // Create a super admin user
                $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (1, 'Super Admin', 'superadmin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);

                $roles = $rbac->getUserRoles($pdo, 1);
                T::assertTrue(in_array(RbacManager::ROLE_SUPER_ADMIN, $roles, true));

                // Super admin possesses all permissions
                $perms = $rbac->getUserPermissions($pdo, 1);
                T::assertEquals(count(RbacManager::PERMISSIONS), count($perms));
                T::assertTrue($rbac->hasPermission($pdo, 1, 'user:list'));
                T::assertTrue($rbac->hasPermission($pdo, 1, 'user:invite'));
                T::assertTrue($rbac->hasPermission($pdo, 1, 'settings:update'));
                T::assertTrue($rbac->hasPermission($pdo, 1, 'audit:read'));
            });

            T::test('enforces role-based permission boundaries strictly for least privilege', function () {
                $pdo = self::createDatabase();
                $rbac = new RbacManager();
                $rbac->seedRbac($pdo);

                // Create Content Editor
                $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (2, 'Content Editor', 'editor@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);

                // Content editor should have page:read, page:update, media:upload
                T::assertTrue($rbac->hasPermission($pdo, 2, 'page:read'));
                T::assertTrue($rbac->hasPermission($pdo, 2, 'page:update'));
                T::assertTrue($rbac->hasPermission($pdo, 2, 'media:upload'));

                // Content editor MUST NOT have user management or settings permissions
                T::assertFalse($rbac->hasPermission($pdo, 2, 'user:list'));
                T::assertFalse($rbac->hasPermission($pdo, 2, 'user:invite'));
                T::assertFalse($rbac->hasPermission($pdo, 2, 'settings:update'));
                T::assertFalse($rbac->hasPermission($pdo, 2, 'audit:read'));

                // Create HR Manager
                $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (3, 'HR Manager', 'hr@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $rbac->assignRole($pdo, 3, RbacManager::ROLE_HR_MANAGER);

                T::assertTrue($rbac->hasPermission($pdo, 3, 'job:create'));
                T::assertTrue($rbac->hasPermission($pdo, 3, 'candidate:list'));
                T::assertFalse($rbac->hasPermission($pdo, 3, 'service:delete'));
                T::assertFalse($rbac->hasPermission($pdo, 3, 'user:invite'));
            });
        });
    }
}
