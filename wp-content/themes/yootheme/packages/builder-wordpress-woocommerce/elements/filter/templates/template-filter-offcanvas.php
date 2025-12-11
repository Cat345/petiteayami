<?php

// Offcanvas
$offcanvas = $this->el('div', [

    'id' => 'tm-element-woo-filter-offcanvas',

    'uk-offcanvas' => [
        'container: true;',
        'flip: {filter_offcanvas_flip};',
        'overlay: {filter_offcanvas_overlay};',
    ],

]);

$offcanvas_content = $this->el('div', [

    'class' => [
        'uk-offcanvas-bar',
    ],

]);

// Filter Title
$filter_title = $this->el('h3', [

    'class' => [
        'uk-{filter_title_style}',
    ],

]);

?>

<?= $this->render("{$__dir}/template-filter-toggle", compact('props', 'filters')) ?>

<?= $offcanvas($props) ?>
    <?= $offcanvas_content($props) ?>

        <button class="uk-offcanvas-close uk-close-large" type="button" uk-close uk-toggle="cls: uk-close-large; mode: media; media: @s"></button>

        <?php if (array_filter($filters['attributes'])) : ?>
            <?php foreach ($filters['attributes'] as $name => $filter) : ?>
                <?= $filter_title($props, ucwords($name)) ?>
                <?= $filter ?>
            <?php endforeach ?>
        <?php endif ?>

        <?php if ($filters['price']) : ?>
            <?= $filter_title($props, __('Price', 'woocommerce')) ?>
            <?= $filters['price'] ?>
        <?php endif ?>

        <?php if ($filters['rating']) : ?>
            <?= $filter_title($props, __('Rating', 'woocommerce')) ?>
            <?= $filters['rating'] ?>
        <?php endif ?>

        <?php if ($active_filters && $props['filter_active_display']) : ?>
            <?= $filter_title($props, __('Active Filters', 'woocommerce')) ?>
            <?= $active_filters ?>
        <?php endif ?>

    <?= $offcanvas_content->end() ?>
<?= $offcanvas->end() ?>
