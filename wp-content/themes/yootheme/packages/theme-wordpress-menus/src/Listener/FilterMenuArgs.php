<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Theme\Wordpress\MenuWalker;
use function YOOtheme\app;

class FilterMenuArgs
{
    /**
     * Filters the arguments used to display a navigation menu.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_nav_menu_args/
     */
    public static function handle(array $args): array
    {
        return array_replace($args, [
            'walker' => app(MenuWalker::class),
            'container' => false,
            'fallback_cb' => false,
            'items_wrap' => '%3$s',
            'position' => get_current_sidebar(),
        ]);
    }
}
