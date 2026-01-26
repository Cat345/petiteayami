<?php

namespace YOOtheme\Theme\Wordpress\Listener;

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
        $active = false;

        foreach ($sorted_menu_items as $i => $item) {
            // Unset active class for posts_page if currently on none blog page
            if (
                in_array('current_page_parent', $item->classes) &&
                $item->object_id == get_option('page_for_posts') &&
                !is_singular('post') &&
                !is_category() &&
                !is_tag() &&
                !is_date() &&
                get_query_var('post_type') !== 'post'
            ) {
                unset($item->classes[array_search('current_page_parent', $item->classes)]);
            }

            // Polylang language switcher
            if (str_starts_with($item->post_name ?? '', 'languages')) {
                $item->classes = array_diff($item->classes, [
                    'current-menu-parent',
                    'current_page_item',
                ]);
            }

            // set current
            $item->active =
                !empty($item->active) ||
                preg_match(
                    '/\bcurrent-([a-z]+-ancestor|menu-(item|parent))\b/',
                    implode(' ', $item->classes),
                );

            if ($item->active) {
                static::setParentItemActive($sorted_menu_items, $i);
            }

            $active = $active || $item->active;
        }

        if (!$active) {
            foreach ($sorted_menu_items as $i => $item) {
                $item->active = preg_match(
                    '/\bcurrent_page_(item|parent)\b/',
                    implode(' ', $item->classes),
                );

                if ($item->active) {
                    static::setParentItemActive($sorted_menu_items, $i);
                }
            }
        }

        return $sorted_menu_items;
    }

    /**
     * @param list<object> $items
     */
    protected static function setParentItemActive(array $items, int $index): void
    {
        $item = $items[$index];
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$item->menu_item_parent) {
                break;
            }

            if (($items[$i]->db_id ?? -1) === (int) $item->menu_item_parent) {
                $items[$i]->active = true;
                $item = $items[$i];
            }
        }
    }
}
