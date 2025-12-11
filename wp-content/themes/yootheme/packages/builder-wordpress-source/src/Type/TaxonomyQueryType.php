<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Str;

class TaxonomyQueryType
{
    /**
     * @param Source       $source
     * @param \WP_Taxonomy $taxonomy
     * @param bool         $custom
     *
     * @return array
     */
    public static function config(Source $source, \WP_Taxonomy $taxonomy, $custom = true)
    {
        $name = Str::camelCase([SourceHelper::getBase($taxonomy), 'Query'], true);
        $field = Str::camelCase(SourceHelper::getBase($taxonomy));

        $source->objectType($name, TaxonomyArchiveQueryType::config($taxonomy));

        if ($custom) {
            $source->objectType($name, CustomTaxonomyQueryType::config($taxonomy));
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

    public static function resolve($root)
    {
        return $root;
    }
}
