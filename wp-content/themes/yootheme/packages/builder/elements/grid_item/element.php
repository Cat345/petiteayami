<?php

namespace YOOtheme;

return [
    'name' => 'grid_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'title' => 'Title',
            'meta' => '',
            'content' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
            'image' => '',
            'video' => '',
            'icon' => '',
            'hover_image' => '',
            'hover_video' => '',
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
            foreach (
                ['title', 'meta', 'content', 'link', 'image', 'video', 'hover_image', 'hover_video']
                as $key
            ) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
                    if ($key === 'image') {
                        $node->props['icon'] = '';
                    }
                }
            }

            /**
             * Auto-correct media rendering for dynamic content
             *
             * @var View $view
             */
            $view = app(View::class);

            foreach (['', 'hover_'] as $prefix) {
                if (
                    $node->props["{$prefix}image"] &&
                    $view->isVideo($node->props["{$prefix}image"])
                ) {
                    $node->props["{$prefix}video"] = $node->props["{$prefix}image"];
                    $node->props["{$prefix}image"] = null;
                } elseif (
                    $node->props["{$prefix}video"] &&
                    $view->isImage($node->props["{$prefix}video"])
                ) {
                    $node->props["{$prefix}image"] = $node->props["{$prefix}video"];
                    $node->props["{$prefix}video"] = null;
                }
            }

            // Don't render element if content fields are empty
            return $node->props['title'] != '' ||
                $node->props['meta'] != '' ||
                $node->props['content'] != '' ||
                $node->props['image'] ||
                $node->props['video'] ||
                $node->props['icon'];
        },
    ],
    'fields' => [
        'title' => [
            'label' => 'Title',
            'source' => true,
        ],
        'meta' => [
            'label' => 'Meta',
            'source' => true,
        ],
        'content' => [
            'label' => 'Content',
            'type' => 'editor',
            'source' => true,
        ],
        'image' => [
            'label' => 'Image',
            'type' => 'image',
            'source' => true,
            'show' => '!video',
            'altRef' => '%name%_alt',
        ],
        'video' => [
            'label' => 'Video',
            'type' => 'video',
            'source' => true,
            'show' => '!image',
        ],
        'image_alt' => [
            'label' => 'Image Alt',
            'source' => true,
            'show' => 'image && !video',
        ],
        'icon' => [
            'label' => 'Icon',
            'description' =>
                'Instead of using a custom image, you can click on the pencil to pick an icon from the icon library.',
            'type' => 'icon',
            'source' => true,
            'enable' => '!image && !video',
        ],
        'link' => '${builder.link}',
        'link_text' => [
            'label' => 'Link Text',
            'description' => 'Set a different link text for this item.',
            'source' => true,
            'enable' => 'link',
        ],
        'link_aria_label' => [
            'label' => 'Link ARIA Label',
            'description' => 'Set a different link ARIA label for this item.',
            'source' => true,
            'enable' => 'link',
        ],
        'hover_image' => [
            'label' => 'Hover Image',
            'description' => 'Select an optional image that appears on hover.',
            'type' => 'image',
            'source' => true,
            'show' => '!hover_video',
            'enable' => 'image || video',
        ],
        'hover_video' => [
            'label' => 'Hover Video',
            'description' => 'Select an optional video that appears on hover.',
            'type' => 'video',
            'source' => true,
            'show' => '!hover_image',
            'enable' => 'image || video',
        ],
        'tags' => [
            'label' => 'Tags',
            'description' =>
                'Enter a comma-separated list of tags, for example, <code>blue, white, black</code>.',
            'source' => true,
        ],
        'panel_style' => [
            'label' => 'Style',
            'description' => 'Select one of the boxed card or tile styles or a blank panel.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Card Default' => 'card-default',
                'Card Primary' => 'card-primary',
                'Card Secondary' => 'card-secondary',
                'Card Hover' => 'card-hover',
                'Card Overlay' => 'card-overlay',
                'Tile Default' => 'tile-default',
                'Tile Muted' => 'tile-muted',
                'Tile Primary' => 'tile-primary',
                'Tile Secondary' => 'tile-secondary',
            ],
        ],
        'item_element' => '${builder.html_element_item}',
        'lightbox_image_focal_point' => [
            'label' => 'Image Focal Point',
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
        ],
        'lightbox_text_color' => [
            'label' => 'Text Color',
            'description' => 'Set light or dark color mode for text, buttons and controls.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Light' => 'light',
                'Dark' => 'dark',
            ],
            'source' => true,
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
            'enable' => 'image || video',
        ],
        'image_text_color' => [
            'label' => 'Text Color',
            'description' =>
                'Set light or dark color mode for text, buttons and controls if a sticky transparent navbar is displayed above.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Light' => 'light',
                'Dark' => 'dark',
            ],
            'source' => true,
            'enable' => 'image || video',
        ],
        'hover_image_focal_point' => [
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
        ],
        'link_style' => [
            'label' => 'Style',
            'description' => 'Set the link style.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Button Default' => 'default',
                'Button Primary' => 'primary',
                'Button Secondary' => 'secondary',
                'Button Danger' => 'danger',
                'Button Text' => 'text',
                'Link Muted' => 'link-muted',
                'Link Text' => 'link-text',
            ],
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
                        'title',
                        'meta',
                        'content',
                        'image',
                        'video',
                        'image_alt',
                        'icon',
                        'link',
                        'link_text',
                        'link_aria_label',
                        'hover_image',
                        'hover_video',
                        'tags',
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Panel',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['panel_style', 'item_element'],
                        ],
                        [
                            'label' => 'Lightbox',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['lightbox_image_focal_point', 'lightbox_text_color'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['image_focal_point', 'image_text_color'],
                        ],
                        [
                            'label' => 'Hover Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['hover_image_focal_point'],
                        ],
                        [
                            'label' => 'Link',
                            'type' => 'group',
                            'fields' => ['link_style'],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
];
