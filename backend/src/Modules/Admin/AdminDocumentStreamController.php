<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Container;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Services\MediaStorageService;

/**
 * Controller for Authenticated Document Streaming (FR-SEC-004 / TEST-SEC-004).
 * Coordinates memory-safe streaming downloads of quarantined tender documents and candidate resumes
 * with object-level authorization, anti-sniff headers, and cryptographic audit records.
 */
class AdminDocumentStreamController
{
    private Connection $connection;
    private ?MediaStorageService $mediaService;
    private ?Container $container;

    public function __construct(Connection $connection, ?MediaStorageService $mediaService = null, ?Container $container = null)
    {
        $this->connection = $connection;
        $this->mediaService = $mediaService;
        $this->container = $container;
    }

    private function getMediaService(): MediaStorageService
    {
        if ($this->container !== null && $this->container->has(MediaStorageService::class)) {
            return $this->container->get(MediaStorageService::class);
        }
        if ($this->mediaService !== null) {
            return $this->mediaService;
        }
        throw new \RuntimeException("MediaStorageService not configured.");
    }

    /**
     * Extracts authenticated admin user ID, strictly failing closed if identity is missing or invalid.
     */
    private function requireAdminUserId(Request $request): int
    {
        $userId = $request->getAttribute('admin_user_id');
        if ($userId === null || (int)$userId <= 0) {
            throw new AuthenticationException("Authentication required. No authenticated administrative user identity present.");
        }
        return (int)$userId;
    }

    /**
     * GET /api/v1/admin/proposals/:id/document
     * GET /api/admin/enquiries/proposals/:id/document
     */
    public function streamProposal(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT id, quarantined_file_path, original_file_name FROM proposals WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $proposal = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$proposal) {
            throw new NotFoundException("Proposal [{$id}] not found.");
        }

        $filePath = (string)$proposal['quarantined_file_path'];
        $originalName = (string)$proposal['original_file_name'];

        return $this->getMediaService()->streamQuarantinedFile(
            $filePath,
            $originalName,
            'application/pdf',
            $userId,
            'PROPOSAL_DOCUMENT_DOWNLOADED',
            'PROPOSALS',
            $id,
            $request->getClientIp()
        );
    }

    /**
     * GET /api/v1/admin/careers/applications/:id/resume
     * GET /api/admin/careers/applications/:id/resume
     */
    public function streamResume(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid application ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT id, quarantined_resume_path, original_resume_name FROM career_applications WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $application = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$application) {
            throw new NotFoundException("Career application [{$id}] not found.");
        }

        $filePath = (string)$application['quarantined_resume_path'];
        $originalName = (string)$application['original_resume_name'];

        return $this->getMediaService()->streamQuarantinedFile(
            $filePath,
            $originalName,
            'application/pdf',
            $userId,
            'RESUME_DOWNLOADED',
            'RECRUITMENT',
            $id,
            $request->getClientIp()
        );
    }

    /**
     * GET /api/v1/admin/documents/:id/stream
     * General streaming proxy for quarantined documents.
     */
    public function streamDocument(Request $request): Response
    {
        $rawId = (string)($request->getAttribute('id') ?? '');
        if ($rawId === '') {
            throw new BadRequestException("Invalid document identifier.");
        }

        $userId = $this->requireAdminUserId($request);
        $pdo = $this->connection->getPdo();

        // 1. Try finding in proposals by ID or reference code
        $stmt = $pdo->prepare(
            "SELECT id, quarantined_file_path, original_file_name FROM proposals
             WHERE id = :idInt OR reference_code = :ref OR quarantined_file_path LIKE :pattern"
        );
        $stmt->execute([
            'idInt'   => is_numeric($rawId) ? (int)$rawId : 0,
            'ref'     => $rawId,
            'pattern' => "%{$rawId}%",
        ]);
        $match = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($match) {
            return $this->getMediaService()->streamQuarantinedFile(
                (string)$match['quarantined_file_path'],
                (string)$match['original_file_name'],
                'application/pdf',
                $userId,
                'DOCUMENT_DOWNLOADED',
                'PROPOSALS',
                (int)$match['id'],
                $request->getClientIp()
            );
        }

        // 2. Try finding in career_applications by ID or reference code
        $stmt2 = $pdo->prepare(
            "SELECT id, quarantined_resume_path, original_resume_name FROM career_applications
             WHERE id = :idInt OR reference_code = :ref OR quarantined_resume_path LIKE :pattern"
        );
        $stmt2->execute([
            'idInt'   => is_numeric($rawId) ? (int)$rawId : 0,
            'ref'     => $rawId,
            'pattern' => "%{$rawId}%",
        ]);
        $match2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $stmt2->closeCursor();

        if ($match2) {
            return $this->getMediaService()->streamQuarantinedFile(
                (string)$match2['quarantined_resume_path'],
                (string)$match2['original_resume_name'],
                'application/pdf',
                $userId,
                'DOCUMENT_DOWNLOADED',
                'RECRUITMENT',
                (int)$match2['id'],
                $request->getClientIp()
            );
        }

        // 3. Fallback: Search filesystem if rawId matches sanitized filename pattern
        if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $rawId)) {
            $quarantineDir = $this->getMediaService()->getQuarantineStorageDir();
            $matches = glob("{$quarantineDir}/*/*/{$rawId}*");
            if (!empty($matches) && file_exists($matches[0])) {
                return $this->getMediaService()->streamQuarantinedFile(
                    $matches[0],
                    basename($matches[0]),
                    'application/pdf',
                    $userId,
                    'DOCUMENT_DOWNLOADED',
                    'DOCUMENTS',
                    0,
                    $request->getClientIp()
                );
            }
        }

        throw new NotFoundException("Quarantined document [{$rawId}] not found.");
    }
}
