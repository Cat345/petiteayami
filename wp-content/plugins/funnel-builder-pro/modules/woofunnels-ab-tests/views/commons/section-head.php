<?php
$exp_state = __( 'Draft', 'woofunnels-ab-tests' );
$btn_text  = __( 'Start', 'woofunnels-ab-tests' );
$btnClass  = 'wfabt_btn_primary';
if ( ( $experiment->is_started() && false === $experiment->is_paused() && false === $experiment->is_completed() ) ) {
	$btn_text  = __( 'Pause', 'woofunnels-ab-tests' );
	$exp_state = __( 'Running', 'woofunnels-ab-tests' );
	$btnClass  = 'wfabt_btn_grey wfabt_start_t';
} elseif ( $experiment->is_completed() ) {
	$btn_text  = __( 'Completed', 'woofunnels-ab-tests' );
	$exp_state = $btn_text;
} elseif ( $experiment->is_paused() ) {
	$btn_text  = __( 'Resume', 'woofunnels-ab-tests' );
	$exp_state = __( 'Paused', 'woofunnels-ab-tests' );
	$btnClass  = 'wfabt_btn_success';
}
include_once( __DIR__ . '/section-breacrumb.php' );
BWFABT_Core()->admin->get_tabs_html( $experiment->get_id() );
