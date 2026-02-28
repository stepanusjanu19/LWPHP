<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use FastRoute\Dispatcher;
use Kei\Lwphp\Async\JobDispatcher;
use Kei\Lwphp\Controller\BenchmarkController;
use Kei\Lwphp\Controller\JobController;
use Kei\Lwphp\Controller\SchemaController;
use Kei\Lwphp\Controller\TaskController;
use Kei\Lwphp\Core\ConfigLoader;
use Kei\Lwphp\Middleware\BandwidthMiddleware;
use Kei\Lwphp\Middleware\SecurityMiddleware;
use Kei\Lwphp\Repository\DoctrineTaskRepository;
use Kei\Lwphp\Repository\JobRepository;
use Kei\Lwphp\Repository\TaskRepositoryInterface;
use Kei\Lwphp\Routing\Router;
use Kei\Lwphp\Security\AnomalyDetector;
use Kei\Lwphp\Security\InputSanitizer;
use Kei\Lwphp\Security\IpFilter;
use Kei\Lwphp\Security\RateLimiter;
use Kei\Lwphp\Security\SsrfGuard;
use Kei\Lwphp\Security\ThreatLogger;
use Kei\Lwphp\Service\HeavyJobService;
use Kei\Lwphp\Service\JobQueue;
use Kei\Lwphp\Service\QueueWorker;
use Kei\Lwphp\Service\TaskService;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;


return [
    // ------------------------------------------------------------------
    // Core framework config
    // ------------------------------------------------------------------
  ConfigLoader::class => \DI\factory(function (): ConfigLoader {
    $loader = new ConfigLoader();
    return $loader;
  }),

  Router::class => \DI\factory(function (ConfigLoader $config): Router {
    return new Router($config);
  }),

  Dispatcher::class => \DI\factory(function (Router $router): Dispatcher {
    return $router->getDispatcher();
  }),

  \Kei\Lwphp\Core\View::class => \DI\factory(function (ConfigLoader $config) {
    $templateDir = base_path('resources/views');
    if (!is_dir($templateDir)) {
      @mkdir($templateDir, 0755, true);
    }
    $cacheDir = $config->get('cache.stores.file.path', sys_get_temp_dir() . '/lwphp_cache') . '/twig';
    $debug = (bool) $config->get('app.debug', true);
    return new \Kei\Lwphp\Core\View($templateDir, $cacheDir, $debug);
  }),

    // ------------------------------------------------------------------
    // Doctrine ORM — multi-driver (SQLite default)
    // ------------------------------------------------------------------
  EntityManagerInterface::class => \DI\factory(
    function (ConfigLoader $config): EntityManagerInterface {
      $default = $config->get('database.default', 'sqlite');
      $dbParams = $config->get("database.connections.{$default}", []);
      $entityPath = $config->get('database.entity_path', base_path('src/Entity'));

      // For SQLite: auto-create the storage directory and ensure absolute path
      if (($dbParams['driver'] ?? '') === 'pdo_sqlite') {
        $sqlitePath = $dbParams['path'] ?? storage_path('database.sqlite');
        // Make path absolute if it's relative
        if (!str_starts_with($sqlitePath, '/')) {
          $sqlitePath = base_path($sqlitePath);
        }
        $dbParams['path'] = $sqlitePath;
        $dir = dirname($sqlitePath);
        if (!is_dir($dir)) {
          mkdir($dir, 0755, true);
        }
      }

      $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
        paths: [$entityPath],
        isDevMode: (bool) $config->get('app.debug', true),
      );

      $connection = DriverManager::getConnection($dbParams, $ormConfig);
      return new EntityManager($connection, $ormConfig);
    }
  ),

  EntityManager::class => \DI\get(EntityManagerInterface::class),

    // ------------------------------------------------------------------
    // Repository layer — DoctrineTaskRepository (real SQLite/MySQL/PG/MSSQL)
    // ------------------------------------------------------------------
  TaskRepositoryInterface::class => \DI\factory(
    function (EntityManagerInterface $em, ConfigLoader $config): TaskRepositoryInterface {
      $repo = new DoctrineTaskRepository($em);
      // Seed demo data only on first boot (dev mode)
      if ((bool) $config->get('app.debug', true)) {
        try {
          $repo->seed();
        } catch (\Throwable) {
        }
      }
      return $repo;
    }
  ),

  JobRepository::class => \DI\create(JobRepository::class)
    ->constructor(\DI\get(EntityManagerInterface::class)),

    // ------------------------------------------------------------------
    // Service layer
    // ------------------------------------------------------------------
  TaskService::class => \DI\create(TaskService::class)
    ->constructor(\DI\get(TaskRepositoryInterface::class), \DI\get(LoggerInterface::class)),

  HeavyJobService::class => \DI\create(HeavyJobService::class),

  JobQueue::class => \DI\create(JobQueue::class)
    ->constructor(\DI\get(JobRepository::class), \DI\get(HeavyJobService::class), \DI\get(LoggerInterface::class)),

  JobDispatcher::class => \DI\create(JobDispatcher::class)
    ->constructor(\DI\get(ConfigLoader::class)),

    // ------------------------------------------------------------------
    // Controller layer
    // ------------------------------------------------------------------
  TaskController::class => \DI\autowire(TaskController::class),

  BenchmarkController::class => \DI\create(BenchmarkController::class)
    ->constructor(\DI\get(HeavyJobService::class), \DI\get(JobDispatcher::class)),

  JobController::class => \DI\autowire(JobController::class),

  SchemaController::class => \DI\create(SchemaController::class)
    ->constructor(\DI\get(JobRepository::class)),

    // ------------------------------------------------------------------
    // Security layer
    // ------------------------------------------------------------------
  RateLimiter::class => \DI\factory(function (EntityManagerInterface $em, ConfigLoader $config): RateLimiter {
    return new RateLimiter(
      $em->getConnection(),
      (int) $config->get('security.rate_limit.window_seconds', 60),
      (int) $config->get('security.rate_limit.max_requests', 180),
      (int) $config->get('security.rate_limit.burst_bonus', 20),
    );
  }),

  IpFilter::class => \DI\factory(function (ConfigLoader $config): IpFilter {
    return new IpFilter(
      (array) $config->get('security.blocked_ips', []),
      (array) $config->get('security.allowed_ips', []),
    );
  }),

  InputSanitizer::class => \DI\create(InputSanitizer::class),

  SsrfGuard::class => \DI\create(SsrfGuard::class),

  ThreatLogger::class => \DI\factory(function (ConfigLoader $config): ThreatLogger {
    return new ThreatLogger(
      (string) $config->get('app.logging.threat_log', storage_path('logs/threats.log'))
    );
  }),

  AnomalyDetector::class => \DI\factory(function (EntityManagerInterface $em, ThreatLogger $threatLogger, ConfigLoader $config): AnomalyDetector {
    return new AnomalyDetector(
      $em->getConnection(),
      $threatLogger,
      (int) $config->get('security.rate_limit.window_seconds', 60),
    );
  }),

  SecurityMiddleware::class => \DI\create(SecurityMiddleware::class)
    ->constructor(
      \DI\get(ConfigLoader::class),
      \DI\get(RateLimiter::class),
      \DI\get(IpFilter::class),
      \DI\get(InputSanitizer::class),
      \DI\get(SsrfGuard::class),
      \DI\get(AnomalyDetector::class),
      \DI\get(ThreatLogger::class),
      \DI\get(LoggerInterface::class),
    ),

  BandwidthMiddleware::class => \DI\factory(function (ConfigLoader $config, LoggerInterface $logger): BandwidthMiddleware {
    return new BandwidthMiddleware(
      logger: $logger,
      slowThresholdMs: (int) $config->get('bandwidth.slow_threshold_ms', 3000),
      gzipEnabled: (bool) $config->get('bandwidth.gzip', true),
    );
  }),

    // ------------------------------------------------------------------
    // Cache (plug-and-play)
    // ------------------------------------------------------------------
  CacheInterface::class => \DI\factory(function (ConfigLoader $config): CacheInterface {
    return new FilesystemAdapter(
      namespace: '',
      defaultLifetime: 0,
      directory: $config->get('cache.stores.file.path', sys_get_temp_dir() . '/lwphp_cache')
    );
  }),

    // ------------------------------------------------------------------
    // Logger (Monolog)
    // ------------------------------------------------------------------
  LoggerInterface::class => \DI\factory(function (ConfigLoader $config): LoggerInterface {
    $channel = (string) $config->get('app.logging.channel', 'app');
    $level = Level::fromName((string) $config->get('app.logging.level', 'debug'));
    $logPath = (string) $config->get('app.logging.path', storage_path('logs/lwphp.log'));
    $logDir = dirname($logPath);

    if (!is_dir($logDir)) {
      @mkdir($logDir, 0755, true);
    }

    $logger = new Logger($channel);
    $logger->pushHandler(new RotatingFileHandler($logPath, 7, $level));
    if ((bool) $config->get('app.debug', true)) {
      $logger->pushHandler(new StreamHandler('php://stderr', Level::Debug));
    }
    return $logger;
  }),

  \Kei\Lwphp\Service\PostService::class => \DI\autowire(\Kei\Lwphp\Service\PostService::class),

  \Kei\Lwphp\Service\GeneratorService::class => \DI\autowire(\Kei\Lwphp\Service\GeneratorService::class),

  \Kei\Lwphp\Controller\PostController::class => \DI\autowire(\Kei\Lwphp\Controller\PostController::class),

  \Kei\Lwphp\Controller\HomeController::class => \DI\autowire(\Kei\Lwphp\Controller\HomeController::class),

  \Kei\Lwphp\Controller\AuthController::class => \DI\autowire(\Kei\Lwphp\Controller\AuthController::class),

  \Kei\Lwphp\Controller\CmsController::class => \DI\autowire(\Kei\Lwphp\Controller\CmsController::class),

  \Kei\Lwphp\Controller\DocsController::class => \DI\autowire(\Kei\Lwphp\Controller\DocsController::class),

  \Kei\Lwphp\Controller\CategoryController::class => \DI\autowire(\Kei\Lwphp\Controller\CategoryController::class),

  \Kei\Lwphp\Service\ArticleService::class => \DI\autowire(\Kei\Lwphp\Service\ArticleService::class),

  \Kei\Lwphp\Controller\ArticleController::class => \DI\autowire(\Kei\Lwphp\Controller\ArticleController::class),

  \Kei\Lwphp\Service\LandingFeatureService::class => \DI\autowire(\Kei\Lwphp\Service\LandingFeatureService::class),

  \Kei\Lwphp\Controller\LandingFeatureController::class => \DI\autowire(\Kei\Lwphp\Controller\LandingFeatureController::class),

];