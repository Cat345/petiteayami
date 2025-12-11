<?php
if ( ! class_exists( 'WFOB_Layout_3' ) ) {
	class WFOB_Layout_3 extends WFOB_Bump {
		protected static $slug = 'layout_3';

		public function __construct( $wfob_id ) {
			parent::__construct( $wfob_id );
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
			return WFOB_PLUGIN_URL . '/assets/img/skin-3.jpg';
		}

		protected function get_product_content_schema( $product, $product_key ) {

			$schema   = [];
			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_title",
				"label"     => __( "Title", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_title',
				"hint"      => __( "Use merge tag {{product_name}} to show product name dynamically.", "woofunnels-order-bump" )
			];

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_sub_title",
				"label"     => __( "Sub Title", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_l3_c_sub_head',
			];


			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_small_description",
				"label"     => __( "Short Description", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_l3_c_sub_desc',
				'default'   => '<p><strong style="color: #008000;">Exclusive Offer:</strong> Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry. {{more}}</p>',
				"hint"      => __( "Use merge tag {{more}} to make Description collapsible.", "woofunnels-order-bump" )
			];

			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_description",
				"label"     => __( "Description", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_skin_description',
				'default'   => 'Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry.',
				"hint"      => __( 'Use merge tag {{quantity_incrementer}} to show the quantity changer.', 'woofunnels-order-bump' )
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
				"class"     => "bwf-field-one-third",
			];
			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_added_btn_text",
				"label"     => __( "Added Button", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_btn_add.wfob_btn_remove .wfob_btn_text_added',
				'default'   => __( 'ADDED', 'woofunnels-order-bump' ),
				"hint"      => "",
				"class"     => "bwf-field-one-third",
			];
			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_remove_btn_text",
				"label"     => __( "Remove Button", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_btn_add.wfob_btn_remove .wfob_btn_text_remove',
				'default'   => __( 'REMOVE', 'woofunnels-order-bump' ),
				"hint"      => "",
				"class"     => "bwf-field-one-third",
			];
			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_read_more",
				"label"     => __( "Read More Text", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_read_more_link',
				'default'   => 'more...',
				"hint"      => "",
				"class"     => "bwf-field-one-third",
			];

			// Field add added remove button text

			return $schema;

		}

		public function get_admin_schema() {
			return parent::get_admin_schema();
		}

		public static function get_default_models() {
			return array(
				'heading_color'       => '#09B29C',
				'heading_hover_color' => '',
				'heading_font_size'   => '14',
				'heading_box_padding' => '0',

				'sub_heading_font_size'   => '14',
				'sub_heading_color'       => '#353030',
				'sub_heading_hover_color' => '',

				'sub_content_font_size' => '13',
				'sub_content_color'     => '#353030',

				'add_button_font_size'         => '13',
				'add_button_enable_box_border' => 'true',
				'add_button_border_style'      => 'solid',
				'add_button_border_color'      => '#353030',
				'add_button_border_width'      => '0',
				'add_button_padding'           => '5 12 5 12',
				'add_button_border_radius'     => '0',
				'add_button_width'             => '78',
				'add_button_color'             => '#ffffff',
				'add_button_hover_color'       => '',
				'add_button_bg_color'          => '#353030',
				'add_button_hover_bg_color'    => '',

				'added_button_color'    => '#ffffff',
				'added_button_bg_color' => '#9A9797',

				'remove_button_color'    => '#ffffff',
				'remove_button_bg_color' => '#E20707',

				'enable_price'         => false,
				'price_font_size'      => '12',
				'price_color'          => '#353030',
				'price_sale_font_size' => '',
				'price_sale_color'     => '',

				'content_box_padding'                => '0',
				'content_font_size'                  => '13',
				'content_color'                      => '#353030',
				'content_variation_link_color'       => '#e15334',
				'content_variation_link_hover_color' => '',

				'enable_featured_image_border' => 'true',
				'featured_image_border_width'  => '1',
				'featured_image_border_style'  => 'solid',
				'featured_image_border_color'  => '#f2f2f2',
				'featured_image_border_radius' => '0',

				'box_padding'          => 16,
				'bump_max_width'       => '',
				'box_background'       => '#ffffff',
				'box_background_hover' => '',
				'enable_box_border'    => 'true',
				'border_width'         => '1',
				'border_style'         => 'solid',
				'border_color'         => '#DEDFEA',
				'box_border_radius'    => '0',

				'exclusive_content_font_size' => '12',
				'exclusive_content_color'     => '#09B29C',
				'exclusive_content_enable'    => 'false',
				'exclusive_content'           => __( 'Exclusive Offer', 'woofunnels-order-bump' ),
				'exclusive_content_position'  => 'wfob_exclusive_above_description',


				'social_proof_enable'                    => 'false',
				'social_proof_heading'                   => __( '30% of Our Customers Choose this Upgrade', 'woofunnels-order-bump' ),
				'social_proof_content'                   => __( 'This is by far the most popular option with over 30% of customers choosing this value offer. {{product_regular_price}} worth but today only {{price}} ({{saving_value}} / {{saving_percentage}} savings).', 'woofunnels-order-bump' ),
				'social_proof_tooltip_bg_color'          => '#ffffff',
				'social_proof_tooltip_font_size'         => '12',
				'social_proof_tooltip_color'             => '#353030',
				'social_proof_tooltip_heading_bg_color'  => '#353030',
				'social_proof_tooltip_heading_font_size' => '14',
				'social_proof_tooltip_heading_color'     => '#ffffff',

				'layout'      => 'layout_3',
				'layout_name' => __( 'Skin 3', 'woofunnels-order-bump' ),
				'class_name'  => 'WFOB_Layout_3',

				'error_color'               => '#E20707',
				'product_title'             => __( 'Exclusive Offer', 'woofunnels-order-bump' ),
				'product_preview_title'     => __( 'Exclusive Offer', 'woofunnels-order-bump' ),
				'product_small_title'       => __( 'Add {{product_name}} for just {{price}}', 'woofunnels-order-bump' ),
				'product_featured_image'    => true,
				'product_description'       => __( 'The long description can come here', 'woofunnels-order-bump' ),
				'product_small_description' => __( 'Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text  <br>{{more}}', 'woofunnels-order-bump' ),

				'product_add_button_text'    => __( 'ADD', 'woofunnels-order-bump' ),
				'product_added_button_text'  => __( 'ADDED', 'woofunnels-order-bump' ),
				'product_remove_button_text' => __( 'REMOVE', 'woofunnels-order-bump' ),
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
			$text          = isset( $design_data["product_{$product_key}_{$old_key}"] ) ? $design_data["product_{$product_key}_{$old_key}"] : $default_value;

			return $text;

		}
	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_3' );
}