<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(RequestContext $context, callable $next): Response
    {
        $response = $next($context);

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
    }
}