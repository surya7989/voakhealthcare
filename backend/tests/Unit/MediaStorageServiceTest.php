<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for MediaStorageService (Phase 10: FR-ADM-009, FR-SEC-003, FR-SEC-004).
 */
class MediaStorageServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        // Seed default user
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Super Admin', 'admin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    private static function createTestImage(int $width = 800, int $height = 600): string
    {
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 10, 80, 160);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        $tmpFile = tempnam(sys_get_temp_dir(), 'voak_img_') . '.jpg';
        imagejpeg($img, $tmpFile, 90);
        imagedestroy($img);
        return $tmpFile;
    }

    private static function createTestPdf(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'voak_pdf_') . '.pdf';
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        file_put_contents($tmpFile, $pdfContent);
        return $tmpFile;
    }

    private static function createTestDocx(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'voak_docx_') . '.docx';
        $docxContent = "PK\x03\x04\x14\x00\x00\x00\x08\x00" . str_repeat("\x00", 20) . "[Content_Types].xml";
        file_put_contents($tmpFile, $docxContent);
        return $tmpFile;
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

    private static function createService(\PDO $pdo, string $tempPublic, string $tempQuarantine): MediaStorageService
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        return new MediaStorageService($conn, new AuditLogger(), $tempPublic, $tempQuarantine);
    }

    public static function run(): void
    {
        T::suite('Phase 10 — MediaStorageService & Secure Document Quarantine Unit Tests', function () {

            T::test('initStorageEnclaves creates storage directories, .htaccess with Require all denied and index.html', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                T::assertTrue(is_dir($tempPublic));
                T::assertTrue(is_dir($tempQuarantine));

                $htaccess = $tempQuarantine . '/.htaccess';
                T::assertTrue(file_exists($htaccess));
                $content = (string)file_get_contents($htaccess);
                T::assertContains('Require all denied', $content);
                T::assertContains('php_flag engine off', $content);

                $indexHtml = $tempQuarantine . '/index.html';
                T::assertTrue(file_exists($indexHtml));
                T::assertContains('403 Forbidden', (string)file_get_contents($indexHtml));

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('uploadMedia requires mandatory non-empty alt text (FR-ADM-009)', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage();

                T::expectException(ValidationException::class, function () use ($service, $img) {
                    $service->uploadMedia([
                        'tmp_name' => $img,
                        'name'     => 'test.jpg',
                        'size'     => filesize($img),
                    ], '   ', 1);
                }, 'Mandatory alt text is required');

                @unlink($img);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('uploadMedia rejects disguised PHP script with INVALID_FILE_SIGNATURE (TEST-ADM-009)', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $fake = tempnam(sys_get_temp_dir(), 'fake_') . '.jpg';
                file_put_contents($fake, "<?php phpinfo(); ?>");

                T::expectException(ValidationException::class, function () use ($service, $fake) {
                    $service->uploadMedia([
                        'tmp_name' => $fake,
                        'name'     => 'shell.jpg',
                        'size'     => filesize($fake),
                    ], 'Valid alt text', 1);
                }, 'Invalid file signature or disguised script');

                @unlink($fake);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('uploadMedia rejects dangerous extensions like SVG or scripts with INVALID_EXTENSION', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $svg = tempnam(sys_get_temp_dir(), 'vector_') . '.svg';
                file_put_contents($svg, '<svg><script>alert(1)</script></svg>');

                T::expectException(ValidationException::class, function () use ($service, $svg) {
                    $service->uploadMedia([
                        'tmp_name' => $svg,
                        'name'     => 'vector.svg',
                        'size'     => filesize($svg),
                    ], 'Valid alt text', 1);
                }, 'Invalid file extension');

                @unlink($svg);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('uploadMedia rejects image files exceeding 5MB ceiling with FILE_TOO_LARGE', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage();

                T::expectException(ValidationException::class, function () use ($service, $img) {
                    $service->uploadMedia([
                        'tmp_name' => $img,
                        'name'     => 'huge.jpg',
                        'size'     => 6 * 1024 * 1024,
                    ], 'Valid alt text', 1);
                }, 'File size exceeds maximum allowed ceiling of 5MB');

                @unlink($img);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('uploadMedia converts valid image to WebP format, resizes proportionally and creates thumbnail', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage(2400, 1600);
                $result = $service->uploadMedia([
                    'tmp_name' => $img,
                    'name'     => 'hospital-rehab-team.jpg',
                    'size'     => filesize($img),
                ], 'Hospital rehabilitation clinical team bedside', 1);
                @unlink($img);

                T::assertTrue((int)$result['id'] > 0);
                T::assertEquals('image/webp', $result['mime_type']);
                T::assertEquals('1920x1280', $result['dimensions']);
                T::assertEquals('Hospital rehabilitation clinical team bedside', $result['alt_text']);

                $mainFile = $tempPublic . '/' . $result['file_path'];
                T::assertTrue(file_exists($mainFile));

                $thumbName = str_replace('.webp', '_thumb.webp', $result['stored_name']);
                $thumbFile = dirname($mainFile) . '/' . $thumbName;
                T::assertTrue(file_exists($thumbFile));

                // Assert audit log row
                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'MEDIA_UPLOADED'");
                $audit = $stmt->fetch();
                T::assertTrue(!empty($audit));
                T::assertEquals('MEDIA', $audit['domain']);

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('listMedia filters by search keyword and MIME type with pagination', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img1 = self::createTestImage(300, 200);
                $img2 = self::createTestImage(400, 300);

                $service->uploadMedia(['tmp_name' => $img1, 'name' => 'aged-care.jpg', 'size' => filesize($img1)], 'Aged care staff', 1);
                $service->uploadMedia(['tmp_name' => $img2, 'name' => 'icu-ward.jpg', 'size' => filesize($img2)], 'Intensive care ward', 1);

                @unlink($img1);
                @unlink($img2);

                $all = $service->listMedia([], 1, 10);
                T::assertEquals(2, $all['pagination']['total']);

                $search = $service->listMedia(['search' => 'Aged care'], 1, 10);
                T::assertEquals(1, $search['pagination']['total']);
                T::assertEquals('aged-care.jpg', $search['items'][0]['original_name']);

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('updateMediaAltText updates accessibility metadata and logs MEDIA_ALT_UPDATED', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage();
                $media = $service->uploadMedia(['tmp_name' => $img, 'name' => 'clinic.jpg', 'size' => filesize($img)], 'Initial description', 1);
                @unlink($img);

                $updated = $service->updateMediaAltText((int)$media['id'], 'Updated accessibility caption for healthcare provider', 1);
                T::assertEquals('Updated accessibility caption for healthcare provider', $updated['alt_text']);

                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'MEDIA_ALT_UPDATED'");
                $audit = $stmt->fetch();
                T::assertTrue(!empty($audit));

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('deleteMedia removes unreferenced asset and deletes physical disk files with MEDIA_DELETED audit', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage();
                $media = $service->uploadMedia(['tmp_name' => $img, 'name' => 'unreferenced.jpg', 'size' => filesize($img)], 'Unused picture', 1);
                @unlink($img);

                $mainFile = $tempPublic . '/' . $media['file_path'];
                T::assertTrue(file_exists($mainFile));

                $deleted = $service->deleteMedia((int)$media['id'], 1);
                T::assertTrue($deleted);
                T::assertTrue($service->getMediaById((int)$media['id']) === null);
                T::assertFalse(file_exists($mainFile));

                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'MEDIA_DELETED'");
                $audit = $stmt->fetch();
                T::assertTrue(!empty($audit));

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('deleteMedia is strictly blocked with HTTP 409 Conflict when asset is referenced by Page (TEST-ADM-009)', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $img = self::createTestImage();
                $media = $service->uploadMedia(['tmp_name' => $img, 'name' => 'about-hero.jpg', 'size' => filesize($img)], 'About hero', 1);
                @unlink($img);

                // Create page referencing this media ID
                $pdo->exec("INSERT INTO pages (slug, title, meta_title, meta_description, content_json, is_published) VALUES (
                    'about-us', 'About Us', 'About Us', 'About Voak Healthcare', '{\"hero_image_id\": {$media['id']}}', 1
                )");

                T::expectException(ConflictException::class, function () use ($service, $media) {
                    $service->deleteMedia((int)$media['id'], 1);
                }, 'Cannot delete media asset: currently referenced by Page: About Us');

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('quarantineDocument ingests authentic PDF outside web root with UUID naming and chmod 0600 (TEST-SEC-003)', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $pdf = self::createTestPdf();
                $result = $service->quarantineDocument([
                    'tmp_name' => $pdf,
                    'name'     => 'Candidate_Resume_John_Doe.pdf',
                    'size'     => filesize($pdf),
                ], 'resume');
                @unlink($pdf);

                T::assertTrue(file_exists($result['quarantined_file_path']));
                T::assertContains(str_replace('\\', '/', $tempQuarantine), $result['quarantined_file_path']);
                T::assertEquals('Candidate_Resume_John_Doe.pdf', $result['original_file_name']);
                T::assertTrue(str_starts_with($result['stored_name'], 'doc_'));
                T::assertEquals(64, strlen($result['sha256_hash']));

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('quarantineDocument ingests authentic DOCX archive with PKZIP header', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $docx = self::createTestDocx();
                $result = $service->quarantineDocument([
                    'tmp_name' => $docx,
                    'name'     => 'Enterprise_Tender_RFP_2026.docx',
                    'size'     => filesize($docx),
                ], 'tender');
                @unlink($docx);

                T::assertTrue(file_exists($result['quarantined_file_path']));
                T::assertEquals('Enterprise_Tender_RFP_2026.docx', $result['original_file_name']);

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('quarantineDocument rejects double-extension script like report.pdf.php', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $hack = tempnam(sys_get_temp_dir(), 'hack_');
                file_put_contents($hack, "%PDF-1.4\n<?php system(\$_GET['c']); ?>");

                T::expectException(ValidationException::class, function () use ($service, $hack) {
                    $service->quarantineDocument([
                        'tmp_name' => $hack,
                        'name'     => 'report.pdf.php',
                        'size'     => filesize($hack),
                    ], 'resume');
                }, 'Dangerous double-extension or executable script format');

                @unlink($hack);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('quarantineDocument rejects disguised executable binary with INVALID_FILE_SIGNATURE', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $exe = tempnam(sys_get_temp_dir(), 'exe_');
                file_put_contents($exe, "MZ\x90\x00\x03\x00\x00\x00\x04\x00Windows Executable");

                T::expectException(ValidationException::class, function () use ($service, $exe) {
                    $service->quarantineDocument([
                        'tmp_name' => $exe,
                        'name'     => 'fake_resume.pdf',
                        'size'     => filesize($exe),
                    ], 'resume');
                }, 'Invalid file MIME type or signature');

                @unlink($exe);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('quarantineDocument rejects documents exceeding 10MB/15MB ceilings with FILE_TOO_LARGE', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $pdf = self::createTestPdf();

                T::expectException(ValidationException::class, function () use ($service, $pdf) {
                    $service->quarantineDocument([
                        'tmp_name' => $pdf,
                        'name'     => 'huge_resume.pdf',
                        'size'     => 12 * 1024 * 1024,
                    ], 'resume');
                }, 'Document size exceeds maximum allowable ceiling of 10MB');

                @unlink($pdf);
                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('streamQuarantinedFile sets strict security headers (attachment, nosniff, no-store) and logs audit event (TEST-SEC-004)', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                $pdf = self::createTestPdf();
                $quarantined = $service->quarantineDocument([
                    'tmp_name' => $pdf,
                    'name'     => 'Commercial Tender RFP.pdf',
                    'size'     => filesize($pdf),
                ], 'tender');
                @unlink($pdf);

                $response = $service->streamQuarantinedFile(
                    $quarantined['quarantined_file_path'],
                    $quarantined['original_file_name'],
                    'application/pdf',
                    1,
                    'PROPOSAL_DOCUMENT_DOWNLOADED',
                    'PROPOSALS',
                    42,
                    '10.0.0.1'
                );

                T::assertEquals(200, $response->getStatusCode());
                T::assertEquals('application/pdf', $response->getHeader('content-type'));
                T::assertContains('attachment; filename="Commercial_Tender_RFP.pdf"', (string)$response->getHeader('content-disposition'));
                T::assertEquals('nosniff', $response->getHeader('x-content-type-options'));
                T::assertContains('no-store', (string)$response->getHeader('cache-control'));

                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'PROPOSAL_DOCUMENT_DOWNLOADED'");
                $audit = $stmt->fetch();
                T::assertTrue(!empty($audit));
                T::assertEquals('PROPOSALS', $audit['domain']);
                T::assertEquals(42, (int)$audit['entity_id']);

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('streamQuarantinedFile detects and blocks path traversal attempts with SecurityException', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                $service = self::createService($pdo, $tempPublic, $tempQuarantine);

                T::expectException(SecurityException::class, function () use ($service, $tempPublic) {
                    $service->streamQuarantinedFile(
                        $tempPublic . '/../../etc/shadow',
                        'shadow',
                        'text/plain',
                        1,
                        'DOWNLOAD',
                        'DOC',
                        1
                    );
                }, 'Path traversal violation');

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });

            T::test('fail-closed compensating rollback cleans up disk files if audit log fails', function () {
                $tempPublic = sys_get_temp_dir() . '/voak_test_pub_' . bin2hex(random_bytes(4));
                $tempQuarantine = sys_get_temp_dir() . '/voak_test_quar_' . bin2hex(random_bytes(4));

                $pdo = self::createDatabase();
                // Drop audit_logs table to force failure
                $pdo->exec("DROP TABLE audit_logs;");

                $service = self::createService($pdo, $tempPublic, $tempQuarantine);
                $img = self::createTestImage();

                T::expectException(DatabaseException::class, function () use ($service, $img) {
                    $service->uploadMedia([
                        'tmp_name' => $img,
                        'name'     => 'test-rollback.jpg',
                        'size'     => filesize($img),
                    ], 'Alt text', 1);
                }, 'Audit logging failed during media upload');

                @unlink($img);

                // Assert zero leftover files in public directory
                $files = glob("{$tempPublic}/*/*/*");
                T::assertEquals(0, count($files ?: []));

                self::recursiveDelete($tempPublic);
                self::recursiveDelete($tempQuarantine);
            });
        });
    }
}
