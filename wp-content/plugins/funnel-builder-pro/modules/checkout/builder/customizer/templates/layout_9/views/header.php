<?php

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$checkout = WC()->checkout();

/**
 * @var $this WFACP_template_layout9
 */

$page_meta_title            = WFACP_Common::get_option( 'wfacp_header_section_page_meta_title' );
$wfacp_shopcheckout_sidebar = 'wfacp_shopcheckout_sidebar_no';
if ( is_array( $this->active_sidebar() ) && count( $this->active_sidebar() ) > 0 ) {
	$wfacp_shopcheckout_sidebar = 'wfacp_shopcheckout_sidebar_yes';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $page_meta_title ? $page_meta_title : get_bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>

	<?php
	do_action( 'wfacp_header_print_in_head' );
	?>
</head>
<body class="<?php echo $this->get_class_from_body() ?> <?php echo $wfacp_shopcheckout_sidebar ?>">