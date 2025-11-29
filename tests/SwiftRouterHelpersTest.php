<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Tests;

use Omegaalfa\SwiftRouter\Router\RequestContext;
use Omegaalfa\SwiftRouter\Router\Response;
use Omegaalfa\SwiftRouter\Router\SwiftRouter;
use PHPUnit\Framework\TestCase;

final class SwiftRouterHelpersTest extends TestCase
{
    public function testHttpHelperMethodsChainAndDispatch(): void
    {
        $router = new SwiftRouter();

        $post = $router->post('/post', fn (RequestContext $context, Response $response) => 'post');
        $put = $router->put('/put', fn (RequestContext $context, Response $response) => 'put');
        $delete = $router->delete('/delete', fn (RequestContext $context, Response $response) => 'delete');
        $patch = $router->patch('/patch', fn (RequestContext $context, Response $response) => 'patch');

        $this->assertSame($router, $post);
        $this->assertSame($router, $put);
        $this->assertSame($router, $delete);
        $this->assertSame($router, $patch);

        $this->assertSame('post', $router->dispatch('POST', '/post')->body);
        $this->assertSame('put', $router->dispatch('PUT', '/put')->body);
        $this->assertSame('delete', $router->dispatch('DELETE', '/delete')->body);
        $this->assertSame('patch', $router->dispatch('PATCH', '/patch')->body);
    }

    public function testRequestContextAccessors(): void
    {
        $context = new RequestContext('GET', '/resource', ['id' => '9'], ['role' => 'admin']);
        $this->assertSame('admin', $context->get('role'));
        $this->assertTrue($context->has('role'));
        $this->assertSame('admin', $context->get('role', 'fallback'));

        $context->set('debug', true);
        $this->assertTrue($context->get('debug'));

        $this->assertSame('fallback', $context->get('unknown', 'fallback'));
        $this->assertFalse($context->has('unknown'));
    }
}
