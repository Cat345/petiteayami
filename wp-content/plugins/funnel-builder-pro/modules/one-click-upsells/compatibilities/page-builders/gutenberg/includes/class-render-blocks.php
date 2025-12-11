<?php
/**
 * This file handles the dynamic parts of our blocks.
 *
 * @package BWFBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'BWFBlocksUpsell_Render_Block' ) ) {

	/**
	 * Render the dynamic aspects of our blocks.
	 *
	 * @since 1.2.0
	 */
	class BWFBlocksUpsell_Render_Block {
		/**
		 * Instance.
		 *
		 * @access private
		 * @var object Instance
		 * @since 1.2.0
		 */
		private static $instance;

		/**
		 * Initiator.
		 *
		 * @return object initialized object of class.
		 * @since 1.2.0
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_blocks' ) );
		}

		/**
		 * Register our dynamic blocks.
		 *
		 * @since 1.2.0
		 */
		public function register_blocks() {
			// Only load if Gutenberg is available.
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			$bwfblocks = [
				[
					'name'     => 'bwfblocks/accept-button',
					'callback' => 'do_accept_button_block',
				],
				[
					'name'     => 'bwfblocks/reject-button',
					'callback' => 'do_reject_button_block',
				],
				[
					'name'     => 'bwfblocks/accept-link',
					'callback' => 'do_accept_link_block',
				],
				[
					'name'     => 'bwfblocks/reject-link',
					'callback' => 'do_reject_link_block',
				],
				[
					'name'     => 'bwfblocks/offer-price',
					'callback' => 'do_offer_block',
				],
				[
					'name'     => 'bwfblocks/quantity-selector',
					'callback' => 'do_quantity_selector_block',
				],
				[
					'name'     => 'bwfblocks/product-description',
					'callback' => 'do_product_description_block',
				],
				[
					'name'     => 'bwfblocks/product-title',
					'callback' => 'do_product_title_block',
				],
				[
					'name'     => 'bwfblocks/variation-selector',
					'callback' => 'do_variation_selector_block',
				],
				[
					'name'     => 'bwfblocks/product-images',
					'callback' => 'do_product_images_block',
				],
			];

			foreach ( $bwfblocks as $block ) {
				register_block_type( $block['name'], array(
					'render_callback' => array( $this, $block['callback'] ),
				) );
			}
		}

		public function has_block_visibiliy_classes( $settings, $classes ) {
			if ( ! empty( $settings['vsdesk'] ) ) {
				$classes[] = 'bwf-hide-lg';
			}
			if ( ! empty( $settings['vstablet'] ) ) {
				$classes[] = 'bwf-hide-md';
			}
			if ( ! empty( $settings['vsmobile'] ) ) {
				$classes[] = 'bwf-hide-sm';
			}

			return $classes;
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_accept_button_block( $attributes, $content ) {
			if ( ! class_exists( 'WFOCU_Guten_Accept_Button' ) ) {
				return;
			}
			$defaults = bwfupsell_get_block_defaults();

			$attributes    = wp_parse_args( $attributes, $defaults['accept-button'] );
			$accept_button = new WFOCU_Guten_Accept_Button();

			return $accept_button->html( $attributes, $content );
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_reject_button_block( $attributes, $content ) {
			if ( ! class_exists( 'WFOCU_Guten_Reject_Button' ) ) {
				return;
			}
			$defaults = bwfupsell_get_block_defaults();

			$attributes    = wp_parse_args( $attributes, $defaults['reject-button'] );
			$reject_button = new WFOCU_Guten_Reject_Button();

			return $reject_button->html( $attributes, $content );
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_accept_link_block( $attributes, $content ) {
			if ( ! class_exists( 'WFOCU_Guten_Accept_Link' ) ) {
				return;
			}
			$defaults = bwfupsell_get_block_defaults();

			$attributes  = wp_parse_args( $attributes, $defaults['accept-link'] );
			$accept_link = new WFOCU_Guten_Accept_Link();

			return $accept_link->html( $attributes, $content );
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_reject_link_block( $attributes, $content ) {
			if ( ! class_exists( 'WFOCU_Guten_Reject_Link' ) ) {
				return;
			}
			$defaults = bwfupsell_get_block_defaults();

			$attributes = wp_parse_args( $attributes, $defaults['reject-link'] );

			$reject_link = new WFOCU_Guten_Reject_Link();

			return $reject_link->html( $attributes, $content );
		}

		/**
		 * Output the dynamic aspects of our Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public static function do_button_block( $attributes, $content ) {

			$output = '';

			$settings = $attributes;

			$classNames = array(
				'bwf-btn-wrap',
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
				'wp-block-wrap'
			);

			if ( ! empty( $settings['className'] ) ) {
				// $classNames[] = $settings['className'];
			}

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );

			$type                = $settings['type'] ?? 'solid';
			$buttonAnchorClasses = array( 'bwf-btn', $type );

			if ( ! empty( $settings['anchorclasses'] ) ) {
				$buttonAnchorClasses[] = $settings['anchorclasses'];
			}

			if ( ! empty( $settings['secondaryContentEnable'] ) ) {
				$buttonAnchorClasses[] = 'has-secondary-text';
			}

			$button_rel    = '';
			$button_target = '';
			$button        = isset( $settings['button'] ) ? $settings['button'] : [];
			if ( isset( $button['newTab'] ) && ! empty( $button['newTab'] ) ) {
				$button_target = '_blank';
				$button_rel    = 'noopener noreferrer';
			}

			if ( isset( $button['noFollow'] ) && ! empty( $button['noFollow'] ) ) {
				$button_rel .= ' nofollow';
			}
			$output .= sprintf( '<a %1$s data-key="%2$s" %3$s href="javascript:void(0);">', bwfblocks_attr( 'button-anchor', array(
				'id'     => isset( $settings['anchor'] ) ? $settings['anchor'] : '',
				'class'  => implode( ' ', $buttonAnchorClasses ),
				'href'   => isset( $settings['link'] ) ? esc_url( $settings['link'] ) : '',
				'target' => $button_target,
				'rel'    => $button_rel,
			), $settings ), isset( $settings['product'] ) ? $settings['product'] : '', isset( $settings['attributes'] ) ? $settings['attributes'] : '' );

			$outputsvg = '';
			//Button Icon Left Side
			if ( isset( $button['icon'] ) && ! empty( $button['icon'] ) ) {
				$outputsvg .= '<span class="bwf-icon-inner-svg">' . $button['icon'] . '</span>';
			}

			//Button content
			$content = isset( $settings['content'] ) ? $settings['content'] : '';
			$output  .= isset( $settings['content'] ) ? '<span class="bwf-btn-inner-text">' : '';
			if ( empty( $button['iconPos'] ) || ( ! empty( $button['iconPos'] ) && 'left' === $button['iconPos'] ) ) {
				$output .= $outputsvg;
			}
			$output .= $content;
			if ( ! empty( $button['iconPos'] ) && 'right' === $button['iconPos'] ) {
				$output .= $outputsvg;
			}
			$output .= '</span>';
			//Button Icon Right Side
			// if( isset( $button['icon'] ) && ! empty( $button['icon'] ) && 'right' === $button['iconPos'] ) {
			// 	$output .= '<span class="bwf-icon-inner-svg">' . $button['icon'] . '</span>';
			// }

			// Button Secondary Text (Sub heading)
			if ( isset( $settings['secondaryContentEnable'] ) && ! empty( $settings['secondaryContentEnable'] ) && $settings['secondaryContentEnable'] ) {
				$content2 = isset( $settings['secondaryContent'] ) ? $settings['secondaryContent'] : '';
				$output   .= '<span class="bwf-btn-sub-text">' . $content2 . '</span>';
			}

			$output .= '</a>';
			$output .= '</div>';

			return $output;
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_offer_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Offer_Price' ) ) {
				return;
			}
			$output   = '';
			$defaults = bwfupsell_get_block_defaults();

			$offer_price = new WFOCU_Guten_Offer_Price();

			$settings = wp_parse_args( $attributes, $defaults['offer-block'] );

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );

			$attr = array(
				'product'          => isset( $settings['product'] ) ? $settings['product'] : '',
				'show_reg_price'   => $settings['contentEnable'],
				'show_offer_price' => $settings['secondaryContentEnable'],
				'reg_label'        => $settings['content'],
				'offer_label'      => $settings['secondaryContent'],
			);

			ob_start();
			$offer_price->html( $attr );

			return $output . ob_get_clean() . '</div>';
		}

		/**
		 * Output the dynamic aspects of our Advance Button blocks.
		 *
		 * @param array $attributes The block attributes.
		 * @param string $content The inner blocks.
		 *
		 * @since 1.2.0
		 */
		public function do_quantity_selector_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Quantity_Selector' ) ) {
				return;
			}
			$defaults = bwfupsell_get_block_defaults();

			$settings = wp_parse_args( $attributes, $defaults['product-quantity'] );

			$output = '';

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output            .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );
			$quantity_selector = new  WFOCU_Guten_Quantity_Selector();
			$attr              = array(
				'product' => isset( $settings['product'] ) ? $settings['product'] : '',
				'text'    => $settings['content'],
			);
			ob_start();
			$quantity_selector->html( $attr );
			$qty_html = ob_get_clean();

			return $qty_html ? $output . $qty_html . '</div>' : '';
		}

		public function do_product_description_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Product_Short_Desc' ) ) {
				return;
			}
			$product_desc = new WFOCU_Guten_Product_Short_Desc();
			$output       = '';
			$defaults     = bwfupsell_get_block_defaults();

			$settings = wp_parse_args( $attributes, $defaults['product-description'] );

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );
			$attr   = array(
				'product' => isset( $settings['product'] ) ? $settings['product'] : '',
				'htmlTag' => $settings['htmlTag'],
			);
			ob_start();
			$product_desc->html( $attr );
			$html = ob_get_clean();

			return $html ? $output . $html . '</div>' : '';
		}

		public function do_product_title_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Product_Title' ) ) {
				return;
			}
			$product_title = new  WFOCU_Guten_Product_Title();
			$output        = '';
			$defaults      = bwfupsell_get_block_defaults();

			$settings = wp_parse_args( $attributes, $defaults['product-title'] );

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );
			$attr   = array(
				'product'     => isset( $settings['product'] ) ? $settings['product'] : '',
				'text'        => isset( $settings['content'] ) ? $settings['content'] : '',
				'header_size' => $settings['htmlTag'],
			);
			ob_start();
			$product_title->html( $attr );
			$html = ob_get_clean();

			return $html ? $output . $html . '</div>' : '';
		}

		public function do_variation_selector_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Variation_Selector' ) ) {
				return;
			}
			$variation_selector = new WFOCU_Guten_Variation_Selector();
			$output             = '';
			$defaults           = bwfupsell_get_block_defaults();

			$settings = wp_parse_args( $attributes, $defaults['variation-selector'] );

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );
			$attr   = array(
				'product'          => isset( $settings['product'] ) ? $settings['product'] : '',
				'widget_block_id'  => 'bwf-' . $settings['uniqueID'],
				'attr_value_block' => true,
			);
			ob_start();
			$variation_selector->html( $attr );
			$html = ob_get_clean();

			return $html ? $output . $html . '</div>' : '';
		}

		public function do_product_images_block( $attributes, $content ) {

			if ( ! class_exists( 'WFOCU_Guten_Product_Image' ) ) {
				return;
			}
			$product_image = new WFOCU_Guten_Product_Image();
			$output        = '';
			$defaults      = bwfupsell_get_block_defaults();

			$settings = wp_parse_args( $attributes, $defaults['product-images'] );

			$classNames = array(
				'bwf-' . $settings['uniqueID'],
				$settings['classWrap'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output .= sprintf( '<div %1$s>', bwfblocks_attr( 'button', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['blockID'] ) ? $settings['blockID'] : null,
			), $settings ) );
			$attr   = array(
				'product'         => isset( $settings['product'] ) ? $settings['product'] : '',
				'widget_block_id' => 'bwf-' . $settings['uniqueID'],
				'slider_enabled'  => $settings['enableSlider'],
			);
			ob_start();
			$product_image->html( $attr );
			$html = ob_get_clean();

			return $html ? $output . $html . '</div>' : '';
		}
	}

	BWFBlocksUpsell_Render_Block::get_instance();
}