<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class LinkFieldType
{
    /**
     * @return ObjectConfig
     */
    public static function config()
    {
        return [
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Text'),
                    ],
                ],

                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Url'),
                    ],
                ],
            ],
        ];
    }
}
