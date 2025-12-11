<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Event;
use YOOtheme\Str;

class FieldsType
{
    /**
     * @param Source $source
     * @param array  $fields
     * @param string $type
     *
     * @return array
     */
    public static function config(Source $source, array $fields, string $type)
    {
        $isType = $source->objectType($type)->config['metadata']['type'] ?? false;

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

    protected static function configFields($field, array $config, Source $source)
    {
        $type = Str::camelCase($field['type'], true);
        $fields = [];

        if (is_callable($callback = [__CLASS__, "config{$type}"])) {
            $fields[Str::snakeCase($field['name'])] = $callback($field, $config, $source);
        } else {
            $fields += static::configGenericField($field, $config, $source);
        }

        return array_map(
            fn($config) => Event::emit('source.acf.field|filter', $config, $field, $source),
            $fields,
        );
    }

    protected static function configGenericField($field, array $config, Source $source)
    {
        $name = Str::snakeCase($field['name']);

        if (isset($field['choices'])) {
            return [
                $name => static::configChoices($field, $config),
                "{$name}String" => static::configChoicesString($field, $config),
            ];
        }

        if ($field['type'] === 'image') {
            $config = static::configAttachment($field, $config);
        } elseif ($field['type'] === 'file') {
            $config = static::configFileField($field, $config);
        } elseif (in_array($field['type'], ['time_picker', 'date_time_picker'])) {
            $config = static::configDatePicker($field, $config);
        } elseif ($field['type'] === 'relationship') {
            $config = static::configPostObject($field, $config);
        } elseif (isset($field['sub_fields'])) {
            $config = static::configSubfields($field, $config, $source);
        } elseif (in_array($field['type'], ['text', 'text_area', 'wysiwyg'])) {
            $config = array_merge_recursive($config, [
                'metadata' => ['filters' => ['limit', 'preserve']],
            ]);

            if (static::isMultiple($field)) {
                $config = ['type' => ['listOf' => 'ValueField']] + $config;
            }
        }

        return [$name => $config];
    }

    protected static function configDatePicker($field, array $config)
    {
        return array_merge_recursive($config, ['metadata' => ['filters' => ['date']]]);
    }

    protected static function configPostObject($field, $config)
    {
        if (empty($field['post_type']) || count($field['post_type']) > 1) {
            return;
        }

        if (!($type = static::getPostType(array_pop($field['post_type'])))) {
            return;
        }

        $type = Str::camelCase($type->name, true);

        return ['type' => static::isMultiple($field) ? ['listOf' => $type] : $type] + $config;
    }

    protected static function configTaxonomy($field, $config)
    {
        $taxonomy = !empty($field['taxonomy']) ? static::getTaxonomy($field['taxonomy']) : false;

        if (!$taxonomy) {
            return;
        }

        $taxonomy = Str::camelCase($taxonomy->name, true);

        return ['type' => static::isMultiple($field) ? ['listOf' => $taxonomy] : $taxonomy] +
            $config;
    }

    protected static function configUser($field, array $config)
    {
        return ['type' => static::isMultiple($field) ? ['listOf' => 'User'] : 'User'] + $config;
    }

    protected static function configChoices($field, array $config)
    {
        return [
            'type' => static::isMultiple($field) ? ['listOf' => 'ChoiceField'] : 'ChoiceField',
        ] + $config;
    }

    protected static function configChoicesString($field, array $config)
    {
        if (!static::isMultiple($field)) {
            return;
        }

        return ['type' => 'ChoiceFieldString'] + $config;
    }

    protected static function configLink($field, array $config)
    {
        return ['type' => 'LinkField'] + $config;
    }

    protected static function configGoogleMap($field, array $config)
    {
        return ['type' => 'GoogleMapsField'] + $config;
    }

    protected static function configAttachment($field, array $config)
    {
        return ['type' => 'Attachment'] + $config;
    }

    protected static function configFileField($field, array $config)
    {
        return ['type' => 'FileField'] + $config;
    }

    protected static function configGallery($field, array $config)
    {
        return ['type' => ['listOf' => 'Attachment']] + $config;
    }

    protected static function configSubfields($field, array $config, Source $source)
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

        if ($fields = array_filter($fields)) {
            $name = Str::camelCase(['Field', $field['name']], true);
            $source->objectType($name, compact('fields'));

            return (static::isMultiple($field)
                ? ['type' => ['listOf' => $name]]
                : [
                    'type' => $name,
                    'metadata' => array_merge($config['metadata'], [
                        'label' => $field['label'],
                    ]),
                ]) + $config;
        }
    }

    public static function field($post, $args, $context, $info)
    {
        return $post;
    }

    public static function resolve($post, $args, $context, $info)
    {
        if ($field = acf_get_field($args['field'])) {
            if (
                $field['parent'] &&
                ($parent = acf_get_field($field['parent'])) &&
                $field['name'] === $parent['name']
            ) {
                $field = $parent;
            }

            return static::getField($post, $field);
        }
    }

    public static function resolveSubfield($config, $args, $context, $info)
    {
        [$post, $fields] = $config;

        if (!empty($fields[$args['field']])) {
            return static::getField($post, $fields[$args['field']]);
        }
    }

    protected static function getField($post, array $field)
    {
        $postId = acf_get_valid_post_id($post);

        // Subfields field
        if (array_key_exists('sub_fields', $field)) {
            if (empty($field['sub_fields'])) {
                return;
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

        switch ($field['type']) {
            case 'post_object':
            case 'relationship':
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

        // reset cached values
        if (function_exists('acf_flush_value_cache')) {
            acf_flush_value_cache($postId, $field['name']);
        }

        // get value for field
        if (is_null($value = acf_get_value($postId, $field))) {
            return;
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

    protected static function getTaxonomy($taxonomy)
    {
        $taxonomies = Helper::getTaxonomies(['name' => $taxonomy]);
        return array_pop($taxonomies);
    }

    protected static function getPostType($postType)
    {
        $postTypes = Helper::getPostTypes(['name' => $postType]);
        return array_pop($postTypes);
    }

    protected static function isMultiple(array $field): bool
    {
        return !empty($field['multiple']) ||
            in_array($field['type'], ['checkbox', 'gallery', 'relationship']) ||
            (!empty($field['field_type']) &&
                !in_array($field['field_type'], ['select', 'radio'])) ||
            isset($field['sub_fields'], $field['max']);
    }
}
