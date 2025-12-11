<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class UserQueryType
{
    /**
     * @return array
     */
    public static function config()
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

    public static function resolve()
    {
        return get_queried_object();
    }
}
