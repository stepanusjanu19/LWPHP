<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller;
use Kei\Lwphp\Service\PostService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SitemapController extends Controller
{
    public function __construct(
        private readonly PostService $postService
    ) {
        parent::__construct();
    }

    public function index(Request $request): Response
    {
        $posts = $this->postService->getAll();
        $domain = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Static URLs
        $xml .= $this->createUrNode($domain . '/', '1.0', 'daily');
        $xml .= $this->createUrNode($domain . '/posts/feed', '0.8', 'daily');
        
        // Dynamic Posts
        foreach ($posts as $post) {
            $xml .= $this->createUrNode($domain . '/posts/' . $post->getId(), '0.6', 'weekly');
        }

        $xml .= '</urlset>';

        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->factory->createStream($xml));
    }

    private function createUrNode(string $loc, string $priority, string $changefreq): string
    {
        return sprintf(
            '<url><loc>%s</loc><priority>%s</priority><changefreq>%s</changefreq></url>',
            htmlspecialchars($loc),
            $priority,
            $changefreq
        );
    }
}