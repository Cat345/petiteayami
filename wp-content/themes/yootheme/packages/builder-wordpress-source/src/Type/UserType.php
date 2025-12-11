<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use WP_User;
use function YOOtheme\trans;

class UserType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'name' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Name'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::name',
                    ],
                ],

                'nicename' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Nicename'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::nicename',
                    ],
                ],

                'nickname' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Nickname'),
                        'filters' => ['limit', 'preserve'],
                    ],
                ],

                'firstName' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('First name'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::firstName',
                    ],
                ],

                'lastName' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Last name'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::lastName',
                    ],
                ],

                'description' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Description'),
                        'filters' => ['limit', 'preserve'],
                    ],
                ],

                'email' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Email'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::email',
                    ],
                ],

                'registered' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Registered Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::registered',
                    ],
                ],

                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Website Url'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::url',
                    ],
                ],

                'link' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Link'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::link',
                    ],
                ],

                'avatar' => [
                    'type' => 'String',
                    'args' => [
                        'size' => [
                            'type' => 'String',
                            'default' => '96',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Avatar'),
                        'arguments' => [
                            'size' => [
                                'label' => trans('Image Size'),
                                'description' => trans(
                                    'Set the size for the Gravatar image in pixels.',
                                ),
                                'type' => 'text',
                                'default' => '96',
                                'attrs' => [
                                    'min' => 1,
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::avatar',
                    ],
                ],

                'rolesString' => [
                    'type' => 'String',
                    'args' => [
                        'separator' => [
                            'type' => 'String',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Roles'),
                        'arguments' => [
                            'separator' => [
                                'label' => trans('Separator'),
                                'description' => trans('Set the separator between user roles.'),
                                'default' => ', ',
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::rolesString',
                    ],
                ],

                'id' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('ID'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::id',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
                'label' => trans('User'),
            ],
        ];
    }

    public static function id(WP_User $user)
    {
        return $user->ID;
    }

    public static function name(WP_User $user)
    {
        return $user->display_name;
    }

    public static function nicename(WP_User $user)
    {
        return $user->user_nicename;
    }

    public static function firstName(WP_User $user)
    {
        return $user->first_name;
    }

    public static function lastName(WP_User $user)
    {
        return $user->last_name;
    }

    public static function email(WP_User $user)
    {
        return $user->user_email;
    }

    public static function registered(WP_User $user)
    {
        return $user->user_registered;
    }

    public static function url(WP_User $user)
    {
        return $user->user_url;
    }

    public static function link(WP_User $user)
    {
        return get_author_posts_url($user->ID);
    }

    public static function avatar(WP_User $user, $args)
    {
        return get_avatar_url($user->ID, $args);
    }

    public static function rolesString(WP_User $user, $args): string
    {
        $result = [];
        $roles = wp_roles();
        foreach ($user->roles as $role) {
            if (isset($roles->role_names[$role])) {
                $result[] = translate_user_role($roles->role_names[$role]);
            }
        }

        return implode($args['separator'] ?? ', ', $result);
    }
}
