<?php

namespace YOOtheme;

use Exception;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class RouterMiddleware
{
    public const FOUND = 1;
    public const NOT_FOUND = 0;
    public const METHOD_NOT_ALLOWED = 2;

    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Handles the route dispatch.
     *
     * @param Request  $request
     */
    public function handleRoute($request, callable $next): Response
    {
        return $next($this->router->dispatch($request));
    }

    /**
     * Handles the route status.
     *
     * @param Request  $request
     */
    public function handleStatus($request, callable $next): Response
    {
        $status = $request->getAttribute('routeStatus');

        // Not found
        if ($status === static::NOT_FOUND) {
            $request->abort(404);
        }

        // Method not allowed
        if ($status === static::METHOD_NOT_ALLOWED) {
            $request->abort(405);
        }

        return $next($request);
    }

    /**
     * Handles an error.
     *
     * @param Response   $response
     * @param Exception $exception
     */
    public function handleError($response, $exception): Response
    {
        if ($exception instanceof Http\Exception) {
            return $response->withStatus($exception->getCode(), $exception->getMessage());
        }

        return $response->withStatus(500, $exception->getMessage());
    }
}
