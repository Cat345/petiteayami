<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<style>
    .wfob_wrap_l {
        background: #fff;
    }
</style>
<style [data-type='wfob' ]></style>
<?php
$product = WFOB_Common::get_bump_products( WFOB_Common::get_id() );
?>
<div class="wfob_section_setting" id="wfob_bump_setting">
	<div class="wfob-product-tabs-view-vertical wfob-product-widget-tabs">
		<div class="wfob-product-tabs-wrapper wfob-tab-center">
			<div class="wfob-tab-title wfob-tab-desktop-title wfob-active" data-tab="1" role="tab">
				<?php echo __( 'Priority', 'woofunnels-order-bump' ); ?>
			</div>

			<?php do_action('wfob_bump_settings_tabs'); ?>
		</div>
		<div class="wfob-product-widget-container">
			<div class="wfob-product-tabs wfob-tabs-style-line" role="tablist">
				<div class="wfob-product-tabs-content-wrapper">
					<div class="wfob_global_setting_inner" >
						<form class="wfob_forms_wrap wfob_forms_global_settings " data-wfoaction="global_settings" v-on:submit.prevent="save">
							<vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
							<fieldset>
								<div class="wfob_form_submit" style="display: inline-block">
									<input type="submit" style="float: left" class="wfob_btn wfob_btn_primary" value="<?php _e( 'Save Changes', 'woofunnels-aero-checkout' ); ?>"/>
									<span class="wfob_spinner spinner" ></span>
								</div>
							</fieldset>
						</form>
						<div class="wfob_success_modal" style="display: none" id="modal-saved-data-success" data-iziModal-icon="icon-home"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="wfob_clear"></div>
</div>
