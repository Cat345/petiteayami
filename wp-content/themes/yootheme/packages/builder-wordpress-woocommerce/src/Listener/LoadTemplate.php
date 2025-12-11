<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use WooCommerce;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Str;
use YOOtheme\View;

class LoadTemplate
{
    public View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function handle(string $template): string
    {
        if ($this->view['sections']->exists('builder') && (is_shop() || is_product())) {
            $this->view->addLoader(
                [$this, 'loadLayout'],
                '*/builder/elements/layout/templates/template.php',
            );
        }

        return $template;
    }

    /**
     * Ensure actions are only applied on main page content.
     */
    public function loadLayout(string $name, array $parameters, callable $next): string
    {
        $parameters += ['prefix' => ''];

        return Str::startsWith($parameters['prefix'], ['page', 'template-'])
            ? Helper::renderTemplate([$this, 'loadContent'], [$name, $parameters, $next])
            : $next($name, $parameters);
    }

    /**
     * Trigger WooCommerce actions before/after builder content.
     */
    public function loadContent(string $name, array $parameters, callable $next): void
    {
        /**
         * Hook: woocommerce_before_main_content.
         *
         * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
         * @hooked woocommerce_breadcrumb - 20
         * @hooked WC_Structured_Data::generate_website_data() - 30
         */
        remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

        do_action('woocommerce_before_main_content');

        if (is_product()) {
            do_action('woocommerce_before_single_product');
        }

        // Render builder content
        echo $next($name, $parameters);

        /**
         * Hook: woocommerce_after_main_content.
         *
         * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
         */
        remove_action(
            'woocommerce_after_main_content',
            'woocommerce_output_content_wrapper_end',
            10,
        );

        do_action('woocommerce_after_main_content');

        if (is_product()) {
            do_action('woocommerce_after_single_product');
        }

        if (is_product() && !did_filter('woocommerce_structured_data_product')) {
            WooCommerce::instance()->structured_data->generate_product_data();
        }
    }
}
