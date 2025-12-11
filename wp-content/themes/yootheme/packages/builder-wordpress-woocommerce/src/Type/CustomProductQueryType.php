<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use YOOtheme\Builder\Wordpress\Source\Type\CustomPostQueryType;
use function YOOtheme\trans;

class CustomProductQueryType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'customProduct' => [
                    'args' => [
                        'on_sale' => [
                            'type' => 'Boolean',
                        ],
                    ],

                    'metadata' => [
                        'fields' => [
                            'on_sale' => [
                                'label' => trans('Limit by Products on sale'),
                                'type' => 'checkbox',
                                'text' => trans('Load product on sale only'),
                                'enable' => '!id',
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveProduct',
                        ],
                    ],
                ],

                'customProducts' => [
                    'args' => [
                        'on_sale' => [
                            'type' => 'Boolean',
                        ],
                    ],

                    'metadata' => [
                        'fields' => [
                            'on_sale' => [
                                'label' => trans('Limit by Products on sale'),
                                'type' => 'checkbox',
                                'text' => trans('Load products on sale only'),
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveProducts',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function resolveProduct($root, array $args)
    {
        if (!empty($args['id'])) {
            return CustomPostQueryType::resolvePost($root, $args);
        }

        if (!empty($args['on_sale'])) {
            $args['include'] = wc_get_product_ids_on_sale();
        }

        return CustomPostQueryType::resolvePost($root, $args);
    }

    public static function resolveProducts($root, array $args)
    {
        if (!empty($args['on_sale'])) {
            $args['include'] = wc_get_product_ids_on_sale();
        }

        return CustomPostQueryType::resolvePosts($root, $args);
    }
}
