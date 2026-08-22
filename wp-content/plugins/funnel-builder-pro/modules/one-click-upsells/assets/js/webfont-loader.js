/**
 * Lightweight Google Fonts loader for the block editors.
 *
 * Exposes window.WebFont with a load() method that accepts
 * { google: { families: [ 'Family:weights', ... ] } } and injects the
 * matching Google Fonts stylesheet once per family.
 */
( function ( window, document ) {
	'use strict';

	if ( window.WebFont ) {
		return;
	}

	var requested = {};

	window.WebFont = {
		load: function ( config ) {
			var families = config && config.google && config.google.families;
			if ( ! families || ! families.length ) {
				return;
			}

			var fresh = [];
			for ( var i = 0; i < families.length; i++ ) {
				// Callers pass either "Open Sans:400" or the pre-encoded "Open+Sans:400".
				var family = String( families[ i ] || '' ).replace( /\s/g, '+' );
				if ( family && ! requested[ family ] ) {
					requested[ family ] = true;
					fresh.push( family );
				}
			}

			if ( ! fresh.length ) {
				return;
			}

			var link  = document.createElement( 'link' );
			link.rel  = 'stylesheet';
			link.href = 'https://fonts.googleapis.com/css?family=' + fresh.join( '|' );
			document.head.appendChild( link );
		},
	};
} )( window, document );
