<?php

namespace YOOtheme;

return [
    'name' => 'icon',
    'title' => 'Icon',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'icon_width' => 60,
        'margin_top' => 'default',
        'margin_bottom' => 'default',
    ],
    'placeholder' => [
        'props' => [
            'icon' => 'star',
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
            return (bool) $node->props['icon'];
        },
    ],
    'fields' => [
        'icon' => [
            'label' => 'Icon',
            'description' => 'Click on the pencil to pick an icon from the icon library.',
            'type' => 'icon',
            'source' => true,
        ],
        'link' => '${builder.link}',
        'link_aria_label' => '${builder.link_aria_label}',
        'icon_color' => [
            'label' => 'Color',
            'description' => 'Select the icon color.',
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
            'enable' => '!link',
        ],
        'icon_width' => [
            'label' => 'Icon Width',
            'description' => 'Set the icon width.',
            'enable' => 'link_style != \'button\'',
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
        'link_style' => [
            'label' => 'Style',
            'description' => 'Set the link style.',
            'type' => 'select',
            'options' => [
                'Icon Link' => '',
                'Icon Button' => 'button',
                'Link' => 'link',
                'Link Muted' => 'muted',
                'Link Text' => 'text',
                'Link Reset' => 'reset',
            ],
            'enable' => 'link',
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
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element'],
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
                        'icon',
                        'link',
                        'link_aria_label'
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Icon',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['icon_color', 'icon_width'],
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
                                'link_style'
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
