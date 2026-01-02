# 🔐 Guia de Segurança - Swift Router

## Correções Implementadas

Este documento descreve as correções de segurança implementadas após auditoria completa do framework.

---

## ✅ Vulnerabilidades Corrigidas

### 1. **CRITICAL** - Instanciação Arbitrária de Classes

**Problema**: Controllers podiam ser instanciados sem validação, permitindo RCE.

**Correção**:
```php
// Configurar namespaces permitidos
$router = new SwiftRouter([
    'App\\Controllers\\',
    'App\\Api\\Controllers\\',
]);

// Ou após a criação
$router->setAllowedNamespaces([
    'App\\Controllers\\',
]);
```

**Proteções**:
- ✅ Validação de existência da classe
- ✅ Whitelist de namespaces
- ✅ Validação de método

---

### 2. **CRITICAL** - Middleware Bypass via Exception

**Problema**: Exceções não tratadas interrompiam a cadeia de middlewares.

**Correção**: Todos os middlewares agora executam dentro de try-catch:

```php
private function wrapMiddleware(...): callable
{
    return static function (RequestContext $context) use ($middleware, $next): Response {
        try {
            // Executa middleware
            return $middleware->process($context, $next);
        } catch (Throwable $e) {
            error_log("Middleware error: " . $e->getMessage());
            return (new Response())->withStatus(500)->withBody(['error' => 'Internal server error']);
        }
    };
}
```

---

### 3. **HIGH** - Validação de Método HTTP

**Problema**: Métodos HTTP não eram validados, permitindo method spoofing.

**Correção**: Criado enum `HttpMethod` com validação estrita:

```php
// Enum com métodos válidos
enum HttpMethod: string {
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
}

// Validação automática no dispatch
$router->dispatch('GET', '/path'); // ✅ OK
$router->dispatch('INVALID', '/path'); // ❌ InvalidArgumentException
```

**Proteções**:
- ✅ Whitelist de métodos HTTP
- ✅ Normalização automática (case-insensitive)
- ✅ Exception clara para métodos inválidos
- ✅ Suporte automático para HEAD (tratado como GET)
- ✅ Suporte automático para OPTIONS

---

### 4. **HIGH** - Path Traversal

**Problema**: Normalização inconsistente entre addRoute e findRoute.

**Correção**: Método centralizado `normalizePath()`:

```php
private function normalizePath(string $path): string
{
    // Remove slashes duplicados
    $path = preg_replace('#/+#', '/', $path);
    
    // Remove trailing slash
    $path = $path !== '/' ? rtrim($path, '/') : '/';
    
    // Detecta path traversal
    $decoded = urldecode($path);
    if (str_contains($decoded, '..') || str_contains($decoded, '\\')) {
        throw new InvalidArgumentException('Path traversal detected');
    }
    
    return $path;
}
```

**Proteções**:
- ✅ Detecção de `../`
- ✅ Detecção de `%2F` encoded
- ✅ Normalização consistente
- ✅ Prevenção de backslash injection

---

### 5. **HIGH** - DoS via Parâmetros Longos

**Problema**: Parâmetros de rota não tinham limite de tamanho.

**Correção**:

```php
// Configurar limite (padrão: 255)
$router->setMaxParamLength(255);

// Validação automática
if (strlen($segment) > $this->maxParamLength) {
    throw new RuntimeException("Parameter exceeds maximum length");
}
```

---

### 6. **HIGH** - Callables Perigosos

**Problema**: Funções perigosas como `system`, `eval` podiam ser usadas como handlers.

**Correção**:

```php
private function validateAndNormalizeHandler(callable|array $handler): callable
{
    if (is_string($handler)) {
        $dangerous = ['system', 'exec', 'passthru', 'shell_exec', 'eval', 'assert'];
        
        if (in_array(strtolower($handler), $dangerous, true)) {
            throw new InvalidArgumentException("Dangerous callable not allowed");
        }
    }
    
    return $handler;
}
```

---

### 7. **MEDIUM** - Type Juggling em Middlewares

**Problema**: AuthMiddleware e ValidationMiddleware usavam comparações fracas.

**Correção**:

```php
// AuthMiddleware - Antes
if (!$token || $token !== 'secret-token') { }

// AuthMiddleware - Depois
if (!is_string($token) || $token === '' || $token !== 'secret-token') { }

// ValidationMiddleware - Antes
if (empty($value)) { }

// ValidationMiddleware - Depois
if (!$exists || $value === null || $value === '') { }
```

---

### 8. **MEDIUM** - JSON Encoding sem Tratamento

**Problema**: `json_encode()` podia falhar silenciosamente.

**Correção**:

```php
try {
    $json = json_encode(
        $response->body,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );
    return $response->withBody($json);
} catch (\JsonException $e) {
    error_log("JSON encoding error: " . $e->getMessage());
    return (new Response())->withStatus(500);
}
```

---

## 🛡️ Boas Práticas de Uso

### 1. Configuração Segura

```php
<?php

use Omegaalfa\SwiftRouter\Router\SwiftRouter;

// SEMPRE especificar namespaces permitidos
$router = new SwiftRouter([
    'App\\Controllers\\',
    'App\\Api\\',
]);

// Configurar limites
$router->setMaxParamLength(255);
$router->setCacheLimit(2048);
```

---

### 2. Validação de Entrada

```php
// Validar método HTTP
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true)) {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Normalizar path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
```

---

### 3. Middlewares de Segurança

```php
use Omegaalfa\SwiftRouter\Middleware\AuthMiddleware;
use Omegaalfa\SwiftRouter\Middleware\ValidationMiddleware;

// Auth global
$router->use(new AuthMiddleware());

// Validação por rota
$router->get('/user/:id', $handler, [
    new ValidationMiddleware(['id' => 'numeric'])
]);
```

---

### 4. Tratamento de Erros

```php
try {
    $response = $router->dispatch($method, $path, $initialData);
    
    // Enviar resposta
    http_response_code($response->statusCode);
    echo $response->body;
    
} catch (\InvalidArgumentException $e) {
    // Erro de validação (400)
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    
} catch (\RuntimeException $e) {
    // Rota não encontrada (404)
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    
} catch (\Throwable $e) {
    // Erro interno (500)
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
```

---

### 5. Controllers Seguros

```php
namespace App\Controllers;

class UserController
{
    public function show(RequestContext $ctx, Response $res): Response
    {
        $id = $ctx->params['id'];
        
        // Validar input
        if (!is_numeric($id)) {
            return $res->withStatus(400)->withBody(['error' => 'Invalid ID']);
        }
        
        // Processar...
        return $res->withBody(['user' => ['id' => $id]]);
    }
}

// Registrar rota
$router->get('/user/:id', [UserController::class, 'show']);
```

---

## 🚨 Checklist de Segurança

Antes de colocar em produção:

- [ ] ✅ Configurar namespaces permitidos para controllers
- [ ] ✅ Configurar limite de tamanho de parâmetros
- [ ] ✅ Adicionar middlewares de autenticação em rotas protegidas
- [ ] ✅ Validar entrada do usuário (query params, body, headers)
- [ ] ✅ Implementar rate limiting (usar Redis/Memcached)
- [ ] ✅ Configurar CORS adequadamente (não usar `*`)
- [ ] ✅ Adicionar logging de erros
- [ ] ✅ Implementar CSRF protection para formulários
- [ ] ✅ Usar HTTPS em produção
- [ ] ✅ Validar Content-Type em POST/PUT
- [ ] ✅ Implementar timeout de requisição

---

## 📊 Testes de Segurança

Execute os testes para verificar as correções:

```bash
# Instalar dependências
composer install

# Rodar testes
vendor/bin/phpunit

# Análise estática
vendor/bin/phpstan analyse
```

---

## 🔍 Auditoria Contínua

Recomendações:

1. **Revisar código regularmente** - Use ferramentas como PHPStan, Psalm
2. **Atualizar dependências** - `composer update` regularmente
3. **Monitorar logs** - Alertar em erros 500 e tentativas de path traversal
4. **Rate limiting** - Implementar em produção com Redis
5. **WAF** - Considerar usar Web Application Firewall

---

## 📝 Changelog de Segurança

### v2.0.0 (2025-12-27)

**Correções Críticas:**
- ✅ Validação de método HTTP com enum
- ✅ Prevenção de instanciação arbitrária
- ✅ Try-catch em middlewares
- ✅ Normalização consistente de paths
- ✅ Validação de handlers perigosos

**Correções Altas:**
- ✅ Limite de tamanho em parâmetros
- ✅ Type juggling corrigido em middlewares
- ✅ JSON encoding com tratamento de erro

**Melhorias:**
- ✅ Suporte automático para HEAD e OPTIONS
- ✅ Configuração de namespaces permitidos
- ✅ Métodos de configuração de segurança

---

## 📧 Reportar Vulnerabilidade

Se encontrar uma vulnerabilidade de segurança:

1. **NÃO** abra uma issue pública
2. Envie email para: security@example.com
3. Inclua: descrição, impacto, PoC (se possível)

Você receberá resposta em até 48 horas.

---

## 🎖️ Créditos

Auditoria de segurança realizada em 27/12/2025 por sistema especializado em AppSec e frameworks PHP.
