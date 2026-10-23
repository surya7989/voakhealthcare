<?php

declare(strict_types=1);

namespace Voak\Health\Modules\PublicContent;

use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\ArticleService;
use Voak\Health\Services\CareerService;
use Voak\Health\Services\CaseStudyService;
use Voak\Health\Services\ContactService;
use Voak\Health\Services\FaqService;
use Voak\Health\Services\HomepageService;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\PartnershipService;
use Voak\Health\Services\ProposalService;
use Voak\Health\Services\ServiceService;
use Voak\Health\Services\SettingService;

/**
 * Public Content Delivery Controller.
 * Provides high-performance, cached, read-only endpoints for corporate pages,
 * homepage hero configuration, public settings, services catalog, industries catalog,
 * public media, careers, articles, case studies, and FAQs with HTTP ETag / 304 validation.
 */
class PublicContentController
{
    private PageService $pageService;
    private HomepageService $homepageService;
    private SettingService $settingService;
    private ?ServiceService $serviceService;
    private ?IndustryService $industryService;
    private ?MediaStorageService $mediaService;
    private ?ContactService $contactService;
    private ?PartnershipService $partnershipService;
    private ?ProposalService $proposalService;
    private ?CareerService $careerService;
    private ?ArticleService $articleService;
    private ?CaseStudyService $caseStudyService;
    private ?FaqService $faqService;

    public function __construct(
        PageService $pageService,
        HomepageService $homepageService,
        SettingService $settingService,
        ?ServiceService $serviceService = null,
        ?IndustryService $industryService = null,
        ?MediaStorageService $mediaService = null,
        ?ContactService $contactService = null,
        ?PartnershipService $partnershipService = null,
        ?ProposalService $proposalService = null,
        ?CareerService $careerService = null,
        ?ArticleService $articleService = null,
        ?CaseStudyService $caseStudyService = null,
        ?FaqService $faqService = null
    ) {
        $this->pageService = $pageService;
        $this->homepageService = $homepageService;
        $this->settingService = $settingService;
        $this->serviceService = $serviceService;
        $this->industryService = $industryService;
        $this->mediaService = $mediaService;
        $this->contactService = $contactService;
        $this->partnershipService = $partnershipService;
        $this->proposalService = $proposalService;
        $this->careerService = $careerService;
        $this->articleService = $articleService;
        $this->caseStudyService = $caseStudyService;
        $this->faqService = $faqService;
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

    /**
     * GET /api/v1/public/media/:id
     * Returns sanitized public metadata for a media asset.
     */
    public function getMedia(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0 || $this->mediaService === null) {
            throw new NotFoundException("Media asset not found.");
        }

        $media = $this->mediaService->getMediaById($id);
        if (!$media) {
            throw new NotFoundException("Media asset [{$id}] not found.");
        }

        // Return only safe public fields
        $publicData = [
            'id'              => $media['id'],
            'original_name'   => $media['original_name'],
            'url'             => $media['url'],
            'thumb_url'       => $media['thumb_url'],
            'dimensions'      => $media['dimensions'],
            'alt_text'        => $media['alt_text'],
            'mime_type'       => $media['mime_type'],
            'file_size_bytes' => $media['file_size_bytes'],
            'created_at'      => $media['created_at'],
        ];

        return Response::success(['media' => $publicData], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/public/contact
     * POST /api/enquiries/contact
     * Submits a public healthcare contact inquiry with multi-layer abuse defense.
     */
    public function submitContact(Request $request): Response
    {
        if ($this->contactService === null) {
            throw new \RuntimeException("Contact service is not configured.");
        }

        $payload = $request->getParsedBody() ?? [];
        if (empty($payload)) {
            $payload = $request->getJson();
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Public/Web');

        $result = $this->contactService->submitInquiry($payload, $ipAddress, $userAgent);

        return Response::success($result, 201, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/public/partnerships/inquire
     * POST /api/enquiries/partner
     * Submits a public strategic healthcare partnership proposal.
     */
    public function submitPartnership(Request $request): Response
    {
        if ($this->partnershipService === null) {
            throw new \RuntimeException("Partnership service is not configured.");
        }

        $payload = $request->getParsedBody() ?? [];
        if (empty($payload)) {
            $payload = $request->getJson();
        }

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Public/Web');

        $result = $this->partnershipService->submitPartnership($payload, $ipAddress, $userAgent);

        return Response::success($result, 201, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/public/proposals/submit
     * POST /api/enquiries/proposal
     * Submits a public multi-step RFP wizard entry with tender document attachment.
     */
    public function submitProposal(Request $request): Response
    {
        if ($this->proposalService === null) {
            throw new \RuntimeException("Proposal service is not configured.");
        }

        $payload = $request->getParsedBody() ?? [];
        if (empty($payload)) {
            $payload = $request->getJson();
        }

        $file = $_FILES['rfp_file'] ?? $_FILES['tender_file'] ?? $_FILES['file'] ?? null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Public/Web');

        $result = $this->proposalService->submitProposal($payload, $file, $ipAddress, $userAgent);

        return Response::success($result, 201, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/public/jobs
     * GET /api/v1/public/careers
     * GET /api/careers
     * Delivers active published careers openings.
     */
    public function getJobs(Request $request): Response
    {
        if ($this->careerService === null) {
            throw new \RuntimeException("Career service is not configured.");
        }

        $filters = $request->getQueryParams();
        $jobs = $this->careerService->listPublishedJobs($filters);

        $etag = '"' . md5(json_encode($jobs)) . '"';
        $ifNoneMatch = $request->getHeader('if-none-match');

        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return new Response(304, [
                'ETag'          => $etag,
                'Cache-Control' => 'public, max-age=300, must-revalidate',
            ]);
        }

        return new Response(200, [
            'Content-Type'  => 'application/json; charset=UTF-8',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ], json_encode([
            'success'   => true,
            'data'      => $jobs,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]));
    }

    /**
     * GET /api/v1/public/jobs/:slug
     * GET /api/v1/public/careers/:slug
     * GET /api/careers/:slug
     * Delivers single published job detail.
     */
    public function getJob(Request $request): Response
    {
        if ($this->careerService === null) {
            throw new \RuntimeException("Career service is not configured.");
        }

        $slug = (string)($request->getAttribute('slug') ?? '');
        $job = $this->careerService->getPublishedJobBySlug($slug);

        if ($job === null) {
            throw new NotFoundException("Job opening [{$slug}] not found or is no longer active.");
        }

        $etag = '"' . md5(json_encode($job)) . '"';
        $ifNoneMatch = $request->getHeader('if-none-match');

        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return new Response(304, [
                'ETag'          => $etag,
                'Cache-Control' => 'public, max-age=300, must-revalidate',
            ]);
        }

        return new Response(200, [
            'Content-Type'  => 'application/json; charset=UTF-8',
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ], json_encode([
            'success'   => true,
            'data'      => $job,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]));
    }

    /**
     * POST /api/v1/public/careers/apply
     * POST /api/v1/public/careers/:slug/apply
     * POST /api/careers/:slug/apply
     * Submits a candidate job application with resume attachment.
     */
    public function submitApplication(Request $request): Response
    {
        if ($this->careerService === null) {
            throw new \RuntimeException("Career service is not configured.");
        }

        $payload = $request->getParsedBody() ?? [];
        if (empty($payload)) {
            $raw = trim($request->getRawBody());
            if ($raw !== '') {
                $contentType = (string)($request->getHeader('content-type') ?? '');
                if (str_contains(strtolower($contentType), 'application/x-www-form-urlencoded')) {
                    parse_str($raw, $payload);
                } else {
                    $payload = $request->getJson();
                }
            }
        }

        // Check if slug is in route parameter
        $slugAttr = $request->getAttribute('slug');
        if ($slugAttr !== null && empty($payload['job_slug']) && empty($payload['job_id'])) {
            $payload['job_slug'] = (string)$slugAttr;
        }

        $file = $_FILES['resume_file'] ?? $_FILES['resume'] ?? $_FILES['file'] ?? null;

        $ipAddress = $request->getClientIp();
        $userAgent = (string)($request->getHeader('user-agent') ?? 'Public/Web');

        $result = $this->careerService->submitApplication($payload, $file, $ipAddress, $userAgent);

        return Response::success($result, 201, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/public/articles
     * GET /api/articles
     * Delivers published articles feed with optional category, tag, or search filtering.
     */
    public function getArticles(Request $request): Response
    {
        if ($this->articleService === null) {
            throw new NotFoundException("Article service not available.");
        }

        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 12;

        $filters = [
            'category' => $params['category'] ?? null,
            'tag'      => $params['tag'] ?? null,
            'search'   => $params['search'] ?? $params['q'] ?? null,
        ];

        $data = $this->articleService->getPublishedArticles($filters, $page, $perPage);
        $etag = $data['etag'];

        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success($data, 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/articles/:slug
     * GET /api/articles/:slug
     * Delivers single published article and related articles.
     */
    public function getArticle(Request $request): Response
    {
        if ($this->articleService === null) {
            throw new NotFoundException("Article service not available.");
        }

        $slug = (string)($request->getAttribute('slug') ?? '');
        if ($slug === '') {
            throw new NotFoundException("Article not found.");
        }

        $data = $this->articleService->getPublishedBySlug($slug);
        $etag = $data['etag'];

        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success($data['article'], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/case-studies
     * GET /api/case-studies
     * Delivers verified published case studies.
     */
    public function getCaseStudies(Request $request): Response
    {
        if ($this->caseStudyService === null) {
            throw new NotFoundException("Case study service not available.");
        }

        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 12;

        $filters = [
            'sector' => $params['sector'] ?? $params['client_sector'] ?? null,
            'search' => $params['search'] ?? $params['q'] ?? null,
        ];

        $data = $this->caseStudyService->getVerifiedPublishedCaseStudies($filters, $page, $perPage);
        $etag = $data['etag'];

        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success($data, 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/case-studies/:slug
     * GET /api/case-studies/:slug
     * Delivers single verified published case study.
     */
    public function getCaseStudy(Request $request): Response
    {
        if ($this->caseStudyService === null) {
            throw new NotFoundException("Case study service not available.");
        }

        $slug = (string)($request->getAttribute('slug') ?? '');
        if ($slug === '') {
            throw new NotFoundException("Case study not found.");
        }

        $data = $this->caseStudyService->getVerifiedPublishedBySlug($slug);
        $etag = $data['etag'];

        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success($data['case_study'], 200, $request->getCorrelationId());
        return $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=300, must-revalidate');
    }

    /**
     * GET /api/v1/public/faqs
     * GET /api/faqs
     * Delivers published FAQ accordions.
     */
    public function getFaqs(Request $request): Response
    {
        if ($this->faqService === null) {
            throw new NotFoundException("FAQ service not available.");
        }

        $params = $request->getQueryParams();
        $category = $params['category'] ?? null;
        $search = $params['search'] ?? $params['q'] ?? null;

        $data = $this->faqService->getPublishedFaqs($category, $search);
        $etag = $data['etag'];

        $ifNoneMatch = $request->getHeader('if-none-match');
        if ($ifNoneMatch !== null && $this->isEtagMatch($ifNoneMatch, $etag)) {
            return (new Response(304, [
                'ETag'             => $etag,
                'Cache-Control'    => 'public, max-age=300, must-revalidate',
                'X-Correlation-ID' => $request->getCorrelationId(),
            ]));
        }

        $response = Response::success($data, 200, $request->getCorrelationId());
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
