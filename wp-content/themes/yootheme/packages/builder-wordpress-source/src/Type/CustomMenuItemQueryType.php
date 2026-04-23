<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class CustomMenuItemQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'customMenuItem' => [
                    'type' => 'MenuItem',

                    'args' => [
                        'menu' => [
                            'type' => 'Int',
                        ],
                        'id' => [
                            'type' => 'String',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Custom Menu Item'),
                        'group' => trans('Custom'),
                        'fields' => [
                            'menu' => [
                                'label' => trans('Menu'),
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    ['evaluate' => 'yootheme.customizer.menu.menusSelect()'],
                                ],
                            ],
                            'id' => [
                                'label' => trans('Menu Item'),
                                'description' => trans('Select menu item.'),
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    ['evaluate' => 'yootheme.customizer.menu.itemsSelect(menu)'],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolveItem',
                    ],
                ],
                'customMenuItems' => [
                    'type' => [
                        'listOf' => 'MenuItem',
                    ],

                    'args' => [
                        'id' => [
                            'type' => 'Int',
                        ],
                        'parent' => [
                            'type' => 'String',
                        ],
                        'heading' => [
                            'type' => 'String',
                        ],
                        'include_heading' => [
                            'type' => 'Boolean',
                            'defaultValue' => true,
                        ],
                        'ids' => [
                            'type' => [
                                'listOf' => 'String',
                            ],
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Custom Menu Items'),
                        'group' => trans('Custom'),
                        'fields' => [
                            'id' => [
                                'label' => trans('Menu'),
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    ['evaluate' => 'yootheme.customizer.menu.menusSelect()'],
                                ],
                            ],
                            'parent' => [
                                'label' => trans('Parent Menu Item'),
                                'description' => trans(
                                    'Menu items are only loaded from the selected parent item.',
                                ),
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    ['value' => '', 'text' => trans('Root')],
                                    ['evaluate' => 'yootheme.customizer.menu.itemsSelect(id)'],
                                ],
                            ],
                            'heading' => [
                                'label' => trans('Limit by Menu Heading'),
                                'type' => 'select',
                                'defaultIndex' => 0,
                                'options' => [
                                    ['value' => '', 'text' => trans('None')],
                                    [
                                        'evaluate' =>
                                            'yootheme.customizer.menu.headingItemsSelect(id, parent)',
                                    ],
                                ],
                            ],
                            'include_heading' => [
                                'description' => trans(
                                    'Only load menu items from the selected menu heading.',
                                ),
                                'type' => 'checkbox',
                                'text' => trans('Include heading itself'),
                            ],
                            'ids' => [
                                'label' => trans('Select Manually'),
                                'description' => trans(
                                    'Select menu items manually. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple menu items.',
                                ),
                                'type' => 'select',
                                'options' => [
                                    ['evaluate' => 'yootheme.customizer.menu.itemsSelect(id)'],
                                ],
                                'attrs' => [
                                    'multiple' => true,
                                    'class' => 'uk-height-small',
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|object $root
     * @param array<string, mixed> $args
     * @return array<object>
     */
    public static function resolve($root, array $args): array
    {
        $items = static::getItems($args['id']);

        $result = [];
        foreach ($items as $item) {
            // Pull children of heading items up to their parent.
            if (
                !empty($heading) &&
                (int) $item->menu_item_parent === $heading->ID &&
                $item->ID != ($args['parent'] ?? '')
            ) {
                $item->menu_item_parent = $heading->menu_item_parent;
            }

            if (
                $item->type === 'custom' &&
                $item->url === '#' &&
                $item->ID != ($args['parent'] ?? '')
            ) {
                $heading = $item;
            }

            if (!empty($args['ids'])) {
                if (in_array($item->ID, $args['ids'])) {
                    $result[] = $item;
                }
            } elseif (!empty($args['heading'])) {
                if (empty($found)) {
                    if ((string) $item->ID === $args['heading']) {
                        $found = $item;
                        if ($args['include_heading']) {
                            $result[] = $item;
                        }
                    }
                    continue;
                }

                if ($item->menu_item_parent !== $found->menu_item_parent) {
                    continue;
                }

                if (!($item->type === 'custom' && $item->url === '#')) {
                    $result[] = $item;
                    continue;
                }

                break;
            } elseif (!empty($args['parent'])) {
                if ($item->menu_item_parent == $args['parent']) {
                    $result[] = $item;
                }
            } elseif ($item->menu_item_parent == 0) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|object $root
     * @param array<string, mixed> $args
     * @return ?object
     */
    public static function resolveItem($root, array $args)
    {
        foreach (static::getItems($args['menu']) as $item) {
            if ($item->ID == $args['id']) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<object>
     */
    protected static function getItems(int $menu): array
    {
        $items = wp_get_nav_menu_items($menu);

        _wp_menu_item_classes_by_context($items);

        apply_filters('wp_nav_menu_objects', $items, []);

        return $items;
    }
}
