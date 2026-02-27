<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

class HomeController
{
    public function __construct(private View $view)
    {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->view->render('home');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
