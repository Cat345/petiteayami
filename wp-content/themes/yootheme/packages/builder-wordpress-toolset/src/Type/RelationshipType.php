<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use WP_Post;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class RelationshipType
{
    /**
     * @param array<string, mixed> $relationship
     *
     * @return ObjectConfig
     */
    public static function config(string $name, array $relationship): array
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

    /**
     * @param array<string, mixed> $item
     * @return ?WP_Post
     */
    public static function resolvePost($item)
    {
        return get_post($item['post']);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $args
     * @return mixed
     */
    public static function resolveIntermediaryField($item, $args)
    {
        if (!isset($item['intermediary'])) {
            return null;
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

        return null;
    }
}
