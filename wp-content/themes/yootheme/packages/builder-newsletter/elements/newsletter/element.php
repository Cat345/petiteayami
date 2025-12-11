<?php

namespace YOOtheme;

use YOOtheme\Builder\Newsletter\NewsletterController;

return [
    'transforms' => [
        'render' => function ($node) {
            /**
             * @var NewsletterController $controller
             * @var Metadata $meta
             */
            [$controller, $meta] = app(NewsletterController::class, Metadata::class);

            $provider = (array) $node->props['provider'];

            $node->settings = $controller->encodeData(
                array_merge($provider, (array) $node->props[$provider['name']]),
            );
            $node->form = [
                'action' => Url::route('theme/newsletter/subscribe', [
                    'hash' => $controller->getHash($node->settings),
                ]),
            ];

            $meta->set('script:newsletter', [
                'src' => Path::get('../../app/newsletter.min.js', __DIR__),
                'defer' => true,
            ]);
        },
    ],
];
