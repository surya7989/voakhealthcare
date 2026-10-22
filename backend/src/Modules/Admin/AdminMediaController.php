<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\MediaStorageService;

/**
 * Administrative Media Library Controller (ADM-009 / FR-ADM-009).
 * Manages public marketing media uploads, WebP conversion, alt-text, and referential deletion protection.
 */
class AdminMediaController
{
    private MediaStorageService $mediaService;

    public function __construct(MediaStorageService $mediaService)
    {
        $this->mediaService = $mediaService;
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
     * GET /api/v1/admin/media
     */
    public function index(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 20;

        $filters = [
            'search'    => $queryParams['search'] ?? null,
            'mime_type' => $queryParams['mime_type'] ?? null,
        ];

        $result = $this->mediaService->listMedia($filters, $page, $perPage);

        return Response::success($result, 200, $request->getCorrelationId());
    }

    /**
     * GET /api/v1/admin/media/:id
     */
    public function show(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid media asset ID.");
        }

        $media = $this->mediaService->getMediaById($id);
        if (!$media) {
            throw new NotFoundException("Media asset [{$id}] not found.");
        }

        return Response::success(['media' => $media], 200, $request->getCorrelationId());
    }

    /**
     * POST /api/v1/admin/media/upload
     */
    public function upload(Request $request): Response
    {
        $userId = $this->requireAdminUserId($request);

        // Extract uploaded file
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? $files['media'] ?? $files['image'] ?? null;

        if ($file === null && !empty($files)) {
            $file = reset($files);
        }

        if ($file === null) {
            throw new ValidationException(
                "No image file uploaded. Provide multipart form-data with 'file' field.",
                'ERR_VALIDATION',
                ['file' => 'File payload is required.']
            );
        }

        // Extract mandatory alt text
        $parsedBody = $request->getParsedBody() ?? [];
        $altText = (string)($parsedBody['alt_text'] ?? $request->getQueryParam('alt_text', ''));

        if (trim($altText) === '') {
            throw new ValidationException(
                "Mandatory alt text is required for all media assets.",
                'ERR_VALIDATION',
                ['alt_text' => 'Alt text cannot be empty.']
            );
        }

        $media = $this->mediaService->uploadMedia($file, $altText, $userId);

        return Response::success(['media' => $media], 201, $request->getCorrelationId());
    }

    /**
     * PATCH /api/v1/admin/media/:id
     * PUT /api/v1/admin/media/:id
     */
    public function update(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid media asset ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $json = $request->getParsedBody() ?? [];
        if (empty($json)) {
            $json = $request->getJson();
        }

        $altText = (string)($json['alt_text'] ?? '');
        if (trim($altText) === '') {
            throw new ValidationException("Alt text cannot be empty.", 'ERR_VALIDATION');
        }

        $media = $this->mediaService->updateMediaAltText($id, $altText, $userId);

        return Response::success(['media' => $media], 200, $request->getCorrelationId());
    }

    /**
     * DELETE /api/v1/admin/media/:id
     */
    public function delete(Request $request): Response
    {
        $id = (int)($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            throw new BadRequestException("Invalid media asset ID.");
        }

        $userId = $this->requireAdminUserId($request);

        $this->mediaService->deleteMedia($id, $userId);

        return Response::success(['deleted' => true, 'id' => $id], 200, $request->getCorrelationId());
    }
}
