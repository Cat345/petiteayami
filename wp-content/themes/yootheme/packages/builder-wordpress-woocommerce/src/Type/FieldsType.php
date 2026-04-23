<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use WC_Product;
use WP_Post;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Helper as PostsHelper;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Str;
use YOOtheme\View\HtmlElement;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class FieldsType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'sku' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => __('SKU', 'woocommerce'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::sku',
                    ],
                ],
                'price' => [
                    'type' => 'String',
                    'args' => [
                        'type' => [
                            'type' => 'String',
                            'defaultValue' => '',
                        ],
                    ],
                    'metadata' => [
                        'label' => __('Price', 'woocommerce'),
                        'group' => 'WooCommerce',
                        'arguments' => [
                            'type' => [
                                'label' => trans('Price Type'),
                                'description' => trans(
                                    'Show the active price, or only the sale or regular price.',
                                ),
                                'type' => 'select',
                                'options' => [
                                    trans('Default') => '',
                                    trans('Sale price only') => 'sale',
                                    trans('Regular price only') => 'regular',
                                ],
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::price',
                    ],
                ],
                'stock' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => __('Stock', 'woocommerce'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::stock',
                    ],
                ],
                'rating' => [
                    'type' => 'String',
                    'args' => [
                        'link' => [
                            'type' => 'Boolean',
                            'defaultValue' => true,
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Rating'),
                        'group' => 'WooCommerce',
                        'arguments' => [
                            'link' => [
                                'label' => trans('Display'),
                                'description' => trans('Show or hide the reviews link.'),
                                'type' => 'checkbox',
                                'text' => trans('Show reviews link'),
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::rating',
                    ],
                ],
                'on_sale' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => __('On Sale', 'woocommerce'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::isOnSale',
                    ],
                ],
                'total_sales' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => __('Total Sales', 'woocommerce'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::totalSales',
                    ],
                ],
                'add_to_cart_url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Add to Cart Link'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::addToCartUrl',
                    ],
                ],
                'add_to_cart_text' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Add to Cart Text'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::addToCartText',
                    ],
                ],
                'additional_information' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Additional Information'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::additionalInformation',
                    ],
                ],
                'attributes' => [
                    'type' => ['listOf' => 'AttributeField'],
                    'metadata' => [
                        'label' => trans('Attributes'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::attributes',
                    ],
                ],
                'gallery_image_ids' => [
                    'type' => ['listOf' => 'Attachment'],
                    'metadata' => [
                        'label' => trans('Product Gallery'),
                        'group' => 'WooCommerce',
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::galleryImageIds',
                    ],
                ],
                'upsell_products' => [
                    'type' => ['listOf' => 'Product'],
                    'args' => [
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => 10,
                        ],
                        'order' => [
                            'type' => 'String',
                            'defaultValue' => 'rand',
                        ],
                        'order_direction' => [
                            'type' => 'String',
                            'defaultValue' => 'DESC',
                        ],
                        'order_alphanum' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Upsell Products'),
                        'group' => 'WooCommerce',
                        'arguments' => [
                            '_offset' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'min' => 1,
                                        ],
                                    ],
                                ],
                            ],

                            '_order' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'order' => [
                                        'label' => trans('Order'),
                                        'type' => 'select',
                                        'options' => [
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.postTypeOrderOptions',
                                            ],
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.productOrderOptions',
                                            ],
                                        ],
                                    ],
                                    'order_direction' => [
                                        'label' => trans('Direction'),
                                        'type' => 'select',
                                        'options' => [
                                            trans('Ascending') => 'ASC',
                                            trans('Descending') => 'DESC',
                                        ],
                                    ],
                                ],
                            ],

                            'order_alphanum' => [
                                'text' => trans('Alphanumeric Ordering'),
                                'type' => 'checkbox',
                            ],
                        ],
                        'directives' => [],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::upsellProducts',
                    ],
                ],
                'cross_sell_products' => [
                    'type' => ['listOf' => 'Product'],
                    'args' => [
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => 10,
                        ],
                        'order' => [
                            'type' => 'String',
                            'defaultValue' => 'rand',
                        ],
                        'order_direction' => [
                            'type' => 'String',
                            'defaultValue' => 'DESC',
                        ],
                        'order_alphanum' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Cross-Sell Products'),
                        'group' => 'WooCommerce',
                        'arguments' => [
                            '_offset' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'min' => 1,
                                        ],
                                    ],
                                ],
                            ],

                            '_order' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'order' => [
                                        'label' => trans('Order'),
                                        'type' => 'select',
                                        'options' => [
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.postTypeOrderOptions',
                                            ],
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.productOrderOptions',
                                            ],
                                        ],
                                    ],
                                    'order_direction' => [
                                        'label' => trans('Direction'),
                                        'type' => 'select',
                                        'options' => [
                                            'Ascending' => 'ASC',
                                            'Descending' => 'DESC',
                                        ],
                                    ],
                                ],
                            ],

                            'order_alphanum' => [
                                'text' => trans('Alphanumeric Ordering'),
                                'type' => 'checkbox',
                            ],
                        ],
                        'directives' => [],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::crossSellProducts',
                    ],
                ],
                'grouped_products' => [
                    'type' => ['listOf' => 'Product'],
                    'args' => [
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => 10,
                        ],
                        'order' => [
                            'type' => 'String',
                            'defaultValue' => 'menu_order',
                        ],
                        'order_direction' => [
                            'type' => 'String',
                            'defaultValue' => 'DESC',
                        ],
                        'order_alphanum' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Grouped Products'),
                        'group' => 'WooCommerce',
                        'arguments' => [
                            '_offset' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'min' => 1,
                                        ],
                                    ],
                                ],
                            ],

                            '_order' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'order' => [
                                        'label' => trans('Order'),
                                        'type' => 'select',
                                        'options' => [
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.postTypeOrderOptions',
                                            ],
                                            [
                                                'evaluate' =>
                                                    'yootheme.builder.sources.productOrderOptions',
                                            ],
                                        ],
                                    ],
                                    'order_direction' => [
                                        'label' => trans('Direction'),
                                        'type' => 'select',
                                        'options' => [
                                            'Ascending' => 'ASC',
                                            'Descending' => 'DESC',
                                        ],
                                    ],
                                ],
                            ],

                            'order_alphanum' => [
                                'text' => trans('Alphanumeric Ordering'),
                                'type' => 'checkbox',
                            ],
                        ],
                        'directives' => [],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::groupedProducts',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param WC_Product $product
     * @return int
     */
    public static function totalSales($product)
    {
        return $product->get_total_sales();
    }

    /**
     * @param WC_Product $product
     * @return ?string
     */
    public static function sku($product)
    {
        $sku = $product->get_sku();

        if (empty($sku)) {
            return null;
        }

        return HtmlElement::tag(
            'div',
            [
                'class' => [
                    'tm-source-woo-sku',
                    'tm-source-page' => Helper::isPageSource($product),
                ],
            ],
            $sku,
        );
    }

    /**
     * @param WC_Product $product
     * @param array<string, mixed> $args
     * @return ?string
     */
    public static function price($product, $args)
    {
        if ($args['type']) {
            $price =
                $args['type'] === 'regular'
                    ? $product->get_regular_price()
                    : $product->get_sale_price();

            if (empty($price)) {
                return null;
            }

            $price =
                wc_price(wc_get_price_to_display($product, ['price' => $price])) .
                $product->get_price_suffix();
        } else {
            $price = $product->get_price_html();
        }

        if (empty($price)) {
            return null;
        }

        return HtmlElement::tag(
            'div',
            [
                'class' => [
                    'tm-source-woo-price',
                    'tm-source-page' => Helper::isPageSource($product),
                    static::applyFilters($product, 'woocommerce_product_price_class', 'price'),
                ],
            ],
            $price,
        );
    }

    /**
     * @param WC_Product $product
     * @return ?string
     */
    public static function stock($product)
    {
        $stock = wc_get_stock_html($product);

        if (empty($stock)) {
            return null;
        }

        return HtmlElement::tag(
            'div',
            [
                'class' => [
                    'uk-panel tm-source-woo-stock',
                    'tm-source-page' => Helper::isPageSource($product),
                ],
            ],
            $stock,
        );
    }

    /**
     * @param WC_Product $product
     * @param array<string, mixed> $args
     * @return ?string
     */
    public static function rating($product, $args)
    {
        $count = $product->get_rating_count();
        $average = $product->get_average_rating();

        if (!wc_review_ratings_enabled() || !$count) {
            return null;
        }
        $rating = wc_get_rating_html($average, $count);

        if ($args['link'] && comments_open($product->get_id())) {
            $review_count = $product->get_review_count();
            $review_msg = _n(
                '%s customer review',
                '%s customer reviews',
                $review_count,
                'woocommerce',
            );
            $review = sprintf(
                $review_msg,
                '<span class="count">' . esc_html((string) $review_count) . '</span>',
            );

            $link = '#reviews';
            if (!Helper::isPageSource($product)) {
                $link = get_permalink($product->get_id()) . $link;
            }

            $rating .= sprintf(
                ' <a href="%s" class="woocommerce-review-link" rel="nofollow">(%s)</a>',
                $link,
                $review,
            );
        }

        if (empty($rating)) {
            return null;
        }

        return HtmlElement::tag(
            'div',
            [
                'class' => [
                    'tm-source-woo-rating',
                    'tm-source-page' => Helper::isPageSource($product),
                ],
            ],
            $rating,
        );
    }

    /**
     * @param WC_Product $product
     * @return bool
     */
    public static function isOnSale($product)
    {
        return $product->is_on_sale();
    }

    /**
     * @param WC_Product $product
     * @return string
     */
    public static function addToCartUrl($product)
    {
        return $product->add_to_cart_url();
    }

    /**
     * @param WC_Product $product
     * @return ?string
     */
    public static function addToCartText($product)
    {
        return $product->add_to_cart_text();
    }

    /**
     * @param WC_Product $product
     * @return ?string
     */
    public static function additionalInformation($product)
    {
        return Helper::renderTemplate('do_action', [
            'woocommerce_product_additional_information',
            $product,
        ]) ?:
            null;
    }

    /**
     * @param WC_Product $product
     * @return array<string, array{name: string, value: string}|object>
     *
     * @see wc_display_product_attributes
     */
    public static function attributes($product): array
    {
        $attributes = [];

        // Display weight and dimensions before attribute list.
        if (
            apply_filters(
                'wc_product_enable_dimensions_display',
                $product->has_weight() || $product->has_dimensions(),
            )
        ) {
            if ($product->has_weight()) {
                $attributes['weight'] = [
                    'name' => __('Weight', 'woocommerce'),
                    // @phpstan-ignore argument.type
                    'value' => wc_format_weight($product->get_weight()),
                ];
            }

            if ($product->has_dimensions()) {
                $attributes['dimensions'] = [
                    'name' => __('Dimensions', 'woocommerce'),
                    'value' => wc_format_dimensions($product->get_dimensions(false)),
                ];
            }
        }

        return $attributes +
            array_map(
                fn($attribute) => (object) [$product, $attribute],
                array_filter($product->get_attributes(), 'wc_attributes_array_filter_visible'),
            );
    }

    /**
     * @param WC_Product $product
     * @return array<int>
     */
    public static function galleryImageIds($product)
    {
        return $product->get_gallery_image_ids();
    }

    /**
     * @param WC_Product $product
     * @param array<string, mixed> $args
     * @return ?list<WP_Post>
     */
    public static function upsellProducts($product, $args): ?array
    {
        return static::getProducts($product->get_upsell_ids(), $args);
    }

    /**
     * @param WC_Product $product
     * @param array<string, mixed> $args
     * @return ?list<WP_Post>
     */
    public static function crossSellProducts($product, $args): ?array
    {
        return static::getProducts($product->get_cross_sell_ids(), $args);
    }

    /**
     * @param WC_Product $product
     * @param array<string, mixed> $args
     * @return ?list<WP_Post>
     */
    public static function groupedProducts($product, $args): ?array
    {
        return static::getProducts($product->get_children(), $args);
    }

    /**
     * @param array<int> $ids
     * @param array<string, mixed> $args
     * @return ?list<WP_Post>
     */
    protected static function getProducts($ids, $args): ?array
    {
        if (empty($ids)) {
            return null;
        }

        return PostsHelper::getPosts(
            $args + [
                'post_type' => 'product',
                'include' => $ids,
            ],
        );
    }

    /**
     * @param WC_Product $productObj
     * @param mixed ...$args
     * @return mixed
     */
    protected static function applyFilters($productObj, ...$args)
    {
        global $product;

        $temp = $product;
        $product = $productObj;

        $res = apply_filters(...$args);

        $product = $temp;

        return $res;
    }

    /* Legacy fix for backwards compatibility. */
    /**
     * @param string $name
     * @param list<mixed> $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (is_callable($fn = [static::class, Str::camelCase($name)])) {
            return $fn(...$arguments);
        }
        return null;
    }
}
