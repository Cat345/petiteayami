<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Gutenberg' ) ) {
	class WFOCU_Template_Group_Gutenberg extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $prefix = 'gutenberg';
		public $listing_index = 3;
		private $post_id = null;

		public function __construct() {
			parent::__construct();
			$this->init_extension();
		}

		public function get_nice_name() {
			return __( 'Block Editor', 'woofunnels-upstroke-one-click-upsell' );
		}

		public function get_slug() {
			return 'gutenberg';
		}

		protected function get_template() {
			return __DIR__ . '/class-wfocu-template-gutenberg.php';
		}

		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );


			foreach ( $template as $temp_key => $temp_val ) {
				if ( empty( $temp_val ) ) {
					continue;
				}
				$temp_val = wp_parse_args( $temp_val, array(
					'path' => $this->get_template(),
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
				'post'   => '{{offer_id}}',
				'action' => 'edit',
			], admin_url( 'post.php' ) );
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p' => '{{offer_id}}',
			], site_url() );
		}


		public function update_template( $template, $offer, $offer_settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$this->post_id = $offer;

			wp_update_post( [
				'ID'           => $offer,
				'post_content' => '',
			] );


			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}

			$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );

			if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
				return $get_template_json['error'];
			}
			$this->delete_other_builder_data( $this->post_id );// Delete Other Builder Meta

			$get_template_json = json_decode( $get_template_json, true );

			update_post_meta( $this->post_id, '_wp_page_template', 'wfocu-canvas.php' );
			$content   = $get_template_json['post_content'];
			$meta_data = $get_template_json['meta_data'];
			if ( ! empty( $content ) ) {

				$post               = get_post( $offer );
				$post->post_content = $content;
				wp_update_post( $post );
				foreach ( $meta_data as $meta_key => $meta_value ) {
					update_post_meta( $offer, $meta_key, trim( $meta_value ) );
				}

				return [ 'status' => true ];
			}

			return true;
		}

		public function get_template_path() {
			return __DIR__ . '/class-wfocu-template-gutenberg.php';
		}

		public function handle_remote_import( $data ) {

			return is_string( $data ) ? $data : json_encode( $data );//phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		}

		public function handle_remote_import_error( $data ) {
			return $data;
		}

		public function init_extension() {


			include __DIR__ . '/class-wfocu-gutenberg-extension.php';

		}

		public function delete_other_builder_data( $post_id ) {
			delete_post_meta( $post_id, '_et_pb_use_builder' );
			delete_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_other_template' ) );
			update_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ), '' );
			update_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_json' ), '' );
			delete_post_meta( $post_id, '_elementor_edit_mode' );
			delete_post_meta( $post_id, '_elementor_data' );
		}
	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Gutenberg, 'gutenberg' );
}