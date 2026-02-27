<?php

namespace Kei\Lwphp\Base;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Component — Base class for Livewire-style HTMX reactive components.
 * 
 * Supports state hydration, action invocation, and re-rendering via Twig.
 */
abstract class Component extends Controller
{
    /** @var array<string, mixed> Component reactive state */
    public array $state = [];

    /** @var string The Twig template path for this component */
    protected string $template = '';

    /**
     * Initialize the component state
     */
    public function mount(array $initialState = []): void
    {
        $this->state = array_merge($this->state, $initialState);
    }

    /**
     * Handles the HTMX / AJAX request cycle: Hydrate -> Action -> Render
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $input = $this->parseBody($request);
        
        // 1. Hydrate state
        if (isset($input['state']) && is_array($input['state'])) {
            $this->state = array_merge($this->state, $input['state']);
        }

        // 2. Execute Action (HTMX could pass action via query string or body)
        $action = $request->getQueryParams()['action'] ?? $input['action'] ?? null;
        if ($action && method_exists($this, $action)) {
            // Unset action so it doesn't pollute state/arguments passed to action method
            unset($input['action']);
            $this->{$action}($input);
        }

        // 3. Re-render
        return $this->render($this->template, [
            'state' => $this->state,
            'component' => $this,
        ]);
    }
}
