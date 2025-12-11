<?php
if ( ! class_exists( 'WFOB_Layout_2' ) ) {
	class WFOB_Layout_2 extends WFOB_Layout_1 {
		protected static $slug = 'layout_2';

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
			return WFOB_PLUGIN_URL . '/assets/img/skin-2.jpg';
		}

		public function get_admin_schema() {
			return parent::get_admin_schema();
		}

		public static function get_default_models() {
			return [
				'heading_background'        => '#1d96f3',
				'heading_hover_background'  => '38a9ff',
				'heading_font_size'         => '14',
				'heading_color'             => '#ffffff',
				'heading_hover_color'       => '',
				'heading_box_padding'       => '10 12 10 12',
				'heading_box_border_style'  => 'none',
				'heading_box_border_color'  => '',
				'heading_box_border_width'  => '0 0 0 0',
				'heading_box_border_radius' => '0',

				'header_enable_pointing_arrow' => 'true',
				'point_animation'              => '1',
				'point_animation_color'        => '#D80027',
				'error_color'                  => '#e15334',

				'enable_featured_image_border' => 'true',
				'featured_image_border_style'  => 'solid',
				'featured_image_border_color'  => '#ECECEC',
				'featured_image_border_width'  => '1 1 1 1',
				'featured_image_border_radius' => '0',

				'content_font_size'                  => '14',
				'content_color'                      => '#353030',
				'content_variation_link_color'       => '#e15334',
				'content_variation_link_hover_color' => '',
				'content_box_padding'                => '16 16 16 16',


				'enable_price'         => "true",
				'price_font_size'      => '12',
				'price_color'          => '#ffffff',
				'price_sale_font_size' => '',
				'price_sale_color'     => '',


				'box_background'       => '#FFFFFF',
				'box_background_hover' => '',
				'box_padding'          => '0',
				'enable_box_border'    => 'true',
				'border_style'         => 'solid',
				'border_color'         => '#cccccc',
				'border_width'         => '1',
				'box_border_radius'    => '0',
				'bump_max_width'       => '',

				'exclusive_content_bg_color'  => '',
				'exclusive_content_font_size' => '12',
				'exclusive_content_color'     => '#09B29C',
				'exclusive_content_enable'    => 'false',
				'exclusive_content'           => __( 'Exclusive Offer', 'woofunnels-order-bump' ),
				'exclusive_content_position'  => 'wfob_exclusive_above_description',

				'social_proof_enable'  => 'false',
				'social_proof_heading' => __( '30% of Our Customers Choose this Upgrade', 'woofunnels-order-bump' ),
				'social_proof_content' => __( 'This is by far the most popular option with over 30% of customers choosing this value offer. {{product_regular_price}} worth but today only {{price}} ({{saving_value}} / {{saving_percentage}} savings).', 'woofunnels-order-bump' ),

				'social_proof_tooltip_bg_color'          => '#ffffff',
				'social_proof_tooltip_font_size'         => '12',
				'social_proof_tooltip_color'             => '#353030',
				'social_proof_tooltip_heading_bg_color'  => '#09b29c',
				'social_proof_tooltip_heading_font_size' => '14',
				'social_proof_tooltip_heading_color'     => '#ffffff',

				'layout'      => 'layout_2',
				'layout_name' => __( 'Skin 2', 'woofunnels-order-bump' ),
				'class_name'  => 'WFOB_Layout_2',

				'product_title'                => __( "<span style='color:#E15334'>Yes!</span> Add ", 'woofunnels-order-bump' ) . '{{product_name}}' . __( ' to my order', 'woofunnels-order-bump' ),
				'product_preview_title'        => __( "<span style='color:#E15334'>Yes!</span>" . ' Add Complete Skin Care Pack to my order', 'woofunnels-order-bump' ),
				'product_featured_image'       => true,
				'product_image_url'            => WFOB_PLUGIN_URL . '/admin/assets/img/preview_bump_product_icon.jpg',
				'product_image_position_class' => 'wfob_img_position_left',
				'product_image_position_width' => '96',
				'product_image_position'       => 'center',
				'product_description'          => __( "Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry.", 'woofunnels-order-bump' ),
				'product_price'                => wc_format_sale_price( 39.97, 35.67 ),
				'product_price_numeric'        => '35.67',
			];
		}


	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_2' );
}