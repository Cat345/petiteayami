<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use function YOOtheme\trans;

class ChoiceFieldType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'label' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Label'),
                    ],
                ],

                'value' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Value'),
                    ],
                ],
            ],
        ];
    }
}
