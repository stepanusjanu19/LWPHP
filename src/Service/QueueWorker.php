<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Base\Worker;
use Psr\Log\LoggerInterface;

/**
 * QueueWorker — Concrete worker that processes LWPHP background jobs.
 *
 * Extends Base\Worker which provides:
 *   - run() loop with configurable sleep + max-jobs
 *   - SIGTERM / SIGINT graceful shutdown
 *   - onSuccess() / onFailure() hooks
 *   - Structured starting/stopping logs
 *
 * Usage:
 *   $worker = new QueueWorker($queue, $logger);
 *   $worker->run();           // run forever
 *   $worker->run(1);          // process one job then exit
 *   $worker->run(0, 1000);    // sleep 1s between polls
 */
class QueueWorker extends Worker
{
    public function __construct(
        private readonly JobQueue $queue,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    protected function processOne(): mixed
    {
        return $this->queue->processNext();
    }

    protected function onSuccess(mixed $result): void
    {
        // JobQueue already logs success internally; no double-logging needed.
    }

    protected function onShutdown(): void
    {
        $this->logger->info('QueueWorker shutdown complete', [
            'processed' => $this->getProcessed(),
            'failed' => $this->getFailed(),
            'uptime_s' => $this->getUptime(),
        ]);
    }
}
