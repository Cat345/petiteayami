<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$checkout = WC()->checkout();


/**
 * @var $this WFACP_template_layout1
 */
$page_meta_title        = WFACP_Common::get_option( 'wfacp_header_section_page_meta_title' );
$selected_template_slug = $this->get_template_slug();
$header_footer_status   = apply_filters( 'wfacp_disabled_pre_built_header_footer', false, $this );

if ( false == $header_footer_status ) {
	include __DIR__ . '/header.php';
} else {
	$this->get_theme_header();
}
include __DIR__ . '/container.php';
if ( false == $header_footer_status ) {
	include __DIR__ . '/footer.php';
} else {
	$this->get_theme_footer();
}
?>