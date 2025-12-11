<?php
/**
 * Order-Bumps Export View
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>


<div class="">
    <div class="postbox">
        <div class="inside">
            <div class="wfob_flex_import_page wfob_flex_export_page">
                <div class="wfob_import_head">Export All Order Bumps</div>
                <div class="wf_funnel_clear_10"></div>
                <div class="wfob_import_para">Download a JSON file containing a list of all Order Bumps.</div>
                <div class="wf_funnel_clear_10"></div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="wfob-action" value="export">
                    <div class="wf_funnel_clear_10"></div>
                    <p style="margin-bottom:0">
                        <input type="hidden" id="wfob-action" name="wfob-action-nonce" value="<?php echo wp_create_nonce( 'wfob-action-nonce' ); ?>">
                        <input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="Download Export File"></p>
                </form>
            </div>
        </div>
    </div>
</div>
