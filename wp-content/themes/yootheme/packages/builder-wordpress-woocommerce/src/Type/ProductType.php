<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\Source\Type\PostType;
use function YOOtheme\trans;
use YOOtheme\View\HtmlElement;

class ProductType
{
    public static function config()
    {
        return [
            'fields' => [
                'excerpt' => [
                    'metadata' => [
                        'label' => trans('Short Description'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::excerpt',
                    ],
                ],
                'featuredImage' => [
                    'metadata' => [
                        'label' => trans('Product Image'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::featuredImage',
                    ],
                ],
                'relatedProducts' => [
                    'args' => [
                        'exclude_upsell' => [
                            'type' => 'Boolean',
                        ],
                        'exclude_cross_sell' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'arguments' => [
                            'exclude_upsell' => [
                                'type' => 'checkbox',
                                'text' => trans('Exclude upsell products'),
                            ],
                            'exclude_cross_sell' => [
                                'type' => 'checkbox',
                                'text' => trans('Exclude cross sell products'),
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolveProducts',
                        ],
                    ],
                ],
                'woocommerce' => [
                    'type' => 'WoocommerceFields',
                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    public static function excerpt($post)
    {
        $excerpt = apply_filters('woocommerce_short_description', $post->post_excerpt);

        if (empty($excerpt)) {
            return;
        }

        return HtmlElement::tag(
            'div',
            [
                'class' => [
                    'uk-panel tm-source-woo-description',
                    'tm-source-page' => Helper::isPageSource($post),
                ],
            ],
            $excerpt,
        );
    }

    public static function featuredImage($post)
    {
        if ($thumnail_id = get_post_thumbnail_id($post)) {
            return $thumnail_id;
        }

        $placeholder = get_option('woocommerce_placeholder_image', 0);
        return is_numeric($placeholder) ? $placeholder : attachment_url_to_postid($placeholder);
    }

    public static function resolve($post)
    {
        return wc_get_product($post) ?: null;
    }

    public static function resolveProducts($post, $args)
    {
        $exclude = [];

        if (!empty($args['exclude_upsell'])) {
            $exclude = wc_get_product($post)->get_upsell_ids();
        }

        if (!empty($args['exclude_cross_sell'])) {
            $exclude = array_merge($exclude ?: [], wc_get_product($post)->get_cross_sell_ids());
        }

        return PostType::relatedPosts($post, $args + compact('exclude'));
    }
}
