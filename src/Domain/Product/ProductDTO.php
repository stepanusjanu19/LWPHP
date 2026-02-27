<?php

namespace Kei\Lwphp\Domain\Product;

/**
 * ProductDTO
 * 
 * Data Transfer Object for Product creation and updates.
 * Keeps request data validation isolated from the framework and ORM.
 */
class ProductDTO
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
