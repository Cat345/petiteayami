<?php

$el = $this->el($props['title_element'], [

    'class' => [
        'uk-{title_style}',
        'uk-text-stroke {@title_text_stroke}',
        'uk-heading-{title_decoration}',
        'uk-font-{title_font_family}',
        'uk-text-{title_color} {@!title_color: background}',
        'uk-margin-remove {@position: absolute}',
    ],

]);

// Image
$props['image'] = $this->render("{$__dir}/template-media", compact('props'));

if ($props['image']) {

    $props['content'] = $this->el('span', [

        'class' => [
            'uk-text-middle',
        ],

    ], $props['content'])->render($props);

}

// Container
$container = $props['title_color'] == 'background' || $props['title_decoration'] == 'line' ? $this->el('span', [

    'class' => [
        'uk-text-background {@title_color: background}',
    ],

]) : null;

// Link
$link = $props['link'] ? $this->el('a', [

    'class' => [
        'el-link',
        'uk-link-{0}' => $props['link_style'] ? 'heading' : 'reset',
    ],

    'href' => ['{link}'],
    'target' => ['_blank {@link_target}'],
    'download' => $props['link_download'],
    'rel' => [
        'nofollow {@link_rel_nofollow}',
        'noreferrer {@link_rel_noreferrer}'
    ],
    'uk-scroll' => str_contains((string) $props['link'], '#'),

]) : null;

?>

<?= $el($props, $attrs) ?>

    <?php if ($container) : ?>
    <?= $container($props) ?>
    <?php endif ?>

        <?php if ($link) : ?>
        <?= $link($props) ?>
        <?php endif ?>

        <?php if ($props['image']) : ?>
            <?php if ($props['image_align'] == 'left') : ?>
                <?= $props['image'] ?><?= $props['content'] ?>
            <?php elseif ($props['image_align'] == 'right') : ?>
                <?= $props['content'] ?><?= $props['image'] ?>
            <?php endif ?>
        <?php else : ?>
            <?= $props['content'] ?>
        <?php endif ?>

        <?php if ($link) : ?>
        <?= $link->end() ?>
        <?php endif ?>

    <?php if ($container) : ?>
    <?= $container->end() ?>
    <?php endif ?>

<?= $el->end() ?>
