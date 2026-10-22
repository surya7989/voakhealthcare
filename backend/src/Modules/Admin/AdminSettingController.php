<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\SettingService;

/**
 * Administrative Controller for System and Platform Settings.
 */
class AdminSettingController
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * GET /api/v1/admin/settings
     */
    public function index(Request $request): Response
    {
        $settings = $this->settingService->getAllSettings();

        return Response::success([
            'settings' => $settings,
            'count'    => count($settings),
        ], 200, $request->getCorrelationId());
    }

    /**
     * PUT /api/v1/admin/settings
     */
    public function update(Request $request): Response
    {
        $userId = (int)($request->getAttribute('admin_user_id') ?? 1);
        $data = $request->getJson();

        $result = $this->settingService->updateSettings($data, $userId);

        return Response::success([
            'message'        => "{$result['updated_count']} settings updated successfully.",
            'updated_count'  => $result['updated_count'],
        ], 200, $request->getCorrelationId());
    }
}
