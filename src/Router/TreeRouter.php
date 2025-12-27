<?php

declare(strict_types=1);


namespace Omegaalfa\SwiftRouter\Router;


use InvalidArgumentException;
use Omegaalfa\SwiftRouter\Interfaces\MiddlewareInterface;
use RuntimeException;
use Throwable;

class TreeRouter
{
    /**
     * @var TreeNode
     */
    protected TreeNode $root;

    /** @var array<string, array{handler:callable,middlewares:array<int,MiddlewareInterface>,params:array<string,string>}> */
    private array $staticMap = [];

    /** @var array<string, array{handler:callable,middlewares:array<int,MiddlewareInterface>,params:array<string,string>}> */
    private array $routeCache = [];

    /** @var string Prefixo atual do grupo */
    private string $groupPrefix = '';

    /** @var array<MiddlewareInterface> Middlewares do grupo atual */
    private array $groupMiddlewares = [];

    /**
     * @var int
     */
    private int $cacheLimit = 2048;

    /** @var array<MiddlewareInterface> Middlewares globais */
    private array $globalMiddlewares = [];

    /** @var int Tamanho máximo de parâmetro de rota */
    private int $maxParamLength = 255;

    /** @var array<string> Namespaces permitidos para controllers */
    private array $allowedNamespaces = [];

    public function __construct(array $allowedNamespaces = [])
    {
        $this->root = new TreeNode();
        $this->allowedNamespaces = $allowedNamespaces;
    }


    /**
     * @param callable|MiddlewareInterface $middleware
     * @return $this
     */
    public function use(callable|MiddlewareInterface $middleware): self
    {
        if ($middleware instanceof MiddlewareInterface) {
            $this->globalMiddlewares[] = $middleware;
            return $this;
        }

        $this->globalMiddlewares[] = new class ($middleware) implements MiddlewareInterface {
            /**
             * @var callable(RequestContext, callable): Response
             */
            private $callable;

            public function __construct(callable $callable)
            {
                $this->callable = $callable;
            }

            public function process(RequestContext $context, callable $next): Response
            {
                return ($this->callable)($context, $next);
            }
        };

        return $this;
    }

    /**
     * Cria um grupo de rotas com prefixo e middlewares compartilhados
     *
     * @param string $prefix Prefixo do grupo (/api, /admin, etc)
     * @param callable $callback Callback que recebe o router
     * @param array<int, MiddlewareInterface> $middlewares Middlewares aplicados a todas as rotas do grupo
     *
     * @example
     * $router->group('/api/v1', function($router) {
     *     $router->get('/users', $handler);      // /api/v1/users
     *     $router->post('/users', $handler);     // /api/v1/users
     * }, [new AuthMiddleware()]);
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        // Salva estado anterior
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        // Aplica novo estado
        $this->groupPrefix = $this->buildGroupPath($prefix);
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        // Executa callback com o router
        $callback($this);

        // Restaura estado anterior (permite grupos aninhados)
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Constrói o caminho completo considerando o prefixo do grupo
     *
     * @param string $path Caminho da rota
     * @return string Caminho completo com prefixo
     */
    private function buildGroupPath(string $path): string
    {
        $prefix = trim($this->groupPrefix, '/');
        $path = trim($path, '/');

        if ($prefix === '') {
            return '/' . $path;
        }

        if ($path === '') {
            return '/' . $prefix;
        }

        return '/' . $prefix . '/' . $path;
    }

    /**
     * Adiciona uma rota
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc)
     * @param string $path Padrão da rota (/users/:id)
     * @param callable|array<string, string> $handler Handler da rota
     * @param array<int, MiddlewareInterface> $middlewares Middlewares opcionais
     */
    public function addRoute(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        // Valida e normaliza o método
        $method = HttpMethod::fromString($method)->value;

        // Valida e normaliza o handler
        $handler = $this->validateAndNormalizeHandler($handler);

        // Aplica prefixo do grupo se existir e normaliza o path
        $fullPath = $this->normalizePath(
            $this->groupPrefix ? $this->buildGroupPath($path) : $path
        );

        // Combina middlewares do grupo com os da rota
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        // Adiciona método ao caminho para evitar conflitos
        $routePath = $method . '::' . $fullPath;

        // normalize without excessive trimming
        $p = ltrim($routePath, '/');

        // register static fast-path (only quando o caminho não tem parâmetros)
        if (!str_contains($fullPath, ':')) {
            $this->staticMap['/' . $p] = [
                'handler' => $handler,
                'middlewares' => $allMiddlewares,
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
        $currentNode->middlewares = $allMiddlewares;
    }

    /**
     * Normaliza e valida o path da rota
     *
     * @param string $path
     * @return string
     * @throws InvalidArgumentException
     */
    private function normalizePath(string $path): string
    {
        // Remove slashes duplicados
        $path = preg_replace('#/+#', '/', $path);

        // Remove trailing slash (exceto root)
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        // Detecta path traversal
        $decoded = urldecode($path);
        if (str_contains($decoded, '..') || str_contains($decoded, '\\')) {
            throw new InvalidArgumentException('Path traversal detected in route: ' . $path);
        }

        return $path;
    }

    /**
     * Valida e normaliza o handler
     *
     * @param callable|array<string, string> $handler
     * @return callable
     * @throws InvalidArgumentException
     */
    private function validateAndNormalizeHandler(callable|array $handler): callable
    {
        if (is_array($handler)) {
            return $this->normalizeArrayHandler($handler);
        }

        if (is_string($handler)) {
            // Bloquear funções perigosas
            $dangerous = [
                'system', 'exec', 'passthru', 'shell_exec', 'eval',
                'assert', 'create_function', 'call_user_func', 'call_user_func_array'
            ];

            if (in_array(strtolower($handler), $dangerous, true)) {
                throw new InvalidArgumentException("Dangerous callable not allowed: {$handler}");
            }
        }

        if (!is_callable($handler)) {
            throw new InvalidArgumentException('Handler must be callable');
        }

        return $handler;
    }

    /**
     * @param array<string, string> $handler
     * @return callable
     */
    protected function normalizeArrayHandler(array $handler): callable
    {
        if (count($handler) !== 2) {
            throw new InvalidArgumentException(
                'Array handler must be [ControllerClass, method]'
            );
        }

        [$controller, $method] = $handler;

        if (!is_string($controller) || !is_string($method)) {
            throw new InvalidArgumentException(
                'Invalid array handler format'
            );
        }

        // Valida que a classe existe
        if (!class_exists($controller)) {
            throw new InvalidArgumentException(
                "Controller class does not exist: {$controller}"
            );
        }

        // Valida namespace se configurado
        if (!empty($this->allowedNamespaces)) {
            $isAllowed = false;
            foreach ($this->allowedNamespaces as $namespace) {
                if (str_starts_with($controller, $namespace)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                throw new InvalidArgumentException(
                    "Controller must be in allowed namespace: {$controller}. " .
                    "Allowed: " . implode(', ', $this->allowedNamespaces)
                );
            }
        }

        return static function (...$args) use ($controller, $method) {
            $instance = new $controller();

            if (!method_exists($instance, $method)) {
                throw new RuntimeException(
                    "Method {$method} does not exist on {$controller}"
                );
            }

            return $instance->$method(...$args);
        };
    }

    /**
     * Busca e executa uma rota com middlewares
     *
     * @param string $method Método HTTP
     * @param string $path Caminho da requisição
     * @param array<string, mixed> $initialData Dados iniciais para o contexto
     * @return Response Resposta processada
     * @throws RuntimeException Se rota não for encontrada
     * @throws InvalidArgumentException Se método HTTP for inválido
     */
    public function dispatch(string $method, string $path, array $initialData = []): Response
    {
        // Valida o método HTTP
        $validatedMethod = HttpMethod::fromString($method);
        $originalMethod = $validatedMethod->value;

        // Tratar HEAD como GET
        $searchMethod = $originalMethod === 'HEAD' ? 'GET' : $originalMethod;

        $route = $this->findRoute($searchMethod, $path);

        // Se não encontrou e é OPTIONS, retorna lista de métodos permitidos
        if ($route === null && $originalMethod === 'OPTIONS') {
            return $this->handleOptions($path);
        }

        if ($route === null) {
            throw new RuntimeException("Route not found: {$originalMethod} {$path}");
        }

        // Cria contexto da requisição
        $context = new RequestContext($originalMethod, $path, $route['params'], $initialData);

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
        $response = $chain($context);

        // Se era HEAD, remove o body
        if ($originalMethod === 'HEAD') {
            $response = $response->withBody(null);
        }

        return $response;
    }

    /**
     * Trata requisições OPTIONS retornando métodos permitidos
     *
     * @param string $path
     * @return Response
     */
    private function handleOptions(string $path): Response
    {
        $methods = [];

        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            if ($this->findRoute($method, $path) !== null) {
                $methods[] = $method;
            }
        }

        if (in_array('GET', $methods, true)) {
            $methods[] = 'HEAD';
        }

        $methods[] = 'OPTIONS';

        return (new Response())
            ->withHeader('Allow', implode(', ', array_unique($methods)))
            ->withStatus(204);
    }

    /**
     * Busca uma rota sem executar
     *
     * @param string $method Método HTTP
     * @param string $path Caminho da requisição
     * @return array{handler:callable,middlewares:array<int,MiddlewareInterface>,params:array<string,string>}|null
     */
    public function findRoute(string $method, string $path): ?array
    {
        // Valida e normaliza o método
        $method = HttpMethod::fromString($method)->value;

        // Normaliza o path usando o mesmo método de addRoute
        $normalizedPath = $this->normalizePath($path);

        // Adiciona método ao caminho
        $fullPath = $method . '::' . $normalizedPath;

        // normalize incoming path
        $normalized = '/' . ltrim($fullPath, '/');

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
                // Valida tamanho do parâmetro para prevenir DoS
                if (strlen($segment) > $this->maxParamLength) {
                    throw new RuntimeException(
                        "Route parameter exceeds maximum length of {$this->maxParamLength} characters"
                    );
                }

                $params[$currentNode->paramName ?? 'param'] = $segment;
                $currentNode = $currentNode->paramChild;
                continue;
            }

            // 3 — no match
            return null;
        }

        if ($currentNode->isEndOfRoute && $currentNode->handler !== null) {
            $result = [
                'handler' => $currentNode->handler,
                'middlewares' => $currentNode->middlewares,
                'params' => $params,
            ];

            // store in cache
            $this->routeCache[$normalized] = $result;
            if (count($this->routeCache) > $this->cacheLimit) {
                reset($this->routeCache);
                /** @var string|null $k */
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
     * @param callable|MiddlewareInterface $middleware
     * @param callable $next
     * @return callable
     */
    private function wrapMiddleware(callable|MiddlewareInterface $middleware, callable $next): callable
    {
        return static function (RequestContext $context) use ($middleware, $next): Response {
            try {
                if ($middleware instanceof MiddlewareInterface) {
                    return $middleware->process($context, $next);
                }

                $result = $middleware($context, $next);
                if ($result instanceof Response) {
                    return $result;
                }

                return new Response($result);
            } catch (Throwable $e) {
                // Retorna erro 500
                // Nota: Em produção, considere usar um logger para registrar erros
                return (new Response())
                    ->withStatus(500)
                    ->withBody(['error' => 'Internal server error', 'message' => $e->getMessage()]);
            }
        };
    }

    /**
     * Retorna estatísticas do router
     *
     * @return array{static_routes:int,cached_routes:int,cache_limit:int,global_middlewares:int}
     */
    public function getStats(): array
    {
        return [
            'static_routes' => count($this->staticMap),
            'cached_routes' => count($this->routeCache),
            'cache_limit' => $this->cacheLimit,
            'global_middlewares' => count($this->globalMiddlewares),
        ];
    }

    /**
     * Limpa o cache de rotas
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    /**
     * Define o limite do cache
     *
     * @param int $limit
     * @return void
     */
    public function setCacheLimit(int $limit): void
    {
        $this->cacheLimit = $limit;
    }

    /**
     * Define o tamanho máximo de parâmetro de rota
     *
     * @param int $length
     * @return void
     */
    public function setMaxParamLength(int $length): void
    {
        $this->maxParamLength = $length;
    }

    /**
     * Define os namespaces permitidos para controllers
     *
     * @param array<string> $namespaces
     * @return void
     */
    public function setAllowedNamespaces(array $namespaces): void
    {
        $this->allowedNamespaces = $namespaces;
    }
}
