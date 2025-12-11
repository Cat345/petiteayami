<?php

namespace YOOtheme\Theme\Widgets\Listener;

use WP_Widget;

class AddThemeInputField
{
    /**
     * @param WP_Widget $widget
     * @param null       $return
     * @param array      $instance
     */
    public static function handle($widget, $return, $instance)
    {
        echo sprintf(
            '<input type="hidden" name="%s" value="%s" data-widget>',
            $widget->get_field_name('_theme'),
            esc_attr($instance['_theme'] ?? '{}'),
        );
    }
}
