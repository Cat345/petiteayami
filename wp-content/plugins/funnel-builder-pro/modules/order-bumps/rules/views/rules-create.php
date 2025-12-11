<?php
global $wfob_is_rules_saved;
?>
<div id="wfob_bump_rule_add_settings" data-is_rules_saved="<?php echo ( 'yes' === $wfob_is_rules_saved ) ? 'yes' : 'no'; ?>">
    <div class="wfob_welcome_wrap">

        <div class="bwf-zero-state">
            <div class="bwf-zero-state-wrap">
                <div class="bwf-zero-sec bwf-zero-sec-icon bwf-pb-gap">
                    <img src="<?php echo esc_url( plugin_dir_url( WFOB_PLUGIN_FILE ) . 'admin/assets/img/zero-state/rule.svg'); ?>"/>
                </div>
                <div class="bwf-zero-sec bwf-zero-sec-content bwf-h2 bwf-pb-10">
                    <div><?php _e( 'Add rules to show conditional order bump page', 'woofunnels-order-bump' ); ?></div>
                </div>
                <div class="bwf-zero-sec bwf-zero-sec-content bwf-pb-gap">
                    <div class="bwf-h4-1"><?php _e( 'Want to show different order bump pages based on buyer purchase?<br>
Use this section to add rules.', 'woofunnels-order-bump' ); ?></div>
                </div>
                <div class="bwf-zero-sec bwf-zero-sec-buttons">
                    <button type="button" class="wfob_btn wfob_btn_primary wfob_bump_rule_add_settings">
                            <?php esc_html_e( 'Add Rules', 'woofunnels-order-bump' ); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
