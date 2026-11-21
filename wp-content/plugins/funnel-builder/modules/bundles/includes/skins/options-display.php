<?php
/**
 * Options_Display — how a variable product's options render inside an offer.
 *
 * Every render path resolves through here rather than off a raw get_option, and
 * Settings_Controller validates against the MODES constant.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Skins;

use WFFN\Bundles\Includes\Storage\Install;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves how a variable product’s options render inside an offer.
 */
class Options_Display {

	/** Auto-detect: simple products inline, complex ones (3+ attributes, swatches, add-ons, bookings) in the popup. */
	const MODE_AUTO = 'auto';

	/** Always render option dropdowns directly in the offer. */
	const MODE_INLINE = 'inline';

	/** Always open the quick-view popup for variable products. */
	const MODE_MODAL = 'modal';

	/** The full set of valid stored modes (validation contract). */
	const MODES = array( self::MODE_AUTO, self::MODE_INLINE, self::MODE_MODAL );

	/** Mode applied when nothing valid is stored. */
	const DEFAULT_MODE = self::MODE_AUTO;

	/**
	 * The effective global mode, read once per request.
	 *
	 * @return string One of the MODE_* constants.
	 */
	public static function mode() {
		static $mode = null;
		if ( null !== $mode ) {
			return $mode;
		}
		$stored = get_option( Install::OPTION_SETTINGS, array() );
		$value  = is_array( $stored ) ? ( $stored[ Install::SETTINGS_VARIATION_OPTIONS_DISPLAY ] ?? '' ) : '';
		$mode   = in_array( $value, self::MODES, true ) ? $value : self::DEFAULT_MODE;
		return $mode;
	}

	/**
	 * Resolve one product to 'inline' or 'modal'. The explicit modes always win;
	 * auto sends a complex product to the popup.
	 *
	 * @param bool $complex Whether the product's options warrant the popup.
	 * @return string 'inline' or 'modal'.
	 */
	public static function variant_mode( $complex ) {
		$mode = self::mode();
		if ( self::MODE_INLINE === $mode ) {
			return 'inline';
		}
		if ( self::MODE_MODAL === $mode ) {
			return 'modal';
		}
		return $complex ? 'modal' : 'inline';
	}
}
