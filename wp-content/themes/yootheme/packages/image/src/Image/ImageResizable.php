<?php

namespace YOOtheme\Image;

use YOOtheme\Image;
use YOOtheme\Path;

/**
 * @phpstan-type ImageInfo array{int, int, string, array<string, mixed>}
 */
abstract class ImageResizable extends Image
{
    protected ?string $path;
    protected bool $resizable = true;
    protected int $quality = 80;

    /**
     * @var array<string, mixed>
     */
    protected array $info = [];

    public function __construct(string $file)
    {
        parent::__construct($file);

        $this->path = Path::resolve('~', $this->file);

        $info = static::getInfo($this->path);

        if ($info) {
            [$this->width, $this->height, $this->type, $this->info] = $info;
        } else {
            $this->resizable = false;
        }
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Convert the image.
     *
     * @return static
     */
    public function type(string $type, int $quality = 80): self
    {
        $image = clone $this;
        $image->type = $type;
        $image->quality = $quality;

        return $image;
    }

    /**
     * Crops the image.
     *
     * @param int|float|string $width
     * @param int|float|string $height
     * @param int|string $x
     * @param int|string $y
     *
     * @return static
     */
    public function crop($width = null, $height = null, $x = 'center', $y = 'center'): self
    {
        $image = clone $this;

        $image->width = $this->parseValue($width, $this->width);
        $image->height = $this->parseValue($height, $this->height);

        return $image;
    }

    /**
     * Resizes the image.
     *
     * @param int|float|string $width
     * @param int|float|string $height
     *
     * @return static
     */
    public function resize($width = null, $height = null, string $background = 'crop'): self
    {
        if ($background == 'cover') {
            return $this->crop($width, $height);
        }

        $image = clone $this;
        $width = $this->parseValue($width, $this->width);
        $height = $this->parseValue($height, $this->height);

        if ($background === 'crop') {
            $scale = max($this->width / $width, $this->height / $height);
            $width = $this->width / $scale;
            $height = $this->height / $scale;
        }

        $image->width = $width;
        $image->height = $height;

        return $image;
    }

    /**
     * Rotate the image.
     *
     * @return static
     */
    public function rotate(int $angle, string $background = 'transparent'): self
    {
        $image = clone $this;

        // update width/height for rotation
        if (in_array($angle, [90, 270], true)) {
            [$image->height, $image->width] = [$this->width, $this->height];
        }

        return $image;
    }

    /**
     * Flip the image.
     *
     * @return static
     */
    public function flip(bool $horizontal, bool $vertical): self
    {
        return clone $this;
    }

    /**
     * Thumbnail the image.
     *
     * @param int|float|string $width
     * @param int|float|string $height
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
        if ($flip) {
            $width = str_ends_with($width ?? '', '%')
                ? $this->parseValue($width, $this->width)
                : $width;
            $height = str_ends_with($height ?? '', '%')
                ? $this->parseValue($height, $this->height)
                : $height;

            if ($this->isPortrait() && $width > $height) {
                [$width, $height] = [$height, $width];
            } elseif ($this->isLandscape() && $height > $width) {
                [$width, $height] = [$height, $width];
            }
        }

        return is_numeric($width) && is_numeric($height)
            ? $this->crop($width, $height, $x, $y)
            : $this->resize($width, $height);
    }

    /**
     * Parses a percent value.
     *
     * @param mixed $value
     * @param int|float $baseValue
     */
    protected function parseValue($value, $baseValue): float
    {
        if (is_string($value) && str_ends_with($value, '%')) {
            $value = round($baseValue * (intval($value) / 100));
        }

        return floatval($value) ?: $baseValue;
    }

    /**
     * Gets the image info.
     *
     * @return ?ImageInfo
     */
    public static function getInfo(string $file): ?array
    {
        static $cache = [];

        if (isset($cache[$file])) {
            return $cache[$file];
        }

        if ($data = @getimagesize($file, $info)) {
            return $cache[$file] = [$data[0], $data[1], substr($data['mime'], 6), $info];
        }

        return null;
    }
}
