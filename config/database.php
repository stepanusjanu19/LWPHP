<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

return [
    EntityManager::class => DI\factory(function (){
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../src/Entity'],
            isDevMode: true
        );

        $connection = [
            'drive' => '',
            'host' => '',
            'dbname' => '',
            'user' => '',
            'password' => '',
            'port' => ''
        ];
        return EntityManager::create($connection, $config);
    }),

];
