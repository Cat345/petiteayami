<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Oxygen' ) ) {
	class WFOCU_Template_Group_Oxygen extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $prefix = 'oxy';
		public $listing_index = 4;

		public function __construct() {
			parent::__construct();
			$this->init_extension();
		}

		public function get_nice_name() {
			return __( 'Oxygen Classic', 'woofunnels-upstroke-one-click-upsell' );
		}

		public function get_slug() {
			return 'oxy';
		}

		protected function get_template_oxygen() {
			return __DIR__ . '/class-wfocu-template-oxygen.php';
		}

		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );


			foreach ( $template as $temp_key => $temp_val ) {
				if ( empty( $temp_val ) ) {
					continue;
				}
				$temp_val = wp_parse_args( $temp_val, array(
					'path' => $this->get_template_oxygen(),
				) );
				WFOCU_Core()->template_loader->register_template( $temp_key, $temp_val );
			}
		}

		public function local_templates() {
			$template = $this->get_empty_template();

			return $template;
		}

		public function get_edit_link() {
			return add_query_arg( [
				'p'            => '{{offer_id}}',
				'oxy_wfocu_id' => '{{offer_id}}',
				'ct_builder'   => 'true'
			], site_url() );
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p' => '{{offer_id}}',
			], site_url() );
		}


		public function update_template( $template, $offer, $offer_settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			delete_post_meta( $offer, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ) );
			delete_post_meta( $offer, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_json' ) );

			wp_update_post( [
				'ID'           => $offer,
				'post_content' => '',
			] );

			if ( $this->if_current_template_is_empty( $template ) ) {
				delete_post_meta( $offer, 'ct_other_template' );

				return;
			}


			$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );

			if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
				return $get_template_json['error'];
			}
			$content = $get_template_json;
			if ( ! empty( $content ) && ( false == strpos( $content, '<script' ) ) ) {
				update_post_meta( $offer, WFOCU_Common::oxy_get_meta_prefix( 'ct_other_template' ), '-1' );
				update_post_meta( $offer, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ), $content );
				$this->clear_oxy_page_cache_css( $offer );

				return [ 'status' => true ];
			}

			return true;

		}

		public function get_template_path() {
			return __DIR__ . '/class-wfocu-template-oxygen.php';
		}

		public function handle_remote_import( $data ) {

			return is_string( $data ) ? $data : json_encode( $data );//phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		}

		public function handle_remote_import_error( $data ) {
			return $data;
		}

		public function init_extension() {


			include __DIR__ . '/class-wfocu-oxygen-extension.php';

		}

		public function clear_oxy_page_cache_css( $post_id ) {

			if ( function_exists( 'oxygen_vsb_cache_universal_css' ) && function_exists( 'oxygen_vsb_delete_css_file' ) && get_option( "oxygen_vsb_universal_css_cache" ) == 'true' ) {
				/**
				 * generate universal css when oxygen cache setting is enabled and delete previous css
				 */
				oxygen_vsb_delete_css_file( $post_id );
				oxygen_vsb_cache_universal_css();
			} elseif ( function_exists( 'oxygen_vsb_cache_page_css' ) ) {

				/**
				 * generate oxygen css
				 */
				oxygen_vsb_cache_page_css( $post_id );
			}
		}

	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Oxygen, 'oxy' );
}
