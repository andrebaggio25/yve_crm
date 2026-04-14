<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    public function get(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    public function delete(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    public function patch(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute('PATCH', $path, $handler, $name);
    }

    public function any(string $path, $handler, ?string $name = null): self
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $handler, $name);
    }

    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;
        
        $this->prefix .= '/' . trim($prefix, '/');
        $this->middleware = array_merge($this->middleware, $middleware);
        
        $callback($this);
        
        $this->prefix = $previousPrefix;
        $this->middleware = $previousMiddleware;
    }

    private function addRoute($methods, string $path, $handler, ?string $name = null): self
    {
        $methods = is_array($methods) ? $methods : [$methods];
        
        $fullPath = $this->prefix . '/' . trim($path, '/');
        $fullPath = trim($fullPath, '/');
        
        $route = [
            'methods' => $methods,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $this->middleware,
            'name' => $name,
            'regex' => $this->pathToRegex($fullPath),
            'params' => $this->extractParams($fullPath)
        ];
        
        $this->routes[] = $route;
        
        return $this;
    }

    private function pathToRegex(string $path): string
    {
        $path = trim($path, '/');
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = $pattern ?: '';
        return '#^' . $pattern . '$#';
    }

    private function extractParams(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }

    public function dispatch(Request $request, Response $response): void
    {
        $uri = $request->getPath();
        $method = $request->getMethod();
        
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'])) {
                continue;
            }
            
            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }
            
            $params = [];
            foreach ($route['params'] as $param) {
                $params[$param] = $matches[$param] ?? null;
            }
            
            $request->setParams($params);
            
            try {
                $this->runMiddleware($route['middleware'], $request, $response);
                $this->runHandler($route['handler'], $request, $response);
            } catch (\Exception $e) {
                $this->handleError($e, $request, $response);
            }
            
            return;
        }
        
        $this->handleNotFound($request, $response);
    }

    private function runMiddleware(array $middleware, Request $request, Response $response): void
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && str_contains($entry, ':')) {
                [$className, $param] = explode(':', $entry, 2);
                if (!class_exists($className)) {
                    throw new \Exception("Middleware not found: {$className}");
                }
                $ref = new \ReflectionClass($className);
                $instance = $ref->newInstance($param);
            } elseif (is_string($entry)) {
                if (!class_exists($entry)) {
                    throw new \Exception("Middleware not found: {$entry}");
                }
                $instance = new $entry();
            } else {
                $instance = $entry;
            }

            $result = $instance->handle($request, $response);

            if ($result === false) {
                exit;
            }
        }
    }

    private function runHandler($handler, Request $request, Response $response): void
    {
        if (is_callable($handler)) {
            $result = $handler($request, $response);
            
            if ($result !== null) {
                if (is_array($result) || is_object($result)) {
                    $response->json($result);
                } else {
                    echo $result;
                }
            }
            
            return;
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controllerClass, $method) = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controllerClass}";
            
            if (!class_exists($controllerClass)) {
                throw new \Exception("Controller not found: {$controllerClass}");
            }
            
            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method not found: {$controllerClass}::{$method}");
            }
            
            $result = $controller->$method($request, $response);
            
            if ($result !== null) {
                if (is_array($result) || is_object($result)) {
                    $response->json($result);
                } else {
                    echo $result;
                }
            }
            
            return;
        }
        
        if (is_array($handler) && count($handler) === 2) {
            list($controller, $method) = $handler;
            $result = $controller->$method($request, $response);
            
            if ($result !== null) {
                if (is_array($result) || is_object($result)) {
                    $response->json($result);
                } else {
                    echo $result;
                }
            }
            
            return;
        }
        
        throw new \Exception("Invalid route handler");
    }

    private function handleError(\Exception $e, Request $request, Response $response): void
    {
        App::logError('Router error', $e);

        $debug = App::config('debug', false);

        if ($request->isJson()) {
            $msg = $debug ? $e->getMessage() : 'Erro interno do servidor';
            $response->jsonError($msg, 500, $debug ? ['exception' => $e->getMessage()] : []);
        } else {
            $response->view('errors.500', $debug ? ['error' => $e->getMessage()] : [], null);
        }
    }

    private function handleNotFound(Request $request, Response $response): void
    {
        if ($request->isJson() || str_starts_with($request->getPath(), 'api/')) {
            $response->jsonError('Route not found', 404);
        } else {
            $response->setStatusCode(404);
            $response->view('errors.404', [], null);
        }
    }

    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $name) {
                $url = $route['path'];
                
                foreach ($params as $key => $value) {
                    $url = str_replace("{{$key}}", $value, $url);
                }
                
                return $url;
            }
        }
        
        throw new \Exception("Route not found: {$name}");
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
