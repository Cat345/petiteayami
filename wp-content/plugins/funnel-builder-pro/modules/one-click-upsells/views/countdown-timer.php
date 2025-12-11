<?php
$template_ins = $this->get_template_ins();

$style = WFOCU_Common::get_option( 'wfocu_other_ctimer_skin' );

if ( ! empty( $template_ins->countdown_timer ) ) {
	switch ( $template_ins->countdown_timer ) {
		case 'square_fill':
			$style = 'style1';
			break;
		default:
			$style = 'style2';
			break;
	}
}

$timer_bg_color = WFOCU_Common::get_option( 'wfocu_other_ctimer_timer_bg_color' );

$ct_timer_text_color = WFOCU_Common::get_option( 'wfocu_other_ctimer_timer_text_color' );
$ct_timer_text_fs    = WFOCU_Common::get_option( 'wfocu_other_ctimer_timer_text_fs' );
$ct_digit_color      = WFOCU_Common::get_option( 'wfocu_other_ctimer_digit_color' );
$ct_digit_fs         = WFOCU_Common::get_option( 'wfocu_other_ctimer_digit_fs' );
$ct_label_color      = WFOCU_Common::get_option( 'wfocu_other_ctimer_label_color' );
$ct_label_fs         = WFOCU_Common::get_option( 'wfocu_other_ctimer_label_fs' );

$template_ins->internal_css['timer_bg_color']      = $timer_bg_color;
$template_ins->internal_css['ct_timer_text_color'] = $ct_timer_text_color;
$template_ins->internal_css['ct_timer_text_fs']    = $ct_timer_text_fs;
$template_ins->internal_css['ct_digit_color']      = $ct_digit_color;
$template_ins->internal_css['ct_digit_fs']         = $ct_digit_fs;
$template_ins->internal_css['ct_label_color']      = $ct_label_color;
$template_ins->internal_css['ct_label_fs']         = $ct_label_fs;
switch ( $style ) {
	case 'style1':
	case 'style2':
		WFOCU_Core()->template_loader->get_template_part( 'countdown-timer/' . $style );
		break;
}
