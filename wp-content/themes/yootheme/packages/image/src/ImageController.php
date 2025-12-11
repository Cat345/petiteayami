<?php

namespace YOOtheme;

use YOOtheme\Http\CallbackStream;
use YOOtheme\Http\Exception;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class ImageController
{
    protected ?string $cache;
    protected ImageProvider $provider;

    /**
     * Constructor.
     */
    public function __construct(ImageProvider $provider)
    {
        $this->cache = $provider->cache;
        $this->provider = $provider;
    }

    /**
     * Gets the image file.
     *
     * @param Request       $request
     * @param Response      $response
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function get(Request $request, Response $response)
    {
        $src = $request->getQueryParam('src', '');
        $hash = $request->getQueryParam('hash');

        if (!$request->getAttribute('save')) {
            $this->cache = null;
        }

        if ($hash !== $this->provider->getHash($src)) {
            throw new Exception(400, 'Invalid image hash');
        }

        $params = json_decode($src, true);
        $file = $params['file'] ?? '';
        unset($params['file']);

        $response =
            $this->loadImage($this->getImage($file, $params, false), $response) ??
            $this->createImage($this->getImage($file, $params), $response);

        return $response->withHeader('Cache-Control', 'max-age=600, must-revalidate');
    }

    protected function getImage(string $file, array $params, bool $resource = true): Image
    {
        $image = $this->provider->create($file, $resource);

        if (!$image) {
            throw new Exception(404, "Image '{$file}' not found");
        }

        if ($resource) {
            Memory::raise();
        }

        return $image->apply($params);
    }

    protected function loadImage(Image $image, Response $response): ?Response
    {
        $cache = $this->cache
            ? Path::join($this->cache, substr($image->getHash(), 0, 2), $image->getFilename())
            : null;

        $callback = function () use ($cache): string {
            readfile($cache);
            return '';
        };

        return $cache && is_file($cache)
            ? $response
                ->withBody(new CallbackStream($callback))
                ->withHeader('Content-Type', "image/{$image->getType()}")
                ->withHeader('Content-Length', filesize($cache))
            : null;
    }

    protected function createImage(Image $image, Response $response): Response
    {
        $temp = fopen('php://temp', 'rw+');

        if (!$temp) {
            throw new Exception(500, 'Unable to create temporary file');
        }

        if (!$image->save($temp)) {
            throw new Exception(500, 'Image saving failed');
        }

        $cache = $this->cache
            ? Path::join($this->cache, substr($image->getHash(), 0, 2), $image->getFilename())
            : null;

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
            ->withHeader('Content-Length', ftell($temp));
    }
}
