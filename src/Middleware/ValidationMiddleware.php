<?php


declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Middleware;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;

class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, string> $rules
     */
    public function __construct(private array $rules) {}

    public function process(RequestContext $context, callable $next): Response
    {
        foreach ($this->rules as $param => $rule) {
            // Validação estrita: verifica se existe E não é string vazia
            $exists = isset($context->params[$param]);
            $value = $exists ? $context->params[$param] : null;

            if ($rule === 'required' && (!$exists || $value === null || $value === '')) {
                return (new Response())
                    ->withStatus(400)
                    ->withBody(['error' => "Parameter '{$param}' is required"]);
            }

            if ($rule === 'numeric' && $value !== null && !is_numeric($value)) {
                return (new Response())
                    ->withStatus(400)
                    ->withBody(['error' => "Parameter '{$param}' must be numeric"]);
            }
        }

        return $next($context);
    }
}
