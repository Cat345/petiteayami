<?php

namespace YOOtheme;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class CsrfMiddleware
{
    /**
     * Current token.
     *
     * @var string
     */
    protected string $token;

    /**
     * Verify callable.
     *
     * @var callable
     */
    protected $verify;

    /**
     * Constructor.
     */
    public function __construct(string $token, ?callable $verify = null)
    {
        $this->token = $token;
        $this->verify = $verify ?: [$this, 'verifyToken'];
    }

    /**
     * Handles CSRF token from request.
     *
     * @param Request  $request
     * @param callable $next
     */
    public function handle($request, callable $next): Response
    {
        $csrf = $request->getAttribute('csrf', in_array($request->getMethod(), ['POST', 'DELETE']));

        if ($csrf && !($this->verify)($request->getHeaderLine('X-XSRF-Token'))) {
            $request->abort(401, 'Invalid CSRF token.');
        }

        return $next($request);
    }

    /**
     * Verifies a CSRF token.
     */
    public function verifyToken(string $token): bool
    {
        return $this->token === $token;
    }
}
