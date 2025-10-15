<?php

require __DIR__ . '/../vendor/autoload.php';

$requsestFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
$requst = \Nyholm\Psr7Server\ServerRequestCreator::fromGlobals(
   $requsestFactory, $requsestFactory, $requsestFactory, $requsestFactory
);

$app = new \Kei\Lwphp\Core\App();
$app->run();
