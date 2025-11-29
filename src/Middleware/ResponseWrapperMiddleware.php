<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class ResponseWrapperMiddleware implements MiddlewareInterface
{
    public function process(RequestContext $context, callable $next): Response
    {
        $response = $next($context);

        // Encapsula resposta em formato padrão
        if ($response->statusCode === 200) {
            return $response->withBody([
                'success' => true,
                'data' => $response->body,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }

        return $response->withBody([
            'success' => false,
            'error' => $response->body,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}