<?php
/**
 * Woodmart Theme Compatibility
 *
 * Fixes lazy loading issues with product images in cart when using Woodmart theme.
 * Triggers 'wood-images-loaded' event when cart fragments are refreshed.
 */
namespace FKCart\Compatibilities;

if ( ! class_exists( '\FKCart\Compatibilities\FKCART_Woodmart' ) ) {

	class FKCART_Woodmart {

		public function __construct() {
			add_action( 'wp_footer', array( $this, 'internal_js' ) );
			add_filter( 'fkcart_fragments', array( $this, 'add_header_cart_fragments' ) );
		}

		/**
		 * FKCart's add/update/remove responses carry only FKCart's fragments, so Woodmart's header cart
		 * count and subtotal (normally sent through woocommerce_add_to_cart_fragments) stay stale.
		 * Woodmart suffixes its keys with "_wd" and strips it in its own JS; FKCart replaces fragments
		 * by the key as is, so send the real selectors.
		 *
		 * @param array $fragments FKCart fragments.
		 *
		 * @return array
		 */
		public function add_header_cart_fragments( $fragments ) {
			if ( ! function_exists( 'woodmart_cart_data' ) ) {
				return $fragments;
			}

			foreach ( woodmart_cart_data( array() ) as $selector => $html ) {
				$fragments[ str_replace( '_wd', '', $selector ) ] = $html;
			}

			return $fragments;
		}

		public function internal_js() {
			// Only run if Woodmart lazy loading is enabled
			if ( ! function_exists( 'woodmart_get_opt' ) || ! woodmart_get_opt( 'lazy_loading' ) ) {
				return;
			}

			?>
			<script>
				window.addEventListener('DOMContentLoaded', function () {
					(function ($) {

						// Trigger Woodmart's lazy loading for images
						function triggerWoodmartImageLoading(delay = 100) {
							setTimeout(function() {
								if (typeof woodmartThemeModule !== 'undefined' && woodmartThemeModule.$document) {
									woodmartThemeModule.$document.trigger('wood-images-loaded');
								}
							}, delay);
						}

						// Cart open events
						$(document.body).on('fkcart_cart_open', function () {
							triggerWoodmartImageLoading(100);
						});

						$(document.body).on('fkcart_cart_quick_view_open', function () {
							triggerWoodmartImageLoading(100);
						});

						// Cart fragment events - handles quantity updates, additions, removals
						// Use wc_fragments_refreshed as it's actually triggered by the plugin
						$(document.body).on('wc_fragments_refreshed', function () {
							triggerWoodmartImageLoading(150);
						});

					})(jQuery);
				});
			</script>
			<?php
		}
	}

	Compatibility::register( new FKCART_Woodmart(), 'woodmart' );
}
