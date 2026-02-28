<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;
use Kei\Lwphp\Repository\LandingFeatureRepository;

class HomeController
{
    public function __construct(
        private View $view,
        private LandingFeatureRepository $featureRepo
        )
    {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return new Response(302, ['Location' => '/admin']);
        }
        // Fetch dynamic CMS elements for landing page
        $features = $this->featureRepo->findAll();

        $html = $this->view->render('home', [
            'features' => $features
        ]);
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}