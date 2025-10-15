<?php

namespace Kei\Lwphp\Middleware;

class MiddlewareStack {
    private $middlewares = [];

    public function add($middleware) { $this->middlewares[] = $middleware; }

    public function handle($request, $handler){
        $next = $handler;
        foreach (array_reverse($this->middlewares) as $mw){
            $next = fn($req) => $mw->process($req, $next);
        }
        return $next($request);
    }


}