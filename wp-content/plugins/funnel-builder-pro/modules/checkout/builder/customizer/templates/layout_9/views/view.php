<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$checkout = WC()->checkout();

/**
 * @var $this WFACP_template_layout9
 */

$header_layout_is = $this->get_temaplete_header_layout();
$numOfSteps       = $this->get_step_count();

$fullWidthCls = 'full_width_bar';
if ( $numOfSteps > 1 ) {
	$fullWidthCls = 'multistep_bar';
}
$header_footer_status = apply_filters( 'wfacp_disabled_pre_built_header_footer', false, $this );

if ( false == $header_footer_status ) {
	include __DIR__ . '/header.php';
} else {
	$this->get_theme_header();
}

include $this->template_dir . '/views/container.php';

if ( false == $header_footer_status ) {
	include __DIR__ . '/footer.php';
} else {
	$this->get_theme_footer();
}

?>
