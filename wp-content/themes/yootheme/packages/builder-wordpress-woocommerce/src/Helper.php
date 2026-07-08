<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use Automattic\Jetpack\Constants;
use Closure;
use WC_Product;
use WP_Taxonomy;
use WP_Widget;

class Helper
{
    public static function addFilter(
        string $name,
        callable $fn,
        int $priority = 10,
        int $args = 1
    ): Closure {
        add_filter($name, $fn, $priority, $args);

        return fn() => remove_filter($name, $fn, $priority);
    }

    /**
     * @param int|false $priority
     */
    public static function removeFilter(string $name, $priority = false): Closure
    {
        global $wp_filter;

        if ($filter = $wp_filter[$name] ?? null) {
            $clone = $wp_filter[$name] = clone $filter;
            $clone->remove_all_filters($priority);
        }

        return function () use (&$wp_filter, $name, $filter) {
            return $wp_filter[$name] = $filter;
        };
    }

    /**
     * @param list<mixed> $args
     * @return false|string
     */
    public static function renderTemplate(callable $function, array $args = [])
    {
        ob_start();

        $function(...$args);

        return ob_get_clean();
    }

    /**
     * @param WC_Product $product
     */
    public static function isPageSource($product): bool
    {
        return absint(get_the_ID()) === $product->get_id();
    }

    /**
     * @param string|WP_Widget $type
     * @param array<string, mixed> $options
     */
    public static function renderWidget($type, array $options = []): string
    {
        if ($type instanceof WP_Widget) {
            $widget = $type;
        } else {
            global $wp_widget_factory;

            $widget = $wp_widget_factory->widgets[$type];
        }

        if (!$widget) {
            return '';
        }

        ob_start();

        $widget->widget(
            [
                'before_widget' => '',
                'after_widget' => '',
                'before_title' => '',
                'after_title' => '',
            ],
            $options + ['title' => ''],
        );

        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function renderLayeredNavWidget(array $options = []): string
    {
        // @see https://github.com/woocommerce/woocommerce/issues/17355
        $filter = 'woocommerce_layered_nav_count_maybe_cache';
        $removeFilter = static::addFilter($filter, fn() => false);

        if ($options['attribute'] === 'product_brand') {
            $result = static::renderWidget(new WidgetBrandsLayeredNav(), $options);
        } else {
            $result = static::renderWidget(new WidgetLayeredNav(), $options);
        }

        $removeFilter();

        return $result;
    }

    /**
     * @return list<WP_Taxonomy>
     */
    public static function getAttributeTaxonomies(): array
    {
        $taxonomies = [];

        foreach (wc_get_attribute_taxonomy_names() as $name) {
            $taxonomy = get_taxonomy($name);

            if ($taxonomy) {
                $taxonomies[$name] = $taxonomy;
            }
        }

        return $taxonomies;
    }

    public static function getCurrentPageUrl(): string
    {
        // @see wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-widget.php
        if (Constants::is_defined('SHOP_IS_ON_FRONT')) {
            $link = home_url();
        } elseif (is_shop()) {
            $link = get_permalink(wc_get_page_id('shop'));
        } elseif (is_product_category()) {
            $link = get_term_link(get_query_var('product_cat'), 'product_cat');
        } elseif (is_product_tag()) {
            $link = get_term_link(get_query_var('product_tag'), 'product_tag');
        } else {
            $queried_object = get_queried_object();
            $link = isset($queried_object->slug, $queried_object->taxonomy)
                ? get_term_link($queried_object->slug, $queried_object->taxonomy)
                : '';
        }

        // Post Type Arg.
        if (isset($_GET['post_type'])) {
            $link = add_query_arg('post_type', wc_clean(wp_unslash($_GET['post_type'])), $link);

            // Prevent post type and page id when pretty permalinks are disabled.
            if (is_shop()) {
                $link = remove_query_arg('page_id', $link);
            }
        }

        return static::addProductTagToCurrentPageUrl($link);
    }

    public static function addProductTagToCurrentPageUrl(string $url): string
    {
        // Preserve `product_tag` query var, when on product category page.
        if (is_product_category() && ($value = get_query_var('product_tag'))) {
            $url = add_query_arg('product_tag', $value, $url);
        }

        return $url;
    }
}
