<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Listener;

use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Builder\Wordpress\Toolset\Type;
use YOOtheme\Str;

class LoadSourceTypes
{
    public static function handle($source): void
    {
        if (!Helper::isActive()) {
            return;
        }

        $source->objectType('ToolsetValueField', Type\ValueType::config());
        $source->objectType('ToolsetDateField', Type\ValueType::configDate());

        if (class_exists(\Toolset_Addon_Maps_Common::class)) {
            $source->objectType('ToolsetMapsField', Type\MapsFieldType::config());
        }

        // add user fields
        if ($fields = Helper::fieldsGroups('users')) {
            static::configFields($source, 'User', $fields);
        }

        // add post fields
        foreach (SourceHelper::getPostTypes() as $type) {
            if ($fields = Helper::fieldsGroups('posts', $type->name)) {
                static::configFields($source, $type->name, $fields);
            }

            if ($relationships = Helper::getRelationships($type->name)) {
                static::configRelationshipFields($source, $type->name, $relationships);
            }
        }

        // add taxonomy fields
        foreach (SourceHelper::getTaxonomies() as $taxonomy) {
            if ($fields = Helper::fieldsGroups('terms', $taxonomy->name)) {
                static::configFields($source, $taxonomy->name, $fields);
            }
        }
    }

    protected static function configFields($source, string $name, array $fields): void
    {
        $type = Str::camelCase([$name, 'Toolset'], true);

        // add field on type
        $source->objectType(Str::camelCase($name, true), [
            'fields' => [
                'toolset' => [
                    'type' => $type,
                    'extensions' => [
                        'call' => Type\FieldsType::class . '::toolset',
                    ],
                ],
            ],
        ]);

        $source->objectType($type, Type\FieldsType::config($source, $fields));
    }

    protected static function configRelationshipFields(
        $source,
        string $name,
        array $relationships
    ): void {
        $type = Str::camelCase([$name, 'Toolset'], true);

        // add field on type
        $source->objectType(Str::camelCase($name, true), [
            'fields' => [
                'toolset' => [
                    'type' => $type,
                    'extensions' => [
                        'call' => Type\FieldsType::class . '::toolset',
                    ],
                ],
            ],
        ]);

        $source->objectType(
            $type,
            Type\RelationshipFieldsType::config($source, $name, $relationships),
        );
    }
}
