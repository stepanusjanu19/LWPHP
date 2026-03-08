<?php

namespace Kei\Lwphp\Repository;

use Kei\Lwphp\Base\Repository;
use Kei\Lwphp\Entity\TestFeature;

class TestFeatureRepository extends Repository
{
    protected function entityClass(): string
    {
        return TestFeature::class;
    }

    // Add your custom queries here
}
