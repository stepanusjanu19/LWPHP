<?php

namespace Kei\Lwphp\Base;

/**
 * Abstract DDD Value Object.
 *
 * Value objects are:
 *   - Immutable (no setters)
 *   - Compared by value, not identity
 *   - Self-validating (assertValid() called in constructor)
 *
 * Usage:
 *   final class Email extends ValueObject {
 *       public function __construct(private readonly string $value) {
 *           parent::__construct();
 *       }
 *       protected function assertValid(): void {
 *           if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
 *               throw new \InvalidArgumentException("Invalid email: {$this->value}");
 *           }
 *       }
 *       public function getValue(): string { return $this->value; }
 *       public function equals(ValueObject $other): bool {
 *           return $other instanceof self && $this->value === $other->value;
 *       }
 *   }
 */
abstract class ValueObject
{
    final public function __construct()
    {
        $this->assertValid();
    }

    /**
     * Validates the value object's internal state.
     * Throw \InvalidArgumentException or \DomainException on failure.
     */
    abstract protected function assertValid(): void;

    /**
     * Two value objects are equal if all their properties are equal.
     * Subclasses SHOULD override this for efficient field-by-field comparison.
     */
    public function equals(self $other): bool
    {
        if (static::class !== get_class($other)) {
            return false;
        }
        return $this->toArray() === $other->toArray();
    }

    /**
     * Serialize the value object to a primitive representation.
     * Used for equality and debugging.
     */
    abstract public function toArray(): array;

    /** Prevent mutation by disallowing cloning with modifications. */
    public function __clone()
    {
        // Allow clone; subclasses should not add mutable state.
    }
}
