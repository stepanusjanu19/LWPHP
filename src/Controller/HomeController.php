<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;
use Kei\Lwphp\Repository\LandingFeatureRepository;
use Kei\Lwphp\Service\HeroSettingService;
use Kei\Lwphp\Service\TestimonialService;

class HomeController
{
    public function __construct(
        private View $view,
        private LandingFeatureRepository $featureRepo,
        private HeroSettingService $heroService,
        private TestimonialService $testimonialService
    ) {
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
        $hero = $this->heroService->getSettings();
        $testimonials = $this->testimonialService->getAll();

        $html = $this->view->render('home', [
            'features' => $features,
            'hero' => $hero,
            'testimonials' => $testimonials
        ]);
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}