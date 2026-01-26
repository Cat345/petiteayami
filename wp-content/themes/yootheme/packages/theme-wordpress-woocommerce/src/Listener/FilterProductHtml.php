<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\Wordpress\WooCommerce\ReviewWalker;

class FilterProductHtml
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Set the number of product cross-sell columns.
     *
     * @return string
     */
    public function crossSellsColumns()
    {
        return $this->config->get('~theme.woocommerce.cross_sells_columns', '2');
    }

    /**
     * Set the number of product thumbnail columns.
     *
     * @return string
     */
    public function thumbnailsColumns()
    {
        return $this->config->get('~theme.woocommerce.product_thumbnails_columns', '4');
    }

    /**
     * Adds custom ReviewWalker.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function reviewListArgs(array $args): array
    {
        return $args + ['walker' => new ReviewWalker()];
    }

    /**
     * Add custom classes to <input> tags in review comment form.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public static function reviewCommentArgs($form)
    {
        foreach ($form['fields'] as &$field) {
            $field = str_replace('<input ', '<input class="uk-input uk-form-width-large" ', $field);
        }

        return $form;
    }
}
