<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\IndustryService;

/**
 * Administrative CMS Controller for Healthcare Customer Verticals & Industries Management.
 */
class AdminIndustryController
{
    private IndustryService $industryService;

    public function __construct(IndustryService $industryService)
    {
        $this->industryService = $industryService;
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
     * GET /api/v1/admin/industries
     */
    public function index(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'is_published' => $queryParams['is_published'] ?? null,
            'search'       => $queryParams['search'] ?? null,
        ];

        $result = $this->industryService->listIndustries($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/industries
     */
    public function create(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $industry = $this->industryService->createIndustry($data, $userId);

        return Response::success(['industry' => $industry], 201, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/industries/:id
     */
    public function show(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid industry ID.");
        }

        $industry = $this->industryService->getIndustryById($id);
        if (!$industry) {
            throw new NotFoundException("Industry [{$id}] not found.");
        }

        return Response::success(['industry' => $industry], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/industries/:id
     */
    public function update(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid industry ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $industry = $this->industryService->updateIndustry($id, $data, $userId);

        return Response::success(['industry' => $industry], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/industries/:id/status
     */
    public function updateStatus(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid industry ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        if (!isset($data['is_published'])) {
            throw new BadRequestException("Field 'is_published' is required.");
        }

        $isPublished = (int)$data['is_published'];
        $industry = $this->industryService->updateIndustryStatus($id, $isPublished, $userId);

        return Response::success(['industry' => $industry], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/industries/reorder
     */
    public function reorder(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $orderMappings = $data['order'] ?? $data['industries'] ?? [];
        if (!is_array($orderMappings) || empty($orderMappings)) {
            throw new BadRequestException("Array of industry orders is required.");
        }

        $this->industryService->reorderIndustries($orderMappings, $userId);

        return Response::success(['message' => 'Industries reordered successfully.'], 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/industries/:id
     */
    public function delete(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid industry ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $this->industryService->deleteIndustry($id, $userId);

        return Response::success(['message' => "Industry [{$id}] deleted successfully."], 200, $request->getCorrelationId());
    }
}
