<?php

declare(strict_types=1);


namespace Omegaalfa\TreeRouter\Router;


use Omegaalfa\TreeRouter\Interfaces\MiddlewareInterface;
use RuntimeException;

class TreeRouter
{
    protected TreeNode $root;

    /** @var array<string, array{handler:callable,middlewares:array<int,MiddlewareInterface>,params:array<string,string>}> */
    private array $staticMap = [];

    /** @var array<string, array{handler:callable,middlewares:array<int,MiddlewareInterface>,params:array<string,string>}> */
    private array $routeCache = [];

    private int $cacheLimit = 2048;

    /** @var array<MiddlewareInterface> Middlewares globais */
    private array $globalMiddlewares = [];

    public function __construct()
    {
        $this->root = new TreeNode();
    }


    /**
     * @param callable|MiddlewareInterface $middleware
     * @return $this
     */
    public function use(callable|MiddlewareInterface $middleware): self
    {
        if (is_callable($middleware) && !($middleware instanceof MiddlewareInterface)) {
            $middlewares = new class ($middleware) implements MiddlewareInterface {
                public function __construct(private $callable)
                {
                }

                public function process(RequestContext $context, callable $next): Response
                {
                    return ($this->callable)($context, $next);
                }
            };
        }

        $this->globalMiddlewares[] = $middlewares ?? $middleware;
        return $this;
    }


    /**
     * Adiciona uma rota
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc)
     * @param string $path Padrão da rota (/users/:id)
     * @param callable $handler Handler da rota
     * @param array<int, MiddlewareInterface> $middlewares Middlewares opcionais
     */
    public function addRoute(string $method, string $path, callable $handler, array $middlewares = []): void
    {
        // Normaliza o método
        $method = strtoupper($method);

        // Adiciona método ao caminho para evitar conflitos
        $fullPath = $method . '::' . $path;

        // normalize without excessive trimming
        $p = ltrim($fullPath, '/');

        // register static fast-path
        if (!str_contains($p, ':')) {
            $this->staticMap['/' . $p] = [
                'handler' => $handler,
                'middlewares' => $middlewares,
                'params' => [],
            ];
        }

        $currentNode = $this->root;
        $parts = $p === '' ? [] : explode('/', $p);

        foreach ($parts as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment[0] === ':') {
                $name = substr($segment, 1);
                if ($currentNode->paramChild === null) {
                    $currentNode->paramChild = new TreeNode();
                    $currentNode->paramName = $name;
                }
                $currentNode = $currentNode->paramChild;
                continue;
            }

            if (!isset($currentNode->children[$segment])) {
                $currentNode->children[$segment] = new TreeNode();
            }

            $currentNode = $currentNode->children[$segment];
        }

        $currentNode->isEndOfRoute = true;
        $currentNode->handler = $handler;
        $currentNode->middlewares = $middlewares;
    }

    /**
     * Busca e executa uma rota com middlewares
     *
     * @param string $method Método HTTP
     * @param string $path Caminho da requisição
     * @param array $initialData Dados iniciais para o contexto
     * @return Response Resposta processada
     * @throws \RuntimeException Se rota não for encontrada
     */
    public function dispatch(string $method, string $path, array $initialData = []): Response
    {
        $route = $this->findRoute($method, $path);

        if ($route === null) {
            throw new RuntimeException("Route not found: {$method} {$path}");
        }

        // Cria contexto da requisição
        $context = new RequestContext($method, $path, $route['params'], $initialData);

        // Combina middlewares: globais + específicos da rota
        $allMiddlewares = array_merge($this->globalMiddlewares, $route['middlewares']);

        // Cria a cadeia de execução (middlewares + handler)
        $handler = $route['handler'];


        // Constrói a cadeia de middlewares (de trás para frente)
        $chain = static function (RequestContext $ctx) use ($handler): Response {
            $result = $handler($ctx, new Response());

            // Se handler retornar Response, usa ela
            if ($result instanceof Response) {
                return $result;
            }

            // Caso contrário, cria Response com o resultado
            return new Response($result);
        };

        // Encadeia middlewares em ordem reversa
        foreach (array_reverse($allMiddlewares) as $middleware) {
            $chain = $this->wrapMiddleware($middleware, $chain);
        }

        // Executa a cadeia
        return $chain($context);
    }

    /**
     * Busca uma rota sem executar
     *
     * @param string $method Método HTTP
     * @param string $path Caminho da requisição
     * @return array{handler:callable,middlewares:array,params:array}|null
     */
    public function findRoute(string $method, string $path): ?array
    {
        // Normaliza o método
        $method = strtoupper($method);

        // Adiciona método ao caminho
        $fullPath = $method . '::' . $path;

        // normalize incoming path
        $normalized = '/' . preg_replace('#/+#', '/', ltrim($fullPath, '/'));

        // static fast map
        if (isset($this->staticMap[$normalized])) {
            return $this->staticMap[$normalized];
        }

        // cache
        if (isset($this->routeCache[$normalized])) {
            $entry = $this->routeCache[$normalized];
            unset($this->routeCache[$normalized]);
            $this->routeCache[$normalized] = $entry;
            return $entry;
        }

        $currentNode = $this->root;
        $p = ltrim($normalized, '/');
        $parts = $p === '' ? [] : explode('/', $p);
        $params = [];

        foreach ($parts as $segment) {
            if ($segment === '') {
                continue;
            }

            // 1 — exact child
            if (isset($currentNode->children[$segment])) {
                $currentNode = $currentNode->children[$segment];
                continue;
            }

            // 2 — param child
            if ($currentNode->paramChild !== null) {
                $params[$currentNode->paramName ?? 'param'] = $segment;
                $currentNode = $currentNode->paramChild;
                continue;
            }

            // 3 — no match
            return null;
        }

        if ($currentNode->isEndOfRoute && is_callable($currentNode->handler)) {
            $result = [
                'handler' => $currentNode->handler,
                'middlewares' => $currentNode->middlewares,
                'params' => $params,
            ];

            // store in cache
            $this->routeCache[$normalized] = $result;
            if (count($this->routeCache) > $this->cacheLimit) {
                reset($this->routeCache);
                $k = key($this->routeCache);
                if ($k !== null) {
                    unset($this->routeCache[$k]);
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * Encapsula um middleware na cadeia
     *
     * @param $middleware
     * @param callable $next
     * @return callable
     */
    private function wrapMiddleware($middleware, callable $next): callable
    {
        return static function (RequestContext $context) use ($middleware, $next): Response {
            if ($middleware instanceof MiddlewareInterface) {
                return $middleware->process($context, $next);
            }

            // Suporta callable simples
            if (is_callable($middleware)) {
                return $middleware($context, $next);
            }

            throw new \RuntimeException('Invalid middleware type');
        };
    }

    /**
     * Retorna estatísticas do router
     */
    public function getStats(): array
    {
        return [
            'static_routes' => count($this->staticMap),
            'cached_routes' => count($this->routeCache),
            'cache_limit' => $this->cacheLimit,
        ];
    }

    /**
     * Limpa o cache de rotas
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    /**
     * Define o limite do cache
     */
    public function setCacheLimit(int $limit): void
    {
        $this->cacheLimit = $limit;
    }
}