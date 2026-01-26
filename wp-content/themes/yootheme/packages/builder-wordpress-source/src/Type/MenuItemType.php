<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Config;
use function YOOtheme\app;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class MenuItemType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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
            ],
        ];
    }

    /**
     * @return int
     */
    public static function id(object $item)
    {
        return $item->ID;
    }

    /**
     * @param array<string> $args
     * @param mixed $context
     * @return array<mixed>
     */
    public static function data(object $item, array $args, $context, object $info)
    {
        return app(Config::class)->get("~theme.menu.items.{$item->ID}.{$info->fieldName}");
    }

    public static function type(object $item): string
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

    public static function parent(object $item): ?object
    {
        $menu = array_first(wp_get_object_terms($item->ID, 'nav_menu'));

        return $menu
            ? CustomMenuItemQueryType::resolveItem($item, [
                'menu' => $menu->term_id,
                'id' => $item->menu_item_parent,
            ])
            : null;
    }

    /**
     * @return ?array<object>
     */
    public static function children(object $item): ?array
    {
        $menu = array_first(wp_get_object_terms($item->ID, 'nav_menu'));

        return $menu
            ? CustomMenuItemQueryType::resolve($item, [
                'parent' => $item->ID,
                'id' => $menu->term_id,
            ])
            : null;
    }
}
