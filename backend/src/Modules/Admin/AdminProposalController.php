<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\ProposalService;

/**
 * Administrative Enterprise Proposal & RFP Management Controller (ADM-012 / FR-ADM-012).
 * Implements proposal inspection, tender streaming proxy, commercial value attachment,
 * DAG status progression, proposal lead assignment, CSV export, and Super Admin deletion.
 */
class AdminProposalController
{
    private ProposalService $proposalService;

    public function __construct(ProposalService $proposalService)
    {
        $this->proposalService = $proposalService;
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
     * GET /api/v1/admin/proposals
     * GET /api/admin/enquiries/proposals
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'status'           => $queryParams['status'] ?? null,
            'facility_type'    => $queryParams['facility_type'] ?? null,
            'search'           => $queryParams['search'] ?? null,
            'assigned_lead_id' => $queryParams['assigned_lead_id'] ?? null,
            'date_from'        => $queryParams['date_from'] ?? null,
            'date_to'          => $queryParams['date_to'] ?? null,
        ];

        $result = $this->proposalService->listProposals($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/proposals/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $proposal = $this->proposalService->getProposal($id);

        return Response::success($proposal, 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/proposals/:id/status
     */
    public function updateStatus(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $targetStatus = (string)($body['status'] ?? '');
        $estimatedCommercialValue = isset($body['estimated_commercial_value']) ? (float)$body['estimated_commercial_value'] : null;
        $notes = isset($body['notes']) ? (string)$body['notes'] : null;
        $assignedLeadId = isset($body['assigned_lead_id']) ? (int)$body['assigned_lead_id'] : null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->proposalService->updateStatus(
            $id,
            $targetStatus,
            $estimatedCommercialValue,
            $notes,
            $assignedLeadId,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/proposals/:id/assign
     */
    public function assignLead(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $leadId = (int)($body['assigned_lead_id'] ?? $body['lead_id'] ?? 0);
        if ($leadId <= 0) {
            throw new BadRequestException("Valid assigned_lead_id is required.");
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $updated = $this->proposalService->assignLead(
            $id,
            $leadId,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success($updated, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/proposals/:id/download
     * GET /api/v1/admin/proposals/:id/document
     * GET /api/admin/enquiries/proposals/:id/document
     */
    public function streamDocument(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $ipAddress = $request->getClientIp();

        return $this->proposalService->streamTenderAttachment($id, $adminUserId, $ipAddress);
    }

    /**
     * GET /api/v1/admin/proposals/export
     * GET /api/admin/enquiries/proposals/export
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'status'        => $queryParams['status'] ?? null,
            'facility_type' => $queryParams['facility_type'] ?? null,
            'search'        => $queryParams['search'] ?? null,
        ];

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $csv = $this->proposalService->exportCsv(
            $filters,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        $filename = 'proposals_export_' . gmdate('Ymd_His') . '.csv';

        return new Response(200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ], $csv);
    }

    /**
     * DELETE /api/v1/admin/proposals/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid proposal ID.");
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $this->proposalService->deleteProposal(
            $id,
            $adminUserId,
            $ipAddress,
            $userAgent
        );

        return Response::success(['deleted' => true, 'id' => $id], 200, $request->getCorrelationId());
    }
}
