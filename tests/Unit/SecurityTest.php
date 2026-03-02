<?php

use Kei\Lwphp\Security\RateLimiter;
use Kei\Lwphp\Core\App;
use Doctrine\ORM\EntityManagerInterface;
use Kei\Lwphp\Core\SchemaManager;

test('rate limiter permits requests within capacity and blocks overcapacity', function () {
    $app = new App();
    $em = $app->getContainer()->get(EntityManagerInterface::class);
    $conn = $em->getConnection();

    // Ensure the SQLite schema is fully updated and ready
    $schema = new SchemaManager($em);
    $schema->updateSchema();

    $ratelimit = new RateLimiter($conn, 60, 180, 20);

    $ip = '192.168.1.100';
    $maxSettings = 180;

    // Clear out any previous test data gracefully
    try {
        $conn->executeStatement("DELETE FROM rate_limits WHERE ip = ?", [$ip]);
    } catch (\Throwable $e) {
        // Table might not exist yet; RateLimiter will lazily create it
    }

    // Simulate X requests
    for ($i = 0; $i < ($maxSettings + 20); $i++) {
        expect($ratelimit->isAllowed($ip))->toBeTrue();
    }

    // 201st should be blocked
    expect($ratelimit->isAllowed($ip))->toBeFalse();

    // Retry should be > 0
    expect($ratelimit->retryAfterSeconds())->toBeGreaterThan(0);
});
