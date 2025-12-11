<?php

// Initialize prop if not set
$props += [
    'media_overlay_gradient' => null,
    'background_parallax_background' => null,
];

// Resets
if (!($props['image'] || $props['video'])) {
    $props['media_overlay'] = false;
    $props['media_overlay_gradient'] = false;
}
if (!($props['height'] == 'viewport' && $props['height_viewport'] <= 100 || $props['height'] == 'section')) {
    $props['height_offset_top'] = false;
}
if (!$props['height']) {
    $props['vertical_align'] = false;
}

if ($props['image']) { $props['video'] = false; }

$el = $this->el($props['html_element'] ?: 'div', $attrs);

$el->attr([

    'class' => [
        'uk-section-{style}',
        'uk-section-overlap {@overlap}',
        'uk-position-z-index-negative {@sticky}',
        'uk-preserve-color {@style}' => $props['preserve_color'] || ($props['text_color'] && ($props['image'] || $props['video'])),
        'uk-{text_color}' => !$props['style'] || $props['image'] || $props['video'],
        'uk-inverse-{header_transparent_text_color}' => $props['header_transparent'] && ($props['text_color'] != $props['header_transparent_text_color'] || !($props['style'] || $props['image'] || $props['video'])),
        'uk-position-relative {@!sticky}' => ($props['image'] && ($props['media_overlay'] || $props['media_overlay_gradient'])) || ($props['video'] && !$this->iframeVideo($props['video'])), // uk-sticky already sets a position context
        'uk-cover-container {@video}' => $this->iframeVideo($props['video']),
    ],

    'style' => [
        'background-color: {media_background}; {@video}',
        'background-color: {background_color}; {@!media_background} {@!video} {@!style}'
    ],

    'tm-header-transparent-noplaceholder' => $props['header_transparent_noplaceholder'],

]);

if (!$props['style'] && $bgParallax = $this->parallaxOptions($props, 'background_', ['background'])) {
    $el->attr('uk-parallax', $bgParallax);
}

if ($props['sticky']) {
    $el->attr([
        'uk-sticky' => [
            'overflow-flip: true; end: 100%; {@sticky: cover}',
            'position: bottom; overflow-flip: true; start: -100%; end: 0; {@sticky: reveal}',
        ],
        'style' => ['z-index: calc(var(--tm-reveal, 0) - {0}); {@sticky: reveal}' => 1 + ($i ?? 0)],
    ]);
}

if ($props['animation']) {
    $el->attr('uk-scrollspy', [
        'target: [uk-scrollspy-class];',
        'cls: uk-animation-{animation};',
        'delay: {0};' => $props['animation_delay'] ?: 'false',
    ]);
}

// Section
$attrs_section = [
    'class' => [
        'uk-section',
        'uk-section-{!padding: |none}' ,
        'uk-padding-remove-vertical {@padding: none}',
        'uk-padding-remove-top {@!padding: none} {@padding_remove_top}',
        'uk-padding-remove-bottom {@!padding: none} {@padding_remove_bottom}',
        'uk-flex [uk-flex-{vertical_align} {@!title}] {@vertical_align}',

        // Height Viewport
        'uk-height-viewport {@height: viewport} {@!height_offset_top} {@height_viewport: |100}',
        'uk-height-viewport-{0} {@height: viewport} {@!height_offset_top} {@height_viewport: 200|300|400}' => (int) $props['height_viewport'] / 100,
    ],

    'style' => [
        // Height Viewport
        'min-height: {!height_viewport: |100|200|300|400}vh {@height: viewport} {@!height_offset_top}',
        'min-height: {0}px {@height: pixels}' => $props['height_viewport'] ?: 100,
    ],

    // Height Viewport
    'uk-height-viewport' => $props['height'] !== 'pixels' ? [
        'offset-top: true; {@height: viewport|section} {@height_offset_top}',
        'offset-bottom: {0}; {@height: viewport} {@height_offset_top}' => $props['height_viewport'] && $props['height_viewport'] < 100 ? 100 - (int) $props['height_viewport'] : false,
        'offset-bottom: {0}; {@height: section}' => $props['image'] ? '! +' : 'true',
        'expand: true; {@height: page}',
    ] : false,
];

// Image
$image = $props['image'] ? $this->el('div', $this->bgImage($props['image'], [
    'width' => $props['image_width'],
    'height' => $props['image_height'],
    'focal_point' => $props['media_focal_point'],
    'loading' => $props['image_loading'] ? 'eager' : null,
    'size' => $props['image_size'],
    'position' => $props['image_position'],
    'visibility' => $props['media_visibility'],
    'blend_mode' => $props['media_blend_mode'],
    'background' => $props['media_background'],
    'effect' => $props['image_effect'] != 'parallax' ? $props['image_effect'] : '',
])) : null;

if ($image && $props['image_effect'] == 'parallax' && $imageParallax = $this->parallaxOptions($props, 'image_', ['bgx', 'bgy'])) {
    $image->attr('uk-parallax', $imageParallax);
}

// Video
$video = $props['video'] ? include "{$__dir}/template-video.php" : null;

$overlay = ($props['media_overlay'] || $props['media_overlay_gradient']) && ($props['image'] || $props['video'])
    ? $this->el('div', [
        'class' => ['uk-position-cover'],
        'style' => [
            'background-color: {media_overlay};',
            // `background-clip` fixes sub-pixel issue
            'background-image: {media_overlay_gradient}; background-clip: padding-box;',
        ],
    ]) : null;

if (($props['media_overlay'] || $props['media_overlay_gradient']) && ($props['image'] || $props['video']) && $mediaOverlayParallax = $this->parallaxOptions($props, 'media_overlay_', ['opacity'])) {
    $overlay->attr('uk-parallax', $mediaOverlayParallax);
}

$container = $props['width'] || $props['video'] || $overlay
    ? $this->el('div', [
        'class' => [
            'uk-container {@width}',
            'uk-container-{width: xsmall|small|large|xlarge|expand}',
            'uk-padding-remove-horizontal {@padding_remove_horizontal} {@width} {@!width:expand}',
            'uk-container-expand-{width_expand} {@width} {@!width:expand}',

            // Make sure overlay and video is always below content
            'uk-position-relative [{@!width} uk-panel]' => $overlay || $props['video'],
        ],
    ]) : null;

$title = $this->el('div', [
    'class' => [
        'tm-section-title',
        'uk-position-{title_position} uk-position-medium',
        !in_array($props['title_position'], ['center-left', 'center-right']) ? 'uk-margin-remove-vertical' : 'uk-text-nowrap',
        'uk-visible@{title_breakpoint}',
    ],
]);
?>

<?= $el($props, !$image ? $attrs_section : []) ?>

    <?php if ($props['image']) : ?>
    <?= $image($props, $attrs_section) ?>
    <?php endif ?>

        <?php if ($video) : ?>
        <?= $video($props, '') ?>
        <?php endif ?>

        <?php if ($overlay) : ?>
        <?= $overlay($props, '') ?>
        <?php endif ?>

        <?php if ($props['title'] != '') : ?>
        <?= $this->el('div', ['class' => [
            'uk-position-relative',
            'uk-width-1-1 uk-flex uk-flex-{vertical_align}',
        ]])->render($props) ?>
        <?php endif ?>

            <?php if ($props['vertical_align']) : ?>
            <div class="uk-width-1-1">
            <?php endif ?>

                <?php if ($container) : ?>
                <?= $container($props) ?>
                <?php endif ?>

                    <?= $builder->render($children) ?>

                <?php if ($container) : ?>
                <?= $container->end() ?>
                <?php endif ?>

            <?php if ($props['vertical_align']) : ?>
            </div>
            <?php endif ?>

        <?php if ($props['title'] != '') : ?>
            <?= $title($props) ?>
                <div class="<?= $props['title_rotation'] == 'left' ? 'tm-rotate-180' : '' ?>"><?= $props['title'] ?></div>
            <?= $title->end() ?>
        </div>
        <?php endif ?>

    <?php if ($props['image']) : ?>
    </div>
    <?php endif ?>

<?= $el->end() ?>
