<?php

// Dropdown
$dropdown = $this->el('div', [

    'class' => [
        'tm-element-filter-columns {@filter_attribute_columns}',
    ],

    'uk-drop' => [
        'mode: click;',
        'stretch: x;',
        'target-x: !.js-filter;',
        'animate-out: true;',
    ],

]);

if ($props['filter_dropdown_dropbar']) {

    $dropdown->attr([

        'class' => [
            'uk-dropbar uk-dropbar-top',
            'uk-dropbar-large {@filter_dropdown_size}',
        ],

        'uk-drop' => [
            'dropbar: true;',
            'dropbar-anchor: !.js-filter > :last-child:not(.uk-dropbar), !.js-filter:has(> .uk-dropbar) > :nth-last-child(2);',
            'boundary-x: !.tm-page;',
            'target-y: !.js-filter > :first-child;',
        ],

    ]);

} else {

    $dropdown->attr([

        'class' => [
            'uk-dropdown',
            'uk-dropdown-large {@filter_dropdown_size}',
        ],

        'uk-drop' => [
            'boundary-x: !.js-filter;',
        ],

    ]);

}

// Dropbar Content
$dropbar_content = $this->el('div', [

    'class' => [
        'uk-{filter_dropdown_dropbar_content_width} uk-margin-auto',
        'uk-padding-remove-horizontal {@filter_dropdown_dropbar_content_width: container}',
    ],

]);

// Dropdown Grid
$dropdown_grid = $this->el('div', [

    'class' => [
        'uk-child-width-1-1',
        'uk-child-width-1-{filter_dropdown_grid}[@{filter_dropdown_grid_breakpoint}] {@!filter_dropdown_grid: 1|auto|grow}',
        'uk-child-width-auto[@{filter_dropdown_grid_breakpoint}] {@filter_dropdown_grid: auto|grow}',
        'tm-grid-expand {@filter_dropdown_grid: grow}',
        $props['filter_dropdown_grid_column_gap'] == $props['filter_dropdown_grid_row_gap'] ? 'uk-grid-{filter_dropdown_grid_column_gap}' : '[uk-grid-column-{filter_dropdown_grid_column_gap}] [uk-grid-row-{filter_dropdown_grid_row_gap}]',

    ],

    'uk-grid' => true,
]);

// Filter Title
$filter_title = $this->el('h3', [

    'class' => [
        'uk-{filter_title_style}',
    ],

]);

?>

<?= $this->render("{$__dir}/template-filter-toggle", compact('props', 'filters')) ?>

<?= $dropdown($props) ?>

    <?php if ($props['filter_dropdown_dropbar']) : ?>
    <?= $dropbar_content($props) ?>
    <?php endif ?>

        <?= $dropdown_grid($props) ?>

            <?php if (array_filter($filters['attributes'])) : ?>
                <?php foreach ($filters['attributes'] as $name => $filter) : ?>
                <div>
                    <?= $filter_title($props, ucwords($name)) ?>
                    <?= $filter ?>
                </div>
                <?php endforeach ?>
            <?php endif ?>

            <?php if ($filters['price']) : ?>
            <div>
                <?= $filter_title($props, __('Price', 'woocommerce')) ?>
                <?= $filters['price'] ?>
            </div>
            <?php endif ?>

            <?php if ($filters['rating']) : ?>
            <div>
                <?= $filter_title($props, __('Rating', 'woocommerce')) ?>
                <?= $filters['rating'] ?>
            </div>
            <?php endif ?>

            <?php if ($active_filters && $props['filter_active_display']) : ?>
            <div>
                <?= $filter_title($props, __('Active Filters', 'woocommerce')) ?>
                <?= $active_filters ?>
            </div>
            <?php endif ?>

        <?= $dropdown_grid->end() ?>

    <?php if ($props['filter_dropdown_dropbar']) : ?>
    <?= $dropbar_content->end() ?>
    <?php endif ?>

<?= $dropdown->end() ?>
