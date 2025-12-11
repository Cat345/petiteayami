<!--main panel wrapper open -->
<div class="wrapper wfacp-main-container">
    <div class="wfacp-wrapper-decoration <?php echo $fullWidthCls; ?>">

        <!--header section wrapper -->
        <!--Breadcrumb-->
		<?php
		if ( isset( $header_layout_is ) && $header_layout_is != 'outside_header' ) {
			include( $this->wfacp_get_header() );
			$this->custom_add_form_steps();
			/*add_outside_header*/
		}
		?>
        <!--header section wrapper close -->

        <!--Breadcrumb close-->

        <!-- contener wrapper open -->
        <div class="wfacp-panel-wrapper <?php echo 'wfacp_' . $header_layout_is; ?> ">
            <div class="wfacp-middle-container">
                <div class="wfacp-form-panel clearfix">
                    <div class="wfacp-comm-wrapper wfacp-clearfix">
						<?php
						if ( $this->device_type != 'mobile' ) {
							$footer = $this->customizer_fields_data[ $this->customizer_keys['footer'] ];
							if ( ( is_array( $footer ) && count( $footer ) <= 0 ) || is_null( $footer ) ) {
								return;
							}
							?>
                            <!--left wrapper -->
                            <div class="wfacp-left-wrapper">
                                <div class="wfacp-form">
									<?php
									if ( isset( $header_layout_is ) && $header_layout_is != 'outside_header' ) {
										if ( $this->device_mb_tab == 'tablet' ) {
											$this->get_mobile_mini_cart( 'mobile_Collapsible' );
										}
									}
									include( $this->wfacp_get_form() );

									if ( ( is_array( $this->excluded_other_widget() ) && count( $this->excluded_other_widget() ) > 0 ) && $this->device_type != 'mobile' ) {
										foreach ( $this->excluded_other_widget() as $key => $value ) {
											$data        = array();
											$section_key = $value;
											if ( isset( $this->customizer_fields_data[ $section_key ] ) ) {
												$data = $this->customizer_fields_data[ $section_key ];
											}
											switch ( $value ) {
												case ( false !== strpos( $section_key, 'wfacp_promises_' ) ):
													$promise_data[ $section_key ] = $data;
													$this->get_module( $data, false, 'promises', $section_key );
													break;
												case ( false !== strpos( $section_key, 'wfacp_customer_' ) ):
													$this->get_module( $data, false, 'customer-support', $section_key );
													break;
												case ( false !== strpos( $section_key, 'wfacp_html_widget_' ) ):

													$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
													break;
											}
										}
									}
									?>
                                    <!-- testimonial panel close -->
                                </div>
								<?php
								if ( isset( $header_layout_is ) && $header_layout_is == 'outside_header' ) {
									?>
                                    <div class="wfacp_inner_footer_m wfacp-footer wfacp_footer ">
                                        <div class="wfacp_footer_sec clearfix">
											<?php
											if ( isset( $footer['footer_data']['ft_ct_content'] ) && $footer['footer_data']['ft_ct_content'] != '' ) {
												?>
                                                <div class=" wfacp_footer_n">
                                                    <div class=" wfacp_footer_wrap_n">
                                                        <div class="wfacp-footer-text">
															<?php echo apply_filters( 'wfacp_the_content', $footer['footer_data']['ft_ct_content'] ); ?>
                                                        </div>
                                                    </div>

                                                </div>
												<?php
											}
											?>
                                        </div>
                                    </div>
									<?php
								}
								?>
                            </div>
                            <!--left wrapper close-->

                            <!-- right wrapper -->

							<?php include( $this->wfacp_get_sidebar() ); ?>
							<?php
						} else {
							$mobile_layout_order = $this->mobile_layout_order();

							if ( isset( $header_layout_is ) && $header_layout_is == 'outside_header' ) {
								echo "<header class='mb_header_section'>";
								$this->add_outside_header();
								echo '</header>';
							}
							if ( apply_filters( 'wfacp_enable_shopcheckout_mobile_order_summary', true ) ) {
								if ( isset( $this->customizer_fields_data['wfacp_form']['form_data']['enable_collapsible_order_summary'] ) && wc_string_to_bool( $this->customizer_fields_data['wfacp_form']['form_data']['enable_collapsible_order_summary'] ) ) {
									$this->get_mobile_mini_cart( 'mobile_Collapsible' );

								}
							}
							echo '<div class="wfacp_layout_content_wrapper">';
							foreach ( $mobile_layout_order as $key => $value ) {
								$section_key = $value;
								if ( isset( $this->customizer_fields_data[ $section_key ] ) ) {
									$data = $this->customizer_fields_data[ $section_key ];
								}
								switch ( $value ) {
									case 'wfacp_product':
										include( $this->wfacp_get_product() );
										break;
									case 'wfacp_form':
										printf( "<div class='wfacp-form clearfix'>" );

										echo '   <div class="wfacp-left-wrapper clearfix">';
										include( $this->wfacp_get_form() );
										echo '</div>';
										echo '</div>';
										break;

									case ( false !== strpos( $section_key, 'wfacp_benefits_' ) ):
										$this->get_module( $data, false, 'benefits', $section_key );
										break;
									case ( false !== strpos( $section_key, 'wfacp_testimonials_' ) ):
										$this->get_module( $data, false, 'testimonials', $section_key );
										break;
									case ( false !== strpos( $section_key, 'wfacp_assurance_' ) ):
										$this->get_module( $data, false, 'assurance', $section_key );
										break;
									case ( false !== strpos( $section_key, 'wfacp_promises_' ) ):
										$this->get_module( $data, false, 'promises', $section_key );
										break;
									case ( false !== strpos( $section_key, 'wfacp_customer_' ) ):
										$this->get_module( $data, false, 'customer-support', $section_key );
										break;
									case ( false !== strpos( $section_key, 'wfacp_html_widget_' ) ):
										$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
										break;

								}
							}
							echo '</div>';
						}
						?>
                    </div>
                </div>
                <!-- wfacp-form panel close-->
            </div>
        </div>
        <!--content wrappre close -->

		<?php

		if ( ( isset( $header_layout_is ) && $header_layout_is != 'outside_header' ) || $this->device_type == 'mobile' || $this->device_mb_tab == 'tablet' ) {


			include( $this->wfacp_get_footer() );
		}
		?>
        <!--footer wrapper -->

        <!--footer wrapper close -->
    </div>
</div>
<!--main panel wrapper close -->
