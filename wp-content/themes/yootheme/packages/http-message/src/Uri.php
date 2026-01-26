<?php

namespace YOOtheme\Http;

use YOOtheme\Http\Message\Uri as BaseUri;

/** @phpstan-ignore class.extendsFinalByPhpDoc */
class Uri extends BaseUri
{
    /**
     * @see https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/Routing/Generator/UrlGenerator.php
     */
    protected const QUERY_FRAGMENT_DECODED = [
        // RFC 3986 explicitly allows those in the query/fragment to reference other URIs unencoded
        '%2F' => '/',
        '%252F' => '%2F',
        '%3F' => '?',
        // reserved chars that have no special meaning for HTTP URIs in a query or fragment
        // this excludes esp. "&", "=" and also "+" because PHP would treat it as a space (form-encoded)
        '%40' => '@',
        '%3A' => ':',
        '%21' => '!',
        '%3B' => ';',
        '%2C' => ',',
        '%2A' => '*',
    ];

    /**
     * Retrieve query string arguments.
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        parse_str($this->getQuery(), $query);

        return $query;
    }

    /**
     * Retrieve a value from query string arguments.
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
     * @inheritdoc
     */
    public function withQuery($query): self
    {
        return parent::withQuery(strtr($query, self::QUERY_FRAGMENT_DECODED));
    }

    /**
     * Return an instance with the specified query parameters.
     *
     * @param array<string, mixed> $parameters
     *
     * @return static
     */
    public function withQueryParams(array $parameters): self
    {
        return $this->withQuery(http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }
}
