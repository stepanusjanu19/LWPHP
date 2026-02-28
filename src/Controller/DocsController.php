<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

class DocsController
{
    public function __construct(private View $view)
    {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $html = $this->view->render('cms/docs');

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}