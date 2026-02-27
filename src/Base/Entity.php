<?php

namespace Kei\Lwphp\Base;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Abstract DDD Entity — SOLID base for all Doctrine-mapped domain objects.
 *
 * All concrete entities MUST:
 *   - Add #[ORM\Entity] + #[ORM\Table(name:'...')] on the subclass
 *   - Implement toArray(): array
 *
 * Provides:
 *   - Auto-managed $id, $createdAt, $updatedAt
 *   - Identity comparison via equals()
 *   - Lightweight domain event recording
 *   - #[ORM\PreUpdate] hook to auto-bump $updatedAt
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class Entity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    /** @var list<object> Recorded domain events (cleared after dispatch) */
    private array $domainEvents = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ── Identity comparison (DDD) ─────────────────────────────────────────────

    /**
     * Two entities are equal when they have the same class AND the same id.
     */
    public function equals(self $other): bool
    {
        return $this->id !== null
            && $this->id === $other->id
            && static::class === get_class($other);
    }

    // ── Domain Events ────────────────────────────────────────────────────────

    final protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** Pull and clear all recorded events. */
    final public function pullEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // ── Doctrine lifecycle ───────────────────────────────────────────────────

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Serialization (enforced on subclasses) ───────────────────────────────

    abstract public function toArray(): array;
}
