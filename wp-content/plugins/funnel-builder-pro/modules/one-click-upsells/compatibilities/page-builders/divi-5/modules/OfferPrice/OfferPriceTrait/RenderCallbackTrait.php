<?php
/**
 * OfferPrice::render_callback()
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Module;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use WFOCU\Modules\OfferPrice\OfferPrice;

trait RenderCallbackTrait {

	private static function get_attr_value( array $attrs, string $key, $default = '' ) {
		// Support innerContent format (element attributes like regLabel, offerLabel).
		if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
			return $attrs[ $key ]['innerContent']['desktop']['value'];
		}
		if ( isset( $attrs[ $key ]['desktop']['value'] ) ) {
			$desktop_value = $attrs[ $key ]['desktop']['value'];
			if ( is_array( $desktop_value ) ) {
				if ( isset( $desktop_value['value'] ) && ! is_array( $desktop_value['value'] ) ) {
					return $desktop_value['value'];
				}
				if ( isset( $desktop_value['text'] ) && ! is_array( $desktop_value['text'] ) ) {
					return $desktop_value['text'];
				}
			}
			return $desktop_value;
		}
		if ( isset( $attrs[ $key ]['desktop'] ) && ! is_array( $attrs[ $key ]['desktop'] ) ) {
			return $attrs[ $key ]['desktop'];
		}
		if ( isset( $attrs[ $key ] ) && ! is_array( $attrs[ $key ] ) ) {
			return $attrs[ $key ];
		}

		return $default;
	}

	private static function is_on( $value, bool $default = false ): bool {
		if ( '' === $value || null === $value ) {
			return $default;
		}
		return true === $value || 'on' === $value || '1' === $value || 'yes' === $value || 'Yes' === $value;
	}

	private static function get_product_key( array $attrs ): string {
		\WFOCU\Modules\ModuleRegistry::ensure_product_data();

		$selected_product = (string) self::get_attr_value( $attrs, 'selectedProduct', '0' );
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader || ! method_exists( WFOCU_Core()->template_loader, 'default_product_key' ) ) {
			return $selected_product;
		}

		$product_key = WFOCU_Core()->template_loader->default_product_key( $selected_product );

		// Verify the key exists in the offer's products — template imports
		// may have stale hashes that don't match the current offer config.
		if ( ! empty( $product_key ) && isset( WFOCU_Core()->template_loader->product_data->products ) ) {
			$products = (array) WFOCU_Core()->template_loader->product_data->products;
			if ( ! isset( $products[ $product_key ] ) ) {
				$product_key = WFOCU_Core()->template_loader->default_product_key( 0 );
			}
		}

		return ! empty( $product_key ) ? (string) $product_key : $selected_product;
	}

	private static function get_price_markup( array $attrs, string $product_key, \WC_Product $product ): string {
		$show_reg_price   = self::is_on( self::get_attr_value( $attrs, 'showRegPrice', 'on' ), true );
		$show_offer_price = self::is_on( self::get_attr_value( $attrs, 'showOfferPrice', 'on' ), true );
		$show_signup_fee  = self::is_on( self::get_attr_value( $attrs, 'showSignupFee', 'off' ), false );
		$show_rec_price   = self::is_on( self::get_attr_value( $attrs, 'showRecPrice', 'off' ), false );

		$reg_label       = (string) self::get_attr_value( $attrs, 'regLabel', '' );
		$offer_label     = (string) self::get_attr_value( $attrs, 'offerLabel', __( 'Offer Price: ', 'woofunnels-upstroke-one-click-upsell' ) );
		$signup_label    = (string) self::get_attr_value( $attrs, 'signupLabel', __( 'Signup Fee: ', 'woofunnels-upstroke-one-click-upsell' ) );
		$recurring_label = (string) self::get_attr_value( $attrs, 'recurringLabel', __( 'Recurring Total: ', 'woofunnels-upstroke-one-click-upsell' ) );

		$regular_price     = \WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' );
		$sale_price        = \WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' );
		$regular_price_raw = (float) \WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
		$sale_price_raw    = (float) \WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

		$reg_label_markup   = '<span class="wfocu-reg-label">' . wp_kses_post( $reg_label ) . '</span>';
		$offer_label_markup = '<span class="wfocu-offer-label">' . wp_kses_post( $offer_label ) . '</span>';

		$price_output = '';
		if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
			if ( $show_reg_price ) {
				$price_output .= '<span class="reg_wrapper">' . $reg_label_markup . '<span class="wfocu-regular-price"><strike>' . $regular_price . '</strike></span></span>';
			}
			if ( $show_offer_price ) {
				$price_output .= '<span class="offer_wrapper">' . $offer_label_markup . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>';
			}
		} else {
			if ( 'variable' === $product->get_type() ) {
				$price_output .= sprintf( '<span class="wfocu-regular-price"><strike><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></strike></span>', esc_attr( $product_key ) );
			}
			if ( $show_offer_price ) {
				$price_output .= '<span class="offer_wrapper">' . $offer_label_markup . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>';
			}
		}

		if ( $show_signup_fee ) {
			$price_output .= \WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . esc_attr( $signup_label ) . '"}}' );
		}

		if ( $show_rec_price ) {
			$price_output .= \WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . esc_attr( $recurring_label ) . '"}}' );
		}

		return $price_output;
	}

	public static function render_callback( array $attrs, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}
			if ( ! isset( $block->parsed_block ) || ! isset( $block->block_type ) ) {
				return '';
			}

			$default_attrs = array();
			if ( class_exists( 'ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration' ) ) {
				try {
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/offer-price' );
				} catch ( \Exception $e ) {
					$default_attrs = array();
				}
			}
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			if ( ! class_exists( 'WFOCU_Core' ) || ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_key  = self::get_product_key( $attrs );
			$product      = isset( $product_data->{$product_key} ) ? $product_data->{$product_key}->data : null;

			if ( ! $product instanceof \WC_Product ) {
				return '';
			}

			$stacked = self::is_on( self::get_attr_value( $attrs, 'offerSliderEnabled', 'on' ), true );
			$class   = $stacked ? 'wfocu-price_block-yes' : 'wfocu-price_block-no';
			$markup  = self::get_price_markup( $attrs, $product_key, $product );

			if ( '' === $markup ) {
				return '';
			}

			$price_html = '<div class="wfocu_offer_price ' . esc_attr( $class ) . '"><div class="wfocu-element wfocu-widget wfocu-widget-wfocu_price"><div class="wfocu-widget-container"><div class="wfocu-price-wrapper">' . $markup . '</div></div></div></div>';

			$parent       = null;
			$parent_attrs = array();
			$parent_id    = '';
			$parent_name  = '';
			if ( isset( $block->parsed_block['id'] ) && isset( $block->parsed_block['storeInstance'] ) ) {
				try {
					$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
					if ( $parent ) {
						$parent_attrs = $parent->attrs ?? array();
						$parent_id    = $parent->id ?? '';
						$parent_name  = $parent->blockName ?? '';
					}
				} catch ( \Exception $e ) {
				}
			}

			return Module::render(
				array(
					'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
					'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,
					'attrs'               => $attrs,
					'elements'            => $elements,
					'id'                  => $block->parsed_block['id'] ?? '',
					'name'                => $block->block_type->name ?? 'wfocu/offer-price',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => array( OfferPrice::class, 'module_classnames' ),
					'stylesComponent'     => array( OfferPrice::class, 'module_styles' ),
					'scriptDataComponent' => array( OfferPrice::class, 'module_script_data' ),
					'parentAttrs'         => $parent_attrs,
					'parentId'            => $parent_id,
					'parentName'          => $parent_name,
					'children'            => array(
						ElementComponents::component(
							array(
								'attrs'         => $attrs['module']['decoration'] ?? array(),
								'id'            => $block->parsed_block['id'] ?? '',
								'orderIndex'    => $block->parsed_block['orderIndex'] ?? 0,
								'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
							)
						),
						$price_html . $content,
					),
				)
			);
		} catch ( \Exception $e ) {
			return '';
		}
	}
}
