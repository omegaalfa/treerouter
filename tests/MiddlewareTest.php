<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Tests;

use Omegaalfa\SwiftRouter\Middleware\AuthMiddleware;
use Omegaalfa\SwiftRouter\Middleware\CorsMiddleware;
use Omegaalfa\SwiftRouter\Middleware\JsonMiddleware;
use Omegaalfa\SwiftRouter\Middleware\RateLimitMiddleware;
use Omegaalfa\SwiftRouter\Middleware\ResponseWrapperMiddleware;
use Omegaalfa\SwiftRouter\Middleware\TimerMiddleware;
use Omegaalfa\SwiftRouter\Middleware\ValidationMiddleware;
use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MiddlewareTest extends TestCase
{
    public function testAuthMiddlewareBlocksRequestsWithoutToken(): void
    {
        $middleware = new AuthMiddleware();
        $context = new RequestContext('GET', '/profile');
        $called = false;

        $next = function (RequestContext $context) use (&$called): Response {
            $called = true;
            return new Response('ok');
        };

        ob_start();
        $response = $middleware->process($context, $next);
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'Unauthorized'], $response->body);
    }

    public function testAuthMiddlewareAllowsValidToken(): void
    {
        $middleware = new AuthMiddleware();
        $context = new RequestContext('GET', '/profile', [], ['token' => 'secret-token']);
        $called = false;

        $next = function (RequestContext $context) use (&$called): Response {
            $called = true;
            return new Response('ok');
        };

        ob_start();
        $response = $middleware->process($context, $next);
        ob_end_clean();

        $this->assertTrue($called);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('ok', $response->body);
        $this->assertSame(123, $context->get('user_id'));
    }

    public function testAuthMiddlewareRejectsInvalidToken(): void
    {
        $middleware = new AuthMiddleware();
        $context = new RequestContext('GET', '/profile', [], ['token' => 'wrong-token']);
        $called = false;

        $next = function (RequestContext $context) use (&$called): Response {
            $called = true;
            return new Response('ok');
        };

        ob_start();
        $response = $middleware->process($context, $next);
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'Unauthorized'], $response->body);
    }

    public function testJsonMiddlewareEncodesArrays(): void
    {
        $middleware = new JsonMiddleware();
        $payload = ['message' => 'ok'];

        $response = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => new Response($payload)
        );

        $this->assertSame(json_encode($payload), $response->body);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testJsonMiddlewareLeavesNonArrayBody(): void
    {
        $middleware = new JsonMiddleware();

        $response = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => new Response('plain text')
        );

        $this->assertSame('plain text', $response->body);
        $this->assertArrayNotHasKey('Content-Type', $response->headers);
    }

    public function testCorsMiddlewareAddsHeaders(): void
    {
        $middleware = new CorsMiddleware();

        $response = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => new Response('ok')
        );

        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST, PUT, DELETE', $response->headers['Access-Control-Allow-Methods']);
    }

    public function testTimerMiddlewareAddsDurationHeader(): void
    {
        $middleware = new TimerMiddleware();

        $response = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => new Response('ok')
        );

        $this->assertArrayHasKey('X-Response-Time', $response->headers);
        $this->assertMatchesRegularExpression('#^\d+\.\d{3}ms$#', $response->headers['X-Response-Time']);
    }

    public function testValidationMiddlewareRejectsMissingRequired(): void
    {
        $middleware = new ValidationMiddleware(['email' => 'required']);

        $response = $middleware->process(
            new RequestContext('POST', '/users', ['email' => '']),
            static fn (RequestContext $context) => new Response('ok')
        );

        $this->assertSame(400, $response->statusCode);
        $this->assertSame(['error' => "Parameter 'email' is required"], $response->body);
    }

    public function testValidationMiddlewareRejectsNumericViolation(): void
    {
        $middleware = new ValidationMiddleware(['age' => 'numeric']);

        $response = $middleware->process(
            new RequestContext('POST', '/users', ['age' => 'abc']),
            static fn (RequestContext $context) => new Response('ok')
        );

        $this->assertSame(400, $response->statusCode);
        $this->assertSame(['error' => "Parameter 'age' must be numeric"], $response->body);
    }

    public function testValidationMiddlewareAllowsValidParameters(): void
    {
        $middleware = new ValidationMiddleware(['id' => 'numeric']);
        $called = false;

        $next = function (RequestContext $context) use (&$called): Response {
            $called = true;
            return new Response('ok');
        };

        $response = $middleware->process(
            new RequestContext('POST', '/users', ['id' => '123']),
            $next
        );

        $this->assertTrue($called);
        $this->assertSame('ok', $response->body);
    }

    public function testValidationMiddlewareAcceptsRequiredParameter(): void
    {
        $middleware = new ValidationMiddleware(['name' => 'required']);
        $called = false;

        $next = function (RequestContext $context) use (&$called): Response {
            $called = true;
            return new Response('ok');
        };

        $response = $middleware->process(
            new RequestContext('POST', '/users', ['name' => 'value']),
            $next
        );

        $this->assertTrue($called);
        $this->assertSame('ok', $response->body);
    }

    public function testRateLimitMiddlewareBlocksAfterMaxRequests(): void
    {
        $middleware = new RateLimitMiddleware();
        $context = new RequestContext('GET', '/limit', [], ['ip' => '10.0.0.1']);

        $next = static fn (RequestContext $context) => new Response('ok');

        ob_start();
        for ($i = 0; $i < 3; $i++) {
            $response = $middleware->process($context, $next);
            $this->assertSame('ok', $response->body);
        }
        $blocked = $middleware->process($context, $next);
        ob_end_clean();

        $this->assertSame(429, $blocked->statusCode);
        $this->assertSame(['error' => 'Too many requests'], $blocked->body);
        $this->assertSame('10', $blocked->headers['Retry-After']);
    }

    public function testRateLimitMiddlewarePurgesExpiredRequests(): void
    {
        $middleware = new RateLimitMiddleware();
        $reflection = new ReflectionClass($middleware);

        $requestsProperty = $reflection->getProperty('requests');
        $requestsProperty->setAccessible(true);

        $windowProperty = $reflection->getProperty('window');
        $windowProperty->setAccessible(true);
        $windowProperty->setValue($middleware, 3);

        $oldTimestamp = time() - 3;
        $requestsProperty->setValue($middleware, [
            '10.0.0.1' => [$oldTimestamp, $oldTimestamp, $oldTimestamp]
        ]);

        $context = new RequestContext('GET', '/limit', [], ['ip' => '10.0.0.1']);
        $next = static fn () => new Response('ok');

        ob_start();
        $response = $middleware->process($context, $next);
        ob_end_clean();

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('ok', $response->body);
    }

    public function testResponseWrapperMiddlewareWrapsSuccessAndFailure(): void
    {
        $middleware = new ResponseWrapperMiddleware();

        $success = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => new Response('value')
        );

        $this->assertTrue($success->body['success']);
        $this->assertSame('value', $success->body['data']);
        $this->assertMatchesRegularExpression('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $success->body['timestamp']);

        $failureResponse = $middleware->process(
            new RequestContext('GET', '/'),
            static fn (RequestContext $context) => (new Response('fail'))->withStatus(500)
        );

        $this->assertFalse($failureResponse->body['success']);
        $this->assertSame('fail', $failureResponse->body['error']);
        $this->assertMatchesRegularExpression('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $failureResponse->body['timestamp']);
    }
}
