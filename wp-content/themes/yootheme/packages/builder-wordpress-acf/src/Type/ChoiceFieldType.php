<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class ChoiceFieldType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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
