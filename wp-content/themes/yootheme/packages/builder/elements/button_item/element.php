<?php

namespace YOOtheme;

return [
    'name' => 'button_item',
    'title' => 'Item',
    'width' => 500,
    'defaults' => [
        'button_style' => 'default',
        'icon_align' => 'left',
        'dialog_layout' => 'modal',
        'dialog_offcanvas_flip' => true,
    ],
    'placeholder' => [
        'props' => [
            'content' => 'Button',
            'icon' => '',
            'link' => '#',
            'button_style' => 'default',
        ],
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            $config = app(Config::class);

            // Force reload, otherwise Modal/Offcanvas might be orphaned in the DOM
            if (
                $config('app.isCustomizer') &&
                (($node->props['link'] && $node->props['link_target'] == 'modal') ||
                    (!$node->props['link'] &&
                        $node->props['dialog'] &&
                        in_array($node->props['dialog_layout'], ['modal', 'offcanvas'])))
            ) {
                $node->attrs['data-preview'] = 'reload';
            }

            // Don't render element if content fields are empty
            return ($node->props['link'] || $node->props['dialog']) &&
                ($node->props['content'] != '' || $node->props['icon']);
        },
    ],
    'fields' => [
        'content' => [
            'label' => 'Content',
            'source' => true,
        ],
        'icon' => [
            'label' => 'Icon',
            'description' => 'Pick an optional icon from the icon library.',
            'type' => 'icon',
            'source' => true,
        ],
        'link' => '${builder.link}',
        'link_title' => '${builder.link_title}',
        'link_aria_label' => '${builder.link_aria_label}',
        'dialog' => [
            'label' => 'Dialog',
            'description' =>
                'Instead of opening a link, display an alternative content in a modal or an offcanvas sidebar.',
            'type' => 'editor',
            'source' => true,
            'enable' => '!link',
        ],
        'button_style' => [
            'label' => 'Style',
            'description' => 'Set the button style.',
            'type' => 'select',
            'options' => [
                'Default' => 'default',
                'Primary' => 'primary',
                'Secondary' => 'secondary',
                'Danger' => 'danger',
                'Text' => 'text',
                'Link' => '',
                'Link Muted' => 'link-muted',
                'Link Text' => 'link-text',
            ],
        ],
        'icon_align' => [
            'label' => 'Alignment',
            'description' => 'Choose the icon position.',
            'type' => 'select',
            'options' => [
                'Left' => 'left',
                'Right' => 'right',
            ],
            'enable' => 'icon',
        ],
        'link_target' => [
            'label' => 'Attributes',
            'description'  => 'Optionally, open the link in a new window, treat it as download, don\'t endorse the linked page or don\'t include the referrer header.',
            'type' => 'checkbox',
            'text' => 'Open in a new window',
            'enable' => 'link && !lightbox',
        ],
        'link_download' => [
            'type' => 'checkbox',
            'text' => 'Download',
            'enable' => 'link && !lightbox',
        ],
        'link_rel_nofollow' => [
            'type' => 'checkbox',
            'text' => 'Nofollow',
            'enable' => 'link && !lightbox',
        ],
        'link_rel_noreferrer' => [
            'type' => 'checkbox',
            'text' => 'Noreferrer',
            'enable' => 'link && !lightbox',
        ],
        'lightbox' => [
            'label' => 'Modal',
            'type' => 'checkbox',
            'text' => 'Enable modal window',
            'enable' => 'link',
        ],
        'image_width' => [
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'link && lightbox',
        ],
        'image_height' => [
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'link && lightbox',
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
            'enable' => 'link && lightbox',
        ],
        'dialog_layout' => [
            'label' => 'Target',
            'type' => 'select',
            'options' => [
                'Modal' => 'modal',
                'Offcanvas' => 'offcanvas',
            ],
            'enable' => 'dialog && !link',
        ],
        'dialog_close_large' => [
            'label' => 'Close',
            'type' => 'checkbox',
            'text' => 'Display large icon',
            'enable' => 'dialog && !link && dialog_layout',
        ],
        'dialog_close_outside' => [
            'type' => 'checkbox',
            'text' => 'Display outside',
            'enable' => 'dialog && !link && dialog_layout == \'modal\'',
        ],
        'dialog_modal_width' => [
            'label' => 'Modal Width',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'Container' => 'container',
                'Expand' => 'expand',
            ],
            'enable' => 'dialog && !link && dialog_layout == \'modal\'',
        ],
        'dialog_offcanvas_flip' => [
            'label' => 'Offcanvas',
            'type' => 'checkbox',
            'text' => 'Display on the right',
            'enable' => 'dialog && !link && dialog_layout == \'offcanvas\'',
        ],
        'dialog_offcanvas_overlay' => [
            'type' => 'checkbox',
            'text' => 'Overlay the site',
            'enable' => 'dialog && !link && dialog_layout == \'offcanvas\'',
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
                    'fields' => [
                        'content',
                        'icon',
                        'link',
                        'link_title',
                        'link_aria_label',
                        'dialog',
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Button',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['button_style'],
                        ],
                        [
                            'label' => 'Icon',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['icon_align'],
                        ],
                        [
                            'label' => 'Link',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'link_target',
                                'link_download',
                                'link_rel_nofollow',
                                'link_rel_noreferrer',
                                'lightbox',
                                [
                                    'label' => 'Width/Height',
                                    'description' =>
                                        'Set the width and height for the content the link is linking to, i.e. image, video or iframe.',
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => ['image_width', 'image_height'],
                                ],
                                'image_focal_point',
                            ],
                        ],
                        [
                            'label' => 'Dialog',
                            'type' => 'group',
                            'fields' => [
                                'dialog_layout',
                                'dialog_close_large',
                                'dialog_close_outside',
                                'dialog_modal_width',
                                'dialog_offcanvas_flip',
                                'dialog_offcanvas_overlay',
                            ],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
];
