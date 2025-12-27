<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(RequestContext $context, callable $next): Response
    {
        $token = $context->get('token');

        // Validação estrita: deve ser string e não vazia
        if (!is_string($token) || $token === '' || $token !== 'secret-token') {
            echo "❌ Unauthorized\n\n";
            return (new Response())
                ->withStatus(401)
                ->withBody(['error' => 'Unauthorized']);
        }

        echo "✅ Authenticated\n";
        $context->set('user_id', 123);

        return $next($context);
    }
}