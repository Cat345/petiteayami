<?php

namespace YOOtheme;

return [
    '5.0.0-beta.0.14' => function ($config) {
        // Menu Positions
        if (Arr::has($config, 'menu.positions')) {
            $positions = Arr::get($config, 'menu.positions', []);
            foreach ($positions as &$position) {
                // Workaround for invalid configs (it seems update '3.0.0-beta.1.3' has not run properly everywhere)
                if (is_string($position)) {
                    continue;
                }

                if (($position['icon_width'] ?? '') && !($position['image_width'] ?? '')) {
                    $position['image_width'] = $position['icon_width'];
                }
                unset($position['icon_width']);
            }
            Arr::set($config, 'menu.positions', $positions);
        }

        return $config;
    },

    '5.0.0-beta.0.6' => function ($config) {
        Arr::del($config, 'navbar.dropdown_hide');

        return $config;
    },

    '4.5.5' => function ($config) {
        Arr::del($config, 'post.content_length');

        return $config;
    },

    '4.5.0-beta.0.5' => function ($config) {
        foreach (['header.', 'mobile.header.'] as $header) {
            $layout = Arr::pull($config, $header . 'search_mode') ?: (
                Arr::get($config, $header . 'search_results_dropbar')
                    ? 'input-dropbar'
                    : 'input-dropdown'
            );
            Arr::set($config, $header . 'search_layout', $layout);
            Arr::del($config, $header . 'search_results_dropbar');
        }

        return $config;
    },

    '4.5.0-beta.0.1' => function ($config) {
        foreach (['header.', 'mobile.header.'] as $header) {
            if (Arr::pull($config, $header . 'search_style') == 'modal') {
                Arr::set($config, $header . 'search_mode', 'modal');
            }
        }

        return $config;
    },

    '4.4.0-beta.0.3' => function ($config) {
        foreach (['top', 'bottom'] as $section) {
            if (
                Arr::get($config, "$section.vertical_align") &&
                !(
                    (Arr::get($config, "$section.height") == 'viewport' &&
                        Arr::get($config, "$section.height_viewport") <= 100) ||
                    Arr::get($config, "$section.height") == 'section'
                )
            ) {
                Arr::set($config, "$section.vertical_align", '');
            }
        }

        return $config;
    },

    '4.3.0-beta.0.5' => function ($config) {
        foreach (['top', 'bottom'] as $section) {
            if ($height = Arr::get($config, "$section.height")) {
                $rename = [
                    'full' => 'viewport',
                    'percent' => 'viewport',
                    'expand' => 'page',
                ];
                Arr::set($config, "$section.height", $rename[$height]);

                if ($height !== 'expand' && $section === 'top') {
                    Arr::set($config, "$section.height_offset_top", true);
                }

                if ($height === 'percent') {
                    Arr::set($config, "$section.height_viewport", 80);
                }
            }
        }

        return $config;
    },

    '4.3.0-beta.0.3' => function ($config) {
        if (Arr::get($config, 'header.transparent')) {
            Arr::set($config, 'header.transparent', true);
        }
        if (Arr::get($config, 'mobile.header.transparent')) {
            Arr::set($config, 'mobile.header.transparent', true);
        }
        if (Arr::get($config, 'top.header_transparent')) {
            if (
                Arr::get($config, 'top.text_color') !=
                    Arr::get($config, 'top.header_transparent') ||
                !(Arr::get($config, 'top.image') || Arr::get($config, 'top.video'))
            ) {
                Arr::set(
                    $config,
                    'top.header_transparent_text_color',
                    Arr::get($config, 'top.header_transparent'),
                );
            }
            Arr::set($config, 'top.header_transparent', true);
        }

        if (
            Arr::get($config, 'site.layout') === 'boxed' &&
            Arr::get($config, 'site.boxed.header_outside') &&
            !Arr::get($config, 'site.boxed.media') &&
            Arr::get($config, 'site.boxed.header_transparent')
        ) {
            Arr::updateKeys($config, [
                'site.boxed.header_transparent' => 'less.@theme-page-container-color-mode'
            ]);
        }

        if (Arr::get($config, 'site.boxed.header_transparent')) {
            Arr::set(
                $config,
                'site.boxed.header_text_color',
                Arr::get($config, 'site.boxed.header_transparent'),
            );
            Arr::set($config, 'site.boxed.header_transparent', true);
        }

        Arr::updateKeys($config, [
            'top.image_visibility' => 'top.media_visibility',
            'bottom.image_visibility' => 'bottom.media_visibility',
        ]);

        return $config;
    },

    '4.1.0-beta.0.2' => function ($config) {
        if (Arr::has($config, 'less.@internal-fonts')) {
            Arr::update(
                $config,
                'less.@internal-fonts',
                fn($fonts) => preg_replace('/&subset=[a-z,\s-]+/', '', $fonts),
            );
        }

        return $config;
    },
    '4.1.0-beta.0.1' => function ($config) {
        Arr::del($config, 'mobile.header.transparent');

        return $config;
    },
    '4.0.0-beta.11.1' => function ($config) {
        if (empty(Arr::get($config, 'footer.content.children'))) {
            Arr::del($config, 'footer.content');
        }

        return $config;
    },
    '3.1.0-beta.0.4' => function ($config) {
        Arr::updateKeys($config, [
            'header.social_links' => 'header.social_items',
            'mobile.header.social_links' => 'mobile.header.social_items',
        ]);

        return $config;
    },
    '3.1.0-beta.0.2' => function ($config) {
        foreach (['mobile.header', 'header'] as $header) {
            $links = array_map(
                fn($link) => ['link' => $link],
                array_filter((array) Arr::get($config, "{$header}.social_links", []))
            );

            if ($links) {
                Arr::set($config, "{$header}.social_links", $links);
            } else {
                Arr::del($config, "{$header}.social_links");
            }
        }

        return $config;
    },
    '3.0.1.1' => function ($config) {
        if (Arr::get($config, 'image_metadata')) {
            Arr::set($config, 'webp', false);
        }

        return $config;
    },
    '3.0.0-beta.3.2' => function ($config) {
        Arr::del($config, 'webp');

        return $config;
    },
    '3.0.0-beta.3.1' => function ($config) {
        if (
            Arr::get($config, 'site.image_effect') == 'parallax' &&
            !is_numeric(Arr::get($config, 'site.image_parallax_easing'))
        ) {
            Arr::set($config, 'site.image_parallax_easing', '1');
        }

        return $config;
    },
    '3.0.0-beta.1.8' => function ($config) {
        if (Arr::get($config, 'mobile.dialog.dropbar.animation') == 'slide') {
            Arr::set($config, 'mobile.dialog.dropbar.animation', 'reveal-top');
        }

        return $config;
    },
    '3.0.0-beta.1.7' => function ($config) {
        Arr::updateKeys($config, [
            'navbar.boundary_align' => 'navbar.dropdown_target',
            'mobile.dialog.dropdown.animation' => 'mobile.dialog.dropbar.animation',
        ]);

        if (Arr::get($config, 'mobile.dialog.layout') == 'dropdown-top') {
            Arr::set($config, 'mobile.dialog.layout', 'dropbar-top');
        }

        if (Arr::get($config, 'mobile.dialog.layout') == 'dropdown-center') {
            Arr::set($config, 'mobile.dialog.layout', 'dropbar-center');
        }

        // Menu Items
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                Arr::updateKeys($item, [
                    'dropdown.justify' => 'dropdown.stretch',
                ]);
                if (Arr::get($item, 'dropdown.stretch') == 'dropbar') {
                    Arr::set($item, 'dropdown.stretch', 'navbar-container');
                }
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },
    '3.0.0-beta.1.6' => function ($config) {
        // Menu Positions
        if (Arr::has($config, 'menu.positions')) {
            $positions = Arr::get($config, 'menu.positions', []);
            foreach ($positions as &$position) {
                if (empty($position['style'])) {
                    $position['style'] = 'default';
                }
            }
            Arr::set($config, 'menu.positions', $positions);
        }

        return $config;
    },
    '3.0.0-beta.1.5' => function ($config) {
        Arr::updateKeys($config, [
            'dialog.menu_style' => 'menu.positions.dialog.style',
            'dialog.menu_divider' => 'menu.positions.dialog.divider',
            'mobile.dialog.menu_style' => 'menu.positions.dialog-mobile.style',
            'mobile.dialog.menu_divider' => 'menu.positions.dialog-mobile.divider',
        ]);

        return $config;
    },
    '3.0.0-beta.1.4' => function ($config) {
        // Menu Items
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                unset($item['image_width']);
                unset($item['image_height']);
                unset($item['image_svg_inline']);
                unset($item['icon_width']);
                unset($item['image_margin']);
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },
    '3.0.0-beta.1.3' => function ($config) {
        Arr::update($config, 'menu.positions', function ($positions) {
            foreach ($positions ?: [] as $position => $menu) {
                $positions[$position] = isset($menu) ? ['menu' => $menu] : null;
            }
            return $positions;
        });

        return $config;
    },

    '2.8.0-beta.0.11' => function ($config) {
        Arr::del($config, 'mobile.dialog.dropdown.animation');
        Arr::del($config, 'mobile.dialog.dropdown');

        return $config;
    },

    '2.8.0-beta.0.10' => function ($config) {
        [$style] = explode(':', Arr::get($config, 'style'));
        if ($style == 'makai') {
            Arr::set($config, 'mobile.header.layout', 'horizontal-right');
        }

        return $config;
    },

    '2.8.0-beta.0.9' => function ($config) {
        if (Arr::get($config, 'mobile.dialog.layout') == 'dropdown') {
            Arr::set($config, 'mobile.dialog.layout', 'dropdown-top');
        }
        Arr::set($config, 'mobile.dialog.dropdown.animation', 'slide');
        Arr::del($config, 'mobile.dialog.dropdown');

        return $config;
    },

    '2.8.0-beta.0.8' => function ($config) {
        Arr::updateKeys($config, [
            'dialog.menu_center' => 'dialog.text_center',
            'mobile.dialog.menu_center' => 'mobile.dialog.text_center',
        ]);

        return $config;
    },

    '2.8.0-beta.0.7' => function ($config) {
        Arr::updateKeys($config, [
            'dialog.menu_center' => 'dialog.text_center',
            'mobile.dialog.menu_center' => 'mobile.dialog.text_center',
        ]);
        // Menu Items
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                if (Arr::get($item, 'dropdown.width') == 400) {
                    Arr::del($item, 'dropdown.width');
                }
                Arr::updateKeys($item, [
                    'image-margin' => 'image_margin',
                    'image-only' => 'image_only',
                ]);
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },

    '2.8.0-beta.0.5' => function ($config) {
        // Convert builder menu items from type 'layout' to 'fragment'
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                if (Arr::get($item, 'content')) {
                    Arr::set($item, 'content.type', 'fragment');
                }
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },
    '2.8.0-beta.0.4' => function ($config) {
        // Mobile Header
        Arr::set($config, 'mobile.header.layout',
            Arr::pull($config, 'mobile.logo') == 'left'
                ? (Arr::get($config, 'mobile.toggle') == 'left'
                    ? 'horizontal-left'
                    : 'horizontal-right'
                ) : 'horizontal-center-logo'
        );

        Arr::set($config, 'mobile.dialog.toggle',
            Arr::pull($config, 'mobile.toggle') == 'left'
                ? 'navbar-mobile:start'
                : 'header-mobile:end'
        );

        Arr::set(
            $config,
            'mobile.dialog.offcanvas.flip',
            Arr::pull($config, 'mobile.offcanvas.flip', false),
        );

        Arr::updateKeys($config, [
            // Mobile Header
            'mobile.logo_padding_remove' => 'mobile.header.logo_padding_remove',
            // Mobile Navbar
            'mobile.sticky' => 'mobile.navbar.sticky',
            // Mobile Dialog
            'mobile.animation' => function ($value) use (&$config) {
                $menu_center_vertical = Arr::pull($config, 'mobile.menu_center_vertical');
                switch ($value) {
                    case 'offcanvas':
                        return [
                            'mobile.dialog.layout' => $menu_center_vertical
                                ? 'offcanvas-center'
                                : 'offcanvas-top',
                        ];
                    case 'modal':
                        return [
                            'mobile.dialog.layout' => $menu_center_vertical
                                ? 'modal-center'
                                : 'modal-top',
                        ];
                    case 'dropdown':
                        return ['mobile.dialog.layout' => 'dropdown'];
                }
            },
            'mobile.toggle_text' => 'mobile.dialog.toggle_text',
            'mobile.close_button' => 'mobile.dialog.close',
            'mobile.menu_style' => 'mobile.dialog.menu_style',
            'mobile.menu_divider' => 'mobile.dialog.menu_divider',
            'mobile.menu_center' => 'mobile.dialog.menu_center',
            'mobile.offcanvas.mode' => 'mobile.dialog.offcanvas.mode',
            'mobile.dropdown' => 'mobile.dialog.dropdown',
            'social_links' => 'header.social_links',
        ]);

        // Mobile Search and Social settings
        foreach (['search', 'social'] as $key) {
            if (Arr::get($config, "header.{$key}")) {
                Arr::set($config, "mobile.header.{$key}", 'dialog-mobile:end');
            }
        }

        foreach (
            ['search_style', 'social_links', 'social_target', 'social_style', 'social_gap']
            as $key
        ) {
            if (Arr::has($config, "header.{$key}")) {
                Arr::set($config, "mobile.header.{$key}", Arr::get($config, "header.{$key}"));
            }
        }

        return $config;
    },
    '2.8.0-beta.0.3' => function ($config) {
        foreach (['bgx', 'bgy'] as $prop) {
            $key = "site.image_parallax_{$prop}";
            $start = preg_replace('/\s*,\s*/', ',', Arr::get($config, "{$key}_start", ''));
            $end = preg_replace('/\s*,\s*/', ',', Arr::get($config, "{$key}_end", ''));

            if ($start !== '' || $end !== '') {
                Arr::set(
                    $config,
                    $key,
                    implode(',', [$start !== '' ? $start : '0', $end !== '' ? $end : '0']),
                );
            }
            Arr::del($config, "{$key}_start");
            Arr::del($config, "{$key}_end");
        }

        return $config;
    },
    '2.8.0-beta.0.1' => function ($config) {
        // Stacked Center Split
        if (Arr::get($config, 'header.layout') == 'stacked-center-split') {
            Arr::set($config, 'header.layout', 'stacked-center-split-a');
        }
        // Stacked Left
        if (Arr::get($config, 'header.layout') == 'stacked-left-b') {
            Arr::set($config, 'header.push_index', 1);
        }
        if (in_array(Arr::get($config, 'header.layout'), ['stacked-left-a', 'stacked-left-b'])) {
            Arr::set($config, 'header.layout', 'stacked-left');
        }
        // Dialog Layout
        if (
            in_array(Arr::get($config, 'header.layout'), [
                'offcanvas-top-b',
                'offcanvas-center-b',
                'modal-top-b',
                'modal-center-b',
            ])
        ) {
            Arr::set($config, 'dialog.push_index', 1);
        }
        if (preg_match('/offcanvas-top/', Arr::get($config, 'header.layout'))) {
            Arr::set($config, 'dialog.layout', 'offcanvas-top');
        }
        if (preg_match('/offcanvas-center/', Arr::get($config, 'header.layout'))) {
            Arr::set($config, 'dialog.layout', 'offcanvas-center');
        }
        if (preg_match('/modal-top/', Arr::get($config, 'header.layout'))) {
            Arr::set($config, 'dialog.layout', 'modal-top');
        }
        if (preg_match('/modal-center/', Arr::get($config, 'header.layout'))) {
            Arr::set($config, 'dialog.layout', 'modal-center');
        }
        if (preg_match('/(offcanvas|modal)/', Arr::get($config, 'header.layout'))) {
            Arr::set($config, 'header.layout',
                Arr::pull($config, 'header.logo_center')
                    ? 'horizontal-center-logo'
                    : 'horizontal-left'
            );
        }
        // Dialog Options
        Arr::updateKeys($config, [
            'navbar.toggle_text' => 'dialog.toggle_text',
            'navbar.toggle_menu_style' => 'dialog.menu_style',
            'navbar.toggle_menu_divider' => 'dialog.menu_divider',
            'navbar.toggle_menu_center' => 'dialog.menu_center',
            'navbar.offcanvas.mode' => 'dialog.offcanvas.mode',
            'navbar.offcanvas.overlay' => 'dialog.offcanvas.overlay',
        ]);
        // Navbar
        if (Arr::get($config, 'navbar.dropbar')) {
            Arr::set($config, 'navbar.dropbar', true);
        }
        // Search
        if (Arr::get($config, 'header.search')) {
            Arr::set($config, 'header.search', Arr::get($config, 'header.search') . ':end');
        }
        // Social
        if (Arr::get($config, 'header.social') == 'toolbar-left') {
            Arr::set($config, 'header.social', 'toolbar-right:start');
        } elseif (Arr::get($config, 'header.social')) {
            Arr::update($config, 'header.social', fn($value) => "{$value}:end");
        }
        // Menu Items
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                if (Arr::pull($item, 'justify')) {
                    Arr::set($item, 'dropdown.justify', 'navbar');
                }
                if ($columns = Arr::pull($item, 'columns')) {
                    Arr::set($item, 'dropdown.columns', $columns);
                }
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },

    '2.5.0-beta.1.2' => function ($config) {
        if (Arr::has($config, 'menu.items')) {
            $items = Arr::get($config, 'menu.items', []);
            foreach ($items as &$item) {
                Arr::updateKeys($item, [
                    'icon' => 'image',
                    'icon-only' => 'image-only',
                ]);
            }
            Arr::set($config, 'menu.items', $items);
        }

        return $config;
    },

    '2.0.0-beta.5.1' => function ($config) {
        foreach (['blog.width', 'post.width', 'header.width'] as $prop) {
            if (Arr::get($config, $prop) == '') {
                Arr::set($config, $prop, 'default');
            }

            if (Arr::get($config, $prop) == 'none') {
                Arr::set($config, $prop, '');
            }
        }

        [$style] = explode(':', Arr::get($config, 'style'));

        foreach (
            [
                'site.toolbar_width',
                'header.width',
                'top.width',
                'bottom.width',
                'blog.width',
                'post.width',
            ]
            as $prop
        ) {
            if (!in_array($style, ['jack-baker', 'morgan-consulting', 'vibe'])) {
                if (Arr::get($config, $prop) == 'large') {
                    Arr::set($config, $prop, 'xlarge');
                }
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
                if (Arr::get($config, $prop) == 'default') {
                    Arr::set($config, $prop, 'large');
                }
            }
        }

        return $config;
    },

    '1.22.0-beta.0.1' => function ($config) {
        // Rename Top and Bottom options
        foreach (['top', 'bottom'] as $position) {
            $gutter = Arr::pull($config, "{$position}.grid_gutter");
            Arr::set($config, "{$position}.column_gap", $gutter);
            Arr::set($config, "{$position}.row_gap", $gutter);

            Arr::set(
                $config,
                "{$position}.divider",
                Arr::pull($config, "{$position}.grid_divider", ''),
            );
        }

        // Rename Blog options
        if (Arr::pull($config, 'blog.column_gutter')) {
            Arr::set($config, 'blog.grid_column_gap', 'large');
        }
        Arr::set($config, 'blog.grid_row_gap', 'large');
        Arr::set($config, 'blog.grid_breakpoint', Arr::pull($config, 'blog.column_breakpoint', 'm'));

        // Rename Sidebar options
        foreach (['width', 'breakpoint', 'first', 'gutter', 'divider'] as $prop) {
            Arr::updateKeys($config, ["sidebar.{$prop}" => "main_sidebar.{$prop}"]);
        }

        return $config;
    },

    '1.20.4.1' => function ($config) {
        Arr::updateKeys($config, [
            // Header settings
            'site.toolbar_fullwidth' => function ($value) {
                if ($value) {
                    return ['site.toolbar_width' => 'expand'];
                }
            },
        ]);

        return $config;
    },

    '1.20.0-beta.7' => function ($config) {
        // Remove empty menu items
        if (Arr::has($config, 'menu.items')) {
            Arr::set(
                $config,
                'menu.items',
                array_filter((array) Arr::get($config, 'menu.items', [])),
            );
        }

        return $config;
    },

    '1.20.0-beta.6' => function ($config) {
        Arr::updateKeys($config, [
            // Header settings
            'header.fullwidth' => function ($value) {
                if ($value) {
                    return ['header.width' => 'expand'];
                }
            },
        ]);

        if (Arr::get($config, 'header.layout') == 'toggle-offcanvas') {
            Arr::set($config, 'header.layout', 'offcanvas-top-a');
        }

        if (Arr::get($config, 'header.layout') == 'toggle-modal') {
            Arr::set($config, 'header.layout', 'modal-center-a');
            Arr::set($config, 'navbar.toggle_menu_style', 'primary');
            Arr::set($config, 'navbar.toggle_menu_center', true);
        }

        if (
            Arr::get($config, 'mobile.animation') == 'modal' &&
            !Arr::has($config, 'mobile.menu_center')
        ) {
            Arr::set($config, 'mobile.menu_style', 'primary');
            Arr::set($config, 'mobile.menu_center', true);
            Arr::set($config, 'mobile.menu_center_vertical', true);
        }

        if (
            Arr::get($config, 'site.boxed.padding') &&
            (!Arr::has($config, 'site.boxed.margin_top') ||
                !Arr::has($config, 'site.boxed.margin_bottom'))
        ) {
            Arr::set($config, 'site.boxed.margin_top', true);
            Arr::set($config, 'site.boxed.margin_bottom', true);
        }

        if (!Arr::has($config, 'cookie.mode') && Arr::get($config, 'cookie.active')) {
            Arr::set($config, 'cookie.mode', 'notification');
        }
        if (!Arr::has($config, 'cookie.button_consent_style')) {
            Arr::set(
                $config,
                'cookie.button_consent_style',
                Arr::get($config, 'cookie.button_style'),
            );
        }

        foreach (['top', 'bottom'] as $position) {
            if (Arr::get($config, "{$position}.vertical_align") === true) {
                Arr::set($config, "{$position}.vertical_align", 'middle');
            }

            if (Arr::get($config, "{$position}.style") === 'video') {
                Arr::set($config, "{$position}.style", 'default');
            }

            if (Arr::get($config, "{$position}.width") == '1') {
                Arr::set($config, "{$position}.width", 'default');
            }

            if (Arr::get($config, "{$position}.width") == '2') {
                Arr::set($config, "{$position}.width", 'small');
            }

            if (Arr::get($config, "{$position}.width") == '3') {
                Arr::set($config, "{$position}.width", 'expand');
            }
        }

        return $config;
    },
];
