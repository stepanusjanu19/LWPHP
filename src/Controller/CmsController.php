<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

class CmsController
{
    public function __construct(private View $view)
    {
    }

    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $html = $this->view->render('cms/dashboard', [
            'username' => $_SESSION['username'] ?? 'Admin',
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
