<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/helpers.php';

\Kei\Lwphp\Core\App::$bootTime = hrtime(true) / 1e6;  // record boot start in ms

$requestFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
$creator = new \Nyholm\Psr7Server\ServerRequestCreator(
   $requestFactory,
   $requestFactory,
   $requestFactory,
   $requestFactory
);
$request = $creator->fromGlobals();

$app = new \Kei\Lwphp\Core\App();
$app->run($request);
