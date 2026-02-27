<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Async\JobDispatcher;
use Kei\Lwphp\Base\Controller as BaseController;
use Kei\Lwphp\Core\App;
use Kei\Lwphp\Service\HeavyJobService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BenchmarkController — Sync / Async Fiber / Parallel subprocess benchmarking.
 *
 * Extends Base\Controller for json() helper.
 */
class BenchmarkController extends BaseController
{
    public function __construct(
        private readonly HeavyJobService $heavyJob,
        private readonly JobDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    // POST /benchmark/sync
    public function sync(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $start = hrtime(true);
        $results = $this->dispatcher->runSyncBatch($this->heavyJob->allJobs());
        $wallMs = round((hrtime(true) - $start) / 1e6, 3);

        return $this->json(200, [
            'mode' => 'sync_sequential',
            'wall_time_ms' => $wallMs,
            'total_cpu_ms' => JobDispatcher::totalMs($results),
            'jobs' => $this->stripResults($results),
            'explanation' => 'Jobs run one-after-another. Wall time ≈ sum of all job times.',
        ]);
    }

    // POST /benchmark/async
    public function async(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $start = hrtime(true);
        $results = $this->dispatcher->runAsync($this->heavyJob->allJobs());
        $wallMs = round((hrtime(true) - $start) / 1e6, 3);

        return $this->json(200, [
            'mode' => 'async_fiber',
            'wall_time_ms' => $wallMs,
            'total_cpu_ms' => JobDispatcher::totalMs($results),
            'max_job_ms' => JobDispatcher::maxMs($results),
            'jobs' => $this->stripResults($results),
            'explanation' => 'Cooperative PHP 8.2 Fibers. Best for mixed I/O + CPU workloads.',
        ]);
    }

    // POST /benchmark/parallel — web-server safe via proc_open()
    public function parallel(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $start = hrtime(true);
        $results = $this->dispatcher->runParallel($this->heavyJob->allJobs());
        $wallMs = round((hrtime(true) - $start) / 1e6, 3);

        return $this->json(200, [
            'mode' => 'parallel_subprocess',
            'wall_time_ms' => $wallMs,
            'total_cpu_ms' => JobDispatcher::totalMs($results),
            'max_job_ms' => JobDispatcher::maxMs($results),
            'jobs' => $this->stripResults($results),
            'explanation' => 'Each job runs in a fresh PHP subprocess via proc_open(). '
                . 'True CPU parallelism. Wall ≈ slowest single job.',
            'sapi' => PHP_SAPI,
        ]);
    }

    // GET /benchmark/info
    public function info(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $bootMs = round((hrtime(true) / 1e6) - App::$bootTime, 3);

        return $this->json(200, [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
            'framework_boot_ms' => $bootMs,
            'opcache_enabled' => function_exists('opcache_get_status')
                ? (opcache_get_status(false)['opcache_enabled'] ?? false)
                : false,
            'extensions' => [
                'pcntl' => extension_loaded('pcntl'),
                'sockets' => extension_loaded('sockets'),
                'redis' => extension_loaded('redis'),
                'opcache' => extension_loaded('Zend OPcache'),
            ],
            'fiber_support' => class_exists(\Fiber::class),
            'memory_limit' => ini_get('memory_limit'),
            'parallel_mode' => 'proc_open (web-server safe)',
        ]);
    }

    private function stripResults(array $results): array
    {
        return array_map(fn($r) => [
            'name' => $r['name'],
            'elapsed_ms' => $r['elapsed_ms'],
            'error' => $r['error'] ?? null,
        ], $results);
    }
}
