<?php

namespace Kei\Lwphp\Domain\Task;

/**
 * TaskDTO — Immutable Data Transfer Object.
 *
 * Used for input validation and as a safe boundary between layers
 * (Controller → Service). Prevents the Service layer from receiving
 * raw user input arrays.
 */
final readonly class TaskDTO
{
    public function __construct(
        public string $title,
        public string $description = '',
        public int $priority = 0,
        public ?string $dueAt = null,
    ) {
    }

    /**
     * Construct from a raw request body array.
     * Validates required fields and casts types.
     *
     * @throws \InvalidArgumentException on missing/invalid fields
     */
    public static function fromArray(array $data): self
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new \InvalidArgumentException('Task title is required.');
        }
        if (mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Task title must be  ≤ 255 characters.');
        }

        $priority = (int) ($data['priority'] ?? 0);
        if ($priority < 0 || $priority > 10) {
            throw new \InvalidArgumentException('Priority must be between 0 and 10.');
        }

        $dueAt = null;
        if (!empty($data['due_at'])) {
            $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['due_at']);
            if ($dt === false) {
                throw new \InvalidArgumentException('due_at must be ISO 8601 format (e.g. 2026-12-31T00:00:00+07:00)');
            }
            $dueAt = $data['due_at'];
        }

        return new self(
            title: $title,
            description: trim($data['description'] ?? ''),
            priority: $priority,
            dueAt: $dueAt,
        );
    }
}
