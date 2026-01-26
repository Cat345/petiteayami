<?php

namespace YOOtheme\Image;

use YOOtheme\Image;

class ImageYoutube extends Image
{
    protected bool $remote = true;

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        $cacheKey = hash(PHP_VERSION_ID < 80100 ? 'md5' : 'xxh32', $this->file);

        return "image:/{$this->file}?cachekey={$cacheKey}";
    }
}
