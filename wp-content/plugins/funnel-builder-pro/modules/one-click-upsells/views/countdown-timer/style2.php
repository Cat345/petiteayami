<?php
$current_offer_id = WFOCU_Core()->data->get_current_offer();
$text_above_timer = WFOCU_Common::get_option( 'wfocu_other_ctimer_text_above_timer' );
$ct_timer_hrs     = WFOCU_Common::get_option( 'wfocu_other_ctimer_timer_hours' );
$ct_timer_mins    = WFOCU_Common::get_option( 'wfocu_other_ctimer_timer_mins' );

$ct_label_hrs   = WFOCU_Common::get_option( 'wfocu_other_ctimer_label_hrs' );
$ct_label_mins  = WFOCU_Common::get_option( 'wfocu_other_ctimer_label_mins' );
$ct_label_secs  = WFOCU_Common::get_option( 'wfocu_other_ctimer_label_secs' );
$ct_show_labels = WFOCU_Common::get_option( 'wfocu_other_ctimer_show_labels' );

$ct_hide_hrs       = WFOCU_Common::get_option( 'wfocu_other_ctimer_hide_hrs' );
$ct_action_on_zero = WFOCU_Common::get_option( 'wfocu_other_ctimer_action_on_zero' );

$show_labels = $ct_show_labels === true ? 'wfocu-show-ctlabels' : 'wfocu-hide-ctlabels';

$show_hrs     = $ct_hide_hrs === true ? 'no' : 'yes';
$time_in_mins = array();
$time_in_secs = '';
if ( ! empty( $ct_timer_hrs ) ) {
	$time_in_mins[] = $ct_timer_hrs * 60;
}
if ( ! empty( $ct_timer_mins ) ) {
	$time_in_mins[] = $ct_timer_mins;
}
if ( is_array( $time_in_mins ) && count( $time_in_mins ) > 0 ) {
	$time_mins    = array_sum( $time_in_mins );
	$time_in_secs = $time_mins * 60;
}
?>
<div class="wfocu-countdown-timer-wrap">
    <div class="wfocu-countdown-timer wfocu-timer wfocu-countdown-highlight <?php echo $show_labels; ?>" data-is-hrs="<?php echo $show_hrs; ?>" data-hrs="<?php echo $ct_label_hrs; ?>" data-mins="<?php echo $ct_label_mins; ?>" data-secs="<?php echo $ct_label_secs; ?>" data-zero-action="<?php echo $ct_action_on_zero; ?>" data-offer-id="<?php echo $current_offer_id; ?>">
		<?php if ( ! empty( $text_above_timer ) ) { ?>
            <div class="wfocu-countdown-timer-text">
				<?php echo $text_above_timer; ?>
            </div>
		<?php } ?>
        <div class="wfocu-timer-wrap" data-time-left="<?php echo $time_in_secs; ?>" data-timer-skin="highlight">
        </div>
    </div>
</div>

