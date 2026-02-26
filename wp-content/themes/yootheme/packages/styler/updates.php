<?php

namespace YOOtheme;

return [
    '5.0.15' => function ($config) {
        Arr::del($config, 'less.@search-navbar-border-mode');

        return $config;
    },

    '5.0.14' => function ($config) {
        Arr::del($config, 'less.@slider-container-margin-top');
        Arr::del($config, 'less.@slider-container-margin-bottom');

        return $config;
    },

    '5.0.0-beta.9.1' => function ($config) {
        Arr::updateKeys($config, [
            'less.@form-padding-vertical' => 'less.@form-multi-line-padding-vertical',
        ]);

        return $config;
    },

    '5.0.0-beta.8.2' => function ($config) {
        foreach ($config['less'] ?? [] as $key => $value) {
            $newKey = preg_replace('/^@(inverse-)?accordion-/', '$0default-', $key);
            if ($newKey !== $key) {
                $config['less'][$newKey] = $config['less'][$key];
                unset($config['less'][$key]);
            }
        }

        return $config;
    },

    '5.0.0-beta.0.12' => function ($config) {
        foreach (
            [
                'less.@countdown-number-line-height',
                'less.@countdown-number-font-size',
                'less.@countdown-number-font-size-s',
                'less.@countdown-number-font-size-m',
                'less.@countdown-separator-line-height',
                'less.@countdown-separator-font-size',
                'less.@countdown-separator-font-size-s',
                'less.@countdown-separator-font-size-m',

                'less.@countdown-item-color',
                'less.@countdown-item-font-family',
                'less.@countdown-item-font-weight',
                'less.@countdown-item-text-transform',
                'less.@countdown-item-letter-spacing',
                'less.@countdown-item-font-style',
                'less.@countdown-label-font-size',
                'less.@countdown-label-font-size-s',
                'less.@countdown-label-font-size-m',
                'less.@countdown-label-color',
                'less.@countdown-label-font-family',
                'less.@countdown-label-font-weight',
                'less.@countdown-label-text-transform',
                'less.@countdown-label-letter-spacing',
                'less.@countdown-label-letter-spacing-s',
                'less.@countdown-label-font-style',

                'less.@inverse-countdown-item-color',
                'less.@inverse-countdown-label-color',
            ]
            as $key
        ) {
            Arr::del($config, $key);
        }

        return $config;
    },

    '5.0.0-beta.0.5' => function ($config) {
        // Less
        if (Arr::get($config, 'less.@navbar-mode') === 'border') {
            Arr::del($config, 'less.@navbar-mode');
        }
        if (Arr::get($config, 'less.@navbar-mode') === 'border-always') {
            Arr::set($config, 'less.@navbar-mode', 'bottom');
        }
        if (Arr::get($config, 'less.@navbar-mode') === 'rail') {
            Arr::set($config, 'less.@navbar-mode', 'top-bottom');
        }
        if (Arr::get($config, 'less.@navbar-mode') === 'frame') {
            Arr::set($config, 'less.@navbar-mode', 'full');
        }
        Arr::updateKeys($config, [
            'less.@navbar-mode' => 'less.@navbar-border-mode',
            'less.@navbar-mode-border-vertical' => 'less.@navbar-border-vertical-mode',
        ]);

        return $config;
    },

    '5.0.0-beta.0.1' => function ($config) {
        Arr::updateKeys($config, [
            'less.@accordion-title-background' => 'less.@accordion-item-background',
            'less.@tab-item-mode' => 'less.@tab-mode',
        ]);

        return $config;
    },

    '4.5.0-beta.0.8' => function ($config) {
        Arr::del($config, 'less.@accordion-item-padding-top');

        return $config;
    },

    '4.5.0-beta.0.2' => function ($config) {
        Arr::updateKeys($config, [
            'less.@lightbox-item-color' => 'less.@lightbox-color',
            'less.@lightbox-toolbar-padding-vertical' => 'less.@lightbox-caption-padding-vertical',
            'less.@lightbox-toolbar-padding-horizontal' => 'less.@lightbox-caption-padding-horizontal',
            'less.@lightbox-toolbar-background' => 'less.@lightbox-caption-background',
            'less.@lightbox-toolbar-color' => 'less.@lightbox-caption-color',
        ]);
        foreach (
            [
                'less.@lightbox-toolbar-icon-padding',
                'less.@lightbox-toolbar-icon-color',
                'less.@lightbox-toolbar-icon-hover-color',
                'less.@lightbox-button-size',
                'less.@lightbox-button-background',
                'less.@lightbox-button-color',
                'less.@lightbox-button-hover-color',
                'less.@lightbox-button-active-color',
                'less.@lightbox-button-hover-background',
                'less.@lightbox-button-active-background',
                'less.@lightbox-button-border-width',
                'less.@lightbox-button-border',
                'less.@lightbox-button-hover-border',
                'less.@lightbox-button-active-border',
            ]
            as $key
        ) {
            Arr::del($config, $key);
        }

        return $config;
    },

    '4.4.0-beta.5' => function ($config) {
        Arr::updateKeys($config, [
            'less.@search-navbar-width' => 'less.@search-medium-width',
            'less.@search-navbar-height' => 'less.@search-medium-height',
            'less.@search-navbar-background' => 'less.@search-medium-background',
            'less.@search-navbar-font-size' => 'less.@search-medium-font-size',
            'less.@search-navbar-icon-width' => 'less.@search-medium-icon-width',
            'less.@search-navbar-icon-padding' => 'less.@search-medium-icon-padding',
            'less.@inverse-search-navbar-background' => 'less.@inverse-search-medium-background',
            'less.@search-navbar-backdrop-filter' => 'less.@search-medium-backdrop-filter',
            'less.@search-navbar-focus-background' => 'less.@search-medium-focus-background',
            'less.@inverse-search-navbar-focus-background' => 'less.@inverse-search-medium-focus-background',
            'less.@search-navbar-border-mode' => 'less.@search-medium-border-mode',
            'less.@search-navbar-border-width' => 'less.@search-medium-border-width',
            'less.@search-navbar-border' => 'less.@search-medium-border',
            'less.@search-navbar-focus-border' => 'less.@search-medium-focus-border',
            'less.@inverse-search-navbar-border' => 'less.@inverse-search-medium-border',
            'less.@inverse-search-navbar-focus-border' => 'less.@inverse-search-medium-focus-border',
            'less.@search-navbar-border-radius' => 'less.@search-medium-border-radius',
            'less.@search-navbar-input-box-shadow' => 'less.@search-medium-input-box-shadow',
            'less.@search-navbar-input-focus-box-shadow' => 'less.@search-medium-input-focus-box-shadow',
            'less.@inverse-search-navbar-input-box-shadow' => 'less.@inverse-search-medium-input-box-shadow',
            'less.@inverse-search-navbar-input-focus-box-shadow' => 'less.@inverse-search-medium-input-focus-box-shadow',
        ]);

        return $config;
    },

    '4.3.0-beta.0.1' => function ($config) {
        // Less
        if (Arr::get($config, 'less.@button-text-mode') === 'arrow') {
            Arr::set($config, 'less.@button-text-icon-mode', 'arrow');
            Arr::set($config, 'less.@button-text-mode', '');
        }
        if (Arr::get($config, 'less.@button-text-mode') === 'em-dash') {
            Arr::set($config, 'less.@button-text-icon-mode', 'dash');
            Arr::set($config, 'less.@button-text-mode', '');
        }

        Arr::updateKeys($config, [
            'less.@internal-button-text-em-dash-padding' =>
                'less.@internal-button-text-dash-padding',
            'less.@internal-button-text-em-dash-size' => 'less.@internal-button-text-dash-size',
        ]);

        return $config;
    },

    '4.1.0-beta.0.3' => function ($config) {
        $height = Arr::pull($config, 'less.@pagination-item-line-height', '');
        if (str_ends_with($height, 'px')) {
            Arr::set($config, 'less.@pagination-item-height', $height);
        }

        return $config;
    },

    '3.0.0-beta.1.1' => function ($config) {
        // Less
        Arr::updateKeys($config, [
            'less.@navbar-dropdown-dropbar-margin-top' =>
                'less.@navbar-dropdown-dropbar-padding-top',
            'less.@navbar-dropdown-dropbar-margin-bottom' =>
                'less.@navbar-dropdown-dropbar-padding-bottom',
        ]);

        return $config;
    },

    '2.8.0-beta.0.14' => function ($config) {
        // Less
        Arr::updateKeys($config, [
            'less.@nav-primary-item-font-size' => 'less.@nav-primary-font-size',
            'less.@nav-primary-item-line-height' => 'less.@nav-primary-line-height',
        ]);

        return $config;
    },

    '2.8.0-beta.0.6' => function ($config) {
        // Less
        Arr::updateKeys($config, [
            'less.@offcanvas-bar-width-m' => 'less.@offcanvas-bar-width-s',
            'less.@offcanvas-bar-padding-vertical-m' => 'less.@offcanvas-bar-padding-vertical-s',
            'less.@offcanvas-bar-padding-horizontal-m' =>
                'less.@offcanvas-bar-padding-horizontal-s',
        ]);

        return $config;
    },

    '2.7.15.1' => function ($config) {
        // Less
        if (Arr::get($config, 'less.@navbar-mode-border-vertical') === 'true') {
            Arr::set($config, 'less.@navbar-mode-border-vertical', 'partial');
        }

        return $config;
    },

    '2.4.14' => function ($config) {
        // Less
        if (Arr::get($config, 'less.@navbar-mode') === 'border') {
            Arr::set($config, 'less.@navbar-mode', 'border-always');
        }

        if (Arr::get($config, 'less.@navbar-nav-item-line-slide-mode') === 'false') {
            Arr::set($config, 'less.@navbar-nav-item-line-slide-mode', 'left');
        }

        return $config;
    },

    '2.1.0-beta.0.1' => function ($config) {
        // Less
        Arr::updateKeys($config, [
            'less.@width-xxlarge-width' => 'less.@width-2xlarge-width',
            'less.@global-xxlarge-font-size' => 'less.@global-2xlarge-font-size',
        ]);

        return $config;
    },

    '2.0.11.1' => function ($config) {
        $style = Arr::get($config, 'style');

        $mapping = [
            'framerate:dark-blue' => 'framerate:black-blue',
            'framerate:dark-lightblue' => 'framerate:dark-blue',
            'joline:black-pink' => 'joline:dark-pink',
            'max:black-black' => 'max:dark-black',
        ];

        if (array_key_exists($style, $mapping)) {
            $config['style'] = $mapping[$style];
        }

        return $config;
    },

    '2.0.8.1' => function ($config) {
        $style = Arr::get($config, 'style');

        $mapping = [
            'copper-hill:white-turquoise' => 'copper-hill:light-turquoise',
            'florence:white-lilac' => 'florence:white-beige',
            'pinewood-lake:white-green' => 'pinewood-lake:light-green',
            'pinewood-lake:white-petrol' => 'pinewood-lake:light-petrol',
        ];

        if (array_key_exists($style, $mapping)) {
            $config['style'] = $mapping[$style];
        }

        return $config;
    },

    '2.0.0-beta.5.1' => function ($config) {
         [$style] = explode(':', Arr::get($config, 'style'));

        // Less
        if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
            Arr::updateKeys($config, [
                'less.@container-large-max-width' => 'less.@container-xlarge-max-width',
            ]);
        }

        if (
            in_array($style, [
                'craft',
                'district',
                'florence',
                'makai',
                'matthew-taylor',
                'pinewood-lake',
                'summit',
                'tomsen-brody',
                'trek',
                'vision',
                'yard',
            ])
        ) {
            Arr::updateKeys($config, [
                'less.@container-max-width' => 'less.@container-large-max-width',
            ]);
        }

        return $config;
    },

    '1.20.4.1' => function ($config) {
        Arr::updateKeys($config, [
            // Less
            'less.@theme-toolbar-padding-vertical' => fn($value) => [
                'less.@theme-toolbar-padding-top' => $value,
                'less.@theme-toolbar-padding-bottom' => $value,
            ],
        ]);

        return $config;
    },

    '1.20.0-beta.6' => function ($config) {
        foreach (Arr::get($config, 'less', []) as $key => $value) {
            if (
                in_array($key, [
                    '@heading-primary-line-height',
                    '@heading-hero-line-height-m',
                    '@heading-hero-line-height',
                ])
            ) {
                Arr::del($config, "less.{$key}");
            } elseif (preg_match('/heading-(primary|hero)-/', $key)) {
                Arr::set(
                    $config,
                    'less.' .
                    strtr($key, [
                        'heading-primary-line-height-l' => 'heading-medium-line-height',
                        'heading-primary-' => 'heading-medium-',
                        'heading-hero-line-height-l' => 'heading-xlarge-line-height',
                        'heading-hero-' => 'heading-xlarge-',
                    ]),
                    $value,
                );
                Arr::del($config, "less.{$key}");
            }
        }

        [$style] = explode(':', Arr::get($config, 'style', ''));

        $less = Arr::get($config, 'less', []);

        foreach (
            [
                [
                    ['fuse', 'horizon', 'joline', 'juno', 'lilian', 'vibe', 'yard'],
                    ['medium', 'small'],
                ],
                [['trek', 'fjord'], ['medium', 'large']],
                [['juno', 'vibe', 'yard'], ['xlarge', 'medium']],
                [
                    ['district', 'florence', 'flow', 'nioh-studio', 'summit', 'vision'],
                    ['xlarge', 'large'],
                ],
                [['lilian'], ['xlarge', '2xlarge']],
            ]
            as $change
        ) {
            [$styles, $transform] = $change;

            if (in_array($style, $styles)) {
                foreach ($less as $key => $value) {
                    if (str_contains($key, "heading-{$transform[0]}")) {
                        Arr::set(
                            $config,
                            'less.' .
                            str_replace(
                                "heading-{$transform[0]}",
                                "heading-{$transform[1]}",
                                $key,
                            ),
                            $value,
                        );
                    }
                }
            }
        }

        return $config;
    },
];
