<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\CaseStudyService;

/**
 * Administrative Case Studies & Social Proof Controller (ADM-015 / FR-ADM-016).
 *
 * Implements case study management, Problem-Intervention-Outcome metrics,
 * mandatory verified client legal consent release guard, and CSV export.
 */
class AdminCaseStudyController
{
    private CaseStudyService $caseStudyService;

    public function __construct(CaseStudyService $caseStudyService)
    {
        $this->caseStudyService = $caseStudyService;
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
     * GET /api/v1/admin/case-studies
     * GET /api/admin/case-studies
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'sector'           => $queryParams['sector'] ?? $queryParams['client_sector'] ?? null,
            'is_published'     => $queryParams['is_published'] ?? $queryParams['status'] ?? null,
            'consent_verified' => $queryParams['consent_verified'] ?? null,
            'search'           => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $result = $this->caseStudyService->getCaseStudies($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/case-studies/export
     * GET /api/admin/case-studies/export
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'sector'           => $queryParams['sector'] ?? null,
            'is_published'     => $queryParams['is_published'] ?? null,
            'consent_verified' => $queryParams['consent_verified'] ?? null,
            'search'           => $queryParams['search'] ?? null,
        ];

        $csv = $this->caseStudyService->exportCsv($filters, $adminUserId);

        return new Response(200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="case_studies_export_' . gmdate('Ymd_His') . '.csv"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ], $csv);
    }

    /**
     * GET /api/v1/admin/case-studies/:id
     * GET /api/admin/case-studies/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid case study ID.");
        }

        $caseStudy = $this->caseStudyService->getCaseStudyById($id);

        return Response::success($caseStudy, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/case-studies
     * POST /api/admin/case-studies
     */
    public function create(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $caseStudy = $this->caseStudyService->createCaseStudy($body, $adminUserId);

        return Response::success($caseStudy, 201, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/case-studies/:id
     * PATCH /api/v1/admin/case-studies/:id
     * PUT /api/admin/case-studies/:id
     * PATCH /api/admin/case-studies/:id
     */
    public function update(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid case study ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $caseStudy = $this->caseStudyService->updateCaseStudy($id, $body, $adminUserId);

        return Response::success($caseStudy, 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/case-studies/:id
     * DELETE /api/admin/case-studies/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid case study ID.");
        }

        $this->caseStudyService->deleteCaseStudy($id, $adminUserId);

        return Response::success(['message' => "Case study [{$id}] has been archived."], 200, $request->getCorrelationId());
    }
}
