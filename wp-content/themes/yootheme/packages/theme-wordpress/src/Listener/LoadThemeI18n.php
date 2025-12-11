<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class LoadThemeI18n
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $this->config->add('theme.data.i18n', [
            'close' => ['label' => __('Close'), 'yootheme'],
            'totop' => ['label' => __('Back to top'), 'yootheme'],
            'marker' => ['label' => __('Open'), 'yootheme'],
            'navbarToggleIcon' => ['label' => __('Open menu'), 'yootheme'],
            'paginationPrevious' => ['label' => __('Previous page'), 'yootheme'],
            'paginationNext' => ['label' => __('Next page'), 'yootheme'],
            'searchIcon' => [
                'toggle' => __('Open Search', 'yootheme'),
                'submit' => __('Submit Search', 'yootheme'),
            ],
            'slider' => [
                'next' => __('Next slide', 'yootheme'),
                'previous' => __('Previous slide', 'yootheme'),
                'slideX' => __('Slide %s', 'yootheme'),
                'slideLabel' => __('%s of %s', 'yootheme'),
            ],
            'slideshow' => [
                'next' => __('Next slide', 'yootheme'),
                'previous' => __('Previous slide', 'yootheme'),
                'slideX' => __('Slide %s', 'yootheme'),
                'slideLabel' => __('%s of %s', 'yootheme'),
            ],
            'lightboxPanel' => [
                'next' => __('Next slide', 'yootheme'),
                'previous' => __('Previous slide', 'yootheme'),
                'slideLabel' => __('%s of %s', 'yootheme'),
                'close' => __('Close', 'yootheme'),
            ],
        ]);
    }
}
