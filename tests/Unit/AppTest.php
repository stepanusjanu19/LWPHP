<?php

use Kei\Lwphp\Core\App;
use Psr\Container\ContainerInterface;
use Kei\Lwphp\Core\ConfigLoader;
use Doctrine\ORM\EntityManagerInterface;

test('app initializes properly and binds container', function () {
    $app = new App();
    expect($app)->toBeInstanceOf(App::class);

    $container = $app->getContainer();
    expect($container)->toBeInstanceOf(ContainerInterface::class);
});

test('config loader is bound and can resolve basic env values', function () {
    $app = new App();
    $config = $app->getContainer()->get(ConfigLoader::class);

    expect($config)->toBeInstanceOf(ConfigLoader::class);
    // app.env should be local or production
    expect($config->get('app.env'))->not->toBeEmpty();
});

test('doctrine entity manager resolves sqlite connection properly', function () {
    $app = new App();
    $em = $app->getContainer()->get(EntityManagerInterface::class);

    expect($em)->toBeInstanceOf(EntityManagerInterface::class);
    $conn = $em->getConnection();

    // Default config typically has driver as pdo_sqlite
    $params = $conn->getParams();
    expect(isset($params['driver']))->toBeTrue();
    expect($params['driver'])->toBe('pdo_sqlite');
});
