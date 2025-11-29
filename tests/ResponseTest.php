<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Tests;

use Omegaalfa\SwiftRouter\Router\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testWithBodyReturnsClone(): void
    {
        $original = new Response('body', 200, ['x' => 'y']);
        $updated = $original->withBody('other');

        $this->assertNotSame($original, $updated);
        $this->assertSame('body', $original->body);
        $this->assertSame('other', $updated->body);
    }

    public function testWithStatusReturnsClone(): void
    {
        $original = new Response('body', 200);
        $updated = $original->withStatus(404);

        $this->assertNotSame($original, $updated);
        $this->assertSame(200, $original->statusCode);
        $this->assertSame(404, $updated->statusCode);
    }

    public function testWithHeaderReturnsClone(): void
    {
        $original = new Response('body', 200, ['foo' => 'bar']);
        $updated = $original->withHeader('foo', 'baz');

        $this->assertNotSame($original, $updated);
        $this->assertSame('bar', $original->headers['foo']);
        $this->assertSame('baz', $updated->headers['foo']);
    }
}
