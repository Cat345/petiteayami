<?php

namespace YOOtheme\Theme\Wordpress\Polylang\Listener;

class FilterMenuItems
{
    /**
     * Filters the sorted list of menu item objects before generating the menu's HTML.
     *
     * @param list<object> $sorted_menu_items
     *
     * @return list<object>
     */
    public static function handle(array $sorted_menu_items): array
    {
        if (!class_exists('Polylang', false)) {
            return $sorted_menu_items;
        }

        foreach ($sorted_menu_items as $item) {
            // set current
            $item->active = $item->active || in_array('current-lang', $item->classes);
        }

        return $sorted_menu_items;
    }
}
