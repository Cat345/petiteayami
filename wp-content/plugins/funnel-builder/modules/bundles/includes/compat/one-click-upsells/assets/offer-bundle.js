/**
 * OCU offer-page bridge — Fixed Bundle selection, and mirroring the widget's
 * own live price into OCU's static offer-price display. Another script can
 * teach it a widget kind of its own through `window.wffnBundlesOcuKinds`:
 * { selector, events, selection(widget, scope), price(widget), sync?(widget, scope), priceHost?, nativePick?(widget) }.
 *
 * A Fixed Bundle with 2+ members needs the shopper's per-member variation
 * picks to reach the server, the same way fixed-bundle.js's own collect()
 * builds them for a normal add-to-cart. There is no
 * add-to-cart button here (button_mode 'never' — OCU's own Accept button
 * owns the submit), so nothing ever calls the widget's own real collect().
 * This mirrors just the read half of it — window.wffnBundlesSkins.readAttributes()
 * and window.wffnBundlesSkins.selectionExtras(), the plugin's own public utilities, not
 * reimplemented here.
 *
 * OCU's own accept click handler (wfocu-public.min.js: bindAcceptButton)
 * NEVER serialized our `form.cart.wffn-bundles-ocu-form` on its own — it only reads
 * two form shapes it recognises by class: `form.wfocu_variation_selector_form`
 * (a SINGLE, always-present, visually-hidden form OCU itself prints once per
 * offer product, but ONLY for a variable anchor — a simple product gets no
 * such form at all) or `form.wfocu_attributes_selector_form`. adapter.php's
 * picker_markup() now stamps that second class (plus the matching `data-key`)
 * onto our own form directly, so OCU serializes it like any other recognised
 * form — confirmed live as the only thing that gets wffn_bundles_selection/
 * wffn_bundles_bundle_id and the other offer fields into $_POST at all for a simple anchor, since there
 * is no native form to fall back on there. The one thing that class doesn't
 * touch is `_wfocu_variation` (only the native variation form supplies OCU's
 * own gate/price lookup for a variable anchor) — see
 * syncNativeVariationField().
 *
 * The price mirror hides `.wfocu_price_wrapper[data-key]` and inserts a live
 * sibling next to it, reusing WFFN_Bundles' own already-correct display
 * (`.wffn-bundles-fb-price-final` on the selected option) rather than re-deriving a
 * total.
 *
 * An offer that repeats its whole CTA block (this plugin's own demo offers
 * do — one per "Yes, add to my order" button down the page) renders the
 * SAME product, and therefore the SAME `data-wffn-bundles-offer-key`, in 2-3 separate
 * widget instances, all pointed at the one shared OCU price element. Left
 * alone, an untouched instance's own passive settle (its initial seed, or a
 * quote its own MutationObserver reacts to for reasons that have nothing to
 * do with the shopper) can clobber whichever instance the shopper actually
 * used — confirmed live: picking "Just the hoodie" in the first block
 * correctly wrote ₹69.00, then an untouched later block's own passive
 * write overwrote it back to the default ₹37.40 a moment after. activeFor()
 * below exists solely to stop that: once a key's mirror has been claimed by
 * a real interaction, only that same widget instance may keep writing it.
 */
(function () {
	"use strict";

	/** Offer key => the widget instance whose interactions currently own its price mirror. */
	var activeWidgetByKey = {};

	/** Offer form => re-writes that form's own posted fields from its own widget. */
	var syncByForm = new WeakMap();

	/** Widget kinds another script registers; see the file docblock. */
	window.wffnBundlesOcuKinds = window.wffnBundlesOcuKinds || [];

	// A repeated CTA block prints the bundle once per Accept, so the quote must read only its own form's add-ons.
	var priorPartsRoot = window.wffnBundlesSkins.partsRoot;
	window.wffnBundlesSkins.partsRoot = function ( widget ) {
		var form = widget && widget.closest ? widget.closest( "form.wffn-bundles-ocu-form" ) : null;
		return form || ( "function" === typeof priorPartsRoot ? priorPartsRoot( widget ) : null );
	};

	window.wffnBundlesOcuKinds.push( {
		selector: ".wffn-bundles-fbt",
		events: [],
		selection: function ( widget ) {
			return window.wffnBundlesSkins.fbtSelection ? window.wffnBundlesSkins.fbtSelection( widget ) || {} : {};
		},
		price: function ( widget ) {
			var total = widget.querySelector( ".wffn-bundles-fbt-sum-price .wffn-bundles-fbt-total" );
			if ( ! total ) {
				return null;
			}
			// Keep the struck-through sum the widget shows beside it, as the static price did.
			var original = widget.querySelector( ".wffn-bundles-fbt-sum-price .wffn-bundles-fbt-price-original" );
			var struck = original && ! original.hidden && original.textContent.trim() ? "<del>" + original.innerHTML + "</del> " : "";
			return struck + total.innerHTML;
		},
	} );

	function registeredKind( widget ) {
		for ( var i = 0; i < window.wffnBundlesOcuKinds.length; i++ ) {
			if ( widget.matches( window.wffnBundlesOcuKinds[ i ].selector ) ) {
				return window.wffnBundlesOcuKinds[ i ];
			}
		}
		return null;
	}

	function claim( widget, offerKey ) {
		if ( offerKey ) {
			activeWidgetByKey[ offerKey ] = widget;
		}
	}

	/** May this widget write the shared mirror for offerKey right now? */
	function mayWrite( widget, offerKey ) {
		var owner = offerKey ? activeWidgetByKey[ offerKey ] : null;
		return ! owner || owner === widget;
	}

	/**
	 * Match the shopper's picked axis values (from `.wffn-bundles-variant-axis` selects,
	 * each value shaped "Axis:Value") against the `data-wffn-bundles-variations` combo
	 * list WFFN_Bundles already embeds on the `.wffn-bundles-variant` wrapper, to find that
	 * combo's own variation id — the same list fixed-bundle.js's own
	 * constrainVariants() reads. Every axis must be picked; returns null
	 * otherwise (mirrors Cart::resolve_variation()'s own "half-completed pick
	 * resolves to nothing" rule).
	 */
	function resolveVariationId( container ) {
		var variantEl = container.querySelector( ".wffn-bundles-variant[data-wffn-bundles-variations]" );
		if ( ! variantEl ) {
			return null;
		}
		var combos;
		try {
			combos = JSON.parse( variantEl.getAttribute( "data-wffn-bundles-variations" ) );
		} catch ( e ) {
			return null;
		}
		var axes = container.querySelectorAll( ".wffn-bundles-variant-axis" );
		if ( ! axes.length ) {
			return null;
		}
		var picked = {};
		for ( var i = 0; i < axes.length; i++ ) {
			var raw = axes[ i ].value;
			if ( ! raw ) {
				return null;
			}
			var sep = raw.indexOf( ":" );
			if ( sep < 0 ) {
				return null;
			}
			picked[ raw.slice( 0, sep ) ] = raw.slice( sep + 1 );
		}
		for ( var j = 0; j < combos.length; j++ ) {
			var row = combos[ j ], match = true;
			for ( var axis in picked ) {
				if ( row.a[ axis ] !== picked[ axis ] ) {
					match = false;
					break;
				}
			}
			if ( match ) {
				return row.id;
			}
		}
		return null;
	}

	function computeSelectionFB( widget, scope ) {
		var single = widget.querySelector( ".wffn-bundles-fb-single" );
		if ( single && single.classList.contains( "wffn-bundles-fb-single--selected" ) ) {
			// Distinct shape from a bundle pick — the server must not mistake
			// "Buy 1" for a bundle with defaulted members (it has no members).
			return {
				single: true,
				attributes: window.wffnBundlesSkins.readAttributes( single ),
				variationId: resolveVariationId( single ),
			};
		}
		var members = widget.querySelectorAll( ".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row" );
		var items = [];
		for ( var i = 0; i < members.length; i++ ) {
			items.push( { attributes: window.wffnBundlesSkins.readAttributes( members[ i ] ) } );
		}
		return { items: items };
	}

	// Add-on layers render as the widget's sibling, so extras are gathered from the shared form.
	function computeSelection( widget ) {
		var scope = widget.closest( "form.cart" ) || widget.parentElement || widget;
		var registered = registeredKind( widget );
		var selection = registered ? registered.selection( widget, scope ) : computeSelectionFB( widget, scope );
		var extras = window.wffnBundlesSkins.selectionExtras ? window.wffnBundlesSkins.selectionExtras( scope ) : {};
		for ( var key in extras ) {
			if ( Object.prototype.hasOwnProperty.call( extras, key ) ) {
				selection[ key ] = extras[ key ];
			}
		}
		return selection;
	}

	/**
	 * The price WFFN_Bundles itself is currently showing as the active pick's
	 * total — the selected card's `.wffn-bundles-fb-price-final` (bundle), the
	 * single-purchase row's (Buy 1), or a registered kind's own.
	 */
	function currentPriceHtml( widget ) {
		// The live quote, when one has landed, is what the product page's price shows: add-ons included.
		var quoted = widget.querySelector( "[data-wffn-bundles-price]" );
		if ( quoted && /\d/.test( quoted.textContent ) ) {
			return quoted.innerHTML;
		}
		var registered = registeredKind( widget );
		if ( registered ) {
			return registered.price( widget );
		}
		var single = widget.querySelector( ".wffn-bundles-fb-single" );
		var active = ( single && single.classList.contains( "wffn-bundles-fb-single--selected" ) )
			? single
			: widget.querySelector( ".wffn-bundles-fb-card, .wffn-bundles-fb-flat-head" );
		var priceEl = active && active.querySelector( ".wffn-bundles-fb-price-final" );
		return priceEl ? priceEl.innerHTML : null;
	}

	/** A displayed amount as a number, read in the store's own separators. */
	function parseAmount( text, fmt ) {
		var s = String( text );
		if ( fmt.thousand ) {
			s = s.split( fmt.thousand ).join( "" );
		}
		if ( fmt.decimal && "." !== fmt.decimal ) {
			s = s.split( fmt.decimal ).join( "." );
		}
		return parseFloat( s.replace( /[^0-9.\-]/g, "" ) ) || 0;
	}

	/** A number written back in the store's own decimals and separators. */
	function formatAmount( n, fmt ) {
		var parts = n.toFixed( Math.max( 0, parseInt( fmt.decimals, 10 ) || 0 ) ).split( "." );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, fmt.thousand || "" );
		return parts.join( fmt.decimal || "." );
	}

	/**
	 * Price HTML multiplied by the offer row's quantity, so the headline shows
	 * what accepting charges when the offer sells more than one bundle.
	 *
	 * @param {string}      html   Price HTML for one bundle.
	 * @param {HTMLElement} widget The widget the price belongs to.
	 * @return {string}
	 */
	function scaleByOfferQty( html, widget ) {
		var form = widget.closest( "form.wffn-bundles-ocu-form" );
		var qty = form ? parseInt( form.getAttribute( "data-wffn-bundles-offer-qty" ), 10 ) : 1;
		if ( ! ( qty > 1 ) ) {
			return html;
		}
		var fmt;
		try {
			fmt = JSON.parse( form.getAttribute( "data-wffn-bundles-price-format" ) || "{}" );
		} catch ( e ) {
			return html;
		}
		var box = document.createElement( "div" );
		box.innerHTML = html;
		var walker = document.createTreeWalker( box, NodeFilter.SHOW_TEXT );
		var node;
		while ( ( node = walker.nextNode() ) ) {
			if ( node.parentElement.closest( ".woocommerce-Price-currencySymbol" ) ) {
				continue;
			}
			// Only the number itself is rewritten; a symbol or label in the same text stays.
			node.nodeValue = node.nodeValue.replace( /\d[\d.,'  ]*\d|\d/, function ( number ) {
				return formatAmount( parseAmount( number, fmt ) * qty, fmt );
			} );
		}
		return box.innerHTML;
	}

	/**
	 * The price block of the product a picker sits in, on a template whose price
	 * wrapper carries no key (OCU's multi-product grid and list).
	 *
	 * Climbs from the picker to the first ancestor holding a price wrapper, and
	 * gives up at one whose Accept buttons sell another product.
	 *
	 * @param {Element} widget   The picker's widget.
	 * @param {string}  offerKey Its offer product key.
	 * @return {Element|null}
	 */
	function blockPriceWrapper( widget, offerKey ) {
		var node = widget.closest( "form.wffn-bundles-ocu-form" ) || widget;
		while ( node && node.parentElement && node !== document.body ) {
			node = node.parentElement;
			var foreign = Array.prototype.some.call( node.querySelectorAll( ".wfocu_upsell[data-key]" ), function ( button ) {
				return button.getAttribute( "data-key" ) !== offerKey;
			} );
			if ( foreign ) {
				return null;
			}
			var wrapper = node.querySelector( ".wfocu-price-wrapper" );
			if ( wrapper ) {
				return wrapper;
			}
		}
		return null;
	}

	function syncOfferPrice( widget, offerKey ) {
		if ( ! offerKey || ! mayWrite( widget, offerKey ) ) {
			return;
		}
		var original = document.querySelector( '.wfocu_price_wrapper[data-key="' + offerKey + '"]' ) || blockPriceWrapper( widget, offerKey );
		if ( ! original ) {
			return;
		}
		var html = currentPriceHtml( widget );
		if ( ! html ) {
			return;
		}
		html = scaleByOfferQty( html, widget );
		original.style.display = "none";
		var mirror = original.parentElement.querySelector( '.wffn-bundles-ocu-price-mirror[data-key="' + offerKey + '"]' );
		if ( ! mirror ) {
			mirror = document.createElement( "div" );
			mirror.className = "price wffn-bundles-ocu-price-mirror";
			mirror.setAttribute( "data-key", offerKey );
			original.insertAdjacentElement( "afterend", mirror );
		}
		mirror.innerHTML = html;
		original.classList.add( "wffn-bundles-price-settled" );
		syncOfferSaving( widget, offerKey, html );
	}

	/**
	 * Restate OCU's saving merge tags for the picked bundle.
	 *
	 * `{{product_save_percentage}}` and `{{product_save_combined}}` print keyed
	 * spans worked out on the server from the row's own price, before anything
	 * is picked, so a Mix & Match box read "0% OFF" whatever it saved. The live
	 * price just written carries the struck sum and the final one; the saving is
	 * read off those two.
	 *
	 * @param {Element} widget   The picker's widget.
	 * @param {string}  offerKey Its offer product key.
	 * @param {string}  html     The price markup just mirrored.
	 */
	function syncOfferSaving( widget, offerKey, html ) {
		var form = widget.closest( "form.wffn-bundles-ocu-form" );
		var fmt;
		try {
			fmt = JSON.parse( ( form && form.getAttribute( "data-wffn-bundles-price-format" ) ) || "{}" );
		} catch ( e ) {
			return;
		}
		var box = document.createElement( "div" );
		box.innerHTML = html;
		var priced = box.querySelectorAll( ".woocommerce-Price-amount" );
		var texts = priced.length
			? Array.prototype.map.call( priced, function ( el ) {
				return el.textContent;
			} )
			: box.textContent.match( /\d[\d.,'  ]*\d|\d/g ) || [];
		var amounts = texts.map( function ( text ) {
			return parseAmount( text, fmt );
		} );
		var keyed = '[data-key="' + offerKey + '"]';
		var tags = document.querySelectorAll( ".wfocu_variable_price_save_percentage" + keyed + ", .wfocu_variable_price_save_percentage_combo" + keyed );
		// Nothing picked yet: no saving to state, so the line waits rather than read "0%".
		// A builder's own text block ("You Save …") waits with it, or reads as a label with no number.
		tags.forEach( function ( el ) {
			( el.closest( ".wfocu-text-below-price" ) || el.closest( "p, h1, h2, h3, h4, h5, h6, li" ) || el ).style.display = amounts.length ? "" : "none";
			el.classList.add( "wffn-bundles-price-settled" );
		} );
		if ( ! amounts.length ) {
			return;
		}
		var final = amounts[ amounts.length - 1 ];
		var regular = Math.max.apply( null, amounts );
		var diff = Math.max( 0, regular - final );
		var percent = regular > 0 ? Math.round( ( diff / regular ) * 100 ) : 0;
		document.querySelectorAll( ".wfocu_variable_price_save_percentage" + keyed ).forEach( function ( el ) {
			el.textContent = percent + "%";
		} );
		document.querySelectorAll( ".wfocu_variable_price_save_percentage_combo" + keyed ).forEach( function ( el ) {
			var numbers = 0;
			var walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT );
			var node;
			while ( ( node = walker.nextNode() ) ) {
				if ( node.parentElement.closest( ".woocommerce-Price-currencySymbol" ) ) {
					continue;
				}
				node.nodeValue = node.nodeValue.replace( /\d[\d.,'  ]*\d|\d/g, function () {
					numbers++;
					return 1 === numbers ? formatAmount( diff, fmt ) : String( percent );
				} );
			}
		} );
	}

	/**
	 * OCU's own always-present hidden form for this offer key (see file
	 * docblock) — only exists for a VARIABLE anchor, since it carries variation
	 * stock/price JSON a simple product has no use for.
	 */
	function nativeVariationForm( offerKey ) {
		return offerKey ? document.querySelector( 'form.wfocu_variation_selector_form[data-key="' + offerKey + '"]' ) : null;
	}

	/**
	 * Our own form (wfocu_attributes_selector_form, see picker_markup()) is now
	 * what OCU's own accept handler serializes for wffn_bundles_bundle_id /
	 * wffn_bundles_selection — no mirroring needed for those. The one thing
	 * only the NATIVE form supplies is `_wfocu_variation` (OCU's own gate/price
	 * lookup for a variable anchor's "Buy 1" pick), which this keeps pointed at
	 * the shopper's real choice rather than the wrap's own default. A bundle
	 * pick leaves it untouched, which is harmless — the order-write filter
	 * fully overrides whatever OCU would have added once it detects the
	 * bundle.
	 */
	function syncNativeVariationField( offerKey, selection ) {
		if ( ! selection.single || ! selection.variationId ) {
			return;
		}
		var nf = nativeVariationForm( offerKey );
		var variationField = nf && nf.querySelector( 'input[name="_wfocu_variation"]' );
		if ( variationField ) {
			variationField.value = String( selection.variationId );
		}
	}

	/**
	 * The picker whose choice stands for the offer product itself: a set's
	 * "This item", what a registered kind names, else the widget's first picker.
	 *
	 * @param {Element} widget The widget.
	 * @return {Element|null}
	 */
	function nativePickSource( widget ) {
		if ( widget.matches( ".wffn-bundles-fbt" ) ) {
			return widget.querySelector( '.wffn-bundles-fbt-item[data-wffn-bundles-key="host"]' );
		}
		var kind = registeredKind( widget ) || {};
		if ( kind.nativePick ) {
			return kind.nativePick( widget );
		}
		var axes = window.wffnBundlesSkins.axesIn( widget );
		return axes.length ? axes[ 0 ].closest( ".wffn-bundles-variant" ) : null;
	}

	/**
	 * Mirror the offer product's pick into the offer's own variation form, the one
	 * place the offer reads a variable product's variation from on Accept.
	 *
	 * @param {Element} widget   The widget.
	 * @param {string}  offerKey Offer product key.
	 */
	function syncNativePick( widget, offerKey ) {
		var nf = nativeVariationForm( offerKey );
		var host = nf ? nativePickSource( widget ) : null;
		if ( ! host ) {
			return;
		}
		var slugs = function ( select, prefixed ) {
			var out = [];
			for ( var i = 0; i < select.options.length; i++ ) {
				var v = select.options[ i ].value;
				if ( v ) {
					out.push( prefixed ? v.slice( v.indexOf( ":" ) + 1 ) : v );
				}
			}
			return out.sort().join( "|" );
		};
		var natives = nf.querySelectorAll( 'select[name^="attribute_"]' );
		window.wffnBundlesSkins.axesIn( host ).forEach( function ( axis ) {
			var key = slugs( axis, true );
			var value = axis.value ? axis.value.slice( axis.value.indexOf( ":" ) + 1 ) : "";
			for ( var i = 0; i < natives.length; i++ ) {
				if ( slugs( natives[ i ], false ) === key && natives[ i ].value !== value ) {
					natives[ i ].value = value;
					natives[ i ].dispatchEvent( new Event( "change", { bubbles: true } ) );
				}
			}
		} );
	}

	function setup( widget ) {
		var scope = widget.closest( "form.cart" ) || widget.parentElement || widget;
		var field = scope.querySelector( 'input[name="wffn_bundles_selection"]' );
		var offerKey = scope.getAttribute ? scope.getAttribute( "data-wffn-bundles-offer-key" ) : null;
		var kind = registeredKind( widget ) || {};
		var kindEvents = kind.events || [];

		function writeSelection() {
			var selection = computeSelection( widget );
			if ( field ) {
				field.value = JSON.stringify( selection );
			}
			syncNativeVariationField( offerKey, selection );
			syncNativePick( widget, offerKey );
		}

		function writePrice() {
			syncOfferPrice( widget, offerKey );
		}

		if ( scope.matches && scope.matches( "form.wffn-bundles-ocu-form" ) ) {
			var gate = scope.querySelector( ".wffn-bundles-ocu-gate" );
			if ( gate && window.MutationObserver ) {
				var syncAccept = function () {
					acceptButtonsFor( scope ).forEach( function ( button ) {
						button.classList.toggle( "wffn-bundles-ocu-accept-disabled", gate.disabled );
						button.setAttribute( "aria-disabled", gate.disabled ? "true" : "false" );
					} );
				};
				syncAccept();
				new MutationObserver( syncAccept ).observe( gate, { attributes: true, attributeFilter: [ "disabled" ] } );
			}
			syncByForm.set( scope, function () {
				if ( kind.sync ) {
					kind.sync( widget, scope );
				}
				writeSelection();
			} );
		}

		writeSelection();
		writePrice(); // Seed from whatever's selected by default — not a claim.

		// Bound on the shared scope, not on widget: wffn-bundles:addon-change is
		// dispatched on an add-on layer, widget's SIBLING (see computeSelection
		// above) — a listener on widget itself would never see it bubble past.
		//
		// Every one of these is a genuine, shopper-driven interaction with THIS
		// widget instance, so each also claims the shared price mirror for it —
		// the one thing that stops a repeated, untouched block's own later
		// passive settle from clobbering what the shopper actually picked.
		function onInteraction( writer ) {
			return function () {
				claim( widget, offerKey );
				writer();
			};
		}
		scope.addEventListener( "change", onInteraction( writeSelection ) );
		scope.addEventListener( "wffn-bundles:fb-change", onInteraction( writeSelection ) );
		kindEvents.forEach( function ( ev ) {
			scope.addEventListener( ev, onInteraction( writeSelection ) );
		} );
		scope.addEventListener( "wffn-bundles:addon-change", onInteraction( writeSelection ) );

		// Both triggers below are needed, for two different reasons:
		//  - The discrete events cover switching WHICH element is active (Buy 1
		//    vs the bundle card) — its price can be a static, already-rendered
		//    figure that never mutates (Buy 1's ₹69.00 needs no live quote), so
		//    a mutation-only watch would never re-read it on that switch alone.
		//  - The MutationObserver covers the figure WITHIN the active element
		//    changing later, asynchronously — the live quote (core.js
		//    F.liveQuote, an AJAX round-trip) lands in .wffn-bundles-fb-price-final well
		//    after the interaction event that triggered it; a synchronous read
		//    on "change" alone was confirmed live to catch the stale figure.
		scope.addEventListener( "change", onInteraction( writePrice ) );
		scope.addEventListener( "wffn-bundles:fb-change", onInteraction( writePrice ) );
		kindEvents.forEach( function ( ev ) {
			scope.addEventListener( ev, onInteraction( writePrice ) );
		} );
		scope.addEventListener( "wffn-bundles:addon-change", onInteraction( writePrice ) );

		var priceHost = widget.querySelector( kind.priceHost || ".wffn-bundles-fb-stack, .wffn-bundles-fb-flat" ) || widget;
		if ( window.MutationObserver ) {
			var pending = null;
			var observer = new MutationObserver( function () {
				// NOT an interaction, deliberately: a mutation alone does not
				// claim the mirror, so an untouched repeated block's own async
				// settle (see file docblock) stays silent once another instance
				// has been interacted with.
				clearTimeout( pending );
				pending = setTimeout( writePrice, 30 );
			} );
			observer.observe( priceHost, { childList: true, characterData: true, subtree: true } );
			var quoted = widget.querySelector( "[data-wffn-bundles-price]" );
			if ( quoted && ! priceHost.contains( quoted ) ) {
				observer.observe( quoted, { childList: true, characterData: true, subtree: true } );
			}
		}
	}

	/**
	 * The offer form an Accept button posts: the one in its own widget, else the
	 * nearest one printed before it, else the first for its key.
	 *
	 * @param {HTMLElement} button An Accept button.
	 * @return {HTMLFormElement|null}
	 */
	function formFor( button ) {
		var selector = 'form.wffn-bundles-ocu-form[data-key="' + button.getAttribute( "data-key" ) + '"]';
		var host = button.closest( ".elementor-widget" );
		var own = host && host.querySelector( selector );
		if ( own ) {
			return own;
		}
		var forms = document.querySelectorAll( selector );
		var before = null;
		for ( var i = 0; i < forms.length; i++ ) {
			// A form prints right above its Accept, so a repeated block's own is the last one before it.
			if ( forms[ i ].compareDocumentPosition( button ) & Node.DOCUMENT_POSITION_FOLLOWING ) {
				before = forms[ i ];
			}
		}
		return before || forms[ 0 ] || null;
	}

	/**
	 * The Accept buttons an offer form's pick governs: those that post it.
	 *
	 * @param {HTMLFormElement} form The offer form.
	 * @return {HTMLElement[]}
	 */
	function acceptButtonsFor( form ) {
		var key = form.getAttribute( "data-key" );
		return Array.prototype.filter.call( document.querySelectorAll( '.wfocu_upsell[data-key="' + key + '"]' ), function ( button ) {
			return formFor( button ) === form;
		} );
	}

	/**
	 * Hand the offer the picker beside the Accept button that was clicked.
	 *
	 * The accept posts the first selector form carrying the button's key, and a
	 * pick writes into every form for the product, so a repeated CTA block
	 * would post whichever picker changed last. Capture phase runs this first;
	 * the other forms drop the selector class only for this click.
	 *
	 * @param {MouseEvent} e Click anywhere on the page.
	 */
	function handOwnPicker( e ) {
		var button = e.target && e.target.closest ? e.target.closest( ".wfocu_upsell[data-key]" ) : null;
		if ( ! button ) {
			return;
		}
		var key = button.getAttribute( "data-key" );
		var own = formFor( button );
		if ( ! own ) {
			return;
		}
		// The widget's own add gate says the pick is incomplete: keep the offer from posting it.
		var gate = own.querySelector( ".wffn-bundles-ocu-gate" );
		if ( gate && gate.disabled ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			var widget = own.querySelector( ".wffn-bundles" );
			if ( widget ) {
				widget.scrollIntoView( { block: "center", behavior: "smooth" } );
				window.wffnBundlesSkins.showNotice( widget, ( window.wffnBundlesOcuOffer || {} ).incomplete || "" );
			}
			return;
		}
		var sync = syncByForm.get( own );
		if ( sync ) {
			sync();
		}
		var others = Array.prototype.filter.call(
			document.querySelectorAll( 'form.wfocu_attributes_selector_form[data-key="' + key + '"]' ),
			function ( form ) {
				return form !== own;
			}
		);
		others.forEach( function ( form ) {
			form.classList.remove( "wfocu_attributes_selector_form" );
		} );
		setTimeout( function () {
			others.forEach( function ( form ) {
				form.classList.add( "wfocu_attributes_selector_form" );
			} );
		}, 0 );
	}
	document.addEventListener( "click", handOwnPicker, true );

	function widgetSelectors() {
		return [ ".wffn-bundles-fb" ].concat( window.wffnBundlesOcuKinds.map( function ( k ) {
			return k.selector;
		} ) );
	}

	/**
	 * What accepting an offer row charges for the bundle its widget shows, or null
	 * while the widget shows no single price (options still to pick).
	 *
	 * @param {string} key Offer product key.
	 * @return {number|null}
	 */
	function bundleCharge( key ) {
		var form = document.querySelector( 'form.wffn-bundles-ocu-form.wfocu_attributes_selector_form[data-key="' + key + '"]' ) ||
			document.querySelector( 'form.wffn-bundles-ocu-form[data-key="' + key + '"]' );
		var widget = form && form.querySelector( widgetSelectors().join( ", " ) );
		var html = widget && currentPriceHtml( widget );
		var fmt;
		if ( ! html ) {
			return null;
		}
		try {
			fmt = JSON.parse( form.getAttribute( "data-wffn-bundles-price-format" ) || "{}" );
		} catch ( e ) {
			return null;
		}
		var box = document.createElement( "div" );
		box.innerHTML = html;
		// A struck sum is never the charge, and a price can strike it without marking the final one.
		Array.prototype.forEach.call( box.querySelectorAll( "del" ), function ( struck ) {
			struck.parentNode.removeChild( struck );
		} );
		var finals = box.querySelectorAll( "ins" );
		var numbers = ( finals.length ? finals[ finals.length - 1 ].textContent : box.textContent ).match( /\d[\d.,'  ]*\d|\d/g );
		if ( ! numbers || ( ! finals.length && numbers.length > 1 ) ) {
			return null;
		}
		var qty = parseInt( form.getAttribute( "data-wffn-bundles-offer-qty" ), 10 ) || 1;
		var amount = parseAmount( numbers[ numbers.length - 1 ], fmt ) * Math.max( 1, qty );
		return amount > 0 ? amount : null;
	}

	// The offer's own price is the product's; a bundle row charges what its widget shows.
	function hookCharge() {
		if ( window.wfocuCommons && "function" === typeof window.wfocuCommons.addFilter ) {
			// After the default priority, where the offer page reprices the product alone.
			window.wfocuCommons.addFilter( "wfocu_additem_price", function ( price, key ) {
				var charge = bundleCharge( key );
				return null === charge ? price : charge;
			}, 15 );
		}
	}
	if ( "loading" === document.readyState ) {
		document.addEventListener( "DOMContentLoaded", hookCharge );
	} else {
		hookCharge();
	}

	window.wffnBundlesSkins.register( function ( root ) {
		window.wffnBundlesSkins.each( root, widgetSelectors().join( ", " ), setup );
	} );
})();
