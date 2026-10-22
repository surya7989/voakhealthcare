<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\ServiceService;

/**
 * Administrative CMS Controller for B2B Healthcare Services Catalog Management.
 */
class AdminServiceController
{
    private ServiceService $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
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
     * GET /api/v1/admin/services
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

        $result = $this->serviceService->listServices($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/services
     */
    public function create(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $service = $this->serviceService->createService($data, $userId);

        return Response::success(['service' => $service], 201, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/services/:id
     */
    public function show(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid service ID.");
        }

        $service = $this->serviceService->getServiceById($id);
        if (!$service) {
            throw new NotFoundException("Service [{$id}] not found.");
        }

        return Response::success(['service' => $service], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/services/:id
     */
    public function update(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid service ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $service = $this->serviceService->updateService($id, $data, $userId);

        return Response::success(['service' => $service], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/services/:id/status
     */
    public function updateStatus(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid service ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        if (!isset($data['is_published'])) {
            throw new BadRequestException("Field 'is_published' is required.");
        }

        $isPublished = (int)$data['is_published'];
        $service = $this->serviceService->updateServiceStatus($id, $isPublished, $userId);

        return Response::success(['service' => $service], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/services/reorder
     */
    public function reorder(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $orderMappings = $data['order'] ?? $data['services'] ?? [];
        if (!is_array($orderMappings) || empty($orderMappings)) {
            throw new BadRequestException("Array of service orders is required.");
        }

        $this->serviceService->reorderServices($orderMappings, $userId);

        return Response::success(['message' => 'Services reordered successfully.'], 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/services/:id
     */
    public function delete(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid service ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $this->serviceService->deleteService($id, $userId);

        return Response::success(['message' => "Service [{$id}] deleted successfully."], 200, $request->getCorrelationId());
    }
}
