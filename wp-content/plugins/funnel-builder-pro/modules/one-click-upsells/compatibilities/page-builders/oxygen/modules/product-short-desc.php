<?php
if ( ! class_exists( 'WFOCU_Oxy_Product_Short_Desc' ) ) {
	class WFOCU_Oxy_Product_Short_Desc extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_product_short_description';
		protected $id = 'wfocu_product_short_description';

		public function __construct() {
			$this->name = __( "WF Product Short Description" );
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {

			$this->text_settings();
			$this->color_settings();
			$this->typography_settings();
			$this->spacing_setting();
			$this->border_setting();
		}

		private function text_settings() {
			$tab_id = $this->add_tab( __( 'Product', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );
		}

		private function color_settings() {
			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_text_color', '.wfocu_short_description p', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349' );
			$this->add_background_color( $tab_id, $this->slug . '_background_color', '.wfocu_short_description', 'transparent', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}

		private function typography_settings() {

			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Description Typography' ) );

			$default = [
				'font_size' => '16',
			];

			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu_short_description' );
			$this->custom_typography( $tab_id, $this->slug . '_typography', '.wfocu_short_description p', '', $default );
		}

		private function spacing_setting() {
			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, $this->slug . '_text_margin', '.wfocu_short_description' );
			$this->add_padding( $tab_id, $this->slug . '_text_padding', '.wfocu_short_description' );
		}

		private function border_setting() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu_short_description' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu_short_description' );
		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );

			if ( false === $product ) {
				return;
			}
			$post_object = get_post( $product->get_id() );
			$description = $post_object->post_excerpt;
			if ( 'product_variation' === $post_object->post_type ) {
				$product = wc_get_product( $product->get_id() );
				if ( $product instanceof WC_Product ) {
					$description = $product->get_description();
				}
			}

			$short_description = apply_filters( 'woocommerce_short_description', $description );
			if ( empty( $short_description ) ) {
				return;
			}
			?>
            <div class="wfocu-widget-containe wfocu_short_description">
				<?php echo wp_kses_post( $short_description ); ?>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
			 .wfocu_short_description {
                color: #414349;
                background-color: transparent
            }

            .wfocu_short_description p {
                color: #414349;
                font-size: 16px;
                line-height: 1.5;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Product_Short_Desc();
}