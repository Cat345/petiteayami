<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Str;

class PostQueryType
{
    /**
     * @param Source        $source
     * @param \WP_Post_Type $type
     *
     * @return array
     */
    public static function config(Source $source, \WP_Post_Type $type)
    {
        $name = Str::camelCase([SourceHelper::getBase($type), 'Query'], true);
        $field = Str::camelCase(SourceHelper::getBase($type));

        $source->objectType($name, SinglePostQueryType::config($type));
        $source->objectType($name, CustomPostQueryType::config($type));

        if ($type->has_archive || $type->name === 'post') {
            $source->objectType($name, PostArchiveQueryType::config($type));
        }

        if (!$type->exclude_from_search) {
            $source->objectType($name, PostSearchQueryType::config($type));
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
