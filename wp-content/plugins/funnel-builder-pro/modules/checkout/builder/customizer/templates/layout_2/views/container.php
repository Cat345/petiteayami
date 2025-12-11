<!--main panel wrapper open -->
<div class="wrapper wfacp-main-container">
	<div class="wfacp-wrapper-decoration">
		<!--header section wrapper -->
		<?php include( $this->wfacp_get_header() ); ?>
		<!--header section wrapper close -->

		<?php
		if ( is_array( $mobile_layout_order ) && count( $mobile_layout_order ) > 0 ) {
			?>

			<!-- contener wrapper open -->
			<div class="wfacp-panel-wrapper clearfix <?php echo $selected_template_slug . '_temp'; ?>">
				<div class="wfacp-container wfacp-contenter-inner-wrapper clearfix">
					<div class="wfacp-form-panel clearfix">

						<div class="wfacp-comm-wrapper clearfix <?php echo $selected_template_slug; ?>_temp">
							<div class="wfacp-right-wrapper">
								<?php
								foreach ( $mobile_layout_order as $key => $value ) {
									$section_key = $value;

									if ( isset( $this->customizer_fields_data[ $section_key ] ) ) {
										$data = $this->customizer_fields_data[ $section_key ];
										switch ( $value ) {
											case 'wfacp_product':
												include( $this->wfacp_get_product() );
												break;
											case 'wfacp_form':
												echo "<div class='wfacp-form clearfix'>";
												echo '   <div class="wfacp-left-wrapper clearfix">';
												include( $this->wfacp_get_form() );
												echo '</div>';
												echo '</div>';
												break;

											case (!is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_benefits_' ) ):
												$this->get_module( $data, false, 'benefits', $section_key );
												break;
											case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_testimonials_' ) ):
												$this->get_module( $data, false, 'testimonials', $section_key );
												break;
											case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_assurance_' ) ):
												$this->get_module( $data, false, 'assurance', $section_key );
												break;
											case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_promises_' ) ):
												$this->get_module( $data, false, 'promises', $section_key );
												break;
											case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_customer_' ) ):
												$this->get_module( $data, false, 'customer-support', $section_key );
												break;
											case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_html_widget_' ) ):
												$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
												break;

										}
									}
								}
								?>
							</div>
						</div>

					</div>
					<!-- wfacp-form panel close-->
				</div>
			</div>
			<!--content wrappre close -->
			<?php
		}
		?>

		<!--footer wrapper -->
		<?php include( $this->wfacp_get_footer() ); ?>
		<!--footer wrapper close -->
	</div>
</div>
<!--main panel wrapper close -->