<?php

namespace YOOtheme;

use YOOtheme\Http\CallbackStream;
use YOOtheme\Http\Exception;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use YOOtheme\Http\Uri;
use YOOtheme\Image\ImageDriver;
use YOOtheme\Image\ImageResizable;
use YOOtheme\Image\Listener\LoadImageUrl;

class ImageController
{
    protected ?string $cache;

    public function __construct(Config $config)
    {
        $this->cache = $config('image.cacheDir');

        // create cache directory
        File::makeDir($this->cache, 0777, true);
    }

    /**
     * Gets the image file.
     */
    public function get(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $file = $request->getAttribute('routeParams')['file'] ?? '';
        $cache = Path::join($this->cache, $file);

        if (!$this->verifyHash($file, $params)) {
            throw new Exception(400, 'Invalid image hash');
        }

        if (($params['cache'] ?? '') === 'false') {
            $this->cache = null;
        }

        $response =
            $this->loadImage($cache, $response) ?? $this->createImage($cache, $params, $response);

        if ($request->isMethod('HEAD')) {
            $response = $response->withBody(new CallbackStream(fn() => ''));
        }

        return $response->withHeader('Cache-Control', 'max-age=600, must-revalidate');
    }

    protected function loadImage(string $cache, Response $response): ?Response
    {
        if (!is_file($cache) || !($info = ImageResizable::getInfo($cache))) {
            return null;
        }

        $callback = function () use ($cache): string {
            readfile($cache);
            return '';
        };

        return $response
            ->withBody(new CallbackStream($callback))
            ->withHeader('Content-Type', "image/{$info[2]}")
            ->withHeader('Content-Length', (string) filesize($cache));
    }

    /**
     * @param array<string, string> $params
     */
    protected function createImage(string $cache, array $params, Response $response): Response
    {
        Memory::raise();

        $file = Event::emit('image.resolve|filter', $params['src']);

        $image = ImageDriver::fromFile($file);

        if (!$image) {
            throw new Exception(404, "Image '{$params['src']}' not found");
        }

        foreach ($params as $key => $args) {
            if (method_exists($image, $key)) {
                $image = $image->$key(...explode(',', $args));
            }
        }

        $temp = fopen('php://temp', 'rw+');

        if (!$temp) {
            throw new Exception(500, 'Unable to create temporary file');
        }

        if (!$image->save($temp)) {
            throw new Exception(500, 'Image saving failed');
        }

        $cache = $this->cache ? $cache : null;

        $callback = function () use ($temp, $cache): string {
            // output image first
            if (rewind($temp)) {
                stream_copy_to_stream($temp, fopen('php://output', 'w'));
                flush();
            }

            // write image to cache
            if ($cache && !is_file($cache) && rewind($temp) && File::makeDir(dirname($cache))) {
                file_put_contents($cache, $temp, LOCK_EX);
            }

            fclose($temp);
            return '';
        };

        return $response
            ->withBody(new CallbackStream($callback))
            ->withHeader('Content-Type', "image/{$image->getType()}")
            ->withHeader('Content-Length', (string) ftell($temp));
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function verifyHash(string $file, array $params): bool
    {
        $query = array_intersect_key($params, array_flip(get_class_methods(ImageDriver::class)));
        $query += ['cachekey' => array_last(explode('-', pathinfo($file, PATHINFO_FILENAME)))];

        if ($params['cache'] ?? false) {
            $query['cache'] = $params['cache'];
        }

        $uri = ($params['src'] ?? '') . '?' . (new Uri(''))->withQueryParams($query)->getQuery();
        return hash_equals($params['hash'] ?? '', LoadImageUrl::getHash($uri));
    }
}
