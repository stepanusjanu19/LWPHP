<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Base\Service;
use Kei\Lwphp\Repository\TestFeatureRepository;
use Psr\Log\LoggerInterface;
use DI\Attribute\Inject;

class TestFeatureService extends Service
{
    public function __construct(
        private readonly TestFeatureRepository $repository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    // Add business logic here
}
