<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

use YOOtheme\Config;

class FilterMenuItems
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Filters the sorted list of menu item objects before generating the menu's HTML.
     *
     * @param list<object> $sorted_menu_items
     *
     * @return list<object>
     */
    public function handle(array $sorted_menu_items): array
    {
        if (!class_exists('SitePress', false)) {
            return $sorted_menu_items;
        }

        foreach ($sorted_menu_items as $item) {
            if (!$item instanceof \WPML_LS_Menu_Item || $item->menu_item_parent !== 0) {
                continue;
            }

            $this->config->set(
                "~theme.menu.items.$item->ID",
                $this->config->get('~theme.wpml', []),
            );
        }

        return $sorted_menu_items;
    }
}
