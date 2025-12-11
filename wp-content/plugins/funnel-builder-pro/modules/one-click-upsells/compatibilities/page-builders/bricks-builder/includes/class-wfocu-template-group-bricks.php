<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WFOCU_Template_Group' ) ) {
	return;
}
if ( ! class_exists( 'WFOCU_Template_Group_Bricks' ) ) {
	class WFOCU_Template_Group_Bricks extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $prefix = 'bricks';
		public $listing_index = 7;

		public function get_nice_name() {
			return __( 'Bricks' );
		}

		public function get_slug() {
			return 'bricks';
		}

		public function load_templates() {
			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			foreach ( $template as $temp_key => $temp_val ) {
				$temp_val = wp_parse_args( $temp_val, array(
					'path' => WFOCU_BRICKS_INTEGRATION_DIR . 'class-wfocu-template-bricks.php',
				) );

				WFOCU_Core()->template_loader->register_template( $temp_key, $temp_val );
			}

			$this->maybe_register_empty( WFOCU_BRICKS_INTEGRATION_DIR . 'class-wfocu-template-bricks.php' );
		}

		public function local_templates() {
			$template = array();

			return $template;
		}

		public function get_edit_link() {
			return add_query_arg( array(
				'post'   => '{{offer_id}}',
				'action' => 'elementor',
			), admin_url( 'post.php' ) );
		}

		public function get_preview_link() {
			return add_query_arg( array(
				'p' => '{{offer_id}}',
			), site_url() );
		}


		/**
		 * @param $template
		 * @param $offer
		 * @param $offer_settings
		 *
		 * @return bool|mixed|string|void
		 */
		public function update_template( $template, $offer, $offer_settings ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}
			$template_data     = null;
			$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );
			if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
				return $get_template_json['error'];
			}

			wp_update_post( array(
				'ID'           => $offer,
				'post_content' => '',
			) );

			if ( ! is_array( $get_template_json ) && is_string( $get_template_json ) ) {
				try {
					$template_data = json_decode( $get_template_json, true );
				} catch ( Exception $error ) {
					return $error->getMessage();
				}
			}

			if ( ! is_array( $template_data ) ) {
				return false;
			}

			if ( empty( $template_data ) ) {
				return false;
			}

			$elements = array();
			$area     = 'content';
			$meta_key = BRICKS_DB_PAGE_CONTENT;

			if ( ! empty( $template_data[ $area ] ) ) {
				$elements = $template_data[ $area ];
			}

			if ( isset( $template_data['pageSettings'] ) ) {
				update_post_meta( $offer, BRICKS_DB_PAGE_SETTINGS, $template_data['pageSettings'] );
			}
			// STEP: Save final template elements
			$elements = \Bricks\Helpers::sanitize_bricks_data( $elements );

			// Add backslashes to element settings (needed for '_content' HTML entities, and Custom CSS) @since 1.7.1
			foreach ( $elements as $index => $element ) {
				$element_settings = ! empty( $element['settings'] ) ? $element['settings'] : array();

				foreach ( $element_settings as $setting_key => $setting_value ) {
					if ( is_string( $setting_value ) ) {
						$elements[ $index ]['settings'][ $setting_key ] = addslashes( $setting_value );
					}
				}
			}

			// STEP: Generate element IDs (@since 1.9.8)
			$elements = \Bricks\Helpers::generate_new_element_ids( $elements );

			// Update content.
			update_post_meta( $offer, $meta_key, apply_filters( 'wfocu_import_bricks_content', $elements, $offer ) );
			if ( defined( 'BRICKS_VERSION' ) ) {
				update_post_meta( $offer, '_bricks_version', BRICKS_VERSION );
			}
			wp_update_post( array(
				'ID'           => $offer,
				'post_content' => '',
			) );

			return true;
		}


		public function handle_remote_import( $data ) {
			return is_string( $data ) ? $data : wp_json_encode( $data );
		}


		public function get_template_path() {
			return WFOCU_BRICKS_INTEGRATION_DIR . 'class-wfocu-template-bricks.php';
		}
	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Bricks(), 'bricks' );
}