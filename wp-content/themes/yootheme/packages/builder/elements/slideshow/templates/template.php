<?php

// Resets
if ($props['overlay_link']) { $props['title_link'] = ''; }
if ($props['height_viewport']) {
    $props['height_expand'] = '';
}
if ($props['height_viewport'] == 'viewport') {
    if ($props['height_viewport_height'] > 100) {
        $props['height_viewport_offset'] = false;
    } elseif (!$props['height_viewport_height']) {
        $props['height_viewport_height'] = 100;
    }
}
if ($props['slideshow_parallax']) {
    $props['slidenav'] = '';
}

// New logic shortcuts
$props['content_expand'] = $props['title_margin_auto'] ?: $props['meta_margin_auto'] ?: $props['content_margin_auto'];

$el = $this->el('div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

    'uk-slideshow' => $this->expr([
        'ratio: {0};' => $props['height_viewport'] ? 'false' : $props['slideshow_ratio'],
        'minHeight: {slideshow_min_height}; {@!height_viewport} {@!height_expand}',
        'maxHeight: {slideshow_max_height}; {@!height_viewport} {@!height_expand}',
        'animation: {slideshow_animation};',
        'velocity: {slideshow_velocity}; {@!slideshow_parallax}',
        'autoplay: {slideshow_autoplay}; [pauseOnHover: false; {!slideshow_autoplay_pause}; ] [autoplayInterval: {slideshow_autoplay_interval}000;] {@!slideshow_parallax}',
        // Parallax
        'parallax: true; {@slideshow_parallax}',
        'parallax-easing: {slideshow_parallax_easing}; {@slideshow_parallax}',
        'parallax-target: {slideshow_parallax_target}; {@slideshow_parallax}',
        'parallax-start: {slideshow_parallax_start}; {@slideshow_parallax}',
        'parallax-end: {slideshow_parallax_end}; {@slideshow_parallax}',
    ], $props) ?: true,

]);

// Container
$container = $this->el('div', [

    'class' => [
        'uk-position-relative',
        'uk-visible-toggle' => ($props['slidenav'] && $props['slidenav_hover']) || ($props['nav'] && $props['nav_hover']),
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

    'tabindex' => ($props['slidenav'] && $props['slidenav_hover']) || ($props['nav'] && $props['nav_hover']) ? '-1' : null,

]);

// Box decoration
$decoration = $props['slideshow_box_decoration'] ? $this->el('div', [

    'class' => [
        'uk-box-shadow-bottom [uk-display-block {@!height_expand}] {@slideshow_box_decoration: shadow}',
        'tm-mask-default {@slideshow_box_decoration: mask}',
        'tm-box-decoration-{slideshow_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@slideshow_box_decoration_inverse} {@slideshow_box_decoration: default|primary|secondary}',
        'uk-flex-1 uk-flex uk-flex-column {@height_expand}',
    ],

]) : null;

// Items
$items = $this->el($props['link'] ? 'a' : 'div', [

    'class' => [
        'uk-slideshow-items',
        'uk-border-{slideshow_border} {@!slideshow_box_decoration}',
        'uk-box-shadow-{slideshow_box_shadow}',
        'uk-flex-1 {@height_expand}',
    ],

    'style' => [
        'min-height: max({0}px, {height_viewport_height}vh); {@height_viewport: viewport} {@!height_viewport_offset}' => [$props['slideshow_min_height'] ?: '0'],
    ],

    'uk-height-viewport' => ($props['height_viewport'] == 'viewport' && $props['height_viewport_offset']) || $props['height_viewport'] == 'section' ? [
        'offset-top: true; {@height_viewport_offset}',
        'min: {slideshow_min_height};',
        'offset-bottom: {0}; {@height_viewport: viewport}' => $props['height_viewport_height'] && $props['height_viewport_height'] < 100 ? 100 - (int) $props['height_viewport_height'] : false,
        'offset-bottom: !:is(.uk-section-default,.uk-section-muted,.uk-section-primary,.uk-section-secondary) +; {@height_viewport: section}',
    ] : false,

]);

if ($props['link']) {

    $items->attr([

        'href' => ['{link}'],
        'aria-label' => ['{link_aria_label}'],
        'target' => ['_blank {@link_target}'],
        'uk-scroll' => str_contains((string) $props['link'], '#'),
        'class' => ['uk-display-block'],

    ]);

}

?>

<?= $el($props, $attrs) ?>

    <?= $container($props) ?>

        <?php if ($decoration) : ?>
        <?= $decoration($props) ?>
        <?php endif ?>

            <?= $items($props) ?>
                <?php foreach ($children as $i => $child) :

                    $item = $this->el('div', [

                        'class' => [
                            'el-item',
                            'uk-inverse-{0}' => $child->props['text_color'] ?: $props['text_color'],
                        ],

                        'style' => [
                            'background-color: {0};' => $child->props['media_background'] ?: $props['media_background'],
                        ],

                    ]);

                    ?>

                    <?= $item($props, $builder->render($child, ['i' => $i, 'element' => $props])) ?>

                <?php endforeach ?>
            <?= $items->end() ?>

        <?php if ($decoration) : ?>
        <?= $decoration->end() ?>
        <?php endif ?>

        <?php if ($props['slidenav']) : ?>
        <?= $this->render("{$__dir}/template-slidenav") ?>
        <?php endif ?>

        <?php if ($props['nav'] && !$props['nav_below']) : ?>
        <?= $this->render("{$__dir}/template-nav") ?>
        <?php endif ?>

    <?= $container->end() ?>

    <?php if ($props['nav'] && $props['nav_below']) : ?>
    <?= $this->render("{$__dir}/template-nav") ?>
    <?php endif ?>

<?= $el->end() ?>
