<?php
/**
 * Order Bumps Import Page
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>
<div class="wfacp_import">
    <div class="postbox">
        <div class="inside">

            <div class="wfacp_flex_import_page">
				<?php if ( false === WFACP_Core()->import->is_imported ) { ?>
                    <div class="wfacp_import_head"><?php esc_html_e( 'Import FunnelKit Checkout Pages from a JSON file', 'woofunnels-aero-checkout' ); ?></div>
                    <div class="wf_funnel_clear_20"></div>
					<div class="wfacp_import_para"><?php echo wp_kses_post( __('Note: Designs made using page builders needs to be imported separately.', 'woofunnels-aero-checkout' )); ?> </div>
                   <div class="wf_funnel_clear_10"></div>
                    <form method="POST" enctype="multipart/form-data">
                        <p>
                            <input type="file" name="file">
                            <input type="hidden" name="wfacp-action" value="import">
                        </p>
                        <div class="wf_funnel_clear_10"></div>
                        <p style="margin-bottom:0">
                            <input type="hidden" id="wfacp-action" name="wfacp-action-nonce" value="<?php echo wp_create_nonce( 'wfacp-action-nonce' ); ?>">
                            <input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="Upload Exported File"></p>
                    </form>
				<?php } else { ?>
                    <div class="wfacp_import_head"><?php esc_html_e( 'Import Success', '' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
                    <div class="wfacp_import_para"><?php esc_html_e( 'FunnelKit Checkout page(s) have been imported successfully.', 'woofunnels-aero-checkout' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
					<?php $wfacp_url = add_query_arg( array( 'page' => 'wfacp' ), admin_url( 'admin.php' ) ); ?>
                    <a href="<?php echo esc_url( $wfacp_url ) ?>" class="wf_funnel_btn wf_funnel_btn_primary"><?php esc_html_e( 'Go to FunnelKit Checkout Pages', 'woofunnels-aero-checkout' ); ?></a>
				<?php } ?>
            </div>

        </div>
    </div>
</div>
