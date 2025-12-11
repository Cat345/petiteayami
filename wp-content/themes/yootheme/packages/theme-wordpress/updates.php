<?php

use YOOtheme\Arr;

return [
    '3.0.1.1' => function ($config) {
        $sidebars = get_option('sidebars_widgets', []);
        Arr::set($sidebars, 'dialog-mobile', Arr::get($sidebars, 'dialog-mobile') ?: []);
        update_option('sidebars_widgets', $sidebars);

        return $config;
    },
    '3.0.0-beta.1.5' => function ($config) {
        global $wp_widget_factory;
        if ($wp_widget_factory->widgets) {
            foreach ($wp_widget_factory->widgets as $widget) {
                $settings = $widget->get_settings();
                foreach ($settings as $key => $value) {
                    if (!empty($value['_theme'])) {
                        $conf = json_decode($value['_theme'], true);
                        if (Arr::has($conf, 'menu_style')) {
                            Arr::updateKeys($conf, ['menu_style' => 'menu_type']);
                            $settings[$key]['_theme'] = json_encode($conf);
                        }
                    }
                }
                $widget->save_settings($settings);
            }
        }

        return $config;
    },
    '2.8.0-beta.0.4' => function ($config) {
        $locations = get_nav_menu_locations();
        Arr::set($locations, 'dialog-mobile', Arr::get($locations, 'mobile'));
        Arr::del($locations, 'mobile');
        set_theme_mod('nav_menu_locations', $locations);

        $sidebars = get_option('sidebars_widgets', []);
        Arr::set($sidebars, 'dialog-mobile', Arr::get($sidebars, 'mobile'));
        Arr::del($sidebars, 'mobile');
        update_option('sidebars_widgets', $sidebars);

        return $config;
    },
    '2.8.0-beta.0.1' => function ($config, array $params) {
        if (preg_match('/(offcanvas|modal)/', Arr::get($params['config'], 'header.layout'))) {
            $locations = get_nav_menu_locations();
            Arr::set($locations, 'dialog', Arr::get($locations, 'navbar'));
            Arr::del($locations, 'navbar');
            set_theme_mod('nav_menu_locations', $locations);

            $sidebars = get_option('sidebars_widgets', []);
            Arr::set($sidebars, 'dialog', Arr::get($sidebars, 'navbar'));
            Arr::del($sidebars, 'navbar');
            update_option('sidebars_widgets', $sidebars);
        }

        // Check child theme's "theme.js" for jQuery
        if (
            is_child_theme() &&
            !isset($config['jquery']) &&
            ($contents = @file_get_contents(get_stylesheet_directory() . '/js/theme.js')) &&
            str_contains($contents, 'jQuery')
        ) {
            $config['jquery'] = true;
        }

        return $config;
    },
    '1.20.0-beta.6' => function ($config) {
        // Deprecated Blog settings
        if (!Arr::has($config, 'post.image_margin')) {
            if (Arr::get($config, 'post.meta_align') == 'top') {
                Arr::set(
                    $config,
                    'post.meta_margin',
                    Arr::get($config, 'post.image_align') == 'top' ? 'large' : 'medium',
                );
            }

            if (Arr::get($config, 'post.meta_align') == 'bottom') {
                Arr::set(
                    $config,
                    'post.title_margin',
                    Arr::get($config, 'post.image_align') == 'top' ? 'large' : 'medium',
                );
            }

            if (Arr::get($config, 'post.content_width') === true) {
                Arr::set($config, 'post.content_width', 'small');
            }

            if (Arr::get($config, 'post.content_width') === false) {
                Arr::set($config, 'post.content_width', '');
            }

            if (Arr::get($config, 'post.header_align') === true) {
                Arr::set($config, 'blog.header_align', 1);
            }
        }

        return $config;
    },
];
