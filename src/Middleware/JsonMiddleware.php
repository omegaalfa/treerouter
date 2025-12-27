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
            try {
                $json = json_encode(
                    $response->body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                return $response
                    ->withBody($json)
                    ->withHeader('Content-Type', 'application/json; charset=utf-8');
            } catch (\JsonException $e) {
                error_log("JSON encoding error: " . $e->getMessage());

                return (new Response())
                    ->withStatus(500)
                    ->withBody('Internal server error: Unable to encode JSON')
                    ->withHeader('Content-Type', 'text/plain');
            }
        }

        return $response;
    }
}
