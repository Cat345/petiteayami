<?php

namespace YOOtheme;

return [
    'name' => 'menu',
    'title' => 'Menu',
    'group' => 'system',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'show_all_children' => true,
        'style' => 'default',
        'image_align' => 'center',
    ],
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
    ],
    'transforms' => [
        'render' => [Builder\Wordpress\MenuElement::class, 'render'],
    ],
    'fields' => [
        'taxonomy' => [
            'label' => 'Menu',
            'type' => 'select',
            'options' => [
                [
                    'text' => 'Menu',
                    'value' => '',
                ],
                [
                    'evaluate' => 'yootheme.builder.allTaxonomies',
                ],
            ],
        ],
        'menu' => [
            'description' => 'Select a menu or a taxonomy that will be rendered as menu.',
            'type' => 'select',
            'defaultIndex' => 0,
            'options' => [
                [
                    'evaluate' => 'yootheme.customizer.menu.menusSelect()',
                ],
            ],
            'enable' => '!taxonomy',
        ],
        'menu_base_item' => [
            'label' => 'Base Item',
            'description' =>
                'By default, the menu is based on the current menu item. Alternatively, select a base item to always show the same menu.',
            'type' => 'select',
            'options' => [
                [
                    'text' => 'None',
                    'value' => '',
                ],
                [
                    'evaluate' => 'yootheme.customizer.menu.itemsSelect(menu)',
                ],
            ],
            'show' => '!taxonomy',
        ],
        'taxonomy_base_item' => [
            'label' => 'Base Item',
            'description' =>
                'By default, the menu is based on the current menu item. Alternatively, select a base item to always show the same menu.',
            'type' => 'select',
            'options' => [
                [
                    'text' => 'None',
                    'value' => '',
                ],
                [
                    'evaluate' => 'yootheme.builder.taxonomies[taxonomy].options',
                ],
            ],
            'show' => 'taxonomy',
        ],
        'start_level' => [
            'label' => 'Start Level',
            'type' => 'number',
            'attrs' => [
                'placeholder' => 1,
                'min' => 1,
                'max' => 10,
            ],
        ],
        'end_level' => [
            'label' => 'End Level',
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'All',
                'min' => 0,
                'max' => 10,
            ],
        ],
        'show_all_children' => [
            'text' => 'Show all submenu items',
            'type' => 'checkbox',
        ],
        'type' => [
            'label' => 'Type',
            'description' => 'Select the menu type.',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'Nav' => 'nav',
                'Subnav' => 'subnav',
                'Iconnav' => 'iconnav',
            ],
        ],
        'divider' => [
            'label' => 'Nav/Subnav Divider',
            'description' => 'Show optional dividers between nav or subnav items.',
            'type' => 'checkbox',
            'text' => 'Show dividers',
        ],
        'style' => [
            'label' => 'Nav Style',
            'description' => 'Select the nav style.',
            'type' => 'select',
            'options' => [
                'Default' => 'default',
                'Primary' => 'primary',
                'Secondary' => 'secondary',
            ],
        ],
        'size' => [
            'label' => 'Nav Primary Size',
            'description' => 'Select the primary nav size.',
            'type' => 'select',
            'options' => [
                'Default' => '',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
            ],
            'enable' => 'style == \'primary\'',
        ],
        'image_width' => [
            'label' => 'Image Width',
            'description' =>
                'Setting just one value preserves the original proportions. The image will be resized and cropped automatically, and where possible, high resolution images will be auto-generated.',
            'attrs' => [
                'placeholder' => 'auto',
            ],
        ],
        'image_height' => [
            'label' => 'Image Height',
            'description' =>
                'Setting just one value preserves the original proportions. The image will be resized and cropped automatically, and where possible, high resolution images will be auto-generated.',
            'attrs' => [
                'placeholder' => 'auto',
            ],
        ],
        'image_svg_inline' => [
            'label' => 'Inline SVG',
            'description' =>
                'Inject SVG images into the markup so they adopt the text color automatically.',
            'type' => 'checkbox',
            'text' => 'Make SVG stylable with CSS',
        ],
        'image_margin' => [
            'label' => 'Image and Title',
            'type' => 'checkbox',
            'text' => 'Add margin between',
        ],
        'image_align' => [
            'label' => 'Image Align',
            'type' => 'select',
            'options' => [
                'Top' => 'top',
                'Center' => 'center',
            ],
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
                        'taxonomy',
                        'menu',
                        'menu_base_item',
                        'taxonomy_base_item',
                        [
                            'name' => '_level',
                            'type' => 'grid',
                            'width' => '1-2',
                            'fields' => ['start_level', 'end_level'],
                        ],
                        'show_all_children',
                    ],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Menu',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                'type',
                                'divider',
                                'style',
                                'size',
                                'image_width',
                                'image_height',
                                'image_svg_inline',
                                'image_margin',
                                'image_align',
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
