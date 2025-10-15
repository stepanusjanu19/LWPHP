<?php

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Kei\Lwphp\Routing\Routes;

return [
    Dispatcher::class => DI\factory(function (){
        return \FastRoute\cachedDispatcher(function(RouteCollector $r){
            Routes::define($r);
        }, ['cacheFile' => __DIR__ . '/../cache/route.cache']);
    }),

    //DI Pattern Modules
    //declare middleware

    //declare controller
];
