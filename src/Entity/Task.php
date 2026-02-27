<?php

namespace Kei\Lwphp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Kei\Lwphp\Base\Entity as BaseEntity;

/**
 * Task Entity — DDD aggregate root for the task lifecycle.
 *
 * Extends Base\Entity (MappedSuperclass) which provides:
 *   $id, $createdAt, $updatedAt, equals(), toArray(), domain events
 *
 * Domain lifecycle: pending → in_progress → done
 *                                         → cancelled
 */
#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
class Task extends BaseEntity
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** pending | in_progress | done | cancelled */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: Types::INTEGER)]
    private int $priority = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    public function __construct(
        string $title,
        string $description = '',
        int $priority = 0,
        ?\DateTimeImmutable $dueAt = null,
    ) {
        parent::__construct(); // sets $createdAt / $updatedAt
        $this->title = $title;
        $this->description = $description !== '' ? $description : null;
        $this->priority = $priority;
        $this->dueAt = $dueAt;
        $this->status = 'pending';
    }

    // ── Domain behaviour ──────────────────────────────────────────────────────

    public function start(): void
    {
        if ($this->status !== 'pending') {
            throw new \DomainException("Cannot start task #{$this->id}: status is '{$this->status}'.");
        }
        $this->status = 'in_progress';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        if ($this->status !== 'in_progress') {
            throw new \DomainException("Cannot complete task #{$this->id}: status is '{$this->status}'.");
        }
        $this->status = 'done';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (in_array($this->status, ['done', 'cancelled'], true)) {
            throw new \DomainException("Cannot cancel task #{$this->id}: already '{$this->status}'.");
        }
        $this->status = 'cancelled';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $title, string $description, int $priority): void
    {
        if (in_array($this->status, ['done', 'cancelled'], true)) {
            throw new \DomainException("Cannot update task #{$this->id}: in final status '{$this->status}'.");
        }
        $this->title = $title;
        $this->description = $description !== '' ? $description : null;
        $this->priority = $priority;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getTitle(): string
    {
        return $this->title;
    }
    public function getDescription(): string
    {
        return $this->description ?? '';
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getPriority(): int
    {
        return $this->priority;
    }
    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    // ── Serialization ─────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->getDescription(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'priority' => $this->priority,
            'due_at' => $this->dueAt?->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
