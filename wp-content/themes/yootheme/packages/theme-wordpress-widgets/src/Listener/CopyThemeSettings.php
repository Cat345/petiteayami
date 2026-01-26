<?php

namespace YOOtheme\Theme\Widgets\Listener;

class CopyThemeSettings
{
    /**
     * @link https://developer.wordpress.org/reference/hooks/widget_update_callback/
     *
     * @param array<string, mixed> $instance
     * @param array<string, mixed> $new_instance
     *
     * @return array<string, mixed>
     */
    public static function handle($instance, $new_instance)
    {
        if (isset($new_instance['_theme'])) {
            $instance['_theme'] = $new_instance['_theme'];
        }

        return $instance;
    }
}
