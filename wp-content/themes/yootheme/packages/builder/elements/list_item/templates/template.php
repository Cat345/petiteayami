<?php

// Image Align
$grid = $this->el('div', [

    'class' => [
        'uk-grid uk-grid-small uk-child-width-expand uk-flex-nowrap',
        'uk-flex-middle {@image_vertical_align}',
    ],

]);

$cell_image = $this->el('div', [

    'class' => [
        'uk-width-auto',
        'uk-flex-last {@image_align: right}',
    ],

]);

// Image
if ($props['image']) {

    $image = $this->el('image', [

        'class' => [
            'el-image',
            'uk-border-{image_border}',
            'uk-text-{image_svg_color} {@image_svg_inline}' => $this->isImage($props['image']) == 'svg',
        ],

        'src' => $props['image'],
        'alt' => $props['image_alt'],
        'loading' => $element['image_loading'] ? false : null,
        'width' => $element['image_width'],
        'height' => $element['image_height'],
        'focal_point' => $props['image_focal_point'],
        'uk-svg' => $element['image_svg_inline'],
        'thumbnail' => true,
    ]);

    $props['image'] = $image($element);

} elseif ($props['icon'] || $element['icon']) {

    $icon = $this->el('span', [

        'class' => [
            'el-image',
            'uk-text-{icon_color}',
        ],

        'uk-icon' => [
            'icon: {icon};',
            'width: {icon_width};',
            'height: {icon_width};',
        ],

    ]);

    $props['image'] = $icon(array_merge($element, array_filter($props)), '');
}

// Content
$content = $this->el($element['list_type'] == 'vertical' ? 'div' : 'span', [

    'class' => [
        'el-content',
        'uk-panel {@list_type: vertical}',
        'uk-{content_style}',
    ],

]);

// Horizontal List: Image is content
if ($props['image'] && $element['list_type'] == 'horizontal') {

    $text = $this->el('span', [

        'class' => [
            'uk-text-middle uk-margin-remove-last-child',
        ],

    ]);

    $props['content'] = $text($element, $props['content'] ?? '');

    if ($element['image_align'] == 'left') {
        $props['content'] = "{$props['image']} {$props['content']}";
    } else {
        $props['content'] = "{$props['content']} {$props['image']}";
    }

    $props['image'] = '';

}

// Link
$link = $props['link'] ? $this->el('a', [
    'href' => $props['link'],
    'target' => ['_blank {@link_target}'],
    'uk-scroll' => str_contains((string) $props['link'], '#'),
]) : null;

if ($link && $props['image']) {

    $link->attr([

        'class' => [
            'uk-link-toggle',
        ],

    ]);

    $props['content'] = $this->striptags($props['content']);

    if ($element['link_style'] != 'reset') {

        $props['content'] = $this->el('span', [

            'class' => [
                'uk-link-{link_style: muted|text|heading}',
                'uk-link {!link_style}',
                'uk-margin-remove-last-child',
            ],

        ], $props['content'])->render($element);

        $cell_image->attr([

            'class' => [
                'uk-link-{link_style: muted|text|heading}',
                'uk-link {!link_style}',
            ],

        ]);

    }

}

if ($link && !$props['image']) {

    $props['content'] = $link($props, ['class' => [

        'el-link',
        'uk-link-{0}' => $element['link_style'],
        'uk-margin-remove-last-child',

    ]], $this->striptags($props['content']));

}

// No white space for horizontal lists
?>
<?php if ($props['image']) : ?>

    <?php if ($props['link']) : ?>
    <?= $link($props) ?>
    <?php endif ?>

        <?= $grid($element) ?>
            <?= $cell_image($element, $props['image']) ?>
            <div>
                <?= $content($element, $props['content'] ?: '') ?>
            </div>
        <?= $grid->end() ?>

    <?php if ($props['link']) : ?>
    <?= $link->end() ?>
    <?php endif ?>

<?php else : // No white space for horizontal lists ?>
<?= $content($element, $props['content'] ?: '') ?>
<?php endif ?>
