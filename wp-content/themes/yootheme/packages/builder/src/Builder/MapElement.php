<?php

namespace YOOtheme\Builder;

use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Image;
use YOOtheme\Metadata;
use YOOtheme\Theme\I18nConfig;
use YOOtheme\Theme\ThemeConfig;
use YOOtheme\Url;
use function YOOtheme\app;

class MapElement
{
    public static function render(object $node): void
    {
        // map options
        $node->options = Arr::pick($node->props, [
            'type',
            'zoom',
            'fit_bounds',
            'min_zoom',
            'max_zoom',
            'zooming',
            'dragging',
            'clustering',
            'controls',
            'poi',
            'styler_invert_lightness',
            'styler_hue',
            'styler_saturation',
            'styler_lightness',
            'styler_gamma',
            'popup_max_width',
        ]);
        $node->options['lazyload'] = true;
        $node->options +=
            static::getMarkerIcon($node) + // Default Marker Icon
            static::getMarkers($node) +
            static::getClusterIcons($node) +
            static::handleScripts($node);

        $node->options = array_filter($node->options, fn($value) => isset($value));

        /** @var Config $config */
        $config = app(Config::class);
        $node->props['consent'] = $config('~theme.consent.type') !== 'none';
        if (empty($node->props['consent_placeholder_image'])) {
            $node->props['consent_placeholder_image'] =
                '~assets/images/consent_placeholder_map.svg';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getMarkers(object $node): array
    {
        $markers = [];
        foreach ($node->children as $child) {
            @[$lat, $lng] = explode(',', $child->props['location'] ?? '');

            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $center ??= ['lat' => (float) $lat, 'lng' => (float) $lng];

            if (!empty($child->props['hide'])) {
                continue;
            }

            $marker =
                [
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'title' => $child->props['title'],
                ] + static::getMarkerIcon($child);

            if (!empty($child->props['show_popup'])) {
                $marker['show_popup'] = true;
            }

            $child->props['show'] = true;
            $markers[] = $marker;
        }

        return [
            'markers' => $markers,
            'center' => $center ?? ['lat' => 53.5503, 'lng' => 10.0006],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getClusterIcons(object $node): array
    {
        if (!$node->props['clustering']) {
            return [];
        }

        $icons = [];
        for ($i = 1; $i < 4; $i++) {
            $icon = $node->props["cluster_icon_{$i}"];

            if ($icon) {
                [$icon, $width, $height] = static::createIconImage(
                    $icon,
                    (int) $node->props["cluster_icon_{$i}_width"],
                    (int) $node->props["cluster_icon_{$i}_height"],
                );

                $icons[] = [
                    'url' => Url::to($icon),
                    'size' => $width && $height ? [$width, $height] : null,
                    'textColor' => $node->props["cluster_icon_{$i}_text_color"],
                ];
            }
        }

        return ['cluster_icons' => $icons];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function handleScripts(object $node): array
    {
        /**
         * @var I18nConfig $i18n
         * @var ThemeConfig $theme
         * @var Metadata $metadata
         */
        [$i18n, $theme, $metadata] = app(I18nConfig::class, ThemeConfig::class, Metadata::class);

        foreach ($theme->scripts as &$script) {
            if (!str_starts_with($script['type'], 'script-maps-')) {
                continue;
            }

            $script['active'] = true;

            $options = $script['options'] ?? [];
            $node->props += $script['element'] ?? [];

            $service = "{$script['category']}.{$script['service']}";
            $node->attrs['data-map-consent'] = $service;

            break;
        }

        $metadata->set('script:map', [
            'src' => '~assets/site/js/map.js',
            'type' => 'module',
        ]);

        $node->props['consent_icon'] ??= '~assets/images/consent_icon_openstreetmap.svg';
        $node->props['consent_content'] ??= $i18n->get('consent.text_openstreetmap');
        $node->props['consent_accept_button'] = $i18n->get('consent.button_accept');

        return $options ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getMarkerIcon(object $node): array
    {
        $icon = $node->props['marker_icon'];

        if (empty($icon)) {
            return [];
        }

        [$icon, $width, $height] = static::createIconImage(
            $icon,
            (int) $node->props['marker_icon_width'],
            (int) $node->props['marker_icon_height'],
        );

        return [
            'icon' => Url::to($icon),
            'iconSize' => $width && $height ? [$width, $height] : null,
            'iconAnchor' => $width && $height ? [$width / 2, $height] : null,
        ];
    }

    /**
     * @return ?list{Image|string, int|float, int|float}
     */
    protected static function createIconImage(string $icon, int $width, int $height): ?array
    {
        $image = Image::create($icon);

        if (!$image) {
            return [$icon, $width, $height];
        }

        if ($image->isResizable() && ($width || $height)) {
            $image = $image->thumbnail($width ?: '', $height ?: '');
        }

        if ($image->isType('svg')) {
            ['width' => $width, 'height' => $height] = $image->ratio([
                'width' => $width,
                'height' => $height,
            ]);
        } else {
            $width = $image->getWidth();
            $height = $image->getHeight();
        }

        return [$image, $width, $height];
    }
}
