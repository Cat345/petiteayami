<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class FilterMenuItems
{
    /**
     * Filters the sorted list of menu item objects before generating the menu's HTML.
     */
    public static function handle(array $sorted_menu_items): array
    {
        $active = false;

        foreach ($sorted_menu_items as $item) {
            $classes = empty($item->classes) ? [] : (array) $item->classes;

            // Unset active class for posts_page if currently on none blog page
            if (
                in_array('current_page_parent', $classes) &&
                $item->object_id == get_option('page_for_posts') &&
                !is_singular('post') &&
                !is_category() &&
                !is_tag() &&
                !is_date() &&
                get_query_var('post_type') !== 'post'
            ) {
                unset($classes[array_search('current_page_parent', $classes)]);
            }

            $item->classes = $classes;

            // set current
            $item->active =
                !empty($item->active) ||
                (is_page() && in_array($item->object_id, get_post_ancestors(get_the_ID()))) ||
                preg_match(
                    '/\bcurrent-([a-z]+-ancestor|menu-(item|parent))\b/',
                    implode(' ', $item->classes),
                );

            if ($item->active) {
                static::setParentItemActive($sorted_menu_items, $item);
            }

            $active = $active || $item->active;
        }

        if (!$active) {
            foreach ($sorted_menu_items as $item) {
                $item->active = preg_match(
                    '/\bcurrent_page_(item|parent)\b/',
                    implode(' ', $item->classes),
                );

                if ($item->active) {
                    static::setParentItemActive($sorted_menu_items, $item);
                }
            }
        }

        return $sorted_menu_items;
    }

    protected static function setParentItemActive($items, $item)
    {
        $current = $item;

        while (
            $parent = array_find(
                $items,
                fn($item) => $item->ID === (int) $current->menu_item_parent,
            )
        ) {
            $parent->active = true;
            $current = $parent;
        }
    }
}
