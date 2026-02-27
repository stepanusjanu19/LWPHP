<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller as BaseController;
use Kei\Lwphp\Repository\JobRepository;
use Kei\Lwphp\Service\JobQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SchemaController — Dynamic resource schema API.
 *
 * Provides field definitions that the CMS UI uses to auto-generate
 * tables and forms for any registered resource.
 *
 * GET /schema          → list all registered resources
 * GET /schema/{name}   → field definitions for one resource
 * GET /workers/status  → background worker status + queue depth
 */
class SchemaController extends BaseController
{
    public function __construct(
        private readonly JobRepository $jobRepo,
    ) {
        parent::__construct();
    }

    // ── Schema registry ───────────────────────────────────────────────────────

    private const SCHEMAS = [
        'tasks' => [
            'label' => 'Tasks',
            'api' => '/tasks',
            'icon' => 'check-square',
            'lifecycle' => ['start', 'complete', 'cancel'],
            'sortable' => ['id', 'title', 'status', 'priority', 'created_at'],
            'filterable' => ['status'],
            'fields' => [
                ['name' => 'id', 'type' => 'int', 'label' => 'ID', 'readonly' => true, 'list' => true, 'form' => false],
                ['name' => 'title', 'type' => 'string', 'label' => 'Title', 'readonly' => false, 'list' => true, 'form' => true, 'required' => true, 'maxLength' => 255],
                ['name' => 'description', 'type' => 'text', 'label' => 'Description', 'readonly' => false, 'list' => false, 'form' => true, 'required' => false],
                [
                    'name' => 'status',
                    'type' => 'badge',
                    'label' => 'Status',
                    'readonly' => true,
                    'list' => true,
                    'form' => false,
                    'badges' => ['pending' => 'yellow', 'in_progress' => 'blue', 'done' => 'green', 'cancelled' => 'gray']
                ],
                ['name' => 'priority', 'type' => 'int', 'label' => 'Priority', 'readonly' => false, 'list' => true, 'form' => true, 'min' => 0, 'max' => 10],
                ['name' => 'due_at', 'type' => 'datetime', 'label' => 'Due At', 'readonly' => false, 'list' => true, 'form' => true, 'required' => false],
                ['name' => 'created_at', 'type' => 'datetime', 'label' => 'Created', 'readonly' => true, 'list' => true, 'form' => false],
            ],
        ],

        'jobs' => [
            'label' => 'Job Queue',
            'api' => '/jobs',
            'icon' => 'cpu',
            'lifecycle' => [],
            'sortable' => ['id', 'name', 'status', 'created_at'],
            'filterable' => ['status'],
            'fields' => [
                ['name' => 'id', 'type' => 'int', 'label' => 'ID', 'readonly' => true, 'list' => true, 'form' => false],
                ['name' => 'name', 'type' => 'enum', 'label' => 'Job Type', 'readonly' => false, 'list' => true, 'form' => true, 'options' => JobQueue::JOBS],
                [
                    'name' => 'status',
                    'type' => 'badge',
                    'label' => 'Status',
                    'readonly' => true,
                    'list' => true,
                    'form' => false,
                    'badges' => ['pending' => 'yellow', 'processing' => 'blue', 'done' => 'green', 'failed' => 'red']
                ],
                ['name' => 'attempts', 'type' => 'int', 'label' => 'Attempts', 'readonly' => true, 'list' => true, 'form' => false],
                ['name' => 'elapsed_ms', 'type' => 'float', 'label' => 'Elapsed (ms)', 'readonly' => true, 'list' => true, 'form' => false],
                ['name' => 'error', 'type' => 'text', 'label' => 'Error', 'readonly' => true, 'list' => false, 'form' => false],
                ['name' => 'created_at', 'type' => 'datetime', 'label' => 'Created', 'readonly' => true, 'list' => true, 'form' => false],
                ['name' => 'processed_at', 'type' => 'datetime', 'label' => 'Processed', 'readonly' => true, 'list' => true, 'form' => false],
            ],
        ],
    ];

    // ── Endpoints ─────────────────────────────────────────────────────────────

    // GET /schema
    public function index(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $resources = [];
        foreach (self::SCHEMAS as $name => $schema) {
            $resources[] = [
                'name' => $name,
                'label' => $schema['label'],
                'api' => $schema['api'],
                'icon' => $schema['icon'],
            ];
        }
        return $this->json(200, ['resources' => $resources]);
    }

    // GET /schema/{name}
    public function show(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $name = strtolower($vars['name'] ?? '');
        if (!isset(self::SCHEMAS[$name])) {
            return $this->notFound('Schema resource', null);
        }
        return $this->json(200, ['schema' => array_merge(['name' => $name], self::SCHEMAS[$name])]);
    }

    // GET /workers/status
    public function workerStatus(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $stats = $this->jobRepo->stats();

        // Try to detect if a worker process is running
        $workerPid = null;
        $isRunning = false;
        if (function_exists('shell_exec')) {
            $pids = shell_exec("pgrep -f 'bin/worker.php' 2>/dev/null") ?? '';
            $pids = array_filter(array_map('trim', explode("\n", $pids)));
            if (!empty($pids)) {
                $isRunning = true;
                $workerPid = (int) reset($pids);
            }
        }

        return $this->json(200, [
            'worker' => [
                'running' => $isRunning,
                'pid' => $workerPid,
                'command' => 'php bin/worker.php',
            ],
            'queue' => $stats,
        ]);
    }
}
