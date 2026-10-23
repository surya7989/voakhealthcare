<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\EmailService;

/**
 * Administrative Email Outbox & Telemetry Controller (ADM-012, FR-ADM-013).
 * Governs outbox monitoring, manual redelivery retry, bulk retry, and scheduled dispatching.
 */
class AdminEmailQueueController
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Lists outbox messages with filtering and pagination.
     * GET /api/v1/admin/email-queue
     */
    public function index(Request $request): Response
    {
        $queryParams = $request->getQueryParams();

        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'status'       => $queryParams['status'] ?? null,
            'template_key' => $queryParams['template_key'] ?? null,
            'search'       => $queryParams['search'] ?? null,
            'date_from'    => $queryParams['date_from'] ?? null,
            'date_to'      => $queryParams['date_to'] ?? null,
        ];

        $result = $this->emailService->listQueue($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'pagination' => $result['pagination'],
            ],
        ]);
    }

    /**
     * Shows detail of a single outbox message.
     * GET /api/v1/admin/email-queue/:id
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = (int)($request->getAttribute('id') ?? $params['id'] ?? 0);
        if ($id <= 0) {
            throw new BadRequestException('Valid queue message identifier is required.');
        }

        $item = $this->emailService->getQueueItem($id);

        return Response::json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    /**
     * Manually triggers immediate redelivery retry for a failed email (TEST-ADM-013).
     * POST /api/v1/admin/email-queue/:id/retry
     */
    public function retry(Request $request, array $params = []): Response
    {
        $id = (int)($request->getAttribute('id') ?? $params['id'] ?? 0);
        if ($id <= 0) {
            throw new BadRequestException('Valid queue message identifier is required.');
        }

        $adminUserId = (int)$request->getAttribute('admin_user_id', 1);
        $ip = $request->getClientIp();
        $ua = (string)($request->getHeader('user-agent') ?? 'AdminPortal');

        $updated = $this->emailService->retryMessage($id, $adminUserId, $ip, $ua);

        return Response::json([
            'success' => true,
            'data'    => $updated,
            'message' => 'Message reset to QUEUED for delivery attempt.',
        ]);
    }

    /**
     * Bulk resets all failed outbox messages for redelivery retry.
     * POST /api/v1/admin/email-queue/retry-all-failed
     */
    public function retryAllFailed(Request $request): Response
    {
        $adminUserId = (int)$request->getAttribute('admin_user_id', 1);
        $ip = $request->getClientIp();
        $ua = (string)($request->getHeader('user-agent') ?? 'AdminPortal');

        $count = $this->emailService->retryAllFailed($adminUserId, $ip, $ua);

        return Response::json([
            'success' => true,
            'data'    => [
                'reset_count' => $count,
            ],
            'message' => "Successfully reset {$count} failed messages to QUEUED status.",
        ]);
    }

    /**
     * Triggers batch queue processing (called by cron runner or authenticated webhook).
     * POST /api/v1/admin/email-queue/dispatch
     */
    public function dispatch(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;

        $report = $this->emailService->processQueueBatch($limit);

        return Response::json([
            'success' => true,
            'data'    => $report,
            'message' => "Batch processing complete: {$report['sent']} sent, {$report['failed']} failed.",
        ]);
    }
}
