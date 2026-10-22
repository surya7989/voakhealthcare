<?php

declare(strict_types=1);

namespace Voak\Health\Modules\PublicContent;

use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\HomepageService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\SettingService;

/**
 * Public Content Delivery Controller.
 * Provides high-performance, cached, read-only endpoints for corporate pages,
 * homepage hero configuration, and public settings with HTTP ETag / 304 validation.
 */
class PublicContentController
{
    private PageService $pageService;
    private HomepageService $homepageService;
    private SettingService $settingService;

    public function __construct(
        PageService $pageService,
        HomepageService $homepageService,
        SettingService $settingService
    ) {
        $this->pageService = $pageService;
        $this->homepageService = $homepageService;
        $this->settingService = $settingService;
    }

    /**
     * GET /api/v1/public/pages/:slug
     * Delivers published static page content. Strictly excludes drafts, archived, and deleted pages.
     */
    public function getPage(Request $request): Response
    {
        $slug = (string)($request->getAttribute('slug') ?? '');
        if ($slug === '') {
            throw new NotFoundException("Page not found.");
        }

        $page = $this->pageService->getPublishedPageBySlug($slug);
        if (!$page) {
            throw new NotFoundException("Page not found.");
        }

        $etag = $this->pageService->calculateEtag($page);

        // Conditional GET check
        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success(['page' => $page], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/homepage
     * Delivers active homepage hero section, proof metrics, and bento layout.
     */
    public function getHomepage(Request $request): Response
    {
        $config = $this->homepageService->getActiveConfig();
        $etag = $config['etag'] ?? $this->homepageService->calculateEtag($config);

        // Conditional GET check
        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success(['config' => $config], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/settings
     * Delivers public branding, contact information, and business hours.
     * Strictly isolates private credentials and secrets (WHERE is_secret = 0).
     */
    public function getSettings(Request $request): Response
    {
        $data = $this->settingService->getPublicSettings();
        $etag = $data['etag'];

        // Conditional GET check
        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success(['settings' => $data['settings']], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    private function isEtagMatch(string $headerValue, string $etag): bool
    {
        $cleanHeader = trim($headerValue);
        $cleanEtag = trim($etag);

        if ($cleanHeader === $cleanEtag) {
            return true;
        }

        // Handle quoted vs unquoted comparison
        return trim($cleanHeader, '"') === trim($cleanEtag, '"');
    }
}
