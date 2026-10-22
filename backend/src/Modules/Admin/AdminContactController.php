<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\ContactService;

/**
 * Administrative Contact Enquiry CRM Controller (ADM-010 / FR-ADM-010).
 * Implements triage, DAG lifecycle state transitions, internal audit notes, team assignment,
 * CSV export, and Super Admin deletion.
 */
class AdminContactController
{
    private ContactService $contactService;

    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
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
     * GET /api/v1/admin/inquiries/contact
     * GET /api/admin/enquiries/contact
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'status'      => $queryParams['status'] ?? null,
            'search'      => $queryParams['search'] ?? null,
            'assigned_to' => $queryParams['assigned_to'] ?? null,
            'date_from'   => $queryParams['date_from'] ?? null,
            'date_to'     => $queryParams['date_to'] ?? null,
        ];

        $result = $this->contactService->listInquiries($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/inquiries/contact/export
     * GET /api/admin/enquiries/contact/export
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'status' => $queryParams['status'] ?? null,
            'search' => $queryParams['search'] ?? null,
        ];

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $csv = $this->contactService->exportCsv($filters, $adminUserId, $ipAddress, $userAgent);

        $filename = 'contact_inquiries_' . gmdate('Ymd_His') . '.csv';

        return new Response(200, [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                    => 'no-cache',
            'X-Correlation-Id'          => $request->getCorrelationId(),
        ], $csv);
    }

    /**
     * GET /api/v1/admin/inquiries/contact/:id
     * GET /api/admin/enquiries/contact/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid contact enquiry ID.");
        }

        $inquiry = $this->contactService->getInquiryById($id);

        return Response::success(['inquiry' => $inquiry], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/inquiries/contact/:id/status
     * PATCH /api/admin/enquiries/contact/:id
     */
    public function updateStatus(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid contact enquiry ID.");
        }

        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $body = $request->getJson();
        }

        $status = (string)($body['status'] ?? '');
        if (trim($status) === '') {
            throw new ValidationException(
                "Target status is required.",
                'ERR_VALIDATION',
                ['status' => 'Status field cannot be empty.']
            );
        }

        $note = isset($body['note']) ? (string)$body['note'] : null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $inquiry = $this->contactService->updateStatus($id, $status, $note, $adminUserId, $ipAddress, $userAgent);

        return Response::success(['inquiry' => $inquiry], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/inquiries/contact/:id/notes
     */
    public function addNote(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid contact enquiry ID.");
        }

        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $body = $request->getJson();
        }

        $note = (string)($body['note'] ?? '');
        if (trim($note) === '') {
            throw new ValidationException(
                "Note content cannot be empty.",
                'ERR_VALIDATION',
                ['note' => 'Note field cannot be empty.']
            );
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $inquiry = $this->contactService->addNote($id, $note, $adminUserId, $ipAddress, $userAgent);

        return Response::success(['inquiry' => $inquiry], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/inquiries/contact/:id/assign
     */
    public function assign(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid contact enquiry ID.");
        }

        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $body = $request->getJson();
        }

        $rawAssignee = $body['assigned_user_id'] ?? $body['user_id'] ?? null;
        $assignedUserId = ($rawAssignee !== null && $rawAssignee !== '') ? (int)$rawAssignee : null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $inquiry = $this->contactService->assignInquiry($id, $assignedUserId, $adminUserId, $ipAddress, $userAgent);

        return Response::success(['inquiry' => $inquiry], 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/inquiries/contact/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid contact enquiry ID.");
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Admin/Web');

        $this->contactService->deleteInquiry($id, $adminUserId, $ipAddress, $userAgent);

        return Response::success(['deleted' => true, 'id' => $id], 200, $request->getCorrelationId());
    }
}
