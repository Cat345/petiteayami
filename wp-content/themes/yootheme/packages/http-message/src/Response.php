<?php

namespace YOOtheme\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use YOOtheme\Http\Message\Response as BaseResponse;
use YOOtheme\Http\Message\Stream;

/** @phpstan-ignore class.extendsFinalByPhpDoc */
class Response extends BaseResponse
{
    use MessageTrait;

    /**
     * @var array<string, mixed>
     */
    protected array $cookies = [];

    /**
     * Writes data to the body.
     *
     * @return $this
     */
    public function write(string $data): self
    {
        $body = $this->getBody();
        $body->write($data);

        return $this;
    }

    /**
     * Writes a file to body.
     *
     * @param string|resource|StreamInterface $file
     *
     * @return static
     * @throws InvalidArgumentException
     *
     */
    public function withFile($file, ?string $mimetype = null): self
    {
        $body = Stream::create(is_string($file) ? fopen($file, 'r') : $file);

        if (!$mimetype && is_string($file) && function_exists('finfo_file')) {
            $mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
        }

        if (!$mimetype) {
            $mimetype = mime_content_type($file);
        }

        if (!$mimetype) {
            throw new InvalidArgumentException('Unknown file MIME type.');
        }

        return $this->withBody($body)
            ->withHeader('Content-Type', $mimetype)
            ->withHeader('Content-Length', (string) $body->getSize());
    }

    /**
     * Writes JSON to the body.
     *
     * @param mixed $data
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withJson(
        $data,
        ?int $status = null,
        int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): self {
        if (!is_string($json = @json_encode($data, $options))) {
            throw new InvalidArgumentException(json_last_error_msg(), json_last_error());
        }

        $body = Stream::create($json);
        $response = $this->withBody($body)
            ->withHeader('Content-Length', (string) $body->getSize())
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        return is_null($status) ? $response : $response->withStatus($status);
    }

    /**
     * Redirect response.
     *
     * @return static
     */
    public function withRedirect(string $url, int $status = 302): self
    {
        return $this->withStatus($status)->withHeader('Location', $url);
    }

    /**
     * Sets a response cookie.
     *
     * @param array<string, mixed>  $options
     *
     * @return static
     */
    public function withCookie(string $name, string $value = '', array $options = []): self
    {
        $defaults = [
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        ];

        $cookie = array_replace($defaults, $options);
        $cookie['value'] = $value;
        $cookie['expire'] = is_string($cookie['expire'])
            ? strtotime($cookie['expire'])
            : intval($cookie['expire']);

        $clone = clone $this;
        $clone->cookies[$name] = $cookie;

        return $clone;
    }

    /**
     * Sends the response.
     *
     * @return $this
     */
    public function send(): self
    {
        if (!headers_sent()) {
            $this->sendHeaders();
        }

        echo $this->getBody();

        flush();

        return $this;
    }

    /**
     * Sends the response headers.
     *
     * @return $this
     */
    public function sendHeaders(): self
    {
        header(
            sprintf(
                'HTTP/%s %s %s',
                $this->getProtocolVersion(),
                $this->getStatusCode(),
                $this->getReasonPhrase(),
            ),
        );

        foreach ($this->getHeaders() as $name => $values) {
            header(sprintf('%s: %s', $name, implode(',', $values)));
        }

        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly'],
            );
        }

        flush();

        return $this;
    }

    /**
     * Is this response informational?
     */
    public function isInformational(): bool
    {
        return $this->getStatusCode() >= 100 && $this->getStatusCode() < 200;
    }

    /**
     * Is this response OK?
     */
    public function isOk(): bool
    {
        return $this->getStatusCode() == 200;
    }

    /**
     * Is this response empty?
     */
    public function isEmpty(): bool
    {
        return in_array($this->getStatusCode(), [204, 205, 304]);
    }

    /**
     * Is this response successful?
     */
    public function isSuccessful(): bool
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    /**
     * Is this response a redirect?
     */
    public function isRedirect(): bool
    {
        return in_array($this->getStatusCode(), [301, 302, 303, 307]);
    }

    /**
     * Is this response a redirection?
     */
    public function isRedirection(): bool
    {
        return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
    }

    /**
     * Is this response forbidden?
     */
    public function isForbidden(): bool
    {
        return $this->getStatusCode() == 403;
    }

    /**
     * Is this response not Found?
     */
    public function isNotFound(): bool
    {
        return $this->getStatusCode() == 404;
    }

    /**
     * Is this response a client error?
     */
    public function isClientError(): bool
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    /**
     * Is this response a server error?
     */
    public function isServerError(): bool
    {
        return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
    }

    /**
     * Returns the body as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getBody();
    }
}
