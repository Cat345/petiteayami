<?php

use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\WidgetLayeredNav;
use YOOtheme\Http\Request;
use function YOOtheme\app;

// Resets
if (!wc_get_loop_prop('is_paginated') || !woocommerce_products_will_display()) {
    $props['show_product_ordering'] = '';

    // Do show "0 Results"
    if (!wc_get_loop_prop('is_paginated') || woocommerce_get_loop_display_mode() === 'subcategories') {
        $props['show_result_count'] = '';
    }
}

// Preserve `product_tag` query var, when on product category page.
$removeUrlFilter = Helper::addFilter('woocommerce_widget_get_current_page_url', [Helper::class, 'addProductTagToCurrentPageUrl']);

$filters = [];
$filters['attributes'] = [];
if ($props['show_attribute_filters']) {
    foreach (wc_get_attribute_taxonomies() as $tax) {
        if (taxonomy_exists(wc_attribute_taxonomy_name($tax->attribute_name))) {
            $widget = Helper::renderLayeredNavWidget(['attribute' => $tax->attribute_name]);
            if ($widget) {
                $filters['attributes'][$tax->attribute_label] = "<div class=\"woocommerce widget_layered_nav woocommerce-widget-layered-nav uk-panel uk-text-nowrap\">{$widget}</div>";
            }
        }
    }
}

// Filter Count
if ($props['filter_active_count']) {
    $filters['filter_count'] = [];

    $request = app(Request::class);

    if ($props['show_attribute_filters']) {
        foreach (\WC_Query::get_layered_nav_chosen_attributes() as $name => $attr) {
            $filters['filter_count']['attr'][$name] = count($attr['terms']);
        }
    }

    if ($props['show_price_filter'] && ($request->getQueryParam('min_price') || $request->getQueryParam('max_price'))) {
        $filters['filter_count']['price'] = (bool) $request->getQueryParam('min_price') + (bool) $request->getQueryParam('max_price');
    }

    if ($props['show_rating_filter'] && $request->getQueryParam('rating_filter')) {
        $filters['filter_count']['rating'] = count(explode(',', $request->getQueryParam('rating_filter')));
    }

}

$filters['price'] = $props['show_price_filter'] ? Helper::renderWidget(WC_Widget_Price_Filter::class) : '';
$filters['rating'] = $props['show_rating_filter'] ? Helper::renderWidget(WC_Widget_Rating_Filter::class) : '';

$filters['price'] = $filters['price'] ? '<div class="woocommerce widget_price_filter">'.$filters['price'].'</div>' : '';
$filters['rating'] = $filters['rating'] ? '<div class="woocommerce widget_rating_filter uk-panel">'.$filters['rating'].'</div>' : '';

$removeUrlFilter();

$active_filters = '';
if ($props['show_active_filters'] && is_filtered()) {
    $active_filters = Helper::renderWidget(WC_Widget_Layered_Nav_Filters::class);

    if ($props['filter_active_all']) {
        $active_filters = '<ul><li><a rel="nofollow" aria-label="' . __('Remove Filter', 'woocommerce') . '" href="' . Helper::getCurrentPageUrl() . '">' . __('Clear All', 'yootheme') . '</a></li>'
            . ($props['filter_active_all'] == 'both' ? str_replace('<ul>', '', $active_filters) : '</ul>');
    }

    $active_filters = '<div class="woocommerce widget_layered_nav_filters uk-panel">' . $active_filters . '</div>';
}

// Resets
if ($props['filter_active_display']) {
    $props['active_filters_align'] = '';
    $props['active_filters_below'] = '';
}
if ($props['result_count_below']) {
    $props['result_count_align'] = '';
    $props['result_count_below'] = $props['result_count_align'];
}
if ($props['active_filters_below']) {
    $props['active_filters_align'] = '';
    $props['active_filters_below'] = $props['active_filters_align'];
}

$el = $this->el('div', [

    'class' => [
        'js-filter tm-element-woo-filter',
    ],

]);

// Grid
$grid = $this->el('div', [

    'class' => [
        'uk-child-width-auto[@{grid_breakpoint}] uk-flex-middle',
        $props['grid_column_gap'] == $props['grid_row_gap']
            ? 'uk-grid-{grid_column_gap}'
            : '[uk-grid-column-{grid_column_gap}] [uk-grid-row-{grid_row_gap}]',
    ],

    'uk-grid' => true,
]);

// Cells
$cell_left = $this->el('div', [

    'class' => [
        'uk-width-expand[@{grid_breakpoint}]',
    ]

]);

$cell_right = $this->el('div', [

    'class' => [
        'uk-margin-auto-left' => !(array_filter($filters) && $props['filters_align'] == 'left')
            || ($active_filters && $props['active_filters_align'] == 'left')
            || ($props['show_result_count'] && $props['result_count_align'] == 'left')
            || ($props['show_product_ordering'] && $props['product_ordering_align'] == 'left'),
    ]

]);

$cell_filter_dropnav = $this->el('div', [

    'class' => [
        'uk-visible@{filter_dropnav_breakpoint}',
    ],

]);

$cell_filter_toggle = $this->el('div', [

    'class' => [
        'uk-hidden@{filter_dropnav_breakpoint} {@filter: dropnav}',
    ],

]);

$cell_result = $this->el('div', [

    'class' => [
        'uk-panel',
        'uk-flex-first[@{subgrid_breakpoint}] {@result_count_align: right}',
    ]

]);

$cell_below_active = $this->el('div', [

    'class' => [
        'uk-margin-auto-left {@active_filters_below: right}' => $props['result_count_below'] == 'right' || !$props['show_result_count'],
    ]

]);

$cell_below_result = $this->el('div', [

    'class' => [
        'uk-panel',
        'uk-flex-first[@{grid_breakpoint}] uk-margin-auto-right {@result_count_below: left} {@active_filters_below: right}',
        'uk-margin-auto-left {@result_count_below: right}' => $props['active_filters_below'] == 'left' || !$active_filters,
    ]

]);

// @see: woocommerce_result_count()
$resultCount = $props['show_result_count'] ? sprintf(
    _n(__('%s Result', 'yootheme'), __('%s Results', 'yootheme'), wc_get_loop_prop('total')),
    wc_get_loop_prop('total')
) : '';

// Subgrid
$subgrid = $this->el('div', [

    'class' => [
        'uk-child-width-auto[@{subgrid_breakpoint}] uk-flex-middle',
        $props['grid_column_gap'] == $props['grid_row_gap'] ? 'uk-grid-{grid_column_gap}' : '[uk-grid-column-{grid_column_gap}] [uk-grid-row-{grid_row_gap}]',
    ],

    'uk-grid' => true,
]);

?>

<?php if (array_filter($filters) || $props['show_result_count'] || $props['show_product_ordering']) : ?>
<?= $el($props, $attrs) ?>

    <?= $grid($props) ?>

        <?php if (
            (array_filter($filters) && $props['filters_align'] == 'left')
            || ($active_filters && $props['active_filters_align'] == 'left')
            || ($props['show_result_count'] && $props['result_count_align'] == 'left')
            || ($props['show_product_ordering'] && $props['product_ordering_align'] == 'left')
        ) : ?>
        <?= $cell_left($props) ?>

            <?= $subgrid($props) ?>

                <?php if (array_filter($filters) && $props['filters_align'] == 'left') : ?>

                    <?php if ($props['filter'] == 'dropnav') : ?>
                    <?= $cell_filter_dropnav($props, $this->render("{$__dir}/template-filter-dropnav", compact('props', 'filters', 'active_filters'))) ?>
                    <?php endif ?>

                    <?php if (in_array($props['filter'], ['dropdown', 'offcanvas']) || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'])) : ?>
                        <?= $cell_filter_toggle($props) ?>
                        <?php if ($props['filter'] == 'dropdown' || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'dropdown')) : ?>
                            <?= $this->render("{$__dir}/template-filter-dropdown", compact('props', 'filters', 'active_filters')) ?>
                        <?php else : ?>
                            <?= $this->render("{$__dir}/template-filter-offcanvas", compact('props', 'filters', 'active_filters')) ?>
                        <?php endif ?>
                        <?= $cell_filter_toggle->end() ?>
                    <?php endif ?>

                <?php endif ?>

                <?php if ($active_filters && $props['active_filters_align'] == 'left') : ?>
                <div><?= $active_filters ?></div>
                <?php endif ?>

                <?php if ($props['show_product_ordering'] && $props['product_ordering_align'] == 'left') : ?>
                <div><?= $this->render("{$__dir}/template-ordering", compact('props')) ?></div>
                <?php endif ?>

                <?php if ($props['show_result_count'] && $props['result_count_align'] == 'left') : ?>
                <?= $cell_result($props, $resultCount) ?>
                <?php endif ?>

            <?= $subgrid->end() ?>

        <?= $cell_left->end() ?>
        <?php endif ?>

        <?php if (
            (array_filter($filters) && $props['filters_align'] == 'right')
            || ($active_filters && $props['active_filters_align'] == 'right')
            || ($props['show_result_count'] && $props['result_count_align'] == 'right')
            || ($props['show_product_ordering'] && $props['product_ordering_align'] == 'right')
        ) : ?>
        <?= $cell_right($props) ?>

            <?= $subgrid($props) ?>

                <?php if ($props['show_product_ordering'] && $props['product_ordering_align'] == 'right') : ?>
                <div><?= $this->render("{$__dir}/template-ordering", compact('props')) ?></div>
                <?php endif ?>

                <?php if (array_filter($filters) && $props['filters_align'] == 'right') : ?>

                    <?php if ($props['filter'] == 'dropnav') : ?>
                    <?= $cell_filter_dropnav($props, $this->render("{$__dir}/template-filter-dropnav", compact('props', 'filters', 'active_filters'))) ?>
                    <?php endif ?>

                    <?php if (in_array($props['filter'], ['dropdown', 'offcanvas']) || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'])) : ?>
                        <?= $cell_filter_toggle($props) ?>
                        <?php if ($props['filter'] == 'dropdown' || ($props['filter'] == 'dropnav' && $props['filter_dropnav_breakpoint'] && $props['filter_dropnav_fallback'] == 'dropdown')) : ?>
                            <?= $this->render("{$__dir}/template-filter-dropdown", compact('props', 'filters', 'active_filters')) ?>
                        <?php else : ?>
                            <?= $this->render("{$__dir}/template-filter-offcanvas", compact('props', 'filters', 'active_filters')) ?>
                        <?php endif ?>
                        <?= $cell_filter_toggle->end() ?>
                    <?php endif ?>

                <?php endif ?>

                <?php if ($active_filters && $props['active_filters_align'] == 'right') : ?>
                <div><?= $active_filters ?></div>
                <?php endif ?>

                <?php if ($props['show_result_count'] && $props['result_count_align'] == 'right') : ?>
                <?= $cell_result($props, $resultCount) ?>
                <?php endif ?>

            <?= $subgrid->end() ?>

        <?= $cell_right->end() ?>
        <?php endif ?>

    <?= $grid->end() ?>

    <?php if (($active_filters && $props['active_filters_below']) || ($props['show_result_count'] && $props['result_count_below'])) : ?>
    <?= $grid($props) ?>

        <?php if ($active_filters && $props['active_filters_below']) : ?>
        <?= $cell_below_active($props, $active_filters) ?>
        <?php endif ?>

        <?php if ($props['show_result_count'] && $props['result_count_below']) : ?>
        <?= $cell_below_result($props, $resultCount) ?>
        <?php endif ?>

    <?= $grid->end() ?>
    <?php endif ?>

<?= $el->end() ?>
<?php endif ?>
