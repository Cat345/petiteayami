<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\I18nConfig;

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

    /**
     * @param I18nConfig $config
     */
    public static function handleConfig($config): void
    {
        $config->merge([
            'consent' => [
                'button_accept' => __('Accept', 'yootheme'),
                'text_openstreetmap' => __(
                    'Display external content from OpenStreetMap.',
                    'yootheme',
                ),
                'text_google_maps' => __('Display external content from Google Maps.', 'yootheme'),
                'text_vimeo' => __('Display external content from Vimeo.', 'yootheme'),
                'text_youtube' => __('Display external content from YouTube.', 'yootheme'),
                'service_google_advertising' => __('Google Advertising', 'yootheme'),
                'service_google_analytics' => __('Google Analytics', 'yootheme'),
                'service_google_maps' => __('Google Maps', 'yootheme'),
                'service_openstreetmap' => __('OpenStreetMap', 'yootheme'),
                'service_vimeo' => __('Vimeo', 'yootheme'),
                'service_youtube' => __('YouTube', 'yootheme'),
            ],
        ]);
    }
}
