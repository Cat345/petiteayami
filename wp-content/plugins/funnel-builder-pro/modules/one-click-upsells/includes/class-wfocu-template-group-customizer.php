<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Customizer' ) ) {
	class WFOCU_Template_Group_Customizer extends WFOCU_Template_Group {
		public $listing_index = 5;

		public function get_templates() {
			return apply_filters( 'wfocu_templates_group_customizer', [ 'sp-classic', 'sp-vsl' ] );
		}


		public function is_visible() {

		}

		public function get_nice_name() {
			return __( 'Customizer', 'woofunnels-upstroke-one-click-upsell' );
		}


		public function local_templates() {
			$template = array(
				'sp-classic' => array(
					'path'        => WFOCU_TEMPLATE_DIR . '/sp-classic/template.php',
					'name'        => __( 'Product Upsell', 'woofunnels-upstroke-one-click-upsell' ),
					'thumbnail'   => 'https://woofunnels.s3.amazonaws.com/templates/upsell/product-upsell-style-1.jpg',
					'preview_url' => 'https://templates.buildwoofunnels.com/template-preview/?bwf_id=13876&type=upsell',
					"prevslug"    => "product-upsell-customizer"
				),
				'sp-vsl'     => array(
					'path'        => WFOCU_TEMPLATE_DIR . '/sp-vsl/template.php',
					'name'        => __( 'VSL Upsell', 'woofunnels-upstroke-one-click-upsell' ),
					'thumbnail'   => 'https://woofunnels.s3.amazonaws.com/templates/upsell/vsl-upsell-elementor.jpg',
					'preview_url' => 'https://templates.buildwoofunnels.com/template-preview/?bwf_id=13877&type=upsell',
					"prevslug"    => "vsl-upsell-customizer"
				),
			);

			return $template;
		}

		public function get_edit_link() {
			return admin_url( 'customize.php' ) . '?wfocu_customize=loaded&offer_id={{offer_id}}&funnel_id={{funnel_id}}&url={{step_url}}&return={{return}}';
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p'               => '{{offer_id}}',
				'wfocu_customize' => 'loaded',
				'offer_id'        => '{{offer_id}}',
				'funnel_id'       => '{{funnel_id}}',
			], site_url() );
		}

		public function set_up_template() {

			$get_customizer_instance = WFOCU_Core()->customizer;
			$get_customizer_instance->load_template( WFOCU_Core()->template_loader->offer_data );
			WFOCU_Core()->template_loader->current_template = $get_customizer_instance->get_template_instance();
			if ( false === is_null( WFOCU_Core()->template_loader->current_template ) ) {
				$get_customizer_instance->get_template_instance()->set_offer_data( WFOCU_Core()->template_loader->offer_data );

				WFOCU_Core()->template_loader->current_template->set_data( WFOCU_Core()->template_loader->product_data );
			}
		}

		/**
		 * This method is responsible for serving template path from where the content should be controlled
		 * since its a customizer template group each template has different path.
		 * So we need to check in local templates and registered templates ( for multiple product cases) to server respective template.php
		 *
		 * @param string $template
		 * @param array $offer_data
		 *
		 * @return false|mixed
		 */
		public function get_template_path( $template = '', $offer_data = array() ) {
			if ( empty( $template ) ) {
				$template = WFOCU_Core()->template_loader->get_default_template( $offer_data );

			}
			$templates = $this->local_templates();

			if ( array_key_exists( $template, $templates ) ) {
				return $templates[ $template ]['path'];
			}
			$get_registred_templates = WFOCU_Core()->template_loader->get_templates();
			if ( array_key_exists( $template, $get_registred_templates ) ) {
				return $get_registred_templates[ $template ]['path'];
			}

		}

		public function maybe_get_template( $template = '' ) { //phpcs:ignore
			/**
			 * Loads a assigned template
			 */
			do_action( 'wfocu_front_template_after_validation_success' );
			WFOCU_Core()->template_loader->current_template->set_data( WFOCU_Core()->template_loader->product_data );

			WFOCU_Core()->template_loader->current_template->get_view();
			die();
		}

		public function handle_remote_import( $data ) {
			return $data;
		}

		public function update_template( $template, $offer, $offer_settings ) { //phpcs:ignore

			if ( $this->if_current_template_is_empty( $template ) ) {
				return;
			}
			$get_template_json = WFOCU_Core()->template_retriever->get_single_template_json( $template, $this->get_slug() );

			if ( is_array( $get_template_json ) && isset( $get_template_json['error'] ) ) {
				return $get_template_json['error'];
			}

			return true;
		}
	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Customizer, 'customizer' );
}