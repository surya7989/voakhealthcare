<?php

declare(strict_types=1);

namespace Voak\Health\Modules\PublicContent;

use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\HomepageService;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\ServiceService;
use Voak\Health\Services\SettingService;

/**
 * Public Content Delivery Controller.
 * Provides high-performance, cached, read-only endpoints for corporate pages,
 * homepage hero configuration, public settings, services catalog, and industries catalog with HTTP ETag / 304 validation.
 */
class PublicContentController
{
    private PageService $pageService;
    private HomepageService $homepageService;
    private SettingService $settingService;
    private ?ServiceService $serviceService;
    private ?IndustryService $industryService;

    public function __construct(
        PageService $pageService,
        HomepageService $homepageService,
        SettingService $settingService,
        ?ServiceService $serviceService = null,
        ?IndustryService $industryService = null
    ) {
        $this->pageService = $pageService;
        $this->homepageService = $homepageService;
        $this->settingService = $settingService;
        $this->serviceService = $serviceService;
        $this->industryService = $industryService;
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

    /**
     * GET /api/v1/public/services
     * Delivers published service offerings ordered by display sequence.
     */
    public function getServices(Request $request): Response
    {
        if ($this->serviceService === null) {
            throw new NotFoundException("Services catalog not available.");
        }

        $data = $this->serviceService->getPublishedServices();
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

        $response = Response::success(['services' => $data['services']], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/services/:slug
     * Delivers published service detail.
     * If service is not found, inspects url_redirects for an active 301 redirect.
     */
    public function getService(Request $request): Response
    {
        if ($this->serviceService === null) {
            throw new NotFoundException("Services catalog not available.");
        }

        $slug = (string)($request->getAttribute('slug') ?? '');
        if ($slug === '') {
            throw new NotFoundException("Service not found.");
        }

        $service = $this->serviceService->getPublishedServiceBySlug($slug);
        if ($service !== null) {
            $etag = $service['etag'] ?? $this->serviceService->calculateEtag($service);

            // Conditional GET check
            $ifNoneMatch = $request->getHeader('if-none-match');
            if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
                return (new Response(304, [
                    'ETag'             => $etag,
                    'Cache-Control'    => 'public, max-age=300, must-revalidate',
                    'X-Correlation-ID' => $request->getCorrelationId(),
                ]));
            }

            $response = Response::success(['service' => $service], 200, $request->getCorrelationId());
            return $response
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
        }

        // Check if there is an active 301 redirect in url_redirects (FR-WF-006)
        $redirect = $this->serviceService->findRedirect('/services/' . $slug);
        if ($redirect !== null && !empty($redirect['target_path'])) {
            return Response::redirect($redirect['target_path'], (int)($redirect['http_status_code'] ?? 301));
        }

        throw new NotFoundException("Service [{$slug}] not found.");
    }

    /**
     * GET /api/v1/public/industries
     * Delivers published healthcare industry sectors ordered by display sequence.
     */
    public function getIndustries(Request $request): Response
    {
        if ($this->industryService === null) {
            throw new NotFoundException("Industries catalog not available.");
        }

        $data = $this->industryService->getPublishedIndustries();
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

        $response = Response::success(['industries' => $data['industries']], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/industries/:slug
     * Delivers published industry vertical detail with mapped services.
     * If industry is not found, inspects url_redirects for an active 301 redirect.
     */
    public function getIndustry(Request $request): Response
    {
        if ($this->industryService === null) {
            throw new NotFoundException("Industries catalog not available.");
        }

        $slug = (string)($request->getAttribute('slug') ?? '');
        if ($slug === '') {
            throw new NotFoundException("Industry not found.");
        }

        $industry = $this->industryService->getPublishedIndustryBySlug($slug);
        if ($industry !== null) {
            $etag = $industry['etag'] ?? $this->industryService->calculateEtag($industry);

            // Conditional GET check
            $ifNoneMatch = $request->getHeader('if-none-match');
            if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
                return (new Response(304, [
                    'ETag'             => $etag,
                    'Cache-Control'    => 'public, max-age=300, must-revalidate',
                    'X-Correlation-ID' => $request->getCorrelationId(),
                ]));
            }

            $response = Response::success(['industry' => $industry], 200, $request->getCorrelationId());
            return $response
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
        }

        // Check if there is an active 301 redirect in url_redirects
        $redirect = $this->industryService->findRedirect('/industries/' . $slug);
        if ($redirect !== null && !empty($redirect['target_path'])) {
            return Response::redirect($redirect['target_path'], (int)($redirect['http_status_code'] ?? 301));
        }

        throw new NotFoundException("Industry [{$slug}] not found.");
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
