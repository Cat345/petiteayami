<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use WP_Post;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Acf\AcfHelper;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Event;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type FieldConfig from Source
 * @phpstan-import-type ObjectConfig from Source
 * @phpstan-import-type Field from AcfHelper
 */
class FieldsType
{
    /**
     * @param array<string, Field> $fields
     *
     * @return ObjectConfig
     */
    public static function config(Source $source, object $type, array $fields)
    {
        $isType = $type->config['metadata']['type'] ?? false;

        return [
            'fields' => array_filter(
                array_reduce(
                    $fields,
                    fn($fields, $field) => $fields +
                        static::configFields(
                            $field,
                            [
                                'type' => 'String',
                                'metadata' => [
                                    'label' => $field['label'] ?: $field['name'],
                                    'group' => $isType ? $field['group']['title'] : null,
                                ],
                                'extensions' => [
                                    'call' => [
                                        'func' => __CLASS__ . '::resolve',
                                        'args' => ['field' => $field['name']],
                                    ],
                                ],
                            ],
                            $source,
                        ),
                    [],
                ),
            ),
        ];
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return array<string, FieldConfig>
     */
    protected static function configFields(array $field, array $config, Source $source): array
    {
        $config += ['name' => Str::snakeCase($field['name'])];
        $config = is_callable($callback = [__CLASS__, Str::camelCase(['config', $field['type']])])
            ? $callback($field, $config, $source)
            : static::configGenericField($field, $config, $source);

        $config = Event::emit('source.acf.field|filter', $config, $field, $source);

        if ($config) {
            return array_is_list($config)
                ? array_combine(array_column($config, 'name'), $config)
                : [$config['name'] => $config];
        }

        return [];
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return array<string, FieldConfig>
     */
    protected static function configGenericField(array $field, array $config, Source $source): array
    {
        if (isset($field['choices'])) {
            $config = static::configChoices($field, $config);
        } elseif ($field['type'] === 'image') {
            $config = static::configAttachment($field, $config);
        } elseif ($field['type'] === 'file') {
            $config = static::configFileField($field, $config);
        } elseif (in_array($field['type'], ['time_picker', 'date_time_picker'])) {
            $config = static::configDatePicker($field, $config);
        } elseif ($field['type'] === 'relationship') {
            $config = static::configPostObject($field, $config);
        } elseif (isset($field['sub_fields'])) {
            $config = static::configSubfields($field, $config, $source);
        } elseif (in_array($field['type'], ['text', 'textarea', 'wysiwyg'])) {
            $config = array_merge_recursive($config, [
                'metadata' => ['filters' => ['limit', 'preserve']],
            ]);

            if (static::isMultiple($field)) {
                $config = ['type' => ['listOf' => 'ValueField']] + $config;
            }
        }

        return $config ?? [];
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configDatePicker(array $field, array $config): array
    {
        return array_merge_recursive($config, ['metadata' => ['filters' => ['date']]]);
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return ?list<FieldConfig>
     */
    protected static function configPostObject(array $field, array $config): ?array
    {
        if (empty($field['post_type'])) {
            return null;
        }

        $fields = [];
        foreach ($field['post_type'] as $postType) {
            $type = static::getPostType($postType);

            if (!$type) {
                continue;
            }

            $fields[] = array_replace_recursive(
                $config,
                count($field['post_type']) > 1
                    ? [
                        'name' => Str::snakeCase([$config['name'], $type->name]),
                        'metadata' => [
                            'label' => trans('%label% (%post_type% fields)', [
                                '%label%' => $config['metadata']['label'],
                                '%post_type%' => $type->labels->singular_name,
                            ]),
                        ],
                    ]
                    : [],
                static::configPostObjectSingle($field, $type),
            );
        }

        return $fields;
    }

    /**
     * @param Field $field
     * @param \WP_Post_Type $type
     *
     * @return FieldConfig
     */
    protected static function configPostObjectSingle($field, $type): array
    {
        $typeName = Str::camelCase($type->name, true);

        return static::isMultiple($field)
            ? [
                'type' => ['listOf' => $typeName],
                'args' => [
                    'order' => [
                        'type' => 'String',
                    ],
                    'order_direction' => [
                        'type' => 'String',
                    ],
                    'order_alphanum' => [
                        'type' => 'Boolean',
                    ],
                ],
                'metadata' => [
                    'arguments' => [
                        '_order' => [
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => [
                                'order' => [
                                    'label' => trans('Order'),
                                    'type' => 'select',
                                    'options' => [
                                        ['value' => '', 'text' => trans('Default')],
                                        [
                                            'evaluate' =>
                                                'yootheme.builder.sources.postTypeOrderOptions',
                                        ],
                                        [
                                            'evaluate' => "yootheme.builder.sources['{$type->name}OrderOptions']",
                                        ],
                                    ],
                                ],
                                'order_direction' => [
                                    'label' => trans('Direction'),
                                    'type' => 'select',
                                    'default' => 'ASC',
                                    'options' => [
                                        ['text' => trans('Ascending'), 'value' => 'ASC'],
                                        ['text' => trans('Descending'), 'value' => 'DESC'],
                                        [
                                            'evaluate' => "yootheme.builder.sources['{$type->name}OrderDirectionOptions']",
                                        ],
                                    ],
                                    'enable' => 'order',
                                ],
                            ],
                            '@order' => 60,
                        ],

                        'order_alphanum' => [
                            'text' => trans('Alphanumeric Ordering'),
                            'type' => 'checkbox',
                            'enable' => 'order',
                            '@order' => 70,
                        ],
                    ],
                ],
            ]
            : ['type' => $typeName];
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return ?FieldConfig
     */
    protected static function configTaxonomy(array $field, array $config): ?array
    {
        $taxonomy = !empty($field['taxonomy']) ? static::getTaxonomy($field['taxonomy']) : false;

        if (!$taxonomy) {
            return null;
        }

        $taxonomy = Str::camelCase($taxonomy->name, true);

        return ['type' => static::isMultiple($field) ? ['listOf' => $taxonomy] : $taxonomy] +
            $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configUser(array $field, array $config): array
    {
        return ['type' => static::isMultiple($field) ? ['listOf' => 'User'] : 'User'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig|list<FieldConfig>
     */
    protected static function configChoices(array $field, array $config): array
    {
        if (static::isMultiple($field)) {
            return [
                [
                    'type' => ['listOf' => 'ChoiceField'],
                ] + $config,
                [
                    'name' => "{$config['name']}String",
                    'type' => 'ChoiceFieldString',
                ] + $config,
            ];
        }

        return ['type' => 'ChoiceField'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configLink(array $field, array $config): array
    {
        return ['type' => 'LinkField'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configGoogleMap(array $field, array $config): array
    {
        return ['type' => 'GoogleMapsField'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configAttachment(array $field, array $config): array
    {
        return ['type' => 'Attachment'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configFileField(array $field, array $config): array
    {
        return ['type' => 'FileField'] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return FieldConfig
     */
    protected static function configGallery(array $field, array $config): array
    {
        return ['type' => ['listOf' => 'Attachment']] + $config;
    }

    /**
     * @param Field $field
     * @param FieldConfig $config
     * @return ?FieldConfig
     */
    protected static function configSubfields($field, array $config, Source $source): ?array
    {
        $fields = [];

        foreach ($field['sub_fields'] as $subField) {
            $fields += static::configFields(
                $subField,
                [
                    'type' => 'String',
                    'metadata' => [
                        'label' => $subField['label'] ?: $subField['name'],
                    ],
                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveSubfield',
                            'args' => ['field' => $subField['name']],
                        ],
                    ],
                ],
                $source,
            );
        }

        if ($fields) {
            $type = Str::camelCase(['Field', $field['name']], true);
            $source->objectType($type, compact('fields'));

            return (static::isMultiple($field)
                ? ['type' => ['listOf' => $type]]
                : [
                    'type' => $type,
                    'metadata' => array_merge($config['metadata'], [
                        'label' => $field['label'],
                    ]),
                ]) + $config;
        }

        return null;
    }

    /**
     * @param WP_Post $post
     * @return WP_Post
     */
    public static function field($post)
    {
        return $post;
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     *
     * @return mixed
     */
    public static function resolve($post, $args)
    {
        if ($field = acf_get_field($args['field'])) {
            if (
                $field['parent'] &&
                ($parent = acf_get_field($field['parent'])) &&
                $field['name'] === $parent['name']
            ) {
                $field = $parent;
            }

            return static::getField($post, $field, $args);
        }

        return null;
    }

    /**
     * @param list<mixed> $config
     * @param array<string, mixed> $args
     *
     * @return mixed
     */
    public static function resolveSubfield($config, $args)
    {
        [$post, $fields] = $config;

        if (!empty($fields[$args['field']])) {
            return static::getField($post, $fields[$args['field']], $args);
        }

        return null;
    }

    /**
     * @param WP_Post $post
     * @param Field $field
     * @param array<string, mixed> $args
     * @return mixed
     */
    protected static function getField($post, array $field, $args)
    {
        // Subfields field
        if (array_key_exists('sub_fields', $field)) {
            return static::getSubField($post, $field);
        }

        switch ($field['type']) {
            case 'post_object':
            case 'relationship':
                Helper::filterOnce('acf/acf_get_posts/args', static::filterOrderOptions($args));
            case 'taxonomy':
            case 'user':
                $field['return_format'] = 'object';
                break;
            case 'button_group':
            case 'checkbox':
            case 'radio':
            case 'select':
            case 'link':
                $field['return_format'] = 'array';
                break;
            case 'file':
            case 'gallery':
            case 'image':
                $field['return_format'] = 'id';
                break;
            case 'time_picker':
            case 'date_picker':
            case 'date_time_picker':
                $field['return_format'] = DATE_W3C;
                break;
        }

        $postId = acf_get_valid_post_id($post);

        // reset cached values
        if (function_exists('acf_flush_value_cache')) {
            acf_flush_value_cache($postId, $field['name']);
        }

        // get value for field
        if (is_null($value = acf_get_value($postId, $field)) || $value === false) {
            return null;
        }

        $value = acf_format_value($value, $postId, $field);

        if (!empty($field['return_format'])) {
            return $value ?: null;
        }

        if (static::isMultiple($field)) {
            return array_map(fn($value) => ['value' => $value], $value);
        }

        return $value;
    }

    /**
     * @param WP_Post $post
     * @param Field $field
     * @return mixed
     */
    protected static function getSubField($post, array $field)
    {
        if (empty($field['sub_fields'])) {
            return null;
        }

        if (!static::isMultiple($field)) {
            $subfields = [];

            foreach ($field['sub_fields'] as $subfield) {
                $subfields[$subfield['name']] =
                    ['name' => "{$field['name']}_{$subfield['name']}"] + $subfield;
            }

            return [$post, $subfields];
        }

        $values = [];

        $postId = acf_get_valid_post_id($post);

        for ($i = 0; $i < acf_get_metadata($postId, $field['name']); $i++) {
            $subfields = [];

            foreach ($field['sub_fields'] as $subfield) {
                $subfields[$subfield['name']] =
                    ['name' => "{$field['name']}_{$i}_{$subfield['name']}"] + $subfield;
            }

            $values[] = [$post, $subfields];
        }

        return $values;
    }

    protected static function getTaxonomy(string $taxonomy): ?\WP_Taxonomy
    {
        return array_last(Helper::getTaxonomies(['name' => $taxonomy]));
    }

    protected static function getPostType(string $postType): ?\WP_Post_Type
    {
        return array_last(Helper::getPostTypes(['name' => $postType]));
    }

    /**
     * @param Field $field
     */
    protected static function isMultiple(array $field): bool
    {
        return !empty($field['multiple']) ||
            in_array($field['type'], ['checkbox', 'gallery', 'relationship']) ||
            (!empty($field['field_type']) &&
                !in_array($field['field_type'], ['select', 'radio'])) ||
            isset($field['sub_fields'], $field['max']);
    }

    /**
     * @param array<string, mixed> $args
     */
    protected static function filterOrderOptions($args): callable
    {
        return function ($query) use ($args) {
            if (empty($args['order'])) {
                return $query;
            }

            $query =
                [
                    'orderby' => $args['order'],
                    'order' => $args['order_direction'] ?? 'ASC',
                ] + $query;

            if (str_starts_with($query['orderby'], 'field:')) {
                $query['meta_key'] = substr($query['orderby'], 6);
                $query['orderby'] = 'meta_value';
            }

            if (!empty($args['order_alphanum']) && $args['order'] !== 'rand') {
                $query['suppress_filters'] = false;
                Helper::filterOnce('posts_orderby', Helper::orderAlphanum($query));
            }

            $ids = $query['post__in'] ?? [];
            if (!empty($ids)) {
                $query['post__in'] = [];
                Helper::filterOnce('pre_get_posts', fn($query) => $query->set('post__in', $ids));
            }

            return $query;
        };
    }
}
