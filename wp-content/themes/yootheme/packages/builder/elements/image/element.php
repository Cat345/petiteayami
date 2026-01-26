<?php

namespace YOOtheme;

return [
    'name' => 'image',
    'title' => 'Image',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'margin_top' => 'default',
        'margin_bottom' => 'default',
        'image_svg_color' => 'emphasis',
    ],
    'placeholder' => [
        'props' => [
            'image' => Url::to('~assets/images/element-image-placeholder.png'),
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
            return (bool) $node->props['image'];
        },
    ],
    'fields' => [
        'image' => '${builder.image}',
        'image_alt' => [
            'label' => 'Image Alt',
            'description' => 'Enter the image alt attribute.',
            'source' => true,
            'enable' => 'image',
        ],
        'link' => '${builder.link}',
        'link_aria_label' => '${builder.link_aria_label}',
        'image_width' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
        ],
        'image_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
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
        'height_expand' => [
            'label' => 'Height',
            'description' =>
                'Expand the height of the element to fill the available space in the column. Alternatively, the height can adapt to the height of the viewport, and optionally subtract the header height to fill the first visible viewport.',
            'type' => 'checkbox',
            'text' => 'Fill the available column space',
        ],
        'height_viewport' => [
            'type' => 'checkbox',
            'text' => 'Set viewport height',
            'enable' => '!height_expand',
        ],
        'height_viewport_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => '100',
                'min' => 0,
                'step' => 10,
            ],
            'enable' => '!height_expand && height_viewport',
        ],
        'height_viewport_offset' => [
            'type' => 'checkbox',
            'text' => 'Subtract height above',
            'enable' => '!height_expand && height_viewport && (height_viewport_height || 0) <= 100',
        ],
        'image_loading' => [
            'label' => 'Loading',
            'description' =>
                'By default, images are loaded lazy. Enable eager loading for images in the initial viewport.',
            'type' => 'checkbox',
            'text' => 'Load image eagerly',
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
        ],
        'image_box_shadow' => [
            'label' => 'Box Shadow',
            'description' => 'Select the image box shadow size.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Small' => 'small',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
            ],
        ],
        'image_hover_box_shadow' => [
            'label' => 'Hover Box Shadow',
            'description' => 'Select the image box shadow size on hover.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Small' => 'small',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
            ],
            'enable' => 'link',
        ],
        'image_box_decoration' => [
            'label' => 'Box Decoration',
            'description' => 'Select the image box decoration style.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Default' => 'default',
                'Primary' => 'primary',
                'Secondary' => 'secondary',
                'Floating Shadow' => 'shadow',
                'Mask' => 'mask',
            ],
        ],
        'image_box_decoration_inverse' => [
            'type' => 'checkbox',
            'text' => 'Inverse style',
            'enable' => '$match(image_box_decoration, \'^(default|primary|secondary)$\')',
        ],
        'image_svg_inline' => [
            'label' => 'Inline SVG',
            'description' =>
                'Inject SVG images into the page markup so that they can easily be styled with CSS.',
            'type' => 'checkbox',
            'text' => 'Make SVG stylable with CSS',
        ],
        'image_svg_animate' => [
            'type' => 'checkbox',
            'text' => 'Animate strokes',
            'enable' => 'image_svg_inline',
        ],
        'image_svg_color' => [
            'label' => 'SVG Color',
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
            'enable' => 'image_svg_inline',
        ],
        'text_color' => [
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
        ],
        'link_target' => [
            'label' => 'Attributes',
            'description'  => 'Optionally, open the link in a new window, treat it as download, don\'t endorse the linked page or don\'t include the referrer header.',
            'type' => 'checkbox',
            'text' => 'Open in a new window',
            'enable' => 'link && !lightbox'
        ],
        'link_download' => [
            'type' => 'checkbox',
            'text' => 'Download',
            'enable' => 'link && !lightbox'
        ],
        'link_rel_nofollow' => [
            'type' => 'checkbox',
            'text' => 'Nofollow',
            'enable' => 'link && !lightbox'
        ],
        'link_rel_noreferrer' => [
            'type' => 'checkbox',
            'text' => 'Noreferrer',
            'enable' => 'link && !lightbox'
        ],
        'lightbox' => [
            'label' => 'Modal',
            'type' => 'checkbox',
            'text' => 'Enable modal window',
            'enable' => 'link',
        ],
        'lightbox_width' => [
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'link && lightbox',
        ],
        'lightbox_height' => [
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'link && lightbox',
        ],
        'lightbox_image_focal_point' => [
            'label' => 'Modal Image Focal Point',
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
        'container_padding_remove' => '${builder.container_padding_remove}',
        'name' => '${builder.name}',
        'status' => '${builder.status}',
        'source' => '${builder.source}',
        'id' => '${builder.id}',
        'class' => '${builder.cls}',
        'attributes' => '${builder.attrs}',
        'css' => [
            'label' => 'CSS',
            'description' =>
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>, <code>.el-image</code>, <code>.el-link</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element', '.el-image', '.el-link'],
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
                    'fields' => ['image', 'image_alt', 'link', 'link_aria_label'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
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
                                'height_expand',
                                'height_viewport',
                                'height_viewport_height',
                                'height_viewport_offset',
                                'image_loading',
                                'image_border',
                                'image_box_shadow',
                                'image_hover_box_shadow',
                                'image_box_decoration',
                                'image_box_decoration_inverse',
                                'image_svg_inline',
                                'image_svg_animate',
                                'image_svg_color',
                                'text_color',
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
                                'link_rel_noreferrer',
                                'lightbox',
                                [
                                    'label' => 'Modal Width/Height',
                                    'description' =>
                                        'Set the width and height for the modal content, i.e. image, video or iframe.',
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => ['lightbox_width', 'lightbox_height'],
                                ],
                                'lightbox_image_focal_point',
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
                                'container_padding_remove',
                            ],
                        ],
                    ],
                ],
                '${builder.advanced}',
            ],
        ],
    ],
];
