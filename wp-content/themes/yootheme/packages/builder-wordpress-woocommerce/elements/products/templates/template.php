<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use YOOtheme\Arr;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-products',
    ],

]);

// temporary remove filters
if ($props['show_title'] === false) {
    $title = Helper::removeFilter('woocommerce_shop_loop_item_title');
}

if ($props['show_rating'] === false) {
    $rating = Helper::removeFilter('woocommerce_after_shop_loop_item_title', 5);
}

if ($props['type'] === 'current_query') {

    // temporary remove filters
    $pagination = Helper::removeFilter('woocommerce_after_shop_loop', 10);

    if ($props['show_count'] === false) {
        $count = Helper::removeFilter('woocommerce_before_shop_loop', 20);
    }

    if ($props['show_ordering'] === false) {
        $ordering = Helper::removeFilter('woocommerce_before_shop_loop', 30);
    }

    if ($props['columns']) {
        wc_set_loop_prop('columns', $props['columns']);
    }

    echo $el($props, $attrs, $this->render("{$__dir}/template-query"));

    // restore filters
    $pagination();

    if (isset($count)) {
        $count();
    }

    if (isset($ordering)) {
        $ordering();
    }

} else {

    if ($props['type'] === 'recently_viewed_products') {

        // parse cookie
        $cookie = 'woocommerce_recently_viewed';
        $viewed = !empty($_COOKIE[$cookie]) ? explode('|', wp_unslash($_COOKIE[$cookie])) : [];

        // update props
        $props = array_merge($props, [
            'ids' => implode(',', array_filter(array_map('absint', (array) $viewed))),
            'type' => 'products',
        ]);
    }

    $args = Arr::pick($props, ['limit', 'paginate', 'columns', 'orderby', 'order', 'category', 'cat_operator', 'tag', 'tag_operator', 'attribute', 'terms', 'terms_operator', 'skus', 'ids']);

    /*
     * @link https://woocommerce.com/document/woocommerce-shortcodes/#products
     */
    $shortcode = new \WC_Shortcode_Products($args + ['visibility' => $props['product_visibility']], $props['type']);

    echo $el($props, $attrs, $shortcode->get_content());
}

// restore filters
if (isset($title)) {
    $title();
}

if (isset($rating)) {
    $rating();
}
