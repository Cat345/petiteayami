<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
	return;
}

if ( ! class_exists( 'WFTY_Template_Group_Divi5' ) ) {
	/**
	 * Template group for Thank You Page Divi 5
	 */
	#[\AllowDynamicProperties]
	class WFTY_Template_Group_Divi5 {
		public $allow_empty_template = true;
		public $prefix               = 'divi-5';
		public $listing_index        = 2;

		public function __construct() {
			add_action( 'init', array( $this, 'register_template_group' ), 20 );
		}

		public function register_template_group() {
			if ( ! function_exists( 'WFFN_Core' ) || ! WFFN_Core()->thank_you_pages ) {
				return;
			}
			$template = array(
				'slug'        => 'divi',
				'title'       => __( 'Divi', 'funnel-builder' ),
				'button_text' => __( 'Edit', 'funnel-builder' ),
				'edit_url'    => add_query_arg(
					array(
						'p'         => '{{post_id}}',
						'et_fb'     => '1',
						'PageSpeed' => 'off',
					),
					site_url()
				),
			);
			WFFN_Core()->thank_you_pages->register_template_type( $template );
			$this->load_templates();
		}

		public function get_nice_name() {
			return __( 'Divi', 'funnel-builder' );
		}

		public function get_slug() {
			return 'divi';
		}

		protected function get_template_divi() {
			return plugin_dir_path( __FILE__ ) . 'class-wfty-template-divi5.php';
		}

		public function load_templates() {
			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );
			foreach ( $template as $temp_key => $temp_val ) {
				if ( empty( $temp_val ) ) {
					continue;
				}
				$temp_val = wp_parse_args(
					$temp_val,
					array( 'path' => $this->get_template_divi() )
				);
				WFFN_Core()->thank_you_pages->register_template( $temp_key, $temp_val, 'divi' );
			}
		}

		public function local_templates() {
			return $this->get_empty_template();
		}

		public function get_empty_template() {
			return array(
				'divi_1' => array(
					'type'               => 'view',
					'import'             => 'no',
					'show_import_popup'  => 'no',
					'slug'               => 'divi_1',
					'build_from_scratch' => true,
				),
			);
		}

		public function get_remote_templates() {
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['wc_thankyou'] ) ? $templates['wc_thankyou'] : array();
			$remote    = array();
			if ( isset( $designs['divi'] ) && is_array( $designs['divi'] ) ) {
				foreach ( $designs['divi'] as $d_key => $template_data ) {
					if ( isset( $template_data['pro'] ) && 'yes' === $template_data['pro'] ) {
						$template_data['license_exist'] = WFFN_Core()->admin->get_license_status();
					}
					$remote[ $d_key ] = $template_data;
				}
			}
			return $remote;
		}

		public function get_edit_link() {
			return add_query_arg(
				array(
					'p'         => '{{post_id}}',
					'et_fb'     => '1',
					'PageSpeed' => 'off',
				),
				site_url()
			);
		}

		public function get_preview_link() {
			return add_query_arg( array( 'p' => '{{post_id}}' ), site_url() );
		}

		public function update_template( $template, $post_id, $settings ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			delete_post_meta( $post_id, '_elementor_edit_mode' );
			delete_post_meta( $post_id, '_fl_builder_enabled' );
			update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}
			$response = WFFN_Common::check_builder_status( 'divi' );
			if ( true === $response['found'] && empty( $response['error'] ) ) {
				$get_template_json = apply_filters( 'wffn_thankyou_template_json', array(), $template, $this->get_slug() );
				if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
					return $get_template_json['error'];
				}
				require_once plugin_dir_path( __FILE__ ) . 'class-wfty-divi5-importer.php';
				$obj = new WFTY_Divi5_Importer();
				$obj->single_template_import( $post_id, $get_template_json, $settings );
			}
			return true;
		}

		public function if_current_template_is_empty( $template ) {
			return isset( $template['build_from_scratch'] ) && true === $template['build_from_scratch'];
		}

		public function get_template_path() {
			return plugin_dir_path( __FILE__ ) . 'class-wfty-template-divi5.php';
		}
	}
	new WFTY_Template_Group_Divi5();
}
