<?php
/**
 * Order Bumps Import Page
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
?>
<div class="">
	<div class="postbox">
		<div class="inside">

			<div class="wfocu_flex_import_page">
				<?php if ( false === WFOCU_Core()->import->is_imported ) { ?>
					<div class="wfocu_import_head"><?php esc_html_e( 'Import Upsell from a JSON file', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
					<div class="wf_funnel_clear_10"></div>
					<div class="wfocu_import_para"><?php echo wp_kses_post( __( 'Note: Designs made using page builders needs to be imported separately.', 'woofunnels-upstroke-one-click-upsell' ) ); ?> </div>
					<div class="wf_funnel_clear_10"></div>
					<form method="POST" enctype="multipart/form-data">
						<p>
							<input type="file" name="file">
							<input type="hidden" name="wfocu-action" value="import">
						</p>
						<div class="wf_funnel_clear_10"></div>
						<p style="margin-bottom:0">
							<input type="hidden" id="wfocu-action" name="wfocu-action-nonce" value="<?php echo wp_create_nonce( 'wfocu-action-nonce' ); ?>">
							<input type="submit" name="submit" class="wf_funnel_btn wf_funnel_btn_primary" value="Upload Exported File"></p>
					</form>
				<?php } else { ?>
					<div class="wfocu_import_head"><?php esc_html_e( 'Import Success', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
					<div class="wf_funnel_clear_10"></div>
					<div class="wfocu_import_para"><?php esc_html_e( 'Upsell(s) are imported successfully.', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
					<div class="wf_funnel_clear_10"></div>
					<?php $target_url = add_query_arg( array( 'page' => 'upstroke' ), admin_url( 'admin.php' ) ); ?>
					<a href="<?php echo esc_url( $target_url ); ?>" class="wf_funnel_btn wf_funnel_btn_primary"><?php esc_html_e( 'Go to Upsells', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
				<?php } ?>
			</div>

		</div>
	</div>
</div>
