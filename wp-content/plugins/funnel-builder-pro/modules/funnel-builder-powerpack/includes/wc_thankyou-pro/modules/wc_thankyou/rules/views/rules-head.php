<?php
global $wfty_is_rules_saved;
$funnel_id = WFTY_Rules::get_instance()->get_thankyou_id();
?>
<div class="wfty_wrap_r wfty_table wfty_rules_container wfty_wrap_inner_rules <?php echo ( "yes" === $wfty_is_rules_saved ) ? '' : 'wfty-tgl'; ?>" id="wfty_funnel_rule_settings" data-is_rules_saved="<?php echo ( "yes" === $wfty_is_rules_saved ) ? "yes" : "no"; ?>">
    <div class="wfty_fsetting_table_head">
        <div class="wfty_fsetting_table_head_in wfty_clearfix">
            <div class="wfty_fsetting_table_title "><?php esc_html_e( 'Rules to trigger the thank you page', 'funnel-builder-powerpack' ); ?></div>
            <div class="wfty_form_submit ">
                <span class="wfty_save_funnel_rules_ajax_loader spinner"></span>
                <button class="wfty_save_btn_style wfty_save_funnel_rules button button-primary">
					<?php if ( 'yes' === $wfty_is_rules_saved ) {
						esc_html_e( 'Save changes', 'funnel-builder-powerpack' );
					} else {
						esc_html_e( 'Save changes', 'funnel-builder-powerpack' );
					} ?>
                </button>
            </div>
        </div>
    </div>
    <form class="wfty_rules_form" data-wfoaction="update_rules" method="POST">
        <input type="hidden" name="ty_id" value="<?php echo esc_attr( $funnel_id ); ?>">

