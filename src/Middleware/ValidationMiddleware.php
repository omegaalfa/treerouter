<?php

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(private array $rules) {}

    public function process(RequestContext $context, callable $next): Response
    {
        foreach ($this->rules as $param => $rule) {
            $value = $context->params[$param] ?? null;

            if ($rule === 'required' && empty($value)) {
                return (new Response())
                    ->withStatus(400)
                    ->withBody(['error' => "Parameter '{$param}' is required"]);
            }

            if ($rule === 'numeric' && !is_numeric($value)) {
                return (new Response())
                    ->withStatus(400)
                    ->withBody(['error' => "Parameter '{$param}' must be numeric"]);
            }
        }

        return $next($context);
    }
}