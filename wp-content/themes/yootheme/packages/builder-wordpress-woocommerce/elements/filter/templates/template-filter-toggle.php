<?php

// Icon + Parent Icon
if ($props['filter_toggle_icon'] || ($props['filter_dropdown_parent_icon'] && ($props['filter'] != 'offcanvas' && !($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'offcanvas')))) {

    $icon = $this->el('span');

    if ($props['filter_toggle_icon']) {

        $icon->attr([
            'class' => [
                'uk-margin-small-right {@filter_toggle_icon_align: left}',
                'uk-margin-small-left {@filter_toggle_icon_align: right}',
            ],

            'uk-icon' => $props['filter_toggle_icon'],
        ]);

    } else {

        $icon->attr([

            'uk-drop-parent-icon' => true,

        ]);

    }
}

// Link
$link = $this->el('a', [

    'href' => $props['filter'] == 'offcanvas' || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'offcanvas') ? '#tm-element-woo-filter-offcanvas' : true,

    'class' => [
        'uk-{filter_toggle_style: link-(muted|text)}',
        'uk-button uk-button-{!filter_toggle_style: |link-muted|link-text}',
        'uk-flex-inline uk-flex-center uk-flex-middle {@filter_toggle_icon}',
    ],

    'uk-toggle' => $props['filter'] == 'offcanvas' || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'offcanvas'),

]);

// Filter Count
if ($count = array_sum(($filters['filter_count']['attr'] ?? [])) + ($filters['filter_count']['price'] ?? 0) + ($filters['filter_count']['rating'] ?? 0)) {
    if ($props['filter_active_count'] == 'parenthesis') {
        $count = "({$count})";
    } elseif ($props['filter_active_count'] == 'superscript') {
        $count = "<sup>{$count}</sup>";
    }
}

?>

<?= $link($props) ?>

    <?php if ($props['filter_toggle_icon'] && $props['filter_toggle_icon_align'] == 'left') : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

    <?= __('Filter', 'woocommerce') ?>

    <?= $count ?: '' ?>

    <?php if (($props['filter_toggle_icon'] && $props['filter_toggle_icon_align'] == 'right') || (!$props['filter_toggle_icon'] && ($props['filter_dropdown_parent_icon'] && ($props['filter'] != 'offcanvas' && !($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'offcanvas'))))) : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

<?= $link->end() ?>
