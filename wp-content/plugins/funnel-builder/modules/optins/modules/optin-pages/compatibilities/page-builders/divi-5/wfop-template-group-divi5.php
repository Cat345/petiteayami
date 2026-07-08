<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if Divi 5 is enabled before loading
if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
	return;
}

if ( ! class_exists( 'WFOP_Template_Group_Divi5' ) ) {
	/**
	 * Class WFOP_Template_Group_Divi5
	 * Template group for Divi 5 optin pages
	 */
	#[\AllowDynamicProperties]
	class WFOP_Template_Group_Divi5 {
		public $allow_empty_template = true;
		public $prefix               = 'divi-5';
		public $listing_index        = 2;

		public function __construct() {
			// Register template group
			add_action( 'init', array( $this, 'register_template_group' ), 20 );
		}

		public function register_template_group() {
			if ( ! function_exists( 'WFOPP_Core' ) ) {
				return;
			}

			$template = array(
				'slug'        => 'divi',
				'title'       => __( 'Divi', 'funnel-builder' ),
				'button_text' => __( 'Edit', 'funnel-builder' ),
				'edit_url'    => add_query_arg(
					array(
						'p'         => '{{optin_id}}',
						'et_fb'     => '1',
						'PageSpeed' => 'off',
					),
					site_url()
				),
			);

			WFOPP_Core()->optin_pages->register_template_type( $template );
			$this->load_templates();
		}

		public function get_nice_name() {
			return __( 'Divi', 'funnel-builder' );
		}

		public function get_slug() {
			return 'divi';
		}

		protected function get_template_divi() {
			return plugin_dir_path( __FILE__ ) . 'class-wfop-template-divi5.php';
		}

		public function load_templates() {
			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			foreach ( $template as $temp_key => $temp_val ) {
				if ( empty( $temp_val ) ) {
					continue;
				}
				$temp_val = wp_parse_args(
					$temp_val,
					array(
						'path' => $this->get_template_divi(),
					)
				);
				WFOPP_Core()->optin_pages->register_template( $temp_key, $temp_val, 'divi' );
			}
		}

		public function local_templates() {
			$template = $this->get_empty_template();

			return $template;
		}

		public function get_empty_template() {
			return array(
				'divi_1' => array(
					'type'               => 'view',
					'import'             => 'no',
					'show_import_popup'  => 'no',
					'slug'               => 'divi_1',
					'build_from_scratch' => true,
					'group'              => array(
						'inline',
						'popup',
					),
				),
			);
		}

		public function get_remote_templates() {
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['optin'] ) ? $templates['optin'] : array();

			$remote_templates = array();
			if ( isset( $designs['divi'] ) && is_array( $designs['divi'] ) ) {
				foreach ( $designs['divi'] as $d_key => $template_data ) {
					if ( isset( $template_data['pro'] ) && 'yes' === $template_data['pro'] ) {
						$template_data['license_exist'] = WFFN_Core()->admin->get_license_status();
					}
					$remote_templates[ $d_key ] = $template_data;
				}
			}

			return $remote_templates;
		}

		public function get_edit_link() {
			return add_query_arg(
				array(
					'p'         => '{{optin_id}}',
					'et_fb'     => '1',
					'PageSpeed' => 'off',
				),
				site_url()
			);
		}

		public function get_preview_link() {
			return add_query_arg(
				array(
					'p' => '{{optin_id}}',
				),
				site_url()
			);
		}

		public function update_template( $template, $optin_id, $optin_settings ) {
			wp_update_post(
				array(
					'ID'           => $optin_id,
					'post_content' => '',
				)
			);

			delete_post_meta( $optin_id, '_elementor_edit_mode' );
			delete_post_meta( $optin_id, '_fl_builder_enabled' );
			update_post_meta( $optin_id, '_et_pb_use_builder', 'on' );

			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}

			$response = WFFN_Common::check_builder_status( 'divi' );
			if ( true === $response['found'] && empty( $response['error'] ) ) {
				$get_template_json = WFOPP_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );
				if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
					return $get_template_json['error'];
				}
				require_once plugin_dir_path( __FILE__ ) . 'class-wfop-divi5-importer.php';

				$obj = new WFOP_Divi5_Importer();
				$obj->single_template_import( $optin_id, $get_template_json, $optin_settings );
			}

			return true;
		}

		public function if_current_template_is_empty( $template ) {
			return isset( $template['build_from_scratch'] ) && true === $template['build_from_scratch'];
		}

		public function get_template_path() {
			return plugin_dir_path( __FILE__ ) . 'class-wfop-template-divi5.php';
		}

		public function handle_remote_import( $data ) {
			return is_string( $data ) ? $data : json_encode( $data );
		}

		public function handle_remote_import_error( $data ) {
			return $data;
		}
	}

	// Initialize template group
	new WFOP_Template_Group_Divi5();
}
