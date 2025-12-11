<?php
/**
 * This file handles the dynamic parts of our blocks.
 *
 * @package BWFBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Render the dynamic aspects of our blocks.
 *
 * @since 1.2.0
 */
class SLINGBLOCKS_Render_Block {
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

	public function bwf_kses_post( $content ) {
		global $allowedposttags;

		return wp_kses( $content, $allowedposttags );
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
				'name'     => 'sling-block/accordion',
				'callback' => 'do_accordion_block',
			],
			[
				'name'     => 'sling-block/pane',
				'callback' => 'do_pane_block',
			],
			[
				'name'     => 'sling-block/columns',
				'callback' => 'do_columns_block',
			],
			[
				'name'     => 'sling-block/col',
				'callback' => 'do_inner_column_block',
			],
			[
				'name'     => 'sling-block/advance-button',
				'callback' => 'do_adv_button_block',
			],
			[
				'name'     => 'sling-block/button',
				'callback' => 'do_button_block',
			],
			[
				'name'     => 'sling-block/advance-heading',
				'callback' => 'do_heading_block',
			],
			[
				'name'     => 'sling-block/icon-list',
				'callback' => 'do_icon_list_block',
			],
			[
				'name'     => 'sling-block/space-divider',
				'callback' => 'do_divider_block',
			],
			[
				'name'     => 'sling-block/icon',
				'callback' => 'do_icon_block',
			],
			[
				'name'     => 'sling-block/progress-bar',
				'callback' => 'do_progress_block',
			],
			[
				'name'     => 'sling-block/countdown',
				'callback' => 'do_count_down_block',
			],
		];

		foreach ( $bwfblocks as $block ) {
			register_block_type( $block['name'], array( 'render_callback' => array( $this, $block['callback'] ) ) );
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
	 * Output the dynamic aspects of our Accordion block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_accordion_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_accordion_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output   = '';
		$defaults = array(
			'paneClose'   => false,
			'paneOpenOne' => true,
			'openPane'    => 1,
			'anchor'      => ''
		);

		$settings = wp_parse_args( $attributes, $defaults );


		if ( ! isset( $settings['accordionLayout'] ) ) {
			return $output;
		}

		$alignment = $settings['align'] ?? 'default';

		$classNames = array(
			'bwf-accordion',
			'bwf-' . $settings['accordionLayout'],
			'bwf-accordion-' . $settings['uniqueID'],
			'bwf-width-' . $alignment,
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$start_open = isset( $settings['openPane'] ) ? $settings['openPane'] : 1;
		if ( isset( $settings['paneClose'] ) && true === $settings['paneClose'] ) {
			$start_open = 'none';
		}

		$output = sprintf( '<div %s>', slingblocks_attr( 'accordion', array(
			'id'                       => $settings['anchor'],
			'class'                    => implode( ' ', $classNames ),
			'data-allow-multiple-open' => isset( $settings['paneOpenOne'] ) && true === $settings['paneOpenOne'] ? 'false' : 'true',
			'data-open'                => $start_open,
		), $settings ) );

		$output .= $content;

		$output .= '</div>';


		return $output;
	}

	/**
	 * Output the dynamic aspects of our Pane block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_pane_block( $attributes, $content ) {

		$output = '';

		$defaults = array(
			'paneClose'   => true,
			'paneOpenOne' => false,
			'openPane'    => 1,
			'anchor'      => ''
		);

		$settings = wp_parse_args( $attributes, $defaults );


		$classNames = array(
			'bwf-accordion-wrapper',
			'bwf-pane-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'pane', array(
			'id'    => $settings['anchor'],
			'class' => implode( ' ', $classNames ),
		), $settings ) );
		$output .= '<div class="bwf-accordion-head">';

		$output .= '<h2 class="bwf-accordion-head-tag">';
		$output .= isset( $settings['content'] ) ? $this->bwf_kses_post( $settings['content'] ) : ''; //pane heading title
		$output .= '</h2>';
		$output .= $this->get_pane_toggle_icon( $settings );
		$output .= '</div>';

		$output .= '<div class="bwf-accordion-body">';
		$output .= $content; //InnerBlocks.Content
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}

	public function get_pane_toggle_icon( $attributes ) {
		$icons = '';
		if ( isset( $attributes['triggerIcon'] ) && ! empty( $attributes['triggerIcon'] ) ) {
			switch ( $attributes['triggerIcon'] ) {
				case 'plus-minus':
					$icons = '<div class="bwf-icon-open">' . slingblocks_svg_icons( 'minus' ) . '</div><div class="bwf-icon-close">' . slingblocks_svg_icons( 'plus' ) . '</div>';
					break;
				case 'up-down':
					$icons = '<div class="bwf-icon-open">' . slingblocks_svg_icons( 'collapse-arrow' ) . '</div><div class="bwf-icon-close">' . slingblocks_svg_icons( 'expand-arrow' ) . '</div>';
					break;
				case 'up-down-arrow':
					$icons = '<div class="bwf-icon-open">' . slingblocks_svg_icons( 'up-arrow' ) . '</div><div class="bwf-icon-close">' . slingblocks_svg_icons( 'down-arrow' ) . '</div>';
					break;
				case 'plus-minus-circle':
					$icons = '<div class="bwf-icon-open">' . slingblocks_svg_icons( 'close-circle' ) . '</div><div class="bwf-icon-close">' . slingblocks_svg_icons( 'plus-circle' ) . '</div>';
					break;
			}
		} else {
			$default_open  = ! isset( $attributes['accordionLayout'] ) || 'accordion-3' !== $attributes['accordionLayout'] ? 'minus' : 'collapse-arrow';
			$default_close = ! isset( $attributes['accordionLayout'] ) || 'accordion-3' !== $attributes['accordionLayout'] ? 'plus' : 'expand-arrow';
			$icons         = '<div class="bwf-icon-open">' . slingblocks_svg_icons( $default_open ) . '</div><div class="bwf-icon-close">' . slingblocks_svg_icons( $default_close ) . '</div>';
		}

		return $icons;
	}

	/**
	 * Output the dynamic aspects of our Columns block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_columns_block( $attributes, $content, $col ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_columns_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}


		$output = '';

		$defaults = array(
			'columns' => 1,
			'anchor'  => ''
		);

		$settings = wp_parse_args( $attributes, $defaults );

		$classNames = array(
			'bwf-section-wrap',
			'bwf-section-' . $settings['uniqueID'],
		);

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		if ( isset( $settings['align'] ) && ! empty( $settings['align'] ) ) {
			$output .= '<div class="bwf-align-wrap-' . $settings['align'] . '">';
		}


		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		if ( ! empty( $settings['contentWidth'] ) && 'full' === $settings['contentWidth'] ) {
			$classNames[] = 'bwf-width-full';
		}

		if ( $this->hasColumnBG( $settings ) ) {
			$classNames[] = 'bwf-has-bg';
		}

		$allowed_tags = [ 'div', 'header', 'section', 'article', 'main', 'aside', 'footer' ];

		$tagName = ! empty( $settings['htmlTag'] ) && in_array( $settings['htmlTag'], $allowed_tags ) ? $settings['htmlTag'] : 'div';

		if ( ! empty( $settings['link'] ) ) {
			$classNames[] = 'bwf-cursor';
		}
		$output .= sprintf( '<%1$s %2$s>', $tagName, slingblocks_attr( 'columns', array(
			'id'         => $settings['anchor'],
			'class'      => implode( ' ', $classNames ),
			'bwf-href'   => isset( $settings['link'] ) ? $settings['link'] : '',
			'bwf-newtab' => isset( $settings['linkNewTab'] ) && $settings['linkNewTab'] ? "_blank" : '',
		), $settings ) );

		// Column background video
		if ( isset( $settings['backgroundVideo'] ) && ! empty( $settings['backgroundVideo'] ) && isset( $settings['backgroundVideo']['desktop'] ) ) {
			$column_bgvideo = $settings['backgroundVideo']['desktop'];

			if ( isset( $column_bgvideo['local'] ) && ! empty( $column_bgvideo['local'] ) ) {
				$output .= sprintf( '<video autoplay %2$s><source src="%1$s" type="video/mp4"/></video>', esc_url( $column_bgvideo['local'] ), slingblocks_attr( 'columns-video', array(
					'muted'   => isset( $column_bgvideo['mute'] ) ? $column_bgvideo['mute'] : null,
					'loop'    => isset( $column_bgvideo['loop'] ) ? $column_bgvideo['loop'] : null,
					'control' => isset( $column_bgvideo['control'] ) ? $column_bgvideo['control'] : null,
				), $settings ) );
			}
		}
		$column = $settings['columns'] ?? 1;

		$output .= '<div class="bwf-col">';
		$output .= $content; //InnerBlocks.Content
		$output .= '</div>';


		$output .= sprintf( '</%s>', $tagName );
		if ( isset( $settings['align'] ) && ! empty( $settings['align'] ) ) {
			$output .= '</div>';
		}

		return $output;
	}

	public function hasColumnBG( $attrs ) {
		if ( ! empty( $attrs['background'] ) && ! empty( $attrs['background']['desktop'] ) && ( ! empty( $attrs['background']['desktop']['image'] ) || ! empty( $attrs['background']['desktop']['color'] ) || ! empty( $attrs['background']['desktop']['gradient'] ) ) ) {
			return true;
		}
		if ( ! empty( $attrs['overlayBackground'] ) && ! empty( $attrs['overlayBackground']['desktop'] ) && ( ! empty( $attrs['overlayBackground']['desktop']['image'] ) || ! empty( $attrs['overlayBackground']['desktop']['color'] ) || ! empty( $attrs['overlayBackground']['desktop']['gradient'] ) ) ) {
			return true;
		}
		if ( ! empty( $attrs['backgroundVideo'] ) && ! empty( $attrs['backgroundVideo']['desktop'] ) && ! empty( $attrs['backgroundVideo']['desktop']['local'] ) ) {
			return true;
		}

		return false;
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


	/**
	 * Output the dynamic aspects of our Inner Columns block(Column block child).
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_inner_column_block( $attributes, $content, $col ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_col_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output = '';

		if ( empty( $attributes['uniqueID'] ) ) {
			return $output;
		}

		$defaults = array( 'anchor' => '' );
		$settings = wp_parse_args( $attributes, $defaults );

		$classNames = array(
			'bwf-inner-col',
			'bwf-inner-col-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		if ( ! empty( $settings['link'] ) ) {
			$classNames[] = 'bwf-cursor';
		}

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'inner-column', array(
			'id'         => $settings['anchor'],
			'class'      => implode( ' ', $classNames ),
			'bwf-href'   => isset( $settings['link'] ) ? $settings['link'] : '',
			'bwf-newtab' => isset( $settings['linkNewTab'] ) && $settings['linkNewTab'] ? "_blank" : '',
		), $settings ) );


		$output .= $content; //InnerBlocks.Content
		$output .= '</div>';

		return $output;
	}

	/**
	 * Output the dynamic aspects of our Space Divider block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_divider_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_divider_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output = '';

		$defaults = array(
			'alignment' => [ 'desktop' => 'center' ],
			'width'     => [ 'desktop' => 100 ],
			'anchor'    => ''
		);
		$settings = wp_parse_args( $attributes, $defaults );

		$classNames = array(
			'bwf-space-divider-wrapper',
			'bwf-' . $settings['uniqueID'],
		);

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'space-divider', array(
			'id'    => $settings['anchor'],
			'class' => implode( ' ', $classNames ),
		), $settings ) );


		$output .= '<div class="bwf-space-divider"></div>';
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
	public function do_adv_button_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_adv_button_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output   = '';
		$defaults = array();
		$settings = wp_parse_args( $attributes, $defaults );

		$classNames = array(
			'bwf-advance-btn',
			'bwf-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'adv-button', array(
			'id'    => ! empty( $settings['anchor'] ) ? $settings['anchor'] : '',
			'class' => implode( ' ', $classNames ),
		), $settings ) );

		$output .= $content; //inner block
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
	public function do_button_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_button_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output = '';

		$defaults = array(
			'type'    => 'solid',
			'content' => __( 'Button', 'slingblocks' ),
			'anchor'  => ''
		);

		$settings = wp_parse_args( $attributes, $defaults );

		$type       = $settings['type'] ?? 'solid';
		$classNames = array( 'bwf-btn', 'btn-' . $type, 'bwf-' . $settings['uniqueID'] );

		if ( ! empty( $settings['secondaryContentEnable'] ) ) {
			$classNames[] = 'has-secondary-text';
		}
		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}
		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );


		$button_rel    = '';
		$button_target = '';
		$button        = isset( $settings['button'] ) ? $settings['button'] : [];
		if ( isset( $button['icon'] ) && ! empty( $button['icon'] ) ) {
			$classNames[] = 'has-icon';
		}
		if ( isset( $button['newTab'] ) && ! empty( $button['newTab'] ) ) {
			$button_target = '_blank';
			$button_rel    = 'noopener noreferrer';
		}

		if ( isset( $button['noFollow'] ) && ! empty( $button['noFollow'] ) ) {
			$button_rel .= ' nofollow';
		}
		$output .= sprintf( '<a %1$s>', slingblocks_attr( 'button-anchor', array(
			'id'     => $settings['anchor'],
			'class'  => implode( ' ', $classNames ),
			'href'   => isset( $settings['link'] ) ? esc_url( $settings['link'] ) : '',
			'target' => $button_target,
			'rel'    => $button_rel,
		), $settings ) );

		//Button Icon Left Side
		if ( isset( $button['icon'] ) && ! empty( $button['icon'] ) && 'left' === $button['iconPos'] ) {
			$output .= '<span class="bwf-icon-inner-svg bwf-left-icon">' . $button['icon'] . '</span>';
		}

		//Button content
		$output .= isset( $settings['content'] ) ? '<span class="bwf-btn-inner-text">' . $this->bwf_kses_post( $settings['content'] ) . '</span>' : '';

		//Button Icon Right Side
		if ( isset( $button['icon'] ) && ! empty( $button['icon'] ) && 'right' === $button['iconPos'] ) {
			$output .= '<span class="bwf-icon-inner-svg bwf-right-icon">' . $button['icon'] . '</span>';
		}

		// Button Secondary Text (Sub heading)
		if ( isset( $settings['secondaryContentEnable'] ) && ! empty( $settings['secondaryContentEnable'] ) ) {
			$buttonSubText = isset( $settings['secondaryContent'] ) ? $settings['secondaryContent'] : '';
			$output        .= '<span class="bwf-btn-sub-text">' . $buttonSubText . '</span>';
		}

		$output .= '</a>';

		return $output;
	}

	/**
	 * Output the dynamic aspects of our Advance Heading block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_heading_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_heading_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output    = '';
		$defaults  = array( 'anchor' => '' );
		$settings  = wp_parse_args( $attributes, $defaults );
		$alignment = $settings['align'] ?? 'default';

		$classNames = array(
			'bwf-adv-heading',
			'bwf-adv-head-' . $settings['uniqueID'],
			'bwf-width-' . $alignment,
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$allowed_tags = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ];

		$tagName = ! empty( $settings['htmlTag'] ) && in_array( $settings['htmlTag'], $allowed_tags ) ? $settings['htmlTag'] : 'div';

		$output .= sprintf( '<%1$s %2$s>', $tagName, slingblocks_attr( 'heading', array(
			'id'    => $settings['anchor'],
			'class' => implode( ' ', $classNames ),
		), $settings ) );
		$output .= isset( $settings['content'] ) ? $this->bwf_kses_post( $settings['content'] ) : ''; //Heading Content

		$output .= sprintf( '</%s>', $tagName );

		return $output;
	}

	/**
	 * Output the dynamic aspects of our Icon List block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_icon_list_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_list_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output     = '';
		$defaults   = array(
			'listCount' => 1,
			'anchor'    => ''
		);
		$settings   = wp_parse_args( $attributes, $defaults );
		$classNames = array(
			'bwf-icon-list-wrap',
			'bwf-list-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$output  .= sprintf( '<div %1$s>', slingblocks_attr( 'icon-list', array(
			'class' => implode( ' ', $classNames ),
		), $settings ) );
		$blockID = $settings['anchor'] ? 'id="' . esc_attr( $settings['anchor']) . '"' : '';
		$output  .= '<ul class="bwf-icon-list" ' . $blockID . '>';

		$listCount = isset( $settings['listCount'] ) ? $settings['listCount'] : 1;

		for ( $i = 0; $i < $listCount; $i ++ ) {

			$output .= '<li class="bwf-icon-list-item-wrap bwf-icon-list-item-' . $i . '">';

			if ( isset( $settings['items'] ) && isset( $settings['items'][ $i ] ) ) {
				$defaultIcon = '<svg data-prefix="fas" data-icon="check" class="svg-inline--fa fa-check fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg>';

				$icon = $settings['items'][ $i ]['icon'] ? $settings['items'][ $i ]['icon'] : ( ! empty( $settings['defaultIcon'] ) ? $settings['defaultIcon'] : $defaultIcon );
				$text = isset( $settings['items'][ $i ]['text'] ) ? $this->bwf_kses_post( $settings['items'][ $i ]['text'] ) : '';

				$output .= '<span class="bwf-icon-inner-svg">' . $icon;
				$output .= '</span>';
				$output .= '<span class="bwf-icon-list-text">' . $text;
				$output .= '</span>';
			}

			$output .= '</li>';
		}

		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}

	public function do_icon_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_icon_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output = '';

		$settings = wp_parse_args( $attributes, [] );

		$classNames = array(
			'bwf-icon-wrap',
			'bwf-icon-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'icon', array(
			'class' => implode( ' ', $classNames ),
			'id'    => isset( $settings['anchor'] ) ? $settings['anchor'] : '',
		), $settings ) );

		$listCount = isset( $settings['count'] ) ? $settings['count'] : 1;


		$defaultIcon = isset( $settings['defaultIcon'] ) && $settings['defaultIcon'] ? $settings['defaultIcon'] : '';

		for ( $i = 0; $i < $listCount; $i ++ ) {

			$icon_type = isset( $settings['type'] ) && 'shaped' === $settings['type'] ? ' bwf-svg-shaped' : '';
			$output    .= '<div class="bwf--icon bwf--icon-' . $i . $icon_type . '">';

			if ( isset( $settings['icons'] ) && isset( $settings['icons'][ $i ] ) ) {
				if ( ! empty( $settings['icons'][ $i ]['link'] ) ) {
					$target   = ! empty( $settings['icons'][ $i ]['newTab'] ) ? 'target="__blank"' : '';
					$relation = '';
					$relation .= ! empty( $settings['icons'][ $i ]['newTab'] ) ? 'noopener noreferrer' : '';
					$relation .= ! empty( $settings['icons'][ $i ]['noFollow'] ) ? ' nofollow' : '';

					$output .= sprintf( '<a %1$s>', slingblocks_attr( 'icon-link', array(
						'href'   => $settings['icons'][ $i ]['link'],
						'target' => $target,
						'rel'    => trim( $relation ),
						'class'  => 'bwf-icon-link'
					), $settings ) );
				}

			}
			$icon   = isset( $settings['icons'][ $i ]['icon'] ) ? $settings['icons'][ $i ]['icon'] : $defaultIcon;
			$output .= $icon;

			if ( isset( $settings['icons'] ) && isset( $settings['icons'][ $i ] ) ) {
				$output .= '</a>';
			}
			$output .= '</div>';

		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Output the dynamic aspects of our Progress Bar block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.2.0
	 */
	public function do_progress_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_progress_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		$output = '';

		$settings = wp_parse_args( $attributes, [] );

		$classNames = array(
			'bwf-progress-bar-wrapper',
			'bwf-' . $settings['uniqueID'],
		);

		if ( ! empty( $settings['className'] ) ) {
			$classNames[] = $settings['className'];
		}

		$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'bwf-progress-wrap', array(
			'class' => implode( ' ', $classNames ),
			'id'    => isset( $settings['anchor'] ) ? $settings['anchor'] : '',
		), $settings ) );

		$output .= sprintf( '<div %1$s>', slingblocks_attr( 'bwf-progress-inner-wrap', array(
			'class' => 'bwf-progress-inner-wrap',
		), $settings ) );

		if ( isset( $settings['enableTitle'] ) && $settings['enableTitle'] && isset( $settings['titleContent'] ) ) {
			$output .= '<div class="bwf-progress-title">' . $settings['titleContent'] . '</div>';
		}
		$content = 'Progress...';
		if ( isset( $settings['content'] ) ) {
			$content = $settings['content'];
		}
		$animate = '';
		if ( isset( $settings['enableAnimationStrip'] ) && $settings['enableAnimationStrip'] ) {
			$animate = 'bwf-animate';
		}
		$output .= '<div class="bwf-progress-bar"><div class="bwf-progress ' . $animate . '"><span class="bwf-progress-inner-text">' . $this->bwf_kses_post( $content ) . '</span></div></div>';

		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Output the dynamic aspects of Countdown block.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The inner blocks.
	 *
	 * @since 1.1.0
	 */
	public function do_count_down_block( $attributes, $content ) {

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id = $attributes['uniqueID'];
			$style_id  = 'sling-block-' . esc_attr( $unique_id );
			if ( ! wp_style_is( $style_id, 'enqueued' ) ) {
				$css = SLINGBLOCKS_Frontend_CSS::get_instance()->render_count_down_css_head( $attributes, $unique_id );
				if ( ! empty( $css ) ) {
					SLINGBLOCKS_Frontend_CSS::get_instance()->render_inline_css( $css, $style_id );
				}
			}
		}

		try {
			//code...

			$output = '';

			$settings = wp_parse_args( $attributes, [
				'separatorStyle' => 'dotted'
			] );

			$classNames = array(
				'bwf-countdown-outer',
				'bwf-' . $settings['uniqueID'],
			);

			if ( ! empty( $settings['className'] ) ) {
				$classNames[] = $settings['className'];
			}
			$countdownStyle = 'block';
			if ( ! empty( $settings['countdownStyle'] ) ) {
				$classNames[]   = 'bwf-' . $settings['countdownStyle'];
				$countdownStyle = $settings['countdownStyle'];
			}

			$classNames = $this->has_block_visibiliy_classes( $settings, $classNames );

			$output     .= sprintf( '<div %1$s>', slingblocks_attr( 'bwf-countdown-outer', array(
				'class' => implode( ' ', $classNames ),
				'id'    => isset( $settings['anchor'] ) ? $settings['anchor'] : '',
			), $settings ) );
			$output     .= sprintf( '<div %1$s>', slingblocks_attr( 'bwf-countdown-wrapper', array(
				'class' => 'bwf-countdown-wrapper',
			), $settings ) );
			$date_style = '';
			if ( ! isset( $settings['campaignType'] ) ) {
				$campaignType = 'evergreen';
			} else {
				$campaignType = $settings['campaignType'];
			}

			$timezone    = new DateTimeZone( wp_timezone_string() );
			$currentDate = new DateTime();
			$currentDate->setTimezone( $timezone );

			$checkDate   = $campaignType !== 'evergreen' && ! empty( $settings['reverseCountdown'] ) && ! empty( $settings['startdate'] ) && new DateTime( $settings['startdate'], $timezone ) > $currentDate;
			$end_date    = null;
			$cookie_data = null;
			$cookie_name = null;
			if ( $campaignType === 'evergreen' ) {
				$cookie_name = "sling_user_end_date" . $settings['uniqueID'];

				$evergreenDay    = isset( $settings['evergreenDay'] ) ? $settings['evergreenDay'] : 0;
				$evergreenHour   = isset( $settings['evergreenHour'] ) ? $settings['evergreenHour'] : 11;
				$evergreenMinute = isset( $settings['evergreenMinute'] ) ? $settings['evergreenMinute'] : 0;
				$evergreenSecond = isset( $settings['evergreenSecond'] ) ? $settings['evergreenSecond'] : 0;
				$date            = new DateTime( "+{$evergreenDay} day +{$evergreenHour} hour +{$evergreenMinute} minute +{$evergreenSecond} second" );
				$date->setTimezone( $timezone );
				$date_style = "{$evergreenDay},{$evergreenHour},{$evergreenMinute},{$evergreenSecond}";
				if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
					$cookie_data = $date->getTimestamp() . '000';
				} else {
					$new_date = new DateTime();
					$new_date->setTimezone( $timezone );
					$date_style = $new_date->getTimestamp();
					$end_date   = $_COOKIE[ $cookie_name ];
				}
			} else {


				$date = isset( $settings['timestamp'] ) ? $settings['timestamp'] : null;

				$date_style = $date ? $date : '';
				if ( $checkDate ) {
					$date       = isset( $settings['starttimestamp'] ) ? $settings['starttimestamp'] : null;
					$end_date   = $date_style;
					$date_style = $date ? $date : '';
				} else {
					if ( empty( $settings['startdate'] ) || new DateTime( $settings['startdate'], $timezone ) < $currentDate ) {
						$date = isset( $settings['timestamp'] ) ? $settings['timestamp'] : null;
					} else {
						return;
					}
				}
			}
			ob_start();

			if ( $checkDate && ! empty( $settings['reverseText'] ) ) {
				?>
				<div class="bwf-pre-text bwf-reverse-text"><?php echo $settings['reverseText']; ?></div>
				<?php
			} elseif ( ! empty( $settings['preText'] ) ) {
				?>
				<div class="bwf-pre-text bwf-before-text"><?php echo $this->bwf_kses_post( $settings['preText'] ); ?></div>
				<?php
			}
			global $post;
			$funnel_redirect_link = null;
			if ( $post instanceof WP_Post ) {
				$post_type = $post->post_type;
				if ( $post_type === 'wfocu_offer' ) {
					$funnel_redirect_link = 'wfocu-reject-link=yes';
				} elseif ( $post_type === 'wffn_landing' ) {
					$funnel_redirect_link = 'wffn-next-link=yes';
				}
			}
			$seperator_classes = [
				'bwf-countdown-inner-wrap',
				'bwf-hidden'
			];
			if ( ! isset( $settings['enableSeparator'] ) || $settings['enableSeparator'] && 'inline' !== $countdownStyle ) {
				array_push( $seperator_classes, 'bwf-separator' );
				array_push( $seperator_classes, 'bwf-separator-' . $settings['separatorStyle'] );
			}
			$output                  .= ob_get_clean();
			$output                  .= sprintf( '<div %1$s>', slingblocks_attr( 'bwf-countdown-inner-wrap', array(
				'class'             => implode( ' ', $seperator_classes ),
				'cookie_data'       => $cookie_data ? $cookie_data : null,
				'cookie_name'       => $cookie_data ? $cookie_name : null,
				'bwf-date'          => $date_style,
				'end_date'          => $end_date,
				'msgAfter'          => isset( $settings['expiryType'] ) && 'message' === $settings['expiryType'] ? ( isset( $settings['expiryTitle'] ) ? $this->bwf_kses_post(html_entity_decode( $settings['expiryTitle'] ) ) : 'Countdown is finished!' ) : null,
				'expiryRedirectUrl' => isset( $settings['expiryType'] ) && 'redirect' === $settings['expiryType'] ? ( isset( $settings['expiryRedirectUrl'] ) ? esc_url( $settings['expiryRedirectUrl'] ) : null ) : null,
				'funnel-next-step'  => isset( $settings['expiryType'] ) && 'funnel-next-step' === $settings['expiryType'] ? $funnel_redirect_link : null,
			), $settings ) );
			$settings['dayLabel']    = isset( $settings['dayLabel'] ) ? $settings['dayLabel'] : 'Days';
			$settings['hourLabel']   = isset( $settings['hourLabel'] ) ? $settings['hourLabel'] : 'Hours';
			$settings['minuteLabel'] = isset( $settings['minuteLabel'] ) ? $settings['minuteLabel'] : 'Minutes';
			$settings['secondLabel'] = isset( $settings['secondLabel'] ) ? $settings['secondLabel'] : 'Seconds';
			ob_start();
			?>
			<!-- Days  -->
			<?php if ( ! isset( $settings['fixDayEnable'] ) || $settings['fixDayEnable'] ) { ?>
				<div class="bwf-digit-card" bwftype="<?php echo 'inline' !== $countdownStyle ? ( isset( $settings['separatorStyle'] ) && 'dotted' !== $settings['separatorStyle'] ? "|" : ":" ) : ''; ?>">
					<div class="bwf-card-data">
						<div class="bwf-timer-digit">{days}</div>
						<?php if ( $settings['dayLabel'] ) { ?>
							<div class="bwf-timer-label"><?php echo esc_html( $settings['dayLabel'] ); ?></div>
						<?php } else { ?>
							<div class="bwf-timer-label bwf-hidden">Days</div>
						<?php } ?>
					</div>
				</div>
			<?php } ?>
			<!-- Hours  -->
			<?php if ( ! isset( $settings['fixHourEnable'] ) || $settings['fixHourEnable'] ) { ?>
				<div class="bwf-digit-card" bwftype="<?php echo 'inline' !== $countdownStyle ? ( isset( $settings['separatorStyle'] ) && 'dotted' !== $settings['separatorStyle'] ? "|" : ":" ) : ''; ?>">
					<div class="bwf-card-data">
						<div class="bwf-timer-digit">{hours}</div>
						<?php if ( $settings['hourLabel'] ) { ?>
							<div class="bwf-timer-label"><?php echo esc_html( $settings['hourLabel'] ); ?></div>
						<?php } else { ?>
							<div class="bwf-timer-label bwf-hidden">Hours</div>
						<?php } ?>
					</div>
				</div>
			<?php } ?>
			<!-- Minutes  -->
			<?php if ( ! isset( $settings['fixMinuteEnable'] ) || $settings['fixMinuteEnable'] ) { ?>
				<div class="bwf-digit-card" bwftype="<?php echo 'inline' !== $countdownStyle ? ( isset( $settings['separatorStyle'] ) && 'dotted' !== $settings['separatorStyle'] ? "|" : ":" ) : ''; ?>">
					<div class="bwf-card-data">
						<div class="bwf-timer-digit">{minutes}</div>
						<?php if ( $settings['minuteLabel'] ) { ?>
							<div class="bwf-timer-label"><?php echo esc_html( $settings['minuteLabel'] ); ?></div>
						<?php } else { ?>
							<div class="bwf-timer-label bwf-hidden">Minutes</div>
						<?php } ?>
					</div>
				</div>
			<?php } ?>
			<!-- Seconds  -->
			<?php if ( ! isset( $settings['fixSecEnable'] ) || $settings['fixSecEnable'] ) { ?>
				<div class="bwf-digit-card" bwftype="<?php echo 'inline' !== $countdownStyle ? ( isset( $settings['separatorStyle'] ) && 'dotted' !== $settings['separatorStyle'] ? "|" : ":" ) : ''; ?>">
					<div class="bwf-card-data">
						<div class="bwf-timer-digit">{seconds}</div>
						<?php if ( $settings['secondLabel'] ) { ?>
							<div class="bwf-timer-label"><?php echo esc_html( $settings['secondLabel'] ); ?></div>
						<?php } else { ?>
							<div class="bwf-timer-label bwf-hidden">Seconds</div>
						<?php } ?>
					</div>
				</div>
			<?php } ?>
			<div style="display:none" class="bwf-timer-hidden">{days}{hours}{seconds}{minutes}</div></div>
			<?php
			if ( ! $checkDate && ! empty( $settings['preText'] ) ) {
				?>
				<div class="bwf-pre-text bwf-after-text"><?php echo $this->bwf_kses_post( $settings['postText'] ); ?></div>
				<?php
			}
			$output .= ob_get_clean() . '</div></div>';

			return $output;
		} catch ( \Throwable $th ) {
			//throw $th;
		}
	}
}

SLINGBLOCKS_Render_Block::get_instance();
