<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

class RemoveSelect
{
    public static function handle(): void
    {
        wp_dequeue_style('select2');
        wp_deregister_style('select2');

        wp_dequeue_script('selectWoo');
        wp_deregister_script('selectWoo');

        // workaround for scripts depending on selectWoo
        wp_register_style('select2', false);
        wp_register_script('selectWoo', false);
    }
}
