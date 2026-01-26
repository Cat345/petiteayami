<?php

namespace YOOtheme;

return [
    'name' => 'slideshow_item',
    'title' => 'Item',
    'width' => 500,
    'placeholder' => [
        'props' => [
            'image' => Url::to('~assets/images/element-image-placeholder.png'),
            'video' => '',
            'title' => '',
            'meta' => '',
            'content' => '',
            'thumbnail' => '',
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
            foreach (['title', 'meta', 'content', 'link', 'thumbnail'] as $key) {
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

            if ($node->props['image'] && $view->isVideo($node->props['image'])) {
                $node->props['video'] = $node->props['image'];
                $node->props['image'] = null;
            } elseif ($node->props['video'] && $view->isImage($node->props['video'])) {
                $node->props['image'] = $node->props['video'];
                $node->props['video'] = null;
            }

            // Don't render element if content fields are empty
            return $node->props['image'] || $node->props['video'];
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
            'panel' => 'builder-slideshow-item-media',
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
        'thumbnail' => [
            'label' => 'Navigation Thumbnail',
            'description' => 'This option is only used if the thumbnail navigation is set.',
            'type' => 'image',
            'source' => true,
        ],
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
        ],
        'thumbnail_focal_point' => [
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
            'enable' => 'thumbnail',
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
                        'thumbnail',
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Item',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['text_color', 'item_element'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['image_focal_point'],
                        ],
                        [
                            'label' => 'Thumbnail',
                            'type' => 'group',
                            'fields' => ['thumbnail_focal_point'],
                        ],
                    ],
                ],
                '${builder.advancedItem}',
            ],
        ],
    ],
    'panels' => [
        'builder-slideshow-item-media' => [
            'title' => 'Image/Video',
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
