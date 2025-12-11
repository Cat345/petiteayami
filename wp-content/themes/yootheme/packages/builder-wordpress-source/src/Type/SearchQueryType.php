<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class SearchQueryType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'search' => [
                    'type' => 'Search',
                    'metadata' => [
                        'label' => trans('Search'),
                        'view' => ['search', '_search'],
                        'group' => trans('Page'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    public static function resolve($root, array $args)
    {
        return $root;
    }
}
