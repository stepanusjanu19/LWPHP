<?php

namespace Kei\Lwphp\Async;

/**
 * FiberPool
 *
 * Cooperative scheduler that runs multiple AsyncJobs concurrently on a
 * single thread using PHP 8.2 Fibers.
 *
 * - Jobs that yield give control back to the pool (cooperative multitasking).
 * - CPU-bound jobs run to completion on first start; the pool is still
 *   useful for interleaving I/O-heavy operations without blocking.
 *
 * Usage:
 *   $pool = new FiberPool();
 *   $pool->add('primes', fn() => computePrimes(100_000));
 *   $pool->add('fib',    fn() => fibonacci(35));
 *   $results = $pool->run();
 */
class FiberPool
{
    /** @var AsyncJob[] */
    private array $jobs = [];
    private int $maxConcurrent;

    public function __construct(int $maxConcurrent = 10)
    {
        $this->maxConcurrent = $maxConcurrent;
    }

    public function add(string $name, \Closure $task): self
    {
        $this->jobs[] = new AsyncJob($name, $task);
        return $this;
    }

    /**
     * Run all jobs cooperatively and return an array of results keyed by job name.
     *
     * @return array{name: string, result: mixed, error: string|null, elapsed_ms: float}[]
     */
    public function run(): array
    {
        $queue = $this->jobs;
        $active = [];
        $results = [];

        while (!empty($queue) || !empty($active)) {
            // Fill active slots
            while (!empty($queue) && count($active) < $this->maxConcurrent) {
                $job = array_shift($queue);
                $job->start();
                if ($job->isTerminated()) {
                    $results[] = $this->collectResult($job);
                } else {
                    $active[] = $job;
                }
            }

            // Tick suspended jobs
            foreach ($active as $i => $job) {
                if ($job->isSuspended()) {
                    $job->resume();
                }
                if ($job->isTerminated()) {
                    $results[] = $this->collectResult($job);
                    unset($active[$i]);
                }
            }

            $active = array_values($active);
        }

        $this->jobs = []; // reset pool
        return $results;
    }

    /**
     * Run jobs in parallel subprocesses via proc_open().
     *
     * Web-server SAFE — spawns fresh PHP processes (bin/job_worker.php),
     * so no inherited HTTP sockets or output buffers.
     * Works under CLI, PHP-FPM, and PHP built-in server.
     *
     * Falls back to cooperative Fiber mode if worker script is missing.
     *
     * @return array{name: string, result: mixed, elapsed_ms: float}[]
     */
    public function runParallel(): array
    {
        $workerScript = dirname(__DIR__, 2) . '/bin/job_worker.php';

        if (!file_exists($workerScript)) {
            return $this->run(); // graceful fallback
        }

        $phpBin = PHP_BINARY;
        $processes = [];
        $results = [];

        // Spawn one process per job
        foreach ($this->jobs as $job) {
            $jobName = $job->getName();
            $cmd = escapeshellcmd($phpBin) . ' '
                . escapeshellarg($workerScript) . ' '
                . escapeshellarg($jobName);

            $spec = [
                0 => ['pipe', 'r'],   // stdin  (unused)
                1 => ['pipe', 'w'],   // stdout (JSON result)
                2 => ['pipe', 'w'],   // stderr (error text)
            ];

            $proc = proc_open($cmd, $spec, $pipes);
            if ($proc === false) {
                continue;
            }

            fclose($pipes[0]); // close child stdin

            $processes[] = [
                'proc' => $proc,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
                'name' => $jobName,
                'started' => hrtime(true),
            ];
        }

        // Collect results as each process finishes (blocking per process)
        foreach ($processes as $entry) {
            $stdout = stream_get_contents($entry['stdout']);
            $stderr = stream_get_contents($entry['stderr']);
            fclose($entry['stdout']);
            fclose($entry['stderr']);
            $exitCode = proc_close($entry['proc']);
            $wallMs = (hrtime(true) - $entry['started']) / 1e6;

            $data = @json_decode((string) $stdout, true);

            $results[] = [
                'name' => $entry['name'],
                'result' => is_array($data) ? ($data['result'] ?? null) : null,
                'error' => is_array($data) ? ($data['error'] ?? null) : ($stderr ?: null),
                'elapsed_ms' => is_array($data) && isset($data['elapsed_ms'])
                    ? round($data['elapsed_ms'], 3)
                    : round($wallMs, 3),
                'exit_code' => $exitCode,
            ];
        }

        $this->jobs = [];
        return $results;
    }

    private function collectResult(AsyncJob $job): array
    {
        return [
            'name' => $job->getName(),
            'result' => $job->getResult(),
            'error' => $job->getError()?->getMessage(),
            'elapsed_ms' => round($job->elapsedMs(), 3),
        ];
    }
}
