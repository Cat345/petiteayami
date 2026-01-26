<?php

namespace YOOtheme;

return [
    'name' => 'countdown',
    'title' => 'Countdown',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'show_separator' => true,
        'show_label' => true,
        'grid_column_gap' => 'small',
        'grid_row_gap' => 'small',
        'countdown_style' => 'heading-medium',
        'label_margin' => 'small',
        'margin_top' => 'default',
        'margin_bottom' => 'default',
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'placeholder' => [
        'props' => [
            'date' => date('Y-m-d', strtotime('+1 week')),
        ],
    ],

    'transforms' => [
        'render' => function ($node) {

            // Don't render element if content fields are empty
            if (!$node->props['date']) {
                return false;
            }

            $time = strtotime($node->props['date']);

            if (!$time) {
                return false;
            }

            $node->props['date'] = date(DATE_W3C, $time);

            return !$node->props['reload'] || $time > time();
        },
    ],
    'fields' => [
        'date' => [
            'label' => 'Date',
            'type' => 'datetime',
            'description' => 'Enter a date for the countdown to expire.',
            'source' => true,
        ],
        'label_days' => [
            'label' => 'Labels',
            'attrs' => [
                'placeholder' => 'Days',
            ],
        ],
        'label_hours' => [
            'attrs' => [
                'placeholder' => 'Hours',
            ],
        ],
        'label_minutes' => [
            'attrs' => [
                'placeholder' => 'Minutes',
            ],
        ],
        'label_seconds' => [
            'attrs' => [
                'placeholder' => 'Seconds',
            ],
        ],
        'show_label' => [
            'description' => 'Enter labels for the countdown time.',
            'type' => 'checkbox',
            'text' => 'Show Labels',
        ],
        'reload' => [
            'label' => 'Reload',
            'description' => 'Reload the page after the countdown expires.',
            'type' => 'checkbox',
            'text' => 'Reload page when expired',
        ],
        'grid_column_gap' => [
            'label' => 'Column Gap',
            'description' => 'Set the size of the column gap between the numbers.',
            'type' => 'select',
            'options' => [
                'Small' => 'small',
                'Medium' => 'medium',
                'Default' => '',
                'Large' => 'large',
                'None' => 'collapse',
            ],
        ],
        'grid_row_gap' => [
            'label' => 'Row Gap',
            'description' => 'Set the size of the row gap between the numbers.',
            'type' => 'select',
            'options' => [
                'Small' => 'small',
                'Medium' => 'medium',
                'Default' => '',
                'Large' => 'large',
                'None' => 'collapse',
            ],
        ],
        'show_separator' => [
            'label' => 'Separator',
            'description' => 'Show a separator between the numbers.',
            'type' => 'checkbox',
            'text' => 'Show Separators',
        ],
        'countdown_style' => [
            'label' => 'Style',
            'description' =>
                'Select a predefined text style, including color, size and font-family.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Text Meta' => 'text-meta',
                'Text Lead' => 'text-lead',
                'Text Small' => 'text-small',
                'Text Large' => 'text-large',
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
            ],
        ],
        'countdown_font_family' => [
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
        'countdown_color' => [
            'label' => 'Color',
            'description' =>
                'Select the text color.',
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
        ],
        'label_style' => [
            'label' => 'Style',
            'description' =>
                'Select a predefined text style, including color, size and font-family.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Text Meta' => 'text-meta',
                'Text Lead' => 'text-lead',
                'Text Small' => 'text-small',
                'Text Large' => 'text-large',
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
            ],
        ],
        'label_color' => [
            'label' => 'Color',
            'description' => 'Select the text color.',
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
        ],
        'label_margin' => [
            'label' => 'Margin',
            'description' => 'Set the margin between the countdown and the label text.',
            'type' => 'select',
            'options' => [
                'X-Small' => 'xsmall',
                'Small' => 'small',
                'Default' => '',
                'Medium' => 'medium',
                'None' => 'remove',
            ],
            'enable' => 'show_label',
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
                        'date',
                        'label_days',
                        'label_hours',
                        'label_minutes',
                        'label_seconds',
                        'show_label',
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Countdown',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'reload',
                                'grid_column_gap',
                                'grid_row_gap',
                                'show_separator',
                                'countdown_style',
                                'countdown_font_family',
                                'countdown_color',
                            ],
                        ],
                        [
                            'label' => 'Label',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['label_style', 'label_color', 'label_margin'],
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
