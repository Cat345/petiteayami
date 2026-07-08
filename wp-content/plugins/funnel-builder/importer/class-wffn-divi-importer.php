<?php

/**
 * Divi Importer
 *
 * @since 1.0.0
 */


if ( ! class_exists( 'WFFN_Divi_Importer' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Divi_Importer implements WFFN_Import_Export {

		public function __construct() {
			add_action( 'wffn_design_saved', array( $this, 'set_builder' ), 10, 2 );
			add_action( 'woofunnels_module_template_removed', array( $this, 'delete_divi_data' ) );
			// Dont Need To call Parent Constructor because of some time other divi addon created fatal error Like Monarch Plugin.
		}

		protected function set_filesystem() {
			global $wp_filesystem;

			add_filter( 'filesystem_method', array( $this, 'replace_filesystem_method' ) );
			WP_Filesystem();

			return $wp_filesystem;
		}

		public function replace_filesystem_method() {
			return 'direct';
		}

		/**
		 * Proxy method for set_filesystem() to avoid calling it multiple times.
		 *
		 * @return WP_Filesystem_Direct
		 * @since 4.0
		 */
		protected function get_filesystem() {
			static $filesystem = null;

			if ( null === $filesystem ) {
				$filesystem = $this->set_filesystem();
			}

			return $filesystem;
		}

		/**
		 * Get timestamp or create one if it isn't set.
		 *
		 * @since 2.7.0
		 */
		public function get_timestamp() {
			et_core_nonce_verified_previously();

			return isset( $_POST['timestamp'] ) && ! empty( $_POST['timestamp'] ) ? sanitize_text_field( $_POST['timestamp'] ) : time(); //phpcs:ignore
		}

		public function import( $module_id, $export_content = '' ) {
			$status = $this->import_template_single( $module_id, $export_content );

			return $status;
		}

		public function import_template_single( $post_id, $content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);

			delete_post_meta( $post_id, '_elementor_edit_mode' );
			delete_post_meta( $post_id, '_fl_builder_enabled' );
			update_post_meta( $post_id, '_et_pb_use_builder', 'on' );

			if ( ! is_array( $content ) && is_string( $content ) ) {
				try {
					$content = json_decode( $content, true );
				} catch ( Exception $error ) {
					return false;
				}
			}

			$data = $content['data'];
			// Pass the post content and let js save the post.

			$data = reset( $data );

			require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			if ( WFFN_Content_Validator::contains_php_code( $data ) || WFFN_Content_Validator::contains_dangerous_tags( $data ) ) {
				return false;
			}

			$success = true;
			$result  = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $data,
				)
			);

			if ( $result instanceof WP_Error ) {
				$success = false;
			}

			return $success;
		}

		public function set_builder( $post_id, $selected_type ) {
			if ( 'divi' === $selected_type ) {
				update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
			}
		}

		public function export( $module_id, $slug ) { //phpcs:ignore
			$post = get_post( $module_id );

			return $post->post_content;
		}

		public function delete_divi_data( $post_id ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			delete_post_meta( $post_id, 'et_enqueued_post_fonts' );
		}
	}

	if ( class_exists( 'WFFN_Template_Importer' ) ) {
		WFFN_Template_Importer::register( 'divi', new WFFN_Divi_Importer() );
	}
}
