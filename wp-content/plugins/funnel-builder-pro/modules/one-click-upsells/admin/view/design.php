<?php
$allTemplates   = WFOCU_Core()->template_loader->get_templates();
$get_all_groups = WFOCU_Core()->template_loader->get_all_groups();
$offers         = WFOCU_Core()->funnels->get_funnel_offers_admin();



$data = get_option('_bwf_fb_templates');
if( !is_array($data) || count($data) === 0 ){ ?>
	<div class="empty_template_error">
		<div class="bwf-c-global-error" style="display: flex; align-items: center; justify-content: center; height: 60vh;">
			<div class="bwf-c-global-error-center" style="text-align: center; background-color: rgb(255, 255, 255); width: 500px; padding: 50px;">
				<span class="dashicon dashicons dashicons-warning" style="font-size: 70px; height: 70px; width: 70px;"></span>
				<p><?php esc_html_e( 'It seems there are some technical difficulties. Press F12 to open console. Take Screenshot of the error and send it to support.', 'woofunnels-upstroke-one-click-upsell' ) ?></p>
				<a herf="#" class="button button-primary is-primary"><span class="dashicon dashicons dashicons-image-rotate"></span>&nbsp;<?php esc_html_e( 'Refresh', 'woofunnels-upstroke-one-click-upsell' ) ?></a>
			</div>
		</div>
	</div>
<?php } else {
	 ?>
		<div class="wfocu_secondary_top_bar">
			<div class="wfocu_steps">
				<div class="wfocu_steps_sortable">
					<?php include __DIR__ . '/steps/offer-ladder.php'; ?>
				</div>
			</div>
		</div>
	<?php
	if ( false === is_array( $offers['steps'] ) || ( is_array( $offers['steps'] ) && count( $offers['steps'] ) < 1 ) ) {
		$funnel_id      = $offers['id'];
		$section_url    = add_query_arg( array(
			'page'    => 'upstroke',
			'section' => 'offers',
			'edit'    => $funnel_id,
		), admin_url( 'admin.php' ) );
		$offer_page_url = $section_url;
		?>
		<div class="wfocu_wrap_r wfocu_no_offer_wrap_r">
			<div class="wfocu_no_offers_wrapper wfocu_p20">
				<div class="wfocu_welcome_wrap">

					<div class="bwf-zero-state">
						<div class="bwf-zero-state-wrap">
							<div class="bwf-zero-sec bwf-zero-sec-icon bwf-pb-gap">
								<svg width="90" height="90" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
									<rect width="90" height="90" fill="white"/>
									<path d="M88.5 7.50003V13.5H1.5V7.50003C1.5 4.18632 4.18629 1.50003 7.5 1.50003H82.5C85.8137 1.50003 88.5 4.18632 88.5 7.50003Z" fill="#EBF2F6"/>
									<path d="M82.5 1.50003H78C81.3137 1.50003 84 4.18632 84 7.50003V13.5H88.5V7.50003C88.5 4.18632 85.8137 1.50003 82.5 1.50003Z" fill="#A8D3E6"/>
									<path d="M88.5 13.5V82.5C88.5 85.8137 85.8137 88.5 82.5 88.5H7.5C4.18629 88.5 1.5 85.8137 1.5 82.5V13.5H88.5Z" fill="#EBF2F6"/>
									<path d="M84 13.5V82.5C84 85.8137 81.3137 88.5 78 88.5H82.5C85.8137 88.5 88.5 85.8137 88.5 82.5V13.5H84Z" fill="#A8D3E6"/>
									<path d="M40.5 21H10.5C9.67157 21 9 21.6716 9 22.5V28.5C9 29.3285 9.67157 30 10.5 30H40.5C41.3284 30 42 29.3285 42 28.5V22.5C42 21.6716 41.3284 21 40.5 21Z" fill="#EBF2F6"/>
									<path d="M79.5 21H49.5C48.6716 21 48 21.6716 48 22.5V28.5C48 29.3285 48.6716 30 49.5 30H79.5C80.3284 30 81 29.3285 81 28.5V22.5C81 21.6716 80.3284 21 79.5 21Z" fill="#EBF2F6"/>
									<path d="M79.5 36H10.5C9.67157 36 9 36.6715 9 37.5V67.5C9 68.3284 9.67157 69 10.5 69H79.5C80.3284 69 81 68.3284 81 67.5V37.5C81 36.6715 80.3284 36 79.5 36Z" fill="#EBF2F6"/>
									<path d="M78 75H57C55.3431 75 54 76.3431 54 78C54 79.6568 55.3431 81 57 81H78C79.6569 81 81 79.6568 81 78C81 76.3431 79.6569 75 78 75Z" fill="#E8EDFC"/>
									<path d="M7.5 88.5H12C8.68629 88.5 6 85.8137 6 82.5V7.50003C6 4.18632 8.68629 1.50003 12 1.50003H7.5C4.18629 1.50003 1.5 4.18632 1.5 7.50003V82.5C1.5 85.8137 4.18629 88.5 7.5 88.5Z" fill="white"/>
									<path d="M0 70.5H3V82.5H0V70.5Z" fill="white"/>
									<path d="M1.5 78C2.32843 78 3 77.3284 3 76.5C3 75.6715 2.32843 75 1.5 75C0.671573 75 0 75.6715 0 76.5C0 77.3284 0.671573 78 1.5 78Z" fill="#0073AA"/>
									<path d="M82.5 0H7.5C3.35992 0.00495918 0.00495918 3.35992 0 7.5V70.5C0 71.3284 0.671573 72 1.5 72C2.32843 72 3 71.3284 3 70.5V15H87V82.5C87 84.9853 84.9853 87 82.5 87H7.5C5.01472 87 3 84.9853 3 82.5C3 81.6716 2.32843 81 1.5 81C0.671573 81 0 81.6716 0 82.5C0.00495918 86.6401 3.35992 89.995 7.5 90H82.5C86.6401 89.995 89.995 86.6401 90 82.5V7.5C89.995 3.35992 86.6401 0.00495918 82.5 0ZM3 12V7.5C3 5.01472 5.01472 3 7.5 3H82.5C84.9853 3 87 5.01472 87 7.5V12H3Z" fill="#0073AA"/>
									<path d="M70.5 9C71.3284 9 72 8.32843 72 7.5C72 6.67157 71.3284 6 70.5 6C69.6716 6 69 6.67157 69 7.5C69 8.32843 69.6716 9 70.5 9Z" fill="#0073AA"/>
									<path d="M76.5 9C77.3284 9 78 8.32843 78 7.5C78 6.67157 77.3284 6 76.5 6C75.6716 6 75 6.67157 75 7.5C75 8.32843 75.6716 9 76.5 9Z" fill="#0073AA"/>
									<path d="M82.5 9C83.3284 9 84 8.32843 84 7.5C84 6.67157 83.3284 6 82.5 6C81.6716 6 81 6.67157 81 7.5C81 8.32843 81.6716 9 82.5 9Z" fill="#0073AA"/>
									<path d="M10.5 31.5H40.5C42.1569 31.5 43.5 30.1568 43.5 28.5V22.5C43.5 20.8431 42.1569 19.5 40.5 19.5H10.5C8.84315 19.5 7.5 20.8431 7.5 22.5V28.5C7.5 30.1568 8.84315 31.5 10.5 31.5ZM10.5 22.5H40.5V28.5H10.5V22.5Z" fill="#0073AA"/>
									<path d="M79.5 31.5C81.1569 31.5 82.5 30.1568 82.5 28.5V22.5C82.5 20.8431 81.1569 19.5 79.5 19.5H49.5C47.8431 19.5 46.5 20.8431 46.5 22.5V28.5C46.5 30.1568 47.8431 31.5 49.5 31.5H79.5ZM49.5 22.5H79.5V28.5H49.5V22.5Z" fill="#0073AA"/>
									<path d="M7.5 67.5C7.5 69.1569 8.84315 70.5 10.5 70.5H79.5C81.1569 70.5 82.5 69.1569 82.5 67.5V37.5C82.5 35.8432 81.1569 34.5 79.5 34.5H10.5C8.84315 34.5 7.5 35.8432 7.5 37.5V67.5ZM10.5 37.5H79.5V67.5H10.5V37.5Z" fill="#0073AA"/>
									<path d="M78 73.5H57C54.5147 73.5 52.5 75.5147 52.5 78C52.5 80.4853 54.5147 82.5 57 82.5H78C80.4853 82.5 82.5 80.4853 82.5 78C82.5 75.5147 80.4853 73.5 78 73.5ZM78 79.5H57C56.1716 79.5 55.5 78.8285 55.5 78C55.5 77.1716 56.1716 76.5 57 76.5H78C78.8284 76.5 79.5 77.1716 79.5 78C79.5 78.8285 78.8284 79.5 78 79.5Z" fill="#0073AA"/>
									<path d="M15 43.5001H19.5C20.3284 43.5001 21 42.8285 21 42.0001C21 41.1716 20.3284 40.5001 19.5 40.5001H15C14.1716 40.5001 13.5 41.1716 13.5 42.0001C13.5 42.8285 14.1716 43.5001 15 43.5001Z" fill="#0073AA"/>
									<path d="M25.5 43.5001H43.5C44.3284 43.5001 45 42.8285 45 42.0001C45 41.1716 44.3284 40.5001 43.5 40.5001H25.5C24.6716 40.5001 24 41.1716 24 42.0001C24 42.8285 24.6716 43.5001 25.5 43.5001Z" fill="#0073AA"/>
									<path d="M54 46.5H15C14.1716 46.5 13.5 47.1716 13.5 48C13.5 48.8285 14.1716 49.5 15 49.5H54C54.8284 49.5 55.5 48.8285 55.5 48C55.5 47.1716 54.8284 46.5 54 46.5Z" fill="#0073AA"/>
									<path d="M11.5605 4.93955C10.9748 4.35397 10.0253 4.35397 9.43951 4.93955L9.00001 5.37905L8.56051 4.93955C7.97194 4.37109 7.03638 4.37922 6.45778 4.95782C5.87918 5.53642 5.87105 6.47198 6.43951 7.06055L6.87901 7.50005L6.43951 7.93955C6.04957 8.31616 5.89319 8.87386 6.03046 9.39831C6.16773 9.92275 6.5773 10.3323 7.10175 10.4696C7.62619 10.6069 8.18389 10.4505 8.56051 10.0605L9.00001 9.62105L9.43951 10.0605C10.0281 10.629 10.9636 10.6209 11.5422 10.0423C12.1208 9.46367 12.129 8.52812 11.5605 7.93955L11.121 7.50005L11.5605 7.06055C12.1461 6.4748 12.1461 5.5253 11.5605 4.93955V4.93955Z" fill="#0073AA"/>
								</svg>
						
							</div>
							<div class="bwf-zero-sec bwf-zero-sec-content bwf-h2 bwf-pb-10">
								<div><?php _e( 'Create an one click upsell offer', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
							</div>
							<div class="bwf-zero-sec bwf-zero-sec-content bwf-pb-gap">
								<div class="bwf-h4-1"><?php esc_html_e( 'Create an offer and add products to customize the design', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
							</div>
							<div class="bwf-zero-sec bwf-zero-sec-buttons bwf-pb-gap">
								<a href="<?php echo esc_url( $offer_page_url ) ?>" class="wfocu_step wfocu_button_add wfocu_button_inline  wfocu_welc_btn">
									<?php esc_html_e( 'Go to Offers', 'woofunnels-upstroke-one-click-upsell' ); ?>
								</a>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
	} else {
		?>
		<div v-bind:class="`single`===mode?`wfocu_mode_single`:`wfocu_mode_choice`" class="wfocu_wrap_r" id="wfocu_step_design">
			<div class="wfocu-loader"><img src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"/></div>
			<div class="wfocu_template wfocu-hide">
				<div class="wfocu_fsetting_table_head" v-if="!isEmpty(products)">
					<div class="wfocu_fsetting_table_head_in wfocu_clearfix">
						<div class="wfocu_template_holder_head2 wfocu_ml_auto" v-if="mode==`choice`&&!isEmpty(products)">
							<div class="wfocu_template_editor">
								<span class="wfocu_editor_field_label">Page Builder:</span>
								<div class="wfocu_field_select_dropdown">
									<span class="wfocu_editor_label wfocu_field_select_label" v-on:click="show_template_dropdown">
										<!-- {{template_group}} -->
										{{wfocu.template_groups[template_group]}}
										<i class="dashicons dashicons-arrow-down-alt2"></i>
									</span>
									<div class="wfocu_field_dropdown wfocu-hide">
										<div class="wfocu_dropdown_header">
											<label class="wfocu_dropdown_header_label"><?php _e( 'Select Page Builder', 'woofunnels-upstroke-one-click-upsell' ) ?></label>
										</div>
										<div class="wfocu_dropdown_body">
											<div class="wfocu_offer_design_mode">
												<?php
												foreach ( $get_all_groups as $key => $template_group ) {
													?>
													<a data-template="<?php echo $key; ?>" class="wfcou_dropdown_fields" v-bind:class="template_group == `<?php echo $key; ?>`?` wfocu_btn_selected`:``" v-on:click="template_group = `<?php echo $key; ?>`;hide_tempate_dropdown()"><?php echo $template_group->get_nice_name(); ?></a>
													<?php
												}
												?>
												<a class="wffn_dropdown_fields" style="display:none;" v-bind:class="template_group == `custom_page`?` wfocu_btn_selected`:``" v-on:click="template_group = `custom_page`"><?php esc_html_e( 'Custom Page', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
											</div>
										</div>
										<div class="wfocu_dropdown_footer">
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="wfocu_template_holder_head2" v-if="mode==`single`">
							<strong><?php esc_html_e( 'Selected Template', 'woofunnels-upstroke-one-click-upsell' ) ?></strong>: {{getTemplateNiceName()}}
							<span class="bwfan-tag-rounded bwfan_ml_12 clr-primary">{{getTemplateGroupNiceName()}}</span>
						</div>

						<div class="wfocu_selected_header_action" v-if="mode==`single`">

							<div class="wffn-ellipsis-menu">
								<div class="wffn-ellipsis-menu__toggle">
									<?php echo file_get_contents(  plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/assets/img/icons/ellipsis-menu.svg'  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="wffn-ellipsis-menu-dropdown">
									<a href="javascript:void(0)" class="wffn-ellipsis-menu-item" v-on:click="remove_template()"><?php echo file_get_contents(  plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/assets/img/icons/delete.svg'  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Remove Template' ) ?></a>
								</div>
							</div>
						</div>

					</div>
				</div>

				<div v-if="!isEmpty(products)" class="wfocu_template_box_holder">

					

					<div class="wfocu_template_preview" v-if="mode==`single`">
						<div class="wfocu_tp_wrap">
							<div class="wfocu_wrap_i ">
								<div class="wfocu_build_scratch" v-if="(current_template == 'wfocu-'+template_group+'-empty') || (current_template == 'custom-page') ">
									<div class="wfocu_temp_middle_align">
										<div class="wfocu_funnel_temp_overlay" v-if="current_template == 'wfocu-'+template_group+'-empty'">
												<div class="wfocu_template_btn_add">
													<svg viewBox="0 0 24 24" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect fill="white"></rect><path d="M12 2C6.48566 2 2 6.48566 2 12C2 17.5143 6.48566 22 12 22C17.5143 22 22 17.5136 22 12C22 6.48645 17.5143 2 12 2ZM12 20.4508C7.34082 20.4508 3.54918 16.66 3.54918 12C3.54918 7.34004 7.34082 3.54918 12 3.54918C16.6592 3.54918 20.4508 7.34004 20.4508 12C20.4508 16.66 16.66 20.4508 12 20.4508Z" fill="#000000"></path><path d="M15.873 11.1557H12.7746V8.05734C12.7746 7.62976 12.4284 7.28273 12 7.28273C11.5716 7.28273 11.2254 7.62976 11.2254 8.05734V11.1557H8.12703C7.69867 11.1557 7.35242 11.5027 7.35242 11.9303C7.35242 12.3579 7.69867 12.7049 8.12703 12.7049H11.2254V15.8033C11.2254 16.2309 11.5716 16.5779 12 16.5779C12.4284 16.5779 12.7746 16.2309 12.7746 15.8033V12.7049H15.873C16.3013 12.7049 16.6476 12.3579 16.6476 11.9303C16.6476 11.5027 16.3013 11.1557 15.873 11.1557Z" fill="#000000"></path></svg>
												</div>
												<div class="wfocu_p">
													<b><?php esc_html_e('Start from scratch','woofunnels-upstroke-one-click-upsell'); ?></b>
												</div>
										
										</div>
										<div class="wfocu_funnel_temp_overlay" v-if="current_template == 'custom-page'">
											<div class="wfocu_template_btn_add">
												<svg viewBox="0 0 24 24" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect fill="white"></rect><path d="M12 2C6.48566 2 2 6.48566 2 12C2 17.5143 6.48566 22 12 22C17.5143 22 22 17.5136 22 12C22 6.48645 17.5143 2 12 2ZM12 20.4508C7.34082 20.4508 3.54918 16.66 3.54918 12C3.54918 7.34004 7.34082 3.54918 12 3.54918C16.6592 3.54918 20.4508 7.34004 20.4508 12C20.4508 16.66 16.66 20.4508 12 20.4508Z" fill="#000000"></path><path d="M15.873 11.1557H12.7746V8.05734C12.7746 7.62976 12.4284 7.28273 12 7.28273C11.5716 7.28273 11.2254 7.62976 11.2254 8.05734V11.1557H8.12703C7.69867 11.1557 7.35242 11.5027 7.35242 11.9303C7.35242 12.3579 7.69867 12.7049 8.12703 12.7049H11.2254V15.8033C11.2254 16.2309 11.5716 16.5779 12 16.5779C12.4284 16.5779 12.7746 16.2309 12.7746 15.8033V12.7049H15.873C16.3013 12.7049 16.6476 12.3579 16.6476 11.9303C16.6476 11.5027 16.3013 11.1557 15.873 11.1557Z" fill="#000000"></path></svg>
											</div>
											<div class="wfocu_p">
												<b><?php esc_html_e('Link to custom page','woofunnels-upstroke-one-click-upsell'); ?></b>
											</div>
									
										</div>
									</div>
								</div>
								<div v-if="(current_template != 'wfocu-'+template_group+'-empty' && current_template != 'custom-page')">
									<img v-bind:src="getTemplateImage()">
								</div>
							</div>
							<div class="wfocu_wrap_c">
								<a href="javascript:void(0)" class="wfocu_funnel_btn_temp_alter wfocu_funnel_btn_blue_temp" v-on:click="customize_template(current_template)">
									<?php esc_attr_e( 'Edit Template', 'woofunnels-upstroke-one-click-upsell' ) ?>
								</a>

								<a href="javascript:void(0)" class="wfocu_funnel_btn_temp_alter wfocu_funnel_btn_white_temp wfocu_blue_link" v-on:click="preview_template(current_template)">
									<?php esc_attr_e( 'Preview', 'woofunnels-upstroke-one-click-upsell' ) ?>
								</a>

								<a class="wfocu_funnel_btn_temp_alter wfocu_funnel_btn_white_temp" v-bind:href="get_edit_link(current_template)" v-if="mode==`single`">
									<?php esc_html_e( 'Switch to WordPress Editor', 'woofunnels-upstroke-one-click-upsell' ) ?>
								</a>
							</div>
						</div>

					</div>

					<div class="wfocu_template_type_holder_in" v-if="mode==`choice`">
						<div class="wfocu_single_template_list wfocu_template_list wfocu_clearfix">
							<?php

							$license = WFOCU_Remote_Template_Importer::get_instance()->get_license_key();
							if ( empty( $license ) && class_exists( 'WFFN_Pro_Core' ) ) {
								$license = WFFN_Pro_Core()->support->get_license_key();
							}

							foreach ( $get_all_groups as $key => $template_group ) {
								$get_templates = $template_group->get_templates();
								foreach ( $get_templates as $temp ) {
									$template = WFOCU_Core()->template_loader->get_template( $temp );


									$temp_name = isset( $template['name'] ) ? $template['name'] : '';

									$prev_thumbnail = ( isset( $template['thumbnail'] ) ) ? $template['thumbnail'] : '';
									$prev_full      = isset( $template['large_img'] ) ? $template['large_img'] : '';

									$import_status = null;
									if ( 'customizer' !== $key ) {
										$import_status = 'yes';
									}
									if ( empty( $license ) ) {
										$import_status = 'no';
									}

									$temp_slug      = $temp;
									$temp_group     = $key;
									$overlay_icon   = '<i class="dashicons dashicons-visibility"></i>';
									$template_class = 'wfocu_temp_box_normal';
									$has_preview    = ( isset( $template['thumbnail'] ) && ! isset( $template['build_from_scratch'] ) ) ? true : false;
									$prevslug       = isset( $template['prevslug'] ) ? $template['prevslug'] : '';

									include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/templates/grid-template.php';
								}
							}
							include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/templates/grid-template-custom-page.php'; ?>
						</div>
					</div>

				</div>



				<!------HERE----->
				<!-- Fallback when we do not have any products to show -->
				<div v-if="isEmpty(products)" class="wfocu-scodes-wrap">
					<!--<div class="wfocu-scodes-head"><?php /*_e( 'This offer does not have any products.', 'woofunnels-upstroke-one-click-upsell' ); */ ?></div>-->

					<div class="wfocu_welcome_wrap" v-if="isEmpty(products)">
						<div class="wfocu_welcome_wrap_in">

							<div class="wfocu_first_product" v-if="isEmpty(products)">
								<div class="bwf-zero-state">
									<div class="bwf-zero-state-wrap">
										<div class="bwf-zero-sec bwf-zero-sec-icon bwf-pb-gap">
											<svg width="90" height="90" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M88.5 7.5V13.5H1.5V7.5C1.5 4.18629 4.18629 1.5 7.5 1.5H82.5C85.8137 1.5 88.5 4.18629 88.5 7.5Z" fill="#EBF2F6"></path>
												<path d="M82.4999 1.5H77.9999C81.3136 1.5 83.9999 4.18629 83.9999 7.5V13.5H88.4999V7.5C88.4999 4.18629 85.8136 1.5 82.4999 1.5Z" fill="#A8D3E6"></path>
												<path d="M88.5 13.5V82.5C88.5 85.8137 85.8137 88.5 82.5 88.5H7.5C4.18629 88.5 1.5 85.8137 1.5 82.5V13.5H88.5Z" fill="#EBF2F6"></path>
												<path d="M83.9999 13.5V82.5C83.9999 85.8137 81.3136 88.5 77.9999 88.5H82.4999C85.8136 88.5 88.4999 85.8137 88.4999 82.5V13.5H83.9999Z" fill="#A8D3E6"></path>
												<path d="M81 22.5V28.5H33V22.5C33.0049 20.8452 34.3452 19.5049 36 19.5H78C79.6548 19.5049 80.9951 20.8452 81 22.5Z" fill="#EBF2F6"></path>
												<path d="M78 19.5H73.5C75.1548 19.5049 76.4951 20.8452 76.5 22.5V28.5H81V22.5C80.9951 20.8452 79.6548 19.5049 78 19.5Z" fill="#A8D3E6"></path>
												<path d="M33 28.5H81V88.5H33V28.5Z" fill="#EBF2F6"></path>
												<path d="M72 82.5C73.6569 82.5 75 81.1569 75 79.5C75 77.8431 73.6569 76.5 72 76.5C70.3431 76.5 69 77.8431 69 79.5C69 81.1569 70.3431 82.5 72 82.5Z" fill="#E8EDFC"></path>
												<path d="M61.5 61.5H40.5C39.6716 61.5 39 62.1716 39 63V81C39 81.8284 39.6716 82.5 40.5 82.5H61.5C62.3284 82.5 63 81.8284 63 81V63C63 62.1716 62.3284 61.5 61.5 61.5Z" fill="#EBF2F6"></path>
												<path d="M61.5001 61.5H57.0001C57.8285 61.5 58.5001 62.1716 58.5001 63V81C58.5001 81.8284 57.8285 82.5 57.0001 82.5H61.5001C62.3285 82.5 63.0001 81.8284 63.0001 81V63C63.0001 62.1716 62.3285 61.5 61.5001 61.5Z" fill="#A8D3E6"></path>
												<path d="M42 55.5C43.6569 55.5 45 54.1569 45 52.5C45 50.8431 43.6569 49.5 42 49.5C40.3431 49.5 39 50.8431 39 52.5C39 54.1569 40.3431 55.5 42 55.5Z" fill="#E8EDFC"></path>
												<path d="M52.4999 55.5H73.4999C74.3284 55.5 74.9999 54.8284 74.9999 54V36C74.9999 35.1716 74.3284 34.5 73.4999 34.5H52.4999C51.6715 34.5 50.9999 35.1716 50.9999 36V54C50.9999 54.8284 51.6715 55.5 52.4999 55.5Z" fill="#EBF2F6"></path>
												<path d="M73.5 34.5H69C69.8284 34.5 70.5 35.1716 70.5 36V54C70.5 54.8284 69.8284 55.5 69 55.5H73.5C74.3284 55.5 75 54.8284 75 54V36C75 35.1716 74.3284 34.5 73.5 34.5Z" fill="#A8D3E6"></path>
												<path d="M25.5 19.5H9C8.17157 19.5 7.5 20.1716 7.5 21V27C7.5 27.8284 8.17157 28.5 9 28.5H25.5C26.3284 28.5 27 27.8284 27 27V21C27 20.1716 26.3284 19.5 25.5 19.5Z" fill="#EBF2F6"></path>
												<path d="M7.5 88.5H12C8.68629 88.5 6 85.8137 6 82.5V7.5C6 4.18629 8.68629 1.5 12 1.5H7.5C4.18629 1.5 1.5 4.18629 1.5 7.5V82.5C1.5 85.8137 4.18629 88.5 7.5 88.5Z" fill="white"></path>
												<path d="M0 70.5H3V82.5H0V70.5Z" fill="white"></path>
												<path d="M1.5 78C2.32843 78 3 77.3284 3 76.5C3 75.6716 2.32843 75 1.5 75C0.671573 75 0 75.6716 0 76.5C0 77.3284 0.671573 78 1.5 78Z" fill="#0073AA"></path>
												<path d="M82.5 0H7.5C3.35992 0.00495918 0.00495918 3.35992 0 7.5V70.5C0 71.3284 0.671573 72 1.5 72C2.32843 72 3 71.3284 3 70.5V15H87V82.5C87 84.9853 84.9853 87 82.5 87V22.5C82.5 20.0147 80.4853 18 78 18H36C33.5147 18 31.5 20.0147 31.5 22.5V87H7.5C5.01472 87 3 84.9853 3 82.5C3 81.6716 2.32843 81 1.5 81C0.671573 81 0 81.6716 0 82.5C0.00495918 86.6401 3.35992 89.995 7.5 90H82.5C86.6401 89.995 89.995 86.6401 90 82.5V7.5C89.995 3.35992 86.6401 0.00495918 82.5 0ZM36 21H78C78.8284 21 79.5 21.6716 79.5 22.5V27H34.5V22.5C34.5 21.6716 35.1716 21 36 21ZM34.5 87V30H79.5V87H34.5ZM3 12V7.5C3 5.01472 5.01472 3 7.5 3H82.5C84.9853 3 87 5.01472 87 7.5V12H3Z" fill="#0073AA"></path>
												<path d="M70.5 9C71.3284 9 72 8.32843 72 7.5C72 6.67157 71.3284 6 70.5 6C69.6716 6 69 6.67157 69 7.5C69 8.32843 69.6716 9 70.5 9Z" fill="#0073AA"></path>
												<path d="M76.4999 9C77.3284 9 77.9999 8.32843 77.9999 7.5C77.9999 6.67157 77.3284 6 76.4999 6C75.6715 6 74.9999 6.67157 74.9999 7.5C74.9999 8.32843 75.6715 9 76.4999 9Z" fill="#0073AA"></path>
												<path d="M82.5 9C83.3284 9 84 8.32843 84 7.5C84 6.67157 83.3284 6 82.5 6C81.6716 6 81 6.67157 81 7.5C81 8.32843 81.6716 9 82.5 9Z" fill="#0073AA"></path>
												<path d="M11.5605 4.93952C10.9747 4.35394 10.0252 4.35394 9.43947 4.93952L8.99997 5.37902L8.56047 4.93952C7.97191 4.37106 7.03635 4.37919 6.45775 4.95779C5.87915 5.53639 5.87102 6.47195 6.43948 7.06052L6.87898 7.50002L6.43948 7.93952C6.04954 8.31613 5.89316 8.87383 6.03043 9.39828C6.1677 9.92272 6.57727 10.3323 7.10171 10.4696C7.62616 10.6068 8.18386 10.4505 8.56047 10.0605L8.99997 9.62102L9.43947 10.0605C10.028 10.629 10.9636 10.6208 11.5422 10.0422C12.1208 9.46364 12.1289 8.52808 11.5605 7.93952L11.121 7.50002L11.5605 7.06052C12.146 6.47477 12.146 5.52527 11.5605 4.93952V4.93952Z" fill="#0073AA"></path>
												<path d="M72 75C69.5147 75 67.5 77.0147 67.5 79.5C67.5 81.9853 69.5147 84 72 84C74.4853 84 76.5 81.9853 76.5 79.5C76.5 77.0147 74.4853 75 72 75ZM72 81C71.1716 81 70.5 80.3284 70.5 79.5C70.5 78.6716 71.1716 78 72 78C72.8284 78 73.5 78.6716 73.5 79.5C73.5 80.3284 72.8284 81 72 81Z" fill="#0073AA"></path>
												<path d="M61.5 60H40.5C38.8431 60 37.5 61.3431 37.5 63V81C37.5 82.6568 38.8431 84 40.5 84H61.5C63.1569 84 64.5 82.6568 64.5 81V63C64.5 61.3431 63.1569 60 61.5 60ZM61.5 81H40.5V63H61.5V81Z" fill="#0073AA"></path>
												<path d="M42 57C44.4853 57 46.5 54.9853 46.5 52.5C46.5 50.0147 44.4853 48 42 48C39.5147 48 37.5 50.0147 37.5 52.5C37.5 54.9853 39.5147 57 42 57ZM42 51C42.8284 51 43.5 51.6716 43.5 52.5C43.5 53.3284 42.8284 54 42 54C41.1716 54 40.5 53.3284 40.5 52.5C40.5 51.6716 41.1716 51 42 51Z" fill="#0073AA"></path>
												<path d="M73.5 33H52.5C50.8431 33 49.5 34.3431 49.5 36V54C49.5 55.6569 50.8431 57 52.5 57H73.5C75.1569 57 76.5 55.6569 76.5 54V36C76.5 34.3431 75.1569 33 73.5 33ZM73.5 54H52.5V36H73.5V54Z" fill="#0073AA"></path>
												<path d="M9 30H25.5C27.1569 30 28.5 28.6569 28.5 27V21C28.5 19.3431 27.1569 18 25.5 18H9C7.34315 18 6 19.3431 6 21V27C6 28.6569 7.34315 30 9 30ZM9 21H25.5V27H9V21Z" fill="#0073AA"></path>
												<path d="M7.5 37.5H27C27.8284 37.5 28.5 36.8284 28.5 36C28.5 35.1716 27.8284 34.5 27 34.5H7.5C6.67157 34.5 6 35.1716 6 36C6 36.8284 6.67157 37.5 7.5 37.5Z" fill="#0073AA"></path>
												<path d="M7.5 46.5H13.5C14.3284 46.5 15 45.8284 15 45C15 44.1716 14.3284 43.5 13.5 43.5H7.5C6.67157 43.5 6 44.1716 6 45C6 45.8284 6.67157 46.5 7.5 46.5Z" fill="#0073AA"></path>
												<path d="M27 43.5H19.5C18.6716 43.5 18 44.1716 18 45C18 45.8284 18.6716 46.5 19.5 46.5H27C27.8284 46.5 28.5 45.8284 28.5 45C28.5 44.1716 27.8284 43.5 27 43.5Z" fill="#0073AA"></path>
												<path d="M7.5 55.5H27C27.8284 55.5 28.5 54.8284 28.5 54C28.5 53.1716 27.8284 52.5 27 52.5H7.5C6.67157 52.5 6 53.1716 6 54C6 54.8284 6.67157 55.5 7.5 55.5Z" fill="#0073AA"></path>
											</svg>
									
										</div>
										<div class="bwf-zero-sec bwf-zero-sec-content bwf-h2 bwf-pb-10">
											<div><?php _e( 'Add a product', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
										</div>
										<div class="bwf-zero-sec bwf-zero-sec-content bwf-pb-gap">
											<div class="bwf-h4-1"><?php _e( 'Add a product to this offer to customize design', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
										</div>
										<div class="bwf-zero-sec bwf-zero-sec-buttons bwf-pb-gap">
											<button type="button" style="cursor: pointer;" class="wfocu_button_inline wfocu_welc_btn" v-on:click="window.location = '<?php echo esc_url( admin_url( 'admin.php?page=upstroke&section=offer&edit=' . $offers['id'] . ' ' ) ); ?>'">
												<?php esc_html_e( 'Go to Offers', 'woofunnels-upstroke-one-click-upsell' ); ?>
											</button>
										</div>
									</div>
								</div>
								
							</div>
						</div>
					</div>

				</div>


				<!-- Show shortcodes in case of custom pages.  -->
				<div v-else-if="true == shouldShowShortcodeUI()" class="wfocu-scodes-wrap">
					<div class="wfocu-tabs-view-vertical">
						<div role="tablist" class="wfocu-funnel-setting-tabs">
							<div class="wfocu-tab-heading wfocu-scodes-head">
								<?php esc_html_e( 'Offer Settings', 'woofunnels-upstroke-one-click-upsell' ); ?>
							</div>
							<div class="wfocu-tab-title wfocu-active">
								<?php esc_html_e( 'Shortcodes', 'woofunnels-upstroke-one-click-upsell' ); ?>
							</div>
						</div>

						<div class="wfocu-tabs-content-wrapper">
							<div class="wfocu-scodes-desc">
								<?php
								echo sprintf( __( 'Using page builders to build custom upsell pages? <a href=%s target="_blank">Read this guide to learn more</a> about using Button widgets of your page builder <a href=%s target="_blank">Personalization shortcodes</a>', 'woofunnels-upstroke-one-click-upsell' ), esc_url( 'https://funnelkit.com/docs/one-click-upsell/design/custom-designed-one-click-upsell-pages/' ), esc_url( 'https://funnelkit.com/docs/one-click-upsell/design/custom-designs/#order-personalization-shortcodes' ) ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>

							</div>
							<div v-if="typeof template_group !== 'undefined' && template_group == 'oxy'" class="wfocu-scodes-inner-wrap">
								<div class="wfocu-scodes-list-wrap">
									<div class="wfocu-scode-product-head"><?php _e( 'Shortcodes', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
									<div class="wfocu-scodes-products">
										<div v-for="oxyTag in oxyTags" class="wfocu-scodes-row">
											<div class="wfocu-scodes-label">{{oxyTag.name}}</div>
											<div class="wfocu-scodes-value">
												<div class="wfocu-scodes-value-in">
													<span class="wfocu-scode-text"><input readonly type="text" v-bind:value="oxyTag.tag"></span>
													<a href="javascript:void(0)" v-on:click="copy" class="wfocu_copy_text"><?php _e( 'Copy', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div v-else v-for="shortcode in shortcodes" class="wfocu-scodes-inner-wrap">
								<div class="wfocu-scodes-list-wrap">
									<div class="wfocu-scode-product-head"><?php _e( 'Product - ', 'woofunnels-upstroke-one-click-upsell' ); ?> {{shortcode.name}}</div>
									<div class="wfocu-scodes-products">
										<div v-for="key in shortcode.shortcodes" class="wfocu-scodes-row">
											<div class="wfocu-scodes-label">{{key.label}}</div>
											<div class="wfocu-scodes-value">
												<div class="wfocu-scodes-value-in">
													<span class="wfocu-scode-text"><input readonly type="text" v-bind:value="key.value"></span>
													<a href="javascript:void(0)" v-on:click="copy" class="wfocu_copy_text"><svg fill="#0073aa" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20"><path d="M 18.5 5 C 15.480226 5 13 7.4802259 13 10.5 L 13 32.5 C 13 35.519774 15.480226 38 18.5 38 L 34.5 38 C 37.519774 38 40 35.519774 40 32.5 L 40 10.5 C 40 7.4802259 37.519774 5 34.5 5 L 18.5 5 z M 18.5 8 L 34.5 8 C 35.898226 8 37 9.1017741 37 10.5 L 37 32.5 C 37 33.898226 35.898226 35 34.5 35 L 18.5 35 C 17.101774 35 16 33.898226 16 32.5 L 16 10.5 C 16 9.1017741 17.101774 8 18.5 8 z M 11 10 L 9.78125 10.8125 C 8.66825 11.5545 8 12.803625 8 14.140625 L 8 33.5 C 8 38.747 12.253 43 17.5 43 L 30.859375 43 C 32.197375 43 33.4465 42.33175 34.1875 41.21875 L 35 40 L 17.5 40 C 13.91 40 11 37.09 11 33.5 L 11 10 z"></path></svg>&ensp;<?php _e( 'Copy', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>

				<!-------------------------------->


				<!-- Show saved template to chose from here. -->
				<?php
				$template_names = get_option( 'wfocu_template_names', [] );
				if ( count( $template_names ) > 0 ) { ?>

				<div v-if="!isEmpty(products) && template_group == 'customizer' && mode==`single`" class="wfocu-scodes-wrap preset-holder">
					<div class="wfocu-tabs-view-vertical">
						<div role="tablist" class="wfocu-funnel-setting-tabs">
							<div class="wfocu-tab-heading wfocu-scodes-head">
								<?php esc_html_e( 'UpSells Page Settings', 'woofunnels-upstroke-one-click-upsell' ); ?>
							</div>
							<div class="wfocu-tab-title wfocu-active">
								<?php esc_html_e( 'Your Saved Presets', 'woofunnels-upstroke-one-click-upsell' ); ?>
							</div>
						</div>

						<div class="wfocu-tabs-content-wrapper">
							<div class="wfocu-scodes-desc"><?php esc_html_e( 'Click on the button to apply preset to the selected template. This will modify the default settings of the template and load it with settings of preset.', 'woofunnels-upstroke-one-click-upsell' ) ?></div>
							<div v-for="shortcode in shortcodes" class="wfocu-scodes-inner-wrap">
								<div class="wfocu-scodes-list-wrap">
									<div class="wfocu-scode-product-head"><?php esc_html_e( 'Apply and Customize Saved Presets', 'woofunnels-upstroke-one-click-upsell' ); ?> {{shortcode.name}}</div>
									<div class="wfocu-scodes-products preset_scodes">
										<?php
										foreach ( $template_names as $template_slug => $template ) { ?>
											<div class="customize-inside-control-row wfocu_template_holder wfocu-scodes-row">
												<div class="wfocu-scodes-label"><?php echo $template['name']; ?></div>
												<div class="wfocu-scodes-value-in wfocu-preset-right">
													<span class="wfocu-ajax-apply-preset-loader wfocu_hide"><img src="<?php echo admin_url( 'images/spinner.gif' ); ?>"></span>
													<a href="javascript:void(0);" class="wfocu_apply_template button-primary" data-slug="<?php echo $template_slug ?>"><?php _e( 'Apply', 'woofunnels-upstroke-one-click-upsell' ) ?></a>
													<a href="javascript:void(0)" class="wfocu_customize_template button-primary" style="display: none;"><?php echo __( 'Applied', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
												</div>
											</div>
											<?php
										} ?>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>


			</div>

			<?php } ?>
		</div>
	<?php }
}
