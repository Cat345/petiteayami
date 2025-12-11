<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Elementor' ) ) {
	class WFOCU_Template_Group_Elementor extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $prefix = 'el';
		public $listing_index = 1;

		public function get_nice_name() {
			return __( 'Elementor', 'woofunnels-upstroke-one-click-upsell' );
		}

		public function get_slug() {
			return 'elementor';
		}

		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			foreach ( $template as $temp_key => $temp_val ) {

				$temp_val = wp_parse_args( $temp_val, array(
					'path' => plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/class-wfocu-template-elementor.php',
				) );

				WFOCU_Core()->template_loader->register_template( $temp_key, $temp_val );
			}
			$this->maybe_register_empty( plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/class-wfocu-template-elementor.php' );

		}

		public function local_templates() {
			$template = [];

			return $template;
		}

		public function get_edit_link() {
			return add_query_arg( [
				'post'   => '{{offer_id}}',
				'action' => 'elementor',
			], admin_url( 'post.php' ) );
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p' => '{{offer_id}}',
			], site_url() );
		}


		public function update_template( $template, $offer, $offer_settings ) {
			delete_post_meta( $offer, '_elementor_data' );
			delete_post_meta( $offer, '_elementor_version' );
			delete_post_meta( $offer, '_et_pb_use_builder' );

			wp_update_post( [
				'ID'           => $offer,
				'post_content' => '',
			] );

			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				if ( version_compare( ELEMENTOR_VERSION, '3.1.0', '<=' ) ) {
					\Elementor\Plugin::$instance->db->set_is_elementor_page( $offer, true );
				} else {
					\Elementor\Plugin::$instance->documents->get( $offer )->set_is_built_with_elementor( true );
				}
			}

			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}

			$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );
			if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
				return $get_template_json['error'];
			}
			require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/class-wfocu-elementor-importer.php';

			$obj = new WFOCU_Importer_Elementor();
			$obj->single_template_import( $offer, $get_template_json, $offer_settings );
			$this->clear_cache( $offer );

			return true;
		}

		public function clear_cache( $offer_id_new ) {
			if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
				return;
			}
			Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		public function handle_remote_import( $data ) {
			return is_string( $data ) ? $data : json_encode( $data );
		}

		public function handle_remote_import_error( $data ) {
			return $data;
		}

		public function get_template_path() {
			return plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/class-wfocu-template-elementor.php';

		}


	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Elementor, 'elementor' );
}