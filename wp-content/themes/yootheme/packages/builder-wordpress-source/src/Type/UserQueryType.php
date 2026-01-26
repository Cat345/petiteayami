<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_User;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class UserQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'authorArchive' => [
                    'type' => 'User',

                    'metadata' => [
                        'group' => trans('Page'),
                        'label' => trans('Author'),
                        'view' => ['author-archive'],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return ?WP_User
     */
    public static function resolve()
    {
        return get_queried_object();
    }
}
