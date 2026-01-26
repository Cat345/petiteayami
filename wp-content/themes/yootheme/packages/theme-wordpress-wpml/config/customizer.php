<?php

return [
    'sections' => [
        'layout' => [
            'fields' => [
                'layout' => [
                    'items' => [
                        'wpml' => 'WPML',
                    ],
                ],
            ],
        ],
    ],
    'panels' => [
        'wpml' => [
            'title' => 'WPML',
            'width' => 400,
            'fields' => [
                'wpml.image' => [
                    'label' => 'Image',
                    'type' => 'image',
                    'enable' => '!wpml.icon',
                ],
                'wpml.icon' => [
                    'label' => 'Icon',
                    'description' =>
                        'Instead of using a custom image, set an icon for the language switcher parent item.',
                    'type' => 'icon',
                    'enable' => '!wpml.image',
                ],
                'wpml.image_only' => [
                    'label' => 'Image and Title',
                    'description' => 'Only show the image or icon.',
                    'type' => 'checkbox',
                    'text' => 'Hide title',
                    'enable' => 'wpml.icon',
                ],
                'wpml.subtitle' => [
                    'label' => 'Subtitle',
                    'description' =>
                        'Enter a subtitle that will be displayed beneath the nav item.',
                    'enable' => '!((wpml.image || wpml.icon) && wpml.image_only)',
                ],
                'wpml.dropdown.columns' => [
                    'label' => 'Dropdown Columns',
                    'description' => 'Split the dropdown into columns.',
                    'type' => 'select',
                    'default' => 1,
                    'options' => [
                        '1 Column' => 1,
                        '2 Columns' => 2,
                        '3 Columns' => 3,
                        '4 Columns' => 4,
                        '5 Columns' => 5,
                    ],
                ],
                'wpml.dropdown.stretch' => [
                    'label' => 'Dropdown Stretch',
                    'description' =>
                        'Stretch the dropdown to the width of the navbar or the navbar container.',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Navbar' => 'navbar',
                        'Navbar Container' => 'navbar-container',
                    ],
                ],
                'wpml.dropdown.width' => [
                    'label' => 'Dropdown Width',
                    'description' => 'Set the dropdown width in pixels (e.g. 600).',
                    'enable' => '!wpml.dropdown.stretch',
                ],
                'wpml.dropdown.size' => [
                    'label' => 'Dropdown Padding',
                    'type' => 'checkbox',
                    'text' => 'Large padding',
                ],
                'wpml.dropdown.align' => [
                    'label' => 'Dropdown Alignment',
                    'type' => 'select',
                    'options' => [
                        'Default' => '',
                        'Left' => 'left',
                        'Right' => 'right',
                        'Center' => 'center',
                    ],
                    'enable' => '!wpml.dropdown.stretch',
                ],
                'wpml.dropdown.nav_style' => [
                    'label' => 'Dropdown Nav Style',
                    'description' => 'Select the nav style.',
                    'type' => 'select',
                    'options' => [
                        'Default' => '',
                        'Secondary' => 'secondary',
                    ],
                ],
            ],
        ],
    ],
];
