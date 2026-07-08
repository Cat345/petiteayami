<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_Query;
use WP_User;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class SiteQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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

    /**
     * @return array{title: null|string, page_title: null|string, user: null|WP_User, is_guest: bool, item_count: int}
     */
    public static function resolve(): array
    {
        /** @var WP_Query $wp_query */
        global $wp_query;

        $user = wp_get_current_user();

        return [
            'title' => get_bloginfo('name', 'display'),
            'page_title' => wp_title('&raquo;', false),
            'user' => $user->ID !== 0 ? $user : null,
            'is_guest' => ((int) $user->ID) === 0,
            'item_count' => $wp_query->found_posts,
        ];
    }
}
