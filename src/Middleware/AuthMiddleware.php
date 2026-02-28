<?php

namespace Kei\Lwphp\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    private array $roleRequirements = [
        '/admin' => 'admin',
        '/cms' => 'admin',
        '/posts' => 'admin',
        '/categorys' => 'admin',
        '/rpc' => 'admin',
    ];

    private array $protectedPaths;

    public function __construct()
    {
        $this->protectedPaths = array_keys($this->roleRequirements);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Skip auth for login/logout/register/forgot-password and home
        if ($path === '/' || str_starts_with($path, '/login') || str_starts_with($path, '/logout') || str_starts_with($path, '/register') || str_starts_with($path, '/forgot-password')) {
            return $handler->handle($request);
        }

        $isProtected = false;
        foreach ($this->protectedPaths as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $isProtected = true;
                break;
            }
        }

        if (!$isProtected) {
            return $handler->handle($request);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }


        if (empty($_SESSION['user_id'])) {
            return $this->unauthorized($request, 'Unauthenticated');
        }

        // 1. Signature Pattern Validation
        $sessionKey = $_SESSION['auth_key'] ?? '';
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $request->getHeaderLine('User-Agent');

        $currentSignature = hash('sha256', $sessionKey . $ip . $userAgent);
        $storedSignature = $_SESSION['auth_signature'] ?? '';

        if (!hash_equals($storedSignature, $currentSignature)) {
            session_destroy();
            return $this->unauthorized($request, 'Invalid session signature.', 403);
        }

        // 2. Authorization (RBAC)
        $userRole = $_SESSION['role'] ?? 'guest';
        foreach ($this->roleRequirements as $prefix => $requiredRole) {
            if (str_starts_with($path, $prefix)) {
                if ($userRole !== $requiredRole) {
                    return $this->unauthorized($request, "Permission Denied: Requires '{$requiredRole}' role.", 403);
                }
            }
        }

        // 3. CSRF Validation
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $csrfToken = $request->getHeaderLine('X-CSRF-Token');
            $body = $request->getParsedBody();
            $postCsrf = is_array($body) ? ($body['csrf_token'] ?? null) : null;
            $tokenToVerify = $csrfToken ?: $postCsrf;

            if (empty($tokenToVerify) || !hash_equals($_SESSION['csrf_token'] ?? '', $tokenToVerify)) {
                return $this->unauthorized($request, 'Invalid CSRF Token', 403);
            }
        }

        return $handler->handle($request);
    }

    private function unauthorized(ServerRequestInterface $request, string $message, int $status = 401): ResponseInterface
    {
        $factory = new Psr17Factory();
        $accept = $request->getHeaderLine('Accept');

        if (str_contains($accept, 'text/html') && $status === 401) {
            return $factory->createResponse(302)->withHeader('Location', '/login');
        }

        $response = $factory->createResponse($status);
        $response->getBody()->write(json_encode([
            'error' => $status === 403 ? 'Forbidden' : 'Unauthorized',
            'message' => $message
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}