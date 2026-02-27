<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller as BaseController;
use Kei\Lwphp\Domain\Task\TaskDTO;
use Kei\Lwphp\Service\TaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TaskController — HTTP adapter for Task CRUD + lifecycle.
 *
 * Extends Base\Controller which provides:
 *   json(), created(), notFound(), badRequest(), unprocessable()
 *   parseBody(), paginate(), paginationParams()
 */
class TaskController extends BaseController
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {
        parent::__construct();
    }

    // GET /tasks[?status=pending&page=1&limit=25]
    public function index(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $tasks = $this->taskService->list($status);

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $page = $this->paginate(
            array_map(fn($t) => $t->toArray(), $tasks),
            $page,
            $limit
        );

        return $this->json(200, array_merge($page, ['stats' => $this->taskService->stats()]));
    }

    // POST /tasks
    public function store(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $dto = TaskDTO::fromArray($this->parseBody($request));
            $task = $this->taskService->create($dto);
            return $this->created($task->toArray(), 'Task created.');
        } catch (\InvalidArgumentException $e) {
            return $this->unprocessable($e->getMessage());
        }
    }

    // GET /tasks/{id}
    public function show(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $task = $this->taskService->get((int) $vars['id']);
            return $this->json(200, ['data' => $task->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->notFound('Task', (int) $vars['id']);
        }
    }

    // PUT /tasks/{id}
    public function update(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $dto = TaskDTO::fromArray($this->parseBody($request));
            $task = $this->taskService->update((int) $vars['id'], $dto);
            return $this->json(200, ['data' => $task->toArray(), 'message' => 'Task updated.']);
        } catch (\InvalidArgumentException $e) {
            return $this->unprocessable($e->getMessage());
        } catch (\RuntimeException | \DomainException $e) {
            return $this->json($e->getCode() ?: 400, ['error' => $e->getMessage()]);
        }
    }

    // DELETE /tasks/{id}
    public function destroy(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $this->taskService->delete((int) $vars['id']);
            return $this->json(200, ['message' => 'Task deleted.']);
        } catch (\RuntimeException $e) {
            return $this->notFound('Task', (int) $vars['id']);
        }
    }

    // POST /tasks/{id}/start
    public function start(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $task = $this->taskService->start((int) $vars['id']);
            return $this->json(200, ['data' => $task->toArray(), 'message' => 'Task started.']);
        } catch (\DomainException | \RuntimeException $e) {
            return $this->json($e->getCode() ?: 400, ['error' => $e->getMessage()]);
        }
    }

    // POST /tasks/{id}/complete
    public function complete(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $task = $this->taskService->complete((int) $vars['id']);
            return $this->json(200, ['data' => $task->toArray(), 'message' => 'Task completed.']);
        } catch (\DomainException | \RuntimeException $e) {
            return $this->json($e->getCode() ?: 400, ['error' => $e->getMessage()]);
        }
    }

    // POST /tasks/{id}/cancel
    public function cancel(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        try {
            $task = $this->taskService->cancel((int) $vars['id']);
            return $this->json(200, ['data' => $task->toArray(), 'message' => 'Task cancelled.']);
        } catch (\DomainException | \RuntimeException $e) {
            return $this->json($e->getCode() ?: 400, ['error' => $e->getMessage()]);
        }
    }
}
