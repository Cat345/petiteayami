<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Config;
use function YOOtheme\app;
use function YOOtheme\trans;

class MenuItemType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Title'),
                        'filters' => ['limit', 'preserve'],
                    ],
                ],
                'image' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Image'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::data',
                    ],
                ],
                'icon' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Icon'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::data',
                    ],
                ],
                'subtitle' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Subtitle'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::data',
                    ],
                ],
                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Link'),
                    ],
                ],
                'active' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Active'),
                    ],
                ],
                'type' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Type'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::type',
                    ],
                ],
                'parent' => [
                    'type' => 'MenuItem',
                    'metadata' => [
                        'label' => trans('Parent Menu Item'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::parent',
                    ],
                ],
                'children' => [
                    'type' => [
                        'listOf' => 'MenuItem',
                    ],
                    'metadata' => [
                        'label' => trans('Child Menu Items'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::children',
                    ],
                ],
                'id' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('ID'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::id',
                    ],
                ],
            ],
            'metadata' => [
                'type' => true,
                'label' => trans('Menu Item'),
            ],
        ];
    }

    public static function id($item, $args, $context, $info)
    {
        return $item->ID;
    }

    public static function data($item, $args, $context, $info)
    {
        return app(Config::class)->get("~theme.menu.items.{$item->ID}.{$info->fieldName}");
    }

    public static function type($item)
    {
        if ($item->type === 'custom') {
            if ($item->url === '#' && preg_match('/---+/i', $item->title)) {
                return 'divider';
            }
            if ($item->url === '#') {
                return 'heading';
            }
        }

        return '';
    }

    public static function parent($item)
    {
        $menu = current(wp_get_object_terms($item->ID, 'nav_menu'));
        if ($menu) {
            return CustomMenuItemQueryType::resolveItem($item, [
                'menu' => $menu->term_id,
                'id' => $item->menu_item_parent,
            ]);
        }
    }

    public static function children($item)
    {
        $menu = current(wp_get_object_terms($item->ID, 'nav_menu'));
        if ($menu) {
            return CustomMenuItemQueryType::resolve($item, [
                'parent' => $item->ID,
                'id' => $menu->term_id,
            ]);
        }
    }
}
