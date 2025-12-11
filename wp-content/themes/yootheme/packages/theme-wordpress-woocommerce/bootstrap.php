<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Theme\Styler\StylerConfig;
use YOOtheme\View;

$config = [
    'events' => [
        StylerConfig::class => [Listener\LoadStylerConfig::class => '@handle'],
        'styler.imports' => [Listener\LoadStylerImports::class => '@handle'],
        'customizer.init' => [Listener\LoadCustomizer::class => '@handle'],
    ],
];

if (!class_exists('WooCommerce', false)) {
    return $config;
}

return array_merge_recursive($config, [
    'theme' => function (Config $config) {
        $config->set(
            'styles.imports.search',
            Path::get('../../vendor/assets/uikit/src/images/icons/search.svg', __DIR__),
        );

        return $config->loadFile(__DIR__ . '/config/theme.json');
    },

    'config' => [
        StylerConfig::class => __DIR__ . '/config/styler.json',
    ],

    'events' => [
        'theme.breadcrumbs' => [Listener\LoadBreadcrumbs::class => 'handle'],
    ],

    'actions' => [
        'wp_enqueue_scripts' => [Listener\RemoveSelect::class => ['handle', 100]],

        'woocommerce_before_add_to_cart_form' => [
            Listener\AddVariableProductScript::class => '@variableScript',
        ],
    ],

    'filters' => [
        'wp_nav_menu_objects' => [
            Listener\ShowCartQuantity::class => ['@navMenuObjects', 10, 2],
        ],

        'woocommerce_add_to_cart_fragments' => [
            Listener\ShowCartQuantity::class => 'addToCartFragments',
        ],

        'woocommerce_format_sale_price' => [
            Listener\FilterPriceHtml::class => ['@sale', 10, 3],
        ],

        'woocommerce_grouped_price_html' => [
            Listener\FilterPriceHtml::class => ['@grouped', 10, 3],
        ],

        'woocommerce_variable_price_html' => [
            Listener\FilterPriceHtml::class => ['@variable', 10, 2],
        ],

        'woocommerce_cross_sells_columns' => [
            Listener\FilterProductHtml::class => '@crossSellsColumns',
        ],

        'woocommerce_product_thumbnails_columns' => [
            Listener\FilterProductHtml::class => '@thumbnailsColumns',
        ],

        'woocommerce_product_review_list_args' => [
            Listener\FilterProductHtml::class => 'reviewListArgs',
        ],

        'woocommerce_product_review_comment_form_args' => [
            Listener\FilterProductHtml::class => 'reviewCommentArgs',
        ],

        'woocommerce_enqueue_styles' => [Listener\RemoveStyles::class => 'handle'],

        'woocommerce_pagination_args' => [Listener\FilterPaginationHtml::class => 'args'],

        'paginate_links_output' => [Listener\FilterPaginationHtml::class => ['@links', 10, 2]],
    ],

    'services' => [
        Listener\FilterPriceHtml::class => '',
        Listener\ShowCartQuantity::class => '',
    ],

    'extend' => [
        View::class => function (View $view) {
            $view['html']->addTransform('image', [ImageTransform::class, 'handle']);
        },
    ],
]);
