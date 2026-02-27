<?php

namespace Kei\Lwphp\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * View engine integrating Twig templating for server-rendered interfaces.
 */
class View
{
    private Environment $twig;

    public function __construct(string $templateDir, ?string $cacheDir = null, bool $debug = false)
    {
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
