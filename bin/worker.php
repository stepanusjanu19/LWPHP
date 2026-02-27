#!/usr/bin/env php
<?php

/**
 * LWPHP Background Worker
 *
 * Thin shell that bootstraps the DI container and starts QueueWorker.
 * All loop logic lives in Base\Worker + QueueWorker (src/).
 *
 * Usage:
 *   php bin/worker.php              # run continuously
 *   php bin/worker.php --once       # process a single job then exit
 *   php bin/worker.php --sleep 1000 # custom poll interval (ms)
 *
 * Stop with Ctrl+C (SIGTERM / SIGINT handled gracefully by Base\Worker).
 */

declare(strict_types=1);

use Kei\Lwphp\Core\App;
use Kei\Lwphp\Service\JobQueue;
use Kei\Lwphp\Service\QueueWorker;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

// ── Parse options ──────────────────────────────────────────────────────────
$once = in_array('--once', $argv, true);
$sleepIdx = array_search('--sleep', $argv);
$sleepMs = ($sleepIdx !== false && isset($argv[$sleepIdx + 1])) ? (int) $argv[$sleepIdx + 1] : 500;

// ── Bootstrap framework ────────────────────────────────────────────────────
$app = new App();
$container = $app->getContainer();

$queue = $container->get(JobQueue::class);
$logger = $container->get(LoggerInterface::class);

// ── Start worker ───────────────────────────────────────────────────────────
$worker = new QueueWorker($queue, $logger);

fwrite(STDOUT, "\033[32m[LWPHP Worker]\033[0m Starting");
fwrite(STDOUT, $once ? " (--once mode)\n" : " (continuous, Ctrl+C to stop)\n");

$worker->run(maxJobs: $once ? 1 : 0, sleepMs: $sleepMs);

fwrite(STDOUT, sprintf(
    "\033[33m[LWPHP Worker]\033[0m Done. processed=%d failed=%d uptime=%.1fs\n",
    $worker->getProcessed(),
    $worker->getFailed(),
    $worker->getUptime()
));
