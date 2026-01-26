<?php

$el = $this->el('div');

// Grid
$grid = $this->el('div', [

    'class' => [
        'uk-child-width-auto',
        $props['grid_column_gap'] == $props['grid_row_gap'] ? 'uk-grid-{grid_column_gap}' : '[uk-grid-column-{grid_column_gap}] [uk-grid-row-{grid_row_gap}]',
        'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]]',

        'uk-flex-middle {@!show_label}',
        'uk-{countdown_style} {@!show_label}',
        'uk-font-{countdown_font_family} {@!show_label}',
        'uk-text-{countdown_color} {@!show_label}',

        'uk-text-center {@show_label}',
    ],

    'uk-countdown' => $this->expr([
        'date: {date};',
        'reload: {reload};'
    ], $props),

    'uk-grid' => true,
]);

// Number
$number = $this->el('div', [

    'class' => [
        'uk-countdown-number',
        'uk-{countdown_style} {@show_label}',
        'uk-font-{countdown_font_family} {@show_label}',
        'uk-text-{countdown_color} {@show_label}',
    ],

]);

// Separator
$separator = $this->el('div', [

    'class' => [
        'uk-countdown-separator',
    ],

]);

$separator = $this->el('div', [

    'class' => [
        'uk-countdown-separator',
    ],

]);

$separator_container = $props['show_label'] ? $this->el('div', [

    'class' => [
        'uk-{countdown_style}',
        'uk-font-{countdown_font_family}',
        'uk-text-{countdown_color}',
    ],

]) : null;

// Label
$label = $this->el('div', [

    'class' => [
        'uk-countdown-label',
        'uk-margin[-{label_margin}]',
        'uk-{label_style}',
        'uk-font-{label_font_family}',
        'uk-text-{label_color}',
    ],

]);

?>

<?= $el($props, $attrs) ?>
    <?= $grid($props) ?>

        <?php foreach (['days', 'hours', 'minutes', 'seconds'] as $unit) : ?>

        <div>

            <?= $number($props, ['class' => ["uk-countdown-{$unit}"]], '') ?>

            <?php if ($props['show_label']) : ?>
                <?= $label($props, $props["label_{$unit}"] ?: ucfirst($unit)) ?>
            <?php endif ?>

        </div>

        <?php if ($props['show_separator'] && $unit !== 'seconds') : ?>

            <?php if ($separator_container) : ?>
            <?= $separator_container($props) ?>
            <?php endif ?>

                <?= $separator($props, ':') ?>

            <?php if ($separator_container) : ?>
            <?= $separator_container->end() ?>
            <?php endif ?>

        <?php endif ?>

        <?php endforeach ?>

    <?= $grid->end() ?>
<?= $el->end() ?>
