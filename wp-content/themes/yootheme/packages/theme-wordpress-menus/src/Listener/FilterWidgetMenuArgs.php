<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class FilterWidgetMenuArgs
{
    /**
     * Filters the arguments for the Navigation Menu widget.
     *
     * @link https://developer.wordpress.org/reference/hooks/widget_nav_menu_args/
     *
     * @param array<string, mixed> $nav_menu_args
     * @param array<string, mixed> $args
     * @param array<string, mixed> $instance
     *
     * @return array<string, mixed>
     */
    public static function handle(
        array $nav_menu_args,
        \WP_Term $nav_menu,
        array $args,
        array $instance
    ): array {
        $menuArgs = [];

        foreach ($instance['_theme'] ?? [] as $key => $value) {
            if (str_starts_with($key, 'menu_')) {
                $menuArgs[substr($key, 5)] = $value;
            }
            if (
                in_array($key, ['text_align', 'text_align_breakpoint', 'text_align_fallback'], true)
            ) {
                $menuArgs[$key] = $value;
            }
        }

        return $nav_menu_args + $menuArgs;
    }
}
