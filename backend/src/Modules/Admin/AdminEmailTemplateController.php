<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Services\EmailService;

/**
 * Administrative Email Template Controller (ADM-012, FR-ADM-013).
 * Governs transactional email copy, subject lines, placeholder validation, and diagnostic test sending.
 */
class AdminEmailTemplateController
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Lists all 8 canonical email templates.
     * GET /api/v1/admin/email-templates
     */
    public function index(Request $request): Response
    {
        $templates = $this->emailService->getTemplates();

        return Response::json([
            'success' => true,
            'data'    => $templates,
        ]);
    }

    /**
     * Shows a single email template by ID or key.
     * GET /api/v1/admin/email-templates/:id
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = (string)($request->getAttribute('id') ?? $params['id'] ?? '');
        if ($id === '') {
            throw new BadRequestException('Template identifier is required.');
        }

        $template = $this->emailService->getTemplate($id);

        return Response::json([
            'success' => true,
            'data'    => $template,
        ]);
    }

    /**
     * Updates an email template's subject and HTML body.
     * PUT|PATCH /api/v1/admin/email-templates/:id
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = (string)($request->getAttribute('id') ?? $params['id'] ?? '');
        if ($id === '') {
            throw new BadRequestException('Template identifier is required.');
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();

        $adminUserId = (int)$request->getAttribute('admin_user_id', 1);
        $ip = $request->getClientIp();
        $ua = (string)($request->getHeader('user-agent') ?? 'AdminPortal');

        $updated = $this->emailService->updateTemplate(
            $id,
            $body,
            $adminUserId,
            $ip,
            $ua
        );

        return Response::json([
            'success' => true,
            'data'    => $updated,
            'message' => 'Email template updated successfully.',
        ]);
    }

    /**
     * Sends a rendered sample test email to the specified address.
     * POST /api/v1/admin/email-templates/:id/test-send
     */
    public function testSend(Request $request, array $params = []): Response
    {
        $id = (string)($request->getAttribute('id') ?? $params['id'] ?? '');
        if ($id === '') {
            throw new BadRequestException('Template identifier is required.');
        }

        $body = !empty($request->getParsedBody()) ? $request->getParsedBody() : $request->getJson();
        $targetEmail = trim((string)($body['email'] ?? $body['recipient_email'] ?? ''));

        if ($targetEmail === '') {
            throw new ValidationException('Validation failed', [
                'email' => 'Recipient email address is required for test send.',
            ]);
        }

        $adminUserId = (int)$request->getAttribute('admin_user_id', 1);
        $ip = $request->getClientIp();
        $ua = (string)($request->getHeader('user-agent') ?? 'AdminPortal');

        $result = $this->emailService->sendTestEmail(
            $id,
            $targetEmail,
            $adminUserId,
            $ip,
            $ua
        );

        $status = $result['success'] ? 200 : 502;

        return Response::json([
            'success' => $result['success'],
            'data'    => $result,
        ], $status);
    }
}
