<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Query;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class SearchType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'searchword' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Search Word'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::searchQuery',
                    ],
                ],

                'total' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Item Count'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::foundPosts',
                    ],
                ],

                'link' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Link'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::link',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
            ],
        ];
    }

    public static function searchQuery(): string
    {
        return get_search_query();
    }

    public static function foundPosts(): int
    {
        /** @var WP_Query $wp_query */
        global $wp_query;

        return is_search() && get_search_query() ? $wp_query->found_posts : 0;
    }

    public static function link(): string
    {
        return get_search_link();
    }
}
