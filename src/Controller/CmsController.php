<?php

namespace Kei\Lwphp\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

use Kei\Lwphp\Repository\PostRepository;
use Kei\Lwphp\Repository\UserRepository;

class CmsController
{
    public function __construct(
        private View $view,
        private PostRepository $postRepo,
        private UserRepository $userRepo
    ) {
    }

    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $postCount = $this->postRepo->count();
        $userCount = $this->userRepo->count();
        $sysLoad = sys_getloadavg()[0] ?? 0.00;

        $html = $this->view->render('cms/dashboard', [
            'username' => $_SESSION['username'] ?? 'Admin',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'total_posts' => $postCount,
            'total_users' => number_format($userCount),
            'sys_load' => number_format($sysLoad, 2)
        ]);

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function systemInfo(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $html = $this->view->render('cms/system_info', [
            'username' => $_SESSION['username'] ?? 'Admin',
            'php_version' => PHP_VERSION,
            'os' => php_uname(),
            'server_software' => $request->getServerParams()['SERVER_SOFTWARE'] ?? 'Unknown CLI/Built-in',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    public function enabledFeatures(ServerRequestInterface $request): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulating discovered modules from src/Controller directory
        $modules = [];
        $controllers = glob(__DIR__ . '/*Controller.php');
        foreach ($controllers as $file) {
            $base = basename($file, 'Controller.php');
            if (!in_array($base, ['Cms', 'Auth', 'Home', 'Livewire', 'RpcGateway', 'Sitemap', 'Benchmark', 'Docs'])) {
                $modules[] = $base;
            }
        }

        $html = $this->view->render('cms/enabled_features', [
            'username' => $_SESSION['username'] ?? 'Admin',
            'modules' => $modules
        ]);

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}