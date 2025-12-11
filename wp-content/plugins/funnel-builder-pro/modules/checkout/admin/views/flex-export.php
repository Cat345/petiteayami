<?php
/**
 * Order-Bumps Export View
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>


<div class="wfacp_export">
    <div class="postbox">
        <div class="inside">
            <div class="wfacp_flex_import_page wfacp_flex_export_page">
				<div class="wfacp_import_head"><?php esc_html_e( 'Export all FunnelKit Checkout Pages', 'woofunnels-aero-checkout' ); ?></div>
				<div class="wf_funnel_clear_10"></div>
				<div class="wfacp_import_para"><?php echo wp_kses_post( __('Note: Designs made using page builders needs to be exported separately.', 'woofunnels-aero-checkout' )); ?> </div>
				<div class="wf_funnel_clear_10"></div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="wfacp-action" value="export">
                    <div class="wf_funnel_clear_10"></div>
                    <p style="margin-bottom:0">
                        <input type="hidden" id="wfacp-action" name="wfacp-action-nonce" value="<?php echo wp_create_nonce( 'wfacp-action-nonce' ); ?>">
                        <input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="<?php echo _e( 'Download Export File', 'woofunnels-aero-checkout' ); ?>"></p>
                </form>
            </div>
        </div>
    </div>
</div>
