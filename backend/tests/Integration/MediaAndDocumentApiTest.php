<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Integration Test Suite for Phase 10: Media Library & Document Quarantine API (FR-ADM-009, FR-SEC-003, FR-SEC-004).
 */
class MediaAndDocumentApiTest
{
    private static function createTestImage(): string
    {
        $img = imagecreatetruecolor(400, 300);
        $bg = imagecolorallocate($img, 20, 100, 180);
        imagefilledrectangle($img, 0, 0, 400, 300, $bg);
        $tmp = tempnam(sys_get_temp_dir(), 'api_img_') . '.jpg';
        imagejpeg($img, $tmp, 90);
        imagedestroy($img);
        return $tmp;
    }

    private static function createTestPdf(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'api_pdf_') . '.pdf';
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>%%EOF\n");
        return $tmp;
    }

    private static function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Helper to authenticate an admin user and return session cookie + CSRF token.
     */
    private static function loginUser(App $app, string $email, string $password): array
    {
        // 1. Get bootstrap CSRF
        $csrfReq = new Request('GET', '/api/v1/admin/auth/csrf');
        $csrfRes = $app->handle($csrfReq);
        $csrfData = json_decode($csrfRes->getBody(), true);
        $bootstrapToken = (string)($csrfData['data']['csrf_token'] ?? '');
        $cookieHeader = (string)($csrfRes->getHeader('Set-Cookie') ?? '');
        preg_match('/voak_csrf=([^;]+)/', $cookieHeader, $matches);
        $bootstrapCookie = $matches[1] ?? '';

        // 2. Perform login
        $loginReq = new Request(
            'POST',
            '/api/v1/admin/auth/login',
            [],
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $bootstrapToken,
                'Cookie'       => "voak_csrf={$bootstrapCookie}",
            ],
            json_encode(['email' => $email, 'password' => $password])
        );

        $loginRes = $app->handle($loginReq);
        $loginData = json_decode($loginRes->getBody(), true);
        $sessionCookieHeader = (string)($loginRes->getHeader('Set-Cookie') ?? '');
        preg_match('/voak_admin_session=([^;]+)/', $sessionCookieHeader, $sessionMatches);
        $sessionToken = $sessionMatches[1] ?? '';

        $sessionCsrf = (string)($loginData['data']['session']['csrf_token'] ?? '');

        return [
            'session_cookie' => $sessionToken,
            'csrf_token'     => $sessionCsrf,
        ];
    }

    public static function run(): void
    {
        T::suite('Phase 10 — Media Library & Secure Document Quarantine API Tests', function () {

            $tempPublicDir = sys_get_temp_dir() . '/voak_api_pub_' . bin2hex(random_bytes(4));
            $tempQuarantineDir = sys_get_temp_dir() . '/voak_api_quar_' . bin2hex(random_bytes(4));

            $pdo = new \PDO('sqlite::memory:', null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("PRAGMA foreign_keys = ON;");

            $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
            $manager = new MigrationManager($migrationsDir, 'migrations');
            $manager->migrateUp($pdo);

            $hasher = new PasswordHasher();
            $adminHash = $hasher->hash('VoakAdmin#2026!Secure');
            $editorHash = $hasher->hash('VoakEditor#2026!Secure');
            $bdHash = $hasher->hash('VoakBd#2026!Secure');
            $hrHash = $hasher->hash('VoakHr#2026!Secure');

            // Super Admin
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (1, 'Super Admin', 'admin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            // Content Editor
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (2, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            // BD Manager
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (3, 'BD Manager', 'bd@voak.test', '{$bdHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            // HR Manager
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (4, 'HR Manager', 'hr@voak.test', '{$hrHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            $rbac = new RbacManager();
            $rbac->seedRbac($pdo);
            $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
            $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);
            $rbac->assignRole($pdo, 3, RbacManager::ROLE_BD_MANAGER);
            $rbac->assignRole($pdo, 4, RbacManager::ROLE_HR_MANAGER);

            $config = new Config([
                'APP_KEY'            => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
                'AUTH_SIGNING_KEY'   => 'auth-signing-key-32-characters-min!!',
                'MFA_ENCRYPTION_KEY' => 'mfa-encryption-key-32-characters-min!!',
                'CSRF_SIGNING_KEY'   => 'csrf-signing-key-32-characters-min!',
                'APP_ENV'            => 'testing',
                'APP_DEBUG'          => true,
            ]);

            $app = new App($config);
            /** @var Connection $conn */
            $conn = $app->getContainer()->get(Connection::class);
            $conn->setMockPdo($pdo);

            $mediaService = new MediaStorageService($conn, new AuditLogger(), $tempPublicDir, $tempQuarantineDir);
            $app->getContainer()->set(MediaStorageService::class, $mediaService);

            \Voak\Health\Middleware\RateLimitMiddleware::reset();

            // Log in users to acquire authentic session cookies and CSRF tokens
            $super = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');
            $editor = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
            $bd = self::loginUser($app, 'bd@voak.test', 'VoakBd#2026!Secure');
            $hr = self::loginUser($app, 'hr@voak.test', 'VoakHr#2026!Secure');

            T::test('Public delivery endpoint returns sanitized public media metadata with dimensions and alt text', function () use ($app, $mediaService) {
                $img = self::createTestImage();
                $uploaded = $mediaService->uploadMedia([
                    'tmp_name' => $img,
                    'name'     => 'public-photo.jpg',
                    'size'     => filesize($img),
                ], 'Public accessible image', 1);
                @unlink($img);

                $request = new Request('GET', "/api/v1/public/media/{$uploaded['id']}");
                $response = $app->handle($request);

                T::assertEquals(200, $response->getStatusCode());
                $body = json_decode($response->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals($uploaded['id'], $body['data']['media']['id']);
                T::assertEquals('Public accessible image', $body['data']['media']['alt_text']);
                T::assertTrue(!empty($body['data']['media']['url']));
                T::assertTrue(!empty($body['data']['media']['thumb_url']));
                T::assertFalse(isset($body['data']['media']['uploaded_by_user_id']));
            });

            T::test('Fail-closed authentication: unauthenticated requests on admin media endpoints return HTTP 401', function () use ($app) {
                $req1 = new Request('POST', '/api/v1/admin/media/upload');
                T::assertEquals(401, $app->handle($req1)->getStatusCode());

                $req2 = new Request('GET', '/api/v1/admin/media');
                T::assertEquals(401, $app->handle($req2)->getStatusCode());

                $req3 = new Request('DELETE', '/api/v1/admin/media/1');
                T::assertEquals(401, $app->handle($req3)->getStatusCode());
            });

            T::test('CSRF protection: mutating requests without X-CSRF-Token return HTTP 403 ERR_CSRF_MISSING', function () use ($app, $super) {
                $request = (new Request('POST', '/api/v1/admin/media/upload', [], [
                    'cookie' => "voak_admin_session={$super['session_cookie']}",
                ]));

                $response = $app->handle($request);
                T::assertEquals(403, $response->getStatusCode());
                $body = json_decode($response->getBody(), true);
                T::assertEquals('ERR_CSRF_MISSING', $body['error']['code']);
            });

            T::test('Least-privilege RBAC: Content Editor can upload image and update alt-text (201 & 200)', function () use ($app, $editor) {
                $img = self::createTestImage();

                $request = (new Request('POST', '/api/v1/admin/media/upload', [], [
                    'x-csrf-token' => $editor['csrf_token'],
                    'cookie'       => "voak_admin_session={$editor['session_cookie']}",
                ]))
                    ->withUploadedFiles([
                        'file' => [
                            'tmp_name' => $img,
                            'name'     => 'editor-photo.jpg',
                            'size'     => filesize($img),
                        ],
                    ])
                    ->withParsedBody([
                        'alt_text' => 'Content editor upload',
                    ]);

                $response = $app->handle($request);
                @unlink($img);

                T::assertEquals(201, $response->getStatusCode());
                $body = json_decode($response->getBody(), true);
                T::assertTrue($body['success']);
                $mediaId = (int)$body['data']['media']['id'];

                // Update alt text
                $updateReq = (new Request('PATCH', "/api/v1/admin/media/{$mediaId}", [], [
                    'x-csrf-token' => $editor['csrf_token'],
                    'cookie'       => "voak_admin_session={$editor['session_cookie']}",
                    'content-type' => 'application/json',
                ], json_encode(['alt_text' => 'Updated alt description'])));

                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());
            });

            T::test('Least-privilege RBAC: Content Editor cannot delete media assets (HTTP 403 ERR_FORBIDDEN)', function () use ($app, $editor, $mediaService) {
                $img = self::createTestImage();
                $media = $mediaService->uploadMedia([
                    'tmp_name' => $img,
                    'name'     => 'delete-test.jpg',
                    'size'     => filesize($img),
                ], 'Delete candidate', 1);
                @unlink($img);

                $delReq = (new Request('DELETE', "/api/v1/admin/media/{$media['id']}", [], [
                    'x-csrf-token' => $editor['csrf_token'],
                    'cookie'       => "voak_admin_session={$editor['session_cookie']}",
                ]));

                $response = $app->handle($delReq);
                T::assertEquals(403, $response->getStatusCode());
                $body = json_decode($response->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $body['error']['code']);
            });

            T::test('Least-privilege RBAC: Content Editor cannot download proposal tenders or resumes (HTTP 403 ERR_FORBIDDEN)', function () use ($app, $editor) {
                $req1 = (new Request('GET', '/api/v1/admin/proposals/1/document', [], [
                    'cookie' => "voak_admin_session={$editor['session_cookie']}",
                ]));

                T::assertEquals(403, $app->handle($req1)->getStatusCode());

                $req2 = (new Request('GET', '/api/v1/admin/careers/applications/1/resume', [], [
                    'cookie' => "voak_admin_session={$editor['session_cookie']}",
                ]));

                T::assertEquals(403, $app->handle($req2)->getStatusCode());
            });

            T::test('Least-privilege RBAC: BD Manager can stream proposal tender attachment (HTTP 200)', function () use ($app, $bd, $pdo, $mediaService) {
                $pdf = self::createTestPdf();
                $quarantined = $mediaService->quarantineDocument([
                    'tmp_name' => $pdf,
                    'name'     => 'Hospital_Alliance_Proposal.pdf',
                    'size'     => filesize($pdf),
                ], 'tender');
                @unlink($pdf);

                $stmt = $pdo->prepare(
                    "INSERT INTO proposals (
                        reference_code, organization_name, contact_name, contact_email,
                        contact_phone, facility_type, timeline, budget_band,
                        quarantined_file_path, original_file_name, file_size_bytes
                    ) VALUES (
                        'PROP-TEST-002', 'Monash Health', 'Director Jane', 'jane@monash.test',
                        '0400111222', 'Hospital', 'Immediate', 'Enterprise',
                        :path, :name, :size
                    )"
                );
                $stmt->execute([
                    'path' => $quarantined['quarantined_file_path'],
                    'name' => $quarantined['original_file_name'],
                    'size' => $quarantined['file_size_bytes'],
                ]);
                $propId = (int)$pdo->lastInsertId();

                $req = (new Request('GET', "/api/v1/admin/proposals/{$propId}/document", [], [
                    'cookie' => "voak_admin_session={$bd['session_cookie']}",
                ]));

                $response = $app->handle($req);
                T::assertEquals(200, $response->getStatusCode());
                T::assertEquals('application/pdf', $response->getHeader('content-type'));
                T::assertContains('Hospital_Alliance_Proposal.pdf', (string)$response->getHeader('content-disposition'));
            });

            T::test('Least-privilege RBAC: HR Manager can stream candidate resume attachment (HTTP 200)', function () use ($app, $hr, $pdo, $mediaService) {
                $pdf = self::createTestPdf();
                $quarantined = $mediaService->quarantineDocument([
                    'tmp_name' => $pdf,
                    'name'     => 'Clinical_Specialist_Resume.pdf',
                    'size'     => filesize($pdf),
                ], 'resume');
                @unlink($pdf);

                $pdo->exec("INSERT INTO job_postings (title, slug, department, location, employment_type, summary, responsibilities_json, requirements_json, created_by_user_id)
                    VALUES ('Clinical Director', 'clinical-director-syd', 'Medical', 'Sydney', 'FULL_TIME', 'Summary description', '[\"Lead team\"]', '[\"AHPRA\"]', 1)");
                $jobId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    "INSERT INTO career_applications (
                        reference_code, job_id, full_name, email, phone,
                        quarantined_resume_path, original_resume_name, retention_expiry_date
                    ) VALUES (
                        'APP-2026-002', :jobId, 'Dr. Robert Shaw', 'shaw@hospital.test', '0422222222',
                        :path, :name, date('now', '+6 months')
                    )"
                );
                $stmt->execute([
                    'jobId' => $jobId,
                    'path'  => $quarantined['quarantined_file_path'],
                    'name'  => $quarantined['original_file_name'],
                ]);
                $appId = (int)$pdo->lastInsertId();

                $req = (new Request('GET', "/api/v1/admin/careers/applications/{$appId}/resume", [], [
                    'cookie' => "voak_admin_session={$hr['session_cookie']}",
                ]));

                $response = $app->handle($req);
                T::assertEquals(200, $response->getStatusCode());
                T::assertEquals('application/pdf', $response->getHeader('content-type'));
                T::assertContains('Clinical_Specialist_Resume.pdf', (string)$response->getHeader('content-disposition'));
            });

            T::test('Referential integrity deletion guard returns HTTP 409 Conflict when media is referenced by Page (TEST-ADM-009)', function () use ($app, $super, $pdo, $mediaService) {
                $img = self::createTestImage();
                $media = $mediaService->uploadMedia([
                    'tmp_name' => $img,
                    'name'     => 'why-choose-us.jpg',
                    'size'     => filesize($img),
                ], 'Why Choose Us hero', 1);
                @unlink($img);

                // Reference in page
                $pdo->exec("INSERT INTO pages (slug, title, meta_title, meta_description, content_json, is_published) VALUES (
                    'why-choose-us', 'Why Choose Us', 'Why Choose Us', 'Meta description', '{\"hero_image_id\": {$media['id']}}', 1
                )");

                $delReq = (new Request('DELETE', "/api/v1/admin/media/{$media['id']}", [], [
                    'x-csrf-token' => $super['csrf_token'],
                    'cookie'       => "voak_admin_session={$super['session_cookie']}",
                ]));

                $response = $app->handle($delReq);
                T::assertEquals(409, $response->getStatusCode());
                $body = json_decode($response->getBody(), true);
                T::assertEquals('ERR_MEDIA_IN_USE', $body['error']['code']);
                T::assertContains('Cannot delete media asset: currently referenced by Page: Why Choose Us', $body['error']['message']);
            });

            T::test('Quarantine directory .htaccess strictly blocks direct web access (TEST-SEC-004)', function () use ($tempQuarantineDir) {
                $htaccess = $tempQuarantineDir . '/.htaccess';
                T::assertTrue(file_exists($htaccess));
                $content = (string)file_get_contents($htaccess);

                T::assertContains('Require all denied', $content);
                T::assertContains('php_flag engine off', $content);
            });

            self::recursiveDelete($tempPublicDir);
            self::recursiveDelete($tempQuarantineDir);
        });
    }
}
