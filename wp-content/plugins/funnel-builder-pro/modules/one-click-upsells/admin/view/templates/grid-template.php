<div class="<?php echo ( ! $has_preview ) ? 'wfocu_empty_template' : '' ?>" v-if="template_group==`<?php echo $temp_group; ?>`" v-bind:class="current_template==`<?php echo $temp_slug; ?>`?`<?php echo $template_class; ?> wfocu_template_box_single  wfocu_selected_template`:`wfocu_template_box <?php echo $template_class; ?> wfocu_template_box_single`  " data-slug="<?php echo $temp_slug; ?>">
	<div class="wfocu_template_box_inner">
		<?php if ( $has_preview ) { ?>
			<div class="wfocu_template_sec_design">
				<div class="wfocu_overlay">
					<div class="wfocu_template_btm_strip wfocu_clearfix">
						<div class="wfocu_template_button">
							<a v-on:click="triggerPreview('<?php echo $temp_slug; ?>','<?php echo $temp_group; ?>')" target="_blank" class="wfocu_btn wfocu_step_btn wfocu_steps_btn_success"><?php esc_html_e( 'Preview', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
							<?php
							/**
							 * Import status
							 *  no: license key activation required to access this template
							 *  Yes: Import allowed
							 *  null: does not require import
							 */
							if ( 'no' === $import_status ) {
								?>
								<a target="_blank" href="<?php echo esc_url( 'https://funnelkit.com/exclusive-offer/' ) ?>" class="wfocu_btn wfocu_step_btn wfocu_btn_blue wfocu_design_btn_no_license" >
									<?php _e( 'Get PRO', 'woofunnels-upstroke-one-click-upsell' ) ?></a>
								<?php
							} elseif ( null !== $import_status ) { ?>
								<a href="javascript:void(0)" v-bind:class="getButtonClass(`<?php echo $temp_slug; ?>`)" class="wfocu_btn wfocu_step_btn wfocu_btn_blue wfocu_import_template" v-on:click="set_template(`<?php echo $temp_slug; ?>`,false, $event)">
									{{getButtonText(`<?php echo $import_status;?>`,`<?php echo $temp_slug;?>`)}}</a>
								<?php
							} else { ?>
								<a href="javascript:void(0)" v-bind:class="getButtonClass(`<?php echo $temp_slug; ?>`)" class="wfocu_btn wfocu_step_btn wfocu_btn_blue wfocu_import_template" v-on:click="set_template(`<?php echo $temp_slug; ?>`, true, $event)">
									{{getButtonText(`<?php echo $import_status; ?>`,`<?php echo $temp_slug; ?>`)}}</a>
								<?php
							} ?>
						</div>
					</div>
					<div class="wfocu_overlay_icon"><?php echo $overlay_icon; ?></div>
				</div>
				<div class="wfocu_template_thumbnail">
					<div class="wfocu_img_thumbnail ">
						<img src="<?php echo $prev_thumbnail; ?>" alt="<?php echo $temp_name; ?>"/>
					</div>
				</div>

			</div>
			<div class="wfocu_template_sec_meta">
				<div class="wfocu_template_meta_left">
					<?php
					echo esc_html( $temp_name );
					?>
				</div>
			</div>


		<?php } else {
		?>
		<div class="wfocu_template_img_cover">
			<div class="wfocu_template_thumbnail">
				<?php if ( 'wfocu-custom-empty' === $temp_slug || 'wfocu-gutenberg-empty' === $temp_slug) { ?>
				<div class="wfocu_img_thumbnail" v-on:click="set_template(`<?php echo $temp_slug; ?>`)">

					<?php }else{ ?>
					<div class="wfocu_img_thumbnail rest" v-on:click="set_template(`<?php echo $temp_slug; ?>`,false)">

						<?php } ?>
						<div class="wfocu_overlay"></div>
						<div class="wfocu_vertical_mid">
							<div class="wfocu_add_tmp_se">
								<a href="javascript:void(0);" class="wfacp_steps_btn_add">
									<svg viewBox="0 0 24 24" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect fill="white"></rect><path d="M12 2C6.48566 2 2 6.48566 2 12C2 17.5143 6.48566 22 12 22C17.5143 22 22 17.5136 22 12C22 6.48645 17.5143 2 12 2ZM12 20.4508C7.34082 20.4508 3.54918 16.66 3.54918 12C3.54918 7.34004 7.34082 3.54918 12 3.54918C16.6592 3.54918 20.4508 7.34004 20.4508 12C20.4508 16.66 16.66 20.4508 12 20.4508Z" fill="#000000"></path><path d="M15.873 11.1557H12.7746V8.05734C12.7746 7.62976 12.4284 7.28273 12 7.28273C11.5716 7.28273 11.2254 7.62976 11.2254 8.05734V11.1557H8.12703C7.69867 11.1557 7.35242 11.5027 7.35242 11.9303C7.35242 12.3579 7.69867 12.7049 8.12703 12.7049H11.2254V15.8033C11.2254 16.2309 11.5716 16.5779 12 16.5779C12.4284 16.5779 12.7746 16.2309 12.7746 15.8033V12.7049H15.873C16.3013 12.7049 16.6476 12.3579 16.6476 11.9303C16.6476 11.5027 16.3013 11.1557 15.873 11.1557Z" fill="#000000"></path></svg>
								</a>
							</div>
							<div class="wfocu_p">{{getButtonText(`<?php echo $import_status; ?>`,`<?php echo $temp_slug; ?>`,true)}}
							</div>
							<!-- <div class="wfocu_import_description"><?php _e( 'Create your funnel from scratch' ) ?></div> -->
						</div>
					</div>
				</div>
			</div>
			<?php
			} ?>

		</div>
		<div class="hide-preview-over">
			<div v-if="true===ShouldPreview('<?php echo $temp_slug; ?>','<?php echo $temp_group; ?>')" class="wfocu-preview-overlay">
				<div class="wfocu_template_preview_wrap">
					<div class="wfocu_template_preview_header">
						<div class="bwf_template_logo_title">
							<img src="<?php echo esc_url( plugin_dir_url( WooFunnel_Loader::$ultimate_path ) . 'woofunnels/assets/img/menu/funnelkit-logo.svg' ); ?>" alt="Funnel Builder for WordPress" class="bwf-brand-logo-only" width="148">
							<div class="bwf_preview_template_title"><?php echo $temp_name ?></div>
						</div>
						<div class="wfocu_template_viewport">
							<div class="wfocu_template_viewport_inner">
								<span class="wfocu_viewport_icons active" v-on:click="setViewport('desktop', $event)" title="Desktop Viewport">
									<svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.8128 0.5H1.18783C0.900326 0.5 0.666992 0.733333 0.666992 1.02083V11.4375C0.666992 11.725 0.900326 11.9583 1.18783 11.9583H8.47949V14.0417H6.39616C6.10866 14.0417 5.87533 14.275 5.87533 14.5625C5.87533 14.85 6.10866 15.0833 6.39616 15.0833H11.6045C11.892 15.0833 12.1253 14.85 12.1253 14.5625C12.1253 14.275 11.892 14.0417 11.6045 14.0417H9.52116V11.9583H16.8128C17.1003 11.9583 17.3337 11.725 17.3337 11.4375V1.02083C17.3337 0.733333 17.1003 0.5 16.8128 0.5ZM16.292 10.9167H1.70866V1.54167H16.292V10.9167Z" fill="#353030" ></path></svg>
								</span>
								<span class="wfocu_viewport_icons" v-on:click="setViewport('tablet', $event)" title="Tablet Viewport">
									<svg width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.1696 0H1.48758C0.838506 0 0.319336 0.471577 0.319336 1.06108V14.9389C0.319336 15.5285 0.838539 16 1.48758 16H13.1511C13.8002 16 14.3193 15.5284 14.3193 14.9389V1.06108C14.3193 0.471547 13.8001 0 13.1696 0H13.1696ZM7.32861 0.488359C7.56971 0.488359 7.75506 0.656828 7.75506 0.875696C7.75506 1.09468 7.56958 1.26303 7.32861 1.26303C7.08751 1.26303 6.90215 1.09456 6.90215 0.875696C6.90215 0.673627 7.08751 0.488359 7.32861 0.488359ZM7.32861 15.0904C6.90215 15.0904 6.5498 14.7704 6.5498 14.383C6.5498 13.9957 6.90215 13.6757 7.32861 13.6757C7.75506 13.6757 8.10741 13.9957 8.10741 14.383C8.10741 14.7872 7.75506 15.0904 7.32861 15.0904ZM12.9286 12.2104C12.9286 12.5304 12.6505 12.783 12.2982 12.783H2.35913C2.00678 12.783 1.7287 12.5304 1.7287 12.2104L1.72857 2.35777C1.72857 2.03774 2.00666 1.78517 2.359 1.78517H12.298C12.6504 1.78517 12.9285 2.03775 12.9285 2.35777L12.9286 12.2104Z" fill="#353030"></path></svg>
								</span>
								<span class="wfocu_viewport_icons" v-on:click="setViewport('mobile', $event)" title="Mobile Viewport">
									<svg width="12" height="18" viewBox="0 0 12 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.167 0.666504H1.50033C0.766988 0.666504 0.166992 1.2665 0.166992 1.99984V15.9998C0.166992 16.7365 0.766988 17.3332 1.50033 17.3332H10.167C10.9036 17.3332 11.5003 16.7365 11.5003 15.9998V1.99984C11.5003 1.2665 10.9036 0.666504 10.167 0.666504ZM5.83366 16.5132C5.46033 16.5132 5.15365 16.2098 5.15365 15.8332C5.15365 15.4565 5.46033 15.1532 5.83366 15.1532C6.21033 15.1532 6.51365 15.4565 6.51365 15.8332C6.51365 16.2098 6.21033 16.5132 5.83366 16.5132ZM10.167 13.9998C10.167 14.1832 10.0203 14.3332 9.83366 14.3332H1.83366C1.65031 14.3332 1.50033 14.1832 1.50033 13.9998V2.33317C1.50033 2.14984 1.65031 1.99984 1.83366 1.99984H9.83366C10.0203 1.99984 10.167 2.14984 10.167 2.33317V13.9998Z" fill="#353030"></path></svg>
								</span>
							</div>
						</div>
						<div class="bwf-t-center">
							<?php if( 'no' !== $import_status ){ ?>
								<a href="javascript:void(0)" v-bind:class="getButtonClass(`<?php echo $temp_slug; ?>`, true)" class="button button-primary wfocu-import-template-btn is-primary wfocu_steps_btn wfocu_import_template" v-on:click="set_template('<?php echo $temp_slug; ?>')">{{getButtonText(`<?php echo $import_status; ?>`,`<?php echo $temp_slug; ?>`, false)}}</a>
							<?php }else{ ?>
								<a href="javascript:void(0)" v-bind:class="getButtonClass(`<?php echo $temp_slug; ?>`, true)" class="button button-primary wfocu-import-template-btn is-primary wfocu_steps_btn wfocu_import_template">{{getButtonText(`<?php echo $import_status; ?>`,`<?php echo $temp_slug; ?>`, false)}}</a>
							<?php } ?>
						</div>
						<div class="wfocu_template_preview_close">
							<button type="button" v-on:click="previewClosed()" class="components-button">
							<svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14"><path d="M9.46702 7.99987L15.6972 1.76948C16.1027 1.36422 16.1027 0.708964 15.6972 0.303702C15.292 -0.10156 14.6367 -0.10156 14.2315 0.303702L8.00106 6.5341L1.77084 0.303702C1.36539 -0.10156 0.710327 -0.10156 0.305065 0.303702C-0.100386 0.708964 -0.100386 1.36422 0.305065 1.76948L6.53528 7.99987L0.305065 14.2303C-0.100386 14.6355 -0.100386 15.2908 0.305065 15.696C0.507032 15.8982 0.772588 15.9998 1.03795 15.9998C1.30332 15.9998 1.56869 15.8982 1.77084 15.696L8.00106 9.46565L14.2315 15.696C14.4336 15.8982 14.699 15.9998 14.9643 15.9998C15.2297 15.9998 15.4951 15.8982 15.6972 15.696C16.1027 15.2908 16.1027 14.6355 15.6972 14.2303L9.46702 7.99987Z" fill="#353030"></path></svg>
							</button>
						</div>
					</div>
					<div class="wfocu_template_preview_content">
						<div class="wfocu_template_preview_inner wfocu_funnel_preview">
							<div class="woocommerce-web-preview wfocu_template_preview_frame">
								<div class="woocommerce-web-preview__iframe-wrapper">
									<div class="wfocu_global_loader">
										<div class="wfocu-loading-ring wfocu-loading-xxl">
											<div></div>
											<div></div>
											<div></div>
											<div></div>
										</div>
									</div>
									<iframe v-bind:src="getPreviewUrl('<?php echo $prevslug; ?>', '<?php echo $temp_group; ?>')" width="100%" height="100%"></iframe>
								</div>
							</div>
						</div>
						<div class="wfocu_template_preview_sidebar">
							<?php

							$all_groups = WFOCU_Core()->template_loader->get_all_groups();
							foreach ( $all_groups as $t_key => $t_groups ) {
								$get_temp = $t_groups->get_templates();
								if ( $t_key === $temp_group ) {//phpcs:ignore
									foreach ( $get_temp as $t_slug ) {
										$templates = WFOCU_Core()->template_loader->get_template( $t_slug );
										$p_scratch = ( isset( $templates['build_from_scratch'] ) ) ? $templates['build_from_scratch'] : false;
										if ( $p_scratch === false ) {

											$p_thumbnail = ( isset( $templates['thumbnail'] ) ) ? $templates['thumbnail'] : '';
											$p_name      = ( isset( $templates['name'] ) ) ? $templates['name'] : '';
											?>
											<div>
												<label class="wfocu_template_page_options" pre_slug="<?php echo $t_slug;//phpcs:ignore ?>" v-on:click="triggerPreview('<?php echo $t_slug;//phpcs:ignore ?>','<?php echo $t_key;//phpcs:ignore ?>')">
													<div class="wfocu__preview_thumbnail">
														<img src="<?php echo $p_thumbnail;//phpcs:ignore ?>">
													</div>
													<span class="wfocu_template_name"><?php echo $p_name;//phpcs:ignore ?></span>
												</label>
											</div>
											<?php
										}
									}
								}
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>