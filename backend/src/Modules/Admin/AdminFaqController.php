<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\FaqService;

/**
 * Administrative FAQ Support Center Controller (Module 16).
 *
 * Implements FAQ creation, category updates, answer HTML sanitization, sequential reordering, and deletion.
 */
class AdminFaqController
{
    private FaqService $faqService;

    public function __construct(FaqService $faqService)
    {
        $this->faqService = $faqService;
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
     * GET /api/v1/admin/faqs
     * GET /api/admin/faqs
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 50;

        $filters = [
            'category'     => $queryParams['category'] ?? null,
            'is_published' => $queryParams['is_published'] ?? $queryParams['status'] ?? null,
            'search'       => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $result = $this->faqService->getFaqs($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/faqs/:id
     * GET /api/admin/faqs/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid FAQ ID.");
        }

        $faq = $this->faqService->getFaqById($id);

        return Response::success($faq, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/faqs
     * POST /api/admin/faqs
     */
    public function create(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $faq = $this->faqService->createFaq($body, $adminUserId);

        return Response::success($faq, 201, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/faqs/:id
     * PATCH /api/v1/admin/faqs/:id
     * PUT /api/admin/faqs/:id
     * PATCH /api/admin/faqs/:id
     */
    public function update(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid FAQ ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $faq = $this->faqService->updateFaq($id, $body, $adminUserId);

        return Response::success($faq, 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/faqs/:id
     * DELETE /api/admin/faqs/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid FAQ ID.");
        }

        $this->faqService->deleteFaq($id, $adminUserId);

        return Response::success(['message' => "FAQ [{$id}] has been deleted."], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/faqs/reorder
     * POST /api/admin/faqs/reorder
     */
    public function reorder(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $orderedIds = $body['ordered_ids'] ?? $body['ids'] ?? [];

        if (!is_array($orderedIds) || empty($orderedIds)) {
            throw new BadRequestException("ordered_ids array is required for reordering.");
        }

        $this->faqService->reorderFaqs($orderedIds, $adminUserId);

        return Response::success(['message' => "FAQs reordered successfully."], 200, $request->getCorrelationId());
    }
}
