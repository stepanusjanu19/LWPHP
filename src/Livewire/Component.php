<?php

namespace Kei\Lwphp\Livewire;

abstract class Component
{
    public string $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(10));
    }

    abstract public function render(): string;

    /**
     * Extracts public properties to pass to Twig
     */
    public function dehydrate(): array
    {
        $props = [];
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() !== 'id') {
                $props[$property->getName()] = $property->getValue($this);
            }
        }
        return $props;
    }

    /**
     * Re-hydrates state into public properties
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }
}