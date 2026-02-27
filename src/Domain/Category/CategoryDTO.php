<?php

namespace Kei\Lwphp\Domain\Category;

/**
 * CategoryDTO
 * 
 * Data Transfer Object for Category creation and updates.
 * Keeps request data validation isolated from the framework and ORM.
 */
class CategoryDTO
{
    public function __construct(
        // public string $name,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            // name: $data['name'] ?? '',
        );
    }
    
    public function validate(): void
    {
        // if (empty($this->name)) {
        //     throw new \InvalidArgumentException('Name is required');
        // }
    }
}
