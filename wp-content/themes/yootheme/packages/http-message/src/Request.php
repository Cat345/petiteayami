<?php

namespace YOOtheme\Http;

use Psr\Http\Message\UploadedFileInterface;
use YOOtheme\Http\Message\ServerRequest;

/** @phpstan-ignore class.extendsFinalByPhpDoc */
class Request extends ServerRequest
{
    use MessageTrait;

    /**
     * Gets a parameter (shortcut).
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function __invoke(string $key, $default = null)
    {
        return $this->getParam($key, $default);
    }

    /**
     * Retrieve a parameter value from body or query string (in that order).
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        $body = $this->getParsedBody();

        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }

        if (is_object($body) && property_exists($body, $key)) {
            return $body->$key;
        }

        return $this->getQueryParam($key, $default);
    }

    /**
     * Retrieve a value from query string parameters.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getQueryParam(string $key, $default = null)
    {
        $query = $this->getQueryParams();

        return $query[$key] ?? $default;
    }

    /**
     * Retrieve a value from cookies.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getCookieParam(string $key, $default = null)
    {
        $cookies = $this->getCookieParams();

        return $cookies[$key] ?? $default;
    }

    /**
     * Retrieve a value from server parameters.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getServerParam(string $key, $default = null)
    {
        $server = $this->getServerParams();

        return $server[$key] ?? $default;
    }

    /**
     * Retrieve a single file upload.
     *
     * @return ?UploadedFileInterface
     */
    public function getUploadedFile(string $key)
    {
        $files = $this->getUploadedFiles();

        return $files[$key] ?? null;
    }

    /**
     * Does this request use a given method?
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Throws an exception.
     *
     * @throws Exception
     * @return never
     */
    public function abort(int $code, string $message = ''): void
    {
        throw new Exception($code, $message);
    }

    /**
     * Throws an exception if given condition is true.
     *
     * @return $this
     * @throws Exception
     */
    public function abortIf(bool $bool, int $code, string $message = ''): self
    {
        if ($bool) {
            throw new Exception($code, $message);
        }

        return $this;
    }
}
