<!--main panel wrapper open -->
<div class="wrapper wfacp-main-container">
    <div class="wfacp-wrapper-decoration">


        <!--header section wrapper -->

		<?php include( $this->wfacp_get_header() ); ?>

        <!--header section wrapper close -->
        <!-- contener wrapper open -->
        <div class="wfacp-panel-wrapper ">
            <div class="wfacp-middle-container wfacp-contenter-inner-wrapper">

				<?php
				if ( $this->device_type != 'mobile' ) {
					?>
                    <div class="wfacp-form-panel clearfix">
                        <!--about panel-->
						<?php include( $this->wfacp_get_product() ); ?>
                        <!-- about panel close-->

                        <!--wfacp-form panel -->

                        <div class="wfacp-form">
                            <div class="wfacp-comm-wrapper clearfix">
                                <!--left wrapper -->
                                <div class="wfacp-left-wrapper">
                                    <div class="wfacp-left-panel clearfix">

										<?php include( $this->wfacp_get_form() ); ?>
                                    </div>
                                </div>
                                <!-- left wrapper close-->


                                <!-- right wrapper -->
                                <div class="wfacp-right-panel clearfix">
									<?php include( $this->wfacp_get_sidebar() ); ?>
                                    <!--right wrapper -->
                                </div>
                            </div>
                        </div>


                    </div>

					<?php
				} else {
					$mobile_layout_order = $this->mobile_layout_order();

					echo "<div class='wfacp-form-panel'>";
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
								echo '<div class=wfacp-form>';
								echo '   <div class="wfacp-left-wrapper clearfix">';
								include( $this->wfacp_get_form() );
								echo '</div>';
								echo '</div>';
								break;

							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_benefits_' ) ):
								$this->get_module( $data, false, 'benefits', $section_key );
								break;
							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_testimonials_' ) ):
								$this->get_module( $data, false, 'testimonials', $section_key );
								break;
							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_assurance_' ) ):
								$this->get_module( $data, false, 'assurance', $section_key );
								break;
							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_promises_' ) ):
								$this->get_module( $data, false, 'promises', $section_key );
								break;
							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_customer_' ) ):
								$this->get_module( $data, false, 'customer-support', $section_key );
								break;
							case ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_html_widget_' ) ):
								$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
								break;

						}

					}
					echo '</div>';
				}
				?>


                <!-- wfacp-form panel close-->

                <!-- testimonial panel -->

				<?php


				if ( ( is_array( $this->excluded_other_widget() ) && count( $this->excluded_other_widget() ) > 0 ) && $this->device_type != 'mobile' ) {


					echo '<div class=wfacp_sub_foo_sec>';
					foreach ( $this->excluded_other_widget() as $key => $value ) {
						$data        = array();
						$section_key = $value;
						$data        = $this->customizer_fields_data[ $section_key ];


						if ( ! is_null( $section_key ) && false !== strpos( $section_key, 'wfacp_html_widget_' ) ) {

							$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
						} else {
							$this->get_module( $data, false, 'testimonials', $section_key );
						}

					}
					echo '</div>';
				}
				?>
                <!-- testimonial panel close -->
            </div>
        </div>
        <!--content wrappre close -->

        <!--footer wrapper -->
		<?php include( $this->wfacp_get_footer() ); ?>
        <!--footer wrapper close -->
    </div>
</div>
<!--main panel wrapper close -->