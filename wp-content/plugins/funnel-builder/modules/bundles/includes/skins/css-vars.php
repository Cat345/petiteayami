<?php
/** Css_Vars — design tokens → `--wffn-bundles-*` custom properties (PHP). */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serialises design tokens into `--wffn-bundles-*` custom-property declarations.
 */
class Css_Vars {

	/**
	 * Every colour the Design tab can set, as
	 * `[ custom property, plugin default, token, sub-key or null ]`.
	 *
	 * Must agree with DEFAULT_BUNDLE_DESIGN (admin/app/src/pages/bundles/edit/schema.ts)
	 * and the `:where(:root, :host)` block in assets/skins/_shared.css.
	 */
	const MERCHANT_COLORS = array(
		array( '--wffn-bundles-bg', '#FFFFFF', 'layout_bgColor', 'default' ),
		array( '--wffn-bundles-bg-active', '#FAFAFA', 'layout_bgColor', 'active' ),
		array( '--wffn-bundles-border', '#DEDFEA', 'layout_borderColor', 'default' ),
		array( '--wffn-bundles-border-active', '#353030', 'layout_borderColor', 'active' ),
		array( '--wffn-bundles-radio', '#C5C5CD', 'layout_radioColor', 'default' ),
		array( '--wffn-bundles-radio-active', '#353030', 'layout_radioColor', 'active' ),
		// The other controls (add buttons, steppers) share the selected radio's colour.
		array( '--wffn-bundles-control-accent', '#353030', 'layout_radioColor', 'active' ),
		array( '--wffn-bundles-heading-color', '#353030', 'typography_headingColor', null ),
		array( '--wffn-bundles-title-color', '#353030', 'typography_bundleTitleColor', null ),
		array( '--wffn-bundles-desc-color', '#82828E', 'typography_descriptionColor', null ),
		array( '--wffn-bundles-regular-price-color', '#82828E', 'typography_regularPriceColor', null ),
		array( '--wffn-bundles-sale-price-color', '#353030', 'typography_salePriceColor', null ),
		array( '--wffn-bundles-price-desc-color', '#82828E', 'typography_priceDescriptionColor', null ),
		array( '--wffn-bundles-badge-bg', '#353030', 'badge_bgColor', null ),
		array( '--wffn-bundles-badge-fg', '#FFFFFF', 'badge_textColor', null ),
		array( '--wffn-bundles-success', '#059669', 'accent_successColor', null ),
		array( '--wffn-bundles-progress-fill', '#353030', 'progress_fillColor', null ),
		array( '--wffn-bundles-connector', '#353030', 'connector_color', null ),
	);

	/**
	 * A section design's overrides of the shared widget vars, '' when the
	 * merchant moved nothing off the section defaults.
	 *
	 * @param array $design Section design tokens.
	 * @param array $table  Rows of `[ custom property, default or defaults, token, px ]`.
	 * @return string
	 */
	public static function for_section_design( $design, array $table ) {
		$design = is_array( $design ) ? $design : array();
		$parts  = array();
		foreach ( $table as $spec ) {
			list( $prop, $defaults, $token, $px ) = $spec;
			$defaults = is_array( $defaults ) ? $defaults : array( $defaults );
			$val      = $design[ $token ] ?? null;
			if ( null === $val || '' === $val ) {
				continue;
			}
			if ( $px ) {
				if ( in_array( (float) $val, array_map( 'floatval', $defaults ), true ) ) {
					continue;
				}
				$parts[] = $prop . ':' . (float) $val . 'px';
				continue;
			}
			if ( ! is_string( $val ) ) {
				continue;
			}
			$inert = false;
			foreach ( $defaults as $default ) {
				if ( self::same_color( $val, (string) $default ) ) {
					$inert = true;
					break;
				}
			}
			if ( $inert || ! self::safe_token( $val ) ) {
				continue;
			}
			$parts[] = $prop . ':' . trim( $val );
		}
		return implode( ';', $parts );
	}

	/** Tokens that style one bundle's own widget, never the rest of the page. */
	const WIDGET_ONLY = array(
		'--wffn-bundles-radio-display',
		'--wffn-bundles-radio-position',
		'--wffn-bundles-radio-indent',
		'--wffn-bundles-card-focus',
	);

	/**
	 * A design's declarations without its widget-only tokens, for a page-wide copy.
	 *
	 * @param string $css Declarations, as for_design() returns them.
	 * @return string
	 */
	public static function page_wide( $css ) {
		$kept = array();
		foreach ( explode( ';', (string) $css ) as $decl ) {
			$name = trim( strstr( $decl, ':', true ) );
			if ( '' !== $name && ! in_array( $name, self::WIDGET_ONLY, true ) ) {
				$kept[] = $decl;
			}
		}
		return implode( ';', $kept );
	}

	/**
	 * The full `--wffn-bundles-*` declaration string for a design.
	 *
	 * @param array $design Design tokens.
	 * @return string
	 */
	public static function for_design( $design ) {
		$design = is_array( $design ) ? $design : array();
		$vars   = array();

		$px = function ( $n ) {
			return is_numeric( $n ) ? (float) $n . 'px' : null;
		};
		$set = function ( $key, $val ) use ( &$vars ) {
			if ( null !== $val && '' !== $val && self::safe_token( $val ) ) {
				$vars[ $key ] = (string) $val;
			}
		};

		$bg = isset( $design['layout_bgColor'] ) && is_array( $design['layout_bgColor'] ) ? $design['layout_bgColor'] : null;
		if ( $bg ) {
			$set( '--wffn-bundles-bg', $bg['default'] ?? null );
			$set( '--wffn-bundles-bg-active', $bg['active'] ?? null );
		}
		$bd = isset( $design['layout_borderColor'] ) && is_array( $design['layout_borderColor'] ) ? $design['layout_borderColor'] : null;
		if ( $bd ) {
			$set( '--wffn-bundles-border', $bd['default'] ?? null );
			$set( '--wffn-bundles-border-active', $bd['active'] ?? null );
		}
		$rd = isset( $design['layout_radioColor'] ) && is_array( $design['layout_radioColor'] ) ? $design['layout_radioColor'] : null;
		if ( $rd ) {
			$set( '--wffn-bundles-radio', $rd['default'] ?? null );
			$set( '--wffn-bundles-radio-active', $rd['active'] ?? null );
		}

		$set( '--wffn-bundles-border-style', $design['layout_borderStyle'] ?? null );
		// Four sides before 1.0, of which only the top was ever rendered.
		$bw = $design['layout_borderWidth'] ?? null;
		$set( '--wffn-bundles-border-width', $px( is_array( $bw ) ? ( $bw['top'] ?? null ) : $bw ) );
		$set( '--wffn-bundles-corner', $px( $design['layout_cornerRadius'] ?? null ) );

		$pad = isset( $design['layout_padding'] ) && is_array( $design['layout_padding'] ) ? $design['layout_padding'] : null;
		if ( $pad ) {
			$set( '--wffn-bundles-pad-t', $px( $pad['top'] ?? null ) );
			$set( '--wffn-bundles-pad-r', $px( $pad['right'] ?? null ) );
			$set( '--wffn-bundles-pad-b', $px( $pad['bottom'] ?? null ) );
			$set( '--wffn-bundles-pad-l', $px( $pad['left'] ?? null ) );
		}
		$set( '--wffn-bundles-radio-size', $px( $design['layout_radioSize'] ?? null ) );
		// Radio off: the card is the control, so it takes the radio's space and focus ring.
		if ( false === filter_var( $design['layout_radioShow'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
			$set( '--wffn-bundles-radio-display', 'none' );
			$set( '--wffn-bundles-radio-position', 'absolute' );
			$set( '--wffn-bundles-radio-indent', '0px' );
			$set( '--wffn-bundles-card-focus', '2px' );
		}

		$set( '--wffn-bundles-heading-size', $px( $design['typography_headingFontSize'] ?? null ) );
		$set( '--wffn-bundles-heading-color', $design['typography_headingColor'] ?? null );
		$set( '--wffn-bundles-title-size', $px( $design['typography_bundleTitleFontSize'] ?? null ) );
		$set( '--wffn-bundles-title-color', $design['typography_bundleTitleColor'] ?? null );
		$set( '--wffn-bundles-desc-size', $px( $design['typography_descriptionFontSize'] ?? null ) );
		$set( '--wffn-bundles-desc-color', $design['typography_descriptionColor'] ?? null );
		$set( '--wffn-bundles-price-size', $px( $design['typography_priceFontSize'] ?? null ) );
		$set( '--wffn-bundles-regular-price-color', $design['typography_regularPriceColor'] ?? null );
		$set( '--wffn-bundles-sale-price-color', $design['typography_salePriceColor'] ?? null );
		$set( '--wffn-bundles-price-desc-size', $px( $design['typography_priceDescriptionFontSize'] ?? null ) );
		$set( '--wffn-bundles-price-desc-color', $design['typography_priceDescriptionColor'] ?? null );

		$set( '--wffn-bundles-badge-bg', $design['badge_bgColor'] ?? null );
		$set( '--wffn-bundles-badge-fg', $design['badge_textColor'] ?? null );
		$set( '--wffn-bundles-badge-size', $px( $design['badge_fontSize'] ?? null ) );
		$set( '--wffn-bundles-badge-radius', $px( $design['badge_borderRadius'] ?? null ) );

		$set( '--wffn-bundles-border-width-active', $px( $design['layout_selectedBorderWidth'] ?? null ) );
		$set( '--wffn-bundles-image-radius', $px( $design['layout_imageRadius'] ?? null ) );
		$set( '--wffn-bundles-offer-image-size', $px( $design['layout_offerImageWidth'] ?? null ) );
		$set( '--wffn-bundles-item-image-size', $px( $design['layout_itemImageWidth'] ?? null ) );
		$set( '--wffn-bundles-success', $design['accent_successColor'] ?? null );
		$set( '--wffn-bundles-progress-fill', $design['progress_fillColor'] ?? null );
		$set( '--wffn-bundles-progress-height', $px( $design['progress_height'] ?? null ) );
		$set( '--wffn-bundles-hint-bg', $design['hint_bgColor'] ?? null );
		$set( '--wffn-bundles-hint-fg', $design['hint_textColor'] ?? null );
		$set( '--wffn-bundles-connector', $design['connector_color'] ?? null );

		/**
		 * Filters the design-token custom-property map before serialisation.
		 *
		 * Lets an add-on map its own design keys onto `--wffn-bundles-*` properties.
		 *
		 * @since 1.0.0
		 *
		 * @param array $vars   Custom property → value map.
		 * @param array $design Design tokens.
		 */
		$vars = (array) apply_filters( 'wffn_bundles_design_css_vars', $vars, $design );

		$parts = array();
		foreach ( $vars as $key => $val ) {
			// Re-checked after the filter: an add-on's own token map answers this
			// same filter and is not implicitly trusted just because $set() is.
			if ( 1 !== preg_match( '/^--wffn-bundles-[a-z0-9-]+$/i', (string) $key ) || ! self::safe_token( $val ) ) {
				continue;
			}
			$parts[] = $key . ':' . $val;
		}
		return implode( ';', $parts );
	}

	/**
	 * The colours this design moved off the plugin default, as `--wffn-bundles-*`
	 * declarations — '' when the merchant changed none of them.
	 *
	 * Equality with the default is the only reading of "left alone": the editor
	 * stores every token on each save, so absence never happens.
	 *
	 * @param array $design Design tokens.
	 * @return string
	 */
	public static function for_merchant_colors( $design ) {
		$design = is_array( $design ) ? $design : array();
		$parts  = array();

		foreach ( self::MERCHANT_COLORS as $spec ) {
			list( $prop, $default, $token, $sub ) = $spec;

			$val = $design[ $token ] ?? null;
			if ( null !== $sub ) {
				$val = is_array( $val ) ? ( $val[ $sub ] ?? null ) : null;
			}
			$val = is_string( $val ) ? trim( $val ) : '';
			if ( '' === $val || self::same_color( $val, $default ) || ! self::safe_token( $val ) ) {
				continue;
			}
			$parts[ $prop ] = $prop . ':' . $val;
		}

		// A badge's fill and label are one decision. The label equalling the plugin default (white)
		// reads as "left alone", and then a theme's own label colour lands on the merchant's fill:
		// dark text on a dark badge. Moving either one pins both.
		$pair = array( '--wffn-bundles-badge-bg' => 'badge_bgColor', '--wffn-bundles-badge-fg' => 'badge_textColor' );
		if ( 1 === count( array_intersect_key( $pair, $parts ) ) ) {
			foreach ( $pair as $prop => $token ) {
				$val = isset( $design[ $token ] ) && is_string( $design[ $token ] ) ? trim( $design[ $token ] ) : '';
				if ( ! isset( $parts[ $prop ] ) && '' !== $val && self::safe_token( $val ) ) {
					$parts[ $prop ] = $prop . ':' . $val;
				}
			}
		}
		return implode( ';', $parts );
	}

	/**
	 * Whether a design token is a value shape safe to put in inline CSS.
	 *
	 * Allowed: a hex colour, a number with an optional unit, a bare keyword,
	 * a numeric `rgb()`/`hsl()` colour, or a `var(--wffn-bundles-*)` reference.
	 *
	 * @param mixed $val Design token value.
	 * @return bool
	 */
	private static function safe_token( $val ) {
		if ( ! is_string( $val ) && ! is_int( $val ) && ! is_float( $val ) ) {
			return false;
		}
		$patterns = array(
			'/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i',
			'/^-?(?:\d+|\d*\.\d+)(?:px|em|rem|%)?$/i',
			'/^[a-z]+(?:-[a-z]+)*$/i',
			'/^(?:rgb|hsl)a?\([\d.,%\s\/]+\)$/i',
			'/^var\(--wffn-bundles-[a-z0-9-]+\)$/i',
		);
		$val = trim( (string) $val );
		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $val ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Do two colour strings name the same colour?
	 *
	 * Case and hex shorthand only — the picker normalises to `#RRGGBB` uppercase
	 * (admin/app/src/lib/color.ts), but demo and pre-picker data are lowercase.
	 *
	 * @param string $a First colour.
	 * @param string $b Second colour.
	 * @return bool
	 */
	private static function same_color( $a, $b ) {
		return self::normalize_color( $a ) === self::normalize_color( $b );
	}

	/**
	 * Lowercase a colour and expand `#abc` to `#aabbcc`; anything else is
	 * returned trimmed and lowercased for a literal comparison.
	 *
	 * @param string $color Colour string.
	 * @return string
	 */
	private static function normalize_color( $color ) {
		$color = strtolower( trim( (string) $color ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return $color;
	}
}
