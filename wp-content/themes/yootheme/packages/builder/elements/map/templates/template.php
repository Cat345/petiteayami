<?php

// Resets
if (!($props['width'] && $props['height']) || $props['height_viewport'] ) {
    $props['width_breakpoint'] = '';
}

$props['width'] = trim($props['width'] ?: '', 'px');
$props['height'] = trim($props['height'] ?: '300', 'px');

if ($props['height_viewport'] == 'viewport' && $props['height_viewport_height'] > 100) {
    $props['height_viewport_offset'] = false;
}

$el = $this->el('div');

$map = $this->el('div', [

    'class' => [
        'uk-preserve-width', // Fix images
        'uk-position-relative uk-position-z-index',
        'uk-dark',

        // Height Viewport
        'uk-height-viewport {@height_viewport: viewport} {@!height_viewport_offset} {@height_viewport_height: |100}',

        'uk-responsive-width {@width} {@height} {@!height_viewport}',
    ],

    'style' => [
        'aspect-ratio: {width} / {height}; {@!height_viewport}',

        'width: {width}px; {@!width_breakpoint}',
        'height: {height}px; {@!width} {@!height_viewport} {@!width_breakpoint}',

        'min-width: 100%; {@width_breakpoint}',
        'max-height: {height}px; {@width_breakpoint}',

        // Height Viewport
        'min-height: {!height_viewport_height: |100}vh; {@height_viewport: viewport} {@!height_viewport_offset}'
    ],

    // Height Viewport
    'uk-height-viewport' => ($props['height_viewport'] == 'viewport' && $props['height_viewport_offset']) || $props['height_viewport'] == 'section' ? [
        'offset-top: true; {@height_viewport_offset}',
        'offset-bottom: {0}; {@height_viewport: viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
        'offset-bottom: !:is(.uk-section-default,.uk-section-muted,.uk-section-primary,.uk-section-secondary) +; {@height_viewport: section}',
    ] : false,

    'uk-map' => true,

    'hidden' => $props['consent']
]);

$script = $this->el('script', ['type' => 'application/json'], json_encode($options));

// Placeholder (Consent)
$placeholder_container = '';
$placeholder_image = '';

// Consent
$consent = '';
$consent_button = '';

if ($props['consent']) {

    $placeholder_container = $this->el('div', [

        'class' => [
            'uk-{text_color}',
            'uk-inline {@width} {@!width_breakpoint}',
            'uk-position-relative {@!width}',
        ],

    ]);

    if ($props['consent_placeholder_image']) {
        $placeholder_image = include "{$__dir}/template-image.php";

        $placeholder_image->attr([

            'class' => [
                'js-consent-overlay',
                'uk-display-block uk-width-1-1',
            ],

            'style' => [
                'aspect-ratio: auto;',

                'aspect-ratio: {width} / {height}; {@!height_viewport}',

                'width: {width}px; {@!width_breakpoint}',
                'height: {height}px; {@!width} {@!height_viewport} {@!width_breakpoint}',

                'min-width: 100%; {@width_breakpoint}',
                'max-height: {height}px; {@width_breakpoint}',

                // Height Viewport
                'height: 100vh; {@!height_viewport_height} {@height_viewport: viewport} {@!height_viewport_offset}',
                'height: {height_viewport_height}vh; {@height_viewport: viewport} {@!height_viewport_offset}',

                'visibility: hidden',
            ],

            // Height Viewport
            'uk-height-viewport' => ($props['height_viewport'] == 'viewport' && $props['height_viewport_offset']) || $props['height_viewport'] == 'section' ? [
                'property: height;',
                'offset-top: true; {@height_viewport_offset}',
                'offset-bottom: {0}; {@height_viewport: viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
                'offset-bottom: !:is(.uk-section-default,.uk-section-muted,.uk-section-primary,.uk-section-secondary) +; {@height_viewport: section}',
            ] : false,

        ]);
    }

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
        'thumbnail' => true,

    ]);

}

($placeholder_container ?: $map)->attr('class', [
    'uk-inverse-{text_color}'
]);

?>

<?= $el($props, $attrs) ?>

    <?php if ($placeholder_container) : ?>
    <?= $placeholder_container($props) ?>
    <?php endif ?>

        <?= $map($props) ?>

            <?= $script() ?>
            <?php foreach ($children as $child) : ?>
                <?php if (!empty($child->props['show'])) : ?>
                <template>
                    <?= $builder->render($child, ['element' => $props]) ?>
                </template>
                <?php endif ?>
            <?php endforeach ?>

        <?= $map->end() ?>

        <?php if ($placeholder_image) : ?>
        <?= $placeholder_image($props, '') ?>
        <?php endif ?>

        <?php if ($consent) : ?>
        <?= $consent($props) ?>

            <?php if (!$props['consent_card_size']) : ?>
            <?= $consent_card($props, ['class' => ['uk-visible@m']]) ?>
                <?= $consent_icon($props, '') ?>
                <p class="uk-margin-small-top"><?= $this->trans($props['consent_content']) ?></p>
                <?= $consent_button($props, $props['consent_accept_button'] ?? '') ?>
            <?= $consent_card->end() ?>
            <?php endif ?>

            <?= $consent_card($props, ['class' => ['uk-card-small', !$props['consent_card_size'] ? 'uk-hidden@m' : '']]) ?>
                <?= $consent_icon($props, ['width' => '40'], '') ?>
                <p class="uk-text-small uk-margin-small"><?= $props['consent_content'] ?? '' ?></p>
                <?= $consent_button($props, ['class' => ['uk-button-small']], $props['consent_accept_button'] ?? '') ?>
            <?= $consent_card->end() ?>

        <?= $consent->end() ?>
        <?php endif ?>

    <?php if ($placeholder_container) : ?>
    <?= $placeholder_container->end() ?>
    <?php endif ?>

<?= $el->end() ?>
