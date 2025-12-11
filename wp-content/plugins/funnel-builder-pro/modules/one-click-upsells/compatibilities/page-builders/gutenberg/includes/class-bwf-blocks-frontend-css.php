<?php
/**
 * Enqueue CSS/JS of all the blocks.
 *
 * @since   1.0.0
 * @package BWF Blocks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'BWF_Blocks_Upsell_Frontend_CSS' ) ) {
	/**
	 * Class to Enqueue CSS of all the blocks.
	 *
	 * @category class
	 */
	class BWF_Blocks_Upsell_Frontend_CSS {
		/**
		 * Instance of this class
		 *
		 * @var null
		 */
		private static $instance = null;

		/**
		 * Google fonts to enqueue
		 *
		 * @var array
		 */
		public static $gfonts = array();

		/**
		 * Instance Control
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Class Constructor.
		 */
		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_inline_css' ), 20 );
			// add_action( 'wp_head', array( $this, 'frontend_gfonts' ), 90 );
		}

		/**
		 * Outputs extra css for blocks.
		 */
		public function frontend_inline_css() {
			if ( function_exists( 'has_blocks' ) && has_blocks( get_the_ID() ) ) {
				global $post;
				if ( ! is_object( $post ) ) {
					return;
				}
				if ( WFOCU_Common::get_offer_post_type_slug() !== $post->post_type ) {
					return;
				}
				global $wp_query;
				$post_to_pass = $post;
				if ( isset( $wp_query->query['preview'] ) && 'true' === $wp_query->query['preview'] ) {
					$post_to_pass = $wp_query->posts[0];
				}
				$this->frontend_build_css( $post_to_pass );
			}
		}

		/**
		 * Render Inline CSS helper function
		 *
		 * @param array $css the css for each rendered block.
		 * @param string $style_id the unique id for the rendered style.
		 * @param bool $in_content the bool for whether or not it should run in content.
		 */
		public function render_inline_css( $css, $style_id, $in_content = false ) {
			if ( ! is_admin() ) {
				wp_register_style( $style_id, false );
				wp_enqueue_style( $style_id );
				wp_add_inline_style( $style_id, $css );
				if ( 1 === did_action( 'wp_head' ) && $in_content ) {
					wp_print_styles( $style_id );
				}
			}
		}

		/**
		 * Gets the parsed blocks, need to use this becuase wordpress 5 doesn't seem to include gutenberg_parse_blocks
		 *
		 * @param string $content string of page/post content.
		 */
		public function bwf_parse_blocks( $content ) {
			$parser_class = apply_filters( 'block_parser_class', 'WP_Block_Parser' );
			if ( class_exists( $parser_class ) ) {
				$parser = new $parser_class();

				return $parser->parse( $content );
			} elseif ( function_exists( 'gutenberg_parse_blocks' ) ) {
				return gutenberg_parse_blocks( $content );
			} else {
				return false;
			}
		}


		/**
		 * Outputs extra css for blocks.
		 *
		 * @param $post_object object of WP_Post.
		 */
		public function frontend_build_css( $post_object ) {
			if ( ! is_object( $post_object ) ) {
				return;
			}
			if ( ! method_exists( $post_object, 'post_content' ) ) {
				$blocks = $this->bwf_parse_blocks( $post_object->post_content );
				if ( ! is_array( $blocks ) || empty( $blocks ) ) {
					return;
				}
				$this->compute_bwf_blocks( $blocks );

			}
		}

		public function compute_bwf_blocks( $blocks ) {
			foreach ( $blocks as $indexkey => $block ) {
				$block = apply_filters( 'bwf_blocks_frontend_build_css', $block );
				if ( ! is_object( $block ) && is_array( $block ) && isset( $block['blockName'] ) ) {

					if ( 'bwfblocks/quantity-selector' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwfblocks-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_quantity_selector_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'bwfblocks/product-title' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwfblocks-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_product_title_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'bwfblocks/variation-selector' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwfblocks-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_variation_selector_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'bwfblocks/product-images' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwfblocks-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_product_images_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'bwfblocks/product-description' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwfblocks-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_product_description_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( in_array( $block['blockName'], [ 'bwfblocks/accept-button', 'bwfblocks/reject-button', 'bwfblocks/accept-link', 'bwfblocks/reject-link' ], true ) ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwf-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_button_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'bwfblocks/offer-price' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['uniqueID'] ) ) {
								$unique_id = $blockattr['uniqueID'];
								$style_id  = 'bwf-' . esc_attr( $unique_id );
								if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
									$css = $this->render_offerprice_css_head( $blockattr, $unique_id );
									if ( ! empty( $css ) ) {
										$this->render_inline_css( $css, $style_id );
									}
								}
							}
						}
					}

					if ( 'core/block' === $block['blockName'] ) {
						if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
							$blockattr = $block['attrs'];
							if ( isset( $blockattr['ref'] ) ) {
								$reusable_block = get_post( $blockattr['ref'] );
								if ( $reusable_block && 'wp_block' == $reusable_block->post_type ) {
									$reuse_data_block = $this->bwf_parse_blocks( $reusable_block->post_content );
									$this->compute_bwf_blocks( $reuse_data_block );

									// { make testing reusable block inside itself. }
								}
							}
						}
					}
					if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
						$this->compute_bwf_blocks( $block['innerBlocks'] );
					}
				}
			}
		}

		/**
		 * @param mixed $attr
		 * @param string $indexkey - check whether indexkey is set in $attr[] array or not
		 * @param mixed $default - function return default value which you passed as a 3rd parameter eg. you need 'inherit' value when $indexkey value is true
		 *
		 * @return void
		 */
		public function has_attr( $attr, $indexkey, $screen = '', $default_val = null, $misc_val = '' ) {
			$value = null;
			if ( empty( $screen ) ) {
				if ( isset( $attr[ $indexkey ] ) ) {
					$value = $attr[ $indexkey ];
				}
			} else {
				if ( isset( $attr[ $indexkey ] ) && isset( $attr[ $indexkey ][ $screen ] ) ) {
					$value = $attr[ $indexkey ][ $screen ];
				}
			}

			return ! is_null( $default_val ) && ! empty( $value ) ? $default_val : $value;
		}

		/**
		 * Render button Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_button_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = 'body .bwf-btn-wrap.bwf-' . $unique_id;

			$screens = array( 'desktop', 'tablet', 'mobile' );
			$button  = $this->has_attr( $attr, 'button' ) ?? [];

			$icon_space = $this->has_attr( $button, 'iconSpace' );
			if ( $button && $icon_space ) {
				$iconPos = $this->has_attr( $button, 'iconPos' );
				$iconPos = $iconPos && 'left' === $iconPos ? 'margin-right' : 'margin-left';
				$css->set_selector( "{$selector_wrapper} .bwf-icon-inner-svg" );
				$css->add_property( $iconPos, "{$icon_space}px" );
			}
			$css->set_selector( $selector_wrapper . ' a.bwf-btn' );
			$css->add_property( 'text-decoration', 'none' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$alignment = $this->has_attr( $attr, 'alignment', $screen );
				$alignment = $alignment ? $alignment : 'center';
				$css->add_property( 'text-align', $alignment );

				$css->set_selector( $selector_wrapper . ' .bwf-btn' );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );

				if ( $this->has_attr( $attr, 'marginAuto', $screen ) && 'full' !== $this->has_attr( $attr, 'align', $screen ) ) {
					$css->add_property( 'margin-left', 'auto' );
					$css->add_property( 'margin-right', 'auto' );
				}

				$css->set_selector( $selector_wrapper . ' .bwf-btn .bwf-btn-inner-text' );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$text = $this->has_attr( $attr, 'text', $screen );
				$css->add_property( 'text', $text );
				if ( $text && isset( $text['align'] ) ) {
					$css->add_property( 'justify-content', $text['align'] );
				}
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );

				$css->set_selector( $selector_wrapper . ' .bwf-btn:hover .bwf-btn-inner-text' );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );

				$css->set_selector( $selector_wrapper . ' .bwf-btn .bwf-icon-inner-svg svg' );
				$i_size = $this->has_attr( $button, 'iconSize' ) ? $this->has_attr( $button, 'iconSize' ) . 'px' : '';
				$css->add_property( 'width', $i_size );
				$css->add_property( 'height', $i_size );

				$css->set_selector( $selector_wrapper . ' .bwf-btn:hover' );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

				// if( $this->has_attr( $attr, 'secondaryContentEnable', $screen ) ) {
				$css->set_selector( $selector_wrapper . ' .bwf-btn .bwf-btn-sub-text' );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'secondaryLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'secondaryLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'secondaryFont', $screen ) );
				$text = $this->has_attr( $attr, 'secondaryText', $screen );
				$css->add_property( 'text', $text );
				if ( $text && isset( $text['align'] ) ) {
					$css->add_property( 'justify-content', $text['align'] );
				}
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColor', $screen ) );
				$css->add_property( 'margin-top', $this->has_attr( $attr, 'contentSpace' ), true );

				$css->set_selector( $selector_wrapper . ' .bwf-btn:hover .bwf-btn-sub-text' );
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColorHover', $screen ) );

				// }


				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, $selector_wrapper . ' .bwf-btn' )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_offerprice_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = '.wfocu-price-wrapper.bwf-' . $unique_id . ' .wp-offer-price-inner';
			$selector_hover   = '.wfocu-price-wrapper.bwf-' . $unique_id . ':hover .wp-offer-price-inner:hover';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
				$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );
				$grid = $this->has_attr( $attr, 'priceSpace', $screen );
				if ( $grid ) {
					$css->add_property( 'gap', $grid['width'] . ( $grid['unit'] ? $grid['unit'] : 'px' ) );
				}

				$css->set_selector( $selector_hover );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .reg_wrapper.bwf-price-wrap.bwf-regular" );
				$css->add_property( 'text-align', $this->has_attr( $attr, 'alignment', $screen ) );
				$css->set_selector( "{$selector_wrapper} .offer_wrapper.bwf-price-wrap.bwf-offer" );
				$css->add_property( 'text-align', $this->has_attr( $attr, 'alignment', $screen ) );

				// ${priceSpace && priceSpace[screen] && priceSpace[screen]['width']? `gap:${priceSpace[screen]['width']}${priceSpace[screen]['unit'] ? priceSpace[screen]['unit'] : 'px'}`: ``}
				/**
				 * Offer Price
				 */
				$css->set_selector( "{$selector_wrapper} .bwf-regular" );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( "{$selector_wrapper} .bwf-regular:hover" );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-reg-label" );
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-reg-label:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				//Price
				// $css->set_selector( "{$selector_wrapper} .bwf-offer" );
				// $css->add_property( 'text', $this->has_attr( $attr, 'regularPriceText', $screen ) );

				// $css->set_selector( "{$selector_wrapper} .bwf-offer:hover" );
				// $css->add_property( 'text', $this->has_attr( $attr, 'regularPriceTextHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-regular-price" );
				$css->add_property( 'color', $this->has_attr( $attr, 'regularPriceColor', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'regularPriceLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'regularPriceLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'regularPriceFont', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'regularPriceText', $screen ) );
				$grid = $this->has_attr( $attr, 'regularPriceSpace', $screen );
				if ( $grid ) {
					$css->add_property( 'padding-left', $grid['width'] . ( $grid['unit'] ? $grid['unit'] : 'px' ) );
				}

				$css->set_selector( "{$selector_wrapper} .wfocu-regular-price:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'regularPriceColorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'regularPriceLineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'regularPriceLetterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'regularPriceFontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'regularPriceTextHover', $screen ) );


				/**
				 * Offer Price
				 */
				$css->set_selector( "{$selector_wrapper} .wfocu-offer-label" );
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColor', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'secondaryLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'secondaryLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'secondaryFont', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'secondaryText', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-offer-label:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'secondaryLineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'secondaryLetterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'secondaryFontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'secondaryTextHover', $screen ) );

				//Price
				$css->set_selector( "{$selector_wrapper} .wfocu-sale-price" );
				$css->add_property( 'color', $this->has_attr( $attr, 'offerPriceColor', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'offerPriceLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'offerPriceLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'offerPriceFont', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'offerPriceText', $screen ) );
				$grid = $this->has_attr( $attr, 'offerPriceSpace', $screen );
				if ( $grid ) {
					$css->add_property( 'padding-left', $grid['width'] . ( $grid['unit'] ? $grid['unit'] : 'px' ) );
				}

				$css->set_selector( "{$selector_wrapper} .wfocu-sale-price:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'offerPriceColorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'offerPriceLineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'offerPriceLetterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'offerPriceFontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'offerPriceTextHover', $screen ) );


				if ( 'desktop' === $screen ) {
					if ( ! $this->has_attr( $attr, 'layoutStyle' ) || 'column' === $this->has_attr( $attr, 'layoutStyle' ) ) {
						$css->set_selector( "{$selector_wrapper}" );
						$css->add_property( 'flex-direction', 'column' );
						$css->set_selector( "{$selector_wrapper} .bwf-offer" );
						$css->add_property( 'margin-top', $this->has_attr( $attr, 'contentSpace', $screen ), true );
					} else {
						$css->set_selector( "{$selector_wrapper} .bwf-price-wrap" );
						$css->add_property( 'display', 'inline-block' );
						$css->add_property( 'width', 'auto' );
					}

				}

				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_quantity_selector_css_head( $attr, $unique_id, $class_sel = '' ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = '.bwf-qty-wrap.bwf-' . $unique_id . ' .wfocu_proqty_inline';
			$selector_hover   = '.bwf-qty-wrap.bwf-' . $unique_id . ' .wfocu_proqty_inline';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( $selector_hover );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper" );
				$textval = $this->has_attr( $attr, 'alignment', $screen );
				if ( $textval ) {
					if ( 'center' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', 'auto' );
					} elseif ( 'right' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', '0px' );
					} elseif ( 'left' === $textval ) {
						$css->add_property( 'margin-left', '0px' );
						$css->add_property( 'margin-right', 'auto' );
					}
				}

				/**
				 * Offer Price
				 */
				$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper label" );
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'selectWidth', $screen ), 'width' );

				if ( $this->has_attr( $attr, 'layoutStyle' ) === 'column' ) {
					$css->add_property( 'display', 'block' );
				}

				$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper label:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper select" );
				$css->add_property( 'width', $this->has_attr( $attr, 'selectWidth', $screen ), 'width' );
				if ( 'desktop' === $screen ) {
					$css->add_property( 'max-width', '100%' );
					if ( ! $this->has_attr( $attr, 'layoutStyle' ) || 'column' === $this->has_attr( $attr, 'layoutStyle' ) ) {
						$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper" );
						$css->add_property( 'flex-direction', 'column' );
					} else {
						$css->set_selector( "{$selector_wrapper} .wfocu-prod-qty-wrapper label" );
						$css->add_property( 'width', 'auto' );
					}
				}

				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_product_title_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = '.bwf-' . $unique_id . ' .wfocu-product-title-wrapper';
			$selector_hover   = '.bwf-' . $unique_id . ':hover .wfocu-product-title-wrapper:hover';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
				$textval = $this->has_attr( $attr, 'alignment', $screen );
				if ( $textval ) {
					if ( 'center' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', 'auto' );
					} elseif ( 'right' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', '0px' );
					} elseif ( 'left' === $textval ) {
						$css->add_property( 'margin-left', '0px' );
						$css->add_property( 'margin-right', 'auto' );
					}
				}

				$css->set_selector( $selector_hover );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );


				/**
				 * Offer Price
				 */
				$css->set_selector( "{$selector_wrapper} .wfocu-product-title" );
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-product-title:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_product_description_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = 'body .bwf-' . $unique_id . '';
			$selector_hover   = 'body .bwf-' . $unique_id . ':hover';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
				$textval = $this->has_attr( $attr, 'alignment', $screen );
				if ( $textval ) {
					if ( 'center' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', 'auto' );
					} elseif ( 'right' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', '0px' );
					} elseif ( 'left' === $textval ) {
						$css->add_property( 'margin-left', '0px' );
						$css->add_property( 'margin-right', 'auto' );
					}
				}

				$css->set_selector( $selector_hover );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

				/**
				 * Offer Price
				 */
				$css->set_selector( "{$selector_wrapper} .wfocu_short_description p" );
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu_short_description p:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_variation_selector_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = '.bwf-' . $unique_id . '.wfocu-variation-selector .wfocu-product-attr-wrapper';
			$selector_hover   = '.bwf-' . $unique_id . '.wfocu-variation-selector .wfocu-product-attr-wrapper:hover';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );

				$css->set_selector( $selector_hover );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );


				$css->set_selector( "{$selector_wrapper} table.variations" );
				$textval = $this->has_attr( $attr, 'alignment', $screen );
				if ( $textval ) {
					if ( 'center' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', 'auto' );
					} elseif ( 'right' === $textval ) {
						$css->add_property( 'margin-left', 'auto' );
						$css->add_property( 'margin-right', '0px' );
					} elseif ( 'left' === $textval ) {
						$css->add_property( 'margin-left', '0px' );
						$css->add_property( 'margin-right', 'auto' );
					}
					$css->set_selector( '.bwf-' . $unique_id . '.wfocu-variation-selector' );
					$css->add_property( 'text-align', $textval );
				}

				$css->set_selector( "{$selector_wrapper} table.variations:hover" );
				$textval = $this->has_attr( $attr, 'textHover', $screen );

				$attributeSpacing = $this->has_attr( $attr, 'attributeSpacing', $screen );
				if ( $attributeSpacing && isset( $attributeSpacing['width'] ) ) {
					$unit = isset( $attributeSpacing['unit'] ) ? $attributeSpacing['unit'] : 'px';
					$css->set_selector( "{$selector_wrapper} table.variations .label, {$selector_wrapper} table.variations .value" );
					$css->add_property( 'padding-bottom', $attributeSpacing['width'] . $unit );
					if ( 'row' === $this->has_attr( $attr, 'layoutStyle' ) ) {
						$css->set_selector( "{$selector_wrapper} table.variations .label" );
						$css->add_property( 'padding-right', $attributeSpacing['width'] . $unit );
					}
				}

				$css->set_selector( "{$selector_wrapper} table.variations .label label" );
				$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

				$css->set_selector( "{$selector_wrapper} table.variations label:hover" );
				$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} table.variations td select" );
				$css->add_property( 'width', $this->has_attr( $attr, 'selectWidth', $screen ), 'width' );
				if ( 'desktop' === $screen ) {
					$css->add_property( 'max-width', '100%' );
					if ( ! $this->has_attr( $attr, 'layoutStyle' ) || 'column' === $this->has_attr( $attr, 'layoutStyle' ) ) {

						$css->set_selector( "{$selector_wrapper} table.variations td.label" );
						$css->add_property( 'display', 'block' );
						$css->set_selector( "{$selector_wrapper} table.variations td.value" );
						$css->add_property( 'display', 'block' );
					}
				}


				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}

		/**
		 * Render heading Block CSS
		 *
		 * @param array $attributes the blocks attribtues.
		 */
		public function render_product_images_css_head( $attr, $unique_id ) {
			$css                   = new BWF_Blocks_CSS();
			$media_query           = array();
			$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
			$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

			$selector_wrapper = '.bwf-' . $unique_id . '.wfocu-variation-selector';
			$selector_hover   = '.bwf-' . $unique_id . '.wfocu-variation-selector:hover';

			$screens = array( 'desktop', 'tablet', 'mobile' );

			foreach ( $screens as $screen ) {
				if ( 'desktop' !== $screen ) {
					$css->start_media_query( $media_query[ $screen ] );
				}

				$css->set_selector( $selector_wrapper );
				$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
				$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
				$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
				$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
				$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
				$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
				$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
				$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
				$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
				$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );

				$css->set_selector( $selector_hover );
				$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
				$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-product-gallery a img" );
				$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );

				$css->set_selector( "{$selector_wrapper} .wfocu-product-thumbnails .wfocu-thumb-col a img" );
				$css->add_property( 'border', $this->has_attr( $attr, 'borderThumbnails', $screen ) );

				if ( 'desktop' !== $screen ) {
					$css->stop_media_query();
				}
			}

			$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

			return $css->custom_css( $custom_css, '.wfocu-price-wrapper.bwf-' . $unique_id )->css_output();

		}
	}

	BWF_Blocks_Upsell_Frontend_CSS::get_instance();
}