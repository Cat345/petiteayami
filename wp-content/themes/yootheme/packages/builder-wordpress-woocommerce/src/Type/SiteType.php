<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Http\Request;
use function YOOtheme\app;
use function YOOtheme\trans;

class SiteType
{
    public static function config(Source $source)
    {
        $source->objectType('WoocommercePages', [
            'fields' => [
                'my_account' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('My Account Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'login' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Login Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'lost_password' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Lost Password Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'reset_link_sent' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Reset Link Sent Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'lost_password_confirmation' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Lost Password Confirmation Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'checkout' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Checkout Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
                'order_received' => [
                    'type' => 'Boolean',
                    'metadata' => [
                        'label' => trans('Order Received Page'),
                        'group' => 'WooCommerce',
                        'condition' => true,
                    ],
                ],
            ],
        ]);

        return [
            'fields' => [
                'woocommerce' => [
                    'type' => 'WoocommercePages',
                    'metadata' => [],
                    'extensions' => [
                        'call' => [
                            'func' => __CLASS__ . '::resolve',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function resolve()
    {
        if (is_lost_password_page()) {
            $request = app(Request::class);

            if ($request->getQueryParam('reset-link-sent')) {
                return ['reset_link_sent' => true];
            } elseif (
                $request->getQueryParam('show-reset-form') &&
                str_contains($request->getCookieParam('wp-resetpass-' . COOKIEHASH, ''), ':')
            ) {
                return ['lost_password_confirmation' => true];
            } else {
                return ['lost_password' => true];
            }
        } elseif (is_account_page()) {
            if (!is_user_logged_in()) {
                return ['login' => true];
            }
            return ['my_account' => true];
        }

        if (is_order_received_page()) {
            return ['order_received' => true];
        } elseif (is_checkout()) {
            return ['checkout' => true];
        }
    }
}
