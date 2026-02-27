#!/usr/bin/env php
<?php

/**
 * bin/job_worker.php
 *
 * CLI worker spawned by FiberPool::runParallel() via proc_open().
 * Receives a job name as argv[1], runs it, and writes JSON to stdout.
 *
 * This approach is web-server safe — a fresh process is created,
 * so there are no inherited HTTP sockets or output buffers.
 *
 * Usage (internal — called by FiberPool):
 *   php bin/job_worker.php primes
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/helpers.php';

$jobName = $argv[1] ?? '';

if ($jobName === '') {
    echo json_encode(['error' => 'No job name provided', 'name' => '', 'result' => null, 'elapsed_ms' => 0]);
    exit(1);
}

$service = new \Kei\Lwphp\Service\HeavyJobService();
$jobs = $service->allJobs();

if (!isset($jobs[$jobName])) {
    echo json_encode(['error' => "Unknown job: {$jobName}", 'name' => $jobName, 'result' => null, 'elapsed_ms' => 0]);
    exit(1);
}

$start = hrtime(true);
try {
    $result = ($jobs[$jobName])();
    $elapsed = (hrtime(true) - $start) / 1e6;
    echo json_encode([
        'name' => $jobName,
        'result' => $result,
        'error' => null,
        'elapsed_ms' => round($elapsed, 3),
    ]);
    exit(0);
} catch (\Throwable $e) {
    $elapsed = (hrtime(true) - $start) / 1e6;
    echo json_encode([
        'name' => $jobName,
        'result' => null,
        'error' => $e->getMessage(),
        'elapsed_ms' => round($elapsed, 3),
    ]);
    exit(1);
}
