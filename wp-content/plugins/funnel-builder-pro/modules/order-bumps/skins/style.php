<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( apply_filters( 'wfacp_disabled_order_bump_css_printing', false, $this ) ) {
	return;
}
$design_data = $this->get_design_data();


$globalSetting = WFOB_Common::get_global_setting();
if ( isset( $globalSetting['css'] ) && $globalSetting['css'] != '' ) {
	echo "<style>" . $globalSetting['css'] . "</style>";
}

do_action( 'wfob_layout_style' );
