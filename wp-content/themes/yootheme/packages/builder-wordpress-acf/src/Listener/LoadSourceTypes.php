<?php

namespace YOOtheme\Builder\Wordpress\Acf\Listener;

use YOOtheme\Builder\Wordpress\Acf\AcfHelper;
use YOOtheme\Builder\Wordpress\Acf\Type;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;

class LoadSourceTypes
{
    public static function handle($source): void
    {
        if (!AcfHelper::isActive()) {
            return;
        }

        $ignore = ['clone', 'flexible_content'];

        $source->objectType('LinkField', Type\LinkFieldType::config());
        $source->objectType('ValueField', Type\ValueFieldType::config());
        $source->objectType('ChoiceField', Type\ChoiceFieldType::config());
        $source->objectType('ChoiceFieldString', Type\ChoiceFieldStringType::config());
        $source->objectType('GoogleMapsField', Type\GoogleMapsFieldType::config());
        $source->objectType('FileField', Type\FileFieldType::config());

        foreach (['attachment', 'user'] as $type) {
            if ($fields = AcfHelper::fields($type, '', $ignore)) {
                static::configFields($source, $type, $fields);
            }
        }

        foreach (Helper::getPostTypes() as $type) {
            if ($fields = AcfHelper::fields('post', $type->name, $ignore)) {
                static::configFields($source, $type->name, $fields);
            }
        }

        foreach (Helper::getTaxonomies() as $taxonomy) {
            if ($fields = AcfHelper::fields('term', $taxonomy->name, $ignore)) {
                static::configFields($source, $taxonomy->name, $fields);
            }
        }
    }

    protected static function configFields($source, string $name, array $fields): void
    {
        $type = Str::camelCase([$name, 'Fields'], true);

        // add field on type
        $source->objectType(Str::camelCase($name, true), [
            'fields' => [
                'field' => [
                    'type' => $type,
                    'extensions' => [
                        'call' => Type\FieldsType::class . '::field',
                    ],
                ],
            ],
        ]);

        // configure field type
        $source->objectType(
            $type,
            Type\FieldsType::config($source, $fields, Str::camelCase($name, true)),
        );
    }
}
