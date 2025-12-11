<?php

namespace YOOtheme;

use YOOtheme\Theme\Widgets\WidgetsListener;

return [
    'transforms' => [
        'render' => function ($node) {
            global $wp_registered_widgets;

            /**
             * @var Config          $config
             * @var WidgetsListener $helper
             */
            [$config, $helper] = app(Config::class, WidgetsListener::class);

            $widget = $node->props['widget'] ?? null;
            $instance = $wp_registered_widgets[$widget] ?? null;
            $filter = fn($widgets) => in_array($widget, $widgets);

            if (
                !is_callable($instance['callback'] ?? null) ||
                !array_any(wp_get_sidebars_widgets(), $filter)
            ) {
                return false;
            }

            call_user_func(
                $instance['callback'],
                wp_parse_args($instance, [
                    'name' => '',
                    'id' => '',
                    'description' => '',
                    'class' => '',
                    'before_widget' => '<content>',
                    'after_widget' => '</content>',
                    'before_title' => '<title>',
                    'after_title' => '</title>',
                    'widget_id' => $instance['id'],
                    'widget_name' => $instance['name'],
                    'yoo_element' => $node,
                    '_theme' => $node->props,
                ]),
                $instance['params'][0],
            );

            $widget = !empty($helper->widgets[$helper->sidebar])
                ? array_pop($helper->widgets[$helper->sidebar])
                : null;

            if ($widget) {
                $node->widget = $widget;
                $node->attrs = Arr::merge($node->attrs, Arr::pick($widget->attrs, 'class'));
                $node->props = array_merge(
                    ['showtitle' => null],
                    $config("~theme.modules.{$widget->id}", []),
                    $node->props,
                );
            } else {
                return false;
            }
        },
    ],
];
