<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class DateQueryType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'date' => [
                    'type' => 'Date',
                    'metadata' => [
                        'label' => trans('Date'),
                        'view' => ['date-archive'],
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
