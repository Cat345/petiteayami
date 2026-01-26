<?php

// Override default settings
$props['icon'] = $props['icon'] ?: $element['icon'];
$props['image_svg_color'] = $props['image_svg_color'] ?: $element['image_svg_color'];

// Item
$el = ($props['id'] || $props['class'] || $props['attributes']) ? $this->el($element['list_type'] == 'vertical' ? 'div' : 'span') : null;

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
$props['image'] = $this->render("{$__dir}/template-media", compact('props', 'element'));

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
    'download' => $props['link_download'],
    'rel' => [
        'nofollow {@link_rel_nofollow}',
        'noreferrer {@link_rel_noreferrer}'
    ],
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

<?php if ($el) : ?>
<?= $el($element, $attrs) ?>
<?php endif ?>

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

    <?php else :
        echo $content($element, $props['content'] ?: '');
    endif;

if ($el) :
echo $el->end();
endif;
