<?php

namespace YOOtheme;

return [
    'name' => 'headline',
    'title' => 'Headline',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'title_element' => 'h1',
        'image_align' => 'left',
        'image_margin' => 'xsmall',
    ],
    'placeholder' => [
        'props' => [
            'content' => 'Headline',
        ],
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            // Don't render element if content fields are empty
            return $node->props['content'] != '';
        },
    ],
    'fields' => [
        'content' => [
            'label' => 'Content',
            'type' => 'editor',
            'root' => true,
            'source' => true,
        ],
        'link' => '${builder.link}',
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
        'title_style' => [
            'label' => 'Style',
            'description' => 'Headline styles differ in font size and font family.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Heading 3X-Large' => 'heading-3xlarge',
                'Heading 2X-Large' => 'heading-2xlarge',
                'Heading X-Large' => 'heading-xlarge',
                'Heading Large' => 'heading-large',
                'Heading Medium' => 'heading-medium',
                'Heading Small' => 'heading-small',
                'Heading H1' => 'h1',
                'Heading H2' => 'h2',
                'Heading H3' => 'h3',
                'Heading H4' => 'h4',
                'Heading H5' => 'h5',
                'Heading H6' => 'h6',
                'Text Meta' => 'text-meta',
                'Text Lead' => 'text-lead',
                'Text Small' => 'text-small',
                'Text Large' => 'text-large',
            ],
        ],
        'title_text_stroke' => [
            'type' => 'checkbox',
            'text' => 'Outline text',
        ],
        'title_decoration' => [
            'label' => 'Decoration',
            'description' =>
                'Decorate the headline with a divider, bullet or a line that is vertically centered to the heading.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Divider' => 'divider',
                'Bullet' => 'bullet',
                'Line' => 'line',
            ],
        ],
        'title_font_family' => [
            'label' => 'Font Family',
            'description' =>
                'Select an alternative font family. Mind that not all styles have different font families.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Default' => 'default',
                'Primary' => 'primary',
                'Secondary' => 'secondary',
                'Tertiary' => 'tertiary',
            ],
        ],
        'title_color' => [
            'label' => 'Color',
            'description' =>
                'Select the text color. If the Background option is selected, styles that don\'t apply a background image use the primary color instead.',
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
                'Background' => 'background',
            ],
        ],
        'link_style' => [
            'type' => 'checkbox',
            'text' => 'Show hover effect if linked.',
            'enable' => 'link',
        ],
        'title_element' => [
            'label' => 'HTML Element',
            'description' =>
                'Set the level for the section heading or give it no semantic meaning.',
            'type' => 'select',
            'options' => [
                'h1' => 'h1',
                'h2' => 'h2',
                'h3' => 'h3',
                'h4' => 'h4',
                'h5' => 'h5',
                'h6' => 'h6',
                'div' => 'div',
            ],
        ],
        'link_target' => [
            'label' => 'Attributes',
            'description'  => 'Optionally, open the link in a new window, treat it as download, don\'t endorse the linked page or don\'t include the referrer header.',
            'type' => 'checkbox',
            'text' => 'Open in a new window',
            'enable' => 'link'
        ],
        'link_download' => [
            'type' => 'checkbox',
            'text' => 'Download',
            'enable' => 'link'
        ],
        'link_rel_nofollow' => [
            'type' => 'checkbox',
            'text' => 'Nofollow',
            'enable' => 'link'
        ],
        'link_rel_noreferrer' => [
            'type' => 'checkbox',
            'text' => 'Noreferrer',
            'enable' => 'link'
        ],
        'image_width' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'image || icon',
        ],
        'image_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'image || icon',
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
        'image_loading' => [
            'label' => 'Loading',
            'description' =>
                'By default, images are loaded lazy. Enable eager loading for images in the initial viewport.',
            'type' => 'checkbox',
            'text' => 'Load image eagerly',
            'enable' => 'image',
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
            'enable' => 'image',
        ],
        'image_svg_inline' => [
            'label' => 'Inline SVG',
            'description' =>
                'Inject SVG images into the page markup so that they can easily be styled with CSS.',
            'type' => 'checkbox',
            'text' => 'Make SVG stylable with CSS',
            'enable' => 'image',
        ],
        'image_svg_animate' => [
            'type' => 'checkbox',
            'text' => 'Animate strokes',
            'enable' => 'image && image_svg_inline',
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
        'image_align' => [
            'label' => 'Alignment',
            'description' => 'Align the image to the left or right.',
            'type' => 'select',
            'options' => [
                'Left' => 'left',
                'Right' => 'right',
            ],
            'enable' => 'image || icon',
        ],
        'image_margin' => [
            'label' => 'Margin',
            'description' => 'Set the margin between the image and the content.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'X-Small' => 'xsmall',
                'Small' => 'small',
                'Medium' => 'medium',
            ],
            'enable' => 'image || icon',
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
        'text_align' => '${builder.text_align_justify}',
        'text_align_breakpoint' => '${builder.text_align_breakpoint}',
        'text_align_fallback' => '${builder.text_align_justify_fallback}',
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
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>, <code>.el-link</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element', '.el-link'],
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
                    'fields' => [
                        'content',
                        'link',
                        'image',
                        'image_alt',
                        'icon'
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Title',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'title_style',
                                'title_text_stroke',
                                'title_decoration',
                                'title_font_family',
                                'title_color',
                                'link_style',
                                'title_element',
                            ],
                        ],
                        [
                            'label' => 'Link',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'link_target',
                                'link_download',
                                'link_rel_nofollow',
                                'link_rel_noreferrer'
                            ],
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
                                'image_focal_point',
                                'image_loading',
                                'image_border',
                                'image_svg_inline',
                                'image_svg_animate',
                                'image_svg_color',
                                'image_align',
                                'image_margin',
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
