<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;

class LivewireController
{
    public function __construct(private View $view)
    {
    }

    public function handle(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $componentName = $vars['name'] ?? '';
        $className = "\\Kei\\Lwphp\\Livewire\\{$componentName}";

        if (!class_exists($className)) {
            $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            return $factory->createResponse(404)->withBody($factory->createStream('Component not found'));
        }

        // 1. Instantiate Component
        /** @var \Kei\Lwphp\Livewire\Component $component */
        $component = new $className();

        // 2. Hydrate State
        $payload = $request->getParsedBody() ?? [];
        $component->hydrate($payload);

        // 3. Optional: Call action (e.g., from an hx-post or custom header)
        $action = $request->getHeaderLine('HX-Trigger-Name'); // if the trigger element has a name attribute
        if ($action && method_exists($component, $action)) {
            $component->$action();
        }

        // 4. Dehydrate State
        $state = $component->dehydrate();
        $state['_id'] = $component->id;
        $state['_name'] = $componentName;

        // 5. Render View
        $viewPath = $component->render();
        // Remove .twig extension if present because View->render adds it normally, but let's be safe
        $viewPath = str_replace('.twig', '', $viewPath);

        $html = $this->view->render($viewPath, $state);

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $response = $factory->createResponse(200);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
