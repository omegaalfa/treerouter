<?php

declare(strict_types=1);


namespace Omegaalfa\SwiftRouter\Router;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public mixed $body = null,
        public int   $statusCode = 200,
        public array $headers = []
    )
    {
    }

    /**
     * Define o corpo da resposta
     *
     * @param mixed $body
     * @return self
     */
    public function withBody(mixed $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * Define o status code
     *
     * @param int $code
     * @return self
     */
    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    /**
     * Adiciona um header
     *
     * @param string $name
     * @param string $value
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }
}
