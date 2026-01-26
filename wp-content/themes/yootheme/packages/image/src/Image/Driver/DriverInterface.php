<?php

namespace YOOtheme\Image\Driver;

interface DriverInterface
{
    /**
     * Creates an image.
     */
    public function __construct(string $file, ?string $type);

    /**
     * Save image to file.
     *
     * @param string|resource $file
     * @param array<string, mixed>  $info
     */
    public function save($file, string $type, int $quality, array $info = []): bool;

    /**
     * Do the image crop.
     */
    public function doCrop(int $width, int $height, int $x, int $y): void;

    /**
     * Do the image copy.
     */
    public function doCopy(
        int $width,
        int $height,
        int $dstX,
        int $dstY,
        int $srcX,
        int $srcY,
        int $dstWidth,
        int $dstHeight,
        int $srcWidth,
        int $srcHeight,
        string $background = 'transparent'
    ): void;

    /**
     * Do the image resize.
     */
    public function doResize(
        int $width,
        int $height,
        int $dstWidth,
        int $dstHeight,
        string $background = 'transparent'
    ): void;

    /**
     * Do the image rotate.
     */
    public function doRotate(int $angle, string $background = 'transparent'): void;
}
