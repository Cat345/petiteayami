<?php

namespace YOOtheme;

return [
    'name' => 'alert',
    'title' => 'Alert',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'title_element' => 'h3',
    ],
    'placeholder' => [
        'props' => [
            'title' => '',
            'content' =>
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
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
            return $node->props['title'] != '' || $node->props['content'] != '';
        },
    ],
    'fields' => [
        'title' => [
            'label' => 'Title',
            'source' => true,
        ],
        'content' => [
            'label' => 'Content',
            'type' => 'editor',
            'source' => true,
        ],
        'link' => '${builder.link}',
        'alert_style' => [
            'label' => 'Style',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'Primary' => 'primary',
                'Success' => 'success',
                'Warning' => 'warning',
                'Danger' => 'danger',
            ],
        ],
        'alert_size' => [
            'type' => 'checkbox',
            'text' => 'Larger padding',
        ],
        'title_style' => [
            'label' => 'Style',
            'description' =>
                'Title styles differ in font-size but may also come with a predefined color, size and font.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Text Bold' => 'text-bold',
                'Heading Small' => 'heading-small',
                'Heading H1' => 'h1',
                'Heading H2' => 'h2',
                'Heading H3' => 'h3',
                'Heading H4' => 'h4',
                'Heading H5' => 'h5',
                'Heading H6' => 'h6',
            ],
            'enable' => 'title',
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
            'enable' => 'title',
        ],
        'title_inline' => [
            'label' => 'Alignment',
            'description' => 'Display the title in the same line as the content.',
            'type' => 'checkbox',
            'text' => 'Inline title',
            'enable' => 'title',
        ],
        'content_style' => [
            'label' => 'Style',
            'description' =>
                'Select a predefined text style, including color, size and font-family.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Lead' => 'lead',
                'Meta' => 'meta',
            ],
            'enable' => 'content',
        ],
        'content_margin' => [
            'label' => 'Margin Top',
            'description' =>
                'Set the top margin. Note that the margin will only apply if the content field immediately follows another content field.',
            'type' => 'select',
            'options' => [
                'X-Small' => 'xsmall',
                'Small' => 'small',
                'Default' => '',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
                'None' => 'remove',
            ],
            'enable' => 'content && !title_inline',
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
                'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>, <code>.el-title</code>, <code>.el-content</code>, <code>.el-link</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element', '.el-title', '.el-content', '.el-link'],
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
                        'title',
                        'content',
                        'link'
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Alert',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'alert_style',
                                'alert_size'
                            ],
                        ],
                        [
                            'label' => 'Title',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'title_style',
                                'title_element',
                                'title_inline'
                            ],
                        ],
                        [
                            'label' => 'Content',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'content_style',
                                'content_margin'
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
