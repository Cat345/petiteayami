<?php

namespace YOOtheme\Theme;

use YOOtheme\Config;
use YOOtheme\Image;

class ImageLoader
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $convert = [];

    /**
     * Constructor.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        // supports image avif?
        if ($config('~theme.avif') && function_exists('imageavif') && PHP_VERSION_ID >= 80100) {
            $png = intval($config('~theme.image_quality_png_avif'));
            $jpg = intval($config('~theme.image_quality_jpg_avif'));

            $this->convert['png']['image/avif'] = 'avif,' . ($png ?: 85);
            $this->convert['jpeg']['image/avif'] = 'avif,' . ($jpg ?: 75);
        }

        // supports image webp?
        if ($config('~theme.webp') && function_exists('imagewebp')) {
            $png = intval($config('~theme.image_quality_png_webp'));
            $jpg = intval($config('~theme.image_quality_jpg_webp'));

            $this->convert['png']['image/webp'] = 'webp,' . ($png ?: 100);
            $this->convert['jpeg']['image/webp'] = 'webp,' . ($jpg ?: 85);
        }
    }

    public function __invoke(Image $image)
    {
        $params = $image->getAttribute('params', []);

        // convert type
        if (isset($this->convert[$image->type])) {
            $image->setAttribute('types', $this->convert[$image->type]);
        }

        // image quality
        if ($quality = intval($this->config->get('~theme.image_quality_jpg'))) {
            $image->quality = $quality;
        }

        // image covers
        if (!empty($params['covers']) && !isset($params['sizes'])) {
            $img = $image->apply($params);
            if ($img->width && $img->height) {
                $ratio = round(($img->width / $img->height) * 100);
                $params['sizes'] = "(max-aspect-ratio: {$img->width}/{$img->height}) {$ratio}vh";
            }
        }

        // set default srcset
        if (($params['srcset'] ?? '') === '1') {
            $params['srcset'] = '768,1024,1366,1600,1920,200%';
        }

        $image->setAttribute('params', $params);
    }
}
