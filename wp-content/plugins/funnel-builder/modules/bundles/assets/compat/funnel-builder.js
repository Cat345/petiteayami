/**
 * Funnel Builder (Aero Checkout) — keep a row's bundle picks in step with Aero.
 *
 * Aero exposes its payloads on `wfacp_before_ajax_data_{action}`.
 */
(function () {
	'use strict';

	/**
	 * The requests a row's picks have to ride on.
	 *
	 * Aero adds a switcher row through `wfacp_addon_product` but updates an
	 * already-selected one through `wfacp_switch_product_addon`.
	 */
	var ACTIONS = [
		// Aero builds the suffix from its own action key, which for these two
		// endpoints carries no `wfacp_` prefix; both spellings are registered.
		'wfacp_before_ajax_data_switch_product_addon',
		'wfacp_before_ajax_data_addon_product',
		'wfacp_before_ajax_data_wfacp_switch_product_addon',
		'wfacp_before_ajax_data_wfacp_addon_product'
	];

	/** Aero's own "that update is applied" signal, both spellings of its name. */
	var ACKS = [
		'wfacp_ajax_update_variation_data',
		'wfacp_ajax_wfacp_update_variation_data'
	];

	/** The pick waiting for Aero to finish, or null. */
	var pending = null;

	/** Is a quick view holding our widget on screen, and was its Update pressed? */
	var qvOpen = false;
	var qvCommitted = false;

	/** One unit's chosen attributes, as { Color: 'Black', Size: 'XS' }. */
	function unitAttributes( unit ) {
		var attrs = {};
		var axes = unit.querySelectorAll( '.wffn-bundles-variant-axis' );
		for ( var i = 0; i < axes.length; i++ ) {
			// Option values are authored as "Attribute:Option" by Skins\Variants::axis().
			var parts = String( axes[ i ].value || '' ).split( ':' );
			if ( parts.length > 1 ) {
				attrs[ parts.shift() ] = parts.join( ':' );
			}
		}
		return attrs;
	}

	function ready() {
		if ( ! window.wfacp_frontend || ! wfacp_frontend.hooks ) {
			return;
		}
		watchAeroUpdates();
	}

	/**
	 * Hand the injected modal to the skin behavior layer.
	 *
	 * The quick view arrives over AJAX after the behavior modules have booted, so
	 * the widget inside it is inert. `wffn-bundles:rescan` is the re-init entry point
	 * behavior/index.js exposes for exactly this.
	 */
	function watchModal() {
		var host = document.querySelector( '.wfacp_qv-modal' );
		if ( ! host || ! window.MutationObserver ) {
			return;
		}
		new MutationObserver( function () {
			var widget = host.querySelector( '.wffn-bundles' );
			if ( ! widget || widget.dataset.wffnBundlesRescanned === '1' ) {
				return;
			}
			widget.dataset.wffnBundlesRescanned = '1';
			qvOpen = true;
			// Armed here, not at boot: the modal it watches is injected on open.
			discardPicksOnCancel();
			pinPriceTarget( host );
			document.dispatchEvent( new CustomEvent( 'wffn-bundles:rescan', { detail: { root: host } } ) );
			syncNativeVariation();
		} ).observe( host, { childList: true, subtree: true } );
	}

	/**
	 * Name the element the live quote should write into.
	 *
	 * The behavior layer looks for `[data-wffn-bundles-price]` first and then hunts `p.price`
	 * inside a product container, which never finds Aero's modal price. Pinning it
	 * is the documented override for exactly this case.
	 *
	 * @param {HTMLElement} host The modal container.
	 */
	function pinPriceTarget( host ) {
		if ( host.querySelector( '[data-wffn-bundles-price]' ) ) {
			return;
		}
		var price = host.querySelector( '.wfacp_qv-summary p.price' ) || host.querySelector( 'p.price' );
		if ( price ) {
			price.setAttribute( 'data-wffn-bundles-price', '' );
		}
	}

	/** The native select for one attribute name, or null. */
	function nativeAxis( form, name ) {
		var slug = String( name ).toLowerCase().replace( /\s+/g, '-' );
		return form.querySelector( 'select[name="attribute_' + slug + '"]' )
			|| form.querySelector( 'select[name="attribute_pa_' + slug + '"]' );
	}

	/**
	 * Drive the native variation form from a set's host.
	 *
	 * Aero labels its add button from WooCommerce's `show_variation` /
	 * `hide_variation` events, so writing `variation_id` is not enough: the native
	 * selects have to resolve. The host's picks stand for the row.
	 */
	function syncNativeVariation() {
		var modal = document.getElementById( 'wfacp_qr_model_wrap' );
		var form = modal && modal.querySelector( '.variations_form' );
		var host = modal ? modal.querySelector( '.wffn-bundles-fbt-item[data-wffn-bundles-key="host"]' ) : null;
		if ( ! form || ! host ) {
			return;
		}
		var attrs = unitAttributes( host );
		var touched = false;
		Object.keys( attrs ).forEach( function ( name ) {
			var sel = nativeAxis( form, name );
			if ( ! sel || sel.value === attrs[ name ] ) {
				return;
			}
			// Set only, no `change` event: these selects sit inside form.checkout, so a
			// bubbling change triggers update_checkout and the modal visibly fades.
			sel.value = attrs[ name ];
			touched = true;
		} );
		if ( touched && window.jQuery ) {
			// WooCommerce recomputes the match — and Aero relabels the button — off
			// this, not off the change events above.
			window.jQuery( form ).trigger( 'check_variations' );
		}
	}


	/**
	 * Keep the bundle card's own headline in step with the quote.
	 *
	 * The skin re-stamps each member line from the quote but not the card's own
	 * price, which the server renders exactly on a product page. The modal renders
	 * before any variation is resolved, so the card would keep a range.
	 */
	function mirrorCardPrice() {
		var api = window.wffnBundlesSkins;
		if ( ! api || ! api.bus || ! api.bus.addEventListener ) {
			return;
		}
		api.bus.addEventListener( 'wffn-bundles:price-quoted', function ( e ) {
			var modal = document.getElementById( 'wfacp_qr_model_wrap' );
			var d = e && e.detail;
			if ( ! modal || ! d || ! d.widget || ! modal.contains( d.widget ) ) {
				return;
			}
			var data = d.data;
			if ( ! data || ! data.hasItems || ! data.html ) {
				return;
			}
			var card = d.widget.querySelector( '.wffn-bundles-fb-card' );
			if ( ! card ) {
				return;
			}
			// The quote's markup carries <del> original and <ins>/bare final; read
			// them off a detached node rather than re-deriving the money here.
			var box = document.createElement( 'span' );
			box.innerHTML = data.html;
			var del = box.querySelector( 'del' );
			var ins = box.querySelector( 'ins' );
			var fin = card.querySelector( '.wffn-bundles-fb-price-final' );
			var org = card.querySelector( '.wffn-bundles-fb-price-original' );
			if ( fin ) {
				fin.innerHTML = ins ? ins.innerHTML : box.innerHTML;
			}
			if ( org && del ) {
				org.innerHTML = del.innerHTML;
			}
		} );
	}


	/**
	 * Rebuild the row after the quick view's Update.
	 *
	 * Aero's Update refreshes an already-carted row rather than re-adding it, so
	 * the cart keeps the variations the pack was first built with. Re-triggering
	 * the row's own checkbox puts Aero through its rebuild.
	 */
	function rebuildRowAfterUpdate() {
		var openedFrom = null;

		document.addEventListener( 'click', function ( e ) {
			var qv = e.target.closest && e.target.closest( '.wfacp_qv-button' );
			if ( qv ) {
				var row = qv.closest( '.wfacp_product_row' );
				openedFrom = row ? row.querySelector( '.wfacp_switcher_checkbox' ) : null;
				return;
			}
			var update = e.target.closest && e.target.closest( '#wfacp_qr_model_wrap .wfacp_single_add_to_cart_button' );
			if ( ! update ) {
				return;
			}
			// Set before the openedFrom guard: an Update is a commitment whether or
			// not the row it came from is still resolvable.
			qvCommitted = true;
			if ( ! openedFrom ) {
				return;
			}
			var modal = document.getElementById( 'wfacp_qr_model_wrap' );
			if ( ! modal ) {
				return;
			}
			// A pack row: its members are re-pointed from the picks the modal just
			// quoted, rather than through a full Aero rebuild.
			if ( modal.querySelector( '.wffn-bundles-fb' ) && openedFrom.checked ) {
				queueJob( openedFrom, 'pack', null );
				return;
			}
			// A set row: its companions are re-placed from the modal's ticks, once Aero
			// has placed the row (an add) or kept it (an update).
			var set = modal.querySelector( '.wffn-bundles-fbt' );
			if ( set && window.wffnBundlesSkins && window.wffnBundlesSkins.fbtSelection ) {
				queueJob( openedFrom, 'set', window.wffnBundlesSkins.fbtSelection( set, true ) );
			}
		}, true );
	}

	/**
	 * Hold the row's pick until Aero has finished writing its own line.
	 *
	 * Aero rewrites the row's single line on the same click and whichever request
	 * lands second survives. `wfacp_ajax_update_variation_data` fires once Aero's
	 * response has been applied, so there is nothing left to race.
	 *
	 * @param {HTMLElement|null} input     The row's own control, for its key.
	 * @param {string}           kind      'pack' for a bundle, 'set' for a bought-together set.
	 * @param {Object|null}      selection A set's selection, or null.
	 */
	function queueJob( input, kind, selection ) {
		pending = { input: input, kind: kind, selection: selection };
		// Aero's action never firing must not strand the pick, so the wait has an
		// end; long enough that it is the fallback and not the usual path.
		window.setTimeout( sendJob, 6000 );
	}

	/** Register the acknowledgements that release a queued pick. */
	function watchAeroUpdates() {
		ACKS.forEach( function ( name ) {
			wfacp_frontend.hooks.addAction( name, sendJob, 10, 'wffn-bundles' );
		} );
	}

	/**
	 * Send the queued pick to our own endpoint, once.
	 *
	 * Only for a row already in the cart, where Aero's own update rebuilds the row
	 * wholesale.
	 */
	function sendJob() {
		if ( ! pending ) {
			return;
		}
		var job = pending;
		pending = null;

		var cfg = window.wffnBundlesCart || {};
		var row = job.input && job.input.closest ? job.input.closest( '.wfacp_product_row' ) : null;
		var key = row ? row.getAttribute( 'data-item-key' ) : '';
		var page = ( window.wfacp_frontend && wfacp_frontend.wfacp_id ) || document.querySelector( 'input[name="_wfacp_post_id"]' );
		var pageId = page && page.value ? page.value : page;
		if ( ! cfg.url || ! key || ! pageId ) {
			return;
		}
		var action = { pack: 'wffn_bundles_aero_pack', set: 'wffn_bundles_aero_set' }[ job.kind ];
		if ( ! action ) {
			return;
		}
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce || '' );
		body.set( 'item_key', key );
		body.set( 'wfacp_id', String( pageId ) );
		if ( job.selection ) {
			body.set( 'selection', JSON.stringify( job.selection ) );
		}

		window.fetch( endpoint( job.kind, body ), { method: 'POST', credentials: 'same-origin', body: body } )
			.then( refreshTotals )
			.catch( function () {} );
	}

	/**
	 * Tell the server a quick view was closed without confirming.
	 *
	 * The quote stages its member picks server-side, because Aero's switcher
	 * rebuilds the row from the session rather than from the modal. Without this
	 * the row kept re-pricing for a variation the shopper had backed out of.
	 *
	 * Watched through the close gestures rather than the modal's own markup: Aero
	 * owns that, and the id it is found by is the only part of it assumed here.
	 */
	function discardPicksOnCancel() {
		var wrap = document.getElementById( 'wfacp_qr_model_wrap' );
		var cfg = window.wffnBundlesCart || {};
		if ( ! wrap || ! wrap.parentNode || ! cfg.url || ! window.MutationObserver ) {
			return;
		}

		// Aero takes the modal out of the DOM to close it, on every route out —
		// Escape, the X and the backdrop alike. Watched rather than read off those
		// gestures: the close is deferred past the frame they happen in, so a check
		// on the gesture itself still found the modal on screen and let a cancelled
		// pick stand.
		var obs = new MutationObserver( function () {
			if ( document.getElementById( 'wfacp_qr_model_wrap' ) ) {
				return;
			}
			obs.disconnect();
			qvOpen = false;
			if ( qvCommitted ) {
				qvCommitted = false;
				return;
			}
			var body = new URLSearchParams();
			body.set( 'action', 'wffn_bundles_aero_cancel' );
			body.set( 'nonce', cfg.nonce || '' );
			// Fire and forget: the row already shows what it was built from.
			window.fetch( endpoint( 'cancel', body ), { method: 'POST', credentials: 'same-origin', body: body } );
		} );
		obs.observe( wrap.parentNode, { childList: true } );
	}

	/** The row's quick view is where a pack's variations are chosen. */
	function wireChooseLinks() {
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest && e.target.closest( '.wffn-bundles-row-choose' );
			if ( ! link ) {
				return;
			}
			e.preventDefault();
			var row = link.closest( '.wfacp_product_row' );
			var qv = row && row.querySelector( '.wfacp_qv-button' );
			if ( qv ) {
				qv.click();
			}
		} );
	}

	/** Matches once, read on every flip rather than queried per row. */
	var REDUCED_MOTION = window.matchMedia
		? window.matchMedia( '(prefers-reduced-motion: reduce)' )
		: { matches: false };

	/**
	 * Switcher rows' positions the instant before a fragment refresh, keyed by
	 * the item key that survives the swap. Aero replaces the whole panel's
	 * markup in one go, so a row whose neighbour's add-ons collapsed or grew
	 * lands at a different `top` with no transition of its own — it just snaps.
	 * These are the "First" half of a FLIP: recorded here, played in
	 * playRowFlip() once the new markup is in.
	 */
	var flipRects = null;

	/** Note every switcher row's position, just before Aero swaps the panel. */
	function captureRowFlip() {
		var panel = document.getElementById( 'product_switching_field' );
		if ( ! panel ) {
			return;
		}
		var rows = panel.querySelectorAll( '.wfacp_product_row[data-item-key]' );
		var rects = {};
		for ( var i = 0; i < rows.length; i++ ) {
			rects[ rows[ i ].getAttribute( 'data-item-key' ) ] = rows[ i ].getBoundingClientRect().top;
		}
		flipRects = rects;
	}

	/**
	 * Slide every switcher row from where it was to where the refresh put it.
	 *
	 * Each row is offset by the distance it silently jumped, then transitioned
	 * back to zero — the row itself never moved, only the illusion of motion
	 * was added, so this costs nothing when nothing shifted.
	 */
	function playRowFlip() {
		var rects = flipRects;
		flipRects = null;
		if ( ! rects ) {
			return;
		}
		var panel = document.getElementById( 'product_switching_field' );
		if ( ! panel ) {
			return;
		}
		var rows = panel.querySelectorAll( '.wfacp_product_row[data-item-key]' );
		for ( var i = 0; i < rows.length; i++ ) {
			( function ( row ) {
				var before = rects[ row.getAttribute( 'data-item-key' ) ];
				if ( undefined === before ) {
					return;
				}
				var delta = before - row.getBoundingClientRect().top;
				if ( Math.abs( delta ) < 1 ) {
					return;
				}
				row.style.transition = 'none';
				row.style.transform = 'translateY(' + delta + 'px)';
				// Force the jump above to paint before the transition below is
				// applied, or the browser folds the two into one and nothing animates.
				void row.offsetHeight;
				row.style.transition = 'transform 0.32s ease';
				row.style.transform = '';
				row.addEventListener( 'transitionend', function cleanup() {
					row.removeEventListener( 'transitionend', cleanup );
					row.style.transition = '';
					row.style.transform = '';
				} );
			} )( rows[ i ] );
		}
	}

	/**
	 * Keep the switcher's own on/off look for its controls while a row switch reloads.
	 *
	 * The template sets those colours, so they are read off the live controls and
	 * handed to the stylesheet. Read at rest: a click starts the control's own transition.
	 */
	function pinSwitchControls() {
		function pin() {
			var panel = document.getElementById( 'product_switching_field' );
			if ( ! panel || panel.classList.contains( 'wfacp_animation_start' ) ) {
				return;
			}
			[ [ 'on', ':checked' ], [ 'off', ':not(:checked)' ] ].forEach( function ( pair ) {
				var input = panel.querySelector( 'input.wfacp_switcher_checkbox' + pair[ 1 ] );
				if ( ! input ) {
					return;
				}
				var c = window.getComputedStyle( input );
				// On the root: Aero replaces the panel on every refresh.
				document.documentElement.style.setProperty( '--wffn-bundles-switch-' + pair[ 0 ] + '-border', c.borderTopWidth + ' ' + c.borderTopStyle + ' ' + c.borderTopColor );
				document.documentElement.style.setProperty( '--wffn-bundles-switch-' + pair[ 0 ] + '-bg', c.backgroundColor );
			} );
		}

		pin();
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout', pin );
		}
	}

	/**
	 * Hand the checkout's own ticked-control colour to a set's ticks.
	 *
	 * Aero prints it as `:checked` rules with no custom property: a picked switcher
	 * control carries it as its border, so it is read off one of those.
	 */
	function pinFunnelCheck() {
		function pin() {
			var colour = '';
			var picked = document.querySelector( '#product_switching_field input.wfacp_switcher_checkbox:checked' );
			if ( picked ) {
				colour = window.getComputedStyle( picked ).borderTopColor;
			}
			if ( colour && 'transparent' !== colour && 'rgba(0, 0, 0, 0)' !== colour ) {
				document.documentElement.style.setProperty( '--wffn-bundles-funnel-check', colour );
			}
		}

		pin();
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout', pin );
		}
	}

	/**
	 * Fade a bundle's quick view out rather than let it vanish.
	 *
	 * Aero's close hides the panel and empties it in the same tick, so a copy is
	 * taken before its handler runs and faded out in the panel's place.
	 */
	function fadeQuickViewClose() {
		var ghosts = [];

		function clear() {
			while ( ghosts.length ) {
				var g = ghosts.pop();
				if ( g.parentNode ) {
					g.parentNode.removeChild( g );
				}
			}
		}

		// cloneNode copies attributes, not what the shopper picked since.
		function copyState( from, to ) {
			var a = from.querySelectorAll( 'select, input' );
			var b = to.querySelectorAll( 'select, input' );
			for ( var i = 0; i < a.length && i < b.length; i++ ) {
				if ( 'SELECT' === a[ i ].tagName ) {
					b[ i ].selectedIndex = a[ i ].selectedIndex;
				} else {
					b[ i ].checked = a[ i ].checked;
				}
			}
		}

		function snapshot() {
			var panel = document.querySelector( '.wfacp_qv-panel.wfacp_qv-panel-active' );
			if ( REDUCED_MOTION.matches || ! panel || ! panel.querySelector( '#wfacp_qr_model_wrap .wffn-bundles' ) ) {
				return;
			}
			var shade = document.querySelector( '.wfacp_qv-opac' );
			var parts = [ shade, panel ].filter( Boolean ).map( function ( el ) {
				var copy = el.cloneNode( true );
				copyState( el, copy );
				// A fresh insert would replay the modal's own fade-in.
				var wrap = copy.querySelector( '#wfacp_qr_model_wrap' );
				if ( wrap ) {
					wrap.style.transition = 'none';
					wrap.style.animation = 'none';
					wrap.style.opacity = '1';
				}
				return { el: el, copy: copy };
			} );
			var main = panel.querySelector( '.wfacp_qv-main' );
			var scrollTop = main ? main.scrollTop : 0;
			// Aero may refuse the close (an unchosen variation); only a closed panel is faded.
			window.requestAnimationFrame( function () {
				if ( panel.classList.contains( 'wfacp_qv-panel-active' ) ) {
					return;
				}
				clear();
				parts.forEach( function ( p ) {
					p.copy.setAttribute( 'aria-hidden', 'true' );
					p.copy.style.pointerEvents = 'none';
					p.copy.style.transition = 'opacity 0.18s ease';
					// After the original, so lookups by id or class still find Aero's own.
					p.el.parentNode.insertBefore( p.copy, p.el.nextSibling );
					ghosts.push( p.copy );
				} );
				var copyMain = parts[ parts.length - 1 ].copy.querySelector( '.wfacp_qv-main' );
				if ( copyMain ) {
					copyMain.scrollTop = scrollTop;
				}
				// Styled at full opacity first, so the fade has a start to run from.
				void document.body.offsetHeight;
				ghosts.forEach( function ( g ) {
					g.style.opacity = '0';
				} );
				window.setTimeout( clear, 220 );
			} );
		}

		document.addEventListener( 'click', function ( e ) {
			var t = e.target;
			if ( ! t || ! t.closest ) {
				return;
			}
			if ( t.closest( '.wfacp_qv-button' ) ) {
				clear();
				return;
			}
			if ( t.closest( '.wfacp_qv-close, .wfacp_single_add_to_cart_button' ) || ( t.classList && t.classList.contains( 'wfacp_qv-inner-modal' ) ) ) {
				snapshot();
			}
		}, true );

		document.addEventListener( 'keyup', function ( e ) {
			if ( 'Escape' === e.key ) {
				snapshot();
			}
		}, true );
	}

	/**
	 * Ask WooCommerce for fresh totals once a pick has been stored.
	 *
	 * The add-on lines are reconciled server-side by the endpoint that stored the
	 * pick, so nothing here has to make Aero rebuild the row: a change re-raised on
	 * an already-selected row makes Aero drop the row and its offer.
	 */
	function refreshTotals() {
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		}
	}

	/**
	 * Where to post one of our checkout actions.
	 *
	 * WooCommerce's `wc-ajax` endpoint answers from `template_redirect` without
	 * booting the admin, which `admin-ajax` does on every call. The action rides
	 * the query string there, so each one has its own URL; `wffnBundlesCart.url` is the fallback.
	 *
	 * @param {string}          name Short key: 'pack', 'set' or 'cancel'.
	 * @param {URLSearchParams} body Params to send; loses `action` on the fast path.
	 * @return {string} The URL to post to.
	 */
	function endpoint( name, body ) {
		var fast = ( window.wffnBundlesAeroAddons || {} ).endpoints || {};
		if ( fast[ name ] ) {
			body.delete( 'action' );
			return fast[ name ];
		}
		return ( window.wffnBundlesCart || {} ).url || '';
	}

	/** Rebind the switcher Aero swaps in, and slide its rows into their new places. */
	function rowRefresh() {
		// Aero swaps the whole switcher in on this event, so the fresh nodes need
		// the skin behavior bound to them again.
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'wfacp_after_fragment_received updated_checkout', function () {
				document.dispatchEvent( new CustomEvent( 'wffn-bundles:rescan', { detail: { root: document } } ) );
				if ( ! REDUCED_MOTION.matches ) {
					playRowFlip();
				}
			} );
		}

		document.addEventListener( 'change', function ( e ) {
			var target = e.target;
			if ( target.classList && target.classList.contains( 'wfacp_switcher_checkbox' ) && ! REDUCED_MOTION.matches ) {
				captureRowFlip();
			}
		} );
	}

	/**
	 * A Fixed Bundle pack row's own members, each with its own inline picker.
	 *
	 * Changing one member's axes quotes the whole pack — Cart::ajax_quote stages
	 * the pick server-side (Adapter::remember_row_picks) as a side effect of
	 * pricing it — then commits it through the same wffn_bundles_aero_pack the quick
	 * view's own Update already posts to, so a picked member survives a row
	 * refresh exactly like a quick-view pick does.
	 */
	function wirePackMemberAxes() {
		// One pack re-quote at a time: the quote stages the picks server-side, so two
		// in flight could land out of order and leave the older pick committed.
		var busy = false;
		var again = null;

		function run( ctx ) {
			if ( busy ) {
				again = ctx;
				return;
			}
			busy = true;
			var done = function () {
				busy = false;
				if ( again ) {
					var next = again;
					again = null;
					run( next );
				}
			};
			commitPack( ctx ).then( done, done );
		}

		document.addEventListener( 'change', function ( e ) {
			var axis = e.target;
			if ( ! axis.classList || ! axis.classList.contains( 'wffn-bundles-pack-axis' ) ) {
				return;
			}
			var wrap = axis.closest( '.wffn-bundles-aero-pack-members' );
			var row = axis.closest( '.wfacp_product_row' );
			if ( ! wrap || ! row || ! window.wffnBundlesSkins || ! window.wffnBundlesSkins.quote ) {
				return;
			}
			var itemKey = row.getAttribute( 'data-item-key' );
			var page = ( window.wfacp_frontend && wfacp_frontend.wfacp_id ) || document.querySelector( 'input[name="_wfacp_post_id"]' );
			var pageId = page && page.value ? page.value : page;
			if ( ! itemKey || ! pageId ) {
				return;
			}
			var ctx = { wrap: wrap, itemKey: itemKey, pageId: pageId };
			// The refresh redraws the row, which would take an open options popup with it.
			var popup = axis.closest( '.wffn-bundles-variant--open' );
			if ( popup ) {
				popup._wffnBundlesPackPending = ctx;
				return;
			}
			run( ctx );
		} );

		// Capture phase: the popup's own close handler stops the click bubbling.
		document.addEventListener( 'click', function ( e ) {
			var close = e.target.closest && e.target.closest( '[data-act=vclose]' );
			var popup = close && close.closest( '.wffn-bundles-variant--open' );
			if ( ! popup || ! popup._wffnBundlesPackPending ) {
				return;
			}
			var ctx = popup._wffnBundlesPackPending;
			popup._wffnBundlesPackPending = null;
			window.setTimeout( function () {
				run( ctx );
			}, 0 );
		}, true );
	}

	/**
	 * Quote a pack from its members' current picks, then commit it.
	 *
	 * Read when it runs, not when the change fired, so a queued run sends the
	 * latest picks.
	 *
	 * @param {Object} ctx { wrap, itemKey, pageId }.
	 * @return {Promise} Settles once the commit has been sent.
	 */
	function commitPack( ctx ) {
		var members = ctx.wrap.querySelectorAll( '.wffn-bundles-aero-pack-member' );
		var items = [];
		for ( var i = 0; i < members.length; i++ ) {
			var attrs = {};
			var selects = members[ i ].querySelectorAll( '.wffn-bundles-pack-axis' );
			for ( var s = 0; s < selects.length; s++ ) {
				// Option values are authored as "Attribute:Option" by Skins\Variants::axis().
				var parts = String( selects[ s ].value || '' ).split( ':' );
				if ( parts.length > 1 ) {
					attrs[ parts.shift() ] = parts.join( ':' );
				}
			}
			items.push( { attributes: attrs } );
		}

		return window.wffnBundlesSkins.quote( {
			bundle: ctx.wrap.getAttribute( 'data-pack-bundle' ) || '',
			type: 'fixed_bundles',
			offer: ctx.wrap.getAttribute( 'data-pack-offer' ) || '',
			selection: { items: items },
		} ).then( function ( data ) {
			// An incomplete member (an axis still on "Choose an option") quotes
			// {hasItems:false} — nothing to commit until every axis resolves.
			if ( ! data || ! data.hasItems ) {
				return;
			}
			var cfg = window.wffnBundlesCart || {};
			var body = new URLSearchParams();
			body.set( 'action', 'wffn_bundles_aero_pack' );
			body.set( 'nonce', cfg.nonce || '' );
			body.set( 'item_key', ctx.itemKey );
			body.set( 'wfacp_id', String( ctx.pageId ) );

			return window.fetch( endpoint( 'pack', body ), { method: 'POST', credentials: 'same-origin', body: body } )
				.then( refreshAndWait )
				.catch( function () {} );
		} );
	}

	/**
	 * Refresh the checkout and settle once it has redrawn.
	 *
	 * The refresh saves the whole session, cart included, so the next write must
	 * not start until it has, or the refresh puts the older cart back.
	 *
	 * @return {Promise}
	 */
	function refreshAndWait() {
		return new Promise( function ( resolve ) {
			if ( ! window.jQuery ) {
				resolve();
				return;
			}
			window.jQuery( document.body ).one( 'updated_checkout', resolve );
			window.setTimeout( resolve, 8000 );
			refreshTotals();
		} );
	}


	/**
	 * Tick a set row's companions and pick their options in the row itself.
	 *
	 * A carted row re-places its companions at once; an uncarted one carries its
	 * ticks on the add that selects it. The ticks are kept per row and put back on
	 * every redraw, so a redraw from an earlier change never undoes a later one.
	 */
	function wireSetMembers() {
		var kept = {};
		var busy = false;
		var again = '';

		function rowFor( key ) {
			return document.querySelector( '.wfacp_product_row[data-item-key="' + key + '"]' );
		}

		function remember( list, key ) {
			var state = {};
			var members = list.querySelectorAll( '.wffn-bundles-aero-set-member[data-set-key]' );
			for ( var i = 0; i < members.length; i++ ) {
				var box = members[ i ].querySelector( '.wffn-bundles-aero-set-check' );
				var axes = members[ i ].querySelectorAll( '.wffn-bundles-variant-axis' );
				var values = [];
				for ( var j = 0; j < axes.length; j++ ) {
					values.push( axes[ j ].value );
				}
				state[ members[ i ].getAttribute( 'data-set-key' ) ] = { on: !! ( box && box.checked ), axes: values };
			}
			kept[ key ] = state;
		}

		// A ticked companion still missing an option joins once it is picked.
		function selection( key ) {
			var state = kept[ key ] || {};
			var row = rowFor( key );
			var items = [];
			Object.keys( state ).forEach( function ( id ) {
				var member = row && row.querySelector( '.wffn-bundles-aero-set-member[data-set-key="' + id + '"]' );
				var complete = ! window.wffnBundlesSkins || ! member || window.wffnBundlesSkins.axesComplete( member );
				if ( state[ id ].on && complete ) {
					items.push( { id: id, attributes: member ? unitAttributes( member ) : {} } );
				}
			} );
			return { items: items };
		}

		function restore() {
			Object.keys( kept ).forEach( function ( key ) {
				var row = rowFor( key );
				var list = row && row.querySelector( '.wffn-bundles-aero-set-members' );
				if ( ! list ) {
					return;
				}
				Object.keys( kept[ key ] ).forEach( function ( id ) {
					var member = list.querySelector( '.wffn-bundles-aero-set-member[data-set-key="' + id + '"]' );
					var box = member && member.querySelector( '.wffn-bundles-aero-set-check' );
					if ( ! box ) {
						return;
					}
					box.checked = kept[ key ][ id ].on;
					member.classList.toggle( 'is-off', ! box.checked );
					var axes = member.querySelectorAll( '.wffn-bundles-variant-axis' );
					for ( var j = 0; j < axes.length && j < kept[ key ][ id ].axes.length; j++ ) {
						axes[ j ].value = kept[ key ][ id ].axes[ j ];
					}
				} );
			} );
		}

		function commit( key ) {
			if ( busy ) {
				again = key;
				return;
			}
			var cfg = window.wffnBundlesCart || {};
			var page = ( window.wfacp_frontend && wfacp_frontend.wfacp_id ) || document.querySelector( 'input[name="_wfacp_post_id"]' );
			var pageId = page && page.value ? page.value : page;
			if ( ! cfg.url || ! pageId ) {
				return;
			}
			busy = true;
			var body = new URLSearchParams();
			body.set( 'action', 'wffn_bundles_aero_set' );
			body.set( 'nonce', cfg.nonce || '' );
			body.set( 'item_key', key );
			body.set( 'wfacp_id', String( pageId ) );
			body.set( 'selection', JSON.stringify( selection( key ) ) );
			var done = function () {
				busy = false;
				if ( again ) {
					var next = again;
					again = '';
					commit( next );
				}
			};
			window.fetch( endpoint( 'set', body ), { method: 'POST', credentials: 'same-origin', body: body } )
				.then( refreshAndWait )
				.then( done, done );
		}

		document.addEventListener( 'change', function ( e ) {
			var t = e.target;
			var list = t && t.closest ? t.closest( '.wffn-bundles-aero-set-members' ) : null;
			var row = list && list.closest( '.wfacp_product_row' );
			if ( ! row || ! ( t.classList.contains( 'wffn-bundles-aero-set-check' ) || t.classList.contains( 'wffn-bundles-variant-axis' ) ) ) {
				return;
			}
			var member = t.closest( '.wffn-bundles-aero-set-member' );
			if ( member && t.classList.contains( 'wffn-bundles-aero-set-check' ) ) {
				member.classList.toggle( 'is-off', ! t.checked );
			}
			var key = row.getAttribute( 'data-item-key' );
			remember( list, key );
			var picked = row.querySelector( 'input.wfacp_switcher_checkbox' );
			if ( picked && picked.checked ) {
				commit( key );
			}
		} );

		// The row's own control would otherwise read a tick inside it as picking the row.
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( '.wffn-bundles-aero-set-members' ) ) {
				e.stopPropagation();
			}
		} );

		ACTIONS.forEach( function ( action ) {
			wfacp_frontend.hooks.addFilter( action, function ( payload ) {
				var key = payload && payload.data ? payload.data.new_item || payload.data.item_key || '' : '';
				if ( key && kept[ key ] ) {
					payload.data.wffn_bundles_set = JSON.stringify( selection( key ) );
				}
				return payload;
			}, 10, 'wffn-bundles' );
		} );

		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout wfacp_after_fragment_received', restore );
		}
	}

	function start() {
		ready();
		watchModal();
		mirrorCardPrice();
		rebuildRowAfterUpdate();
		wireChooseLinks();
		rowRefresh();
		pinSwitchControls();
		pinFunnelCheck();
		fadeQuickViewClose();
		wirePackMemberAxes();
		if ( window.wfacp_frontend && wfacp_frontend.hooks ) {
			wireSetMembers();
		}
		// A host axis can change which variation the row resolves to.
		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'wffn-bundles-variant-axis' ) ) {
				syncNativeVariation();
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
})();
