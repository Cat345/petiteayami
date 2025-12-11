<?php

namespace YOOtheme\Builder\Wordpress\Toolset;

use YOOtheme\Arr;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Builder\Wordpress\Toolset\Type\FieldsType;
use YOOtheme\Str;
use function YOOtheme\app;

class Helper
{
    public static function isActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('types/wpcf.php');
    }

    public static function groups(string $domain, ?string $name = null): array
    {
        $fieldGroups = [];
        $factory = \Toolset_Field_Group_Factory::get_factory_by_domain($domain);

        if ($domain === \Toolset_Element_Domain::POSTS) {
            /** @var \Toolset_Field_Group_Post_Factory $factory */
            $fieldGroups = $factory->get_groups_by_post_type($name);
        } elseif ($domain === \Toolset_Element_Domain::TERMS) {
            /** @var \Toolset_Field_Group_Term_Factory $factory */
            $fieldGroups = $factory->get_groups_by_taxonomy($name);
        } elseif ($domain === \Toolset_Element_Domain::USERS) {
            /** @var \Toolset_Field_Group_User_Factory $factory */
            $fieldGroups = array_merge(...array_values($factory->get_groups_by_roles()));
        }

        return array_map(
            fn($fieldGroup) => $factory->load_field_group($fieldGroup->get_slug()),
            $fieldGroups,
        );
    }

    public static function fields(string $domain, array $fieldSlugs, bool $loadRFG = true): array
    {
        $rfg_service = new \Types_Field_Group_Repeatable_Service();
        $factory = \Toolset_Field_Definition_Factory::get_factory_by_domain($domain);

        $fields = [];
        foreach ($fieldSlugs as $slug) {
            $field = $factory->load_field_definition($slug);

            if ($field && $field->is_managed_by_types()) {
                $fields[$slug] = $field->get_definition_array();
            } elseif ($loadRFG) {
                $repeatableGroup = $rfg_service->get_object_from_prefixed_string($slug);
                if ($repeatableGroup) {
                    $groupFieldSlugs = $repeatableGroup->get_field_slugs();

                    if ($groupFieldSlugs) {
                        $fields[$slug] = [
                            'name' => $repeatableGroup->get_name(),
                            'slug' => $slug,
                            'type' => 'rfg',
                            'fieldSlugs' => $groupFieldSlugs,
                        ];
                    }
                }
            }
        }

        return $fields;
    }

    public static function fieldsGroups(string $domain, ?string $name = null): array
    {
        $fields = [];
        foreach (static::groups($domain, $name) as $group) {
            foreach (static::fields($domain, $group->get_field_slugs()) as $field) {
                $field['group'] = $group->get_display_name();
                $fields[$field['slug']] = $field;
            }
        }

        return $fields;
    }

    public static function getRelationships($name, $origin = 'standard')
    {
        return toolset_get_relationships([
            'type_constraints' => [
                'any' => [
                    'type' => $name,
                ],
                'parent' => [
                    'domain' => \Toolset_Element_Domain::POSTS,
                ],
                'child' => [
                    'domain' => \Toolset_Element_Domain::POSTS,
                ],
            ],
            'origin' => $origin,
        ]);
    }

    public static function loadFields($field, array $config): array
    {
        $fields = [];

        if ($field['type'] === 'post') {
            $post = self::getPostType($field['data']['post_reference_type']);

            if (!$post) {
                return [];
            }

            $type = Str::camelCase($post->name, true);

            $fields[] =
                [
                    'type' => self::isMultiple($field) ? ['listOf' => $type] : $type,
                ] + $config;
        } elseif ($field['type'] === 'image') {
            $fields[] =
                [
                    'type' => self::isMultiple($field) ? ['listOf' => 'Attachment'] : 'Attachment',
                ] + $config;
        } elseif ($field['type'] === 'date') {
            if (self::isMultiple($field)) {
                $fields[] =
                    [
                        'type' => [
                            'listOf' => 'ToolsetDateField',
                        ],
                    ] + $config;
            } else {
                $fields[] = array_merge_recursive($config, ['metadata' => ['filters' => ['date']]]);
            }
        } elseif (self::isMultiple($field)) {
            $fields[] =
                [
                    'type' => [
                        'listOf' => 'ToolsetValueField',
                    ],
                ] + $config;

            // add concat field
            if ($field['type'] === 'checkboxes') {
                $fields[] =
                    [
                        'name' => $config['name'] . 'String',
                        'type' => 'ToolsetValueField',
                        'extensions' => [
                            'call' => [
                                'func' => FieldsType::class . '::resolveStringField',
                                'args' => ['slug' => $field['slug']],
                            ],
                        ],
                    ] + $config;
            }
        } elseif ($field['type'] === 'google_address') {
            $fields[] = ['type' => 'ToolsetMapsField'] + $config;
        } else {
            $fields[] = $config;
        }

        return array_combine(array_column($fields, 'name'), $fields);
    }

    public static function getFieldValue($fieldInstance)
    {
        $field = $fieldInstance->to_array();

        // support for legacy types
        if (!$field && method_exists($fieldInstance, 'get_data_raw')) {
            $field = $fieldInstance->get_data_raw();
        }

        $fieldType = $fieldInstance->get_type();

        if (in_array($fieldType, ['checkboxes', 'radio', 'select'])) {
            $options = $fieldInstance->get_options();
            $value = array_map(fn($option) => $option->get_value(), $options);

            // filter unchecked radio, select options
            $value = array_values(array_filter($value));
        } elseif ($fieldType === 'checkbox') {
            $option = $fieldInstance->get_option();
            $value = [$option->get_value()];
        } elseif ($fieldType === 'post') {
            return $fieldInstance->get_post();
        } elseif ($fieldType === 'date') {
            $value = $fieldInstance->get_value();
            foreach ($value as $i => $val) {
                $date = date_create("@{$val}")->setTimezone(wp_timezone());
                $value[$i] = $date->getTimestamp() - $date->getOffset();
            }
        } else {
            $value = $fieldInstance->get_value();
        }

        // convert image urls in attachment ids
        if ($fieldType === 'image' && is_array($value)) {
            $value = array_map(fn($src) => \Toolset_Utils::get_attachment_id_by_url($src), $value);

            if (self::isMultiple($field)) {
                return $value;
            }
        }

        if ($value && self::isMultiple($field)) {
            return array_map(fn($value) => ['value' => $value], $value);
        }

        return $value[0] ?? null;
    }

    public static function getPostType($post_type)
    {
        $postTypes = SourceHelper::getPostTypes();

        return $postTypes[$post_type] ?? null;
    }

    public static function isMultiple($field): bool
    {
        if (in_array($field['type'], ['checkboxes', 'relationship'])) {
            return count(Arr::get($field, 'data.options', [])) > 1;
        }

        return Arr::get($field, 'data.repetitive') === '1';
    }

    public static function getDomainFromNode($node)
    {
        if (empty($node->source->query->name)) {
            return;
        }

        $type = app(Source::class)->getSchema()->getType('Query');

        foreach (explode('.', $node->source->query->name) as $part) {
            if (!$type->hasField($part)) {
                return;
            }

            $type = $type->getField($part)->getType();
        }

        $name = Str::snakeCase($type->name);
        if ($name === 'user') {
            return \Toolset_Element_Domain::USERS;
        } elseif (post_type_exists($name)) {
            return \Toolset_Element_Domain::POSTS;
        } elseif (taxonomy_exists($name)) {
            return \Toolset_Element_Domain::TERMS;
        }
    }
}
