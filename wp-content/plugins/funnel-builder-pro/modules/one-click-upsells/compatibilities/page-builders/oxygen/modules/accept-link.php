<?php
if ( ! class_exists( 'WFOCU_Oxy_Accept_Link' ) ) {
	class WFOCU_Oxy_Accept_Link extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_accept_link';
		protected $id = 'wfocu_accept_link';

		public function __construct() {
			$this->name = __( "WF Accept Link" );
			parent::__construct();
		}

		public function setup_data() {

			$this->text_settings();
			$this->color_settings();
			$this->typography_settings();
			$this->spacing_setting();
			$this->border_setting();

		}

		public function text_settings() {


			$offer_id        = WFOCU_Core()->template_loader->get_offer_id();
			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			$tab_id = $this->add_tab( __( 'Text', 'woofunnels-upstroke-one-click-upsell' ) );
			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
			$this->add_text( $tab_id, 'text', __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ), __( 'Accept this offer', 'woofunnels-upstroke-one-click-upsell' ) );


		}

		public function color_settings() {
			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_sub_heading( $tab_id, __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_text_color', '.wfocu-button-wrapper .wfocu-wfocu-accept', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#615f5f' );
			$this->add_background_color( $tab_id, $this->slug . '_background_color', '.wfocu-button-wrapper .wfocu-wfocu-accept', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_sub_heading( $tab_id, __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_hover_color', '.wfocu-button-wrapper .wfocu-wfocu-accept:hover', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#615f5f' );
			$this->add_background_color( $tab_id, $this->slug . '_bg_hover_color', '.wfocu-button-wrapper .wfocu-wfocu-accept:hover', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}

		public function typography_settings() {

			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Title Typography' ) );
			$default = [
				'font_size' => '16',
			];

			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu-button-wrapper .wfocu-wfocu-accept', '', 'center' );
			$this->custom_typography( $tab_id, $this->slug . '_typography', '.wfocu-button-wrapper .wfocu-wfocu-accept', '', $default );

		}

		private function spacing_setting() {
			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, $this->slug . '_text_margin', '.wfocu-button-wrapper .wfocu-wfocu-accept' );
			$this->add_padding( $tab_id, $this->slug . '_text_padding', '.wfocu-button-wrapper .wfocu-wfocu-accept' );


		}

		public function border_setting() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu-button-wrapper .wfocu-wfocu-accept' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu-button-wrapper .wfocu-wfocu-accept' );

			$this->add_heading( $tab_id, __( 'Border Hover Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border_color( $tab_id, $this->slug . '_hover_border_color', '.wfocu-button-wrapper .wfocu-wfocu-accept:hover', '#89e047', __( 'Border Color', 'woofunnels-upstroke-one-click-upsell' ) );

		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			$text = isset( $settings['text'] ) ? $settings['text'] : '';
			?>
            <div class="wfocu-button-wrapper">
                <a class="wfocu-wfocu-accept wfocu_upsell" href="javascript:void(0);" data-key="<?php echo esc_attr( $product_key ) ?>"><?php echo wp_kses_post( $text ) ?></a>
            </div>
			<?php
		}


		public function defaultCSS() {

			$defaultCSS = "
			  .wfocu-button-wrapper .wfocu-wfocu-accept {
                display: block;
                border-style: none;
                border-radius: 0px;
                box-shadow: none;
                font-weight: normal;
                font-size: 16px;
                line-height: 1.5;
                color: #777777;
            }
            .oxy-wfocu-accept-link, .oxy-wfocu-accept-link .wfocu-button-wrapper{
            	width:100%;
            }
            .wfocu-button-wrapper .wfocu-wfocu-accept:hover {
                border-color: #89e047;
            }
            
		";

			return $defaultCSS;


		}


	}

	return new WFOCU_Oxy_Accept_Link;
}