<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use WC_Product;
use WP_Post;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\Source\Type\PostType;
use YOOtheme\View\HtmlElement;
use function YOOtheme\trans;

class ProductType
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
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
                        'call' => [static::class, 'resolveProducts'],
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

    /**
     * @param WP_Post $post
     */
    public static function excerpt($post): ?string
    {
        $excerpt = apply_filters('woocommerce_short_description', $post->post_excerpt);

        if (empty($excerpt)) {
            return null;
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

    /**
     * @param WP_Post $post
     */
    public static function featuredImage($post): int
    {
        if ($thumnail_id = get_post_thumbnail_id($post)) {
            return $thumnail_id;
        }

        $placeholder = get_option('woocommerce_placeholder_image', 0);
        return is_numeric($placeholder)
            ? (int) $placeholder
            : attachment_url_to_postid($placeholder);
    }

    /**
     * @param WP_Post $post
     * @return ?WC_Product
     */
    public static function resolve($post)
    {
        return wc_get_product($post) ?: null;
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return array<WP_Post>
     */
    public static function resolveProducts($post, $args): array
    {
        $exclude = [];

        if (!empty($args['exclude_upsell'])) {
            $exclude = wc_get_product($post)->get_upsell_ids();
        }

        if (!empty($args['exclude_cross_sell'])) {
            $exclude = array_merge($exclude ?: [], wc_get_product($post)->get_cross_sell_ids());
        }

        return PostType::relatedPosts($post, $args + ['exclude' => $exclude]);
    }
}
