<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Middleware;

final class MicrotimeOverride
{
    /**
     * @var array<int, float>
     */
    private static array $values = [];

    public static function push(float $value): void
    {
        self::$values[] = $value;
    }

    public static function next(bool $getAsFloat = false): float
    {
        if (empty(self::$values)) {
            return \microtime($getAsFloat);
        }

        return array_shift(self::$values);
    }

    public static function reset(): void
    {
        self::$values = [];
    }
}

if (!function_exists(__NAMESPACE__ . '\\microtime')) {
    function microtime(bool $getAsFloat = false): float
    {
        return MicrotimeOverride::next($getAsFloat);
    }
}

namespace Omegaalfa\SwiftRouter\Tests;

use Omegaalfa\SwiftRouter\Middleware\MicrotimeOverride;
use Omegaalfa\SwiftRouter\Middleware\TimerMiddleware;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;
use PHPUnit\Framework\TestCase;

final class TimerMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        MicrotimeOverride::reset();
    }

    public function testTimerRecordsAccurateDuration(): void
    {
        MicrotimeOverride::push(1.0);
        MicrotimeOverride::push(1.1);

        $middleware = new TimerMiddleware();
        $response = $middleware->process(
            new RequestContext('GET', '/'),
            static fn () => new Response('ok')
        );

        $this->assertSame('ok', $response->body);
        $this->assertSame('100.000ms', $response->headers['X-Response-Time']);
    }
}
