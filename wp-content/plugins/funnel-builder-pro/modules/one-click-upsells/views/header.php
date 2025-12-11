<?php $page_meta_title = WFOCU_Common::get_option( WFOCU_SLUG . '_header_top_page_meta_title' ); ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex,nofollow"/>
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php wp_site_icon(); ?>
    <title><?php echo $page_meta_title ? $page_meta_title : bloginfo( 'name' ) ?></title>
	<?php
	WFOCU_Core()->assets->print_styles( true );
	WFOCU_Core()->assets->print_scripts( true );
	if ( true === apply_filters( 'wfocu_allow_externals_on_customizer', false ) ) {
		wp_head();
	}
	do_action( 'header_print_in_head' );
	do_action( 'wfocu_header_print_in_head' ); ?>
</head>
<?php do_action( 'wfocu_view_before_body_start' ); ?>
<body class="<?php echo WFOCU_Core()->template_loader->body_classes(); ?>">
<?php do_action( 'wfocu_view_after_body_start' ); ?>
