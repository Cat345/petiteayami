<?php
if ( ! class_exists( 'WFOCU_Accept_Link' ) ) {
	class WFOCU_Accept_Link extends WFOCU_Divi_HTML_BLOCK {
		public $slug = 'wfocu_accept_link';

		public function __construct() {
			parent::__construct();
		}

		public function setup_data() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}
			$tab_id = $this->add_tab( __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ), 5 );
			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
			do_action( 'wfocu_add_divi_controls', $this, $offer_id, $products );
			$this->add_text( $tab_id, 'text', __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ), __( 'Accept this offer', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->style_field();

		}

		private function style_field() {
			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$default = '|700|||||||';
			$this->add_typography( $tab_id, 'typography', ' %%order_class%% .wfocu-wfocu-accept' );
			$this->add_text_alignments( $tab_id, 'align', ' %%order_class%%', '', 'center' );

			$color_id         = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$controls_tabs_id = $this->add_controls_tabs( $color_id, "" );
			$fields_keys      = [];
			$fields_keys[]    = $this->add_color( $color_id, 'wfocu_accept_text_color', '%%order_class%% .wfocu-wfocu-accept', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#777777' );
			$fields_keys[]    = $this->add_background_color( $color_id, 'wfocu_accept_link_bg_color', '%%order_class%% .wfocu-wfocu-accept', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_controls_tab( $controls_tabs_id, "Normal", $fields_keys );

			$colors_field   = [];
			$colors_field[] = $this->add_color( $color_id, 'hover_color', '%%order_class%%:hover .wfocu-wfocu-accept', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_background_color( $color_id, 'wfocu_reject_background_hover_color', '%%order_class%% .wfocu-wfocu-accept:hover', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_controls_tab( $controls_tabs_id, "Hover", $colors_field );


			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$default   = [
				'border_type' => 'none',
			];
			$this->add_border( $border_id, 'border', '%%order_class%% .wfocu-wfocu-accept', [], $default );
			$this->add_border_color( $border_id, 'wfocu_reject_hover_border_color', '%%order_class%% .wfocu-wfocu-accept:hover', '#89e047', __( 'Border Hover Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_box_shadow( $border_id, 'box_shadow', '%%order_class%% .wfocu-wfocu-accept', [ 'enable' => 'off', 'vertical' => 0, 'color' => '#00B211' ] );
			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, 'text_margin', '%%order_class%% .wfocu-wfocu-accept' );
			$this->add_padding( $spacing_id, 'text_padding', '%%order_class%% .wfocu-wfocu-accept' );


		}

		public function html( $attrs, $content = null, $render_slug = '' ) {

			$sel_product = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			ob_start();
			?>
            <div class="wfocu-button-wrapper">
                <a class="wfocu-wfocu-accept wfocu_upsell wfocu_paypal_in_context_btn" href="javascript:void(0);" data-key="<?php echo $product_key ?>"><?php echo $this->props['text'] ?></a>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	return new WFOCU_Accept_Link;
}