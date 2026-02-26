<?php

namespace YOOtheme;

use Stringable;
use YOOtheme\Image\ImageGif;
use YOOtheme\Image\ImageQuery;
use YOOtheme\Image\ImageStockPhoto;
use YOOtheme\Image\ImageSvg;
use YOOtheme\Image\ImageVimeo;
use YOOtheme\Image\ImageYoutube;

class Image implements Stringable
{
    public string $file;

    protected ?string $type = null;

    /**
     * @var int|float|null
     */
    protected $width = null;

    /**
     * @var int|float|null
     */
    protected $height = null;

    protected bool $remote = false;
    protected bool $resizable = false;

    public function __construct(string $file)
    {
        $this->file = strtr($file, '\\', '/');
    }

    /**
     * Gets image as file string.
     */
    public function __toString(): string
    {
        return $this->file;
    }

    /**
     * @return ImageGif|ImageQuery|ImageStockPhoto|ImageSvg|ImageYoutube|ImageVimeo|null
     */
    public static function create(string $file): ?self
    {
        return Event::emit('image.create|middleware', fn($image) => null, $file);
    }

    public function isRemote(): bool
    {
        return $this->remote;
    }

    public function isResizable(): bool
    {
        return $this->resizable;
    }

    /**
     * Gets the type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Gets the height.
     */
    public function getWidth(int $precision = 0): ?float
    {
        return isset($this->width) ? round($this->width, $precision) : $this->width;
    }

    /**
     * Gets the width.
     */
    public function getHeight(int $precision = 0): ?float
    {
        return isset($this->height) ? round($this->height, $precision) : $this->height;
    }

    /**
     * Checks if image is of type.
     */
    public function isType(string ...$types): bool
    {
        return in_array($this->getType(), array_map('strtolower', $types), true);
    }

    /**
     * Checks the portrait orientation.
     */
    public function isPortrait(): bool
    {
        return $this->getHeight() > $this->getWidth();
    }

    /**
     * Checks the landscape orientation.
     */
    public function isLandscape(): bool
    {
        return $this->getWidth() >= $this->getHeight();
    }
}
