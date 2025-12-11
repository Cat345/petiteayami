<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use function YOOtheme\trans;

class ValueFieldType
{
    /**
     * @return array
     */
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
}
