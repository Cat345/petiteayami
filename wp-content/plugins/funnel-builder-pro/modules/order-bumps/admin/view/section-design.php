<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
wp_enqueue_media();
?>
<style>
    .wfob_layout_generator_form {
        display: none;
    }

    .wfob_layout_generator_form fieldset:first-child {
        margin-top: -35px;
    }

</style>
<style [data-type='wfob' ]></style>
<?php
$product = WFOB_Common::get_bump_products( WFOB_Common::get_id() );
?>
<div class="wfob_design_setting" id="wfob_design_setting">
    <div class="wfob_design_setting_inner">
        <div class="wfob_content_wrap wfob_sec_wrap_h wfob_sec_display_flex">
			<?php if ( count( $product ) > 0 ) { ?>
                <div class="wfob_design_header">
                    <div class="wfob-design-modal-header">
                        <h2 class="wfob-style-modal-title"><?php _e( 'Edit Style', 'woofunnels-order-bump' ) ?></h2>
                        <div class="wfob_form_submit">
                            <button class="wfob_btn wfob_btn_primary" v-on:click="save()"><?php _e( 'Save', 'woofunnels-order-bump' ) ?></button>
                        </div>
                        <button class="wfob_close_style_modal" data-tab="1" id="wfob_close_style_modal"></button>
                    </div>
					<?php ?>
                </div>
                <div class="wfob-product-tabs-view-horizontal wfob-product-widget-tabs" id="wfob_design_content_settings">
                    <div class="wfob-product-widget-container">
                        <div class="wfob-product-tabs wfob-tabs-style-line" role="tablist">
                            <div class="wfob-product-tabs-content-wrapper">
                                <div class="wfob_wrap_l">
                                    <div class="wfob_global_setting_inner">
                                        <div class="wfob_global_container" id="wfob_global_settings">
                                            <form id="modal-global-settings-form" class="wfob_forms_global_settings" data-wfoaction="global_settings" v-on:submit.prevent="save" @change="onchange">
                                                <div class="wfob_vue_forms">
                                                    <div class="wfob_design_generator">
														<?php
														$default_layouts = [ 'layout_1', 'layout_2', 'layout_3', 'layout_4', 'layout_5','layout_6' ];
														foreach ( $default_layouts as $layout_key ) {
															?>
                                                            <vue-form-generator :schema="schema['<?php echo $layout_key ?>']" :model="model" class="wfob_layout_generator_form" data-layout="<?php echo $layout_key ?>"></vue-form-generator>
															<?php
														}
														?>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="wfob_wrap_r wfob_sticky_sidebar">
									<?php
									WFOB_Core()->admin->get_bump_layout();
									?>
                                    <div class="wfob-skin-actions setting_save_buttons wfob_form_submit">
                                        <button class="wfob_btn wfob_btn_secondary" data-izimodal-open="#modal_edit_skin"><?php esc_html_e( 'Choose Skin', 'woofunnels-order-bump' ); ?></button>
                                        <button class="wfob_btn wfob_btn_secondary" id="wfob-skin-style-btn" data-tab="3" v-on:click="edit_style()"><?php esc_html_e( 'Edit Style', 'woofunnels-order-bump' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wfob_design_save wfob_form_submit">
                            <span class="wfob_spinner spinner"></span>
                            <button class="wfob_btn wfob_btn_primary" v-on:click="save()"><?php _e( 'Save', 'woofunnels-order-bump' ); ?></button>
                        </div>
                    </div>
                </div>
				<?php
			} else {
				$current_bump_id = $this->get_bump_id();
				$section_url     = add_query_arg( array(
					'page'    => 'wfob',
					'section' => 'products',
					'wfob_id' => $current_bump_id,
				), admin_url( 'admin.php' ) );
				?>
                <div class="wfob_wrap_l wfob_no_product_added">
                    <div class="wfob_welcome_wrap">
                        <div class="bwf-zero-state">
                            <div class="bwf-zero-state-wrap">
                                <div class="bwf-zero-sec bwf-zero-sec-icon bwf-pb-gap">
                                    <img src="<?php echo esc_url( WFOB_PLUGIN_URL ) ?>/admin/assets/img/zero-state/funnel.svg" alt="" title=""/>
                                </div>
                                <div class="bwf-zero-sec bwf-zero-sec-content bwf-h2 bwf-pb-10">
                                    <div><?php _e( 'Add a Product as order bump', 'woofunnels-order-bump' ); ?></div>
                                </div>
                                <div class="bwf-zero-sec bwf-zero-sec-content bwf-pb-gap">
                                    <div class="bwf-h4-1"><?php _e( 'Choose a complimentary product to increase the uptake rate', 'woofunnels-order-bump' ); ?></div>
                                </div>
                                <div class="bwf-zero-sec bwf-zero-sec-buttons">
                                    <a href="<?php echo $section_url; ?>" class="wfob_step wfob_button_add wfob_button_inline wfob_modal_open wfob_welc_btn">
										<?php _e( 'Go to Products', 'woofunnels-order-bump' ); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
			?>
        </div>
        <div class="wfob_confirm_style_snackbar">
            <span><?php esc_html_e( 'Want to save your changes ?' ) ?></span>
            <button class="wfob_btn wfob_btn_primary" id="wfob_save_style_snack" v-on:click="save()"><?php esc_html_e( 'Save' ) ?></button>
            <button class="wfob_btn wfob_btn_secondary" id="wfob_close_style_snack"><?php esc_html_e( 'Cancel' ) ?></button>
        </div>
		<?php include __DIR__ . '/design/skin-modal.php'; ?>
    </div>
</div>
<script>
	<?php
	if ( count( $product ) > 0 ) {
	foreach ( $product as $product_key => $data ) {
	?>
    Vue.component("field-wfob_media_box_<?php echo $product_key?>", {
        mixins: [VueFormGenerator.abstractField],
        props: ['model'],
        template: `<?php include __DIR__ . '/design/media-box.php'?>`
    });
	<?php
	}}
	?>
</script>