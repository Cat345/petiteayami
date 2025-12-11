<?php
$custom_page       = array(
	'name'      => __( 'Custom Page', 'woofunnels-upstroke-one-click-upsell' ),
	'thumbnail' => WFOCU_PLUGIN_URL . '/admin/assets/img/thumbnail-link-to-custom.jpg',
	'type'      => 'link',
);
$custom_active_img = WFOCU_PLUGIN_URL . '/admin/assets/img/thumbnail-custom-page.jpg';

?>
<div class="wfocu_template_box" v-if="template_group==`custom`" v-bind:class="current_template==`custom-page`?` wfocu_temp_box_custom wfocu_template_box_single  wfocu_selected_template`:`wfocu_empty_template wfocu_template_box wfocu_temp_box_custom wfocu_template_box_single`  " data-slug="custom-page">
	<div class="wfocu_template_box_inner">
		<div class="wfocu_template_img_cover">
			<div class="wfocu_template_thumbnail">
				<div class="wfocu_img_thumbnail" data-izimodal-open="#modal-prev-template_custom-page" data-izimodal-transitionin="fadeInUp">
					<div class="wfocu_overlay"></div>
					<div class="wfocu_vertical_mid">
						<div class="wfocu_add_tmp_se">
							<a href="javascript:void(0)" class="wfacp_steps_btn_add">
								<svg viewBox="0 0 24 24" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect fill="white"></rect><path d="M12 2C6.48566 2 2 6.48566 2 12C2 17.5143 6.48566 22 12 22C17.5143 22 22 17.5136 22 12C22 6.48645 17.5143 2 12 2ZM12 20.4508C7.34082 20.4508 3.54918 16.66 3.54918 12C3.54918 7.34004 7.34082 3.54918 12 3.54918C16.6592 3.54918 20.4508 7.34004 20.4508 12C20.4508 16.66 16.66 20.4508 12 20.4508Z" fill="#000000"></path><path d="M15.873 11.1557H12.7746V8.05734C12.7746 7.62976 12.4284 7.28273 12 7.28273C11.5716 7.28273 11.2254 7.62976 11.2254 8.05734V11.1557H8.12703C7.69867 11.1557 7.35242 11.5027 7.35242 11.9303C7.35242 12.3579 7.69867 12.7049 8.12703 12.7049H11.2254V15.8033C11.2254 16.2309 11.5716 16.5779 12 16.5779C12.4284 16.5779 12.7746 16.2309 12.7746 15.8033V12.7049H15.873C16.3013 12.7049 16.6476 12.3579 16.6476 11.9303C16.6476 11.5027 16.3013 11.1557 15.873 11.1557Z" fill="#000000"></path></svg>
							</a>
						</div>
						<div class="wfocu_p"><?php esc_html_e( 'Link to custom page', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
						<!-- <div class="wfocu_import_description"><?php _e( 'Create your funnel from scratch.' ) ?></div> -->
					</div>

				</div>
			</div>
		</div>
	</div>
</div>
<?php include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/modal-search-page.php'; ?>
