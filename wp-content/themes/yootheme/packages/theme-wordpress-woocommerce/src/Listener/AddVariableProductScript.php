<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Metadata;
use YOOtheme\Path;

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
    public function variableScript()
    {
        global $product;

        if ($product->is_type('variable')) {
            $this->metadata->set('script:woocommerce_variable_product', [
                'src' => Path::get('../../assets/js/variable-product.min.js', __DIR__),
                'defer' => true,
            ]);
        }
    }
}
