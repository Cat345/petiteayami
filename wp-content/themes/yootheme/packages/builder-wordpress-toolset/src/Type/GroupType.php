<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use Types_Field_Group_Repeatable_Item;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Str;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class GroupType
{
    /**
     * @param array<string, mixed> $fieldGroup
     * @return ObjectConfig
     */
    public static function config($fieldGroup): array
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

    /**
     * @param Types_Field_Group_Repeatable_Item $item
     * @param array<string, mixed> $args
     * @return mixed
     */
    public static function resolve($item, $args)
    {
        foreach ($item->get_fields() as $fieldInstance) {
            if ($fieldInstance->get_slug() === $args['slug']) {
                return Helper::getFieldValue($fieldInstance);
            }
        }
        return null;
    }
}
