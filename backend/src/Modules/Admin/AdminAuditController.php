<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\AuditService;

/**
 * Administrative Audit Ledger Controller (Module 19 / ADM-019 / FR-ADM-019).
 * 
 * Provides:
 * - Read-only querying and pagination of immutable cryptographic audit events.
 * - Single event detail retrieval with before/after diffs.
 * - Cryptographic hash chain verification endpoint.
 * - Formula-injection hardened CSV export with UTF-8 BOM.
 * 
 * Fail-Closed Security:
 * Strictly restricted to Super Admin role with audit:read / audit:export permissions.
 */
class AdminAuditController
{
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Enforces fail-closed authentication.
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
     * GET /api/v1/admin/audit-logs
     * GET /api/admin/audit-logs
     */
    public function listLogs(Request $request): Response
    {
        $adminId = $this->requireAdminUserId($request);
        $queryParams = $request->getQueryParams();

        $filters = [
            'page'        => isset($queryParams['page']) ? (int)$queryParams['page'] : 1,
            'per_page'    => isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20,
            'domain'      => $queryParams['domain'] ?? null,
            'action'      => $queryParams['action'] ?? null,
            'user_id'     => $queryParams['user_id'] ?? null,
            'actor'       => $queryParams['actor'] ?? null,
            'entity_type' => $queryParams['entity_type'] ?? null,
            'entity_id'   => $queryParams['entity_id'] ?? null,
            'ip_address'  => $queryParams['ip_address'] ?? null,
            'start_date'  => $queryParams['start_date'] ?? null,
            'end_date'    => $queryParams['end_date'] ?? null,
            'search'      => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $result = $this->auditService->queryLogs($filters);

        // Record telemetry
        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Internal/Admin';
        $this->auditService->logAccess($adminId, $ip, $ua);

        return Response::success([
            'logs'       => $result['records'],
            'pagination' => $result['pagination'],
        ], 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/audit-logs/:id
     * GET /api/admin/audit-logs/:id
     */
    public function getLog(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)$request->getAttribute('id');
        if ($id <= 0) {
            throw new NotFoundException("Audit record not found.");
        }

        $log = $this->auditService->getLogById($id);
        if ($log === null) {
            throw new NotFoundException("Audit record not found.");
        }

        return Response::success([
            'log' => $log,
        ], 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/audit-logs/verify
     * POST /api/v1/admin/audit-logs/verify
     * GET /api/admin/audit-logs/verify
     * POST /api/admin/audit-logs/verify
     */
    public function verifyChain(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $verification = $this->auditService->verifyChainIntegrity();

        return Response::success([
            'verification' => $verification,
        ], 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/audit-logs/export
     * GET /api/admin/audit-logs/export
     */
    public function exportCsv(Request $request): Response
    {
        $adminId = $this->requireAdminUserId($request);
        $queryParams = $request->getQueryParams();

        $filters = [
            'domain'      => $queryParams['domain'] ?? null,
            'action'      => $queryParams['action'] ?? null,
            'user_id'     => $queryParams['user_id'] ?? null,
            'actor'       => $queryParams['actor'] ?? null,
            'entity_type' => $queryParams['entity_type'] ?? null,
            'entity_id'   => $queryParams['entity_id'] ?? null,
            'ip_address'  => $queryParams['ip_address'] ?? null,
            'start_date'  => $queryParams['start_date'] ?? null,
            'end_date'    => $queryParams['end_date'] ?? null,
            'search'      => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $csv = $this->auditService->exportCsv($filters);

        // Record telemetry
        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Internal/Admin';
        $recordCount = max(0, substr_count($csv, "\n") - 1);
        $this->auditService->logExport($adminId, $ip, $ua, $recordCount);

        return new Response(
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="audit-logs-export-' . gmdate('Y-m-d') . '.csv"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
                'Pragma'              => 'no-cache',
                'X-Correlation-Id'    => $request->getCorrelationId(),
            ],
            $csv
        );
    }
}
