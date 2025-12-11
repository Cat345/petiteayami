<?php
if ( ! class_exists( 'WFOCU_Oxy_Quantity_Selector' ) ) {
	class WFOCU_Oxy_Quantity_Selector extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_qty_selector';
		protected $id = 'wfocu_qty_selector';

		public function __construct() {
			$this->name = __( "WF Quantity Selector" );
			parent::__construct();
		}

		public function setup_data() {

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

			$tab_id = $this->add_tab( __( 'Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ) );
			if ( false === $qty_selector_enabled ) {
				$this->add_sub_heading( $tab_id, __( 'Quantity selector is not available for this offer. Kindly allow customer to chose the quantity while purchasing this upsell product(s) from "Offers" tab.', 'woofunnels-upstroke-one-click-upsell' ) );

				return;
			}
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );

			$this->style_field();
			$this->spacing_setting();
			$this->border_setting();

		}


		public function style_field() {

			$tab_id = $this->add_tab( __( 'Label', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_width( $tab_id, $this->slug . '_qty_label_width', '.wfocu-prod-qty-wrapper label', '' );

			$this->add_heading( $tab_id, __( 'Text' ) );

			$this->add_text( $tab_id, 'text', __( 'Text', 'woofunnels-upstroke-one-click-upsell' ), __( 'Quantity', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_switcher( $tab_id, 'slider_enabled', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on' );


			$this->add_heading( $tab_id, __( 'Color' ) );
			$this->add_color( $tab_id, $this->slug . '_label_text_color', '.wfocu-prod-qty-wrapper label', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#333' );
			$this->add_background_color( $tab_id, $this->slug . '_label_bg_color', '.wfocu-prod-qty-wrapper label', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_heading( $tab_id, __( 'Typography' ) );

			$default = [
				'font_size' => '16',
			];
			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu-prod-qty-wrapper label' );
			$this->custom_typography( $tab_id, $this->slug . '_label_typography', '.wfocu-prod-qty-wrapper label', '', $default );

			$this->add_heading( $tab_id, __( 'Spacing' ) );
			$this->add_margin( $tab_id, $this->slug . '_qty_block_margin', '.wfocu-prod-qty-wrapper label' );
			$this->add_padding( $tab_id, $this->slug . '_qty_block_padding', '.wfocu-prod-qty-wrapper label' );

			$qty_tab_id = $this->add_tab( __( 'Dropdown', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_width( $qty_tab_id, $this->slug . '_qty_dropdown_width', ' .wfocu-prod-qty-wrapper .wfocu-select-wrapper', '' );

			$this->add_heading( $qty_tab_id, __( 'Color' ) );
			$this->add_color( $qty_tab_id, $this->slug . '_qty_dropdown_text_color', '.wfocu-prod-qty-wrapper .wfocu-select-qty-input', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#333' );
			$this->add_background_color( $qty_tab_id, $this->slug . '_qty_dropdown_bg_color', '.wfocu-prod-qty-wrapper .wfocu-select-qty-input', '#ffffff' );

			$this->add_heading( $qty_tab_id, __( 'Spacing' ) );
			$this->add_margin( $qty_tab_id, $this->slug . '_qty_dropdown_margin', '.wfocu-select-qty-input' );
			$this->add_padding( $qty_tab_id, $this->slug . '_qty_dropdown_padding', '.wfocu-prod-qty-wrapper .wfocu-select-qty-input' );

			$this->add_heading( $qty_tab_id, __( 'Typography' ) );
			$this->custom_typography( $qty_tab_id, $this->slug . '_qty_select', '.wfocu-prod-qty-wrapper .wfocu-select-qty-input', '', $default );

			$this->add_heading( $qty_tab_id, __( 'Border' ) );
			$this->add_border( $qty_tab_id, $this->slug . '_qty_dropdown_border', '.wfocu-prod-qty-wrapper .wfocu-select-qty-input' );


		}

		private function spacing_setting() {
			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, $this->slug . '_text_margin', '.wfocu-prod-qty-wrapper' );
			$this->add_padding( $tab_id, $this->slug . '_text_padding', '.wfocu-prod-qty-wrapper' );
		}

		private function border_setting() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu-prod-qty-wrapper' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu-prod-qty-wrapper' );
		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			if ( false === $product ) {
				return '';
			}

			$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;
			$qty_text             = isset( $settings['text'] ) ? $settings['text'] : '';
			$slider_enabled       = isset( $settings['slider_enabled'] ) ? $settings['slider_enabled'] : '';
			if ( false === $qty_selector_enabled ) {
				return '';
			}

			$class_name = "wfocu_proqty_inline";
			$selector   = isset( $settings['selector'] ) ? '#' . $settings['selector'] : '';
			if ( 'on' === $slider_enabled ) {
				$class_name = "wfocu_proqty_block";
				echo "<style> " . $selector . " .wfocu-prod-qty-wrapper, " . $selector . " .wfocu-prod-qty-wrapper{display:flex !important;}</style>";

			}

			if ( ! empty( $product_key ) ) {
				echo "<div class='wfocu_qty_selector_wrapper " . esc_attr( $class_name ) . "'>";
				echo do_shortcode( '[wfocu_qty_selector key="' . $product_key . '" label="' . $qty_text . '"]' );
				echo "</div>";

			}
		}

		public function defaultCSS() {

			$defaultCSS = "
		    .wfocu-prod-qty-wrapper {
                margin-bottom: 1.2em;
                display: inline-block;
                width: 100%;
                border:0px solid;
            }
            .wfocu-prod-qty-wrapper label {
                text-align: left;
                font-weight: 300;
                line-height: 1;
                padding-bottom: 8px;
                display: block;
                background: transparent;
                font-weight: normal;
            }
            .wfocu-prod-qty-wrapper .wfocu-select-qty-input {
                color: #333;
                font-size: 16px;
                line-height: 1.5;
                background-color: #ffffff;
                text-align: left;
                display: inline-block;
                height: auto;
                width: 100%;
                padding: 10px;
                border: 1px solid #dddddd;
            }
            .wfocu-prod-qty-wrapper .wfocu-select-qty-input option {
                font-weight: 300;
                color: #333;
                box-shadow: none;
                -webkit-box-shadow: none;
                -moz-box-shadow: none;

            }
            .wfocu-prod-qty-wrapper label {
                font-size: 16px;
                line-height: 1.5;
                color: #333;
                display: block;
                background: transparent;
                font-weight: normal;
                width:105px;
            }
            .oxy-wfocu-qty-selector{
                width:100%;    
            }
            .wfocu-prod-qty-wrapper .wfocu-select-wrapper{
                width: 250px;
                display:block;
            }
            .wfocu_qty_selector_wrapper{
                display:inline-block;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Quantity_Selector;
}