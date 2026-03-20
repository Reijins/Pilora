<?php
declare(strict_types=1);

namespace Core\Routing;

use Core\Http\Request;
use Core\Http\Response;
use Core\Context\UserContext;

final class Router
{
    /** @var array<string, array<int, array{pattern: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $this->routes[$method] ??= [];
        $this->routes[$method][] = [
            'pattern' => $path,
            'handler' => $handler,
        ];
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return rtrim($path, '/') ?: '/';
    }

    public function match(string $method, string $path): ?callable
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        foreach ($this->routes[$method] ?? [] as $route) {
            if ($route['pattern'] === $path) {
                return $route['handler'];
            }
        }

        return null;
    }
}

