<?php

namespace YOOtheme;

use YOOtheme\Builder\Templates\TemplateHelper;

$search = &$fields[0];

$search_layout = false;
$search_icon = [];

// Search
$attrs['class'] = array_merge(['uk-search'], (array) ($attrs['class'] ?? null));

// Search Input
$search['type'] = 'search';
$search['class'][] = 'uk-search-input';

$site = '~theme.site';
$header = str_ends_with($position, '-mobile') ? '~theme.mobile.header' : '~theme.header';
$navbar = '~theme.navbar';

$liveSearch = false;

// Apply header settings
if (preg_match('/^(logo|navbar|header|dialog)/', $position)) {

    // Search Icon
    if ($config("$header.search_icon")) {
        $search_icon['uk-search-icon'] = true;
        if ($config("$header.search_icon") == 'right') {
            $search_icon['class'] = 'uk-search-icon-flip';
            $search_icon['tag'] = 'button';
        }
    }

}
if (preg_match('/^(logo|navbar|header)/', $position)) {

    // Config
    $layout = $config("$header.layout");
    $search_layout = $config("$header.search_layout");
    $navbar_position = in_array($position, ['navbar', 'navbar-split', 'navbar-push', 'navbar-mobile', 'header-mobile']) ||
                        (in_array($position, ['header', 'header-split']) && str_starts_with($layout, 'horizontal')) ||
                        ($position == 'logo' && preg_match('/^(horizontal|stacked-center-split-[ab])/', $layout)) ||
                        $position == 'logo-mobile';

    // Expand input width || Search Toggle
    if ((str_starts_with($search_layout, 'input') && preg_match('/^horizontal-(left|center|right|justify)|stacked-(left|justify)$/', $layout) && $config("$header.search_expand")) ||
        !str_starts_with($search_layout, 'input')) {
        $attrs['class'][] = 'uk-width-1-1';
    }

    // Search Input
    $search['autofocus'] = !str_starts_with($search_layout, 'input');

    // Search Results
    $template = app(TemplateHelper::class)->match([
        'type' => '_search',
        'query' => ['lang' => $language ?? ''],
    ]);

    if ($template) {
        $liveSearch = true;
        $liveSearchOutput = $this->builder(
            $template['layout'] ?? [],
            ['prefix' => "template-{$template['id']}", 'template' => $template['type'], 'search' => ['searchword' => $search['value'] ?? '']],
        );

        $id = "{$attrs['id']}-search-results";
        $search['uk-search'] = json_encode([
            'target' => "#{$id}",
            'mode' => !str_starts_with($search_layout, 'input') ? $search_layout : false,
            'preventSubmit' => (bool) $config("$header.search_prevent_submit"),
        ]);

        $attrs_livesearch = ['class' => ['uk-margin uk-hidden-empty']];

        app(Metadata::class)->set('script:theme-search', ['src' => '~theme/js/theme-search.js', 'defer' => true]);
    }

    if (!str_starts_with($search_layout, 'input')) {

        $toggle = [
            'class' => [($navbar_position ? 'uk-navbar-toggle' : 'uk-search-toggle uk-display-block')],
            'id' => $navbar_position && !empty($tag['id']) ? $tag['id'] : null,
            'href' => in_array($search_layout, ['dropbar', 'modal']) ? "#{$attrs['id']}-search" : true,
        ];

        if ($search_layout == 'modal' && $config("$header.search_modal.width") == 'full') {
            $attrs['class'][] = 'uk-search-large';
        } else {
            $attrs['class'][] = 'uk-search-medium';
        }

    } else {

        $attrs['class'][] = 'uk-search-navbar';

    }

    // Dropdown
    if (($search_layout == 'input-dropdown' && $liveSearch) || $search_layout == 'dropdown') {

        // From `navbar.php`
        $attrs_dropdown = [
            'class' => [($navbar_position ? 'uk-drop uk-navbar-dropdown' : 'uk-drop uk-dropdown')],
        ];

        if ($config("$header.search_dropdown.size")) {
            $attrs_dropdown['class'][] = $navbar_position ? ($config("$navbar.dropbar") ? 'uk-navbar-dropdown-dropbar-large' : 'uk-navbar-dropdown-large') : 'uk-dropdown-large';
        }
        $attrs_dropdown['class'][] = $config("$navbar.dropbar") && $config("$header.transparent") ? 'uk-dropbar-inset' : '';

        $stretch = $config("$header.search_dropdown.stretch");

        $align = $config("$header.search_dropdown.align") ?: $config("$navbar.dropdown_align");

        $dropdown = [
            'mode' => 'click',
            'toggle' => $search_layout == 'input-dropdown' ? 'false' : false,
            'pos' => "bottom-{$align}",
            'stretch' => $stretch ? 'x' : null,
            'boundary' => $stretch ? (str_ends_with($position, '-mobile') ? '.tm-header-mobile' : '.tm-header') . " .uk-{$stretch}" : null,
        ];

        if (!$stretch) {
            $attrs_dropdown['style'][] = $config("$header.search_dropdown.width") ? "width: {$config("$header.search_dropdown.width")}px;" : 'width: 400px;';
        }

        if ($config("$header.search_dropdown.padding_remove_horizontal")) {
            if ($config("$navbar.dropbar")) {
                $attrs_dropdown['style'][] = '--uk-position-viewport-offset: 0;';
            } else {
                $attrs_dropdown['class'][] = 'uk-padding-remove-horizontal';
            }
        }

        if ($config("$header.search_dropdown.padding_remove_vertical")) {
            $attrs_dropdown['class'][] = 'uk-padding-remove-vertical';
        }

        if ($navbar_position) {
            $dropdown += $attrs_dropdown;
        } else {
            // From `header.php`
            $outside = $config('~theme.site.layout') == 'boxed' && $config('~theme.site.boxed.header_outside');
            $dropdown = $attrs_dropdown + [
                'uk-dropdown' => $dropdown + [
                    'container' => $config("$header.transparent") && $config("$header.blend") ? ($outside ? '.tm-page-container' : '.tm-page') : '.tm-header',
                    'flip' => 'false',
                    'close-on-scroll' => true,
                ],
            ];
            $dropdown['uk-dropdown'] = json_encode(array_filter($dropdown['uk-dropdown']));
        }

    // Dropbar
    } elseif (($search_layout == 'input-dropbar' && $liveSearch) || $search_layout == 'dropbar') {

        $header_cls = str_ends_with($position, '-mobile') ? '.tm-header-mobile' : '.tm-header';

        // From `header.php`
        $outside = $config("$site.layout") == 'boxed' && $config("$site.boxed.header_outside");

        $attrs_dropbar = [];
        $attrs_dropbar['id'] = "{$attrs['id']}-search";
        $attrs_dropbar['class'][] = str_ends_with($position, '-mobile') ? 'uk-dropbar' : 'uk-dropbar uk-dropbar-large';
        $attrs_dropbar['class'][] = $config("$header.search_dropbar.padding_remove_horizontal") ? 'uk-padding-remove-horizontal' : '';
        $attrs_dropbar['class'][] = $config("$header.search_dropbar.padding_remove_vertical") ? 'uk-padding-remove-vertical' : '';

        if (!$config("$header.search_dropbar.animation") || $config("$header.search_dropbar.animation") == 'reveal-top') {
            $attrs_dropbar['class'][] = 'uk-dropbar-top';
        } elseif ($config("$header.search_dropbar.animation") == 'slide-left') {
            $attrs_dropbar['class'][] = 'uk-dropbar-left';
            $attrs_dropbar['class'][] = $config("$header.search_dropbar.width") ? "uk-width-{$config("$header.search_dropbar.width")}" : '';
        }
        elseif ($config("$header.search_dropbar.animation") == 'slide-right') {
            $attrs_dropbar['class'][] = 'uk-dropbar-right';
            $attrs_dropbar['class'][] = $config("$header.search_dropbar.width") ? "uk-width-{$config("$header.search_dropbar.width")}" : '';
        }

        // If no navbar present
        $container = str_starts_with($layout, 'stacked') && !is_active_sidebar('navbar') ? '.tm-headerbar' : '.uk-navbar-container';

        $attrs_dropbar['uk-drop'] = [
            // Default
            'clsDrop' => 'uk-dropbar',
            'flip' => 'false', // Has to be a string
            'container' => $config("$navbar.sticky") ? "{$header_cls} > [uk-sticky]" : $header_cls,
            'target-y' => "{$header_cls} {$container}",
            // New
            'toggle' => $search_layout == 'input-dropbar' ? 'false' : false,
            'mode' => 'click',
            'target-x' => "{$header_cls} {$container}",
            'boundary-x' => $config("$site.layout") == 'boxed' && !$config("$site.boxed.header_outside") ? "{$header_cls} {$container}" : null,
            'stretch' => in_array($config("$header.search_dropbar.animation"), ['slide-left', 'slide-right']) && $config("$header.search_dropbar.width") ? 'y' : true,
            'pos' => $config("$header.search_dropbar.animation") == 'slide-right' ? 'bottom-right' : 'bottom-left',
            'bgScroll' => 'false', // Has to be a string
            'animation' => $config("$header.search_dropbar.animation") ?: null,
            'animateOut' => true,
            'duration' => 300,
        ];

        // Behind navbar
        if ($config("$header.transparent")) {
            $attrs_dropbar['uk-drop']['inset'] = true;
            $attrs_dropbar['class'][] = 'uk-dropbar-inset';
            $attrs_dropbar['uk-drop']['pos'] = $config("$header.search_dropbar.animation") == 'slide-right' ? 'top-right' : 'top-left';

            if ($config("$header.blend")) {
                $attrs_dropbar['uk-drop']['container'] = $outside ? '.tm-page-container' : '.tm-page';
            }
            // Set same z-index as dropnav (high but behind navbar, which is set to high). Needed in two cases: 1. blend and 2. not sticky and outside
            $attrs_dropbar['style'][] = 'z-index: 980;';
        }

        $attrs_dropbar['uk-drop'] = json_encode(array_filter($attrs_dropbar['uk-drop']));

        $attrs_dropbar_content = [];
        $attrs_dropbar_content['class'][] = 'tm-height-min-1-1 uk-flex uk-flex-column';
        $attrs_dropbar_content['class'][] = $config("$header.search_dropbar.content_width") ? "uk-{$config("$header.search_dropbar.content_width")} uk-margin-auto" : '';
        $attrs_dropbar_content['class'][] = $config("$header.search_dropbar.content_width") == 'container' ? 'uk-padding-remove-horizontal' : '';

    // Modal
    } elseif ($search_layout == 'modal') {

        $modal = [

            'uk-modal' => true,

            'id' => "{$attrs['id']}-search",

            'class' => [
                'uk-modal',
                'uk-modal-container' => $config("$header.search_modal.width") == 'container',
                'uk-modal-full' => $config("$header.search_modal.width") == 'full',
            ],

        ];

        $modal_dialog = [

            'class' => [
                'uk-modal-dialog uk-modal-body',
                'uk-width-1-1' => $config("$header.search_modal.width") == 'expand',
                'uk-padding-large' => $config("$header.search_modal.width") == 'full',
                'uk-flex uk-flex-center uk-flex-middle' => $config("$header.search_modal.width") == 'full' && !$liveSearch,
            ],

            'uk-height-viewport' => $config("$header.search_modal.width") == 'full',

            'uk-toggle' => $config("$header.search_modal.width") == 'full'
                ? 'cls: uk-padding-large; mode: media; media: @s'
                : null,

        ];

        $modal_close = [];
        if ($config("$header.search_modal.close") || $config("$header.search_modal.width") == 'full') {

            $modal_close = [

                'uk-close' => true,

                'class' => [
                    'uk-modal-close-default uk-close-large uk-modal-close-outside' => $config("$header.search_modal.width") != 'full',
                    'uk-modal-close-full uk-close-large' => $config("$header.search_modal.width") == 'full',
                ],

                'uk-toggle' => [
                    'mode' => 'media',
                    'media' =>'@s',
                    'cls' => $config("$header.search_modal.width") == 'full' ? 'uk-modal-close-full uk-close-large uk-modal-close-default' : 'uk-close-large uk-modal-close-outside',
                ]

            ];

            $modal_close['uk-toggle'] = json_encode(array_filter($modal_close['uk-toggle']));

        }

    }

} else {

    // Search
    $attrs['class'][] = 'uk-search-default';

    // Sidebar layouts
    if (preg_match('/^(sidebar|dialog(-mobile)?(-push)?)$/', $position)) {
        $attrs['class'][] = 'uk-width-1-1';
    }

    // Search Icon
    $search_icon['uk-search-icon'] = true;

}

if ($search_icon) {
    $search_icon += ['tag' => 'span'];
    if ($search_icon['tag'] == 'button') {
        $search_icon['type'] = 'submit';
        $fields[] = $search_icon;
    } else {
        array_unshift($fields, $search_icon);
    }
}

?>

<?php if ($search_layout == 'dropdown') : ?>

    <a<?= $this->attrs($toggle) ?> uk-search-icon></a>

    <div<?= $this->attrs($dropdown) ?>>

        <?= $this->form($fields, $attrs) ?>

        <?php if ($liveSearch) : ?>
        <div <?= $this->attrs($attrs_livesearch + ['id' => $id]) ?>><?= $liveSearchOutput ?></div>
        <?php endif ?>

    </div>

<?php elseif ($search_layout == 'dropbar') : ?>

    <a<?= $this->attrs($toggle) ?> uk-search-icon></a>

    <div<?= $this->attrs($attrs_dropbar) ?>>
        <div<?= $this->attrs($attrs_dropbar_content) ?>>

            <?php if ($config("$header.transparent")) : ?>
            <div uk-height-placeholder="<?= $header_cls ?> .uk-navbar-container"></div>
            <?php endif ?>

            <?= $this->form($fields, $attrs) ?>

            <?php if ($liveSearch) : ?>
            <div <?= $this->attrs($attrs_livesearch + ['id' => $id]) ?>><?= $liveSearchOutput ?></div>
            <?php endif ?>

        </div>
    </div>

<?php elseif ($search_layout == 'modal') : ?>

    <a<?= $this->attrs($toggle) ?> uk-search-icon uk-toggle></a>

    <div<?= $this->attrs($modal) ?>>
        <div<?= $this->attrs($modal_dialog) ?>>

            <?php if ($modal_close) : ?>
            <button type="button"<?= $this->attrs($modal_close) ?>></button>
            <?php endif ?>

            <?php if ($config("$header.search_modal.width") == 'full') : ?>
            <div>
            <?php endif ?>

                <?= $this->form($fields, $attrs) ?>

                <?php if ($liveSearch) : ?>
                <div<?= $this->attrs($attrs_livesearch + ['id' => $id]) ?>><?= $liveSearchOutput ?></div>
                <?php endif ?>

            <?php if ($config("$header.search_modal.width") == 'full') : ?>
            </div>
            <?php endif ?>

        </div>
    </div>

<?php else : ?>

    <?= $this->form($fields, $attrs) ?>

    <?php if ($liveSearch) : ?>
        <?php if ($search_layout == 'input-dropdown') : ?>
            <div <?= $this->attrs($dropdown + ['id' => $id]) ?>><?= $liveSearchOutput ?></div>
        <?php elseif ($search_layout == 'input-dropbar') : ?>
            <div<?= $this->attrs($attrs_dropbar) ?>>
                <div<?= $this->attrs($attrs_dropbar_content) ?>>
                    <?php if ($config("$header.transparent")) : ?>
                    <div uk-height-placeholder="<?= $header_cls ?> .uk-navbar-container"></div>
                    <?php endif ?>
                    <div <?= $this->attrs(['id' => $id]) ?>><?= $liveSearchOutput ?></div>
                </div>
            </div>
        <?php endif ?>
    <?php endif ?>

<?php endif ?>
