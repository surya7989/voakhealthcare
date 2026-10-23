<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\CareerService;

/**
 * Administrative Candidate Applications & Clinician Review Desk Controller (ADM-014 / FR-ADM-015).
 *
 * Implements candidate application listing, stage progression (FR-WF-004),
 * authenticated quarantined resume streaming (FR-SEC-004 / TEST-SEC-004), and CSV export.
 */
class AdminApplicationController
{
    private CareerService $careerService;

    public function __construct(CareerService $careerService)
    {
        $this->careerService = $careerService;
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
     * GET /api/v1/admin/applications
     * GET /api/admin/careers/applications
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'job_id'            => $queryParams['job_id'] ?? null,
            'status'            => $queryParams['status'] ?? null,
            'search'            => $queryParams['search'] ?? $queryParams['q'] ?? null,
            'retention_expired' => isset($queryParams['retention_expired']) ? (bool)$queryParams['retention_expired'] : null,
        ];

        $result = $this->careerService->listApplications($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/applications/:id
     * GET /api/admin/careers/applications/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid application ID.");
        }

        $app = $this->careerService->getApplicationById($id);
        if ($app === null) {
            return Response::error('ERR_NOT_FOUND', "Candidate application [{$id}] not found.", 404, $request->getCorrelationId());
        }

        return Response::success($app, 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/applications/:id/status
     * PATCH /api/admin/careers/applications/:id/status
     * Advance candidate recruitment workflow stage (FR-WF-004 / TEST-WF-004).
     */
    public function updateStatus(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid application ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $targetStatus = (string)($body['status'] ?? '');
        $feedback = isset($body['feedback']) ? (string)$body['feedback'] : (isset($body['notes']) ? (string)$body['notes'] : null);
        $rejectionReason = isset($body['rejection_reason']) ? (string)$body['rejection_reason'] : null;

        $clientIp = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->careerService->advanceCandidateStage(
            $id,
            $targetStatus,
            $feedback,
            $rejectionReason,
            $adminUserId,
            $clientIp,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/applications/:id/download
     * GET /api/v1/admin/applications/:id/resume
     * GET /api/admin/careers/applications/:id/resume
     * Authenticated streaming of quarantined resume with security headers (FR-SEC-004 / TEST-SEC-004).
     */
    public function streamResume(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid application ID.");
        }

        return $this->careerService->streamCandidateResume(
            $id,
            $adminUserId,
            $request->getClientIp()
        );
    }

    /**
     * GET /api/v1/admin/applications/export
     * GET /api/admin/careers/applications/export
     * Streams candidates register as CSV with formula injection defense.
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'job_id' => $queryParams['job_id'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'search' => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $clientIp = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $csv = $this->careerService->exportApplicationsCsv($filters, $adminUserId, $clientIp, $userAgent);

        $filename = 'candidate_applications_' . gmdate('Ymd_His') . '.csv';

        return new Response(200, [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'private, no-cache, no-store, must-revalidate',
            'Pragma'                    => 'no-cache',
            'Expires'                   => '0',
            'X-Correlation-ID'          => $request->getCorrelationId(),
        ], $csv);
    }
}
