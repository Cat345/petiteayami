<?php
/**
 * Order Bumps Import Page
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>
<div class="">
    <div class="postbox">
        <div class="inside">

            <div class="wfob_flex_import_page">
				<?php if ( false === WFOB_Core()->import->is_imported ) { ?>
                    <div class="wfob_import_head"><?php esc_html_e( 'Import Order Bumps from a JSON file', '' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
                    <div class="wfob_import_para"><?php esc_html_e( 'This tool allows you to import the Order Bumps from the JSON file.', '' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
                    <form method="POST" enctype="multipart/form-data">
                        <p>
                            <input type="file" name="file">
                            <input type="hidden" name="wfob-action" value="import">
                        </p>
                        <div class="wf_funnel_clear_10"></div>
                        <p style="margin-bottom:0">
                            <input type="hidden" id="wfob-action" name="wfob-action-nonce" value="<?php echo wp_create_nonce( 'wfob-action-nonce' ); ?>">
                            <input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="Upload Exported File"></p>
                    </form>
				<?php } else { ?>
                    <div class="wfob_import_head"><?php esc_html_e( 'Import Success', '' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
                    <div class="wfob_import_para"><?php esc_html_e( 'Order Bumps have been imported successfully.', '' ); ?></div>
                    <div class="wf_funnel_clear_10"></div>
					<?php $wfob_url = add_query_arg( array( 'page' => 'wfob' ), admin_url( 'admin.php' ) ); ?>
                    <a href="<?php echo esc_url( $wfob_url ); ?>" class="wf_funnel_btn wf_funnel_btn_primary"><?php esc_html_e( 'Go to Order Bumps', '' ); ?></a>
				<?php } ?>
            </div>

        </div>
    </div>
</div>
