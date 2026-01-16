<?php
/**
 * Router
 * Simple routing system for handling HTTP requests
 */

namespace aReports\Core;

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private string $basePath = '';
    private array $middleware = [];
    private ?string $currentGroup = null;
    private array $groupMiddleware = [];

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Add a GET route
     */
    public function get(string $path, string|array|callable $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    /**
     * Add a POST route
     */
    public function post(string $path, string|array|callable $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    /**
     * Add a PUT route
     */
    public function put(string $path, string|array|callable $handler, ?string $name = null): self
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    /**
     * Add a DELETE route
     */
    public function delete(string $path, string|array|callable $handler, ?string $name = null): self
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    /**
     * Add route for any HTTP method
     */
    public function any(string $path, string|array|callable $handler, ?string $name = null): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $name);
        }
        return $this;
    }

    /**
     * Add a route
     */
    private function addRoute(string $method, string $path, string|array|callable $handler, ?string $name = null): self
    {
        $path = $this->basePath . '/' . trim($path, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        // Convert path parameters to regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $route = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
            'name' => $name
        ];

        $this->routes[] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        return $this;
    }

    /**
     * Add middleware to the next route
     */
    public function middleware(string|array $middleware): self
    {
        $this->groupMiddleware = array_merge(
            $this->groupMiddleware,
            is_array($middleware) ? $middleware : [$middleware]
        );
        return $this;
    }

    /**
     * Group routes with shared middleware
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware'])
                ? $attributes['middleware']
                : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        }

        $previousBasePath = $this->basePath;
        if (isset($attributes['prefix'])) {
            $this->basePath .= '/' . trim($attributes['prefix'], '/');
        }

        $callback($this);

        $this->groupMiddleware = $previousMiddleware;
        $this->basePath = $previousBasePath;
    }

    /**
     * Match a request to a route
     */
    public function match(string $method, string $uri): ?array
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                    'name' => $route['name']
                ];
            }
        }

        return null;
    }

    /**
     * Generate URL for a named route
     */
    public function url(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $path = $this->namedRoutes[$name]['path'];

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $path;
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(App $app): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Handle method override for forms
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $match = $this->match($method, $uri);

        if ($match === null) {
            $this->handleNotFound($app);
            return;
        }

        // Run middleware
        foreach ($match['middleware'] as $middlewareClass) {
            $middleware = new $middlewareClass($app);
            $result = $middleware->handle();
            if ($result === false) {
                return;
            }
        }

        // Execute handler
        $handler = $match['handler'];
        $params = $match['params'];

        if (is_callable($handler)) {
            call_user_func_array($handler, array_merge([$app], array_values($params)));
        } elseif (is_array($handler)) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass($app);
            call_user_func_array([$controller, $method], array_values($params));
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$controllerClass, $method] = explode('@', $handler);
            $controllerClass = 'aReports\\Controllers\\' . $controllerClass;
            $controller = new $controllerClass($app);
            call_user_func_array([$controller, $method], array_values($params));
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(App $app): void
    {
        http_response_code(404);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found', 'code' => 404]);
        } else {
            $view = new View($app);
            $view->render('errors/404', ['title' => 'Page Not Found']);
        }
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get all routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
