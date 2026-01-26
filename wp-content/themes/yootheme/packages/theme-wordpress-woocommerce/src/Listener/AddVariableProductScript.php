<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Metadata;

class AddVariableProductScript
{
    public Metadata $metadata;

    public function __construct(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Update variable product properties.
     */
    public function variableScript(): void
    {
        global $product;

        if ($product->is_type('variable')) {
            $this->metadata->set('script:woocommerce_variable_product', [
                'src' => '~assets/site/js/variable-product.js',
                'type' => 'module',
            ]);
        }
    }
}
