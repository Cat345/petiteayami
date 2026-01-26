<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Taxonomy;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Str;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class TaxonomyQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(Source $source, WP_Taxonomy $taxonomy, bool $custom = true): array
    {
        $name = Str::camelCase([SourceHelper::getBase($taxonomy), 'Query'], true);
        $field = Str::camelCase(SourceHelper::getBase($taxonomy));

        $source->objectType($name, fn() => TaxonomyArchiveQueryType::config($taxonomy));

        if ($custom) {
            $source->objectType($name, fn() => CustomTaxonomyQueryType::config($taxonomy));
        }

        return [
            'fields' => [
                $field => [
                    'type' => $name,
                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @return array<string, mixed>
     */
    public static function resolve($root)
    {
        return $root;
    }
}
