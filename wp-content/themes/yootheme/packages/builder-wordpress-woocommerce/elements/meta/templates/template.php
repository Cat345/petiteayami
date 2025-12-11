<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Hook;

$el = $this->el('div', [

    'class' => [
        'uk-panel tm-element-woo-meta',
    ],

]);

echo $el($props, $attrs);

/**
 * Hook: woocommerce_single_product_summary.
 *
 * @hooked woocommerce_template_single_meta - 40
 * @hooked woocommerce_template_single_sharing - 50
 * @hooked WC_Structured_Data::generate_product_data() - 60
 */
Hook::doAction('woocommerce_single_product_summary', ['start' => 40]);

echo $el->end();
