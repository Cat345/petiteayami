<?php

// Config
$site = '~theme.site';
$header = '~theme.header';
$navbar = '~theme.navbar';
$dialog = '~theme.dialog';
$mobile = '~theme.mobile';

// Options
$layout = $config("$header.layout");

// Outside
$outside = $config("$site.layout") == 'boxed' && $config("$site.boxed.header_outside");

// Header
$attrs = [];
$attrs['class'][] = 'tm-header';
$attrs['class'][] = $config("$mobile.breakpoint") ? "uk-visible@{$config("$mobile.breakpoint")}" : '';

// Navbar Container
$attrs_navbar_container = [];
$attrs_navbar_container['class'][] = 'uk-navbar-container';
$attrs_navbar_container['class'][] = $config("$navbar.style") ? "uk-navbar-{$config("$navbar.style")}" : '';

// Navbar
$attrs_navbar = [

    'class' => [
        'uk-navbar',
        'uk-navbar-justify' => in_array($layout, ['horizontal-justify', 'stacked-justify']),
    ],

    'uk-navbar' => [
        'align' => $config("$navbar.dropdown_align"),
        'container' => $config("$header.transparent") && $config("$header.blend") ? ($outside ? '.tm-page-container' : '.tm-page') : '.tm-header',
        'boundary' => '.tm-header .uk-navbar-container', // By default, it would be the navbar component's element
        'target-x' => $config("$navbar.dropdown_target") ? '.tm-header .uk-navbar' : null,
    ],

];

if ($config("$navbar.dropbar")) {

    $attrs_navbar['uk-navbar']['target-y'] = '.tm-header .uk-navbar-container';
    $attrs_navbar['uk-navbar']['dropbar'] = true;
    $attrs_navbar['uk-navbar']['dropbar-anchor'] = $config("$header.transparent") && $config("$header.blend") ? ($outside ? '.tm-page-container > .tm-page' : '.tm-page > main') : '.tm-header .uk-navbar-container'; // Has to be after navbar container because it has uk-light/dark
    $attrs_navbar['uk-navbar']['dropbar-transparent-mode'] = $config("$header.transparent") ? 'behind' : 'remove';

}

// Sticky
$attrs_sticky = [];
if ($sticky = $config("$navbar.sticky")) {

    if ($config("$header.transparent") && $config("$header.blend")) {
        $attrs_navbar['uk-navbar']['close-on-scroll'] = true;
    } else {
        $attrs_navbar['uk-navbar']['container'] = '.tm-header > [uk-sticky]';
    }

    $attrs_sticky = array_filter([
        'uk-sticky' => true,
        'media' => $config("$mobile.breakpoint") ? "@{$config("$mobile.breakpoint")}" : false,
        'show-on-up' => $sticky == 2,
        'animation' => $sticky == 2 ? 'uk-animation-slide-top' : '',
        'cls-active' => 'uk-navbar-sticky',
        'sel-target' => '.uk-navbar-container',
    ]);

}

$attrs_navbar['uk-navbar'] = json_encode(array_filter($attrs_navbar['uk-navbar']));

// Search Expand
$search_expand = '';
if ($config("$header.search") && !str_starts_with($config("$header.search"), 'dialog') && str_starts_with($config("$header.search_layout"), 'input') &&
        (preg_match('/^horizontal-(left|center|right)|stacked-left$/', $layout)
        || ($layout == 'stacked-justify' && preg_match('/^(header|logo)/', $config("$header.search"))))
    && $config("$header.search_expand")) {

    if (preg_match('/^horizontal-(left|center|right)$/', $layout)) {
        $layout = 'horizontal-justify';
    } elseif ($layout == 'stacked-left' && str_starts_with($config("$header.search"), 'navbar') ) {
        $layout = 'stacked-justify';
    }
    $search_expand = true;
}

// Transparent
$attrs_headerbar = [];

if ($config("$header.transparent")) {

    $attrs_navbar_container['class'][] = 'uk-navbar-transparent';

    if ($config("$header.blend")) {
        $attrs['class'][] = 'uk-blend-difference uk-position-z-index-high';
        $attrs_navbar_container['class'][] = 'uk-light';
    } else {
        if ($config("$header.transparent_color_separately")) {
            $attrs['uk-inverse'] = 'target: .uk-navbar-left, .uk-navbar-center, .uk-navbar-right, .tm-headerbar';
        } else {
            $attrs['uk-inverse'] = 'target: .uk-navbar-container, .tm-headerbar';
        }
        $attrs_navbar_container['class'][] = 'uk-position-relative uk-position-z-index-high';
    }

} elseif (($config("$site.boxed.header_transparent") && $outside) || $config('header.section.transparent')) {

    if ($sticky) {
        $attrs_sticky['cls-inactive'] = 'uk-navbar-transparent';
        if ($sticky == 1) {
            $attrs_sticky['animation'] = 'uk-animation-slide-top';
        }
    } else {
        $attrs_navbar_container['class'][] = 'uk-navbar-transparent';
    }

} else {

    $attrs_headerbar['class'][] = 'tm-headerbar-default';

}

if ($outside) {

    if (!$config("$header.transparent") && $config("$site.boxed.header_transparent")) {

        $attrs['uk-inverse'] = 'target: .uk-navbar-container, .tm-headerbar; sel-active: .uk-navbar-transparent, .tm-headerbar';

        if ($sticky) {
            $attrs_sticky['top'] = '300';
        }
    }

} elseif ($config("$header.transparent") || $config('header.section.transparent')) {

    $attrs['uk-header'] = true;
    $attrs['class'][] = 'tm-header-overlay';

    if (!$config("$header.transparent")) {
        $attrs['uk-inverse'] = 'target: .uk-navbar-container, .tm-headerbar; sel-active: .uk-navbar-transparent, .tm-headerbar';

        if ($sticky) {
            $attrs_sticky['tm-section-start'] = true;
        }

    }

}

// Width Container
$attrs_width_container = [];
$attrs_width_container['class'][] = 'uk-container';

if ($outside) {
    $attrs_width_container['class'][] = $config("$header.width") == 'expand' ? 'uk-container-expand' : 'tm-page-width';
} else {
    $attrs_width_container['class'][] = $config("$header.width") != 'default' ? "uk-container-{$config("$header.width")}" : '';
}

$hasHeader = array_filter([
    'logo',
    'header',
    'header-split',
    'navbar',
    'navbar-push',
    'navbar-split',
], fn($position) => is_active_sidebar($position));

$toolbar = trim($view('~theme/templates/toolbar'));

?>

<?php if ($config("$mobile.breakpoint")) : ?>
<?= $view('~theme/templates/header-mobile') ?>
<?php endif ?>

<?php if (!$config("$site.toolbar_transparent")) : ?>
<?= $toolbar ?>
<?php endif ?>

<?php if ($hasHeader || $config("$site.toolbar_transparent") && $toolbar) : ?>

<header<?= $this->attrs($attrs) ?>>

<?php if ($config("$site.toolbar_transparent")) : ?>
<?= $toolbar ?>
<?php endif ?>

<?php

if ($hasHeader) :

// Horizontal layouts
if (str_starts_with($layout, 'horizontal')) :

    $attrs_width_container['class'][] = is_active_sidebar('logo') && $config("$header.logo_padding_remove") && $config("$header.width") == 'expand' && $layout != 'horizontal-center-logo' ? 'uk-padding-remove-left' : '';

    ?>

    <?php if ($sticky) : ?>
    <div<?= $this->attrs($attrs_sticky) ?>>
    <?php endif ?>

        <div<?= $this->attrs($attrs_navbar_container) ?>>

            <div<?= $this->attrs($attrs_width_container) ?>>
                <nav<?= $this->attrs($attrs_navbar) ?>>

                    <?php if (($layout != 'horizontal-center-logo' && is_active_sidebar('logo')) || (preg_match('/^horizontal-(left|justify|center-logo)/', $layout) && is_active_sidebar('navbar')) || ($layout == 'horizontal-justify' && is_active_sidebar('header'))) : ?>
                    <div class="uk-navbar-left <?= $search_expand ? 'uk-flex-1' : '' ?>">

                        <?php if ($layout != 'horizontal-center-logo') : ?>
                            <?php dynamic_sidebar("logo") ?>
                        <?php endif ?>

                        <?php if (preg_match('/^horizontal-(left|justify|center-logo)/', $layout)) : ?>
                            <?php dynamic_sidebar("navbar") ?>
                        <?php endif ?>

                        <?php if ($layout == 'horizontal-justify') : ?>
                            <?php dynamic_sidebar("header") ?>
                        <?php endif ?>

                    </div>
                    <?php endif ?>

                    <?php if (($layout == 'horizontal-center-logo' && is_active_sidebar('logo')) || ($layout == 'horizontal-center' && is_active_sidebar('navbar'))) : ?>
                    <div class="uk-navbar-center">

                        <?php if ($layout == 'horizontal-center-logo') : ?>
                            <?php dynamic_sidebar("logo") ?>
                        <?php endif ?>

                        <?php if ($layout == 'horizontal-center') : ?>
                            <?php dynamic_sidebar("navbar") ?>
                        <?php endif ?>

                    </div>
                    <?php endif ?>

                    <?php if (($layout != 'horizontal-justify' && is_active_sidebar('header')) || ($layout == 'horizontal-right' && is_active_sidebar('navbar'))) : ?>
                    <div class="uk-navbar-right">

                        <?php if ($layout == 'horizontal-right') : ?>
                            <?php dynamic_sidebar("navbar") ?>
                        <?php endif ?>

                        <?php if ($layout != 'horizontal-justify') : ?>
                            <?php dynamic_sidebar("header") ?>
                        <?php endif ?>

                    </div>
                    <?php endif ?>

                </nav>
            </div>

        </div>

    <?php if ($sticky) : ?>
    </div>
    <?php endif ?>

<?php endif ?>

<?php

// Stacked Center layouts
if (preg_match('/^stacked-center-(split-)?[ab]/', $layout)) : ?>

    <?php if ((in_array($layout, ['stacked-center-a', 'stacked-center-b']) && is_active_sidebar('logo')) || $layout == 'stacked-center-a' && is_active_sidebar('header')) : ?>
    <div<?= $this->attrs($attrs_headerbar, ['class' => 'tm-headerbar tm-headerbar-top']) ?>>
        <div<?= $this->attrs($attrs_width_container) ?>>

            <?php if (is_active_sidebar('logo')) : ?>
                <?php dynamic_sidebar("logo:grid-center") ?>
            <?php endif ?>

            <?php if ($layout == 'stacked-center-a' && is_active_sidebar('header')) : ?>
            <div class="tm-headerbar-stacked uk-grid-medium uk-child-width-auto uk-flex-center uk-flex-middle" uk-grid>
                <?php dynamic_sidebar("header:cell") ?>
            </div>
            <?php endif ?>

        </div>
    </div>
    <?php endif ?>

    <?php if (is_active_sidebar('navbar') || is_active_sidebar('navbar-split') || (str_starts_with($layout, 'stacked-center-split') && is_active_sidebar('logo'))) : ?>

        <?php if ($sticky) : ?>
        <div<?= $this->attrs($attrs_sticky) ?>>
        <?php endif ?>

            <div<?= $this->attrs($attrs_navbar_container) ?>>

                <div<?= $this->attrs($attrs_width_container) ?>>
                    <nav<?= $this->attrs($attrs_navbar) ?>>

                        <?php if ($layout == 'stacked-center-split-b' && is_active_sidebar('navbar-split')) : ?>
                        <div class="uk-navbar-left">
                            <?php dynamic_sidebar("navbar-split") ?>
                        </div>
                        <?php endif ?>

                        <div class="uk-navbar-center">

                            <?php if ($layout == 'stacked-center-split-a') : ?>

                                <?php if (is_active_sidebar('navbar-split')) : ?>
                                <div class="uk-navbar-center-left uk-preserve-width">
                                    <?php dynamic_sidebar("navbar-split") ?>
                                </div>
                                <?php endif ?>

                                <?php dynamic_sidebar("logo") ?>

                                <?php if (is_active_sidebar('navbar')) : ?>
                                <div class="uk-navbar-center-right uk-preserve-width">
                                    <?php dynamic_sidebar("navbar") ?>
                                </div>
                                <?php endif ?>

                            <?php elseif ($layout == 'stacked-center-split-b') : ?>
                                <?php dynamic_sidebar("logo") ?>
                            <?php else : ?>
                                <?php dynamic_sidebar("navbar") ?>
                            <?php endif ?>

                        </div>

                        <?php if ($layout == 'stacked-center-split-b' && is_active_sidebar('navbar')) : ?>
                        <div class="uk-navbar-right">
                            <?php dynamic_sidebar("navbar") ?>
                        </div>
                        <?php endif ?>

                    </nav>
                </div>

            </div>

        <?php if ($sticky) : ?>
        </div>
        <?php endif ?>

    <?php endif ?>

    <?php if (in_array($layout, ['stacked-center-b', 'stacked-center-split-a', 'stacked-center-split-b']) && is_active_sidebar('header')) : ?>
    <div<?= $this->attrs($attrs_headerbar, ['class' => 'tm-headerbar tm-headerbar-bottom']) ?>>
        <div<?= $this->attrs($attrs_width_container) ?>>
            <?php dynamic_sidebar("header:grid-center") ?>
        </div>
    </div>
    <?php endif ?>

<?php endif ?>

<?php

// Stacked Center C layout
if ($layout == 'stacked-center-c') : ?>

    <?php if (is_active_sidebar('logo') || is_active_sidebar('header') || is_active_sidebar('header-split')) : ?>
    <div<?= $this->attrs($attrs_headerbar, ['class' => 'tm-headerbar tm-headerbar-top']) ?>>
        <div<?= $this->attrs($attrs_width_container) ?>>
            <div class="uk-position-relative uk-flex uk-flex-center uk-flex-middle">

                <?php if (is_active_sidebar('header')) : ?>
                <div class="uk-position-center-left uk-position-z-index-high">
                    <?php dynamic_sidebar("header:grid-middle") ?>
                </div>
                <?php endif ?>

                <?php if (is_active_sidebar('logo')) : ?>
                <?php dynamic_sidebar("logo:grid-middle") ?>
                <?php endif ?>

                <?php if (is_active_sidebar('header-split')) : ?>
                <div class="uk-position-center-right uk-position-z-index-high">
                    <?php dynamic_sidebar("header-split:grid-middle") ?>
                </div>
                <?php endif ?>

            </div>
        </div>
    </div>
    <?php endif ?>

    <?php if (is_active_sidebar('navbar')) : ?>

        <?php if ($sticky) : ?>
        <div<?= $this->attrs($attrs_sticky) ?>>
        <?php endif ?>

            <div<?= $this->attrs($attrs_navbar_container) ?>>

                <div<?= $this->attrs($attrs_width_container) ?>>
                    <nav<?= $this->attrs($attrs_navbar) ?>>

                        <div class="uk-navbar-center">
                            <?php dynamic_sidebar("navbar") ?>
                        </div>

                    </nav>
                </div>

            </div>

        <?php if ($sticky) : ?>
        </div>
        <?php endif ?>

    <?php endif ?>

<?php endif ?>

<?php

// Stacked Left layout
if (preg_match('/^stacked-(left|justify)/', $layout)) :

    $attrs_navbar['class'][] = 'uk-flex-auto';

    ?>

    <?php if (is_active_sidebar('logo') || is_active_sidebar('header')) : ?>
    <div<?= $this->attrs($attrs_headerbar, ['class' => 'tm-headerbar tm-headerbar-top']) ?>>
        <div<?= $this->attrs($attrs_width_container) ?>>

            <?php if (is_active_sidebar('header')) : ?>
            <div class="uk-grid uk-grid-medium uk-child-width-auto uk-flex-middle">
                <div class="<?= $search_expand && str_starts_with($config("$header.search", ''), 'logo') ? 'uk-flex-1 tm-header-search-expand' : '' ?>">
            <?php endif ?>

                    <?php if (is_active_sidebar('logo')) : ?>
                    <?php dynamic_sidebar("logo:grid-middle") ?>
                    <?php endif ?>

            <?php if (is_active_sidebar('header')) : ?>
                </div>
                <div class="<?= $search_expand && str_starts_with($config("$header.search", ''), 'header') ? 'uk-flex-1 tm-header-search-expand' : 'uk-margin-auto-left' ?>">

                    <?php dynamic_sidebar("header:grid-middle") ?>

                </div>
            </div>
            <?php endif ?>

        </div>
    </div>
    <?php endif ?>

    <?php if (is_active_sidebar('navbar') || is_active_sidebar('navbar-push')) : ?>

        <?php if ($sticky) : ?>
        <div<?= $this->attrs($attrs_sticky) ?>>
        <?php endif ?>

            <div<?= $this->attrs($attrs_navbar_container) ?>>

                <div<?= $this->attrs($attrs_width_container) ?>>
                    <nav<?= $this->attrs($attrs_navbar) ?>>

                        <?php if (is_active_sidebar('navbar') || ($layout == 'stacked-justify' && is_active_sidebar('navbar-push'))) : ?>
                        <div class="uk-navbar-left <?= $search_expand ? 'uk-flex-1' : '' ?>">

                            <?php if (is_active_sidebar('navbar')) : ?>
                            <?php dynamic_sidebar("navbar") ?>
                            <?php endif ?>

                            <?php if ($layout == 'stacked-justify' && is_active_sidebar('navbar-push')) : ?>
                            <?php dynamic_sidebar("navbar-push") ?>
                            <?php endif ?>

                        </div>
                        <?php endif ?>

                        <?php if (($layout != 'stacked-justify' && is_active_sidebar('navbar-push'))) : ?>
                        <div class="uk-navbar-right">
                            <?php dynamic_sidebar("navbar-push") ?>
                        </div>
                        <?php endif ?>

                    </nav>
                </div>

            </div>

        <?php if ($sticky) : ?>
        </div>
        <?php endif ?>

    <?php endif ?>

<?php endif ?>

<?php

// Dialog
$attrs_dialog = [];
$attrs_dialog_push = [];

if (preg_match('/^(offcanvas|modal|dropbar)-center/', $config("$dialog.layout"))) {
    $attrs_dialog['class'][] = 'uk-margin-auto-vertical';
} else {
    $attrs_dialog['class'][] = 'uk-margin-auto-bottom';

    // Expand height so rows in builder modules can take the full height. Only if one module on dialog position.
    $attrs_dialog['class'][] = !is_active_sidebar('dialog-push') ? 'tm-height-expand' : '';
}
$attrs_dialog_push['class'][] = 'uk-grid-margin';

$attrs_dialog['class'][] = $config("$dialog.text_center") ? 'uk-text-center' : '';
$attrs_dialog_push['class'][] = $config("$dialog.text_center") ? 'uk-text-center' : '';

// Modal
$attrs_modal = [];
$attrs_modal['class'][] = 'uk-modal-body uk-padding-large uk-margin-auto uk-flex uk-flex-column uk-box-sizing-content';
$attrs_modal['class'][] = $config("$dialog.modal.width") ? 'uk-width-' .  $config("$dialog.modal.width") : 'uk-width-auto@s';
$attrs_modal['uk-height-viewport'] = true;
$attrs_modal['uk-toggle'] = json_encode(array_filter([
    'cls' => 'uk-padding-large',
    'mode' => 'media',
    'media' => '@s',
]));

// Dropbar
if (str_starts_with($config("$dialog.layout"), 'dropbar')) {

    $attrs_dropbar = [];
    $attrs_dropbar['class'][] = 'uk-dropbar uk-dropbar-large';
    $attrs_dropbar['class'][] = $config("$dialog.dropbar.padding_remove_horizontal") ? 'uk-padding-remove-horizontal' : '';
    $attrs_dropbar['class'][] = $config("$dialog.dropbar.padding_remove_vertical") ? 'uk-padding-remove-vertical' : '';

    if (!$config("$dialog.dropbar.animation") || $config("$dialog.dropbar.animation") == 'reveal-top') {
        $attrs_dropbar['class'][] = 'uk-dropbar-top';
    } elseif ($config("$dialog.dropbar.animation") == 'slide-left') {
        $attrs_dropbar['class'][] = 'uk-dropbar-left';
        $attrs_dropbar['class'][] = $config("$dialog.dropbar.width") ? "uk-width-{$config("$dialog.dropbar.width")}" : '';
    }
    elseif ($config("$dialog.dropbar.animation") == 'slide-right') {
        $attrs_dropbar['class'][] = 'uk-dropbar-right';
        $attrs_dropbar['class'][] = $config("$dialog.dropbar.width") ? "uk-width-{$config("$dialog.dropbar.width")}" : '';
    }

    // If no navbar present
    $container = str_starts_with($layout, 'stacked') && !is_active_sidebar('navbar') ? '.tm-headerbar' : '.uk-navbar-container';

    $attrs_dropbar['uk-drop'] = [
        // Default
        'clsDrop' => 'uk-dropbar',
        'flip' => 'false', // Has to be a string
        'container' => $sticky ? '.tm-header > [uk-sticky]' : '.tm-header',
        'target-y' => ".tm-header {$container}",
        // New
        'mode' => 'click',
        'target-x' => ".tm-header {$container}",
        'boundary-x' => $config("$site.layout") == 'boxed' && !$config("$site.boxed.header_outside") ? ".tm-header {$container}" : null,
        'stretch' => in_array($config("$dialog.dropbar.animation"), ['slide-left', 'slide-right']) && $config("$dialog.dropbar.width") ? 'y' : true,
        'pos' => $config("$dialog.dropbar.animation") == 'slide-right' ? "bottom-right" : "bottom-left",
        'bgScroll' => 'false', // Has to be a string
        'animation' => $config("$dialog.dropbar.animation") ?: null,
        'animateOut' => true,
        'duration' => 300,
        'toggle' => 'false', // Has to be a string
    ];

    // Behind navbar
    if ($config("$header.transparent")) {
        $attrs_dropbar['uk-drop']['inset'] = true;
        $attrs_dropbar['class'][] = 'uk-dropbar-inset';
        $attrs_dropbar['uk-drop']['pos'] = $config("$dialog.dropbar.animation") == 'slide-right' ? "top-right" : "top-left";

        if ($config("$header.blend")) {
            $attrs_dropbar['uk-drop']['container'] = $outside ? '.tm-page-container' : '.tm-page';
        }
        // Set same z-index as dropnav (high but behind navbar, which is set to high). Needed in two cases: 1. blend and 2. not sticky and outside
        $attrs_dropbar['style'][] = 'z-index: 980;';
    }

    $attrs_dropbar['uk-drop'] = json_encode(array_filter($attrs_dropbar['uk-drop']));

    $attrs_dropbar_content = [];
    $attrs_dropbar_content['class'][] = 'tm-height-min-1-1 uk-flex uk-flex-column';
    $attrs_dropbar_content['class'][] = $config("$dialog.dropbar.content_width") ? 'uk-' .  $config("$dialog.dropbar.content_width") . ' uk-margin-auto' : '';
    $attrs_dropbar_content['class'][] = $config("$dialog.dropbar.content_width") == 'container' ? 'uk-padding-remove-horizontal' : '';

}

?>

<?php if (is_active_sidebar('dialog') || is_active_sidebar('dialog-push')) : ?>

    <?php if (str_starts_with($config("$dialog.layout"), 'offcanvas')) : ?>
    <div id="tm-dialog" uk-offcanvas="container: true"<?= $this->attrs($config("$dialog.offcanvas") ?: []) ?>>
        <div class="uk-offcanvas-bar uk-flex uk-flex-column">

            <button class="uk-offcanvas-close uk-close-large" type="button" uk-close uk-toggle="cls: uk-close-large; mode: media; media: @s"></button>

            <?php if ((is_active_sidebar('dialog'))) : ?>
            <div<?= $this->attrs($attrs_dialog) ?>>
                <?php dynamic_sidebar("dialog:grid-stack") ?>
            </div>
            <?php endif ?>

            <?php if (is_active_sidebar('dialog-push')) : ?>
            <div<?= $this->attrs($attrs_dialog_push) ?>>
                <?php dynamic_sidebar("dialog-push:grid-stack") ?>
            </div>
            <?php endif ?>

        </div>
    </div>
    <?php endif ?>

    <?php if (str_starts_with($config("$dialog.layout"), 'modal')) : ?>
    <div id="tm-dialog" class="uk-modal uk-modal-full" uk-modal>
        <div class="uk-modal-dialog uk-flex">

            <button class="uk-modal-close-full uk-close-large" type="button" uk-close uk-toggle="cls: uk-close-large; mode: media; media: @s"></button>

            <div<?= $this->attrs($attrs_modal) ?>>

                <?php if ((is_active_sidebar('dialog'))) : ?>
                <div<?= $this->attrs($attrs_dialog) ?>>
                    <?php dynamic_sidebar("dialog:grid-stack") ?>
                </div>
                <?php endif ?>

                <?php if (is_active_sidebar('dialog-push')) : ?>
                <div<?= $this->attrs($attrs_dialog_push) ?>>
                    <?php dynamic_sidebar("dialog-push:grid-stack") ?>
                </div>
                <?php endif ?>

            </div>

        </div>
    </div>
    <?php endif ?>

    <?php if (str_starts_with($config("$dialog.layout"), 'dropbar')) : ?>
    <div id="tm-dialog"<?= $this->attrs($attrs_dropbar) ?>>

        <div<?= $this->attrs($attrs_dropbar_content) ?>>

            <?php if ($config("$header.transparent")) : ?>
            <div uk-height-placeholder=".tm-header .uk-navbar-container"></div>
            <?php endif ?>

            <?php if ((is_active_sidebar('dialog'))) : ?>
            <div<?= $this->attrs($attrs_dialog) ?>>
                <?php dynamic_sidebar("dialog:grid-stack") ?>
            </div>
            <?php endif ?>

            <?php if (is_active_sidebar('dialog-push')) : ?>
            <div<?= $this->attrs($attrs_dialog_push) ?>>
                <?php dynamic_sidebar("dialog-push:grid-stack") ?>
            </div>
            <?php endif ?>

        </div>

    </div>
    <?php endif ?>

<?php endif ?>

<?php endif ?>

</header>

<?php endif ?>
