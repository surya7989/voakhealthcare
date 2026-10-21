<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Multi-Factor Authentication (MFA) Controller.
 * Endpoints for TOTP verification, emergency recovery codes, enrollment, and activation.
 */
class MfaController
{
    private Connection $connection;
    private AuthenticationService $authService;
    private TotpService $totpService;
    private SessionManager $sessionManager;
    private Config $config;

    public function __construct(
        Connection $connection,
        AuthenticationService $authService,
        TotpService $totpService,
        SessionManager $sessionManager,
        Config $config
    ) {
        $this->connection = $connection;
        $this->authService = $authService;
        $this->totpService = $totpService;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
    }

    /**
     * POST /api/v1/admin/auth/mfa/verify
     */
    public function verifyMfa(Request $request): Response
    {
        $data = $request->getJson();
        $token = (string)($data['pre_auth_token'] ?? '');
        $code = (string)($data['totp_code'] ?? '');

        if ($token === '' || $code === '') {
            throw new ValidationException("Pre-auth token and 6-digit TOTP code are required.", [
                'pre_auth_token' => empty($token) ? ['Pre-auth token is required.'] : [],
                'totp_code'      => empty($code) ? ['TOTP code is required.'] : [],
            ]);
        }

        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Unknown';

        $result = $this->authService->verifyMfaAndEstablishSession(
            $this->connection->getPdo(),
            $token,
            $code,
            false,
            $ip,
            $ua
        );

        $isProduction = (string)$this->config->get('APP_ENV') === 'production';
        $cookie = $this->sessionManager->buildSessionCookie(
            $result['session']['session_id'],
            $isProduction
        );

        $response = Response::success([
            'status'     => 'authenticated',
            'user'       => $result['user'],
            'csrf_token' => $result['session']['csrf_token'],
            'expires_at' => $result['session']['expires_at'],
        ], 200);

        return $response->withHeader('Set-Cookie', $cookie);
    }

    /**
     * POST /api/v1/admin/auth/mfa/recovery
     */
    public function verifyRecovery(Request $request): Response
    {
        $data = $request->getJson();
        $token = (string)($data['pre_auth_token'] ?? '');
        $recoveryCode = (string)($data['recovery_code'] ?? '');

        if ($token === '' || $recoveryCode === '') {
            throw new ValidationException("Pre-auth token and recovery code are required.", [
                'pre_auth_token' => empty($token) ? ['Pre-auth token is required.'] : [],
                'recovery_code'  => empty($recoveryCode) ? ['Recovery code is required.'] : [],
            ]);
        }

        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Unknown';

        $result = $this->authService->verifyMfaAndEstablishSession(
            $this->connection->getPdo(),
            $token,
            $recoveryCode,
            true,
            $ip,
            $ua
        );

        $isProduction = (string)$this->config->get('APP_ENV') === 'production';
        $cookie = $this->sessionManager->buildSessionCookie(
            $result['session']['session_id'],
            $isProduction
        );

        $response = Response::success([
            'status'     => 'authenticated',
            'user'       => $result['user'],
            'csrf_token' => $result['session']['csrf_token'],
            'expires_at' => $result['session']['expires_at'],
            'warning'    => 'Emergency recovery code consumed. Please generate new backup codes in profile settings.',
        ], 200);

        return $response->withHeader('Set-Cookie', $cookie);
    }

    /**
     * POST /api/v1/admin/auth/mfa/setup
     * Requires active administrator session.
     */
    public function setupMfa(Request $request): Response
    {
        $userId = $request->getAttribute('admin_user_id');
        $user = $request->getAttribute('admin_user');

        if ($userId === null || !is_array($user)) {
            throw new AuthenticationException("Unauthenticated.");
        }

        $setup = $this->totpService->initiateSetup(
            $this->connection->getPdo(),
            (int)$userId,
            (string)($user['email'] ?? 'admin@voak.health')
        );

        return Response::success([
            'status'           => 'setup_initiated',
            'secret'           => $setup['secret'],
            'provisioning_uri' => $setup['provisioning_uri'],
            'backup_codes'     => $setup['backup_codes'],
            'message'          => 'Scan QR code in authenticator app and call confirm endpoint with 6-digit code to activate.',
        ], 200);
    }

    /**
     * POST /api/v1/admin/auth/mfa/confirm
     * Requires active administrator session.
     */
    public function confirmMfa(Request $request): Response
    {
        $userId = $request->getAttribute('admin_user_id');
        if ($userId === null) {
            throw new AuthenticationException("Unauthenticated.");
        }

        $data = $request->getJson();
        $code = (string)($data['totp_code'] ?? '');

        if ($code === '') {
            throw new ValidationException("6-digit TOTP code is required.", ['totp_code' => ['Code is required.']]);
        }

        $confirmed = $this->totpService->confirmSetup(
            $this->connection->getPdo(),
            (int)$userId,
            $code
        );

        if (!$confirmed) {
            throw new ValidationException("Invalid TOTP verification code. Setup not confirmed.", [
                'totp_code' => ['Verification code mismatch. Check device clock synchronization.']
            ]);
        }

        return Response::success([
            'status'  => 'success',
            'message' => 'Multi-factor authentication (MFA) successfully confirmed and activated.',
        ], 200);
    }
}
