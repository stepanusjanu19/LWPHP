<?php

namespace Kei\Lwphp\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Psr\Container\ContainerInterface;

/**
 * View engine integrating Twig templating for server-rendered interfaces.
 */
class View
{
    private Environment $twig;
    private ContainerInterface $container;

    public function __construct(string $templateDir, ContainerInterface $container, ?string $cacheDir = null, bool $debug = false)
    {
        $this->container = $container;
        $loader = new FilesystemLoader($templateDir);

        $options = [
            'cache' => $debug ? false : ($cacheDir ?: false),
            'debug' => $debug,
            'auto_reload' => $debug,
            'strict_variables' => true,
        ];

        $this->twig = new Environment($loader, $options);

        // Add useful framework extensions/globals here if needed
        if ($debug) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Add Livewire Render Macro
        $this->twig->addFunction(new TwigFunction('render_livewire', function (string $componentName, array $params = []) {
            $className = "\\Kei\\Lwphp\\Livewire\\{$componentName}";
            if (!class_exists($className)) {
                return "<!-- Livewire Component Not Found: {$componentName} -->";
            }

            /** @var \Kei\Lwphp\Livewire\Component $component */
            $component = $this->container->get($className);
            $component->hydrate($params);

            $state = $component->dehydrate();
            $state['_id'] = $component->id;
            $state['_name'] = $componentName;

            // Inject global session variables required for HTMX transactions
            if (session_status() === PHP_SESSION_ACTIVE) {
                $state['csrf_token'] = $_SESSION['csrf_token'] ?? null;
            } else {
                $state['csrf_token'] = $state['csrf_token'] ?? null;
            }

            $viewPath = $component->render();
            $viewPath = str_replace('.twig', '', $viewPath);

            // Fetch the raw twig environment to prevent recursive injection loops in `render()` wrapper
            return $this->twig->render($viewPath . '.twig', $state);
        }, ['is_safe' => ['html']]));
    }

    /**
     * Renders a Twig template to a string.
     */
    public function render(string $template, array $context = []): string
    {
        // Auto-append .twig extension if missing
        if (!str_ends_with($template, '.twig') && !str_ends_with($template, '.html')) {
            $template .= '.twig';
        }

        // Auto-inject common session globals if session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            $context['user'] = $_SESSION['user_id'] ?? null;
            $context['username'] = $_SESSION['username'] ?? null;
            $context['csrf_token'] = $_SESSION['csrf_token'] ?? null;
        } else {
            // Default nulls to avoid Twig strict variable errors
            $context['user'] = $context['user'] ?? null;
            $context['username'] = $context['username'] ?? null;
            $context['csrf_token'] = $context['csrf_token'] ?? null;
            $context['error'] = $context['error'] ?? null;
        }

        return $this->twig->render($template, $context);
    }

    /**
     * Expose raw Twig environment for advanced extension.
     */
    public function getEnvironment(): Environment
    {
        return $this->twig;
    }
}
