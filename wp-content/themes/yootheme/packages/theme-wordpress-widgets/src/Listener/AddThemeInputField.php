<?php

namespace YOOtheme\Theme\Widgets\Listener;

use WP_Widget;

class AddThemeInputField
{
    /**
     * @link https://developer.wordpress.org/reference/hooks/in_widget_form/
     *
     * @param WP_Widget $widget
     * @param null       $return
     * @param array<string, mixed> $instance
     */
    public static function handle($widget, $return, $instance): void
    {
        echo sprintf(
            '<input type="hidden" name="%s" value="%s" data-widget>',
            $widget->get_field_name('_theme'),
            esc_attr($instance['_theme'] ?? '{}'),
        );
    }
}
