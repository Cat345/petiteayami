<?php

namespace YOOtheme\Builder\Newsletter;

use Psr\Http\Message\ResponseInterface;
use YOOtheme\HttpClientInterface;

/**
 * @phpstan-type Response array{success: bool, data:string|array<string, mixed>}
 */
abstract class AbstractProvider
{
    protected string $apiKey;
    protected string $apiEndpoint = '';
    protected HttpClientInterface $client;

    public function __construct(string $apiKey, HttpClientInterface $client)
    {
        $this->apiKey = $apiKey;
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $provider
     *
     * @return array{
     *     lists: list<array{value: string, text: string}>,
     *     clients: list<array{value: string, text: string}>
     * }
     *
     * @throws \Exception
     */
    abstract public function lists(array $provider): array;

    /**
     * @param string $email
     * @param array<string, mixed> $data
     * @param array<string, mixed>  $provider
     *
     * @throws \Exception
     */
    abstract public function subscribe(string $email, array $data, array $provider): bool;

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return Response
     */
    public function get(string $name, array $args = []): array
    {
        return $this->request('GET', $name, $args);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return Response
     */
    public function post(string $name, array $args = []): array
    {
        return $this->request('POST', $name, $args);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @throws \Exception
     *
     * @return Response
     */
    protected function request(string $method, string $name, array $args): array
    {
        $url = "{$this->apiEndpoint}/{$name}";

        $headers = $this->getHeaders() + [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];

        switch ($method) {
            case 'GET':
                $query = http_build_query($args, '', '&');
                $response = $this->client->get("{$url}?{$query}", compact('headers'));
                break;
            case 'POST':
                $response = $this->client->post($url, json_encode($args), compact('headers'));
                break;

            default:
                throw new \Exception("Call to undefined method {$method}");
        }

        $decoded = json_decode($response->getBody(), true);
        $success = $response->isSuccessful() && $decoded;

        return [
            'success' => $success,
            'data' => $success ? $decoded : $this->findError($response, $decoded),
        ];
    }

    /**
     * Get error message from response.
     *
     * @param ResponseInterface $response
     * @param array<string, string> $formattedResponse
     */
    protected function findError($response, $formattedResponse): string
    {
        return $formattedResponse['detail'] ??
            'Unknown error, call getLastResponse() to find out what happened.';
    }
}
