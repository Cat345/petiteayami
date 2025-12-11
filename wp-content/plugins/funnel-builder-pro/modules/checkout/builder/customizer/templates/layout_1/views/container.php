<!--main panel wrapper open -->
<div class="wrapper wfacp-main-container">
    <div class="wfacp-wrapper-decoration">
        <!--header section wrapper -->
		<?php include( $this->wfacp_get_header() ); ?>
        <!--header section wrapper close -->


        <!-- contener wrapper open -->
        <div class="wfacp-panel-wrapper <?php echo $selected_template_slug . '_temp'; ?>">
            <div class="wfacp-container wfacp-contenter-inner-wrapper clearfix">

				<?php
				if ( $this->device_type != 'mobile' ) {
					?>

                    <!--about panel-->
					<?php include( $this->wfacp_get_product() ); ?>
                    <!-- about panel close-->

					<?php
					$sidebar_active = false;
					$left_class     = 'wfacp-left-wrapper-full';
					if ( is_array( $this->active_sidebar() ) && count( $this->active_sidebar() ) > 0 ) {
						$sidebar_active = true;
						$left_class     = '';
					}
					?>

                    <!--wfacp-form panel -->
                    <div class="wfacp-form clearfix">
                        <div class="wfacp-comm-wrapper clearfix">

                            <!--left wrapper -->
                            <div class="wfacp-left-wrapper clearfix <?php echo $left_class; ?>" data-scrollto="wfacp_form_section">
								<?php
								include( $this->wfacp_get_form() );

								do_action( 'wfacp_below_form' );
								?>
                            </div>
                            <!-- left wrapper close-->

							<?php
							if ( true === $sidebar_active ) {
								echo '<!-- right wrapper -->';
								echo '<div class="wfacp-right-wrapper">';
								include( $this->wfacp_get_sidebar() );
								echo '</div>';
								echo '<!-- right wrapper close -->';
							}
							?>
                        </div>
                    </div>
                    <!-- wfacp-form panel close-->

					<?php
				} else {

					$mobile_layout_order = $this->mobile_layout_order();


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
								printf( '<div class="wfacp-form clearfix ">' );
								echo '   <div class="wfacp-left-wrapper clearfix">';
								include( $this->wfacp_get_form() );
								echo '</div>';
								echo '</div>';
								break;
							case ( !is_null($section_key) && false !== strpos( $section_key, 'wfacp_benefits_' ) ):
								$this->get_module( $data, false, 'benefits', $section_key );
								break;
							case ( !is_null($section_key) &&  false !== strpos( $section_key, 'wfacp_testimonials_' ) ):
								$this->get_module( $data, false, 'testimonials', $section_key );
								break;
							case (!is_null($section_key) && false !== strpos( $section_key, 'wfacp_assurance_' ) ):
								$this->get_module( $data, false, 'assurance', $section_key );
								break;
							case ( !is_null($section_key) && false !== strpos( $section_key, 'wfacp_promises_' ) ):
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
        <!--content wrappre close -->


        <!--footer wrapper -->

		<?php include( $this->wfacp_get_footer() ); ?>

        <!--footer wrapper close -->


    </div>
</div>
<!--main panel wrapper close -->