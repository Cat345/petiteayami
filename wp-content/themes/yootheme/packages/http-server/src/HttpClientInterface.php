<?php

namespace YOOtheme;

use YOOtheme\Http\Response;

interface HttpClientInterface
{
    /**
     * Execute a GET HTTP request.
     *
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): Response;

    /**
     * Execute a POST HTTP request.
     *
     * @param array<string, mixed>|string|null $data
     * @param array<string, mixed> $options
     */
    public function post(string $url, $data = null, array $options = []): Response;

    /**
     * Execute a PUT HTTP request.
     *
     * @param array<string, mixed>|string|null $data
     * @param array<string, mixed> $options
     */
    public function put(string $url, $data = null, array $options = []): Response;

    /**
     * Execute a DELETE HTTP request.
     *
     * @param array<string, mixed> $options
     */
    public function delete(string $url, array $options = []): Response;
}
