<?php

/**
 * Divi 5 Importer
 *
 * Server returns D5 templates in Divi portability format:
 * {"context":"et_builder","data":{"<post_id>":"<!-- wp:divi/... -->"}}
 *
 * D5 block content contains JSON unicode escapes (u003c for <) which
 * need wp_slash() before saving so they survive WordPress wp_unslash().
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'WFFN_Divi5_Importer' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_Divi5_Importer extends WFFN_Divi_Importer {

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
				} catch ( Throwable $error ) {
					return false;
				}
			}

			if ( ! is_array( $content ) || empty( $content['data'] ) ) {
				return false;
			}

			require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			$alien_count = WFFN_Content_Validator::sanitize_response_urls( $content );
			if ( $alien_count >= 5 ) {
				return false;
			}

			$data = $content['data'];
			$data = reset( $data );

			if ( WFFN_Content_Validator::contains_php_code( $data ) || WFFN_Content_Validator::contains_dangerous_tags( $data ) ) {
				return false;
			}

			$data = wp_slash( $data );

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
	}

	if ( class_exists( 'WFFN_Template_Importer' ) ) {
		WFFN_Template_Importer::register( 'divi5', new WFFN_Divi5_Importer() );
	}
}
