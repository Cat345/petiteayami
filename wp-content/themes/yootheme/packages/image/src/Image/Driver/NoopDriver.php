<?php

namespace YOOtheme\Image\Driver;

class NoopDriver implements DriverInterface
{
    /**
     * @inheritdoc
     */
    public function __construct(string $file, ?string $type) {}

    /**
     * @inheritdoc
     */
    public function save($file, string $type, int $quality, array $info = []): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function doCrop(int $width, int $height, int $x, int $y): void {}

    /**
     * @inheritdoc
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
    ): void {}

    /**
     * @inheritdoc
     */
    public function doResize(
        int $width,
        int $height,
        int $dstWidth,
        int $dstHeight,
        string $background = 'transparent'
    ): void {}

    /**
     * @inheritdoc
     */
    public function doRotate(int $angle, string $background = 'transparent'): void {}
}
