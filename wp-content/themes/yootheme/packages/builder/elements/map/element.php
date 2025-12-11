<?php

namespace YOOtheme;

use YOOtheme\Http\Uri;
use YOOtheme\Theme\SvgHelper;

return [
    'transforms' => [
        'render' => function ($node) {
            /**
             * @var Config        $config
             * @var ImageProvider $image
             * @var View          $view
             */
            [$config, $image, $view] = app(Config::class, ImageProvider::class, View::class);

            $getIconOptions = function ($node) use ($image, $view) {
                if (empty($node->props['marker_icon'])) {
                    return [];
                }

                $icon = $node->props['marker_icon'];
                $width = (int) $node->props['marker_icon_width'];
                $height = (int) $node->props['marker_icon_height'];

                if ($imageObj = $image->create($icon, false)) {
                    if ($width || $height) {
                        $imageObj = $imageObj->thumbnail($width, $height);
                    }

                    $width = $imageObj->getWidth();
                    $height = $imageObj->getHeight();
                    $icon = $image->getUrl(
                        (string) (new Uri($icon))->withFragment("thumbnail={$width},{$height}"),
                    );
                } elseif ((!$width || !$height) && $view->isImage($icon) === 'svg') {
                    [$width, $height] = SvgHelper::getDimensions($icon, compact('width', 'height'));
                }

                $hasWidthAndHeight = $width && $height;

                return [
                    'icon' => $icon ? Url::to($icon) : false,
                    'iconSize' => $hasWidthAndHeight ? [$width, $height] : null,
                    'iconAnchor' => $hasWidthAndHeight ? [$width / 2, $height] : null,
                ];
            };

            $center = [];
            $node->options = [];
            foreach ($node->children as $child) {
                if (empty($child->props['location'])) {
                    continue;
                }

                @[$lat, $lng] = explode(',', $child->props['location']);

                if (!is_numeric($lat) || !is_numeric($lng)) {
                    continue;
                }

                if (empty($center)) {
                    $center = ['lat' => (float) $lat, 'lng' => (float) $lng];
                }

                if (!empty($child->props['hide'])) {
                    continue;
                }

                $options =
                    [
                        'lat' => (float) $lat,
                        'lng' => (float) $lng,
                        'title' => $child->props['title'],
                    ] + $getIconOptions($child);

                if (!empty($child->props['show_popup'])) {
                    $options['show_popup'] = true;
                }

                $child->props['show'] = true;
                $node->options['markers'][] = $options;
            }

            // map options
            $node->options += Arr::pick($node->props, [
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
            $node->options['center'] = $center ?: ['lat' => 53.5503, 'lng' => 10.0006];
            $node->options['lazyload'] = true;
            $node->options += $getIconOptions($node);

            if ($node->props['clustering']) {
                for ($i = 1; $i < 4; $i++) {
                    $icon = $node->props["cluster_icon_{$i}"];
                    $width = (int) $node->props["cluster_icon_{$i}_width"];
                    $height = (int) $node->props["cluster_icon_{$i}_height"];
                    $textColor = $node->props["cluster_icon_{$i}_text_color"];

                    if ($icon) {
                        if ($imageObj = $image->create($icon, false)) {
                            if ($width || $height) {
                                $imageObj = $imageObj->thumbnail($width, $height);
                            }

                            $width = $imageObj->getWidth();
                            $height = $imageObj->getHeight();
                            $icon = $image->getUrl("{$icon}#thumbnail={$width},{$height}");
                        }

                        $node->options['cluster_icons'][] = [
                            'url' => Url::to($icon),
                            'size' => $width && $height ? [$width, $height] : null,
                            'textColor' => $textColor,
                        ];
                    }
                }
            }

            $node->options = array_filter($node->options, fn($value) => isset($value));

            $node->props['metadata'] = [];

            // add scripts, styles
            if ($key = $config('~theme.google_maps')) {
                $node->options['library'] = 'google';

                $node->options['apiKey'] = $key;
                $node->props['metadata']['script:builder-map'] = [
                    'src' => Path::get('./app/map-google.min.js', __DIR__),
                    'defer' => true,
                ];
                if ($node->props['clustering']) {
                    $node->options['clustererUrl'] = Url::to(
                        Path::get('./app/map-google-markerclusterer.min.js', __DIR__),
                    );
                }
            } else {
                $node->options['library'] = 'leaflet';

                $baseUrl = '~assets/leaflet/leaflet/dist';
                $node->options['baseUrl'] = Url::to($baseUrl);
                $node->props['metadata']['script:leaflet'] = [
                    'src' => "{$baseUrl}/leaflet.js",
                    'defer' => true,
                ];
                $node->props['metadata']['style:leaflet'] = [
                    'href' => "{$baseUrl}/leaflet.css",
                    'defer' => true,
                ];

                if ($node->props['clustering']) {
                    $baseUrl = '~assets/leaflet/markercluster/dist';
                    $node->options['clusterBaseUrl'] = $baseUrl;
                    $node->props['metadata'] += [
                        'script:leaflet-clusterer' => [
                            'src' => "{$baseUrl}/leaflet.markercluster.js",
                            'defer' => true,
                        ],
                        'style:leaflet-clusterer' => [
                            'href' => "{$baseUrl}/MarkerCluster.css",
                            'defer' => true,
                        ],
                        'style:leaflet-clusterer-default' => [
                            'href' => "{$baseUrl}/MarkerCluster.Default.css",
                            'defer' => true,
                        ],
                    ];
                }

                $node->props['metadata']['script:builder-map'] = [
                    'src' => Path::get('./app/map-leaflet.min.js', __DIR__),
                    'defer' => true,
                ];
            }
        },
    ],
];
