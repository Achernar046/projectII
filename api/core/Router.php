<?php
/**
 * api/core/Router.php
 * Object-Oriented RESTful API Router with Dynamic Route Parameters & Middleware
 */

class Router
{
    private array $routes = [];

    /**
     * Add a route to the routing table
     */
    public function add(string $method, string $path, $handler, array $middlewares = []): self
    {
        $normalizedPath = '/' . trim($path, '/');
        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $normalizedPath,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
        return $this;
    }

    public function get(string $path, $handler, array $middlewares = []): self
    {
        return $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, $handler, array $middlewares = []): self
    {
        return $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, $handler, array $middlewares = []): self
    {
        return $this->add('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, $handler, array $middlewares = []): self
    {
        return $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Dispatch incoming HTTP request to matching route
     */
    public function dispatch(string $requestMethod, string $requestUri): void
    {
        // Strip query string and normalize path
        $cleanUri = parse_url($requestUri, PHP_URL_PATH) ?? '';
        $cleanUri = '/' . trim($cleanUri, '/');

        $methodMatched = false;

        foreach ($this->routes as $route) {
            // Build regex: escape literal path segments, then insert named captures for {param}
            $parts = preg_split('#(\{[a-zA-Z0-9_]+\})#', $route['path'], -1, PREG_SPLIT_DELIM_CAPTURE);
            $pattern = '';
            foreach ($parts as $part) {
                if (preg_match('#^\{([a-zA-Z0-9_]+)\}$#', $part, $pm)) {
                    $pattern .= '(?P<' . $pm[1] . '>[^/]+)';
                } else {
                    $pattern .= preg_quote($part, '#');
                }
            }
            $regex = '#^' . $pattern . '$#';

            if (preg_match($regex, $cleanUri, $matches)) {
                if ($route['method'] !== strtoupper($requestMethod)) {
                    $methodMatched = true;
                    continue;
                }

                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        call_user_func($middleware);
                    }
                }

                // Extract named parameter arguments
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Execute Handler
                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], $params);
                    return;
                }

                if (is_array($route['handler']) && count($route['handler']) === 2) {
                    [$classOrObj, $method] = $route['handler'];
                    if (is_object($classOrObj)) {
                        call_user_func_array([$classOrObj, $method], $params);
                        return;
                    }
                }

                Response::error('Invalid route handler configuration', 500);
                return;
            }
        }

        if ($methodMatched) {
            Response::error("Method {$requestMethod} not allowed for endpoint {$cleanUri}", 405);
        } else {
            Response::notFound("Endpoint ไม่ถูกต้อง: [{$requestMethod}] {$cleanUri}");
        }
    }
}
