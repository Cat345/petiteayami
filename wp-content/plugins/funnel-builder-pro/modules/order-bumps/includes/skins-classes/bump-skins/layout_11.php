<?php
if ( ! class_exists( 'WFOB_Layout_11' ) ) {

	class WFOB_Layout_11 extends WFOB_Bump {
		protected static $slug = 'layout_11';

		public function __construct( $wfob_id ) {
			parent::__construct( $wfob_id );

			add_filter( 'wfob_bump_inline_css', [ $this, 'override_dynamic_css' ], 10, 2 );
		}

		/**
		 * Get Default Setting of bump
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Get preview image url
		 * @return string
		 */
		public static function get_preview_image_url() {
			return WFOB_PLUGIN_URL . '/assets/img/skin-11.jpg';
		}

		protected function get_product_content_schema( $product, $product_key ) {


			$schema = [];

			$description_richeditor = __( 'Use merge tag {{quantity_incrementer}} to show the quantity changer.', 'woofunnels-order-bump' );

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_title",
				"label"     => __( "Title", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_title',
				"hint"      => __( "Use merge tag {{product_name}} to show product name dynamically.", "woofunnels-order-bump" )
			];

			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_description",
				"label"     => __( "Description", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_skin_description',
				'default'   => 'Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry.1',
				"hint"      => $description_richeditor
			];

			$schema[] = [
				"type"         => "checkbox",
				"key"          => "product_" . $product_key . "_exclusive_content_enable",
				"label"        => __( "Add Exclusive Offer Text", 'woofunnels-order-bump' ),
				'contentClass' => 'wfob_active_exclusive',
				"selectors"    => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
			];

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_exclusive_content",
				"label"     => '',
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_exclusive_content span',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_exclusive_content_enable",
					'value' => true
				],
			];


			$schema[] = [
				"type"         => "checkbox",
				"key"          => "product_" . $product_key . "_social_proof_enable",
				"label"        => __( "Enable Social Proof Tool Tip", 'woofunnels-order-bump' ),
				'contentClass' => 'wfob_active_social_proof',
				"selectors"    => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
			];

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_social_proof_heading",
				"label"     => '',
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob-social-proof-tooltip .wfob-social-proof-tooltip-header',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_social_proof_enable",
					'value' => true
				],

			];

			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_social_proof_content",
				"label"     => '',
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob-social-proof-tooltip .wfob-social-proof-tooltip-content',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_social_proof_enable",
					'value' => true
				],
			];


			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_add_btn_text",
				"label"     => __( "Add Button", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_btn_add span',
				'default'   => __( 'ADD', 'woofunnels-order-bump' ),
				"hint"      => "",
				"class"     => "bwf-field-one-half",
			];
			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_added_btn_text",
				"label"     => __( "Added Button", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_btn_add.wfob_btn_remove .wfob_btn_text_added',
				'default'   => __( 'ADDED', 'woofunnels-order-bump' ),
				"hint"      => "",
				"class"     => "bwf-field-one-half",
			];


			// Field add added remove button text

			return $schema;

		}

		public function get_admin_schema() {
			return parent::get_admin_schema();
		}

		public static function get_default_models() {
			return array(
				'heading_background'       => '#E1E5FB',
				'heading_hover_background' => '',
				'heading_color'            => '#353030',
				'heading_hover_color'      => '',
				'heading_font_size'        => '14',
				'heading_box_padding'      => '12 16 12 16',

				'heading_box_border_style'  => 'none',
				'heading_box_border_color'  => '',
				'heading_box_border_width'  => '0 0 0 0',
				'heading_box_border_radius' => '8',

				'header_enable_pointing_arrow' => 'true',
				'point_animation'              => '1',
				'point_animation_color'        => '#D80027',

				'error_color' => '#e15334',

				'enable_featured_image_border' => 'true',
				'featured_image_border_style'  => 'solid',
				'featured_image_border_color'  => '#ECECEC',
				'featured_image_border_width'  => '1 1 1 1',
				'featured_image_border_radius' => '8',

				'content_font_size'                  => '14',
				'content_color'                      => '#353030',
				'content_variation_link_color'       => '#e15334',
				'content_variation_link_hover_color' => '',
				'content_box_padding'                => '0',


				'enable_price'         => "true",
				'price_font_size'      => '12',
				'price_color'          => '#e15334',
				'price_sale_font_size' => '14',
				'price_sale_color'     => '#353030',


				'add_button_font_size'         => '15',
				'add_button_enable_box_border' => 'true',
				'add_button_border_style'      => 'solid',
				'add_button_border_color'      => '#09B29C',
				'add_button_border_width'      => '0 0 0 0',
				'add_button_padding'           => '8 16 8 16',
				'add_button_border_radius'     => '8',
				'add_button_width'             => '180',

				'add_button_color'          => '#ffffff',
				'add_button_hover_color'    => '',
				'add_button_bg_color'       => '#09B29C',
				'add_button_hover_bg_color' => '',

				'added_button_color'    => '#ffffff',
				'added_button_bg_color' => '#353030',


				'box_background'       => '#F4F6FA',
				'box_background_hover' => '',
				'box_padding'          => '16',
				'enable_box_border'    => 'true',
				'border_style'         => 'solid',
				'border_color'         => '#4B61D1',
				'border_width'         => '1',
				'box_border_radius'    => '8',


				'exclusive_content_bg_color'  => '#4B61D1',
				'exclusive_content_font_size' => '12',
				'exclusive_content_color'     => '#ffffff',
				'exclusive_content_enable'    => 'true',
				'exclusive_content'           => __( 'Special Offer', 'woofunnels-order-bump' ),
				'exclusive_content_position'  => 'wfob_exclusive_outside_top_left',


				'social_proof_enable'  => 'true',
				'social_proof_heading' => __( '30% of Our Customers Choose this Upgrade', 'woofunnels-order-bump' ),
				'social_proof_content' => __( 'This is by far the most popular option with over 30% of customers choosing this value offer. {{product_regular_price}} worth but today only {{price}} ({{saving_value}} / {{saving_percentage}} savings).', 'woofunnels-order-bump' ),
				'social_proof_tooltip_bg_color'          => '#ffffff',
				'social_proof_tooltip_font_size'         => '12',
				'social_proof_tooltip_color'             => '#353030',
				'social_proof_tooltip_heading_bg_color'  => '#09b29c',
				'social_proof_tooltip_heading_font_size' => '14',
				'social_proof_tooltip_heading_color'     => '#ffffff',

				'bump_max_width' => '',

				'layout'      => 'layout_11',
				'layout_name' => __( 'Skin 11', 'woofunnels-order-bump' ),
				'class_name'  => 'WFOB_Layout_11',

				'product_title'         => __( "<span style='color:#E15334'>Yes!</span> Add ", 'woofunnels-order-bump' ) . '{{product_name}}' . __( ' to my order', 'woofunnels-order-bump' ),
				'product_preview_title' => __( "<span style='color:#E15334'>Yes!</span>" . ' Add Complete Skin Care Pack to my order', 'woofunnels-order-bump' ),

				'product_featured_image'     => "true",
				'product_description'        => __( "Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry.", 'woofunnels-order-bump' ),
				'product_add_button_text'    => __( 'Yes! Add to My Order', 'woofunnels-order-bump' ),
				'product_added_button_text'  => __( 'Added to Order', 'woofunnels-order-bump' ),
				'product_remove_button_text' => __( 'REMOVE', 'woofunnels-order-bump' ),
				'product_add_btn_text'       => __( 'Yes! Add to My Order', 'woofunnels-order-bump' ),
				'add_btn_text'               => __( 'Yes! Add to My Order', 'woofunnels-order-bump' ),
				'product_added_btn_text'     => __( 'Added To Order', 'woofunnels-order-bump' ),
				'product_remove_btn_text'    => __( 'REMOVE', 'woofunnels-order-bump' ),
				'product_read_more'          => __( ' more...', 'woofunnels-order-bump' ),

				'product_image_url'            => WFOB_PLUGIN_URL . '/admin/assets/img/preview_bump_product_icon.jpg',
				'product_image_position_class' => 'wfob_img_position_left',
				'product_image_position_width' => '96',
				'product_image_position'       => 'center',
				'price_numeric'                => '35.67',
				'product_price'                => wc_format_sale_price( 39.97, 35.67 ),
				'product_price_numeric'        => '35.67',

			);
		}


		public function get_bump_product_other_fields( $bump_id, $product_key, $design_data = [], $key = '', $old_key = '' ) {
			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}
			$default_data = WFOB_Common::get_default_model_data( $bump_id );


			$default_data = $default_data[ $design_data['layout'] ];


			$default_value = isset( $default_data["product_{$key}"] ) ? $default_data["product_{$key}"] : '';

			$text = isset( $design_data["product_{$product_key}_{$old_key}"] ) ? $design_data["product_{$product_key}_{$old_key}"] : $default_value;

			return $text;

		}

		public function override_dynamic_css( $dynamic_style, $object ) {

			$bump_id = $object->get_id();

			if ( isset( $dynamic_style[ $bump_id ]['desktop'] ) ) {


			}


			return $dynamic_style;

		}

		public function print_bump_price( $final_data = [], $product_key = '' ) {

			if ( isset( $final_data[ $product_key ]['printed_price'] ) ) {
				$printed_price = $final_data[ $product_key ]['printed_price'];
			}

			include WFOB_SKIN_DIR . "/template-parts/wfob-price.php";


		}
	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_11' );
}