<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\PageService;

/**
 * Administrative CMS Controller for Page Management and Content Revisions.
 */
class AdminPageController
{
    private PageService $pageService;

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
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
     * GET /api/v1/admin/pages
     */
    public function index(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'status' => $queryParams['status'] ?? null,
            'search' => $queryParams['search'] ?? null,
        ];

        $result = $this->pageService->listPages($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/pages
     */
    public function create(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $page = $this->pageService->createPage($data, $userId);

        return Response::success(['page' => $page], 201, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/pages/:id
     */
    public function show(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid page ID.");
        }

        $page = $this->pageService->getPageById($id, true);
        if (!$page) {
            throw new NotFoundException("Page [{$id}] not found.");
        }

        $revisions = $this->pageService->listRevisions($id);

        return Response::success([
            'page'      => $page,
            'revisions' => $revisions,
        ], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/pages/:id
     */
    public function update(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid page ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $page = $this->pageService->updatePage($id, $data, $userId);

        return Response::success(['page' => $page], 200, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/pages/:id/status
     */
    public function updateStatus(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid page ID.");
        }

        $userId = $this->requireAdminUserId($request);
        $data = $request->getJson();
        $status = (string)($data['status'] ?? '');

        if ($status === '') {
            throw new BadRequestException("Status is required (draft, published, archived).");
        }

        $page = $this->pageService->updatePageStatus($id, $status, $userId);

        return Response::success(['page' => $page], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/pages/:id/rollback/:rev
     */
    public function rollback(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        $rev = (int)($request->getAttribute('rev') ?? 0);

        if ($id <= 0 || $rev <= 0) {
            throw new BadRequestException("Invalid page ID or revision number.");
        }

        $userId = $this->requireAdminUserId($request);

        $page = $this->pageService->rollbackRevision($id, $rev, $userId);

        return Response::success([
            'message' => "Page rolled back to revision #{$rev} successfully.",
            'page'    => $page,
        ], 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/pages/:id
     */
    public function delete(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid page ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $this->pageService->softDeletePage($id, $userId);

        return Response::success([
            'message' => "Page [{$id}] soft-deleted successfully.",
        ], 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/pages/:id/revisions
     */
    public function revisions(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid page ID.");
        }

        $revisions = $this->pageService->listRevisions($id);

        return Response::success(['revisions' => $revisions], 200, $request->getCorrelationId());
    }
}
