<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\PartnershipService;

/**
 * Administrative Strategic Partnership & Alliance CRM Controller (ADM-011 / FR-ADM-011).
 * Implements alliance pipeline inspection, DAG stage transitions, internal meeting notes,
 * lead assignment, CSV export, and Super Admin deletion.
 */
class AdminPartnershipController
{
    private PartnershipService $partnershipService;

    public function __construct(PartnershipService $partnershipService)
    {
        $this->partnershipService = $partnershipService;
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
     * GET /api/v1/admin/partnerships
     * GET /api/admin/enquiries/partnerships
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'status'        => $queryParams['status'] ?? null,
            'alliance_type' => $queryParams['alliance_type'] ?? null,
            'search'        => $queryParams['search'] ?? null,
            'assigned_to'   => $queryParams['assigned_to'] ?? null,
            'date_from'     => $queryParams['date_from'] ?? null,
            'date_to'       => $queryParams['date_to'] ?? null,
        ];

        $result = $this->partnershipService->listPartnerships($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/partnerships/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid partnership inquiry ID.");
        }

        $partnership = $this->partnershipService->getPartnership($id);

        return Response::success($partnership, 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/partnerships/:id/status
     */
    public function updateStage(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid partnership inquiry ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $targetStage = (string)($body['status'] ?? $body['stage'] ?? '');
        $notes = isset($body['notes']) ? (string)$body['notes'] : null;
        $assignedUserId = isset($body['assigned_user_id']) ? (int)$body['assigned_user_id'] : null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->partnershipService->updateStage(
            $id,
            $targetStage,
            $notes,
            $assignedUserId,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/partnerships/:id/notes
     */
    public function addNote(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid partnership inquiry ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $note = (string)($body['note'] ?? $body['message'] ?? '');

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->partnershipService->addNote(
            $id,
            $note,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/partnerships/:id/assign
     */
    public function assign(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid partnership inquiry ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $assigneeId = (int)($body['assigned_user_id'] ?? $body['assignee_id'] ?? 0);
        if ($assigneeId <= 0) {
            throw new BadRequestException("Valid assigned_user_id is required.");
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->partnershipService->assign(
            $id,
            $assigneeId,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/partnerships/export
     * GET /api/admin/enquiries/partnerships/export
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'status'        => $queryParams['status'] ?? null,
            'alliance_type' => $queryParams['alliance_type'] ?? null,
            'search'        => $queryParams['search'] ?? null,
        ];

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $csv = $this->partnershipService->exportCsv(
            $filters,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        $filename = 'partnerships_export_' . gmdate('Ymd_His') . '.csv';

        return new Response(200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ], $csv);
    }

    /**
     * DELETE /api/v1/admin/partnerships/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid partnership inquiry ID.");
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $this->partnershipService->deletePartnership(
            $id,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success(['deleted' => true, 'id' => $id], 200, $request->getCorrelationId());
    }
}
