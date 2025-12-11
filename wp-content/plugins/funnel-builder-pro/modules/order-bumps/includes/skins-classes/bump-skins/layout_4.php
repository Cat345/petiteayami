<?php
if ( ! class_exists( 'WFOB_Layout_4' ) ) {
	class WFOB_Layout_4 extends WFOB_Layout_3 {
		protected static $slug = 'layout_4';

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
			return WFOB_PLUGIN_URL . '/assets/img/skin-4.jpg';
		}


		public static function get_default_models() {
			return array(
				'heading_color'       => '#009900',
				'heading_hover_color' => '',
				'heading_font_size'   => '14',


				'sub_heading_font_size'   => '14',
				'sub_heading_color'       => '#353030',
				'sub_heading_hover_color' => '',

				'sub_content_font_size' => '13',
				'sub_content_color'     => '#353030',

				'add_button_font_size'         => '13',
				'add_button_enable_box_border' => 'true',
				'add_button_border_style'      => 'solid',
				'add_button_border_color'      => '#353030',
				'add_button_border_width'      => '1',
				'add_button_padding'           => '5 12 5 12',
				'add_button_border_radius'     => '0',
				'add_button_width'             => '78',

				'add_button_color'          => '#ffffff',
				'add_button_hover_color'    => '',
				'add_button_bg_color'       => '#353030',
				'add_button_hover_bg_color' => '',


				'added_button_color'    => '#dcdcdc',
				'added_button_bg_color' => '#f7f7f7',

				'remove_button_color'    => '#ffffff',
				'remove_button_bg_color' => '#e43b2c',


				'enable_price'         => true,
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
				'social_proof_enable'  => 'false',

				'layout'      => 'layout_4',
				'layout_name' => __( 'Skin 4', 'woofunnels-order-bump' ),
				'class_name'  => 'WFOB_Layout_4',

				'product_title'         => __( "<span style='color:#E15334'>Yes!</span> Add ", 'woofunnels-order-bump' ) . '{{product_name}}' . __( ' to my order', 'woofunnels-order-bump' ),
				'product_preview_title' => __( "<span style='color:#E15334'>Yes!</span>" . ' Add Complete Skin Care Pack to my order', 'woofunnels-order-bump' ),

				'product_small_title'    => '',
				'product_featured_image' => true,

				'product_small_description'  => __( "<span><strong style='color: #008000;'>Exclusive Offer:</strong></span> Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry. {{more}} ", 'woofunnels-order-bump' ),
				'product_add_button_text'    => __( 'ADD', 'woofunnels-order-bump' ),
				'product_added_button_text'  => __( 'ADDED', 'woofunnels-order-bump' ),
				'product_remove_button_text' => __( 'REMOVE', 'woofunnels-order-bump' ),
				'product_read_more'          => __( ' more...', 'woofunnels-order-bump' ),

				'product_image_url'            => WFOB_PLUGIN_URL . '/admin/assets/img/preview_bump_product_icon.jpg',
				'product_image_position_class' => 'wfob_img_position_left',
				'product_image_position_width' => '96',
				'product_image_position'       => 'center',
				'product_description'          => __( "The long description can come here", 'woofunnels-order-bump' ),
				'price_numeric'                => '35.67',
				'product_price'                => wc_format_sale_price( 39.97, 35.67 ),
				'product_price_numeric'        => '35.67',


			);
		}


	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_4' );
}