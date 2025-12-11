<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use YOOtheme\Builder;
use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\UpdateTransform;

if (!class_exists('WooCommerce', false)) {
    return [];
}

return [
    'events' => [
        'source.init' => [Listener\LoadSourceTypes::class => ['handle', -10]],
        'source.object.taxonomies' => [Listener\FilterTaxonomies::class => 'handle'],
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => 'handle'],
    ],

    'filters' => [
        'template_include' => [Listener\LoadTemplate::class => ['@handle', 80]],
    ],

    'extend' => [
        Builder::class => function (Builder $builder) {
            // add transform on single product page
            if (is_product()) {
                $builder->addTransform('render', new RenderTransform());
            }

            $builder->addTypePath(__DIR__ . '/elements/*/element.json');
        },

        UpdateTransform::class => function (UpdateTransform $transform) {
            $transform->addGlobals(require __DIR__ . '/updates.php');
        },
    ],
];
