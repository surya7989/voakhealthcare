<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Security\Crypto;

/**
 * Administrative Authentication and Session Controller.
 * Endpoints for login, logout, identity inspection, and password recovery.
 */
class AdminAuthController
{
    private Connection $connection;
    private AuthenticationService $authService;
    private SessionManager $sessionManager;
    private PasswordResetService $resetService;
    private RbacManager $rbacManager;
    private Config $config;

    public function __construct(
        Connection $connection,
        AuthenticationService $authService,
        SessionManager $sessionManager,
        PasswordResetService $resetService,
        RbacManager $rbacManager,
        Config $config
    ) {
        $this->connection = $connection;
        $this->authService = $authService;
        $this->sessionManager = $sessionManager;
        $this->resetService = $resetService;
        $this->rbacManager = $rbacManager;
        $this->config = $config;
    }

    /**
     * POST /api/v1/admin/auth/login
     */
    public function login(Request $request): Response
    {
        $data = $request->getJson();
        $email = (string)($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new ValidationException("Email and password are required.", [
                'email'    => empty($email) ? ['Email is required.'] : [],
                'password' => empty($password) ? ['Password is required.'] : [],
            ]);
        }

        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Unknown';

        $result = $this->authService->authenticate(
            $this->connection->getPdo(),
            $email,
            $password,
            $ip,
            $ua
        );

        if ($result['status'] === 'requires_mfa') {
            return Response::success([
                'status'         => 'requires_mfa',
                'pre_auth_token' => $result['pre_auth_token'],
                'message'        => 'Multi-factor authentication challenge required.',
            ], 200);
        }

        $isProduction = (string)$this->config->get('APP_ENV') === 'production';
        $cookie = $this->sessionManager->buildSessionCookie(
            $result['session']['session_id'],
            $isProduction
        );

        $response = Response::success([
            'status'     => 'authenticated',
            'user'       => $result['user'],
            'session'    => $result['session'],
            'csrf_token' => $result['session']['csrf_token'],
            'expires_at' => $result['session']['expires_at'],
        ], 200);

        return $response->withHeader('Set-Cookie', $cookie);
    }

    /**
     * POST /api/v1/admin/auth/logout
     */
    public function logout(Request $request): Response
    {
        $sessionId = $request->getAttribute('admin_session_id');
        if ($sessionId !== null) {
            $this->sessionManager->destroySession($this->connection->getPdo(), (string)$sessionId);
        }

        $isProduction = (string)$this->config->get('APP_ENV') === 'production';
        $expiredCookie = $this->sessionManager->buildExpiredCookie($isProduction);

        $response = Response::success([
            'status'  => 'success',
            'message' => 'Logged out successfully.',
        ], 200);

        return $response->withHeader('Set-Cookie', $expiredCookie);
    }

    /**
     * GET /api/v1/admin/auth/me
     */
    public function me(Request $request): Response
    {
        $userId = $request->getAttribute('admin_user_id');
        if ($userId === null) {
            throw new AuthenticationException("Unauthenticated.");
        }

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT `id`, `name`, `email`, `created_at` FROM `users` WHERE `id` = :uid");
        $stmt->execute(['uid' => (int)$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$user) {
            throw new AuthenticationException("User record not found.");
        }

        $roles = $this->rbacManager->getUserRoles($pdo, (int)$userId);
        $permissions = $this->rbacManager->getUserPermissions($pdo, (int)$userId);

        return Response::success([
            'id'          => (int)$user['id'],
            'name'        => (string)$user['name'],
            'email'       => (string)$user['email'],
            'roles'       => $roles,
            'permissions' => $permissions,
            'user' => [
                'id'          => (int)$user['id'],
                'name'        => (string)$user['name'],
                'email'       => (string)$user['email'],
                'roles'       => $roles,
                'permissions' => $permissions,
                'created_at'  => (string)$user['created_at'],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/admin/auth/csrf
     */
    public function csrf(Request $request): Response
    {
        $sessionId = $request->getAttribute('admin_session_id');

        if ($sessionId !== null) {
            // Authenticated session: regenerate session CSRF token
            $rawToken = Crypto::randomToken(32);
            $tokenHash = hash('sha256', $rawToken);

            $stmt = $this->connection->getPdo()->prepare("UPDATE `admin_sessions` SET `csrf_token_hash` = :hash WHERE `id` = :id");
            $stmt->execute(['hash' => $tokenHash, 'id' => $sessionId]);
            $stmt->closeCursor();

            return Response::success([
                'csrf_header' => 'X-CSRF-Token',
                'csrf_token'  => $rawToken,
            ], 200);
        }

        // Unauthenticated CSRF bootstrap flow
        $csrfMiddleware = new \Voak\Health\Middleware\CsrfProtectionMiddleware($this->connection, $this->config);
        $bootstrap = $csrfMiddleware->generateBootstrapToken();

        $response = Response::success([
            'csrf_header' => 'X-CSRF-Token',
            'csrf_token'  => $bootstrap['token'],
        ], 200);

        return $response->withHeader('Set-Cookie', $bootstrap['cookie_header']);
    }

    /**
     * POST /api/v1/admin/auth/password-reset/request
     */
    public function requestPasswordReset(Request $request): Response
    {
        $data = $request->getJson();
        $email = (string)($data['email'] ?? '');

        if ($email === '') {
            throw new ValidationException("Email is required.", ['email' => ['Email field is required.']]);
        }

        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Unknown';

        // Dispatches reset token internally; returns token for test/CLI environments
        $token = $this->resetService->requestReset($this->connection->getPdo(), $email, $ip, $ua);

        $payload = [
            'status'  => 'success',
            'message' => 'If the account exists and is active, a password reset link has been dispatched.',
        ];

        // Disclose reset token only when explicitly authorized by test configuration (never in production or standard staging)
        $allowDevTokens = $this->config->get('ALLOW_DEV_RESET_TOKENS', false);
        $isExplicitTest = ($allowDevTokens === true || $allowDevTokens === '1' || $allowDevTokens === 'true');
        if ($isExplicitTest && (string)$this->config->get('APP_ENV') !== 'production' && $token !== null) {
            $payload['dev_reset_token'] = $token;
        }

        return Response::success($payload, 200);
    }

    /**
     * POST /api/v1/admin/auth/password-reset/confirm
     */
    public function confirmPasswordReset(Request $request): Response
    {
        $data = $request->getJson();
        $token = (string)($data['token'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($token === '' || $password === '') {
            throw new ValidationException("Token and new password are required.", [
                'token'    => empty($token) ? ['Token is required.'] : [],
                'password' => empty($password) ? ['Password is required.'] : [],
            ]);
        }

        $ip = $request->getClientIp();
        $ua = $request->getHeader('user-agent') ?? 'Unknown';

        $success = $this->resetService->confirmReset(
            $this->connection->getPdo(),
            $token,
            $password,
            $ip,
            $ua
        );

        if (!$success) {
            throw new ValidationException("Password reset token is invalid, expired, or already used.", [
                'token' => ['Token is invalid or expired. Please request a new link.']
            ]);
        }

        return Response::success([
            'status'  => 'success',
            'message' => 'Password has been successfully updated. All active sessions have been terminated. You may now log in.',
        ], 200);
    }
}
