<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Post_Type;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Str;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class PostQueryType
{
    /**
     * @param Source        $source
     * @param WP_Post_Type $type
     *
     * @return ObjectConfig
     */
    public static function config(Source $source, WP_Post_Type $type)
    {
        $name = Str::camelCase([SourceHelper::getBase($type), 'Query'], true);
        $field = Str::camelCase(SourceHelper::getBase($type));

        $source->objectType($name, fn() => SinglePostQueryType::config($type));
        $source->objectType($name, fn() => CustomPostQueryType::config($type));

        if ($type->has_archive || $type->name === 'post') {
            $source->objectType($name, fn() => PostArchiveQueryType::config($type));
        }

        if (!$type->exclude_from_search) {
            $source->objectType($name, fn() => PostSearchQueryType::config($type));
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
