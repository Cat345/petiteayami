<?php

namespace YOOtheme;

return [
    'name' => 'social_item',
    'title' => 'Item',
    'width' => 500,
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            // Don't render element if content fields are empty
            return $node->props['link'];
        },
    ],
    'fields' => [
        'link' => [
            'label' => 'Link',
            'attrs' => [
                'placeholder' => 'https://',
            ],
            'source' => true,
            'description' =>
                'Enter link to your social profile. A corresponding <a href="https://getuikit.com/docs/icon" target="_blank">UIkit brand icon</a> will be displayed automatically, if available. Links to email addresses and phone numbers, like mailto:info@example.com or tel:+491570156, are also supported.',
        ],
        'link_aria_label' => [
            'label' => 'Link ARIA Label',
            'description' => 'Set a different link ARIA label for this item.',
            'source' => true,
        ],
        'icon' => [
            'label' => 'Icon',
            'description' => 'Pick an alternative icon from the icon library.',
            'type' => 'icon',
            'source' => true,
            'enable' => '!image',
        ],
        'image' => [
            'label' => 'Image',
            'description' => 'Pick an alternative SVG image from the media manager.',
            'type' => 'image',
            'source' => true,
            'enable' => '!icon',
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
                    'fields' => ['link', 'link_aria_label', 'icon', 'image'],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
];
