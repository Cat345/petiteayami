<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class SearchType
{
    /**
     * @return array
     */
    public static function config()
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
                'label' => trans('Search'),
            ],
        ];
    }

    public static function searchQuery()
    {
        return get_search_query();
    }

    public static function foundPosts()
    {
        global $wp_query;

        return is_search() && get_search_query() ? $wp_query->found_posts : 0;
    }

    public static function link()
    {
        return get_search_link();
    }
}
