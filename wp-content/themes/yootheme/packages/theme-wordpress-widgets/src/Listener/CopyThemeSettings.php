<?php

namespace YOOtheme\Theme\Widgets\Listener;

class CopyThemeSettings
{
    public static function handle($instance, $new_instance)
    {
        if (isset($new_instance['_theme'])) {
            $instance['_theme'] = $new_instance['_theme'];
        }

        return $instance;
    }
}
