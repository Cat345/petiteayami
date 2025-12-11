<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Str;

class GroupType
{
    public static function config($fieldGroup)
    {
        return [
            'fields' => array_filter(
                array_reduce(
                    Helper::fields('posts', $fieldGroup['fieldSlugs']),
                    fn($fields, $field) => $fields +
                        Helper::loadFields($field, [
                            'type' => 'String',
                            'name' => Str::snakeCase($field['slug']),
                            'metadata' => [
                                'label' => $field['name'],
                                'group' => $fieldGroup['name'],
                            ],
                            'extensions' => [
                                'call' => [
                                    'func' => __CLASS__ . '::resolve',
                                    'args' => ['slug' => $field['slug']],
                                ],
                            ],
                        ]),
                    [],
                ),
            ),
        ];
    }

    public static function resolve($item, $args, $context, $info)
    {
        foreach ($item->get_fields() as $fieldInstance) {
            if ($fieldInstance->get_slug() === $args['slug']) {
                return Helper::getFieldValue($fieldInstance);
            }
        }
    }
}
