<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\ConfigObject;

/**
 * @phpstan-type Item array{id: string, level: int, menu: int, parent: string, title: string, type: string, object: string, object_id: int}
 * @property list<array{id: int, name: string}> $menus
 * @property list<Item> $items
 * @property array<string, string> $positions
 * @property bool  $canEdit
 * @property bool  $canCreate
 * @property bool  $canDelete
 */
class MenuConfig extends ConfigObject
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct([
            'menus' => $this->getMenus(),
            'items' => $this->getItems(),
            'positions' => get_registered_nav_menus(),
            'canEdit' => current_user_can('edit_theme_options'),
            'canEditLocation' => current_user_can('edit_theme_options'),
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function getMenus(): array
    {
        return array_map(
            fn($menu) => [
                'id' => $menu->term_id,
                'name' => $menu->name,
            ],
            wp_get_nav_menus(),
        );
    }

    /**
     * @return list<Item>
     */
    protected function getItems(): array
    {
        $results = [];

        foreach (wp_get_nav_menus() as $menu) {
            $items = wp_get_nav_menu_items($menu);
            $results = array_merge(
                $results,
                array_map(
                    fn($item) => [
                        'id' => strval($item->ID),
                        'level' => $this->getLevel($item, $items),
                        'menu' => $menu->term_id,
                        'parent' => (string) intval($item->menu_item_parent),
                        'title' => strip_tags($item->title), // Polylang adds HTML tags (language flags)
                        'type' =>
                            $item->type === 'custom' && $item->url === '#'
                                ? 'heading'
                                : $item->type,
                        'object' => $item->object,
                        'object_id' => intval($item->object_id),
                    ],
                    $items,
                ),
            );
        }

        return $results;
    }

    /**
     * @param list<object> $items
     */
    protected function getLevel(object $item, array $items): int
    {
        $level = 0;

        while (
            $item->menu_item_parent &&
            ($item = array_find($items, fn($post) => $post->ID === (int) $item->menu_item_parent))
        ) {
            $level++;
        }

        return $level;
    }
}
