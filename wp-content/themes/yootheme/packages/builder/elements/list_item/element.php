<?php

namespace YOOtheme;

return [
    'name' => 'list_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'content' => 'Lorem ipsum dolor sit amet.',
            'image' => '',
            'icon' => '',
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
            foreach (['image', 'link'] as $key) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
                    if ($key === 'image') {
                        $node->props['icon'] = '';
                    }
                }
            }

            // Don't render element if content fields are empty
            return $node->props['content'] != '' || $node->props['image'] || $node->props['icon'];
        },
    ],
    'fields' => [
        'content' => [
            'label' => 'Content',
            'type' => 'editor',
            'root' => true,
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
        'image_svg_color' => [
            'label' => 'Icon/SVG Color',
            'description' =>
                'Select the SVG color. It will only apply to supported elements defined in the SVG.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Muted' => 'muted',
                'Emphasis' => 'emphasis',
                'Primary' => 'primary',
                'Secondary' => 'secondary',
                'Success' => 'success',
                'Warning' => 'warning',
                'Danger' => 'danger',
            ],
            'enable' => 'image || icon',
        ],
        'link' => '${builder.link}',
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
                    'fields' => ['content', 'image', 'image_alt', 'icon', 'link'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['image_focal_point', 'image_svg_color'],
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
