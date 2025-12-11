<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use function YOOtheme\trans;

class ProductCategoryType
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

    public static function thumbnail($category)
    {
        return get_term_meta($category->term_id, 'thumbnail_id', true);
    }
}
