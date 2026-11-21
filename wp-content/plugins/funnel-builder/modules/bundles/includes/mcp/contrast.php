<?php
/**
 * Whether a bundle's design leaves its text readable.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the colours a write sets against the colour each is drawn on, so an
 * agent cannot save white text on a white card: the storefront draws whatever
 * it is given, and nobody sees the result until a shopper does.
 *
 * Only `bundleDesign` is checked: this plugin draws it. A pair is checked when
 * the write sets either side of it, so an untouched design is never refused.
 */
class Contrast {

	/** WCAG's floor for large text; below it text is hard to read at any size. */
	const MIN = 3.0;

	/**
	 * The tokens the pairs read, at the editor's defaults.
	 *
	 * Must agree with DEFAULT_BUNDLE_DESIGN (admin/app/src/pages/bundles/edit/schema).
	 */
	const DEFAULTS = array(
		'layout_bgColor'                   => array(
			'default' => '#FFFFFF',
			'active'  => '#FAFAFA',
		),
		'layout_radioColor'                => array(
			'default' => '#C5C5CD',
			'active'  => '#353030',
		),
		'typography_headingColor'          => '#353030',
		'typography_bundleTitleColor'      => '#353030',
		'typography_descriptionColor'      => '#82828E',
		'typography_regularPriceColor'     => '#82828E',
		'typography_salePriceColor'        => '#353030',
		'typography_priceDescriptionColor' => '#82828E',
		'accent_successColor'              => '#059669',
		'badge_bgColor'                    => '#353030',
		'badge_textColor'                  => '#FFFFFF',
		'hint_bgColor'                     => '#F1F1F4',
		'hint_textColor'                   => '#353030',
	);

	/** Text drawn on the option cards, both the resting and the selected one. */
	const ON_CARD = array(
		'typography_bundleTitleColor',
		'typography_salePriceColor',
		'typography_regularPriceColor',
		'typography_descriptionColor',
		'typography_priceDescriptionColor',
		'accent_successColor',
	);

	/** Text drawn on a fill of its own: text token => fill token. */
	const ON_FILL = array(
		'badge_textColor' => 'badge_bgColor',
		'hint_textColor'  => 'hint_bgColor',
	);

	/**
	 * Every pair a write leaves below MIN, as validate-bundle issues.
	 *
	 * @param array $patch  The discountConfig being written.
	 * @param array $stored The bundle's stored discountConfig, or none for a new bundle.
	 * @return array[] { path, message }
	 */
	public static function issues( array $patch, array $stored = array() ) {
		$set = is_array( $patch['bundleDesign'] ?? null ) ? $patch['bundleDesign'] : array();
		if ( ! $set ) {
			return array();
		}
		$was    = is_array( $stored['bundleDesign'] ?? null ) ? $stored['bundleDesign'] : array();
		$design = array_merge( self::DEFAULTS, $was, $set );
		$issues = array();

		$bg    = is_array( $design['layout_bgColor'] ) ? $design['layout_bgColor'] : array( 'default' => $design['layout_bgColor'] );
		$cards = array(
			'default' => self::hex( $bg['default'] ?? null ),
			'active'  => self::hex( $bg['active'] ?? ( $bg['default'] ?? null ) ),
		);
		foreach ( self::ON_CARD as $token ) {
			$text = self::hex( $design[ $token ] ?? null );
			if ( null === $text || ( ! isset( $set[ $token ] ) && ! isset( $set['layout_bgColor'] ) ) ) {
				continue;
			}
			foreach ( $cards as $state => $card ) {
				$ratio = null === $card ? null : self::ratio( $text, $card );
				if ( null !== $ratio && $ratio < self::MIN ) {
					$issues[] = self::issue( $token, $text, $card, $ratio, 'layout_bgColor.' . $state );
					break;
				}
			}
		}

		foreach ( self::ON_FILL as $token => $fill_token ) {
			if ( ! isset( $set[ $token ] ) && ! isset( $set[ $fill_token ] ) ) {
				continue;
			}
			$text  = self::hex( $design[ $token ] ?? null );
			$fill  = self::hex( $design[ $fill_token ] ?? null );
			$ratio = null === $text || null === $fill ? null : self::ratio( $text, $fill );
			if ( null !== $ratio && $ratio < self::MIN ) {
				$issues[] = self::issue( $token, $text, $fill, $ratio, $fill_token );
			}
		}

		// The skins draw the add button and the ticks white on the selected radio colour.
		if ( isset( $set['layout_radioColor'] ) ) {
			$radio = is_array( $design['layout_radioColor'] ) ? $design['layout_radioColor'] : array( 'active' => $design['layout_radioColor'] );
			$fill  = self::hex( $radio['active'] ?? null );
			$ratio = null === $fill ? null : self::ratio( '#FFFFFF', $fill );
			if ( null !== $ratio && $ratio < self::MIN ) {
				$issues[] = array(
					'path'    => 'discountConfig.bundleDesign.layout_radioColor',
					'message' => sprintf( 'Unreadable: layout_radioColor.active %s fills the add button and ticks under white, %.1f:1, needs %.0f:1; make it darker.', $fill, $ratio, self::MIN ),
				);
			}
		}

		$page = self::page_background();
		if ( null !== $page && isset( $set['typography_headingColor'] ) ) {
			$text  = self::hex( $design['typography_headingColor'] );
			$ratio = null === $text ? null : self::ratio( $text, $page );
			if ( null !== $ratio && $ratio < self::MIN ) {
				$issues[] = self::issue( 'typography_headingColor', $text, $page, $ratio, 'the page background (it sits above the cards)' );
			}
		}

		return $issues;
	}

	/**
	 * The page background the active theme sets, when it sets one: the bundle's
	 * heading sits on it, above the cards. Null when the theme leaves it to its CSS.
	 *
	 * @return string|null Hex colour.
	 */
	public static function page_background() {
		$color = null;
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() && function_exists( 'wp_get_global_styles' ) ) {
			$color = self::preset_color( wp_get_global_styles( array( 'color', 'background' ) ) );
		}
		if ( null === $color && current_theme_supports( 'custom-background' ) ) {
			$bg    = (string) get_background_color();
			$color = '' !== $bg ? self::hex( '#' . ltrim( $bg, '#' ) ) : null;
		}
		return $color;
	}

	/**
	 * A global-styles colour as hex: a literal, or a palette preset by reference.
	 *
	 * @param mixed $value `#hex`, `var:preset|color|slug` or `var(--wp--preset--color--slug)`.
	 * @return string|null
	 */
	private static function preset_color( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( ! preg_match( '/^var(?::preset\|color\||\(--wp--preset--color--)([a-z0-9-]+)\)?$/i', trim( $value ), $m ) ) {
			return self::hex( $value );
		}
		$palette = function_exists( 'wp_get_global_settings' ) ? (array) wp_get_global_settings( array( 'color', 'palette' ) ) : array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $entry ) {
				if ( is_array( $entry ) && strtolower( (string) ( $entry['slug'] ?? '' ) ) === strtolower( $m[1] ) ) {
					return self::hex( $entry['color'] ?? null );
				}
			}
		}
		return null;
	}

	/**
	 * The WCAG contrast ratio of two colours, 1 to 21.
	 *
	 * @param string $a `#RRGGBB`.
	 * @param string $b `#RRGGBB`.
	 * @return float
	 */
	public static function ratio( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Relative luminance, per WCAG.
	 *
	 * @param string $hex `#RRGGBB`.
	 * @return float
	 */
	private static function luminance( $hex ) {
		$out = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$c    = hexdec( substr( $hex, 1 + 2 * $i, 2 ) ) / 255;
			$c    = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			$out += $weight * $c;
		}
		return $out;
	}

	/**
	 * An opaque hex colour as `#RRGGBB`, or null: shorthand is expanded, and a
	 * colour with alpha is not judged, since what shows through is unknown.
	 *
	 * @param mixed $value Colour.
	 * @return string|null
	 */
	public static function hex( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( $value ), $m ) ) {
			return null;
		}
		$h = $m[1];
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		return '#' . strtoupper( $h );
	}

	/**
	 * One unreadable pair as an issue, with a colour that would read.
	 *
	 * @param string $token Text token.
	 * @param string $text  Text colour.
	 * @param string $under What it is drawn on.
	 * @param float  $ratio Their ratio.
	 * @param string $where The token or surface it is drawn on.
	 * @return array
	 */
	private static function issue( $token, $text, $under, $ratio, $where ) {
		// Short: a refusal joins every issue into one message, and clients cut long ones.
		$fix = self::ratio( '#FFFFFF', $under ) >= self::ratio( '#111418', $under ) ? 'light, e.g. #FFFFFF' : 'dark, e.g. #111418';
		return array(
			'path'    => 'discountConfig.bundleDesign.' . $token,
			'message' => sprintf( 'Unreadable: %s %s on %s %s is %.1f:1, needs %.0f:1; make it %s.', $token, $text, $where, $under, $ratio, self::MIN, $fix ),
		);
	}
}
