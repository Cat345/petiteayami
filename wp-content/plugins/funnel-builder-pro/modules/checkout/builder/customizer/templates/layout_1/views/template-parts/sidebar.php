<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Pre_Built
 */


$excluded_data = array();


?>


<div class="wfacp-right-panel clearfix">
	<?php

	$active_sidebar_arr = [];


	if ( is_array( $this->excluded_layout_sections_sidebar ) && count( $this->excluded_layout_sections_sidebar ) > 0 ) {
		$active_sidebar_arr = array_merge( $this->active_sidebar(), $this->excluded_layout_sections_sidebar );

	}

	foreach ( $active_sidebar_arr as $layout_index => $section_key ) {
		$data = [];

		if ( ! isset( $this->customizer_fields_data[ $section_key ] ) ) {

			continue;
		}

		$data = $this->customizer_fields_data[ $section_key ];

		if ( strpos( $section_key, 'wfacp_benefits_' ) !== false ) {

			$this->get_module( $data, false, 'benefits', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_testimonials_' ) !== false ) {


			$this->get_module( $data, false, 'testimonials', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_assurance_' ) !== false ) {


			$this->get_module( $data, false, 'assurance', $section_key );

		} elseif ( strpos( $section_key, 'wfacp_promises_' ) !== false ) {
			$excluded_data[ $section_key ] = $data;
		} elseif ( strpos( $section_key, 'wfacp_customer_' ) !== false ) {


			$this->get_module( $data, false, 'customer-support', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_html_widget_' ) !== false ) {

			$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
		}
	}
	?>

</div>
<?php


if ( is_array( $this->excluded_other_widget() ) && count( $this->excluded_other_widget() ) > 0 ) {
	foreach ( $this->excluded_other_widget() as $key => $val ) {
		$data = [];
		if ( array_key_exists( $val, $this->wfacp_html_fields ) ) {
			continue;
		}



		if ( isset( $excluded_data[ $val ] ) && $excluded_data[ $val ] != '' ) {
			$data = $excluded_data[ $val ];
			if ( is_array( $data ) && count( $data ) > 0 ) {
				$this->get_module( $data, false, 'promises', $section_key );

			}
		}
	}
}


?>
