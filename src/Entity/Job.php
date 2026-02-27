<?php

namespace Kei\Lwphp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Kei\Lwphp\Base\Entity as BaseEntity;

/**
 * Job Entity — Background queue record.
 *
 * Extends Base\Entity (MappedSuperclass) for $id, $createdAt, $updatedAt.
 * Lifecycle: pending → processing → done | failed
 *
 * NOTE: $processedAt is job-specific (not in BaseEntity) since
 * $updatedAt serves the general "last touched" role.
 */
#[ORM\Entity]
#[ORM\Table(name: 'jobs')]
class Job extends BaseEntity
{
    /** Job handler key (e.g. 'primes', 'matrix') */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    /** JSON-encoded input payload */
    #[ORM\Column(type: Types::TEXT)]
    private string $payload = '{}';

    /** pending | processing | done | failed */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    /** Result JSON stored after successful processing */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $result = null;

    /** Processing time recorded by the worker (ms) */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $elapsedMs = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(string $name, array $payload = [])
    {
        parent::__construct(); // sets $createdAt / $updatedAt
        $this->name = $name;
        $this->payload = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $this->status = 'pending';
    }

    // ── Lifecycle transitions ─────────────────────────────────────────────────

    public function markProcessing(): void
    {
        $this->status = 'processing';
        $this->attempts++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markDone(mixed $result = null, float $elapsedMs = 0): void
    {
        $this->status = 'done';
        $this->result = $result !== null ? json_encode($result, JSON_UNESCAPED_UNICODE) : null;
        $this->elapsedMs = $elapsedMs;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $error): void
    {
        $this->status = 'failed';
        $this->error = $error;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getName(): string
    {
        return $this->name;
    }
    public function getPayload(): array
    {
        return json_decode($this->payload, true) ?: [];
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getAttempts(): int
    {
        return $this->attempts;
    }
    public function getError(): ?string
    {
        return $this->error;
    }
    public function getElapsedMs(): ?float
    {
        return $this->elapsedMs;
    }
    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }
    public function getResult(): mixed
    {
        return $this->result !== null ? json_decode($this->result, true) : null;
    }

    // ── Serialization ─────────────────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'payload' => $this->getPayload(),
            'status' => $this->status,
            'attempts' => $this->attempts,
            'elapsed_ms' => $this->elapsedMs,
            'error' => $this->error,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
            'processed_at' => $this->processedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
