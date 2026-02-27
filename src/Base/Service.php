<?php

namespace Kei\Lwphp\Base;

use Psr\Log\LoggerInterface;

/**
 * Abstract Domain Service — SOLID base for all application services.
 *
 * Provides:
 *   - Injected PSR-3 logger
 *   - logSuccess() / logError() helpers (structured, no request body)
 *   - execute() wrapper for automatic try/catch + logging
 *
 * Logging policy (enforced here):
 *   INFO  → successful operations, key identifiers only
 *   ERROR → exceptions, class + message only (no stack traces, no body data)
 */
abstract class Service
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    // ── Logging Helpers ───────────────────────────────────────────────────────

    /**
     * Log a successful business operation.
     * Only include safe identifiers — never request bodies or PII.
     *
     * @param array<string, mixed> $context
     */
    final protected function logSuccess(string $operation, array $context = []): void
    {
        $this->logger->info($operation, $context);
    }

    /**
     * Log a thrown exception.
     * Logs class + message only — never full stack trace in non-debug.
     *
     * @param array<string, mixed> $context
     */
    final protected function logError(string $operation, \Throwable $e, array $context = []): void
    {
        $this->logger->error($operation, array_merge([
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'code' => $e->getCode(),
        ], $context));
    }

    /**
     * Execute a callable with automatic logging.
     *
     * On success → logSuccess($operation, $context)
     * On failure → logError($operation, $e, $context), then rethrows
     *
     * @param  array<string, mixed> $context
     * @throws \Throwable
     */
    final protected function execute(callable $fn, string $operation, array $context = []): mixed
    {
        try {
            $result = $fn();
            $this->logSuccess($operation, $context);
            return $result;
        } catch (\Throwable $e) {
            $this->logError($operation, $e, $context);
            throw $e;
        }
    }
}
