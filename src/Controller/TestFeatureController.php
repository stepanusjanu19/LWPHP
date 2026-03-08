<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller;
use Kei\Lwphp\Service\TestFeatureService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use DI\Attribute\Inject;

class TestFeatureController extends Controller
{
    public function __construct(
        private readonly TestFeatureService $service
    ) {
        parent::__construct();
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json(200, ['message' => 'TestFeature API']);
    }
}
