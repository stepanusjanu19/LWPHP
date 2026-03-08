<?php

namespace Kei\Lwphp\Routing;

use FastRoute\RouteCollector;
use Kei\Lwphp\Controller\BenchmarkController;
use Kei\Lwphp\Controller\UnitController;
use Kei\Lwphp\Controller\ReportController;
use Kei\Lwphp\Controller\SaleController;
use Kei\Lwphp\Controller\DeviceController;
use Kei\Lwphp\Controller\TicketController;
use Kei\Lwphp\Controller\TestimonialController;
use Kei\Lwphp\Controller\ProductController;
use Kei\Lwphp\Controller\LandingFeatureController;
use Kei\Lwphp\Controller\LandingEditorController;
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
        $r->get('/register', [\Kei\Lwphp\Controller\AuthController::class, 'registerForm']);
        $r->post('/register', [\Kei\Lwphp\Controller\AuthController::class, 'register']);
        $r->get('/forgot-password', [\Kei\Lwphp\Controller\AuthController::class, 'forgotPasswordForm']);
        $r->post('/forgot-password', [\Kei\Lwphp\Controller\AuthController::class, 'forgotPassword']);
        $r->get('/logout', [\Kei\Lwphp\Controller\AuthController::class, 'logout']);

        // ── Home & SEO ───────────────────────────────────────────────────────
        $r->get('/', [\Kei\Lwphp\Controller\HomeController::class, 'index']);
        $r->get('/sitemap.xml', [\Kei\Lwphp\Controller\SitemapController::class, 'index']);

        // ── CMS Dashboard ────────────────────────────────────────────────────
        $r->get('/admin', [\Kei\Lwphp\Controller\CmsController::class, 'dashboard']);
        $r->get('/cms', [\Kei\Lwphp\Controller\CmsController::class, 'dashboard']);
        $r->get('/dashboard', [\Kei\Lwphp\Controller\CmsController::class, 'dashboard']);
        $r->get('/system-info', [\Kei\Lwphp\Controller\CmsController::class, 'systemInfo']);
        $r->get('/enabled-features', [\Kei\Lwphp\Controller\CmsController::class, 'enabledFeatures']);
        $r->get('/docs', [\Kei\Lwphp\Controller\DocsController::class, 'index']);

        // ── Schema UI Builder API ─────────────────────────────────────────
        $r->get('/schema', [SchemaController::class, 'index']);
        $r->get('/schema/{name}', [SchemaController::class, 'show']);
        $r->get('/workers/status', [SchemaController::class, 'workerStatus']);

        // ── Task Management API ───────────────────────────────────────────
        $r->get('/tasks', [TaskController::class, 'index']);
        $r->post('/tasks', [TaskController::class, 'store']);
        $r->get('/tasks/{id:[a-zA-Z0-9]+}', [TaskController::class, 'show']);
        $r->put('/tasks/{id:[a-zA-Z0-9]+}', [TaskController::class, 'update']);
        $r->delete('/tasks/{id:[a-zA-Z0-9]+}', [TaskController::class, 'destroy']);

        $r->post('/tasks/{id:[a-zA-Z0-9]+}/start', [TaskController::class, 'start']);
        $r->post('/tasks/{id:[a-zA-Z0-9]+}/complete', [TaskController::class, 'complete']);
        $r->post('/tasks/{id:[a-zA-Z0-9]+}/cancel', [TaskController::class, 'cancel']);

        // ── Job Queue API ─────────────────────────────────────────────────
        $r->post('/jobs/dispatch', [JobController::class, 'dispatch']);
        $r->get('/jobs', [JobController::class, 'index']);
        $r->delete('/jobs', [JobController::class, 'purge']);
        $r->get('/jobs/{id:[a-zA-Z0-9]+}', [JobController::class, 'show']);
        $r->delete('/jobs/{id:[a-zA-Z0-9]+}', [JobController::class, 'destroy']);

        // ── Category API ──────────────────────────────────────────────────────
        $r->get('/categorys', [CategoryController::class, 'index']);
        $r->post('/categorys', [CategoryController::class, 'store']);
        $r->get('/categorys/{id:[a-zA-Z0-9]+}', [CategoryController::class, 'show']);
        $r->put('/categorys/{id:[a-zA-Z0-9]+}', [CategoryController::class, 'update']);
        $r->delete('/categorys/{id:[a-zA-Z0-9]+}', [CategoryController::class, 'destroy']);

        // ── Article UI ──────────────────────────────────────────────────────
        $r->get('/articles', [\Kei\Lwphp\Controller\ArticleController::class, 'index']);
        $r->get('/articles/create', [\Kei\Lwphp\Controller\ArticleController::class, 'create']);
        $r->get('/articles/{id:[a-zA-Z0-9]+}/edit', [\Kei\Lwphp\Controller\ArticleController::class, 'edit']);
        $r->post('/articles', [\Kei\Lwphp\Controller\ArticleController::class, 'store']);
        $r->put('/articles/{id:[a-zA-Z0-9]+}', [\Kei\Lwphp\Controller\ArticleController::class, 'update']);
        $r->delete('/articles/{id:[a-zA-Z0-9]+}', [\Kei\Lwphp\Controller\ArticleController::class, 'destroy']);

        // ── Post UI ──────────────────────────────────────────────────────
        $r->get('/posts', [PostController::class, 'index']);
        $r->get('/posts/feed', [PostController::class, 'feed']);
        $r->get('/posts/create', [PostController::class, 'create']);
        $r->get('/posts/{id:[a-zA-Z0-9]+}/edit', [PostController::class, 'edit']);
        $r->post('/posts', [PostController::class, 'store']);
        $r->put('/posts/{id:[a-zA-Z0-9]+}', [PostController::class, 'update']);
        $r->delete('/posts/{id:[a-zA-Z0-9]+}', [PostController::class, 'destroy']);

        // ── Landing Editor UI (SPA) ───────────────────────────────────────────────
        $r->get('/landing-editor', [LandingEditorController::class, 'index']);

        // ── LandingFeature UI ──────────────────────────────────────────────────────
        $r->get('/landing_features', [LandingFeatureController::class, 'index']);
        $r->get('/landing_features/feed', [LandingFeatureController::class, 'feed']);
        $r->get('/landing_features/create', [LandingFeatureController::class, 'create']);
        $r->get('/landing_features/{id:[a-zA-Z0-9]+}/edit', [LandingFeatureController::class, 'edit']);
        $r->post('/landing_features', [LandingFeatureController::class, 'store']);
        $r->put('/landing_features/{id:[a-zA-Z0-9]+}', [LandingFeatureController::class, 'update']);
        $r->delete('/landing_features/{id:[a-zA-Z0-9]+}', [LandingFeatureController::class, 'destroy']);

        // ── Product UI ──────────────────────────────────────────────────────
        $r->get('/products', [ProductController::class, 'index']);
        $r->get('/products/feed', [ProductController::class, 'feed']);
        $r->get('/products/create', [ProductController::class, 'create']);
        $r->get('/products/{id:[a-zA-Z0-9]+}/edit', [ProductController::class, 'edit']);
        $r->post('/products', [ProductController::class, 'store']);
        $r->put('/products/{id:[a-zA-Z0-9]+}', [ProductController::class, 'update']);
        $r->delete('/products/{id:[a-zA-Z0-9]+}', [ProductController::class, 'destroy']);

        // ── Testimonial UI ──────────────────────────────────────────────────────
        $r->get('/testimonials', [TestimonialController::class, 'index']);
        $r->get('/testimonials/feed', [TestimonialController::class, 'feed']);
        $r->get('/testimonials/create', [TestimonialController::class, 'create']);
        $r->get('/testimonials/{id:[a-zA-Z0-9]+}/edit', [TestimonialController::class, 'edit']);
        $r->post('/testimonials', [TestimonialController::class, 'store']);
        $r->put('/testimonials/{id:[a-zA-Z0-9]+}', [TestimonialController::class, 'update']);
        $r->delete('/testimonials/{id:[a-zA-Z0-9]+}', [TestimonialController::class, 'destroy']);

        // ── Ticket UI ──────────────────────────────────────────────────────
        $r->get('/tickets', [TicketController::class, 'index']);
        $r->get('/tickets/feed', [TicketController::class, 'feed']);
        $r->get('/tickets/create', [TicketController::class, 'create']);
        $r->get('/tickets/{id:[a-zA-Z0-9]+}/edit', [TicketController::class, 'edit']);
        $r->post('/tickets', [TicketController::class, 'store']);
        $r->put('/tickets/{id:[a-zA-Z0-9]+}', [TicketController::class, 'update']);
        $r->delete('/tickets/{id:[a-zA-Z0-9]+}', [TicketController::class, 'destroy']);

        // ── Device UI ──────────────────────────────────────────────────────
        $r->get('/devices', [DeviceController::class, 'index']);
        $r->get('/devices/feed', [DeviceController::class, 'feed']);
        $r->get('/devices/create', [DeviceController::class, 'create']);
        $r->get('/devices/{id:[a-zA-Z0-9]+}/edit', [DeviceController::class, 'edit']);
        $r->post('/devices', [DeviceController::class, 'store']);
        $r->put('/devices/{id:[a-zA-Z0-9]+}', [DeviceController::class, 'update']);
        $r->delete('/devices/{id:[a-zA-Z0-9]+}', [DeviceController::class, 'destroy']);

        // ── Sale UI ──────────────────────────────────────────────────────
        $r->get('/sales', [SaleController::class, 'index']);
        $r->get('/sales/feed', [SaleController::class, 'feed']);
        $r->get('/sales/create', [SaleController::class, 'create']);
        $r->get('/sales/{id:[a-zA-Z0-9]+}/edit', [SaleController::class, 'edit']);
        $r->post('/sales', [SaleController::class, 'store']);
        $r->put('/sales/{id:[a-zA-Z0-9]+}', [SaleController::class, 'update']);
        $r->delete('/sales/{id:[a-zA-Z0-9]+}', [SaleController::class, 'destroy']);

        // ── Report UI ──────────────────────────────────────────────────────
        $r->get('/reports', [ReportController::class, 'index']);
        $r->get('/reports/feed', [ReportController::class, 'feed']);
        $r->get('/reports/create', [ReportController::class, 'create']);
        $r->get('/reports/{id:[a-zA-Z0-9]+}/edit', [ReportController::class, 'edit']);
        $r->post('/reports', [ReportController::class, 'store']);
        $r->put('/reports/{id:[a-zA-Z0-9]+}', [ReportController::class, 'update']);
        $r->delete('/reports/{id:[a-zA-Z0-9]+}', [ReportController::class, 'destroy']);

        // ── Unit UI ──────────────────────────────────────────────────────
        $r->get('/units',                           [UnitController::class, 'index']);
        $r->get('/units/feed',                      [UnitController::class, 'feed']);
        $r->get('/units/create',                    [UnitController::class, 'create']);
        $r->get('/units/{id:[a-zA-Z0-9]+}/edit',             [UnitController::class, 'edit']);
        $r->post('/units',                          [UnitController::class, 'store']);
        $r->put('/units/{id:[a-zA-Z0-9]+}',                 [UnitController::class, 'update']);
        $r->delete('/units/{id:[a-zA-Z0-9]+}',              [UnitController::class, 'destroy']);

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