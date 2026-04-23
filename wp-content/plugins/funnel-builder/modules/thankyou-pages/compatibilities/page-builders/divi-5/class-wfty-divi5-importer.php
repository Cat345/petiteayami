<?php
/**
 * Divi 5 Importer for Thank You Pages
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ET_Core_Portability' ) && defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
	include_once ET_BUILDER_PLUGIN_DIR . '/core/components/Portability.php';
}

if ( class_exists( 'ET_Core_Portability' ) && ! class_exists( 'WFTY_Divi5_Importer' ) ) {
	class WFTY_Divi5_Importer extends ET_Core_Portability {

		public function __construct() {
			add_action( 'wffn_thankyou_template_removed', array( $this, 'delete_divi_data' ) );
		}

		/**
		 * Import a single template into a thank you page.
		 *
		 * @param int   $post_id Thank you page post ID.
		 * @param array $content Template content (with 'data' key).
		 * @param array $settings Optional settings.
		 * @return bool
		 */
		public function single_template_import( $post_id, $content, $settings = array() ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			if ( method_exists( $this, 'prevent_failure' ) ) {
				$this->prevent_failure();
			}
			if ( property_exists( 'ET_Core_Portability', '_doing_import' ) ) {
				ET_Core_Portability::$_doing_import = true;
			}
			if ( ! is_array( $content ) && is_string( $content ) ) {
				$content = json_decode( $content, true );
			}
			if ( empty( $content['data'] ) ) {
				return false;
			}
			$data = is_array( $content['data'] ) ? reset( $content['data'] ) : $content['data'];

			// D5 block content has JSON unicode escapes (u003c for <).
			// wp_slash() so they survive WordPress wp_unslash() on retrieval.
			$data = wp_slash( $data );

			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $data,
				)
			);
			return ! ( $result instanceof WP_Error );
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
}
