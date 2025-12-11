<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use Automattic\Jetpack\Constants;

class Helper
{
    public static function addFilter($name, $fn, $priority = 10, $args = 1)
    {
        add_filter($name, $fn, $priority, $args);

        return function () use ($name, $fn, $priority) {
            remove_filter($name, $fn, $priority);
        };
    }

    public static function removeFilter($name, $priority = false)
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

    public static function renderTemplate(callable $function, array $args = [])
    {
        ob_start();

        $function(...$args);

        return ob_get_clean();
    }

    public static function isPageSource($product)
    {
        return absint(get_the_ID()) === $product->get_id();
    }

    public static function renderWidget($type, $options = [])
    {
        if ($type instanceof \WP_Widget) {
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

    public static function renderLayeredNavWidget($options = [])
    {
        // @see https://github.com/woocommerce/woocommerce/issues/17355
        $filter = 'woocommerce_layered_nav_count_maybe_cache';
        $removeFilter = static::addFilter($filter, fn() => false);

        $result = static::renderWidget(new WidgetLayeredNav(), $options);

        $removeFilter();

        return $result;
    }

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

    public static function addProductTagToCurrentPageUrl($url): string
    {
        // Preserve `product_tag` query var, when on product category page.
        if (is_product_category() && ($value = get_query_var('product_tag'))) {
            $url = add_query_arg('product_tag', $value, $url);
        }

        return $url;
    }
}
