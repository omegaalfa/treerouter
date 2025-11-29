<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class TimerMiddleware implements MiddlewareInterface
{
    public function process(RequestContext $context, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($context);
        $duration = microtime(true) - $start;

        return $response->withHeader('X-Response-Time', sprintf('%.3fms', $duration * 1000));
    }
}