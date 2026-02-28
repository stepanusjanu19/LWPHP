<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

use Kei\Lwphp\Repository\PostRepository;
use Kei\Lwphp\Repository\UserRepository;

class CmsController
{
    public function __construct(
        private View $view,
        private PostRepository $postRepo,
        private UserRepository $userRepo
        )
    {
    }

    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $postCount = $this->postRepo->count();
        $userCount = $this->userRepo->count();
        $sysLoad = sys_getloadavg()[0] ?? 0.00;

        $html = $this->view->render('cms/dashboard', [
            'username' => $_SESSION['username'] ?? 'Admin',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'total_posts' => $postCount,
            'total_users' => number_format($userCount),
            'sys_load' => number_format($sysLoad, 2)
        ]);

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}