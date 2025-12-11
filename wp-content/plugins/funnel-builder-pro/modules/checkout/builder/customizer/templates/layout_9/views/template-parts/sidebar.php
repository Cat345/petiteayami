<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


$promise_data = array();


if ( is_array( $this->active_sidebar() ) && count( $this->active_sidebar() ) > 0 ) {
	echo ' <div class="wfacp-right-panel wfacp_mini_cart_start_h">';
	foreach ( $this->active_sidebar() as $layout_index => $section_key ) {
		$data = isset( $this->customizer_fields_data[ $section_key ] ) ? $this->customizer_fields_data[ $section_key ] : [];


		if ( strpos( $section_key, 'wfacp_benefits_' ) !== false ) {
			$this->get_module( $data, false, 'benefits', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_testimonials_' ) !== false ) {
			$this->get_module( $data, false, 'testimonials', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_assurance_' ) !== false ) {
			$this->get_module( $data, false, 'assurance', $section_key );

		} elseif ( strpos( $section_key, 'wfacp_promises_' ) !== false ) {
			$promise_data[ $section_key ] = $data;
			$this->get_module( $data, false, 'promises', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_customer_' ) !== false ) {
			$this->get_module( $data, false, 'customer-support', $section_key );
		} elseif ( strpos( $section_key, 'wfacp_cart' ) !== false ) {
			do_action( 'wfacp_before_sidebar_content_section' );
		} elseif ( strpos( $section_key, 'wfacp_product' ) !== false ) {
			include( $this->wfacp_get_product() );
		} elseif ( strpos( $section_key, 'wfacp_html_widget_' ) !== false ) {

			$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
		}
	}
	echo '</div>';
}


