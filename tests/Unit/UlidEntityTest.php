<?php

use Kei\Lwphp\Base\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DummyUlidEntity extends Entity
{
    #[ORM\Column(type: 'string')]
    public string $name = 'test';

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'created_at' => $this->getCreatedAt()->format('c'),
            'updated_at' => $this->getUpdatedAt()->format('c'),
        ];
    }
}

test('Entity automatically generates a 26-character ULID upon instantiation', function () {
    $entity = new DummyUlidEntity();

    $id = $entity->getId();

    expect($id)->toBeString();
    expect(strlen($id))->toBe(26);
});

test('Two different entities generate unique ULIDs', function () {
    $entity1 = new DummyUlidEntity();
    $entity2 = new DummyUlidEntity();

    expect($entity1->getId())->not->toEqual($entity2->getId());
});

test('Equals method works correctly with ULID strings', function () {
    $entity = new DummyUlidEntity();
    // Simulate finding the exact same record
    $entityClone = clone $entity;

    expect($entity->equals($entityClone))->toBeTrue();
});
