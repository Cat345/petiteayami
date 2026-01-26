<?php

namespace YOOtheme;

return [
    'name' => 'fragment',
    'title' => 'Sublayout',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'container' => true,
    'fragment' => true,
    'width' => 500,
    'defaults' => [
        'margin_top' => 'default',
        'margin_bottom' => 'default',
    ],
    'placeholder' => [],
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'prerender' => function ($node, $params) {
            $node->props['isPartial'] =
                !$params['parent'] && str_starts_with($params['template'] ?? '', '_');

            if ($node->props['isPartial']) {
                $metadata = app(Metadata::class);
                $node->props['metadata'] = $metadata->all();
            }
        },

        'render' => function ($node, $params) {
            $node->props['root'] = !$params['parent'];

            if ($node->props['isPartial']) {
                $metadata = app(Metadata::class);
                $node->props['metadata'] = array_diff($metadata->all(), $node->props['metadata']);
                array_walk($node->props['metadata'], function ($metadata) {
                    if ($metadata->src) {
                        $metadata->attributes['src'] = Url::to($metadata->src);
                    }

                    if ($metadata->href) {
                        $metadata->attributes['href'] = Url::to($metadata->href);
                    }
                });
            }
        },
    ],
    'fields' => [
        'content' => [
            'type' => 'builder-fragment',
        ],
        'html_element' => '${builder.html_element}',
        'position' => '${builder.position}',
        'position_left' => '${builder.position_left}',
        'position_right' => '${builder.position_right}',
        'position_top' => '${builder.position_top}',
        'position_bottom' => '${builder.position_bottom}',
        'position_z_index' => '${builder.position_z_index}',
        'blend' => '${builder.blend}',
        'margin_top' => '${builder.margin_top}',
        'margin_bottom' => '${builder.margin_bottom}',
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
    ],
    'fieldset' => [
        'default' => [
            'type' => 'tabs',
            'fields' => [
                [
                    'title' => 'Content',
                    'fields' => ['content'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Sublayout',
                            'type' => 'group',
                            'fields' => ['html_element'],
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
