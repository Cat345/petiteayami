<?php

// Resets
if ($props['icon'] && !($props['image'] || $props['video'])) { $props['panel_image_no_padding'] = ''; }
if ($props['panel_style'] || !($props['image'] || $props['video'])) {
    $props['image_box_decoration'] = '';
    $props['image_transition_border'] = '';
}
if ($props['panel_link']) {
    $props['title_link'] = '';
    $props['image_link'] = '';
}
if (!$props['height_expand'] && !(($props['image'] || $props['video']) && in_array($props['image_align'], ['left', 'right']))) {
    $props['panel_expand'] = '';
}
if (!($props['panel_style'] || (!$props['panel_style'] && ($props['image'] || $props['video']) && $props['image_align'] != 'between'))) {
    $props['panel_padding'] = '';
}

// New logic shortcuts
$props['flex_column_align'] = '';
$props['flex_column_align_fallback'] = '';
if ($props['panel_expand']) {
    $horizontal = ['left', 'center', 'right'];
    $vertical = ['top', 'middle', 'bottom'];
    $props['flex_column_align'] = str_replace($horizontal, $vertical, $props['text_align'] ?? '');
    $props['flex_column_align_fallback'] = str_replace($horizontal, $vertical, $props['text_align_fallback'] ?? '');

    // `uk-flex-top` prevents child inline elements from taking the whole width, needed for floating shadow + hover image
    if (!$props['panel_style']) {
        if ($props['text_align'] && $props['text_align_breakpoint'] && !$props['text_align_fallback']) {
            $props['flex_column_align_fallback'] = 'top';
        } elseif (!$props['text_align']) {
            $props['flex_column_align'] = 'top';
        }
    }
}

// Image
$props['image'] = $this->render("{$__dir}/template-media", compact('props'));

// Panel/Card/Tile
$el = $this->el($props['html_element'] ?: 'div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 {@height_expand}',
        'uk-flex uk-flex-column {@height_expand} {@link} {@panel_link}',
    ],

]);

// Link Container
$link_container = $props['link'] && $props['panel_link'] ? $this->el('a', [

    'class' => [
        'uk-flex-1 {@height_expand}',
        'uk-display-block {@!height_expand}',
    ],

]) : null;


($link_container ?: $el)->attr([

    'class' => [
        'uk-panel [uk-{panel_style: tile-.*}] {@panel_style: |tile-.*}',
        'uk-card uk-{panel_style: card-.*} [uk-card-{!panel_padding: |default}]',
        'uk-tile-hover {@panel_style: tile-.*} {@panel_link} {@link} {@panel_link_hover}',
        'uk-card-hover {@panel_style: card-(default|primary|secondary)} {@panel_link} {@link} {@panel_link_hover}',
        'uk-transition-toggle {@image} {@panel_link}' => $props['image_transition'] || $props['image_transition_border'] || $props['hover_image'] || $props['hover_video'],
        'uk-flex uk-flex-column {@panel_expand}',
        // Image not wrapped in card media container or grid
        'uk-flex-{flex_column_align}[@{text_align_breakpoint} [uk-flex-{flex_column_align_fallback}]] {@panel_expand}' => !($props['panel_style'] && str_starts_with($props['panel_style'], 'card-') && $props['image'] && $props['panel_image_no_padding'] && $props['image_align'] != 'between') && !in_array($props['image_align'], ['left', 'right']),
    ],

]);

// Padding
$content_container = '';
if ($props['panel_padding'] && $props['image'] && (!$props['panel_style'] || $props['panel_image_no_padding']) && $props['image_align'] != 'between') {

    $content_container = $this->el('div', [
        'class' => [
            'uk-flex-1 uk-flex uk-flex-column {@panel_expand: content|both}',
            // Image not wrapped in card media container or grid
            'uk-width-1-1 {@flex_column_align} {@panel_expand}' => !($props['panel_style'] && str_starts_with($props['panel_style'], 'card-') && $props['image'] && $props['panel_image_no_padding'] && $props['image_align'] != 'between') && !in_array($props['image_align'], ['left', 'right']),
        ],
    ]);

}

($content_container ?: $link_container ?: $el)->attr('class', [
    'uk-padding[-{!panel_padding: default}] {@panel_style: |tile-.*} {@panel_padding}',
    'uk-card-body {@panel_style: card-.*} {@panel_padding}',
]);

// Image align
$grid = '';
$cell_image = '';
$cell_content = '';
if ($props['image'] && in_array($props['image_align'], ['left', 'right'])) {

    $grid = $this->el('div', [

        'class' => [
            $props['panel_style'] && $props['panel_image_no_padding']
                ? 'uk-grid-collapse'
                : ($props['image_grid_column_gap'] == $props['image_grid_row_gap']
                    ? 'uk-grid-{image_grid_column_gap}'
                    : '[uk-grid-column-{image_grid_column_gap}] [uk-grid-row-{image_grid_row_gap}]'),
            // Center vertically
            'uk-flex-middle {@image_vertical_align} {@!panel_expand}',
            // Expand
            'uk-flex-1 {@panel_expand}',
            'uk-flex-column {@panel_expand} uk-flex-row@{image_grid_breakpoint}',
        ],

        'uk-grid' => true,
    ]);

    $cell_image = $this->el('div', [

        'class' => [
            'uk-width-{image_grid_width}[@{image_grid_breakpoint}]',
            // Order
            'uk-flex-last[@{image_grid_breakpoint}] {@image_align: right}',
            // Center vertically and keep text align
            'uk-flex uk-flex-middle[@{image_grid_breakpoint}] uk-flex-{text_align} {@image_vertical_align} {@panel_expand: content}',
            'uk-flex-{text_align}[@{text_align_breakpoint} [uk-flex-{text_align_fallback}]] {@panel_expand: content}',
            // Expand, also when stacking
            'uk-flex uk-flex-column {@panel_expand: image|both} [uk-flex-1 uk-flex-initial@{image_grid_breakpoint}]',
            'uk-flex-{flex_column_align}[@{text_align_breakpoint} [uk-flex-{flex_column_align_fallback}]] {@panel_expand: image|both}',
        ],

    ]);

    $cell_content = $this->el('div', [

        'class' => [
            'uk-width-expand',
            // Center vertically
            'uk-flex uk-flex-column uk-flex-center[@{image_grid_breakpoint}] {@image_vertical_align} {@panel_expand: image}',
            // Expand, also when stacking but only for `content`
            'uk-flex uk-flex-column {@panel_expand: content|both}',
            'uk-flex-1 {@image_grid_breakpoint} {@panel_expand: content}',
            'uk-flex-1[@{image_grid_breakpoint} uk-flex-none] {@panel_expand: both}',
            'uk-flex-none uk-flex-1@{image_grid_breakpoint} {@panel_expand: image}',
        ],

    ]);

}

($content_container ?: $cell_content ?: $link_container ?: $el)->attr('class', [
    'uk-margin-remove-first-child',
]);

// Link
$link = include "{$__dir}/template-link.php";

// Card Media (Needs to be after link)
if ($props['panel_style'] && str_starts_with($props['panel_style'], 'card-') && $props['image'] && $props['panel_image_no_padding'] && $props['image_align'] != 'between') {

    $props['image'] = $this->el('div', [
        'class' => [
            'uk-card-media-{image_align}',
            'uk-flex-1 uk-flex uk-flex-column {@panel_expand: image|both}',
            'uk-flex-{flex_column_align}[@{text_align_breakpoint} [uk-flex-{flex_column_align_fallback}]] {@panel_expand: image|both} {@!image_align: left|right}',
        ],

        'uk-toggle' => [
            'cls: uk-card-media-{image_align} uk-card-media-top; mode: media; media: @{image_grid_breakpoint} {@image_align: left|right}',
        ]
    ], $props['image'])->render($props);

}

?>

<?= $el($props, $attrs) ?>

    <?php if ($link_container) : ?>
    <?= $link_container($props) ?>
    <?php endif ?>

        <?php if ($grid) : ?>
        <?= $grid($props) ?>
        <?php endif ?>

            <?php if ($cell_image) : ?>
            <?= $cell_image($props) ?>
            <?php endif ?>

                <?php if (in_array($props['image_align'], ['left', 'right'])) : ?>
                <?= $props['image'] ?>
                <?php endif ?>

            <?php if ($cell_image) : ?>
            <?= $cell_image->end() ?>
            <?php endif ?>

            <?php if ($cell_content) : ?>
            <?= $cell_content($props) ?>
            <?php endif ?>

                <?php if ($props['image_align'] == 'top') : ?>
                <?= $props['image'] ?>
                <?php endif ?>

                <?php if ($content_container) : ?>
                <?= $content_container($props) ?>
                <?php endif ?>

                    <?= $this->render("{$__dir}/template-content", compact('props', 'link')) ?>

                <?php if ($content_container) : ?>
                <?= $content_container->end() ?>
                <?php endif ?>

                <?php if ($props['image_align'] == 'bottom') : ?>
                <?= $props['image'] ?>
                <?php endif ?>

            <?php if ($cell_content) : ?>
            <?= $cell_content->end() ?>
            <?php endif ?>

        <?php if ($grid) : ?>
        <?= $grid->end() ?>
        <?php endif ?>

    <?php if ($link_container) : ?>
    <?= $link_container->end() ?>
    <?php endif ?>

<?= $el->end() ?>
