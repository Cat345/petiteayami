<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class DateType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'date' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::date',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
                'label' => trans('Date'),
            ],
        ];
    }

    public static function date()
    {
        return get_the_date();
    }
}
