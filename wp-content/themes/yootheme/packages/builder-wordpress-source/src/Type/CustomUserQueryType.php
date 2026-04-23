<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_User;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class CustomUserQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'customUser' => [
                    'type' => 'User',

                    'args' => [
                        'id' => [
                            'type' => 'Int',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Custom User'),
                        'group' => trans('Custom'),
                        'fields' => [
                            'id' => [
                                'label' => trans('User'),
                                'type' => 'select-item',
                                'route' => 'wordpress/users',
                                'labels' => [
                                    'type' => trans('User'),
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolveUser',
                    ],
                ],
                'customUsers' => [
                    'type' => [
                        'listOf' => 'User',
                    ],

                    'args' => [
                        'roles' => [
                            'type' => [
                                'listOf' => 'String',
                            ],
                        ],
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => 10,
                        ],
                        'order' => [
                            'type' => 'String',
                            'defaultValue' => 'display_name',
                        ],
                        'order_direction' => [
                            'type' => 'String',
                            'defaultValue' => 'ASC',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Custom Users'),
                        'group' => trans('Custom'),
                        'fields' => [
                            'roles' => [
                                'label' => trans('Roles'),
                                'description' => trans(
                                    'Users are only loaded from the selected roles. Use the <kbd>shift</kbd> or <kbd>ctrl/cmd</kbd> key to select multiple roles.',
                                ),
                                'type' => 'select',
                                'attrs' => [
                                    'multiple' => true,
                                    'class' => 'uk-height-small',
                                ],
                                'options' => [['evaluate' => 'yootheme.builder.roles']],
                            ],
                            '_offset' => [
                                'description' => trans(
                                    'Set the starting point and limit the number of users.',
                                ),
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'min' => 1,
                                        ],
                                    ],
                                ],
                            ],
                            '_order' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'order' => [
                                        'label' => trans('Order'),
                                        'type' => 'select',
                                        'options' => [
                                            trans('Alphabetical') => 'display_name',
                                            trans('Register date') => 'user_registered',
                                        ],
                                    ],
                                    'order_direction' => [
                                        'label' => trans('Direction'),
                                        'type' => 'select',
                                        'options' => [
                                            trans('Ascending') => 'ASC',
                                            trans('Descending') => 'DESC',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolveUsers',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return ?WP_User
     */
    public static function resolveUser($root, array $args)
    {
        if (empty($args['id'])) {
            return null;
        }

        return get_userdata($args['id']);
    }

    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed> $args
     * @return array<WP_User>
     */
    public static function resolveUsers($root, array $args): array
    {
        $query = [
            'orderby' => $args['order'],
            'order' => $args['order_direction'],
            'offset' => $args['offset'],
            'number' => $args['limit'],
        ];

        if (!empty($args['roles'])) {
            $query['role__in'] = $args['roles'];
        }

        return get_users($query);
    }
}
