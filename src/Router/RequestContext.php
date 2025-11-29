<?php

declare(strict_types=1);


namespace Omegaalfa\SwiftRouter\Router;

class RequestContext
{
    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $params
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $method,
        public string $path,
        public array  $params = [],
        public array  $data = []
    )
    {
    }

    /**
     * Define um valor no contexto
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Obtém um valor do contexto
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Verifica se chave existe
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}