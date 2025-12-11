<?php
if ( ! class_exists( 'WFOCU_Oxy_Variation_Selector' ) ) {
	class WFOCU_Oxy_Variation_Selector extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_variation_selector';
		protected $id = 'wfocu_variation_selector';

		public function __construct() {
			$this->name = __( "WF Variation Selector" );
			parent::__construct();
		}

		public function setup_data() {
			$offer_id        = WFOCU_Core()->template_loader->get_offer_id();
			$variables       = $products = array();
			$product_options = array( '0' => '--No Product--' );


			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();

				if ( in_array( $product->type, array( 'variable', 'variable-subscription' ), true ) ) {
					array_push( $variables, $key );
				}
			}
			$condition = [
				'selected_product' => $variables,
			];
			$tab_id    = $this->add_tab( __( 'Product', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
			$this->add_switcher( $tab_id, 'attr_value_block', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on', $condition );


			/* Margin bottom setting */
			$property_css = 'margin-bottom';
			$this->slider_measure_box( $tab_id, $this->slug . '_space_btw_attributes', '.wfocu-product-attr-wrapper .variations tr', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), "2", [], $property_css );


			$this->style_field();
			//}
		}

		public function style_field() {

			$attribute_label_id = $this->add_tab( __( 'Attribute Label', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_width( $attribute_label_id, $this->slug . '_label_min_width', '.wfocu-product-attr-wrapper .variations label', __( 'Width', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_heading( $attribute_label_id, __( 'Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $attribute_label_id, '_attribute_text_color', '.wfocu-product-attr-wrapper .variations .label label', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349' );

			$this->add_background_color( $attribute_label_id, $this->slug . '_attribute_bg_color', '.variations label' );

			$this->add_heading( $attribute_label_id, __( 'Label Typography' ) );
			$default = [
				'font_size' => '16',
			];
			$this->add_text_alignments( $attribute_label_id, $this->slug . '_text_typography', '.wfocu_variation_selector_form .variations label' );
			$this->custom_typography( $attribute_label_id, $this->slug . '_text_typography', '.wfocu_variation_selector_form .variations label', '', $default );


			$attribute_value_id = $this->add_tab( __( 'Attribute Dropdown', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_width( $attribute_value_id, $this->slug . '_attr_value_width', '.wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value', __( 'Width', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->add_heading( $attribute_value_id, __( 'DropDown Typography' ) );
			$this->custom_typography( $attribute_value_id, $this->slug . '_attr_value_typography', '.wfocu-product-attr-wrapper  .wfocu_variation_selector_form .variations select', '', $default );


			$this->add_heading( $attribute_value_id, __( 'Color' ) );
			$this->add_color( $attribute_value_id, $this->slug . '_attr_value_text_color', '.wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value select', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349' );
			$this->add_background_color( $attribute_value_id, $this->slug . '_attr_value_bg_color', '.wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value select', '#ffffff' );


			$this->add_heading( $attribute_value_id, __( 'Spacing' ) );
			$this->add_margin( $attribute_value_id, $this->slug . '_attr_value_margin', '.wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value select' );
			$this->add_padding( $attribute_value_id, $this->slug . '_attr_value_padding', '.wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value select' );


			$this->add_heading( $attribute_value_id, __( 'Border' ) );
			$this->add_border( $attribute_value_id, $this->slug . '_attr_value_border', '.variations .value select' );

		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			if ( false === $product ) {
				return '';
			}

			$is_variable = false;

			if ( ! empty( $product_key ) ) {

				if ( $product->is_type( 'variable' ) ) {
					$is_variable = true;
				}
			}

			if ( false === $is_variable ) {
				return '';
			}

			echo "<style>";
			echo ".wfocu_variation_selector_wrap { width:100%;}";


			echo ".wfocu_variation_selector_wrap .variations tr{ display: block;}";

			$selector = isset( $settings['selector'] ) ? '#' . $settings['selector'] : '';
			echo $selector . " .wfocu_variation_selector_wrap .variations td{ display:block;}";
			if ( isset( $settings['attr_value_block'] ) && 'on' === $settings['attr_value_block'] ) {
				echo $selector . " .wfocu_variation_selector_wrap .variations td{ display: inline-block;}";
			}
			if ( isset( $settings['attr_value_block'] ) && 'off' === $settings['attr_value_block'] ) {
				echo $selector . " .wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .label{ width:250px;}";
			}
			echo "</style>";
			if ( ! empty( $product_key ) ) {
				if ( true === $is_variable ) {
					echo do_shortcode( '[wfocu_variation_selector_form key="' . $product_key . '"]' );
				}
			}
		}

		public function defaultCSS() {

			$defaultCSS = "
		     .wfocu-product-attr-wrapper table.variations tr td:last-child {
                padding-bottom: 20px;
            }
            .wfocu-product-attr-wrapper table.variations tr > td.label {
                text-align: left;
            }
            .wfocu-product-attr-wrapper table.variations tr td {
                padding-top: 0;
            }
            .wfocu-product-attr-wrapper table.variations tr:last-child td:last-child {
                padding-bottom: 0;
            }
            .wfocu-product-attr-wrapper {
                display: inline-block;
            }
            .wfocu-product-attr-wrapper select {
                padding: 8px;
                width: 100%;
                height: auto;
                font-size: 14px;
                line-height: 1.5;
            }
            table.variations td label {
                font-weight: normal;
            }
            table.variations {
                width: 100%;
            }
            .wfocu-product-attr-wrapper table.variations tr > td.label {
                text-align: left;
            }
            .variations select {
                width: 100%;
                display: block;
                padding: 10px;
                color: #333;
                background-color: #ffffff;
                font-size: 16px;
                line-height: 1.5;
                border: 1px solid #dddddd;
            }
            .wfocu-product-attr-wrapper {
                width: 100%;
            }
            .wfocu-product-attr-wrapper .variations .value select {
                color: #333;
                background-color: #ffffff;
                border: 1px solid #dddddd;
            }
            .wfocu-product-attr-wrapper .variations td label,
            .wfocu-product-attr-wrapper .variations td select option {
                font-weight: normal
            }
            .wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations select,
            .wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations label {
                font-size: 16px;
                line-height: 1.5
            }
            .wfocu-product-attr-wrapper .variations label {
                color: #414349;
            }
            .wfocu-product-attr-wrapper .variations {
                width: 100%;
            }
            .wfocu-product-attr-wrapper .variations .label label {
                display: block;
            }
            .oxy-wfocu-variation-selector{
                width: 100%;
            }
            .wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .label{
                width: 100px;
            }
            .wfocu-product-attr-wrapper .wfocu_variation_selector_form .variations .value{
                width: 250px;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Variation_Selector;
}