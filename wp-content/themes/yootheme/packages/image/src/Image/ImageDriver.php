<?php

namespace YOOtheme\Image;

use YOOtheme\Image\Driver\DriverInterface;
use YOOtheme\Image\Driver\GdDriver;
use YOOtheme\Image\Driver\NoopDriver;

class ImageDriver extends ImageResizable
{
    protected DriverInterface $driver;

    final public function __construct(string $file)
    {
        parent::__construct($file);

        if (!$this->type) {
            return;
        }

        $this->driver = extension_loaded('gd')
            ? new GdDriver($this->path, $this->type)
            : new NoopDriver($this->path, $this->type);
    }

    public static function fromFile(string $file): ?self
    {
        $image = new static($file);
        return $image->getType() ? $image : null;
    }

    public function __clone()
    {
        $this->driver = clone $this->driver;
    }

    /**
     * @inheritdoc
     */
    public function crop($width = null, $height = null, $x = 'center', $y = 'center'): self
    {
        $ratio = $this->width / $this->height;
        $width = $this->parseValue($width, $this->width);
        $height = $this->parseValue($height, $this->height);

        if ($ratio > $width / $height) {
            $image = $this->resize(round($height * $ratio), $height);
        } else {
            $image = $this->resize($width, round($width / $ratio));
        }

        if ($x === 'left') {
            $x = 0;
        } elseif ($x === 'right') {
            $x = $image->width - $width;
        } elseif ($x === 'center') {
            $x = ($image->width - $width) / 2;
        }

        if ($y === 'top') {
            $y = 0;
        } elseif ($y === 'bottom') {
            $y = $image->height - $height;
        } elseif ($y === 'center') {
            $y = ($image->height - $height) / 2;
        }

        $image->driver->doCrop(
            $image->width = (int) $width,
            $image->height = (int) $height,
            (int) $x,
            (int) $y,
        );

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function resize($width = null, $height = null, string $background = 'crop'): self
    {
        if ($background == 'cover') {
            return $this->crop($width, $height);
        }

        $image = parent::resize($width, $height, $background);

        if (in_array($background, ['fill', 'crop'], true)) {
            $image->driver->doResize(
                (int) $image->getWidth(),
                (int) $image->getHeight(),
                (int) $image->getWidth(),
                (int) $image->getHeight(),
            );
        } else {
            $cropped = parent::resize($width, $height);
            $image->driver->doResize(
                $image->width = (int) $width,
                $image->height = (int) $height,
                (int) $cropped->getWidth(),
                (int) $cropped->getHeight(),
                $background,
            );
        }

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function rotate(int $angle, string $background = 'transparent'): self
    {
        $image = parent::rotate($angle, $background);

        $image->driver->doRotate($angle, $background);

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function flip(bool $horizontal, bool $vertical): self
    {
        $srcX = $horizontal ? $this->width - 1 : 0;
        $srcY = $vertical ? $this->height - 1 : 0;
        $srcW = $horizontal ? -$this->width : $this->width;
        $srcH = $vertical ? -$this->height : $this->height;

        $image = parent::flip($horizontal, $vertical);
        $image->driver->doCopy(
            (int) $this->width,
            (int) $this->height,
            0,
            0,
            (int) $srcX,
            (int) $srcY,
            (int) $this->width,
            (int) $this->height,
            (int) $srcW,
            (int) $srcH,
        );

        return $image;
    }

    /**
     * Saves the image.
     *
     * @param string|resource $file
     * @param array<string, mixed>  $info
     */
    public function save($file, ?string $type = null, ?int $quality = null, array $info = []): bool
    {
        if (!$type) {
            $type = $this->type;
        }

        if (!$quality) {
            $quality = $this->quality;
        }

        if (!$info) {
            $info = $this->info;
        }

        return $this->driver->save($file, $type, $quality, $info);
    }
}
