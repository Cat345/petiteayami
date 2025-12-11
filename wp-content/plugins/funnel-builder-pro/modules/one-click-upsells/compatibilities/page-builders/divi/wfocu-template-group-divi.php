<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Divi' ) ) {
	class WFOCU_Template_Group_Divi extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $prefix = 'divi';
		public $listing_index = 2;

		public function __construct() {
			parent::__construct();
			add_action( 'divi_extensions_init', [ $this, 'init_extension' ] );
		}

		public function get_nice_name() {
			return __( 'Divi', 'woofunnels-upstroke-one-click-upsell' );
		}

		public function get_slug() {
			return 'divi';
		}

		protected function get_template_divi() {
			return plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi/class-wfocu-template-divi.php';
		}

		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			foreach ( $template as $temp_key => $temp_val ) {
				if ( empty( $temp_val ) ) {
					continue;
				}
				$temp_val = wp_parse_args( $temp_val, array(
					'path' => $this->get_template_divi(),
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
				'p'         => '{{offer_id}}',
				'et_fb'     => '1',
				'PageSpeed' => 'off',
			], site_url() );
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p' => '{{offer_id}}',
			], site_url() );
		}


		public function update_template( $template, $offer, $offer_settings ) {
			wp_update_post( [
				'ID'           => $offer,
				'post_content' => '',
			] );

			delete_post_meta( $offer, '_elementor_edit_mode' );
			delete_post_meta( $offer, '_fl_builder_enabled' );
			update_post_meta( $offer, '_et_pb_use_builder', 'on' );
			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}


			$response = WFOCU_Common::check_builder_status( 'divi' );
			if ( true === $response['found'] && empty( $response['error'] ) ) {
				$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );
				if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
					return $get_template_json['error'];
				}
				require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi/class-wfocu-divi-importer.php';

				$obj = new WFOCU_Divi_Importer();
				$obj->single_template_import( $offer, $get_template_json, $offer_settings );
			}

			return true;
		}

		public function get_template_path() {
			return plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi/class-wfocu-template-divi.php';
		}

		public function handle_remote_import( $data ) {

			return is_string( $data ) ? $data : json_encode( $data );
		}

		public function handle_remote_import_error( $data ) {
			return $data;
		}

		public function init_extension() {

			if ( wp_doing_ajax() ) {

				$post_type = WFOCU_Common::get_offer_post_type_slug();
				if ( isset( $_REQUEST['action'] ) && "et_fb_get_saved_templates" == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) {
					return;
				}

				if ( isset( $_REQUEST['action'] ) && "et_fb_update_builder_assets" == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) {
					return;
				}

				$post_id = 0;
				if ( isset( $_REQUEST['action'] ) && "heartbeat" == $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) {
					if ( isset( $_REQUEST['data']['et'] ) ) {
						$post_id = $_REQUEST['data']['et']['post_id'];

					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) {
					$post_id = absint( $_REQUEST['post_id'] );
				}
				if ( isset( $_REQUEST['et_post_id'] ) ) {
					$post_id = absint( $_REQUEST['et_post_id'] );
				}
				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( is_null( $post ) || $post->post_type !== $post_type ) {
						return;
					}
				}
			}


			include __DIR__ . '/class-wfocu-divi-extension.php';

		}

	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Divi, 'divi' );
}