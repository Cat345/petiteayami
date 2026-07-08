<?php

namespace YOOtheme\Builder\Wordpress\Toolset;

use Toolset_Field_Group;
use Types_Field_Abstract;
use Types_Field_Type_Checkbox;
use Types_Field_Type_Checkboxes;
use Types_Field_Type_Post;
use Types_Field_Type_Radio;
use Types_Field_Type_Select;
use WP_Post_Type;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Builder\Wordpress\Toolset\Type\FieldsType;
use YOOtheme\Str;
use function YOOtheme\app;

/**
 * @phpstan-import-type FieldConfig from Source
 *
 * @phpstan-type Field array{
 *     id: string,
 *     slug: string,
 *     type:string,
 *     name:string,
 *     description:string,
 *     data:array<string, mixed>,
 *     meta_key: string,
 *     meta_type: string,
 *     group: string
 * }
 */
class Helper
{
    public static function isActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('types/wpcf.php');
    }

    /**
     * @return array<Toolset_Field_Group>
     */
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

    /**
     * @param list<string> $fieldSlugs
     * @return array<string, Field>
     */
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

    /**
     * @return array<string, Field>
     */
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

    /**
     * @return array<array<string, mixed>>
     */
    public static function getRelationships(string $name, string $origin = 'standard'): array
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

    /**
     * @param Field $field
     * @param array<string, mixed> $config
     * @return array<string, FieldConfig>
     */
    public static function loadFields(array $field, array $config): array
    {
        $fields = [];

        if ($field['type'] === 'post') {
            $post = self::getPostType($field['data']['post_reference_type']);

            if (!$post) {
                return [];
            }

            $type = Str::camelCase($post->name, true);

            $fields[] =
                ['type' => self::isMultiple($field) ? ['listOf' => $type] : $type] + $config;
        } elseif ($field['type'] === 'image') {
            $fields[] =
                ['type' => self::isMultiple($field) ? ['listOf' => 'Attachment'] : 'Attachment'] +
                $config;
        } elseif ($field['type'] === 'date') {
            if (self::isMultiple($field)) {
                $fields[] = ['type' => ['listOf' => 'ToolsetDateField']] + $config;
            } else {
                $fields[] = array_merge_recursive($config, ['metadata' => ['filters' => ['date']]]);
            }
        } elseif (self::isMultiple($field)) {
            $fields[] = ['type' => ['listOf' => 'ToolsetValueField']] + $config;

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

    /**
     * @param Types_Field_Abstract $fieldInstance
     * @return mixed
     */
    public static function getFieldValue($fieldInstance)
    {
        $field = $fieldInstance->to_array();

        // support for legacy types
        if (!$field && method_exists($fieldInstance, 'get_data_raw')) {
            $field = $fieldInstance->get_data_raw();
        }

        $fieldType = $fieldInstance->get_type();

        if (in_array($fieldType, ['checkboxes', 'radio', 'select'])) {
            /** @var Types_Field_Type_Checkboxes|Types_Field_Type_Select|Types_Field_Type_Radio $fieldInstance */
            $options = $fieldInstance->get_options();
            $value = array_map(fn($option) => $option->get_value(), $options);

            // filter unchecked radio, select options
            $value = array_values(array_filter($value));
        } elseif ($fieldType === 'checkbox') {
            /** @var Types_Field_Type_Checkbox $fieldInstance */
            $option = $fieldInstance->get_option();
            $value = [$option->get_value()];
        } elseif ($fieldType === 'post') {
            /** @var Types_Field_Type_Post $fieldInstance */
            return $fieldInstance->get_post();
        } elseif ($fieldType === 'date') {
            $timezone = wp_timezone();
            $value = $fieldInstance->get_value();
            foreach ($value as $i => $val) {
                $date = date_create("@{$val}")->setTimezone($timezone);
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

    public static function getPostType(string $post_type): ?WP_Post_Type
    {
        $postTypes = SourceHelper::getPostTypes();

        return $postTypes[$post_type] ?? null;
    }

    /**
     * @param Field $field
     */
    public static function isMultiple(array $field): bool
    {
        if (in_array($field['type'], ['checkboxes', 'relationship'])) {
            return count($field['data']['options'] ?? []) > 1;
        }

        return ($field['data']['repetitive'] ?? '') === '1';
    }

    public static function getDomainFromNode(object $node): ?string
    {
        if (empty($node->source->query->name)) {
            return null;
        }

        $type = app(Source::class)->getSchema()->getType('Query');

        foreach (explode('.', $node->source->query->name) as $part) {
            if (!$type->hasField($part)) {
                return null;
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

        return null;
    }
}
