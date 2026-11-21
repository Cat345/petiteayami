/**
 * Order edit screen: a Fixed Bundle member's share of its bundle.
 *
 * A member's line is stored at 0, so the Cost and Total cells show its share
 * instead, greyed, and the refund form fills that share in once the core
 * handler has run. Figures come from the line's `.wffn-bundles-share` block.
 */
( function ( $ ) {
	'use strict';

	function amount( value ) {
		return parseFloat( accounting.formatNumber( value, woocommerce_admin_meta_boxes.currency_format_num_decimals, '' ) )
			.toString()
			.replace( '.', woocommerce_admin.mon_decimal_point );
	}

	function fill( $view, html ) {
		var $amount = $view.children( '.woocommerce-Price-amount' ).first();
		if ( ! $amount.length || ! html ) {
			return;
		}
		$amount.replaceWith( $( '<span class="wffn-bundles-share-in"></span>' ).html( html ) );
	}

	function showShares() {
		$( '#woocommerce-order-items tr.item' ).each( function () {
			var $row = $( this );
			var $share = $row.find( '.wffn-bundles-share' );
			if ( ! $share.length || ! $share.attr( 'data-line' ) || $row.find( '.wffn-bundles-share-in' ).length ) {
				return;
			}
			fill( $row.find( 'td.item_cost .view' ), $share.attr( 'data-unit' ) );
			fill( $row.find( 'td.line_cost .view' ), $share.attr( 'data-line' ) );
			$share.hide();
		} );
	}

	function packRefunding( group ) {
		var on = false;
		$( '#woocommerce-order-items .wffn-bundles-pack' ).each( function () {
			if ( $( this ).attr( 'data-group' ) === group && ( parseFloat( $( this ).closest( 'tr.item' ).find( 'input.refund_order_item_qty' ).val() ) || 0 ) > 0 ) {
				on = true;
			}
		} );
		return on;
	}

	function fillShare( $row ) {
		var $share = $row.find( '.wffn-bundles-share' );
		if ( ! $share.length ) {
			return;
		}
		var group = $share.attr( 'data-group' ) || '';
		var lineQty = parseFloat( $share.attr( 'data-qty' ) ) || 1;
		var ratio = Math.min( Math.max( parseFloat( $row.find( 'input.refund_order_item_qty' ).val() ) || 0, 0 ), lineQty ) / lineQty;
		// The pack's own refund already returns its members' money.
		if ( group && packRefunding( group ) ) {
			ratio = 0;
		}
		var taxes = JSON.parse( $share.attr( 'data-tax' ) || '{}' );

		$row.find( '.refund_line_tax' ).each( function () {
			var id = $( this ).data( 'tax_id' );
			if ( undefined !== taxes[ id ] ) {
				$( this ).val( amount( taxes[ id ] * ratio ) ).trigger( 'change' );
			}
		} );
		$row.find( 'input.refund_line_total' ).val( amount( parseFloat( $share.attr( 'data-share' ) ) * ratio ) ).trigger( 'change' );
	}

	$( document ).on( 'change', '#woocommerce-order-items input.refund_order_item_qty', function () {
		var $row = $( this ).closest( 'tr.item' );
		fillShare( $row );
		var group = $row.find( '.wffn-bundles-pack' ).attr( 'data-group' );
		if ( ! group ) {
			return;
		}
		$( '#woocommerce-order-items .wffn-bundles-share' ).each( function () {
			var $member = $( this ).closest( 'tr.item' );
			if ( $( this ).attr( 'data-group' ) === group && ( parseFloat( $member.find( 'input.refund_order_item_qty' ).val() ) || 0 ) > 0 ) {
				fillShare( $member );
			}
		} );
	} );

	$( function () {
		showShares();
		// The item list is replaced after each save or reload.
		var box = document.getElementById( 'woocommerce-order-items' );
		if ( box && window.MutationObserver ) {
			new MutationObserver( showShares ).observe( box, { childList: true, subtree: true } );
		}
	} );
}( jQuery ) );
