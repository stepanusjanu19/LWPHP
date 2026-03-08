<?php

namespace Kei\Lwphp\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kei\Lwphp\Base\Entity;

#[ORM\Entity]
#[ORM\Table(name: 'test_features')]
class TestFeature extends Entity
{
    // Define your properties here

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'created_at' => $this->getCreatedAt()->format('c'),
            'updated_at' => $this->getUpdatedAt()->format('c'),
        ];
    }
}
