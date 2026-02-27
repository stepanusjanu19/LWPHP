<?php

namespace Kei\Lwphp\Base;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Abstract HTTP Controller — SOLID base for all application controllers.
 *
 * Provides:
 *   - json()        — PSR-7 JSON response builder
 *   - parseBody()   — reads security-sanitized body from request attribute first
 *   - paginate()    — slice + page metadata for any array
 *   - created()     — 201 shortcut
 *   - noContent()   — 204 shortcut
 *   - notFound()    — 404 shortcut
 *   - badRequest()  — 400 shortcut
 *
 * Concrete controllers inject their own dependencies via constructor
 * and call parent::__construct().
 */
abstract class Controller
{
    protected readonly Psr17Factory $factory;
    
    #[\DI\Attribute\Inject]
    protected ?\Kei\Lwphp\Core\View $view = null;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    public function setView(\Kei\Lwphp\Core\View $view): void
    {
        $this->view = $view;
    }

    // ── Response Builders ─────────────────────────────────────────────────────

    protected function render(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        if (!$this->view) {
            throw new \RuntimeException("View engine not injected into controller. Please call setView() or configure DI autowiring.");
        }

        $html = $this->view->render($template, $data);
        $body = $this->factory->createStream($html);

        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($body);
    }

    protected function json(int $status, mixed $data): ResponseInterface
    {
        $body = $this->factory->createStream(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    protected function created(mixed $data, string $message = 'Created'): ResponseInterface
    {
        return $this->json(201, ['data' => $data, 'message' => $message]);
    }

    protected function noContent(): ResponseInterface
    {
        return $this->factory->createResponse(204);
    }

    protected function notFound(string $resource = 'Resource', ?int $id = null): ResponseInterface
    {
        $msg = $id !== null ? "{$resource} #{$id} not found." : "{$resource} not found.";
        return $this->json(404, ['error' => $msg]);
    }

    protected function badRequest(string $message): ResponseInterface
    {
        return $this->json(400, ['error' => $message]);
    }

    protected function unprocessable(string $message, array $errors = []): ResponseInterface
    {
        return $this->json(422, array_filter(['error' => $message, 'errors' => $errors ?: null]));
    }

    // ── Request Parsing ───────────────────────────────────────────────────────

    /**
     * Parse the request body.
     *
     * Priority:
     *   1. Security-sanitized body from request attribute '_sanitized_body'
     *      (set by SecurityMiddleware — XSS-cleaned, SQLi-checked)
     *   2. Parsed body (form data)
     *   3. Raw JSON decode
     */
    protected function parseBody(ServerRequestInterface $request): array
    {
        // Use the pre-sanitized body set by SecurityMiddleware
        $sanitized = $request->getAttribute('_sanitized_body');
        if (is_array($sanitized)) {
            return $sanitized;
        }

        $parsed = $request->getParsedBody();
        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }

        if (str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            $data = json_decode((string) $request->getBody(), true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Extract pagination params from query string.
     *
     * @return array{page: int, limit: int}
     */
    protected function paginationParams(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        $page = max(1, (int) ($q['page'] ?? 1));
        $limit = min(100, max(1, (int) ($q['limit'] ?? 25)));
        return ['page' => $page, 'limit' => $limit];
    }

    /**
     * Slice an array into a page and return pagination metadata.
     *
     * @param  array $items All items (already fetched)
     * @return array{data: array, pagination: array}
     */
    protected function paginate(array $items, int $page = 1, int $limit = 25): array
    {
        $total = count($items);
        $pages = (int) ceil($total / max(1, $limit));
        $offset = ($page - 1) * $limit;

        return [
            'data' => array_values(array_slice($items, $offset, $limit)),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }
}
