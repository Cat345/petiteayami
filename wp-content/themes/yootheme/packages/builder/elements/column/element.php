<?php

namespace YOOtheme;

return [
    'name' => 'column',
    'title' => 'Column',
    'container' => true,
    'width' => 500,
    'defaults' => [
        'position_sticky_breakpoint' => 'm',
        'image_position' => 'center-center',
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node, $params) {
            foreach (['height', 'height_viewport', 'height_viewport_offset', 'parallax'] as $prop) {
                $node->props["row_{$prop}"] = $params['parent']->props[$prop] ?? null;
            }

            foreach ($node->children as $child) {
                if (
                    // Expand Height
                    (!empty($child->props['height_expand']) ||
                        // Margin Auto
                        (!empty($child->props['margin_top']) &&
                            $child->props['margin_top'] == 'auto') ||
                        (!empty($child->props['margin_bottom']) &&
                            $child->props['margin_bottom'] == 'auto')) &&
                    // Column suitable
                    (!$node->props['position_sticky'] ||
                        (in_array($node->props['position_sticky'], ['row', 'section']) &&
                            $node->props['row_height']))
                ) {
                    $node->props['flex_column'] = true;
                    $node->props['vertical_align'] = '';
                    break;
                }
            }
        },
    ],
    'fields' => [
        'image' => [
            'label' => 'Image',
            'description' => 'Upload a background image.',
            'type' => 'image',
            'source' => true,
            'show' => '!video',
        ],
        'video' => [
            'label' => 'Video',
            'type' => 'video',
            'source' => true,
            'show' => '!image',
        ],
        '_media' => [
            'type' => 'button-panel',
            'panel' => 'builder-column-media',
            'text' => 'Edit Settings',
            'show' => 'image || video',
        ],
        'vertical_align' => [
            'label' => 'Vertical Alignment',
            'description' => 'Vertically align the elements in the column.',
            'type' => 'select',
            'options' => [
                'Top' => '',
                'Middle' => 'middle',
                'Bottom' => 'bottom',
            ],
        ],
        'style' => [
            'label' => 'Style',
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
            'source' => true,
        ],
        'preserve_color' => [
            'type' => 'checkbox',
            'text' => 'Preserve text color',
            'enable' => '$match(style, \'tile-\')',
        ],
        'background_color' => [
            'label' => 'Background Color',
            'type' => 'gradient',
            'internal' => 'background_color_gradient',
            'source' => true,
            'enable' => '!style',
        ],
        'background_color_gradient' => [
            'type' => 'hidden',
        ],
        '_background_parallax_button' => [
            'type' => 'button-panel',
            'text' => 'Edit Parallax',
            'panel' => 'background-parallax',
            'enable' => '!style',
        ],
        'border' => [
            'description' =>
                'Define a custom background color or a color parallax animation instead of using a predefined style.',
            'type' => 'checkbox',
            'text' => 'Round corners',
            'enable' =>
                '!style && (background_color || background_color_gradient || background_parallax_background || image || video)',
        ],
        'text_color' => [
            'label' => 'Text Color',
            'description' =>
                'Force a light or dark color for text, buttons and controls on the image or video background.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Light' => 'light',
                'Dark' => 'dark',
            ],
            'source' => true,
            'enable' => '!style || ($match(style, \'tile-\') && (image || video))',
        ],
        'padding' => [
            'label' => 'Padding',
            'description' => 'Set the padding.',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'X-Small' => 'xsmall',
                'Small' => 'small',
                'Large' => 'large',
                'X-Large' => 'xlarge',
                'None' => 'none',
            ],
            'enable' =>
                'style || background_color || background_color_gradient || background_parallax_background || image || video',
        ],
        'html_element' => '${builder.html_element}',
        'position_sticky' => [
            'label' => 'Position Sticky',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Elements within Column' => 'column',
                'Column within Row' => 'row',
                'Column within Section' => 'section',
            ],
        ],
        'position_blend' => [
            'description' =>
                'Stick the column or its elements to the top of the viewport while scrolling down. They will stop being sticky when they reach the bottom of the containing column, row or section. Optionally, blend all elements with the page content.',
            'type' => 'checkbox',
            'text' => 'Blend with page content',
            'enable' => 'position_sticky',
        ],
        'position_sticky_offset' => [
            'label' => 'Top Offset',
            'attrs' => [
                'placeholder' => '0',
            ],
            'enable' => 'position_sticky',
        ],
        'position_sticky_offset_end' => [
            'label' => 'Bottom Offset',
            'attrs' => [
                'placeholder' => '0',
            ],
            'enable' => 'position_sticky',
        ],
        'position_sticky_breakpoint' => [
            'label' => 'Position Sticky Breakpoint',
            'description' =>
                'Make the column or its elements sticky only from this device width and larger.',
            'type' => 'select',
            'options' => [
                'Always' => '',
                'Small (Phone Landscape)' => 's',
                'Medium (Tablet Landscape)' => 'm',
                'Large (Desktop)' => 'l',
                'X-Large (Large Screens)' => 'xl',
            ],
            'enable' => 'position_sticky',
        ],
        'prevent_collapse' => [
            'label' => 'Empty Dynamic Content',
            'description' => 'Don\'t collapse the column if dynamically loaded content is empty.',
            'type' => 'checkbox',
            'text' => 'Don\'t collapse column',
        ],
        'source' => '${builder.source}',
        'id' => '${builder.id}',
        'class' => '${builder.cls}',
        'attributes' => '${builder.attrs}',
        'css' => [
            'label' => 'CSS',
            'description' =>
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-column</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-column'],
            ],
            'source' => true,
        ],
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
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        'vertical_align',
                        'style',
                        'preserve_color',
                        'background_color',
                        '_background_parallax_button',
                        'border',
                        'text_color',
                        'padding',
                        'html_element',
                        'position_sticky',
                        'position_blend',
                        [
                            'description' =>
                                'Set the sticky top offset, e.g. <code>100px</code>, <code>50vh</code> or <code>50vh - 50%</code>. Percent relates to the column\'s height. Set a bottom offset if the sticky content is larger than the viewport.',
                            'name' => '_position_sticky_offset',
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => ['position_sticky_offset', 'position_sticky_offset_end'],
                        ],
                        'position_sticky_breakpoint',
                        'prevent_collapse',
                    ],
                ],
                [
                    'title' => 'Advanced',
                    'fields' => ['source', 'id', 'class', 'attributes', 'css'],
                ],
            ],
        ],
    ],
    'panels' => [
        'builder-column-media' => [
            'title' => 'Image/Video',
            'width' => 500,
            'fields' => [
                'image_width' => [
                    'label' => 'Width',
                    'type' => 'number',
                    'attrs' => [
                        'placeholder' => 'auto',
                    ],
                ],
                'image_height' => [
                    'label' => 'Height',
                    'type' => 'number',
                    'attrs' => [
                        'placeholder' => 'auto',
                    ],
                ],
                'media_focal_point' => [
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
                'image_loading' => [
                    'label' => 'Loading',
                    'description' =>
                        'By default, images are loaded lazy. Enable eager loading for images in the initial viewport.',
                    'type' => 'checkbox',
                    'text' => 'Load image eagerly',
                ],
                'image_size' => [
                    'label' => 'Image Size',
                    'description' =>
                        'Determine whether the image will fit the section dimensions by clipping it or by filling the empty areas with the background color.',
                    'type' => 'select',
                    'options' => [
                        'Auto' => '',
                        'Cover' => 'cover',
                        'Contain' => 'contain',
                        'Width 100%' => 'width-1-1',
                        'Height 100%' => 'height-1-1',
                    ],
                    'show' => 'image && !video',
                ],
                'image_position' => [
                    'label' => 'Image Position',
                    'description' =>
                        'Set the initial background position, relative to the section layer.',
                    'type' => 'select',
                    'options' => [
                        'Top Left' => 'top-left',
                        'Top Center' => 'top-center',
                        'Top Right' => 'top-right',
                        'Center Left' => 'center-left',
                        'Center Center' => 'center-center',
                        'Center Right' => 'center-right',
                        'Bottom Left' => 'bottom-left',
                        'Bottom Center' => 'bottom-center',
                        'Bottom Right' => 'bottom-right',
                    ],
                    'show' => 'image && !video',
                ],
                'image_effect' => [
                    'label' => 'Image Effect',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'Parallax' => 'parallax',
                        'Fixed' => 'fixed',
                    ],
                    'show' => 'image && !video',
                ],
                '_image_parallax_button' => [
                    'description' =>
                        'Add a parallax effect or fix the background with regard to the viewport while scrolling.',
                    'type' => 'button-panel',
                    'text' => 'Edit Parallax',
                    'panel' => 'image-parallax',
                    'show' => 'image && !video',
                    'enable' => 'image_effect == \'parallax\'',
                ],
                'media_visibility' => [
                    'label' => 'Visibility',
                    'description' =>
                        'Display the image or video only on this device width and larger.',
                    'type' => 'select',
                    'options' => [
                        'Always' => '',
                        'Small (Phone Landscape)' => 's',
                        'Medium (Tablet Landscape)' => 'm',
                        'Large (Desktop)' => 'l',
                        'X-Large (Large Screens)' => 'xl',
                    ],
                ],
                'media_background' => [
                    'label' => 'Background Color',
                    'description' =>
                        'Use the background color in combination with blend modes, a transparent image or to fill the area, if the image doesn\'t cover the whole section.',
                    'type' => 'color',
                ],
                'media_blend_mode' => [
                    'label' => 'Blend Mode',
                    'description' =>
                        'Determine how the image or video will blend with the background color.',
                    'type' => 'select',
                    'options' => [
                        'Normal' => '',
                        'Multiply' => 'multiply',
                        'Screen' => 'screen',
                        'Overlay' => 'overlay',
                        'Darken' => 'darken',
                        'Lighten' => 'lighten',
                        'Color-dodge' => 'color-dodge',
                        'Color-burn' => 'color-burn',
                        'Hard-light' => 'hard-light',
                        'Soft-light' => 'soft-light',
                        'Difference' => 'difference',
                        'Exclusion' => 'exclusion',
                        'Hue' => 'hue',
                        'Saturation' => 'saturation',
                        'Color' => 'color',
                        'Luminosity' => 'luminosity',
                    ],
                ],
                'media_overlay' => [
                    'label' => 'Overlay Color',
                    'type' => 'gradient',
                    'internal' => 'media_overlay_gradient',
                ],
                'media_overlay_gradient' => [
                    'type' => 'hidden',
                ],
                '_media_overlay_parallax_button' => [
                    'description' =>
                        'Set an additional transparent overlay to soften the image or video.',
                    'type' => 'button-panel',
                    'text' => 'Edit Parallax',
                    'panel' => 'media-overlay-parallax',
                    'enable' => 'media_overlay',
                ],
            ],
            'fieldset' => [
                'default' => [
                    'fields' => [
                        [
                            'description' =>
                                'Set the width and height in pixels. Setting just one value preserves the original proportions. The image will be resized and cropped automatically.',
                            'name' => '_image_dimension',
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => ['image_width', 'image_height'],
                        ],
                        'media_focal_point',
                        'image_loading',
                        'image_size',
                        'image_position',
                        'image_effect',
                        '_image_parallax_button',
                        'media_visibility',
                        'media_background',
                        'media_blend_mode',
                        'media_overlay',
                        '_media_overlay_parallax_button',
                    ],
                ],
            ],
        ],
    ],
];
