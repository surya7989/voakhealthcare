<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\HomepageService;

/**
 * Administrative Controller for Homepage Hero and Layout Configuration.
 */
class AdminHomepageController
{
    private HomepageService $homepageService;

    public function __construct(HomepageService $homepageService)
    {
        $this->homepageService = $homepageService;
    }

    /**
     * GET /api/v1/admin/homepage
     */
    public function show(Request $request): Response
    {
        $config = $this->homepageService->getActiveConfig();

        return Response::success(['config' => $config], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/homepage
     */
    public function update(Request $request): Response
    {
        $userId = (int)($request->getAttribute('admin_user_id') ?? 1);
        $data = $request->getJson();

        $config = $this->homepageService->updateConfig($data, $userId);

        return Response::success([
            'message' => 'Homepage configuration updated successfully.',
            'config'  => $config,
        ], 200, $request->getCorrelationId());
    }
}
