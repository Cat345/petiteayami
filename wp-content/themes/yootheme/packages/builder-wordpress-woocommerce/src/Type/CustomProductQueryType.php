<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use WP_Post;
use YOOtheme\Builder\Wordpress\Source\Type\CustomPostQueryType;
use function YOOtheme\trans;

class CustomProductQueryType
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
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
                        'call' => [static::class, 'resolveProduct'],
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
                        'call' => [static::class, 'resolveProducts'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return ?WP_Post
     */
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

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return array<WP_Post>
     */
    public static function resolveProducts($root, array $args): array
    {
        if (!empty($args['on_sale'])) {
            $args['include'] = wc_get_product_ids_on_sale();
        }

        return CustomPostQueryType::resolvePosts($root, $args);
    }
}
