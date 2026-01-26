<?php

namespace YOOtheme;

return [
    'name' => 'overlay-slider_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'image' => Url::to('~assets/images/element-image-placeholder.png'),
            'video' => '',
            'title' => 'Title',
            'meta' => '',
            'content' => '',
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
            foreach (['title', 'meta', 'content', 'link', 'hover_image', 'hover_video'] as $key) {
                if (!$params['parent']->props["show_{$key}"]) {
                    $node->props[$key] = '';
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
            return $node->props['image'] ||
                $node->props['video'] ||
                $node->props['hover_image'] ||
                $node->props['hover_video'];
        },
    ],
    'fields' => [
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
        '_media' => [
            'type' => 'button-panel',
            'panel' => 'builder-overlay-slider-item-media',
            'text' => 'Edit Settings',
            'show' => 'image || video',
        ],
        'image_alt' => [
            'label' => 'Image Alt',
            'source' => true,
            'show' => 'image && !video',
        ],
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
        ],
        'hover_video' => [
            'label' => 'Hover Video',
            'description' => 'Select an optional video that appears on hover.',
            'type' => 'video',
            'source' => true,
            'show' => '!hover_image',
        ],
        'item_element' => '${builder.html_element_item}',
        'text_color' => [
            'label' => 'Text Color',
            'description' => 'Set a different text color for this item.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Light' => 'light',
                'Dark' => 'dark',
            ],
            'source' => true,
        ],
        'text_color_hover' => [
            'type' => 'checkbox',
            'text' => 'Inverse the text color on hover',
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
                        'image',
                        'video',
                        '_media',
                        'image_alt',
                        'title',
                        'meta',
                        'content',
                        'link',
                        'link_text',
                        'link_aria_label',
                        'hover_image',
                        'hover_video',
                    ],
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
                            'label' => 'Overlay',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['text_color', 'text_color_hover'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['image_focal_point'],
                        ],
                        [
                            'label' => 'Hover Image',
                            'type' => 'group',
                            'fields' => ['hover_image_focal_point'],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
    'panels' => [
        'builder-overlay-slider-item-media' => [
            'title' => 'Media',
            'width' => 500,
            'fields' => [
                'media_background' => '${builder.media_background}',
                'media_blend_mode' => '${builder.media_blend_mode}',
                'media_overlay' => '${builder.media_overlay}',
                'media_overlay_gradient' => '${builder.media_overlay_gradient}',
            ],
            'fieldset' => [
                'default' => [
                    'fields' => ['media_background', 'media_blend_mode', 'media_overlay'],
                ],
            ],
        ],
    ],
];
