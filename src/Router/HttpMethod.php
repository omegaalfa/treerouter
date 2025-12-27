<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Router;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';

    /**
     * Valida e normaliza um método HTTP
     * Usa tryFrom nativo e lança exceção personalizada se inválido
     *
     * @param string $method
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $method): self
    {
        $normalized = strtoupper($method);

        // Tenta usar o método nativo tryFrom
        $result = self::tryFrom($normalized);

        if ($result === null) {
            throw new \InvalidArgumentException(
                "Invalid HTTP method: {$method}. Allowed methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD"
            );
        }

        return $result;
    }

    /**
     * Verifica se um método é válido
     *
     * @param string $method
     * @return bool
     */
    public static function isValid(string $method): bool
    {
        $normalized = strtoupper($method);
        return self::tryFrom($normalized) !== null;
    }

    /**
     * Retorna todos os métodos válidos como array de strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $method) => $method->value, self::cases());
    }
}
