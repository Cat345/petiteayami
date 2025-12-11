<?php
$bump_id = WFOB_Common::get_id();
global $wfob_is_rules_saved;
?>
<style>
    .wfob_wrap_r {
        width: 100%;
    }
</style>

<div class="wfob_table wfob_rules_container <?php echo ( 'yes' == $wfob_is_rules_saved ) ? '' : 'wfob-tgl'; ?>" id="wfob_bump_rule_settings" data-is_rules_saved="<?php echo ( 'yes' == $wfob_is_rules_saved ) ? 'yes' : 'no'; ?>">
    <form class="wfob_rules_form" data-wfoaction="update_rules" method="POST">
        <div class="wfob_fsetting_table_head">
            <div class="wfob_fsetting_table_head_in">
                <div class="wfob_fsetting_table_title "><?php echo __( '<strong>Rules to trigger the order bump</strong>', 'woo-bump-one-click-upsell' ); ?></div>
                <div class="wfob_form_submit ">
                    <button type="submit" class="wfob_btn wfob_btn_primary wfob_save_bump_rules"> <?php _e( 'Save Changes', 'woofunnels-order-bump' )  ?></button>
                </div>
            </div>
        </div>


        <input type="hidden" name="wfob_id" value="<?php echo $bump_id; ?>">

