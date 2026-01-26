<?php

namespace YOOtheme\Image;

use YOOtheme\Image;

class ImageStockPhoto extends Image
{
    protected bool $remote = true;
    protected bool $resizable = true;
    protected ?string $focalPoint = null;

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->width || $this->height
            ? $this->getUrl($this->file, $this->width, $this->height, $this->focalPoint)
            : $this->file;
    }

    /**
     * Resizes the image.
     *
     * @param int|string $width
     * @param int|string $height
     *
     * @return static
     */
    public function resize($width = null, $height = null, string $background = 'crop'): self
    {
        $image = clone $this;

        $image->width = $width ? (int) $width : null;
        $image->height = $height ? (int) $height : null;
        $image->focalPoint = null;

        return $image;
    }

    /**
     * Thumbnail the image.
     *
     * @param int|string $width
     * @param int|string $height
     *
     * @return static
     */
    public function thumbnail(
        $width = null,
        $height = null,
        bool $flip = false,
        string $x = 'center',
        string $y = 'center'
    ): self {
        $image = clone $this;

        $image->width = $width ? (int) $width : null;
        $image->height = $height ? (int) $height : null;
        $image->focalPoint =
            implode(',', array_filter([$x, $y], fn($point) => $point && $point !== 'center')) ?:
            null;

        return $image;
    }

    protected function getUrl(string $url, ?int $width, ?int $height, ?string $focalPoint): string
    {
        $url = parse_url($url);
        $query = [
            'w' => $width ?: null,
            'h' => $height ?: null,
            'fit' => 'crop',
            'crop' => $focalPoint ?: null,
        ];

        return "{$url['scheme']}://{$url['host']}{$url['path']}?" .
            http_build_query($query, '', '&');
    }
}
