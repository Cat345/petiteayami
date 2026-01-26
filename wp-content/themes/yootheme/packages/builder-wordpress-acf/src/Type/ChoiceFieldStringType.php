<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class ChoiceFieldStringType
{
    /**
     * @return ObjectConfig
     */
    public static function config()
    {
        $field = [
            'type' => 'String',
            'args' => [
                'separator' => [
                    'type' => 'String',
                ],
            ],
            'metadata' => [
                'arguments' => [
                    'separator' => [
                        'label' => trans('Separator'),
                        'description' => trans('Set the separator between fields.'),
                        'default' => ', ',
                    ],
                ],
            ],
            'extensions' => [
                'call' => __CLASS__ . '::resolve',
            ],
        ];

        return [
            'fields' => [
                'label' => array_merge_recursive($field, [
                    'metadata' => [
                        'label' => trans('Labels'),
                    ],
                ]),

                'value' => array_merge_recursive($field, [
                    'metadata' => [
                        'label' => trans('Values'),
                    ],
                ]),
            ],
        ];
    }

    /**
     * @param array<\stdClass> $item
     * @param array<string>    $args
     * @param mixed            $context
     */
    public static function resolve($item, $args, $context, object $info): string
    {
        $args += ['separator' => ', '];

        return join($args['separator'], array_column($item, $info->fieldName));
    }
}
