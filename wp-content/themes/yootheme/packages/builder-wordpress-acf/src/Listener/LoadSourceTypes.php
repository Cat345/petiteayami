<?php

namespace YOOtheme\Builder\Wordpress\Acf\Listener;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Acf\AcfHelper;
use YOOtheme\Builder\Wordpress\Acf\Type;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Str;

/**
 * @phpstan-import-type Field from AcfHelper
 */
class LoadSourceTypes
{
    /**
     * @param Source $source
     */
    public static function handle($source): void
    {
        if (!AcfHelper::isActive()) {
            return;
        }

        $source->objectType('LinkField', [Type\LinkFieldType::class, 'config']);
        $source->objectType('ValueField', [Type\ValueFieldType::class, 'config']);
        $source->objectType('ChoiceField', [Type\ChoiceFieldType::class, 'config']);
        $source->objectType('ChoiceFieldString', [Type\ChoiceFieldStringType::class, 'config']);
        $source->objectType('GoogleMapsField', [Type\GoogleMapsFieldType::class, 'config']);
        $source->objectType('FileField', [Type\FileFieldType::class, 'config']);

        foreach (['attachment', 'user'] as $type) {
            static::configFields($source, $type, $type, '');
        }

        foreach (Helper::getPostTypes() as $type) {
            static::configFields($source, $type->name, 'post', $type->name);
        }

        foreach (Helper::getTaxonomies() as $taxonomy) {
            static::configFields($source, $taxonomy->name, 'term', $taxonomy->name);
        }

        if (is_callable('acf_get_ui_options_pages')) {
            foreach (acf_get_ui_options_pages(['active' => true]) as $optionsPage) {
                static::configFields($source, 'Site', 'option', $optionsPage['menu_slug']);
            }
        }
    }

    protected static function configFields(Source $source, string $type, string ...$args): void
    {
        $type = Str::camelCase($type, true);
        $fieldType = "{$type}Fields";

        // add field on type
        $source->objectType($type, [
            'fields' => [
                'field' => [
                    'type' => $fieldType,
                    'extensions' => [
                        'call' => Type\FieldsType::class . '::field',
                    ],
                ],
            ],
        ]);

        // configure field type
        $source->objectType(
            $fieldType,
            fn() => Type\FieldsType::config(
                $source,
                $source->getType($type),
                AcfHelper::fields(...array_merge($args, [['clone', 'flexible_content']])),
            ),
        );
    }
}
