<?php

namespace YOOtheme;

return [
    'name' => 'accordion_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'title' => 'Title',
            'content' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
            'image' => '',
        ],
    ],
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node, $params) {
            // Display
            foreach (['image', 'link'] as $key) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
                }
            }

            // Don't render element if content fields are empty
            return $node->props['title'] != '' &&
                ($node->props['content'] != '' || $node->props['image'] || $node->props['link']);
        },
    ],
    'fields' => [
        'title' => [
            'label' => 'Title',
            'source' => true,
        ],
        'content' => [
            'label' => 'Content',
            'type' => 'editor',
            'source' => true,
        ],
        'image' => '${builder.image}',
        'image_alt' => '${builder.image_alt}',
        'link' => '${builder.link}',
        'link_text' => [
            'label' => 'Link Text',
            'description' => 'Set a different link text for this item.',
            'source' => true,
            'enable' => 'link',
        ],
        'item_element' => '${builder.html_element_item}',
        'image_focal_point' => [
            'label' => 'Focal Point',
            'description' => 'Set a focal point to control cropping.',
            'type' => 'select',
            'options' => [
                'Top Left' => 'top-left',
                'Top Center' => 'top-center',
                'Top Right' => 'top-right',
                'Center Left' => 'center-left',
                'Center Center' => '',
                'Center Right' => 'center-right',
                'Bottom Left' => 'bottom-left',
                'Bottom Center' => 'bottom-center',
                'Bottom Right' => 'bottom-right',
            ],
            'source' => true,
            'enable' => 'image',
        ],
        'name' => '${builder.nameItem}',
        'status' => '${builder.statusItem}',
        'source' => '${builder.source}',
        'id' => '${builder.id}',
        'class' => '${builder.cls}',
        'attributes' => '${builder.attrs}',
    ],
    'fieldset' => [
        'default' => [
            'type' => 'tabs',
            'fields' => [
                [
                    'title' => 'Content',
                    'fields' => ['title', 'content', 'image', 'image_alt', 'link', 'link_text'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Item',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['item_element'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'fields' => ['image_focal_point'],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
];
