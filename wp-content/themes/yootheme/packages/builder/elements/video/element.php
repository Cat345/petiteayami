<?php

namespace YOOtheme;

use YOOtheme\Builder\Listener\LoadVimeoScript;
use YOOtheme\Builder\Listener\LoadYoutubeScript;
use YOOtheme\Theme\I18nConfig;
use YOOtheme\Theme\ThemeConfig;
use YOOtheme\Theme\ViewHelper;

return [
    'name' => 'video',
    'title' => 'Video',
    'group' => 'basic',
    'icon' => '${url:images/icon.svg}',
    'iconSmall' => '${url:images/iconSmall.svg}',
    'element' => true,
    'width' => 500,
    'defaults' => [
        'video_loading' => 'lazy',
        'video_controls' => true,
        'margin_top' => 'default',
        'margin_bottom' => 'default',
    ],
    'placeholder' => [
        'props' => [
            'video' => Url::to('~assets/images/element-video-placeholder.mp4'),
        ],
    ],
    'updates' => __DIR__ . '/updates.php',
    'templates' => [
        'render' => __DIR__ . '/templates/template.php',
        'content' => __DIR__ . '/templates/content.php',
    ],
    'transforms' => [
        'render' => function ($node) {
            /** @var Config $config */
            $config = app(Config::class);

            // Fix Chrome bug - initially the poster image is not shown
            if ($config('app.isCustomizer') && $node->props['poster']) {
                $node->attrs['data-preview'] = 'reload';
            }

            $node->props['consent'] = false;

            $types = [LoadYoutubeScript::TYPE => ViewHelper::REGEX_YOUTUBE, LoadVimeoScript::TYPE => ViewHelper::REGEX_VIMEO];

            foreach ($types as $type => $regexp) {
                if (preg_match($regexp, $node->props['video'] ?? '', $matches)) {
                    if (!$node->props['poster']) {
                        switch ($type) {
                            case LoadYoutubeScript::TYPE:
                                $playIcon = 'consent_icon_youtube.svg';
                                $node->props['poster'] = "https://img.youtube.com/vi/{$matches['id']}/maxresdefault.jpg";
                                break;
                            case LoadVimeoScript::TYPE:
                                $playIcon = 'consent_icon_vimeo.svg';
                                $node->props['poster'] = $node->props['video'];
                                break;
                        }

                        $node->props['play_icon'] = $node->props['play_icon'] ?: "~assets/images/{$playIcon}";
                    }

                    /**
                     * @var I18nConfig $i18n
                     * @var ThemeConfig $theme
                     * @var Metadata $metadata
                     */
                    [$i18n, $theme, $metadata] = app(
                        I18nConfig::class,
                        ThemeConfig::class,
                        Metadata::class,
                    );

                    foreach ($theme->scripts as &$script) {
                        if ($script['type'] !== $type) {
                            continue;
                        }

                        $metadata->set('script:video-consent', [
                            'src' => '~assets/site/js/video.js',
                            'type' => 'module',
                        ]);

                        $script['active'] = true;

                        $node->props += $script['element'] ?? [];
                        $node->props['consent'] = true;
                        $node->props['consent_accept_button'] = $i18n->get('consent.button_accept');

                        $service = "{$script['category']}.{$script['service']}";
                        $node->attrs['data-video-consent'] = $service;

                        break;
                    }
                }
            }

            if (($node->props['video_loading'] == 'click' || $node->props['consent']) && !$node->props['poster']) {
                $node->props['poster'] = Url::to('~assets/images/element-video-placeholder.png');
            }

            // Don't render element if content fields are empty
            return (bool) $node->props['video'];
        },
    ],
    'fields' => [
        'video' => [
            'label' => 'Video',
            'description' =>
                'Select a video file or enter a link from <a href="https://www.youtube.com" target="_blank">YouTube</a> or <a href="https://vimeo.com" target="_blank">Vimeo</a>.',
            'type' => 'video',
            'source' => true,
        ],
        'video_title' => [
            'label' => 'Video Title',
            'source' => true,
            'show' => 'video && !yootheme.builder.helpers.Media.isVideo(video)',
        ],
        'poster' => [
            'label' => 'Poster Image',
            'description' =>
                'Select a poster image which is also used as a placeholder if the video is loaded on click or before consent is given.',
            'type' => 'image',
            'source' => true,
            'enable' => 'video',
        ],
        'play_icon' => [
            'label' => 'Play Icon',
            'description' => 'Select an optional play icon for the video loaded on click.',
            'type' => 'image',
            'enable' => 'video && video_loading == \'click\'',
        ],
        'link_aria_label' => [
            'label' => 'ARIA Label',
            'description' => 'Enter a descriptive text label to make it accessible if the video is loaded on click.',
            'enable' => 'video && video_loading == \'click\'',
        ],
        'video_width' => [
            'type' => 'number',
        ],
        'video_height' => [
            'type' => 'number',
        ],
        'video_focal_point' => [
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
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'height_expand' => [
            'label' => 'Height',
            'description' =>
                'Expand the height of the element to fill the available space in the column. Alternatively, the height can adapt to the height of the viewport, and optionally subtract the header height to fill the first visible viewport.',
            'type' => 'checkbox',
            'text' => 'Fill the available column space',
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'height_viewport' => [
            'type' => 'checkbox',
            'text' => 'Set viewport height',
            'enable' => '!height_expand && !yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'height_viewport_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => '100',
                'min' => 0,
                'step' => 10,
            ],
            'enable' =>
                '!height_expand && !yootheme.builder.helpers.Media.isIframeVideo(video) && height_viewport',
        ],
        'height_viewport_offset' => [
            'type' => 'checkbox',
            'text' => 'Subtract height above',
            'enable' =>
                '!height_expand && !yootheme.builder.helpers.Media.isIframeVideo(video) && height_viewport && (height_viewport_height || 0) <= 100',
        ],
        'video_loading' => [
            'label' => 'Loading',
            'description' =>
                'Load the video eagerly, lazily when it enters the viewport, or only after a placeholder image is clicked. If YouTube and Vimeo videos are loaded on click, no external requests are sent, and no JavaScript is loaded until the video is played.',
            'type' => 'select',
            'options' => [
                'Eager' => '',
                'Lazy' => 'lazy',
                'On Click' => 'click',
            ],
        ],
        'video_autoplay' => [
            'label' => 'Autoplay',
            'description' =>
                'Enable autoplay immediately, start as soon as the video enters the viewport or only on hover.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'On' => true,
                'Inview' => 'inview',
                'Hover' => 'hover',
            ],
            'enable' =>
                '(video_loading != \'click\') && !yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_autoplay_restart' => [
            'type' => 'checkbox',
            'text' => 'Restart from beginning',
            'enable' =>
                '$match(video_autoplay, \'^(inview|hover)$\') && (video_loading != \'click\') && !yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_controls' => [
            'label' => 'Options',
            'type' => 'checkbox',
            'text' => 'Show controls',
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_playsinline' => [
            'type' => 'checkbox',
            'text' => 'Play video inline',
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_loop' => [
            'type' => 'checkbox',
            'text' => 'Loop video',
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_muted' => [
            'type' => 'checkbox',
            'text' => 'Mute video',
            'enable' => '!yootheme.builder.helpers.Media.isIframeVideo(video)',
        ],
        'video_border' => [
            'label' => 'Border',
            'description' => 'Select the video border style.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Rounded' => 'rounded',
                'Circle' => 'circle',
                'Pill' => 'pill',
            ],
        ],
        'video_box_shadow' => [
            'label' => 'Box Shadow',
            'description' => 'Select the video box shadow size.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Small' => 'small',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
            ],
        ],
        'video_hover_box_shadow' => [
            'label' => 'Hover Box Shadow',
            'description' => 'Select the video box shadow size on hover.',
            'type' => 'select',
            'options' => [
                'None' => '',
                'Small' => 'small',
                'Medium' => 'medium',
                'Large' => 'large',
                'X-Large' => 'xlarge',
            ],
            'enable' => 'video_loading == \'click\'',
        ],
        'video_box_decoration' => [
            'label' => 'Box Decoration',
            'description' => 'Select the video box decoration style.',
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
        'video_box_decoration_inverse' => [
            'type' => 'checkbox',
            'text' => 'Inverse style',
            'enable' => '$match(video_box_decoration, \'^(default|primary|secondary)$\')',
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
        'play_icon_width' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'play_icon',
        ],
        'play_icon_height' => [
            'type' => 'number',
            'attrs' => [
                'placeholder' => 'auto',
            ],
            'enable' => 'play_icon',
        ],
        'play_icon_hover' => [
            'type' => 'checkbox',
            'text' => 'Show on hover only',
            'enable' => 'play_icon',
        ],
        'play_icon_svg_inline' => [
            'label' => 'Inline SVG',
            'description' =>
                'Inject SVG images into the page markup so that they can easily be styled with CSS.',
            'type' => 'checkbox',
            'text' => 'Make SVG stylable with CSS',
            'enable' => 'play_icon',
        ],
        'consent_card_size' => [
            'label' => 'Card Size',
            'type' => 'checkbox',
            'text' => 'Small card size',
            'enable' => 'yootheme.builder.helpers.Media.isIframeVideo(video)',
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
                    'fields' => ['video', 'video_title', 'poster', 'play_icon', 'link_aria_label'],
                ],
                [
                    'title' => 'Settings',
                    'fields' => [
                        [
                            'label' => 'Video',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                [
                                    'label' => 'Width/Height',
                                    'description' => 'Set the video dimensions.',
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => ['video_width', 'video_height'],
                                ],
                                'video_focal_point',
                                'height_expand',
                                'height_viewport',
                                'height_viewport_height',
                                'height_viewport_offset',
                                'video_loading',
                                'video_autoplay',
                                'video_autoplay_restart',
                                'video_controls',
                                'video_playsinline',
                                'video_loop',
                                'video_muted',
                                'video_border',
                                'video_box_shadow',
                                'video_hover_box_shadow',
                                'video_box_decoration',
                                'video_box_decoration_inverse',
                                'text_color',
                            ],
                        ],
                        [
                            'label' => 'Play Icon',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => [
                                [
                                    'label' => 'Width/Height',
                                    'description' =>
                                        'Setting just one value preserves the original proportions. The image will be resized and cropped automatically, and where possible, high resolution images will be auto-generated.',
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => ['play_icon_width', 'play_icon_height'],
                                ],
                                'play_icon_hover',
                                'play_icon_svg_inline',
                            ],
                        ],
                        [
                            'label' => 'Consent',
                            'type' => 'group',
                            'divider' => true,
                            'fields' => ['consent_card_size'],
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
