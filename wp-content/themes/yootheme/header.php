<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <body>
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 */

namespace YOOtheme;

/**
 * @var Config $config
 * @var View   $view
 */
[$config, $view] = app(Config::class, View::class);

// Config
$site = '~theme.site';

// Set view
$layout = $config('~theme.page_layout', '');

// Page
$attrs_page = [];
$attrs_page_container = [];
$attrs_image = [];
$attrs_media_overlay = [];
$attrs_page['class'][] = 'tm-page';

if ($config("$site.layout") == 'boxed') {

    $attrs_page['class'][] = $config("$site.boxed.alignment") ? 'uk-margin-auto' : '';
    $attrs_page['class'][] = $config("$site.boxed.margin_top") ? 'tm-page-margin-top' : '';
    $attrs_page['class'][] = $config("$site.boxed.margin_bottom") ? 'tm-page-margin-bottom' : '';
    $attrs_page_container['class'][] = 'tm-page-container uk-clearfix';

    // Image
    if ($config("$site.boxed.media")) {

        $attrs_image = $view->bgImage($config("$site.boxed.media"), [
            'width' => $config("$site.image_width"),
            'height' => $config("$site.image_height"),
            'focal_point' => $config("$site.image_focal_point"),
            'size' => $config("$site.image_size"),
            'position' => $config("$site.image_position"),
            'visibility' => $config("$site.image_visibility"),
            'blend_mode' => $config("$site.media_blend_mode"),
            'background' => $config("$site.media_background"),
            'effect' => $config("$site.image_effect") !== 'fixed' ? $config("$site.image_effect") : '',
            'parallax_bgx' => $config("$site.image_parallax_bgx"),
            'parallax_bgy' => $config("$site.image_parallax_bgy"),
            'parallax_easing' => $config("$site.image_parallax_easing"),
            'parallax_breakpoint' => $config("$site.image_parallax_breakpoint"),
            'parallax_target' => 'body',
            'loading' => 'eager'
        ]);

        if ($config("$site.image_effect")) {
            $attrs_image['class'][] = 'uk-position-cover uk-position-fixed';
        } else {
            $attrs_page_container = array_merge_recursive($attrs_page_container, $attrs_image);
            $attrs_image = [];
        }

        // Overlay
        if ($config("$site.media_overlay")) {
            $attrs_page_container['class'][] = 'uk-position-relative';
            $attrs_media_overlay['class'][] = 'uk-position-cover';
            $attrs_media_overlay['style'] = "background-color: {$config("$site.media_overlay")};";
        }

        // Navbar Color
        if ($config("$site.boxed.header_outside") && ($config("$site.boxed.header_transparent") || $config("~theme.header.transparent")) && $config("$site.boxed.header_text_color")) {
            $attrs_page_container['class'][] = "uk-inverse-{$config("$site.boxed.header_text_color")}";
        }

    }

}

// Main section
$attrs_main_section = [];
$attrs_main_section['class'][] = 'tm-main uk-section uk-section-default';
$attrs_main_section['class'][] = $layout == 'blog' && $config('~theme.blog.padding') ? "uk-section-{$config('~theme.blog.padding')}" : '';
$attrs_main_section['class'][] = $layout == 'post' && $config('~theme.post.padding') ? "uk-section-{$config('~theme.post.padding')}" : '';
$attrs_main_section['class'][] = $layout == 'post' && $config('~theme.post.padding_remove') ? 'uk-padding-remove-top' : '';
$attrs_main_section['uk-height-viewport'] = $config("$site.main_section.height") ? 'expand: true' : false;

// Main container
$attrs_main_container = [];

if ($layout == 'post') {
    if ($config('~theme.post.width')) {
        $attrs_main_container['class'][] = $config('~theme.post.width') == 'default' ? 'uk-container' : "uk-container uk-container-{$config('~theme.post.width')}";
    }
} elseif ($layout == 'blog') {
    if ($config('~theme.blog.width')) {
        $attrs_main_container['class'][] = $config('~theme.blog.width') == 'default' ? 'uk-container' : "uk-container uk-container-{$config('~theme.blog.width')}";
    }
} else {
    $attrs_main_container['class'][] = 'uk-container';
}

?>
<!DOCTYPE html>
<html <?php language_attributes() ?>>
    <head>
        <meta charset="<?php bloginfo('charset') ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="<?= $config('~theme.favicon') ?>" sizes="any">
        <?php if ($config('~theme.favicon_svg')) : ?>
        <link rel="icon" href="<?= $config('~theme.favicon_svg') ?>" type="image/svg+xml">
        <?php endif ?>
        <link rel="apple-touch-icon" href="<?= $config('~theme.touchicon') ?>">
        <?php if (is_singular() && pings_open(get_queried_object())) : ?>
        <link rel="pingback" href="<?php bloginfo('pingback_url') ?>">
        <?php endif ?>
        <?php wp_head() ?>
    </head>
    <body <?php body_class((array) $config('~theme.body_class')) ?>>

        <?php
            wp_body_open();

            // Force top position to be evaluated before header
            ob_start();
            dynamic_sidebar("top:section");
            $top = ob_get_clean();
        ?>

        <div class="uk-hidden-visually uk-notification uk-notification-top-left uk-width-auto">
            <div class="uk-notification-message">
                <a href="#tm-main" class="uk-link-reset"><?= __('Skip to main content', 'yootheme') ?></a>
            </div>
        </div>

        <?php if ($config("$site.layout") == 'boxed') : ?>
        <div<?= $view->attrs($attrs_page_container) ?>>

            <?php if ($attrs_image) : ?>
            <div<?= $view->attrs($attrs_image) ?>></div>
            <?php endif ?>

            <?php if ($attrs_media_overlay) : ?>
            <div <?= $view->attrs($attrs_media_overlay) ?>></div>
            <?php endif ?>

        <?php endif ?>

        <?php if ($config("$site.layout") == 'boxed' && $config("$site.boxed.header_outside")) : ?>
        <?= $view('~theme/templates/header') ?>
        <?php endif ?>

        <div<?= $view->attrs($attrs_page) ?>>

            <?php if (!($config("$site.layout") == 'boxed' && $config("$site.boxed.header_outside"))) : ?>
            <?= $view('~theme/templates/header') ?>
            <?php endif ?>

            <?= $top ?>

            <main id="tm-main" <?= !$config('app.isBuilder') ? $view->attrs($attrs_main_section) : '' ?>>

                <?php if (!$config('app.isBuilder')) : ?>
                <div<?= $view->attrs($attrs_main_container) ?>>

                    <?php if (is_active_sidebar('sidebar')) :
                        $sidebar = '~theme.main_sidebar';
                        $grid = ['uk-grid'];
                        $grid[] = $config("$sidebar.gutter") ? "uk-grid-{$config("$sidebar.gutter")}" : '';
                        $grid[] = $config("$sidebar.divider") ? 'uk-grid-divider' : '';
                    ?>

                    <div<?= $view->attrs(['class' => $grid, 'uk-grid' => true]) ?>>
                        <div class="uk-width-expand@<?= $config("$sidebar.breakpoint") ?>">

                    <?php endif ?>

                            <?php if ($config("$site.breadcrumbs")) : ?>
                                <?= $view->section('breadcrumbs') ?>
                            <?php endif ?>

                <?php endif ?>
