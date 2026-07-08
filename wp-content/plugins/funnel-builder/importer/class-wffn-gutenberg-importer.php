<?php

if ( ! class_exists( 'WFFN_Gutenberg_Importer' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Gutenberg_Importer implements WFFN_Import_Export {

		public function __construct() {
			add_action( 'woofunnels_module_template_removed', array( $this, 'delete_oxy_data' ) );
		}

		public function import( $module_id, $content = '' ) {
			if ( ! empty( $content ) ) {
				$data = json_decode( $content, true );

				if ( ! is_array( $data ) || ! isset( $data['post_content'], $data['meta_data'] ) ) {
					return array( 'success' => false );
				}

				$post_content = $data['post_content'];
				require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

				// Remote template content must pass the same dangerous-content checks the Divi/Oxygen importers use.
				if ( WFFN_Content_Validator::contains_php_code( $post_content ) || WFFN_Content_Validator::contains_dangerous_tags( $post_content ) ) {
					return array( 'success' => false );
				}

				$meta_data = WFFN_Content_Validator::sanitize_meta_keys( $data['meta_data'] );

				$content = $post_content;
				foreach ( $meta_data as $meta_key => $meta_value ) {
					update_post_meta( $module_id, $meta_key, trim( $meta_value ) );
				}
			}
			$post               = get_post( $module_id );
			$post->post_content = $content;
			wp_update_post( $post );

			return array( 'success' => true );
		}

		public function import_template_single( $module_id, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		}

		public function export( $module_id, $slug ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return get_post_meta( $module_id, WFFN_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ), true );
		}

		public function delete_oxy_data( $post_id ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
		}
	}

	if ( class_exists( 'WFFN_Gutenberg_Importer' ) ) {
		WFFN_Template_Importer::register( 'gutenberg', new WFFN_Gutenberg_Importer() );
	}
}
