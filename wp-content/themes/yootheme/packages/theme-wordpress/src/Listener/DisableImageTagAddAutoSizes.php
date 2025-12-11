<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class DisableImageTagAddAutoSizes
{
    /**
     * Adds 'auto' to the sizes attribute to the image, if the image is lazy loaded and does not already include it.
     *
     * Fix image resizing incorrectly when using `.webp` images
     *
     * @see hook wp_img_tag_add_auto_sizes
     */
    public static function handle(): bool
    {
        return false;
    }
}
