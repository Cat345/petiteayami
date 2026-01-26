<?php

namespace YOOtheme;

use YOOtheme\Builder\Newsletter\CampaignMonitorProvider;
use YOOtheme\Builder\Newsletter\MailChimpProvider;
use YOOtheme\Builder\Newsletter\NewsletterController;

return [
    'theme' => [
        'newsletterProvider' => [
            'mailchimp' => MailChimpProvider::class,
            'cmonitor' => CampaignMonitorProvider::class,
        ],
    ],

    'routes' => [
        ['post', '/theme/newsletter/list', NewsletterController::class . '@lists'],
        [
            'post',
            '/theme/newsletter/subscribe',
            NewsletterController::class . '@subscribe',
            ['csrf' => false, 'allowed' => true],
        ],
    ],

    'extend' => [
        Builder::class => function (Builder $builder) {
            $builder->addType('newsletter', __DIR__ . '/elements/newsletter/element.php');
        },
    ],

    'services' => [
        MailChimpProvider::class => fn(
            Config $config,
            HttpClientInterface $client
        ) => new MailChimpProvider($config('~theme.mailchimp_api', ''), $client),

        CampaignMonitorProvider::class => fn(
            Config $config,
            HttpClientInterface $client
        ) => new CampaignMonitorProvider($config('~theme.cmonitor_api'), $client),

        NewsletterController::class => fn(Config $config) => new NewsletterController(
            $config('theme.newsletterProvider'),
            $config('app.secret'),
        ),
    ],
];
