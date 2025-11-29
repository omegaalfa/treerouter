<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class JsonMiddleware implements MiddlewareInterface
{
    public function process(RequestContext $context, callable $next): Response
    {
        $response = $next($context);

        // Se body for array, converte para JSON
        if (is_array($response->body)) {
            return $response
                ->withBody(json_encode($response->body))
                ->withHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
