<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use function YOOtheme\trans;

class ValueType
{
    public static function config()
    {
        return [
            'fields' => [
                'value' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Value'),
                    ],
                ],
            ],
        ];
    }

    public static function configDate()
    {
        return [
            'fields' => [
                'value' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Date'),
                        'filters' => ['date'],
                    ],
                ],
            ],
        ];
    }
}
