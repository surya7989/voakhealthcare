<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\SeoService;

/**
 * Administrative SEO, Metadata & Redirect Management Controller (Module 17 / ADM-017 / ADM-018).
 *
 * Implements:
 * - Route-level SEO metadata management and length validation (FR-ADM-018)
 * - Dynamic XML sitemap cache invalidation and rebuild trigger (FR-SEO-003)
 * - Safe 301 URL redirect management with loop detection and chain flattening (FR-WF-006)
 */
class AdminSeoController
{
    private SeoService $seoService;

    public function __construct(SeoService $seoService)
    {
        $this->seoService = $seoService;
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
     * GET /api/v1/admin/seo/routes
     * GET /api/admin/seo/routes
     */
    public function listRoutes(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $routes = $this->seoService->getRouteSeoList();

        return Response::success([
            'routes' => $routes,
            'items'  => $routes,
            'count'  => count($routes),
        ], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/seo/routes/:id
     * PUT /api/admin/seo/routes/:id
     */
    public function updateRoute(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $idOrSlug = $request->getAttribute('id');
        if ($idOrSlug === null || $idOrSlug === '') {
            throw new BadRequestException("Route identifier is required.");
        }

        $data = $request->getJson();
        $ip = $request->getClientIp();
        $userAgent = $request->getHeader('User-Agent') ?? 'AdminSeoController';

        $updated = $this->seoService->updateRouteSeo($idOrSlug, $data, $adminUserId, $ip, $userAgent);

        return Response::success([
            'route'   => $updated,
            'message' => 'Route SEO metadata updated successfully.',
        ], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/seo/rebuild-sitemap
     * POST /api/admin/seo/rebuild-sitemap
     */
    public function rebuildSitemap(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $ip = $request->getClientIp();
        $userAgent = $request->getHeader('User-Agent') ?? 'AdminSeoController';

        $xml = $this->seoService->rebuildSitemap($adminUserId, $ip, $userAgent);

        return Response::success([
            'message'     => 'XML sitemap successfully rebuilt and cache refreshed.',
            'sitemap_url' => '/sitemap.xml',
            'xml_length'  => strlen($xml),
        ], 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/seo/redirects
     * GET /api/admin/seo/redirects
     */
    public function listRedirects(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'search'    => $queryParams['search'] ?? $queryParams['q'] ?? null,
            'is_active' => $queryParams['is_active'] ?? null,
        ];

        $result = $this->seoService->listRedirects($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/seo/redirects
     * POST /api/admin/seo/redirects
     */
    public function createRedirect(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);
        $data = $request->getJson();

        $source = trim((string)($data['source_path'] ?? $data['source'] ?? ''));
        $target = trim((string)($data['target_path'] ?? $data['target'] ?? ''));
        $statusCode = isset($data['http_status_code']) ? (int)$data['http_status_code'] : 301;

        if ($source === '') {
            throw new BadRequestException("Source path is required.");
        }
        if ($target === '') {
            throw new BadRequestException("Target path is required.");
        }

        $ip = $request->getClientIp();
        $userAgent = $request->getHeader('User-Agent') ?? 'AdminSeoController';

        $redirect = $this->seoService->registerRedirect($source, $target, $statusCode, $adminUserId, $ip, $userAgent);

        return Response::success([
            'redirect' => $redirect,
            'message'  => 'Redirect successfully registered.',
        ], 201, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/seo/redirects/:id
     * DELETE /api/admin/seo/redirects/:id
     */
    public function deleteRedirect(Request $request): Response
    {
        $adminUserId = $this->requireAdminUserId($request);

        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid redirect ID.");
        }

        $ip = $request->getClientIp();
        $userAgent = $request->getHeader('User-Agent') ?? 'AdminSeoController';

        $this->seoService->deleteRedirect($id, $adminUserId, $ip, $userAgent);

        return Response::success([
            'message' => 'Redirect successfully deleted.',
            'id'      => $id,
        ], 200, $request->getCorrelationId());
    }
}
