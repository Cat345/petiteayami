<?php

namespace YOOtheme\Builder\Source\Type;

use YOOtheme\Config;
use YOOtheme\Http\Request;
use function YOOtheme\app;
use function YOOtheme\trans;

class SiteType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Site Title'),
                        'filters' => ['limit', 'preserve'],
                    ],
                ],

                'page_title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Page Title'),
                        'filters' => ['limit', 'preserve'],
                    ],
                ],

                'page_locale' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Page Locale'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePageLocale',
                    ],
                ],

                'page_url' => [
                    'type' => 'String',
                    'args' => [
                        'query' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Page URL'),
                        'arguments' => [
                            'query' => [
                                'label' => trans('Query String'),
                                'type' => 'checkbox',
                                'text' => trans('Include query string'),
                                'default' => false,
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolvePageUrl',
                    ],
                ],

                'is_guest' => [
                    'type' => 'Int',
                    'metadata' => [
                        'label' => trans('Guest User'),
                        'condition' => true,
                    ],
                ],

                'user' => [
                    'type' => 'User',
                    'metadata' => [
                        'label' => trans('Current User'),
                    ],
                ],

                'request' => [
                    'type' => 'Request',
                    'metadata' => [
                        'label' => trans('Request'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::resolveRequest',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
                'label' => trans('Site'),
            ],
        ];
    }

    public static function resolveRequest()
    {
        return app(Request::class);
    }

    public static function resolvePageUrl($obj, array $args)
    {
        $uri = static::resolveRequest()->getUri();

        return $uri->getPath() . ($args['query'] ? "?{$uri->getQuery()}" : '');
    }

    public static function resolvePageLocale()
    {
        return app(Config::class)('locale.code');
    }
}
