<?php

declare(strict_types=1);

namespace Omegaalfa\SwiftRouter\Router;

use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface as OmegaMiddlewareInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;

class TreeNode
{
    /** @var array<string, TreeNode> */
    public array $children = [];

    /** @var array<int, OmegaMiddlewareInterface|PsrMiddlewareInterface> */
    public array $middlewares = [];

    /**
     * @var TreeNode|null
     */
    public ?TreeNode $paramChild = null;

    /**
     * @var string|null
     */
    public ?string $paramName = null;

    /** @var bool */
    public bool $isEndOfRoute = false;

    /** @var callable|null */
    public $handler = null;

    /**
     * @var string|null
     */
    public ?string $routeName = null;

    /**
     * @var array<string, string>
     */
    public array $constraints = [];
}
