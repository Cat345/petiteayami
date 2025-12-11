<?php
if ( ! class_exists( 'WFOCU_Importer_Elementor' ) ) {
	/**
	 * Elementor template library local source.
	 *
	 * Elementor template library local source handler class is responsible for
	 * handling local Elementor templates saved by the user locally on his site.
	 *
	 * @since 1.0.0
	 */
	class WFOCU_Importer_Elementor extends Elementor\TemplateLibrary\Source_Local {

		public function __construct() {
			add_action( 'wfocu_template_removed', [ $this, 'delete_elementor_data' ] );
		}

		/**
		 *  Import single template
		 *
		 * @param int $post_id post ID.
		 */
		public function single_template_import( $post_id, $content = '', $offer_settings = array() ) {

			if ( empty( $content ) ) {
				return;
			}

			$content = json_decode( $content, true );

			if ( ! is_array( $content ) ) {
				//skip if not an array
			} else {
				//go ahead and import the content
				if ( isset( $content['content'] ) && ! empty( $content['content'] ) ) {
					$content = $content['content'];
				}

				if ( ! empty( $post_id ) ) {
					$products     = isset( $offer_settings->products ) ? $offer_settings->products : array();
					$product_keys = array_keys( (array) $products );

					$content = $this->wfocu_replace_position_to_selected_products( $product_keys, $content );
				}
				// Update content.
				do_action( 'wfocu_import_elementor_content_data', $content, $post_id, $offer_settings );
				$content = apply_filters( 'wfocu_import_elementor_content', $content, $post_id, $offer_settings );

				$content = wp_slash( wp_json_encode( $content ) );
				update_metadata( 'post', $post_id, '_elementor_data', $content );
				if ( defined( 'ELEMENTOR_VERSION' ) ) {
					update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
				}
			}
			$this->generate_kit();
		}

		/**
		 * @param $offer_id
		 * @param $el_data
		 *
		 * @return mixed
		 */
		public function wfocu_replace_position_to_selected_products( $product_keys, $el_data ) {
			$output = array();
			foreach ( $el_data as $el_key => $el_value ) {

				if ( is_array( $el_value ) ) {
					$output[ $el_key ] = $this->wfocu_replace_position_to_selected_products( $product_keys, $el_value );
				} else {
					if ( 'product_position' === $el_key && 1 < $el_value ) {
						if ( isset( $product_keys[ $el_value - 1 ] ) ) {
							$output['selected_product'] = $product_keys[ $el_value - 1 ];
						}
					} elseif ( 'product_position' === $el_key ) {
						unset( $el_data[ $el_key ] );
					} else {
						$output[ $el_key ] = $el_value;
					}
				}
			}

			return $output;
		}

		public function generate_kit() {
			if ( is_null( Elementor\Plugin::$instance ) || ! Elementor\Plugin::$instance->kits_manager instanceof Elementor\Core\Kits\Manager ) {
				return;
			}
			$kit = Elementor\Plugin::$instance->kits_manager->get_active_kit();
			if ( $kit->get_id() ) {
				return;
			}
			$created_default_kit = Elementor\Plugin::$instance->kits_manager->create_default();
			if ( ! $created_default_kit ) {
				return;
			}
			update_option( Elementor\Core\Kits\Manager::OPTION_ACTIVE, $created_default_kit );
		}

		public function delete_elementor_data( $post_id ) {

			wp_update_post( [ 'ID' => $post_id, 'post_content' => '' ] );
			delete_post_meta( $post_id, '_elementor_version' );
			delete_post_meta( $post_id, '_elementor_template_type' );
			delete_post_meta( $post_id, '_elementor_edit_mode' );
			delete_post_meta( $post_id, '_elementor_data' );
			delete_post_meta( $post_id, '_elementor_controls_usage' );
			delete_post_meta( $post_id, '_elementor_css' );
		}
	}
}