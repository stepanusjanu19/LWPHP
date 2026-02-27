<?php

namespace Kei\Lwphp\Routing;

use FastRoute\RouteCollector;
use Kei\Lwphp\Controller\BenchmarkController;
use Kei\Lwphp\Controller\PostController;
use Kei\Lwphp\Controller\CategoryController;
use Kei\Lwphp\Controller\JobController;
use Kei\Lwphp\Controller\SchemaController;
use Kei\Lwphp\Controller\TaskController;

class Routes
{
    public static function define(RouteCollector $r): void
    {
        // ── Auth ─────────────────────────────────────────────────────────────
        $r->get('/login', [\Kei\Lwphp\Controller\AuthController::class, 'loginForm']);
        $r->post('/login', [\Kei\Lwphp\Controller\AuthController::class, 'login']);
        $r->get('/logout', [\Kei\Lwphp\Controller\AuthController::class, 'logout']);

        // ── Home ─────────────────────────────────────────────────────────────
        $r->get('/', [\Kei\Lwphp\Controller\HomeController::class, 'index']);

        // ── CMS Dashboard ────────────────────────────────────────────────────
        $r->get('/admin', [\Kei\Lwphp\Controller\CmsController::class, 'dashboard']);

        // ── Schema UI Builder API ─────────────────────────────────────────
        $r->get('/schema', [SchemaController::class, 'index']);
        $r->get('/schema/{name}', [SchemaController::class, 'show']);
        $r->get('/workers/status', [SchemaController::class, 'workerStatus']);

        // ── Task Management API ───────────────────────────────────────────
        $r->get('/tasks', [TaskController::class, 'index']);
        $r->post('/tasks', [TaskController::class, 'store']);
        $r->get('/tasks/{id:\d+}', [TaskController::class, 'show']);
        $r->put('/tasks/{id:\d+}', [TaskController::class, 'update']);
        $r->delete('/tasks/{id:\d+}', [TaskController::class, 'destroy']);

        $r->post('/tasks/{id:\d+}/start', [TaskController::class, 'start']);
        $r->post('/tasks/{id:\d+}/complete', [TaskController::class, 'complete']);
        $r->post('/tasks/{id:\d+}/cancel', [TaskController::class, 'cancel']);

        // ── Job Queue API ─────────────────────────────────────────────────
        $r->post('/jobs/dispatch', [JobController::class, 'dispatchJob']);
        $r->get('/jobs', [JobController::class, 'index']);
        $r->delete('/jobs', [JobController::class, 'purge']);
        $r->get('/jobs/{id:\d+}', [JobController::class, 'show']);
        $r->delete('/jobs/{id:\d+}', [JobController::class, 'destroy']);

        // ── Category API ──────────────────────────────────────────────────────
        $r->get('/categorys', [CategoryController::class, 'index']);
        $r->post('/categorys', [CategoryController::class, 'store']);
        $r->get('/categorys/{id:\d+}', [CategoryController::class, 'show']);
        $r->put('/categorys/{id:\d+}', [CategoryController::class, 'update']);
        $r->delete('/categorys/{id:\d+}', [CategoryController::class, 'destroy']);

        // ── Post UI ──────────────────────────────────────────────────────
        $r->get('/posts', [PostController::class, 'index']);
        $r->get('/posts/create', [PostController::class, 'create']);
        $r->get('/posts/{id:\d+}/edit', [PostController::class, 'edit']);
        $r->post('/posts', [PostController::class, 'store']);
        $r->put('/posts/{id:\d+}', [PostController::class, 'update']);
        $r->delete('/posts/{id:\d+}', [PostController::class, 'destroy']);

        // ── Benchmark API ─────────────────────────────────────────────────
        $r->get('/benchmark/info', [BenchmarkController::class, 'info']);
        $r->post('/benchmark/sync', [BenchmarkController::class, 'sync']);
        $r->post('/benchmark/async', [BenchmarkController::class, 'async']);
        $r->post('/benchmark/parallel', [BenchmarkController::class, 'parallel']);

        // ── Livewire UI Component API ─────────────────────────────────────
        $r->post('/livewire/message/{name}', [\Kei\Lwphp\Controller\LivewireController::class, 'handle']);

        // ── RPC Gateway API ───────────────────────────────────────────────
        $r->post('/rpc', [\Kei\Lwphp\Controller\RpcGatewayController::class, 'handle']);
    }

}
