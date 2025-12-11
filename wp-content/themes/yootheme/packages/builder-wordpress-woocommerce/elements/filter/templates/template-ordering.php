<?php

// Ordering
$ordering = $this->el('span', [

    'class' => [
        'uk-{product_ordering_style: link|link-muted|link-text}',
        'uk-flex-inline uk-flex-center uk-flex-middle {@product_ordering_icon}',
    ],

]);

// Icon + Parent Icon
if ($props['product_ordering_icon'] || $props['filter_dropdown_parent_icon']) {

    $icon = $this->el('span');

    if ($props['product_ordering_icon']) {

        $icon->attr([
            'class' => [
                'uk-margin-small-right {@product_ordering_icon_align: left}',
                'uk-margin-small-left {@product_ordering_icon_align: right}',
            ],

            'uk-icon' => $props['product_ordering_icon'],
        ]);

    } else {

        $icon->attr([

            'uk-drop-parent-icon' => true,

        ]);

    }
}

?>

<?php if ($props['product_ordering_style']) : ?>
<div class="uk-display-block uk-link-toggle" uk-form-custom="target: .js-product-ordering">
    <div class="uk-panel">
<?php endif ?>

        <?php woocommerce_catalog_ordering() ?>

<?php if ($props['product_ordering_style']) : ?>
    </div>

    <?= $ordering($props) ?>

        <?php if ($props['product_ordering_icon'] && $props['product_ordering_icon_align'] == 'left') : ?>
        <?= $icon($props, '') ?>
        <?php endif ?>

        <span class="js-product-ordering"></span>

        <?php if (($props['product_ordering_icon'] && $props['product_ordering_icon_align'] == 'right') || (!$props['product_ordering_icon'] && $props['filter_dropdown_parent_icon'])) : ?>
        <?= $icon($props, '') ?>
        <?php endif ?>

    <?= $ordering->end() ?>

</div>
<?php endif ?>


