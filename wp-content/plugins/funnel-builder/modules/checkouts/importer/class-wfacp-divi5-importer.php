<?php

/**
 * Divi 5 template importer for Checkout.
 *
 * Server returns divi5 templates in Divi portability format:
 * {"context":"et_builder","data":{"<post_id>":"<!-- wp:divi/... -->"}}
 *
 * This importer handles that format directly instead of using the
 * D4 importer's {"data": [...]} format.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'WFACP_Divi5_Importer' ) ) {

	#[\AllowDynamicProperties]
	class WFACP_Divi5_Importer extends WFACP_Divi_Importer {

		protected $builder = 'divi5';

		public function import_child( $aero_id, $slug, $is_multi = 'no' ) {
			set_time_limit( 0 ); //phpcs:ignore
			$this->slug    = $slug;
			$this->post_id = $aero_id;

			$this->update_product_switcher_settings();

			update_post_meta( $aero_id, '_et_pb_use_builder', 'on' );

			// Try divi5 first, fall back to divi for shared slugs.
			$templates = WFACP_Core()->template_loader->get_templates( 'divi5' );
			if ( ! isset( $templates[ $slug ] ) ) {
				$templates = WFACP_Core()->template_loader->get_templates( 'divi' );
			}
			if ( isset( $templates[ $slug ]['settings_file'] ) ) {
				$this->settings_file = $templates[ $slug ]['settings_file'];
			}
			if ( isset( $templates[ $slug ] ) && isset( $templates[ $slug ]['build_from_scratch'] ) ) {
				wp_update_post(
					array(
						'ID'           => $this->post_id,
						'post_content' => '',
					)
				);
				$this->delete_template_data( $this->post_id );
				update_post_meta( $this->post_id, '_wp_page_template', 'wfacp-canvas.php' );
				$this->save_data( $aero_id );

				return array( 'status' => true );
			}

			// Use 'divi5' for remote fetch — server returns real D5 content
			$data = WFACP_Core()->importer->get_remote_template( $slug, $this->builder );

			if ( isset( $data['error'] ) ) {
				return $data;
			}

			$content = isset( $data['data'] ) ? $data['data'] : '';
			if ( ! empty( $content ) ) {
				$status = $this->import_divi5_template( $content );
				$this->save_data( $this->post_id );

				return array( 'status' => $status );
			}

			return array( 'status' => true );
		}

		/**
		 * Import Divi 5 template content.
		 *
		 * Server returns D5 templates in Divi portability format:
		 * {"context":"et_builder","data":{"<post_id>":"<!-- wp:divi/... -->"}}
		 *
		 * @param string $content JSON string from the remote server.
		 *
		 * @return bool
		 */
		public function import_divi5_template( $content ) {

			$import = json_decode( $content, true );
			if ( ! is_array( $import ) || ! isset( $import['data'] ) ) {
				return false;
			}

			$data         = $import['data'];
			$post_content = reset( $data );

			// Remote template content must pass the same dangerous-content checks the other importers use.
			require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			if ( WFFN_Content_Validator::contains_php_code( $post_content ) || WFFN_Content_Validator::contains_dangerous_tags( $post_content ) ) {
				return false;
			}

			// D5 block content contains JSON unicode escapes (u003c for <, u003e for >).
			// WordPress wp_unslash() strips the backslash on DB retrieval, leaving bare u003c.
			// wp_slash() the content before saving so the escapes survive the round-trip.
			$post_content = wp_slash( $post_content );

			$success = true;
			$result  = wp_update_post(
				array(
					'ID'           => $this->post_id,
					'post_content' => $post_content,
				)
			);
			if ( $result instanceof WP_Error ) {
				$success = false;
			}

			return $success;
		}
	}

	if ( class_exists( 'WFACP_Template_Importer' ) ) {
		WFACP_Template_Importer::register( 'divi5', new WFACP_Divi5_Importer( 'et_theme_builder' ) );
	}
}
