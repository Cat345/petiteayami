<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

class RelationshipType
{
    public static function config($name, $relationship)
    {
        $intermediary = isset($relationship['roles']['intermediary'])
            ? Helper::fieldsGroups('posts', $relationship['roles']['intermediary']['types'])
            : [];

        return [
            'fields' => array_merge(
                [
                    'post' => [
                        'type' => Str::camelCase($name, true),
                        'metadata' => [
                            'label' => trans('Post'),
                        ],
                        'extensions' => [
                            'call' => __CLASS__ . '::resolvePost',
                        ],
                    ],
                ],
                array_filter(
                    array_reduce(
                        $intermediary,
                        fn($fields, $field) => $fields +
                            Helper::loadFields($field, [
                                'type' => 'String',
                                'name' => Str::snakeCase($field['slug']),
                                'metadata' => [
                                    'label' => $field['name'],
                                    'group' => $field['group'],
                                ],
                                'extensions' => [
                                    'call' => [
                                        'func' => __CLASS__ . '::resolveIntermediaryField',
                                        'args' => ['slug' => $field['slug']],
                                    ],
                                ],
                            ]),
                        [],
                    ),
                ),
            ),
        ];
    }

    public static function resolvePost($item)
    {
        return get_post($item['post']);
    }

    public static function resolveIntermediaryField($item, $args)
    {
        if (!isset($item['intermediary'])) {
            return;
        }

        $post = get_post($item['intermediary']);
        $fieldService = new \Types_Field_Service(false);
        $fieldInstance = $fieldService->get_field(
            new \Types_Field_Gateway_Wordpress_Post(),
            $args['slug'],
            $post->ID,
        );

        if ($fieldInstance) {
            return Helper::getFieldValue($fieldInstance);
        }
    }
}
