<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

class CategoryController
{
    public function __construct(private View $view)
    {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Category List']));
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(201, ['Content-Type' => 'application/json'], json_encode(['message' => 'Category Created']));
    }

    public function show(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Category Details', 'id' => $vars['id']]));
    }

    public function update(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Category Updated', 'id' => $vars['id']]));
    }

    public function destroy(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Category Deleted', 'id' => $vars['id']]));
    }
}
