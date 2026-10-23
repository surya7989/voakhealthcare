<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\CareerService;

/**
 * Administrative Careers Requisitions & Job Openings Controller (ADM-013 / FR-ADM-014).
 *
 * Implements job vacancy listing, detail retrieval, creation, updating, and referential-guarded deletion.
 */
class AdminJobController
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
     * GET /api/v1/admin/jobs
     * GET /api/admin/jobs
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'is_active'       => $queryParams['is_active'] ?? $queryParams['status'] ?? null,
            'department'      => $queryParams['department'] ?? null,
            'employment_type' => $queryParams['employment_type'] ?? null,
            'search'          => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $result = $this->careerService->listJobs($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/jobs/:id
     * GET /api/admin/jobs/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid job vacancy ID.");
        }

        $job = $this->careerService->getJobById($id);
        if ($job === null) {
            return Response::error('ERR_NOT_FOUND', "Job posting [{$id}] not found.", 404, $request->getCorrelationId());
        }

        return Response::success($job, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/jobs
     * POST /api/admin/jobs
     */
    public function create(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $clientIp = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $job = $this->careerService->createJob($body, $adminUserId, $clientIp, $userAgent);

        return Response::success($job, 201, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/jobs/:id
     * PATCH /api/v1/admin/jobs/:id
     * PUT /api/admin/jobs/:id
     */
    public function update(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid job vacancy ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $clientIp = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $job = $this->careerService->updateJob($id, $body, $adminUserId, $clientIp, $userAgent);

        return Response::success($job, 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/jobs/:id
     * DELETE /api/admin/jobs/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid job vacancy ID.");
        }

        $clientIp = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $this->careerService->deleteJob($id, $adminUserId, $clientIp, $userAgent);

        return Response::success([
            'id'      => $id,
            'deleted' => true,
            'message' => "Job vacancy [{$id}] successfully removed.",
        ], 200, $request->getCorrelationId());
    }
}
