<?php

namespace YOOtheme\Builder\Wordpress\Acf;

/**
 * @phpstan-type Field array{
 *     ID: int,
 *     key: string,
 *     label: string,
 *     name: string,
 *     type: string,
 *     menu_order: int,
 *     parent: int,
 *     required: bool,
 *     field_type: string,
 *     group: array<string, mixed>,
 *     choices?: array<string, string>,
 *     sub_fields?: array<mixed>,
 *     post_type?: array<mixed>,
 *     multiple?: bool,
 *     max?: int,
 *     field_type?: string,
 *     taxonomy?: string
 * }
 */
class AcfHelper
{
    public static function isActive(): bool
    {
        return function_exists('acf_get_fields');
    }

    /**
     * @param list<string> $ignore
     *
     * @return array<string, Field>
     */
    public static function fields(string $type, string $name = '', array $ignore = []): array
    {
        $fields = [];

        foreach (static::groups($type, $name) as $group) {
            foreach (acf_get_fields($group) as $field) {
                if (!in_array($field['type'], $ignore) && !empty($field['name'])) {
                    $fields[$field['name']] = $field + compact('group');
                }
            }
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function groups(string $type, string $name): array
    {
        $groups = [];

        foreach (acf_get_field_groups() as $group) {
            if (self::matchGroup($group, $type, $name)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $group
     */
    protected static function matchGroup(array $group, string $type, string $name): bool
    {
        foreach ($group['location'] as $rules) {
            foreach ($rules ?: [] as $rule) {
                if (
                    ($type === 'post' &&
                        $rule['param'] === 'post_type' &&
                        acf_match_location_rule($rule, ['post_type' => $name], $group)) ||
                    ($type === 'term' &&
                        $rule['param'] === 'taxonomy' &&
                        acf_match_location_rule($rule, ['taxonomy' => $name], $group)) ||
                    ($type === 'user' &&
                        $rule['operator'] === '==' &&
                        $rule['value'] === 'all' &&
                        in_array($rule['param'], ['user_role', 'user_form'])) ||
                    ($type === 'attachment' &&
                        $rule['param'] === 'attachment' &&
                        $rule['operator'] === '==')
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
