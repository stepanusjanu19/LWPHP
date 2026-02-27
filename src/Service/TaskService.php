<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Base\Service as BaseService;
use Kei\Lwphp\Domain\Task\TaskDTO;
use Kei\Lwphp\Entity\Task;
use Kei\Lwphp\Repository\TaskRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * TaskService — Task aggregate application service.
 *
 * Extends Base\Service which provides:
 *   - Injected $logger
 *   - logSuccess() / logError() helpers
 *   - execute() with auto-logging
 */
class TaskService extends BaseService
{
    public function __construct(
        private readonly TaskRepositoryInterface $repo,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function create(TaskDTO $dto): Task
    {
        $dueAt = null;
        if ($dto->dueAt !== null) {
            $dueAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->dueAt) ?: null;
        }

        $task = new Task($dto->title, $dto->description, $dto->priority, $dueAt);
        $this->repo->save($task);
        $this->logSuccess('task.created', ['id' => $task->getId(), 'title' => $task->getTitle()]);
        return $task;
    }

    /** @return Task[] */
    public function list(?string $status = null): array
    {
        return $status !== null
            ? $this->repo->findByStatus($status)
            : $this->repo->findAll();
    }

    public function get(int $id): Task
    {
        $task = $this->repo->findById($id);
        if (!$task instanceof Task) {
            $this->logError('task.not_found', new \RuntimeException("Task #{$id} not found."), ['id' => $id]);
            throw new \RuntimeException("Task #{$id} not found.", 404);
        }
        return $task;
    }

    public function update(int $id, TaskDTO $dto): Task
    {
        $task = $this->get($id);
        $task->update($dto->title, $dto->description, $dto->priority);
        $this->repo->save($task);
        $this->logSuccess('task.updated', ['id' => $id]);
        return $task;
    }

    public function delete(int $id): void
    {
        $this->get($id); // throws 404 if not found
        $this->repo->delete($id);
        $this->logSuccess('task.deleted', ['id' => $id]);
    }

    // ── Lifecycle transitions ─────────────────────────────────────────────────

    public function start(int $id): Task
    {
        $task = $this->get($id);
        $task->start();
        $this->repo->save($task);
        $this->logSuccess('task.started', ['id' => $id, 'status' => $task->getStatus()]);
        return $task;
    }

    public function complete(int $id): Task
    {
        $task = $this->get($id);
        $task->complete();
        $this->repo->save($task);
        $this->logSuccess('task.completed', ['id' => $id]);
        return $task;
    }

    public function cancel(int $id): Task
    {
        $task = $this->get($id);
        $task->cancel();
        $this->repo->save($task);
        $this->logSuccess('task.cancelled', ['id' => $id]);
        return $task;
    }

    // ── Aggregate ─────────────────────────────────────────────────────────────

    public function stats(): array
    {
        $all = $this->repo->findAll();
        $byStatus = [];
        foreach ($all as $t) {
            $s = $t instanceof Task ? $t->getStatus() : (string) $t->getStatus();
            $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
        }
        return ['total' => count($all), 'by_status' => $byStatus];
    }
}
