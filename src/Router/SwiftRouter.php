<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Router;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;

class SwiftRouter extends TreeRouter
{
    /**
     * Métodos de conveniência HTTP
     */
    /**
     * @param array<int, MiddlewareInterface|\Psr\Http\Server\MiddlewareInterface> $middlewares
     */
    public function get(string $path, callable $handler, array $middlewares = []): self
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
        return $this;
    }

    /**
     * @param array<int, MiddlewareInterface|\Psr\Http\Server\MiddlewareInterface> $middlewares
     */
    public function post(string $path, callable $handler, array $middlewares = []): self
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
        return $this;
    }

    /**
     * @param array<int, MiddlewareInterface|\Psr\Http\Server\MiddlewareInterface> $middlewares
     */
    public function put(string $path, callable $handler, array $middlewares = []): self
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
        return $this;
    }

    /**
     * @param array<int, MiddlewareInterface|\Psr\Http\Server\MiddlewareInterface> $middlewares
     */
    public function delete(string $path, callable $handler, array $middlewares = []): self
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
        return $this;
    }

    /**
     * @param array<int, MiddlewareInterface|\Psr\Http\Server\MiddlewareInterface> $middlewares
     */
    public function patch(string $path, callable $handler, array $middlewares = []): self
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
        return $this;
    }

}
