<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class SiteQueryType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'site' => [
                    'type' => 'Site',
                    'metadata' => [
                        'label' => trans('Site'),
                        'group' => trans('Global'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    public static function resolve()
    {
        global $wp_query;

        $user = wp_get_current_user();

        return [
            'title' => get_bloginfo('name', 'display'),
            'page_title' => wp_title('&raquo;', false),
            'user' => $user->ID !== 0 ? $user : null,
            'is_guest' => (int) $user->ID === 0,
            'item_count' => (int) $wp_query->found_posts,
        ];
    }
}
