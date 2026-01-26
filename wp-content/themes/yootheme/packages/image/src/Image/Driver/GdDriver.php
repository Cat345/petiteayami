<?php

namespace YOOtheme\Image\Driver;

class GdDriver implements DriverInterface
{
    use DriverHelper;

    /**
     * @var ?resource
     */
    protected $image;

    /**
     * @inheritdoc
     */
    public function __construct(string $file, ?string $type)
    {
        switch ($type) {
            case 'png':
                $image = imagecreatefrompng($file);
                break;

            case 'gif':
                $image = imagecreatefromgif($file);
                break;

            case 'jpeg':
                $image = imagecreatefromjpeg($file);
                break;

            case 'webp':
                $image = imagecreatefromwebp($file);
                break;

            case 'avif':
                /** @phpstan-ignore function.notFound */
                $image = imagecreatefromavif($file);
                break;

            default:
                $image = false;
        }

        $this->image = $image ? static::normalizeImage($image) : null;
    }

    /**
     * @inheritdoc
     */
    public function save($file, string $type, int $quality, array $info = []): bool
    {
        if ($type == 'jpeg') {
            if (!imagejpeg($this->image, $file, (int) round($quality))) {
                return false;
            }

            if (
                is_string($file) &&
                !empty($info['APP13']) &&
                ($iptc = iptcparse($info['APP13'])) &&
                ($data = static::embedIptc($iptc, $file)) &&
                !file_put_contents($file, $data)
            ) {
                return false;
            }

            return true;
        }

        if ($type == 'png') {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);

            return imagepng($this->image, $file, 9);
        }

        if ($type == 'gif') {
            return imagegif($this->image, $file);
        }

        if ($type == 'webp') {
            return imagewebp($this->image, $file, $quality);
        }

        if ($type == 'avif') {
            /** @phpstan-ignore function.notFound */
            return imageavif($this->image, $file, $quality);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function doCrop(int $width, int $height, int $x, int $y): void
    {
        $cropped = static::createImage($width, $height);

        imagecopy(
            $cropped,
            $this->image,
            0,
            0,
            $x,
            $y,
            imagesx($this->image),
            imagesy($this->image),
        );

        $this->image = $cropped;
    }

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
    ): void {
        $resampled = static::createImage($width, $height, $background);

        imagecopyresampled(
            $resampled,
            $this->image,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $dstWidth,
            $dstHeight,
            $srcWidth,
            $srcHeight,
        );

        $this->image = $resampled;
    }

    /**
     * @inheritdoc
     */
    public function doResize(
        int $width,
        int $height,
        int $dstWidth,
        int $dstHeight,
        string $background = 'transparent'
    ): void {
        $this->doCopy(
            $width,
            $height,
            ($width - $dstWidth) / 2,
            ($height - $dstHeight) / 2,
            0,
            0,
            $dstWidth,
            $dstHeight,
            imagesx($this->image),
            imagesy($this->image),
            $background,
        );
    }

    /**
     * @inheritdoc
     */
    public function doRotate(int $angle, string $background = 'transparent'): void
    {
        $rotated = imagerotate($this->image, $angle, static::parseColor($background));

        $this->image = $rotated;
    }

    /**
     * Creates an image resource.
     *
     * @return resource|false
     */
    protected static function createImage(int $width, int $height, string $color = 'transparent')
    {
        $rgba = static::parseColor($color);
        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, $rgba);

        if ($color == 'transparent') {
            imagecolortransparent($image, $rgba);
        }

        return $image;
    }

    /**
     * Normalizes an image to be true color and transparent color.
     *
     * @param resource $image
     *
     * @return resource|false
     */
    protected static function normalizeImage($image)
    {
        if (imageistruecolor($image) && imagecolortransparent($image) == -1) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = static::createImage($width, $height);

        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        return $canvas;
    }
}
