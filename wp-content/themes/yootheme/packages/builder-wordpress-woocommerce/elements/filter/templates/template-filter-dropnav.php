<?php

// Parent Icon
$icon = $props['filter_dropdown_parent_icon'] ? $this->el('span', [

    'uk-drop-parent-icon' => true,

]) : null;

// Dropnav
$dropnav = $this->el('div', [

    'class' => [
        'uk-panel',
        'tm-element-filter-columns {@filter_attribute_columns}',
    ],

    'uk-dropnav' => [
        'mode: click; {@filter_dropnav_dropdown_click}',
        'align: {filter_dropnav_dropdown_align};',
        'boundary: !.js-filter;',
        'target-x: !.js-filter; {@filter_dropnav_dropdown_target}',
    ],

]);

if ($props['filter_dropdown_dropbar']) {

    $dropnav->attr([

        'uk-dropnav' => [
            'dropbar: true;',
            'dropbar-anchor: !.js-filter > :last-child:not(.uk-dropbar), !.js-filter:has(> .uk-dropbar) > :nth-last-child(2);',
            'target-y: !.js-filter > :first-child;',
        ],

    ]);

}

// Filter Nav
$filter_nav = $this->el('ul', [

    'class' => [
        'uk-subnav {@filter_dropnav_style: |divider|pill} [uk-subnav-{filter_dropnav_style: divider|pill}]',
        'uk-tab {@filter_dropnav_style: tab}',
        'uk-grid-small {@filter_dropnav_style: button}',
    ],

    'uk-margin' => $props['filter_dropnav_style'] != 'button',

    'uk-grid' => $props['filter_dropnav_style'] == 'button',
]);

$link = $this->el('a', [
    'href' => true,
    'role' => 'button',
    'class' => [
        'uk-button uk-button-default {@filter_dropnav_style: button}',
    ],
]);

// Dropdown
$dropdown = $this->el('div', [
    'class' => [
        'uk-dropdown',
        'uk-dropdown-large {@filter_dropdown_size} {@!filter_dropdown_dropbar}',
        'uk-dropdown-dropbar-large {@filter_dropdown_size} {@filter_dropdown_dropbar}',
    ],
]);

?>

<?= $dropnav($props) ?>
    <?= $filter_nav($props) ?>

        <?php if (array_filter($filters['attributes'])) : ?>
            <?php foreach ($filters['attributes'] as $name => $filter) :

                if ($count = $filters['filter_count']['attr'][wc_attribute_taxonomy_name($name)] ?? '') {
                    if ($props['filter_active_count'] == 'parenthesis') {
                        $count = "({$count})";
                    } elseif ($props['filter_active_count'] == 'superscript') {
                        $count = "<sup>{$count}</sup>";
                    }
                }

            ?>
            <li>
                <?= $link($props) ?><?= ucwords($name) ?> <?= $count ?> <?= $icon ? $icon($props, '') : '' ?><?= $link->end() ?>
                <?= $dropdown($props) ?>
                    <?= $filter ?>
                <?= $dropdown->end() ?>
            </li>
            <?php endforeach ?>
        <?php endif ?>

        <?php foreach (['price', 'rating'] as $name) : ?>
            <?php if ($filters[$name]) : ?>
                <?php
                    if ($count = isset($filters['filter_count'][$name])) {
                        if ($props['filter_active_count'] == 'parenthesis') {
                            $count = "({$filters['filter_count'][$name]})";
                        } elseif ($props['filter_active_count'] == 'superscript') {
                            $count = "<sup>{$filters['filter_count'][$name]}</sup>";
                        }
                    }
                ?>
            <li>
                <?= $link($props) ?><?= __(ucwords($name), 'woocommerce') ?> <?= $count ?> <?= $icon ? $icon($props, '') : '' ?><?= $link->end() ?>
                <?= $dropdown($props) ?>
                    <?= $filters[$name] ?>
                <?= $dropdown->end() ?>
            </li>
            <?php endif ?>
        <?php endforeach ?>

    <?= $filter_nav->end() ?>
<?= $dropnav->end() ?>
