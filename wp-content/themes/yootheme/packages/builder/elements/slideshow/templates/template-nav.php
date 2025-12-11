<?php

$nav = $this->el('ul', [

    'class' => [
        'el-nav uk-slideshow-nav',
        'uk-{nav} [uk-flex-nowrap {@nav: thumbnav} {@thumbnav_nowrap}]',

        // Alignment
        'uk-flex-{nav_align} {@nav_below}',

        // Vertical
        'uk-{nav}-vertical {@nav_vertical} {@!nav_below}',

        // Wrapping
        'uk-flex-right {@!nav_vertical} {@!nav_below} {@nav_position: .*-right}',
        'uk-flex-center {@!nav_vertical} {@!nav_below} {@nav_position: bottom-center}',
    ],

    'uk-margin' => !$props['nav_vertical'] && ($props['nav'] !== 'thumbnav' || !$props['thumbnav_nowrap']),
]);

$nav_container = $this->el('div', [

    'class' => [
        // Margin
        'uk-margin[-{nav_margin}]-top {@nav_below}',

        // Position
        'uk-position-{nav_position} {@!nav_below}',

        // Margin
        'uk-position-{nav_position_margin} {@!nav_below}',

        // Hover
        'uk-hidden-hover uk-hidden-touch {@nav_hover}',

        // Breakpoint
        'uk-visible@{nav_breakpoint}',

        // Initial text color
        'uk-{text_color} {@!nav_below}',
    ],

    'uk-inverse' => true,

]);

?>

<?= $nav_container($props) ?>

    <?= $nav($props) ?>
        <?php foreach ($children as $i => $child) :

            // Image
            $image = $this->el('image', [
                'class' => [
                    'uk-text-{thumbnav_svg_color}' => $props['thumbnav_svg_inline'] && $props['thumbnav_svg_color'] && $this->isImage($child->props['thumbnail'] ?: $child->props['image']) == 'svg',
                ],
                'src' => $child->props['thumbnail'] ?: $child->props['image'],
                'alt' => $child->props['image_alt'],
                'loading' => $props['image_loading'] ? false : null,
                'width' => $props['thumbnav_width'],
                'height' => $props['thumbnav_height'],
                'focal_point' => $child->props['thumbnail'] ? $child->props['thumbnail_focal_point'] : $child->props['image_focal_point'],
                'uk-svg' => (bool) $props['thumbnav_svg_inline'],
                'thumbnail' => true,
            ]);

            $thumbnail = $image->attrs['src'] && $props['nav'] == 'thumbnav' ? $image($props) : '';
        ?>
        <li uk-slideshow-item="<?= $i ?>">
            <a href="#"><?= $thumbnail ?: $child->props['title'] ?></a>
        </li>
        <?php endforeach ?>
    <?= $nav->end() ?>

<?= $nav_container->end() ?>
