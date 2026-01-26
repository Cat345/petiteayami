<?php

namespace YOOtheme;

return [
    'name' => 'nav_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'content' => 'Item',
        ],
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node, $params) {
            // Display
            foreach (['meta', 'image'] as $key) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
                    if ($key === 'image') {
                        $node->props['icon'] = '';
                    }
                }
            }

            // Don't render element if content fields are empty
            return $node->props['content'] != '';
        },
    ],
    'fields' => [
        'content' => [
            'label' => 'Content',
            'source' => true,
        ],
        'meta' => [
            'label' => 'Subtitle',
            'source' => true,
        ],
        'image' => '${builder.image}',
        'image_alt' => '${builder.image_alt}',
        'icon' => [
            'label' => 'Icon',
            'description' =>
                'Instead of using a custom image, you can click on the pencil to pick an icon from the icon library.',
            'type' => 'icon',
            'source' => true,
            'enable' => '!image',
        ],
        'link' => [
            'label' => 'Link',
            'type' => 'link',
            'description' => 'Enter or pick a link, an image or a video file.',
            'attrs' => [
                'placeholder' => 'http://',
            ],
            'source' => true,
        ],
        'type' => [
            'label' => 'Type',
            'description' => 'Select the item type.',
            'type' => 'select',
            'options' => [
                'Item' => '',
                'Heading' => 'heading',
                'Divider' => 'divider',
            ],
            'source' => true,
        ],
        'active' => [
            'label' => 'Active',
            'description' => 'Highlight the item as the active item.',
            'type' => 'checkbox',
            'text' => 'Enable active state',
            'source' => true,
            'enable' => '!$match(type, \'divider|header\')',
        ],
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
        'link_target' => [
            'label' => 'Attributes',
            'description'  => 'Optionally, open the link in a new window, treat it as download, don\'t endorse the linked page or don\'t include the referrer header.',
            'type' => 'checkbox',
            'text' => 'Open in a new window',
            'enable' => 'link',
        ],
        'link_download' => [
            'type' => 'checkbox',
            'text' => 'Download',
            'enable' => 'link',
        ],
        'link_rel_nofollow' => [
            'type' => 'checkbox',
            'text' => 'Nofollow',
            'enable' => 'link',
        ],
        'link_rel_noreferrer' => [
            'type' => 'checkbox',
            'text' => 'Noreferrer',
            'enable' => 'link',
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
                    'fields' => ['content', 'meta', 'image', 'image_alt', 'icon', 'link'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Item',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['type', 'active'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['image_focal_point'],
                        ],
                        [
                            'label' => 'Link',
                            'type' => 'group',
                            'fields' => [
                                'link_target',
                                'link_download',
                                'link_rel_nofollow',
                                'link_rel_noreferrer',
                            ],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
];
