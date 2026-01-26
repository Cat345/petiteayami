<?php

namespace YOOtheme;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class BodyMiddleware
{
    /**
     * Handles JSON requests.
     *
     * @param Request $request
     */
    public static function parseJson($request, callable $next): Response
    {
        if (stripos($request->getHeaderLine('Content-Type'), 'application/json') === 0) {
            $request = $request->withParsedBody(json_decode((string) $request->getBody(), true));
        }

        return $next($request);
    }
}
