<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use function YOOtheme\trans;

class ProductBrandType
{
    public static function config()
    {
        return [
            'fields' => [
                'thumbnail' => [
                    'type' => 'Attachment',
                    'metadata' => [
                        'label' => trans('Thumbnail'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::thumbnail',
                    ],
                ],
            ],
        ];
    }

    public static function thumbnail($term)
    {
        return get_term_meta($term->term_id, 'thumbnail_id', true);
    }
}
