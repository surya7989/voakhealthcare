<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\ArticleService;

/**
 * Administrative Insights & Articles Controller (ADM-016 / FR-ADM-017).
 *
 * Provides CRUD management, automatic reading time calculation, markdown/HTML sanitization,
 * tag management, and CSV export.
 */
class AdminArticleController
{
    private ArticleService $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
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
     * GET /api/v1/admin/articles
     * GET /api/admin/articles
     */
    public function index(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'category'     => $queryParams['category'] ?? null,
            'is_published' => $queryParams['is_published'] ?? $queryParams['status'] ?? null,
            'author_id'    => $queryParams['author_id'] ?? null,
            'search'       => $queryParams['search'] ?? $queryParams['q'] ?? null,
        ];

        $result = $this->articleService->getArticles($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/articles/export
     * GET /api/admin/articles/export
     */
    public function export(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $filters = [
            'category'     => $queryParams['category'] ?? null,
            'is_published' => $queryParams['is_published'] ?? null,
            'search'       => $queryParams['search'] ?? null,
        ];

        $csv = $this->articleService->exportCsv($filters, $adminUserId);

        return new Response(200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="articles_export_' . gmdate('Ymd_His') . '.csv"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ], $csv);
    }

    /**
     * GET /api/v1/admin/articles/:id
     * GET /api/admin/articles/:id
     */
    public function show(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid article ID.");
        }

        $article = $this->articleService->getArticleById($id);

        return Response::success($article, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/articles
     * POST /api/admin/articles
     */
    public function create(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $article = $this->articleService->createArticle($body, $adminUserId);

        return Response::success($article, 201, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/articles/:id
     * PATCH /api/v1/admin/articles/:id
     * PUT /api/admin/articles/:id
     * PATCH /api/admin/articles/:id
     */
    public function update(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid article ID.");
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $article = $this->articleService->updateArticle($id, $body, $adminUserId);

        return Response::success($article, 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/articles/:id
     * DELETE /api/admin/articles/:id
     */
    public function delete(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid article ID.");
        }

        $this->articleService->deleteArticle($id, $adminUserId);

        return Response::success(['message' => "Article [{$id}] has been archived."], 200, $request->getCorrelationId());
    }
}
