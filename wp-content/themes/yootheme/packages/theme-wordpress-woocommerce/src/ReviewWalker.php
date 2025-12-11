<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce;

use YOOtheme\Str;

class ReviewWalker extends \Walker_Comment
{
    /**
     * @inheritdoc
     */
    public function start_el(&$output, $comment, $depth = 0, $args = [], $id = 0)
    {
        parent::start_el($output, $comment, $depth, $args, $id);

        // Workaround for the woocommerce singe-product/review-meta.php template
        $output = preg_replace_callback(
            '/\((' . preg_quote(esc_attr__('verified owner', 'woocommerce')) . ')\)/',
            fn($matches) => Str::titleCase($matches[1]),
            $output,
        );
    }
}
