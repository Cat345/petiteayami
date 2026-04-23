<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use Types_Field_Group_Repeatable_Item;
use Types_Field_Service;
use WP_Post;
use WP_Term;
use WP_User;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Event;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 * @phpstan-import-type FieldConfig from Source
 * @phpstan-import-type Field from Helper
 */
class FieldsType
{
    /**
     * @param array<Field> $fields
     * @return ObjectConfig
     */
    public static function config(Source $source, array $fields): array
    {
        return [
            'fields' => array_reduce(
                $fields,
                fn($fields, $field) => $fields +
                    static::configFields(
                        $field,
                        [
                            'type' => 'String',
                            'name' => ctype_digit($field['slug'])
                                ? "_{$field['slug']}"
                                : strtr($field['slug'], '-', '_'),
                            'metadata' => [
                                'label' => $field['name'],
                                'group' => $field['group'] ?: trans('Fields'),
                            ],
                        ],
                        $source,
                    ),
                [],
            ),
        ];
    }

    /**
     * @param Field $field
     * @param array<string, mixed> $config
     * @return array<FieldConfig>
     */
    public static function configFields($field, array $config, Source $source): array
    {
        $fields = [];
        if ($field['type'] !== 'rfg') {
            $fields = Helper::loadFields(
                $field,
                $config + [
                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveField',
                            'args' => ['slug' => $field['slug']],
                        ],
                    ],
                ],
            );
        } else {
            $fields[$field['name']] = static::loadRfgField(
                $source,
                $field,
                $config + [
                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveRfgField',
                            'args' => ['slug' => $field['slug']],
                        ],
                    ],
                ],
            );
        }

        return array_map(
            fn($config) => Event::emit('source.toolset.field|filter', $config, $field, $source),
            $fields,
        );
    }

    /**
     * @param WP_Post $post
     * @return ?WP_Post
     */
    public static function toolset($post)
    {
        return $post;
    }

    /**
     * @param WP_Post|WP_Term|WP_User $item
     * @param array<string, mixed> $args
     * @return mixed
     */
    public static function resolveField($item, $args)
    {
        $fieldService = new Types_Field_Service(false);

        if ($item instanceof WP_Post) {
            $fieldInstance = $fieldService->get_field(
                new \Types_Field_Gateway_Wordpress_Post(),
                $args['slug'],
                $item->ID,
            );
        } elseif ($item instanceof WP_Term) {
            $fieldInstance = $fieldService->get_field(
                new \Types_Field_Gateway_Wordpress_Term(),
                $args['slug'],
                $item->term_id,
            );
        } elseif ($item instanceof WP_User) {
            $fieldInstance = $fieldService->get_field(
                new \Types_Field_Gateway_Wordpress_User(),
                $args['slug'],
                $item->ID,
            );
        }

        return !empty($fieldInstance) ? Helper::getFieldValue($fieldInstance) : null;
    }

    /**
     * @param Field $field
     * @param array<string, mixed> $config
     * @return FieldConfig
     */
    protected static function loadRfgField(Source $source, array $field, array $config): array
    {
        $type = Str::camelCase(['toolset', $field['slug'], 'group'], true);
        $source->objectType($type, GroupType::config($field));

        return ['type' => ['listOf' => $type]] + $config;
    }

    /**
     * @param WP_Post|WP_Term|WP_User $item
     * @param array<string, mixed> $args
     * @return ?array<Types_Field_Group_Repeatable_Item>
     */
    public static function resolveRfgField($item, $args)
    {
        $rfg_service = new \Types_Field_Group_Repeatable_Service();
        $repeatableGroup = $rfg_service->get_object_from_prefixed_string($args['slug']);

        if (!$repeatableGroup) {
            return null;
        }

        $rfg = $rfg_service->get_object_by_id($repeatableGroup->get_id(), $item);

        if (!$rfg) {
            return null;
        }

        return $rfg->get_posts();
    }

    /**
     * @param WP_Post|WP_Term|WP_User $item
     * @param array<string, mixed> $args
     * @return ?array{value: string}
     */
    public static function resolveStringField($item, $args): ?array
    {
        $value = static::resolveField($item, $args);

        return $value ? ['value' => join(', ', array_column($value, 'value'))] : null;
    }
}
