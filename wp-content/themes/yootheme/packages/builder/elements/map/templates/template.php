<?php

use YOOtheme\Metadata;
use function YOOtheme\app;

// Resets
if ($props['viewport_height'] == 'viewport' && $props['viewport_height_viewport'] > 100) {
    $props['viewport_height_offset_top'] = false;
}

/** @var Metadata $metadata */
$metadata = app(Metadata::class);

foreach ($props['metadata'] as $name => $attributes) {
    $metadata->set($name, $attributes);
}

$el = $this->el('div', [

    'class' => [
        'uk-position-relative',
        'uk-position-z-index',
        'uk-dark',
        'uk-inverse-{text_color}',

        // Height Viewport
        'uk-height-viewport {@viewport_height: viewport} {@!viewport_height_offset_top} {@viewport_height_viewport: |100}',
    ],

    'style' => [
        'width: {width}px; {@!width_breakpoint} {@!viewport_height}',
        'height: {height}px; {@!width} {@!viewport_height}',

        // Height Viewport
        'min-height: {!viewport_height_viewport: |100}vh; {@viewport_height: viewport} {@!viewport_height_offset_top}'
    ],

    // Height Viewport
    'uk-height-viewport' => ($props['viewport_height'] == 'viewport' && $props['viewport_height_offset_top']) || $props['viewport_height'] == 'section' ? [
        'offset-top: true; {@viewport_height_offset_top}',
        'offset-bottom: {0}; {@viewport_height: viewport}' => $props['viewport_height_viewport'] && $props['viewport_height_viewport'] < 100 ? 100 - (int) $props['viewport_height_viewport'] : false,
        'offset-bottom: !:is(.uk-section-default,.uk-section-muted,.uk-section-primary,.uk-section-secondary) +; {@viewport_height: section}',
    ] : false,

    'uk-responsive' => !$props['viewport_height'] ? [
        'width: {width}; height: {height}',
    ] : false,

    'uk-map' => true,

    'data-map-type' => $options['library']
]);

$script = $this->el('script', ['type' => 'application/json'], json_encode($options));

// Width and Height
$props['width'] = trim($props['width'] ?: '', 'px');
$props['height'] = trim($props['height'] ?: '300', 'px');

?>

<?= $el($props, $attrs) ?>
    <?= $script() ?>
    <?php foreach ($children as $child) : ?>
        <?php if (!empty($child->props['show'])) : ?>
        <template>
            <?= $builder->render($child, ['element' => $props]) ?>
        </template>
        <?php endif ?>
    <?php endforeach ?>
<?= $el->end() ?>
