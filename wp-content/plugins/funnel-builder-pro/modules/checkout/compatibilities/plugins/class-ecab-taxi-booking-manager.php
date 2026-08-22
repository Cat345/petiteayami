<?php
/**
 * eCab Taxi Booking Manager compatibility.
 *
 * The taxi plugin injects its "Booking Details" block (icons + rows) into the cart-item meta.
 * Inside the FunnelKit checkout order summary it renders broken:
 *   - Font Awesome icons show as squares. Font Awesome IS loaded on the page, but the checkout's
 *     broad `font-family: inherit` resets cascade onto the icon <span>s and override it, so the
 *     glyphs fall back to the body font. We re-assert the Font Awesome font-family/weight so the
 *     already-loaded font renders them.
 *   - Layout: the wrapping <dl class="variation"> floats the box narrow & to the right, the <h6>
 *     labels are block-level (every field stacks), and headings inherit wide letter-spacing.
 *
 * Fixed with scoped CSS, printed only on the checkout via `wfacp_internal_css`. Selectors target
 * the taxi plugin's own `.mpStyle` / `.cart_list` classes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Ecab_Taxi' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Ecab_Taxi {

		public function __construct() {
			add_action( 'wfacp_internal_css', array( $this, 'add_css' ) );
		}

		public function add_css() {
			?>
			<style>
				/* Wrapper: dl.variation floats dt/dd → narrow, right-shoved box */
				dl.variation:has(.mpStyle) { display: block; width: 100%; margin: 6px 0 0; overflow: visible; }
				dl.variation:has(.mpStyle) dt,
				dl.variation:has(.mpStyle) dd {
					display: block !important; float: none !important; clear: both !important;
					width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important;
				}
				dl.variation:has(.mpStyle) dt { font-weight: 600; letter-spacing: normal; margin-bottom: 2px; }
				dl.variation dd p:has(.mpStyle) { margin: 0; }

				/* Taxi block: full width */
				.mpStyle, .mpStyle .dLayout_xs, .mpStyle .cart_list {
					width: 100% !important; max-width: 100% !important; float: none !important;
					margin: 0 !important; padding: 0 !important; box-sizing: border-box;
				}
				.mpStyle { font-size: 13px !important; letter-spacing: normal !important; line-height: 1.5; }
				.mpStyle .cart_list { padding: 11px 14px !important; }
				.mpStyle h5 { margin: 12px 0 6px !important; }

				/* Rows: label + value on one line */
				.mpStyle .cart_list { list-style: none !important; }
				.mpStyle .cart_list li {
					display: flex !important; align-items: center; flex-wrap: wrap; gap: 2px 7px;
					margin: 0 0 5px !important; padding: 0 !important; border: 0 !important; background: none !important;
				}
				.mpStyle .cart_list li h6 {
					display: inline !important; margin: 0 !important; padding: 0 !important;
					font-size: 13px !important; font-weight: 600 !important; color: #a9862d !important;
					letter-spacing: 0.3px !important; text-transform: none !important; line-height: 1.5 !important;
				}
				.mpStyle .cart_list li > span:not([class*="fa"]) {
					display: inline !important; font-size: 13px !important; letter-spacing: normal !important; line-height: 1.5 !important;
				}
				.mpStyle .divider { display: none !important; }

				/* Icons: re-assert Font Awesome (the checkout's `font-family: inherit` reset clobbers it) */
				.mpStyle .fa,
				.mpStyle .fas,
				.mpStyle .far,
				.mpStyle .fab {
					font-family: "Font Awesome 5 Free", "Font Awesome 5 Brands", "FontAwesome" !important;
					-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
					font-style: normal !important; font-variant: normal !important; text-rendering: auto !important;
					font-size: 13px !important; line-height: 1 !important; color: #333 !important;
					flex: 0 0 auto; width: auto !important; height: auto !important; display: inline-block;
				}
				.mpStyle .far { font-weight: 400 !important; }
				.mpStyle .fa,
				.mpStyle .fas { font-weight: 900 !important; }
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Ecab_Taxi(), 'ecab-taxi-booking-manager' );
}
