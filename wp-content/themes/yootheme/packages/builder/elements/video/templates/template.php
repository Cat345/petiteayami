<?php

namespace YOOtheme;

$el = $this->el('div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 uk-flex uk-flex-column uk-flex-top {@height_expand}',
    ],

]);

$media = include "{$__dir}/template-video.php";

// Media
if (!$this->iframeVideo($props['video'])) {

    $media->attr([

        'class' => [
            'uk-flex-1 {@height_expand}',
            'uk-object-{video_focal_point}',
        ],

        'style' => [
            // Keep video responsiveness but with new proportions (because video isn't cropped like an image)
            'aspect-ratio: {video_width} / {video_height};',

            'height: 100vh; {@!height_viewport_height} {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
            'height: {height_viewport_height}vh; {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
        ],

        'uk-height-viewport' => $props['height_viewport'] && $props['height_viewport_offset'] && !$props['height_expand'] ? [
            'property: height;',
            'offset-top: true; {@height_viewport_offset}',
            'offset-bottom: {0}; {@height_viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
        ] : false,

    ]);

    if ($props['poster'] && $props['video_loading'] != 'click') {

        $image = Image::create($props['poster']);

        if ($image && $image->isResizable() && ($props['video_width'] || $props['video_height'])) {

            $thumbnail = [$props['video_width'], $props['video_height'], false];
            if (!empty($props['video_focal_point'])) {
                [$y, $x] = explode('-', $props['video_focal_point']);
                $thumbnail += [3 => $x, 4 => $y];
            }

            $props['poster'] = $image->thumbnail(...$thumbnail);

        }

        $video->attr([
            'poster' => Url::to($props['poster']),
        ]);

    }
}

// Decoration
$decoration = $props['video_box_decoration'] ? $this->el('div', [

    'class' => [
        'uk-box-shadow-bottom {@video_box_decoration: shadow}',
        'tm-mask-default {@video_box_decoration: mask}',
        'tm-box-decoration-{video_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@video_box_decoration_inverse} {@video_box_decoration: default|primary|secondary}',

        'uk-inline {@!height_expand}',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

]) : null;

// Placeholder (On Click + Consent)
$placeholder_container = '';
$placeholder_image = '';

if ($props['video_loading'] == 'click' || $props['consent']) {

    $placeholder_container = $this->el('div', [

        'class' => [
            'uk-{text_color}',

            'uk-inline {@!height_expand}',
            'uk-flex-1 uk-flex uk-flex-column uk-position-relative {@height_expand}',
        ],

    ]);

    $media->attr([

        'class' => [
            'uk-flex-1 {@height_expand}'
        ],

        'hidden'
    ]);

    $placeholder_image = include "{$__dir}/template-image.php";

    $placeholder_image->attr([

        'class' => [
            'uk-flex-1 {@height_expand}',
            'uk-object-{video_focal_point}' => $props['height_viewport'] || $props['height_expand'],
        ],

        'style' => [
            'height: 100vh; {@!height_viewport_height} {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
            'height: {height_viewport_height}vh; {@height_viewport} {@!height_viewport_offset} {@!height_expand}',
            // Fix bug in Safari not stretching an image beyond its intrinsic height
            'aspect-ratio: auto; {@height_expand}',
        ],

        'uk-height-viewport' => $props['height_viewport'] && $props['height_viewport_offset'] && !$props['height_expand'] ? [
            'property: height;',
            'offset-top: true; {@height_viewport_offset}',
            'offset-bottom: {0}; {@height_viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
        ] : false,
    ]);

}

($placeholder_container ?: $media)->attr('class', [
        'el-image',
        'uk-border-{video_border}',
        'uk-box-shadow-{video_box_shadow}',
        'uk-box-shadow-hover-{video_hover_box_shadow} {@video_loading: click}',
        'uk-inverse-{text_color}',
]);

// On Click
$placeholder_link = '';
$play_icon = '';
$play_icon_container = '';

if ($props['video_loading'] == 'click') {

    $placeholder_link = $this->el('a', [

        ($props['consent'] ? 'data' : 'uk') . '-toggle' => 'target: ! > *',

        'class' => [
            'uk-disabled {@consent}',
            'uk-inline {@!height_expand}',
            'uk-flex-1 uk-flex uk-flex-column {@height_expand}',

            'uk-visible-toggle {@play_icon_hover}',
        ],

        'aria-label' => ['{link_aria_label}'],

    ]);

    if ($props['play_icon']) {

        $play_icon = include "{$__dir}/template-icon.php";

        $play_icon_container = $this->el('div', [

            'class' => [
                'uk-hidden {@consent}',
                'uk-position-center',
                'uk-hidden-hover uk-hidden-touch {@play_icon_hover}',
            ],

        ]);
    }
}

// Consent
$consent = '';

if ($props['consent']) {

    $consent = $this->el('div', [

        'class' => [
            'js-consent-overlay',
            'uk-position-center',
        ],

        'style' => [
            'visibility: hidden',
        ]

    ]);

    $consent_card = $this->el('div', [

        'class' => [
            'uk-card uk-card-overlay uk-card-body uk-text-center',
        ],

    ]);

    $consent_button = $this->el('button', [

        'type' => 'button',

        'class' => [
            'uk-button uk-button-primary',
        ],

    ]);

    $consent_icon = $this->el('image', [

        'src' => $props['consent_icon'],
        'width' => '60',
        'alt' => true,
        'loading' => !$props['video_loading'] ? false : null,
        'thumbnail' => true,

    ]);

}

?>

<?= $el($props, $attrs) ?>

    <?php if ($decoration) : ?>
    <?= $decoration($props) ?>
    <?php endif ?>

        <?php if ($placeholder_container) : ?>
        <?= $placeholder_container($props) ?>
        <?php endif ?>

            <?= $video($props, '') ?>

            <?php if ($placeholder_link) : ?>
            <?= $placeholder_link($props) ?>
            <?php endif ?>

                <?php if ($placeholder_image) : ?>
                <?= $placeholder_image($props, '') ?>
                <?php endif ?>

                <?php if ($play_icon) : ?>
                <?= $play_icon_container($props) ?>
                    <?= $play_icon($props, '') ?>
                <?= $play_icon_container->end() ?>
                <?php endif ?>

            <?php if ($placeholder_link) : ?>
            <?= $placeholder_link->end() ?>
            <?php endif ?>

            <?php if ($consent) : ?>
            <?= $consent($props) ?>

                <?php if (!$props['consent_card_size']) : ?>
                <?= $consent_card($props, ['class' => ['uk-visible@m']]) ?>
                    <?= $consent_icon($props, '') ?>
                    <p class="uk-margin-small-top"><?= $props['consent_content'] ?? '' ?></p>
                    <?= $consent_button($props, $props['consent_accept_button'] ?? '') ?>
                <?= $consent_card->end() ?>
                <?php endif ?>

                <?= $consent_card($props, ['class' => ['uk-card-small', !$props['consent_card_size'] ? 'uk-hidden@m' : '']]) ?>
                    <?= $consent_icon($props, ['width' => '40'], '') ?>
                    <p class="uk-text-small uk-margin-small"><?= $this->trans($props['consent_content'] ?? '') ?></p>
                    <?= $consent_button($props, ['class' => ['uk-button-small']], $props['consent_accept_button'] ?? '') ?>
                <?= $consent_card->end() ?>

            <?= $consent->end() ?>
            <?php endif ?>

        <?php if ($placeholder_container) : ?>
        <?= $placeholder_container->end() ?>
        <?php endif ?>

    <?php if ($decoration) : ?>
    <?= $decoration->end() ?>
    <?php endif ?>

<?= $el->end() ?>
