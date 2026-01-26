<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Http\Response;
use YOOtheme\HttpClientInterface;

class HttpClient implements HttpClientInterface
{
    /**
     * @inheritdoc
     */
    public function get(string $url, array $options = []): Response
    {
        return $this->makeRequest($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function post(string $url, $data = null, array $options = []): Response
    {
        $options['method'] = 'POST';

        if ($data) {
            $options['body'] = $data;
        }

        return $this->makeRequest($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function put(string $url, $data = null, array $options = []): Response
    {
        $options['method'] = 'PUT';

        if ($data) {
            $options['body'] = $data;
        }

        return $this->makeRequest($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $url, array $options = []): Response
    {
        $options['method'] = 'DELETE';

        return $this->makeRequest($url, $options);
    }

    /**
     * Makes a request by using `wp_remote_request`.
     *
     * @param array<string, mixed> $options
     */
    protected function makeRequest(string $url, array $options): Response
    {
        $options = $this->filterOptions($options);
        $response = wp_remote_request($url, $options);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        return (new Response(
            wp_remote_retrieve_response_code($response),
            wp_remote_retrieve_headers($response)->getAll(),
        ))->write(wp_remote_retrieve_body($response));
    }

    /**
     * Filters request options.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(array $options): array
    {
        $result = [];

        foreach ($options as $name => $value) {
            if ($name == 'userAgent') {
                $name = 'user-agent';
            }

            $result[$name] = $value;
        }

        return $result;
    }
}
