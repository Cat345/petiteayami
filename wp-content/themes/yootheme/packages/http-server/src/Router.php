<?php

namespace YOOtheme;

use YOOtheme\Http\Request;

class Router
{
    protected Routes $routes;

    public function __construct(Routes $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Dispatches router for a request.
     */
    public function dispatch(Request $request): Request
    {
        $path = '/' . trim($request->getAttribute('routePath', ''), '/');

        foreach ($this->routes->getIndex() as $route) {
            if ($route->getMethods() && !in_array($request->getMethod(), $route->getMethods())) {
                continue;
            }

            if (preg_match($this->getPattern($route), $path, $matches)) {
                $params = [];

                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = urldecode($value);
                    }
                }

                foreach ($route->getAttributes() as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                return $request
                    ->withAttribute('route', $route)
                    ->withAttribute('routeParams', $params)
                    ->withAttribute('routeStatus', 1);
            }
        }

        return $request->withAttribute('routeStatus', 0);
    }

    /**
     * Gets the route regex pattern.
     */
    protected function getPattern(Route $route): string
    {
        return '#^' .
            preg_replace_callback(
                '#\{(\w+)(?::([^}]+?))?}#',
                fn($matches) => '(?P<' . $matches[1] . '>' . ($matches[2] ?? '[^/]+') . ')',
                $route->getPath(),
            ) .
            '$#';
    }
}
