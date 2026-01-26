<?php

namespace YOOtheme;

return [
    'name' => 'subnav',
    'title' => 'Subnav',
    'group' => 'multiple items',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'container' => true,
    'width' => 500,
    'defaults' => [
        'show_image' => true,
    ],
    'placeholder' => [
        'children' => [
            [
                'type' => 'subnav_item',
            ],
            [
                'type' => 'subnav_item',
            ],
            [
                'type' => 'subnav_item',
            ],
        ],
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'fields' => [
        'content' => [
            'label' => 'Items',
            'type' => 'content-items',
            'title' => 'content',
            'item' => 'subnav_item',
        ],
        'show_image' => [
            'label' => 'Display',
            'description' =>
                'Show or hide content fields without the need to delete the content itself.',
            'type' => 'checkbox',
            'text' => 'Show the image',
        ],
        'subnav_style' => [
            'label' => 'Style',
            'description' => 'Select the subnav style.',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'Divider' => 'divider',
                'Pill' => 'pill',
                'Tab' => 'tab',
            ],
        ],
        'html_element' => [
            'label' => 'HTML Element',
            'description' => 'Define a navigation menu or give it no semantic meaning.',
            'type' => 'select',
            'options' => [
                'div' => '',
                'nav' => 'nav',
            ],
        ],
        'subnav_wrap' => [
            'label' => 'Wrap',
            'description' =>
                'Set whether subnav items are forced into one line or can wrap into multiple lines.',
            'type' => 'checkbox',
            'text' => 'Don\'t wrap into multiple lines',
        ],
        'image_width' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'show_image',
        ],
        'image_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'show_image',
        ],
        'image_loading' => [
            'label' => 'Loading',
            'description' =>
                'By default, images are loaded lazy. Enable eager loading for images in the initial viewport.',
            'type' => 'checkbox',
            'text' => 'Load image eagerly',
            'enable' => 'show_image',
        ],
        'image_border' => [
            'label' => 'Border',
            'description' => 'Select the image border style.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Rounded' => 'rounded',
                'Circle' => 'circle',
                'Pill' => 'pill',
            ],
            'enable' => 'show_image',
        ],
        'image_margin' => [
            'label' => 'Margin',
            'type' => 'checkbox',
            'text' => 'Add margin between',
            'enable' => 'show_image',
        ],
        'image_svg_inline' => [
            'label' => 'Inline SVG',
            'description' =>
                'Inject SVG images into the page markup so that they can easily be styled with CSS.',
            'type' => 'checkbox',
            'text' => 'Make SVG stylable with CSS',
            'enable' => 'show_image',
        ],
        'image_svg_animate' => [
            'type' => 'checkbox',
            'text' => 'Animate strokes',
            'enable' => 'show_image && image_svg_inline',
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
            'enable' => 'show_image',
        ],
        'position' => '${builder.position}',
        'position_left' => '${builder.position_left}',
        'position_right' => '${builder.position_right}',
        'position_top' => '${builder.position_top}',
        'position_bottom' => '${builder.position_bottom}',
        'position_z_index' => '${builder.position_z_index}',
        'blend' => '${builder.blend}',
        'margin_top' => '${builder.margin_top}',
        'margin_bottom' => '${builder.margin_bottom}',
        'maxwidth' => '${builder.maxwidth}',
        'maxwidth_breakpoint' => '${builder.maxwidth_breakpoint}',
        'block_align' => '${builder.block_align}',
        'block_align_breakpoint' => '${builder.block_align_breakpoint}',
        'block_align_fallback' => '${builder.block_align_fallback}',
        'text_align' => '${builder.text_align}',
        'text_align_breakpoint' => '${builder.text_align_breakpoint}',
        'text_align_fallback' => '${builder.text_align_fallback}',
        'animation' => '${builder.animation}',
        '_parallax_button' => '${builder._parallax_button}',
        'visibility' => '${builder.visibility}',
        'name' => '${builder.name}',
        'status' => '${builder.status}',
        'source' => '${builder.source}',
        'id' => '${builder.id}',
        'class' => '${builder.cls}',
        'attributes' => '${builder.attrs}',
        'css' => [
            'label' => 'CSS',
            'description' =>
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>, <code>.el-item</code>, <code>.el-content</code>, <code>.el-link</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element', '.el-item', '.el-content', '.el-link'],
            ],
            'source' => true,
        ],
        'transform' => '${builder.transform}',
    ],
    'fieldset' => [
        'default' => [
            'type' => 'tabs',
            'fields' => [
                [
                    'title' => 'Content',
                    'fields' => ['content', 'show_image'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Subnav',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['subnav_style', 'html_element', 'subnav_wrap'],
                        ],
                        [
                            'label' => 'Image',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                [
                                    'label' => 'Width/Height',
                                    'description' =>
                                        'Setting just one value preserves the original proportions. The image will be resized and cropped automatically, and where possible, high resolution images will be auto-generated.',
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => ['image_width', 'image_height'],
                                ],
                                'image_loading',
                                'image_margin',
                                'image_border',
                                'image_svg_inline',
                                'image_svg_animate',
                                'image_svg_color',
                            ],
                        ],
                        [
                            'label' => 'General',
                            'type' => 'group',
                            'fields' => [
                                'position',
                                'position_left',
                                'position_right',
                                'position_top',
                                'position_bottom',
                                'position_z_index',
                                'blend',
                                'margin_top',
                                'margin_bottom',
                                'maxwidth',
                                'maxwidth_breakpoint',
                                'block_align',
                                'block_align_breakpoint',
                                'block_align_fallback',
                                'text_align',
                                'text_align_breakpoint',
                                'text_align_fallback',
                                'animation',
                                '_parallax_button',
                                'visibility',
                            ],
                        ],
                    ],
                ],
                '${builder.advanced}',
            ],
        ],
    ],
];
