<?php
/**
 * Adding analytics page
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>


<div class="">
	<div class="postbox">
		<div class="inside">
			<div class="wfocu_flex_import_page">
				<div class="wfocu_import_head"><?php esc_html_e( 'Export One Click Upsells to a JSON file', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
				<div class="wf_funnel_clear_10"></div>
				<div class="wfocu_import_para"><?php echo wp_kses_post( __( 'Note: Designs made using page builders needs to be exported separately.', 'woofunnels-upstroke-one-click-upsell' ) ); ?> </div>
				<div class="wf_funnel_clear_10"></div>
				<form method="POST" enctype="multipart/form-data">
					<input type="hidden" name="wfocu-action" value="export">
					<div class="wf_funnel_clear_10"></div>
					<p style="margin-bottom:0">
						<input type="hidden" id="wfocu-action" name="wfocu-action-nonce" value="<?php echo wp_create_nonce( 'wfocu-action-nonce' ); ?>">
						<input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="<?php echo _e( 'Download Export File', 'woofunnels-upstroke-one-click-upsell' ); ?> "></p>
				</form>
			</div>
		</div>
	</div>
</div>
