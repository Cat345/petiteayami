/**
 * Funnel Builder (Aero Checkout) — point a pack row's edit link at its target.
 *
 * The Products list builds every row's link from the product the row sells, and
 * a pack row sells the shared container placeholder.
 *
 * Rewritten in the DOM rather than in the row's data: the save path re-keys the
 * stored row from `key`, so changing it would rewrite the merchant's products.
 */
(function () {
	'use strict';

	var MAP = ( window.wffnBundlesAeroAdmin && window.wffnBundlesAeroAdmin.packs ) || {};
	var EDIT = ( window.wffnBundlesAeroAdmin && window.wffnBundlesAeroAdmin.editUrl ) || '';

	if ( ! EDIT || ! Object.keys( MAP ).length ) {
		return;
	}

	/** The pack title this row shows, or '' when it shows none we know. */
	function packTitle( row ) {
		var el = row.querySelector( '.bwf-product-name' );
		var name = el ? el.textContent.trim() : '';

		return Object.prototype.hasOwnProperty.call( MAP, name ) ? name : '';
	}

	function retarget( root ) {
		var rows = root.querySelectorAll ? root.querySelectorAll( '[data-product-id]' ) : [];
		for ( var i = 0; i < rows.length; i++ ) {
			var row = rows[ i ];
			var name = packTitle( row );
			if ( ! name ) {
				continue;
			}
			var href = EDIT.replace( '%d', String( MAP[ name ] ) );
			var links = row.querySelectorAll( 'a[href*="post.php"]' );
			for ( var l = 0; l < links.length; l++ ) {
				if ( links[ l ].getAttribute( 'href' ) !== href ) {
					links[ l ].setAttribute( 'href', href );
				}
			}
		}
	}

	function start() {
		retarget( document );
		if ( ! window.MutationObserver ) {
			return;
		}
		var pending = false;
		new MutationObserver( function () {
			if ( pending ) {
				return;
			}
			// Coalesced: the app re-renders the whole list on every keystroke in it.
			pending = true;
			window.requestAnimationFrame( function () {
				pending = false;
				retarget( document );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
})();
