<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var $this WFACP_Template_Common
 */


$promise_data = array();

foreach ( $this->active_sidebar() as $layout_index => $section_key ) {
	$data = $this->customizer_fields_data[ $section_key ];
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
	}elseif ( strpos( $section_key, 'wfacp_html_widget_' ) !== false ) {
		$this->get_module( $data, false, 'wfacp_html_widget', $section_key );
	}
}

