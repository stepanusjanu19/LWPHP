<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingEditorController extends Controller
{
    public function index(Request $request, array $args): Response
    {
        // Serve the Livewire SPA container inside the generic dashboard layout
        return $this->render('landing_editor/index');
    }
}
