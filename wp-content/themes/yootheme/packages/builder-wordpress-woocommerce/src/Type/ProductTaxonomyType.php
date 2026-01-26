<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use WP_Term;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class ProductTaxonomyType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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

    /**
     * @return mixed
     */
    public static function thumbnail(WP_Term $term)
    {
        return get_term_meta($term->term_id, 'thumbnail_id', true);
    }
}
