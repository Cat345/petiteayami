<?php

/**
 * Elementor template library local source.
 *
 * Elementor template library local source handler class is responsible for
 * handling local Elementor templates saved by the user locally on his site.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'WFACP_Customizer_Importer' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Customizer_Importer implements WFACP_Import_Export {
		private $builder = 'pre_built';
		private $settings_file = '';

		public function __construct() {

		}

		public function import( $aero_id, $slug, $is_multi = 'no' ) {
			WFACP_Common::delete_page_layout( $aero_id );

			if ( $slug === 'shopcheckout' ) {

				$pageProductSetting = [
					'coupons'                             => '',
					'enable_coupon'                       => 'false',
					'disable_coupon'                      => 'false',
					'hide_quantity_switcher'              => 'false',
					'enable_delete_item'                  => 'false',
					'hide_product_image'                  => 'false',
					'is_hide_additional_information'      => 'true',
					'additional_information_title'        => WFACP_Common::get_default_additional_information_title(),
					'hide_quick_view'                     => 'false',
					'hide_you_save'                       => 'true',
					'hide_best_value'                     => 'false',
					'best_value_product'                  => '',
					'best_value_text'                     => __( 'Best Value', 'woofunnels-aero-checkout' ),
					'best_value_position'                 => 'above',
					'enable_custom_name_in_order_summary' => 'false',
					'autocomplete_enable'                 => 'false',
					'autocomplete_google_key'             => '',
					'preferred_countries_enable'          => 'false',
					'preferred_countries'                 => '',
					'product_switcher_template'           => 'default',
				];

				$product_settings                     = [];
				$product_settings['settings']         = $pageProductSetting;
				$product_settings['products']         = [];
				$product_settings['default_products'] = [];

				if ( is_array( $product_settings ) && count( $product_settings ) > 0 ) {
					update_post_meta( $aero_id, '_wfacp_product_switcher_setting', $product_settings );
				}
			}
			$templates = WFACP_Core()->template_loader->get_templates( $this->builder );


			$data = WFACP_Core()->importer->get_remote_template( $slug, 'pre-built' );

			if ( isset( $data['error'] ) ) {
				return $data;
			}
			if ( isset( $templates[ $slug ]['settings_file'] ) ) {
				$this->settings_file = $templates[ $slug ]['settings_file'];
				$file_path           = __DIR__ . '/checkout-settings/' . $this->settings_file;
				WFACP_Common::import_checkout_settings( $aero_id, $file_path );
			}

			return [ 'status' => true ];
		}


		public function export( $aero_id, $slug ) {
			return [];
		}


	}

	if ( class_exists( 'WFACP_Template_Importer' ) ) {
		WFACP_Template_Importer::register( 'pre_built', new WFACP_Customizer_Importer() );
	}
}