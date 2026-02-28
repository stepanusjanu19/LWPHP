<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;
use Psr\Log\LoggerInterface;

use Kei\Lwphp\Repository\UserRepository;

class AuthController
{
    public function __construct(
        private View $view,
        private LoggerInterface $logger,
        private UserRepository $userRepository
        )
    {
    }

    public function loginForm(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return new Response(302, ['Location' => '/admin']);
        }

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

        // Verify against DB
        if (!empty($username) && !empty($password)) {
            $user = $this->userRepository->findByUsername($username);

            if ($user && password_verify($password, $user->getPasswordHash())) {
                $_SESSION['user_id'] = $user->getId();
                $_SESSION['username'] = $user->getUsername();
                $_SESSION['role'] = $user->getRole();
                $_SESSION['auth_key'] = bin2hex(random_bytes(16));
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $request->getHeaderLine('User-Agent');
                $_SESSION['auth_signature'] = hash('sha256', $_SESSION['auth_key'] . $ip . $userAgent);

                $this->logger->info("User logged in: {$username}");

                return new Response(302, ['Location' => '/admin']);
            }
        }

        $html = $this->view->render('auth/login', ['error' => 'Invalid credentials']);
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function registerForm(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return new Response(302, ['Location' => '/admin']);
        }

        $html = $this->view->render('auth/register');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (!empty($username) && !empty($email) && !empty($password)) {
            if ($this->userRepository->findByUsername($username) || $this->userRepository->findByEmail($email)) {
                $html = $this->view->render('auth/register', ['error' => 'Username or email already exists.']);
                return new Response(200, ['Content-Type' => 'text/html'], $html);
            }

            $user = new \Kei\Lwphp\Entity\User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
            $user->setRole('user');

            $this->userRepository->save($user);

            $this->logger->info("User registered: {$username} ({$email})");
            $html = $this->view->render('auth/register', ['success' => 'Account created! You can now log in.']);
            return new Response(200, ['Content-Type' => 'text/html'], $html);
        }

        $html = $this->view->render('auth/register', ['error' => 'Please fill in all fields.']);
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function forgotPasswordForm(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->view->render('auth/forgot-password');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function forgotPassword(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';

        if (!empty($email)) {
            $this->logger->info("Password reset requested for: {$email}");
            $html = $this->view->render('auth/forgot-password', ['success' => 'If an account exists, a reset link was sent.']);
            return new Response(200, ['Content-Type' => 'text/html'], $html);
        }

        $html = $this->view->render('auth/forgot-password', ['error' => 'Please provide a valid email.']);
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