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

/**
 * Class to Enqueue CSS of all the blocks.
 *
 * @category class
 */
class SLINGBLOCKS_Frontend_CSS {
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
	 * Available slingblocks in current post content
	 * @var array
	 */
	public static $slingblocks = array();

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
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_inline_css' ),999 );
	}

	/**
	 * Outputs extra css for blocks.
	 */
	public function frontend_inline_css() {
		$post_id = get_the_ID();
		global $post;

		if ( did_action( 'wfacp_after_template_found' ) ) {
			$aero_instance = wfacp_template();
			if ( ! is_null( $aero_instance ) && 'gutenberg' !== $aero_instance->get_template_type()) {
					return;
			}
			// do not overrides global $post for embed & customizer template
			if ( ! is_null( $aero_instance ) && ! $aero_instance instanceof WFACP_Pre_Built ) {
				$post_id = WFACP_Common::get_id();
				$post    = WFACP_Core()->template_loader->get_checkout_post();
			}
		}

		if ( ! is_object( $post ) ) {
			return;
		}

		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $post_id ) ) {
			return;
		}
		global $wp_query;
		$post_to_pass = $post;
		if ( isset( $wp_query->query['preview'] ) && 'true' === $wp_query->query['preview'] ) {
			$post_to_pass = $wp_query->posts[0];
		}
		$this->frontend_build_css( $post_to_pass );
	}

	/**
	 * Render Inline CSS helper function
	 *
	 * @param string $css the css for each rendered block.
	 * @param string $style_id the unique id for the rendered style.
	 * @param bool $in_content the bool for whether or not it should run in content.
	 */
	public function render_inline_css( $css, $style_id, $in_content = false ) {
		if ( is_admin() ) {
			return;
		}
		wp_register_style( $style_id, false );
		wp_enqueue_style( $style_id );
		wp_add_inline_style( $style_id, $css );
		if ( 1 === did_action( 'wp_head' ) && $in_content ) {
			wp_print_styles( $style_id );
		}
	}

	/**
	 * Gets the parsed blocks, need to use this because wordpress 5 doesn't seem to include gutenberg_parse_blocks
	 *
	 * @param string $content string of page/post content.
	 */
	public function bwf_parse_blocks( $content ) {
		$parser_class = apply_filters( 'block_parser_class', 'WP_Block_Parser' );
		if ( class_exists( $parser_class ) ) {
			$parser = new $parser_class();

			return $parser->parse( $content );
		}
		if ( function_exists( 'gutenberg_parse_blocks' ) ) {
			return gutenberg_parse_blocks( $content );
		}

		return false;
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

	public function compute_bwf_blocks( $blocks, $child_block = false ) {
		foreach ( $blocks as $indexkey => $block ) {
			$block = apply_filters( 'bwf_blocks_frontend_build_css', $block );
			if ( ! is_object( $block ) && is_array( $block ) && isset( $block['blockName'] ) ) {

				if ( $this->is_sling_block( $block['blockName'] ) ) {
					array_push( self::$slingblocks, $block['blockName'] );
				}

				if ( 'sling-block/accordion' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_accordion_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/columns' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_columns_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/col' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_col_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/advance-button' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_adv_button_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}
				if ( 'sling-block/button' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_button_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/advance-heading' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_heading_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/icon-list' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_list_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/space-divider' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_divider_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/icon' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_icon_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/progress-bar' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$blockattr = $block['attrs'];
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_progress_css_head( $blockattr, $unique_id );
								if ( ! empty( $css ) ) {
									$this->render_inline_css( $css, $style_id );
								}
							}
						}
					}
				}

				if ( 'sling-block/countdown' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$defaults  = [
							'preTextText'  => [
								'desktop' => [
									'align' => 'center'
								],
							],
							'postTextText' => [
								'desktop' => [
									'align' => 'center'
								],
							],
						];
						$blockattr = wp_parse_args( $block['attrs'], $defaults );
						if ( isset( $blockattr['uniqueID'] ) ) {
							$unique_id = $blockattr['uniqueID'];
							$style_id  = 'sling-block-' . esc_attr( $unique_id );
							if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
								$css = $this->render_count_down_css_head( $blockattr, $unique_id );
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
					$this->compute_bwf_blocks( $block['innerBlocks'], true );
				}
			}
		}
	}

	/**
	 * Checks if a block is slingblock
	 *
	 * @param $block_name
	 *
	 * @return bool
	 */
	public function is_sling_block( $block_name ) {
		return ! is_null( $block_name ) && strpos( $block_name, 'sling-block/' ) === 0;
	}

	/**
	 * @param $attr
	 * @param $indexkey - check whether indexkey is set in $attr[] array or not
	 * @param $screen
	 * @param $default_val - function return default value which you passed as a 3rd parameter eg. you need 'inherit' value when $indexkey value is true
	 * @param $misc_val
	 *
	 * @return mixed|null
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

		return ! is_null( $default_val ) && empty( $value ) ? $default_val : $value;
	}

	/**
	 * Render Accordion Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_accordion_css_head( $attr, $unique_id ) {

		if ( ! isset( $attr['accordionLayout'] ) ) {
			return '';
		}

		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = '.bwf-accordion.bwf-accordion-' . $unique_id . '.bwf-' . $attr['accordionLayout'];
		$active_class     = $selector_wrapper . '>.bwf-accordion-wrapper.bwf-accordion-active>.bwf-accordion-head';

		$screens = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}

			$css->set_selector( $selector_wrapper );
			$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-head' );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderTitle', $screen ) );

			$css->set_selector( $active_class );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderTitleActive', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundActive', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-head h2, ' . $selector_wrapper . ' .bwf-accordion-head' );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true ); // 3rd arg.. true will treat as data in { unit:'', value:'' } structure
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-head svg' );
			$css->add_property( 'fill', $this->has_attr( $attr, 'color', $screen ) );


			$css->set_selector( $selector_wrapper . ' .bwf-accordion-head h2:hover, ' . $selector_wrapper . ' .bwf-accordion-head:hover' );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightHover', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingHover', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'fontHover', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'textHover', $screen ) );

			$css->set_selector( $active_class . ' ,' . $active_class . ' h2' );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorActive', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeightActive', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacingActive', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'fontActive', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'textActive', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-head:hover' );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderTitleHover', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-wrapper' );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-wrapper:hover' );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-accordion-wrapper:not(:last-child)' );
			$css->add_property( 'margin-bottom', $this->has_attr( $attr, 'itemSpacing', $screen ), true ); // 3rd arg.. true will treat as data in { unit:'', value:'' } structure


			$css->set_selector( $selector_wrapper . ' .bwf-accordion-body' );
			$css->add_property( 'margin', $this->has_attr( $attr, 'contentMargin', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'contentPadding', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'contentBackground', $screen ) );

			// check if margin-bottom | margin-top | itemSpacing is empty then add border-bottom:0 on accordion for border collapsed between accordion items
			if ( ( ! $this->has_attr( $attr, 'margin', $screen ) && ! $this->has_attr( $attr, 'itemSpacing', $screen ) ) || ( ( $this->has_attr( $attr, 'margin', $screen ) && empty( $this->has_attr( $attr, 'margin', $screen )['top'] ) && empty( $this->has_attr( $attr, 'margin', $screen )['bottom'] ) ) && ( ! $this->has_attr( $attr, 'itemSpacing', $screen ) || ( $this->has_attr( $attr, 'itemSpacing', $screen ) && empty( $this->has_attr( $attr, 'itemSpacing', $screen )['value'] ) ) ) )

			) {
				$css->set_selector( $selector_wrapper . ' .bwf-accordion-wrapper:not(:last-child)' );
				$css->add_property( 'border-bottom', '0px' );

				if ( 'accordion-4' !== $attr['accordionLayout'] ) {
					$css->set_selector( $selector_wrapper . ' .bwf-accordion-wrapper:not(.bwf-accordion-active):not(:last-child)>.bwf-accordion-head' );
					$css->add_property( 'border-bottom', '0px' );
				}
			}

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		return $css->css_output();
	}

	public function map_column_old_val( $layout ) {
		if ( 'equal' == $layout ) {
			return '';
		}
		$value = $layout;
		switch ( $layout ) {
			case 'left-golden':
				$value = '2-1';
				break;
			case 'right-golden':
				$value = '1-2';
				break;
			case 'right-forty':
				$value = '1-1-1-2';
				break;
			case 'left-forty':
				$value = '2-1-1-1';
				break;
			case 'center-exwide':
				$value = '1-4-1';
				break;
			case 'center-wide':
				$value = '1-3-1';
				break;
			case 'center-half':
				$value = '1-2-1';
				break;
			case 'right-half':
				$value = '1-1-2';
				break;
			case 'left-half':
				$value = '2-1-1';
				break;
		}

		return $value;
	}

	public function manage_wrapper_width( $attr, $screen ) {
		$dynamicWidthStyle = '';

		$calc_margin = $this->has_attr( $attr, 'margin', $screen );
		// check if margin-left || margin-right have value or not
		if ( null == $this->has_attr( $attr, 'margin', $screen ) || ( empty( $calc_margin['left'] ) || empty( $calc_margin['right'] ) ) ) {
			return $dynamicWidthStyle;
		}
		$margin_horz = $calc_margin['left'] + $calc_margin['right'];
		$margin_unit = ! empty( $calc_margin['unit'] ) ? $calc_margin['unit'] : 'px';

		$dynamicWidthStyle = 'calc(100% - ' . $margin_horz . $margin_unit . ')';

		return $dynamicWidthStyle;
	}

	/**
	 * Render columns Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_columns_css_head( $attr, $unique_id ) {

		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = 'body .bwf-section-wrap.bwf-section-' . $unique_id;

		$css->set_selector( $selector_wrapper . '>.bwf-col' );
		$css->add_property( 'margin', '0 auto' );
		if ( $this->has_attr( $attr, 'directionCol' ) ) {
			$css->add_property( 'flex-direction', 'column' );
		}

		// Dynamic style for normal screen
		$screens = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
				$verticalAlignment = $this->has_attr( $attr, 'verticalAlignment' . $screen );
			} else {
				$verticalAlignment = $this->has_attr( $attr, 'verticalAlignment' );
			}

			$this->set_inner_column_width( $css, $attr, $screen, $selector_wrapper );

			$css->set_selector( $selector_wrapper );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'text-align', $this->has_attr( $attr, 'contentAlign', $screen ) );

			$column_wrapper_width = $this->manage_wrapper_width( $attr, $screen );
			if ( $column_wrapper_width ) {
				$css->add_property( 'width', $column_wrapper_width );
			}

			$css->set_selector( $selector_wrapper . ':hover' );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

			$css->set_selector( $selector_wrapper . '>.bwf-col' );
			$css->add_property( 'gap', $this->has_attr( $attr, 'columnGapping', $screen ), true );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'gridAlignment', $screen ) );
			if ( $verticalAlignment ) {
				if ( 'top' === $verticalAlignment ) {
					$css->add_property( 'align-items', 'flex-start' );
				} elseif ( 'center' === $verticalAlignment ) {
					$css->add_property( 'align-items', 'center' );
				} elseif ( 'bottom' === $verticalAlignment ) {
					$css->add_property( 'align-items', 'flex-end' );
				}
				$css->add_property( 'width', '100%' );
			}

			if ( 'tablet' === $screen && true === $this->has_attr( $attr, 'colIntoRowTablet' ) ) {
				$css->add_property( 'flex-direction', 'column' );

				if ( $this->has_attr( $attr, 'reverseColTablet' ) ) {
					$css->add_property( 'flex-direction', 'column-reverse !important' );
				}
			}
			if ( 'mobile' === $screen ) {
				if ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) && ! isset( $attr['reverseColMobile'] ) ) || true === $this->has_attr( $attr, 'reverseColMobile' ) ) {
					$css->add_property( 'flex-direction', 'column-reverse !important' );
				}
			}

			if ( ! empty( $attr['layout'] ) && is_array( $attr['layout'] ) && ! empty( $attr['layout'][ $screen ] ) ) {
				switch ( $attr['layout'][ $screen ] ) {
					case 'grid':
						if ( 'desktop' === $screen && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) {
							$css->add_property( 'flex-direction', 'row-reverse !important' );
							$css->add_property( 'flex-wrap', 'wrap-reverse' );
						} elseif ( 'tablet' === $screen && ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) ) ) || ( ! isset( $attr['reverseColDesktop'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
							if ( $this->has_attr( $attr, 'reverseColTablet' ) ) {
								$css->add_property( 'flex-direction', 'row-reverse !important' );
								$css->add_property( 'flex-wrap', 'wrap-reverse' );
							} else {
								$css->add_property( 'flex-wrap', 'wrap' );
								$css->add_property( 'flex-direction', 'row !important' );
							}
						} else if ( 'mobile' === $screen ) {

							if ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) && ! isset( $attr['reverseColMobile'] ) ) || true === $this->has_attr( $attr, 'reverseColMobile' ) || ( ! isset( $attr['reverseColTablet'] ) && ! isset( $attr['reverseColMobile'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
								$css->add_property( 'flex-direction', 'row-reverse !important' );
								$css->add_property( 'flex-wrap', 'wrap-reverse' );
							} else {
								$css->add_property( 'flex-wrap', 'wrap' );
								$css->add_property( 'flex-direction', 'row !important' );
							}
						} else {
							$css->add_property( 'flex-wrap', 'wrap' );
							$css->add_property( 'flex-direction', 'row !important' );
						}
						break;

					case 'column':
						$css->add_property( 'flex-wrap', 'nowrap' );
						if ( 'desktop' === $screen && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) {
							$css->add_property( 'flex-direction', 'row-reverse !important' );
							$css->add_property( 'flex-wrap', 'wrap-reverse' );
						} elseif ( 'tablet' === $screen && ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) ) ) || ( ! isset( $attr['reverseColDesktop'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
							if ( $this->has_attr( $attr, 'reverseColTablet' ) ) {
								$css->add_property( 'flex-direction', 'row-reverse !important' );
							} else {
								$css->add_property( 'flex-direction', 'row !important' );
							}
						} else if ( 'mobile' === $screen ) {
							if ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) && ! isset( $attr['reverseColMobile'] ) ) || true === $this->has_attr( $attr, 'reverseColMobile' ) || ( ! isset( $attr['reverseColTablet'] ) && ! isset( $attr['reverseColMobile'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
								$css->add_property( 'flex-direction', 'row-reverse !important' );
							} else {
								$css->add_property( 'flex-direction', 'row !important' );
							}
						} else {
							$css->add_property( 'flex-direction', 'row !important' );
						}
						break;

					case 'row':
						if ( 'desktop' === $screen && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) {
							$css->add_property( 'flex-direction', 'row-reverse !important' );
							$css->add_property( 'flex-wrap', 'wrap-reverse' );
						} elseif ( 'tablet' === $screen && ( true === $this->has_attr( $attr, 'colIntoRowTablet' ) || true === $this->has_attr( $attr, 'reverseColTablet' ) ) ) {
							if ( 'tablet' === $screen && ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) ) ) || ( ! isset( $attr['reverseColDesktop'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
								$css->add_property( 'flex-direction', 'column-reverse !important' );
							} else {
								$css->add_property( 'flex-direction', 'column !important' );
							}
						} else if ( 'mobile' === $screen ) {
							if ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) && ! isset( $attr['reverseColMobile'] ) ) || true === $this->has_attr( $attr, 'reverseColMobile' ) || ( ! isset( $attr['reverseColTablet'] ) && ! isset( $attr['reverseColMobile'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
								$css->add_property( 'flex-direction', 'column-reverse !important' );
							} else {
								$css->add_property( 'flex-direction', 'column !important' );
							}
						} else {
							$css->add_property( 'flex-direction', 'column !important' );
						}
						break;
				}
			} elseif ( 'mobile' === $screen ) {
				if ( ! isset( $attr['layout'][ $screen ] ) ) {
					if ( ( true === $this->has_attr( $attr, 'reverseColTablet' ) && ! isset( $attr['reverseColMobile'] ) ) || true === $this->has_attr( $attr, 'reverseColMobile' ) || ( ! isset( $attr['reverseColTablet'] ) && ! isset( $attr['reverseColMobile'] ) && true === $this->has_attr( $attr, 'reverseColDesktop' ) ) ) {
						$css->add_property( 'flex-direction', 'column-reverse !important' );
					} else {
						$css->add_property( 'flex-direction', 'column !important' );
					}
				}
			}

			$css->set_selector( $selector_wrapper . ':before' );
			$opacity = null == ! $this->has_attr( $attr, 'overlayValue', $screen ) ? $this->has_attr( $attr, 'overlayValue', $screen ) / 100 : '';
			$css->add_property( 'opacity', $opacity );
			$css->add_property( 'background', $this->has_attr( $attr, 'overlayBackground', $screen ) );

			$css->set_selector( $selector_wrapper . ':hover:before' );

			$css->add_property( 'opacity', $opacity );
			$css->add_property( 'background', $this->has_attr( $attr, 'overlayBackgroundHover', $screen ) );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}
		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper )->css_output();

	}

	public function set_inner_column_width( $css, $attr, $screen, $selector_wrapper ) {
		$device_type = 'desktop' !== $screen ? "_$screen" : '';
		$column_gap  = $this->has_attr( $attr, 'columnGapping', $screen );

		$columnGap = '';
		if ( ! empty( $column_gap ) && is_array( $column_gap ) ) {
			$unit      = ! empty( $column_gap['unit'] ) ? $column_gap['unit'] : 'px';
			$gapvalue  = isset( $column_gap['value'] ) && '' !== $column_gap['value'] ? $column_gap['value'] : '';
			$columnGap = '' !== $gapvalue ? ' - ' . $gapvalue . $unit : '';
		}

		$columnGap = ( '' === $columnGap ) ? ' - 20px' : $columnGap; //if no value is set, consider default value 20px
		$columnGap = 'grid' == $this->has_attr( $attr, 'layout', $screen ) ? $columnGap : '';

		if ( isset( $attr[ 'columnsWidth' . $device_type ] ) && is_array( $attr[ 'columnsWidth' . $device_type ] ) ) {
			foreach ( $attr[ 'columnsWidth' . $device_type ] as $key => $value ) {
				$col_child = $key + 1;
				$css->set_selector( $selector_wrapper . '>.bwf-col > .bwf-inner-col:nth-child(' . $col_child . ')' );
				if ( 100 === $value ) {
					$css->add_property( 'flex-basis', "100%" );
				} else {
					$css->add_property( 'flex-basis', "calc($value%$columnGap)" );
				}
				$css->add_property( 'flex-grow', 0 );
				$css->add_property( 'flex-shrink', 1 );

			}
		}
	}

	/**
	 * Render col Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_col_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = '.bwf-section-wrap .bwf-col .bwf-inner-col.bwf-inner-col-' . $unique_id;

		$screens = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
				$verticalAlignment = $this->has_attr( $attr, 'verticalAlignment' . $screen );
			} else {
				$verticalAlignment = $this->has_attr( $attr, 'verticalAlignment' );
			}
			$css->set_selector( $selector_wrapper );
			$css->add_property( 'z-index', $this->has_attr( $attr, 'zIndex', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'text-align', $this->has_attr( $attr, 'contentAlign', $screen ) );

			if ( $verticalAlignment ) {
				if ( 'top' === $verticalAlignment ) {
					$css->add_property( 'align-self', 'flex-start' );
				} elseif ( 'center' === $verticalAlignment ) {
					$css->add_property( 'align-self', 'center' );
				} elseif ( 'bottom' === $verticalAlignment ) {
					$css->add_property( 'align-self', 'flex-end' );
				}
				$css->add_property( 'width', '100%' );
			}

			$css->set_selector( $selector_wrapper . ':hover' );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}
		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper )->css_output();
	}

	/**
	 * Render button Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_adv_button_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = 'body .bwf-advance-btn.bwf-' . $unique_id;

		$screens = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}

			$css->set_selector( $selector_wrapper );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'gap', $this->has_attr( $attr, 'buttonGap', $screen ), true );
			$css->add_property( 'place-content', $this->mapFlexValues( $this->has_attr( $attr, 'alignment', $screen ) ) );

			$stack_vertical = $this->has_attr( $attr, 'stackVertically', $screen );

			switch ( $screen ) {
				case 'desktop':
					if ( $stack_vertical ) {
						$css->add_property( 'flex-direction', 'column' );
					}
					break;
				case 'tablet':
					if ( null !== $stack_vertical ) {
						if ( $stack_vertical ) {
							$css->add_property( 'flex-direction', 'column' );
						} else if ( false === $stack_vertical ) {
							$css->add_property( 'flex-direction', 'row' );
						}
					}
					break;
				case 'mobile':
					if ( null !== $stack_vertical ) {
						if ( $stack_vertical ) {
							$css->add_property( 'flex-direction', 'column' );
						} else if ( false === $stack_vertical ) {
							$css->add_property( 'flex-direction', 'row' );
						}
					}
					break;
			}

			$fillSpace = $this->has_attr( $attr, 'fillSpace', $screen );
			switch ( $screen ) {
				case 'desktop':
					if ( $fillSpace ) {
						$css->add_property( 'flex-wrap', 'nowrap' );
					}
					break;
				case 'tablet':
					if ( null !== $fillSpace ) {
						if ( $fillSpace ) {
							$css->add_property( 'flex-wrap', 'nowrap' );
						} else if ( false === $fillSpace ) {
							$css->add_property( 'flex-wrap', 'wrap' );
						}
					}
					break;
				case 'mobile':
					if ( null !== $fillSpace ) {
						if ( $fillSpace ) {
							$css->add_property( 'flex-wrap', 'nowrap' );
						} else if ( false === $fillSpace ) {
							$css->add_property( 'flex-wrap', 'wrap' );
						}
					}
					break;
			}

			// inner button selector
			$css->set_selector( $selector_wrapper . ' .bwf-btn' );
			switch ( $screen ) {
				case 'desktop':
					if ( $fillSpace ) {
						$css->add_property( 'width', '100%' );
					}
					break;
				case 'tablet':
					if ( null !== $fillSpace ) {
						if ( $fillSpace ) {
							$css->add_property( 'width', '100%' );
						} else if ( false === $fillSpace ) {
							$css->add_property( 'width', 'auto' );
						}
					}
					break;
				case 'mobile':
					if ( null !== $fillSpace ) {
						if ( $fillSpace ) {
							$css->add_property( 'width', '100%' );
						} else if ( false === $fillSpace ) {
							$css->add_property( 'width', 'auto' );
						}
					}
					break;
			}

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}
		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper )->css_output();
	}

	private function mapFlexValues( $value ) {
		return ( 'left' === $value ) ? 'flex-start' : ( 'right' === $value ? 'flex-end' : $value );
	}

	/**
	 * Render button Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_button_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$isOutline        = ( is_array( $attr ) && isset( $attr['type'] ) && 'outline' === $attr['type'] ) ? '.btn-outline' : '';
		$selector_wrapper = 'body .bwf-advance-btn ' . $isOutline . '.bwf-btn.bwf-' . $unique_id;

		$screens = array( 'desktop', 'tablet', 'mobile' );
		$button  = $this->has_attr( $attr, 'button' ) ?? [];

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}

			$css->set_selector( $selector_wrapper );
			$css->add_property( 'text-decoration', 'none' );
			$alignment = $this->has_attr( $attr, 'text', $screen ) ? $this->has_attr( $attr, 'text', $screen ) : array();
			$alignment = isset( $alignment['align'] ) ? $alignment['align'] : ( $screen === 'desktop' ? 'center' : '' );
			$css->add_property( 'text-align', $alignment );
			$css->add_property( 'justify-content', $alignment );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$i_space = '';
			if ( 'desktop' === $screen ) {
				$i_space = $this->has_attr( $button, 'iconSpace' ) ? $this->has_attr( $button, 'iconSpace' ) . 'px' : '';
			} else if ( 'tablet' === $screen ) {
				$i_space = $this->has_attr( $attr, 'iconSpaceTab' ) ? $this->has_attr( $attr, 'iconSpaceTab' ) . 'px' : '';
			} else if ( 'mobile' === $screen ) {
				$i_space = $this->has_attr( $attr, 'iconSpaceMobile' ) ? $this->has_attr( $attr, 'iconSpaceMobile' ) . 'px' : '';
			}
			if ( ! empty( $i_space ) ) {
				$css->add_property( 'column-gap', $i_space );
			}
			if ( $this->has_attr( $attr, 'marginAuto', $screen ) && 'full' !== $this->has_attr( $attr, 'align', $screen ) ) {
				$css->add_property( 'margin-left', 'auto' );
				$css->add_property( 'margin-right', 'auto' );
			}

			$css->set_selector( $selector_wrapper . ' .bwf-btn-inner-text' );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			if ( 'desktop' === $screen ) {
				$i_size = $this->has_attr( $button, 'iconSize' ) ? $this->has_attr( $button, 'iconSize' ) . 'px' : '';
			} else {
				if ( 'tablet' === $screen ) {
					$i_size = $this->has_attr( $attr, 'iconSizeTab' ) ? $this->has_attr( $attr, 'iconSizeTab' ) . 'px' : '';
				} else {
					$i_size = $this->has_attr( $attr, 'iconSizeMobile' ) ? $this->has_attr( $attr, 'iconSizeMobile' ) . 'px' : '';
				}
			}
			$css->set_selector( $selector_wrapper . ' .bwf-icon-inner-svg svg,' . $selector_wrapper . ' .bwf-icon-inner-svg' );
			$css->add_property( 'width', $i_size );
			$css->add_property( 'height', $i_size );

			$css->set_selector( $selector_wrapper . ':hover' );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );

			if ( $this->has_attr( $attr, 'secondaryContentEnable' ) ) {
				$css->set_selector( $selector_wrapper . ' .bwf-btn-sub-text' );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'secondaryLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'secondaryLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'secondaryFont', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'secondaryText', $screen ) );
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColor', $screen ) );
				$css->add_property( 'margin-top', $this->has_attr( $attr, 'contentSpace', $screen ), true );

				$css->set_selector( $selector_wrapper . ':hover .bwf-btn-sub-text' );
				$css->add_property( 'color', $this->has_attr( $attr, 'secondaryColorHover', $screen ) );
			}

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper )->css_output();
	}

	/**
	 * Render heading Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_heading_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$container_selector  = '.bwf-adv-heading.bwf-adv-head-' . $unique_id;
		$selector_wrapper    = '.bwf-adv-heading.bwf-adv-head-' . $unique_id . ', .bwf-adv-heading.bwf-adv-head-' . $unique_id . ' a';
		$selector_hover      = '.bwf-adv-heading.bwf-adv-head-' . $unique_id . ':hover, .bwf-adv-heading.bwf-adv-head-' . $unique_id . ' a:hover';
		$selector_link       = '.bwf-adv-heading.bwf-adv-head-' . $unique_id . ' a, .bwf-adv-heading.bwf-adv-head-' . $unique_id . '>a';
		$selector_link_hover = '.bwf-adv-heading.bwf-adv-head-' . $unique_id . ' a:hover, .bwf-adv-heading.bwf-adv-head-' . $unique_id . '>a:hover';

		$screens = array( 'desktop', 'tablet', 'mobile' );
		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}

			$css->set_selector( $container_selector );
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
			if ( 'left' === $this->has_attr( $attr, 'alignment', $screen ) ) {
				$css->add_property( 'margin-right', 'auto' );
			}
			if ( 'right' === $this->has_attr( $attr, 'alignment', $screen ) ) {
				$css->add_property( 'margin-left', 'auto' );
			}
			if ( 'center' === $this->has_attr( $attr, 'alignment', $screen ) ) {
				$css->add_property( 'margin-left', 'auto' );
				$css->add_property( 'margin-right', 'auto' );
			}

			$css->set_selector( $selector_wrapper );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			$css->set_selector( $selector_hover );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );

			$css->set_selector( $selector_link );
			$css->add_property( 'color', $this->has_attr( $attr, 'linkColor', $screen ) );

			$css->set_selector( $selector_link_hover );
			$css->add_property( 'color', $this->has_attr( $attr, 'linkColorHover', $screen ) );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, '.bwf-adv-heading.bwf-adv-head-' . $unique_id )->css_output();
	}

	/**
	 * Render list Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_list_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = '.bwf-icon-list-wrap.bwf-list-' . $unique_id . ' .bwf-icon-list';
		$screens          = array( 'desktop', 'tablet', 'mobile' );

		$columns = $this->has_attr( $attr, 'columns' );
		if ( $columns > 1 ) {
			$css->set_selector( $selector_wrapper );
			$grid = '';
			for ( $i = 0; $i < (int) $columns; $i ++ ) {
				$grid .= '1fr ';
			}
			$css->add_property( 'grid-template-columns', $grid );
		}

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}
			$css->set_selector( $selector_wrapper . ' .bwf-icon-inner-svg svg' );
			$size = $this->has_attr( $attr, 'iconSize', $screen ) ? $this->has_attr( $attr, 'iconSize', $screen ) . 'px' : '';
			$css->add_property( 'width', $size );
			$css->add_property( 'height', $size );
			$css->add_property( 'color', $this->has_attr( $attr, 'iconColor', $screen ) );
			$i_rotate = $this->has_attr( $attr, 'iconRotate', $screen );
			if ( $i_rotate && isset( $i_rotate['value'] ) ) {
				$css->add_property( 'transform', "rotate({$i_rotate['value']}deg)" );
			}

			$css->set_selector( $selector_wrapper );
			$g_gap = $this->has_attr( $attr, 'listSpace', $screen ) ? $this->has_attr( $attr, 'listSpace', $screen ) . $this->has_attr( $attr, 'listSpaceUnit', $screen, 'em' ) : '';
			$css->add_property( 'grid-gap', $g_gap );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-icon-inner-svg' );
			$m_right = $this->has_attr( $attr, 'iconSpace', $screen ) ? $this->has_attr( $attr, 'iconSpace', $screen ) . 'px' : '';
			$css->add_property( 'margin-right', $m_right );

			$css->set_selector( $selector_wrapper . ' .bwf-icon-list-item-wrap' );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'align-items', $this->has_attr( $attr, 'iconAlignment', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-icon-list-item-wrap .bwf-icon-list-text' );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper )->css_output();
	}

	/**
	 * Render divider Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_divider_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = '.bwf-space-divider-wrapper.bwf-' . $unique_id;
		$screens          = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}
			$css->set_selector( $selector_wrapper );
			$css->add_property( 'grid-template-columns', $this->has_attr( $attr, 'dividerWidth', $screen ), true );
			$css->add_property( 'height', $this->has_attr( $attr, 'dividerSpace', $screen ), true );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf-space-divider' );
			$css->add_property( 'border-top-color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'border-top-style', $this->has_attr( $attr, 'dividerStyle' ) );
			$css->add_property( 'border-width', $this->has_attr( $attr, 'dividerHeight', $screen ), true );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper . ' .bwf-space-divider' )->css_output();
	}

	/**
	 * Render icon Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_icon_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper = 'body .bwf-icon-wrap.bwf-icon-' . $unique_id;
		$screens          = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}
			$css->set_selector( $selector_wrapper );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );
			$css->add_property( 'gap', $this->has_attr( $attr, 'iconSpace', $screen ), true );
			$css->add_property( 'padding', $this->has_attr( $attr, 'margin', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf--icon svg' );
			$css->add_property( 'width', $this->has_attr( $attr, 'iconSize', $screen ), true );
			$css->add_property( 'height', $this->has_attr( $attr, 'iconSize', $screen ), true );
			$i_rotate = $this->has_attr( $attr, 'iconRotate', $screen );
			if ( $i_rotate && isset( $i_rotate['value'] ) ) {
				$css->add_property( 'transform', "rotate({$i_rotate['value']}deg)" );
			}

			$css->set_selector( $selector_wrapper . ' .bwf--icon' );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );

			$css->set_selector( $selector_wrapper . ' .bwf--icon:hover' );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper . ' .bwf--icon' )->css_output();
	}

	/**
	 * Render Progress Bar Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_progress_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper           = '.bwf-progress-bar-wrapper.bwf-' . $unique_id;
		$progress_bar_wrapper       = "{$selector_wrapper } .bwf-progress-inner-wrap .bwf-progress-bar";
		$progress_bar_hover_wrapper = "{$selector_wrapper } .bwf-progress-inner-wrap:hover .bwf-progress-bar";
		$screens                    = array( 'desktop', 'tablet', 'mobile' );

		$progress_width = $this->has_attr( $attr, 'percentage' );
		if ( '' !== $progress_width && null !== $progress_width ) {
			$css->set_selector( "{$progress_bar_wrapper} .bwf-progress" );
			$css->add_property( 'width', $progress_width . '%' );
		}

		$enable = $this->has_attr( $attr, 'enableTitle' );
		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}
			$css->set_selector( $selector_wrapper );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );

			if ( $enable ) {
				$css->set_selector( "{$selector_wrapper} .bwf-progress-inner-wrap .bwf-progress-title" );
				$css->add_property( 'color', $this->has_attr( $attr, 'titleColor', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'titleLineHeight', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'titleLetterSpacing', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'titleFont', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'titleText', $screen ) );
				$css->add_property( 'margin-bottom', $this->has_attr( $attr, 'contentSpace', $screen ), 'width' );

				$css->set_selector( "{$selector_wrapper} .bwf-progress-inner-wrap:hover .bwf-progress-title" );
				$css->add_property( 'color', $this->has_attr( $attr, 'titleColorHover', $screen ) );
				$css->add_property( 'line-height', $this->has_attr( $attr, 'titleLineHeightHover', $screen ), true );
				$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'titleLetterSpacingHover', $screen ), true );
				$css->add_property( 'font', $this->has_attr( $attr, 'titleFontHover', $screen ) );
				$css->add_property( 'text', $this->has_attr( $attr, 'titleTextHover', $screen ) );
			}

			$css->set_selector( "{$selector_wrapper} .bwf-progress-inner-wrap" );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );

			$css->set_selector( $progress_bar_wrapper );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundUncompleted', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );

			$css->set_selector( $progress_bar_hover_wrapper );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundUncompletedHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

			$css->set_selector( "{$progress_bar_wrapper} .bwf-progress, {$progress_bar_wrapper}" );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );

			$css->set_selector( "{$progress_bar_hover_wrapper} .bwf-progress, {$progress_bar_hover_wrapper}" );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );

			$css->set_selector( "{$progress_bar_wrapper} .bwf-progress" );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );

			$css->set_selector( "{$progress_bar_hover_wrapper} .bwf-progress" );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );

			$css->set_selector( "{$progress_bar_wrapper} .bwf-progress .bwf-progress-inner-text" );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			$css->set_selector( "{$progress_bar_hover_wrapper} .bwf-progress .bwf-progress-inner-text" );
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

		return $css->custom_css( $custom_css, $selector_wrapper . ' .bwf-space-divider' )->css_output();
	}

	/**
	 * Render Countdown Block CSS
	 *
	 * @param $attr
	 * @param $unique_id
	 *
	 * @return string
	 */
	public function render_count_down_css_head( $attr, $unique_id ) {
		$css                   = new SLINGBLOCKS_CSS();
		$media_query           = array();
		$media_query['mobile'] = apply_filters( 'bwf_blocks_mobile_media_query', '(max-width: 767px)' );
		$media_query['tablet'] = apply_filters( 'bwf_blocks_tablet_media_query', '(max-width: 1024px)' );

		$selector_wrapper      = ".bwf-countdown-outer.bwf-{$unique_id} .bwf-countdown-wrapper";
		$countdownCard         = "{$selector_wrapper} .bwf-countdown-inner-wrap";
		$countdownCardHover    = "{$selector_wrapper} .bwf-countdown-inner-wrap:hover";
		$separator_class       = "{$selector_wrapper} .bwf-countdown-inner-wrap.bwf-separator";
		$separator_class_hover = "{$selector_wrapper} .bwf-countdown-inner-wrap.bwf-separator:hover";
		$screens               = array( 'desktop', 'tablet', 'mobile' );

		foreach ( $screens as $screen ) {
			if ( 'desktop' !== $screen ) {
				$css->start_media_query( $media_query[ $screen ] );
			}

			$css->set_selector( "{$selector_wrapper} .bwf-reverse-text" );
			$css->add_property( 'color', $this->has_attr( $attr, 'reverseTextColor', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'reverseTextLineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'reverseTextLetterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'reverseTextFont', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'reverseTextText', $screen ) );

			$css->set_selector( "{$selector_wrapper} .bwf-before-text:hover" );
			$css->add_property( 'color', $this->has_attr( $attr, 'reverseTextColorHover', $screen ) );

			$css->set_selector( "{$selector_wrapper} .bwf-before-text" );
			$css->add_property( 'color', $this->has_attr( $attr, 'preTextColor', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'preTextLineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'preTextLetterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'preTextFont', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'preTextText', $screen ) );

			$css->set_selector( "{$selector_wrapper} .bwf-before-text:hover" );
			$css->add_property( 'color', $this->has_attr( $attr, 'preTextColorHover', $screen ) );

			$css->set_selector( "{$selector_wrapper} .bwf-after-text" );
			$css->add_property( 'color', $this->has_attr( $attr, 'postTextColor', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'postTextLineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'postTextLetterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'postTextFont', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'postTextText', $screen ) );

			$css->set_selector( "{$selector_wrapper} .bwf-after-text:hover" );
			$css->add_property( 'color', $this->has_attr( $attr, 'postTextColorHover', $screen ) );

			$css->set_selector( ".bwf-countdown-outer.bwf-{$unique_id}" );
			$css->add_property( 'justify-content', $this->has_attr( $attr, 'alignment', $screen ) );

			$gap = $this->has_attr( $attr, 'spaceBtwBox', $screen );
			$gap = isset( $gap['width'] ) ? $gap['width'] . ( $gap['unit'] ? $gap['unit'] : 'px' ) : null;

			$countdownStyle = $this->has_attr( $attr, 'countdownStyle' );
			if ( $countdownStyle && 'inline' === $countdownStyle && isset( $gap['width'] ) && $gap['width'] > 0 ) {
				$css->set_selector( "{$selector_wrapper} .bwf-countdown-inner-wrap>*" );
				$css->add_property( 'margin-left', $gap );
			} else {
				$css->set_selector( "{$selector_wrapper} .bwf-countdown-inner-wrap" );
				$css->add_property( 'gap', $gap );
			}

			$css->set_selector( "{$selector_wrapper}" );
			$css->add_property( 'padding', $this->has_attr( $attr, 'padding', $screen ) );
			$css->add_property( 'margin', $this->has_attr( $attr, 'margin', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'containerBackground', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'containerBorder', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'containerBoxShadow', $screen ) );

			$css->set_selector( "{$selector_wrapper}:hover" );
			$css->add_property( 'background', $this->has_attr( $attr, 'containerBackgroundHover', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'containerBorderHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'containerBoxShadowHover', $screen ) );

			$contentSpace = $this->has_attr( $attr, 'contentSpace', $screen );
			$contentSpace = isset( $contentSpace['width'] ) ? $contentSpace['width'] . ( $contentSpace['unit'] ? $contentSpace['unit'] : 'px' ) : null;

			$css->set_selector( "{$countdownCard} .bwf-card-data" );
			$css->add_property( 'gap', $contentSpace );

			$css->set_selector( "{$countdownCard} .bwf-card-data .bwf-timer-label" );
			$css->add_property( 'color', $this->has_attr( $attr, 'color', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'lineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'letterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'font', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'text', $screen ) );

			$css->set_selector( "{$countdownCardHover} .bwf-card-data .bwf-timer-label" );
			$css->add_property( 'color', $this->has_attr( $attr, 'colorHover', $screen ) );

			$css->set_selector( "{$countdownCard} .bwf-card-data .bwf-timer-digit" );
			$css->add_property( 'color', $this->has_attr( $attr, 'digitColor', $screen ) );
			$css->add_property( 'line-height', $this->has_attr( $attr, 'digitLineHeight', $screen ), true );
			$css->add_property( 'letter-spacing', $this->has_attr( $attr, 'digitLetterSpacing', $screen ), true );
			$css->add_property( 'font', $this->has_attr( $attr, 'digitFont', $screen ) );
			$css->add_property( 'text', $this->has_attr( $attr, 'digitText', $screen ) );

			$css->set_selector( "{$countdownCardHover} .bwf-card-data .bwf-timer-digit" );
			$css->add_property( 'color', $this->has_attr( $attr, 'digitColorHover', $screen ) );

			$css->set_selector( $countdownCard );
			$css->add_property( 'padding', $this->has_attr( $attr, 'paddingCard', $screen ) );
			$css->add_property( 'border', $this->has_attr( $attr, 'border', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'background', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadow', $screen ) );
			$css->add_property( 'width', $this->has_attr( $attr, 'width', $screen ), 'width' );
			$css->add_property( 'min-width', $this->has_attr( $attr, 'minWidth', $screen ), 'width' );
			$css->add_property( 'max-width', $this->has_attr( $attr, 'maxWidth', $screen ), 'width' );
			$css->add_property( 'height', $this->has_attr( $attr, 'height', $screen ), 'height' );
			$css->add_property( 'min-height', $this->has_attr( $attr, 'minHeight', $screen ), 'height' );
			$css->add_property( 'max-height', $this->has_attr( $attr, 'maxHeight', $screen ), 'height' );

			$css->set_selector( $countdownCardHover );
			$css->add_property( 'border', $this->has_attr( $attr, 'borderHover', $screen ) );
			$css->add_property( 'background', $this->has_attr( $attr, 'backgroundHover', $screen ) );
			$css->add_property( 'box-shadow', $this->has_attr( $attr, 'boxShadowHover', $screen ) );

			if ( ( isset( $attr['enableSeparator'] ) && $attr['enableSeparator'] ) || ! isset( $attr['enableSeparator'] ) ) {
				$positionTop  = $this->has_attr( $attr, 'positionTop', $screen );
				$positionTop  = isset( $positionTop['width'] ) ? $positionTop['width'] . ( $positionTop['unit'] ? $positionTop['unit'] : 'px' ) : null;
				$positionLeft = $this->has_attr( $attr, 'positionLeft', $screen );
				$positionLeft = isset( $positionLeft['width'] ) ? $positionLeft['width'] . ( $positionLeft['unit'] ? $positionLeft['unit'] : 'px' ) : null;

				$separator_font = $this->has_attr( $attr, 'separartorFont' );

				$css->set_selector( "$separator_class .bwf-digit-card::before" );
				$css->add_property( 'color', $this->has_attr( $attr, 'separartorColor', $screen ) );
				$css->add_property( 'top', $positionTop );
				$css->add_property( 'left', $positionLeft );
				if ( isset( $separator_font[ $screen ] ) && isset( $separator_font[ $screen ]['size'] ) ) {
					$unit = isset( $separator_font[ $screen ]['unit'] ) ? $separator_font[ $screen ]['unit'] : 'px';
					$css->add_property( 'font-size', $separator_font[ $screen ]['size'] . $unit );
				}

				$css->set_selector( "$separator_class_hover .bwf-digit-card::before" );
				$css->add_property( 'color', $this->has_attr( $attr, 'separartorColorHover', $screen ) );
			}

			if ( 'desktop' !== $screen ) {
				$css->stop_media_query();
			}
		}

		$custom_css = $this->has_attr( $attr, 'bwfBlockCSS' );

		return $css->custom_css( $custom_css, $selector_wrapper . ' .bwf-countdown-block' )->css_output();
	}
}

SLINGBLOCKS_Frontend_CSS::get_instance();
