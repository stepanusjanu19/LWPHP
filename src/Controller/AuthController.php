<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;
use Psr\Log\LoggerInterface;

class AuthController
{
    public function __construct(
        private View $view,
        private LoggerInterface $logger
    ) {
    }

    public function loginForm(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->view->render('auth/login');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        // For demo/generator purpose, we'll allow any non-empty credentials
        // In real app, verify against DB
        if (!empty($username) && !empty($password)) {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'admin'; // Assign root/admin role
            $_SESSION['auth_key'] = bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $request->getHeaderLine('User-Agent');
            $_SESSION['auth_signature'] = hash('sha256', $_SESSION['auth_key'] . $ip . $userAgent);

            $this->logger->info("User logged in: {$username}");

            return new Response(302, ['Location' => '/admin']);
        }

        $html = $this->view->render('auth/login', ['error' => 'Invalid credentials']);
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        return new Response(302, ['Location' => '/']);
    }
}
