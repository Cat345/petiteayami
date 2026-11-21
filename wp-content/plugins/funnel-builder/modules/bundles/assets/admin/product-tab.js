/**
 * Bundles tab on the product edit screen.
 *
 * Bundle types register a form with wffnBundlesProductTab.registerType(); the shell
 * owns the list, the type switcher, the product search and the save.
 */
( function ( $ ) {
	'use strict';

	var D = window.wffnBundlesProductTabData;
	var root = document.getElementById( 'wffn-bundles-pt-root' );
	if ( ! D || ! root ) {
		return;
	}
	var T = D.i18n;
	var registry = {};
	// Named radios point at a form that does not exist, so the product form never submits them.
	var NOFORM = 'wffn-bundles-pt-noform';
	var PANEL = '#wffn_bundles_product_data';
	var NOTICE_MS = 4000;
	var PLUS = '<span class="wffn-bundles-pt__ico wffn-bundles-pt__ico--plus" aria-hidden="true"></span>';
	var state = { noticeTimer: 0, linked: {}, assignable: [], assignMsg: '', assignErr: [], assigning: false, view: 'form', bundles: [], loaded: ! D.product.saved, loading: false, loadError: false, typeKey: null, form: null, busy: false, error: '', created: '', rowBusy: '', picks: {}, pvOption: null, enabling: false, enableError: '', declining: false };

	var live = document.createElement( 'div' );
	live.className = 'screen-reader-text';
	live.setAttribute( 'aria-live', 'polite' );
	root.parentNode.appendChild( live );

	function esc( v ) {
		return String( v == null ? '' : v ).replace( /[&<>"']/g, function ( ch ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ ch ];
		} );
	}

	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(?:(\d+)\$)?[sd]/g, function ( m, n ) {
			return String( n ? args[ n - 1 ] : args[ i++ ] );
		} );
	}

	function trimNum( n ) {
		return String( Math.round( ( Number( n ) || 0 ) * 100 ) / 100 );
	}

	function money( n ) {
		var c = D.currency;
		var fixed = Number( n || 0 ).toFixed( c.decimals );
		var parts = fixed.split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, c.thouSep );
		var num = parts.join( c.decSep );
		var map = { left: c.symbol + num, right: num + c.symbol, left_space: c.symbol + ' ' + num, right_space: num + ' ' + c.symbol };
		return map[ c.position ] || map.left;
	}

	// A variable product's low and high price, as the storefront prints them.
	function range( lo, hi ) {
		return hi > lo ? money( lo ) + ' \u2013 ' + money( hi ) : money( lo );
	}

	function api( method, path, body ) {
		var opts = { method: method, headers: { 'X-WP-Nonce': D.nonce, 'Content-Type': 'application/json' }, credentials: 'same-origin' };
		if ( body ) {
			opts.body = JSON.stringify( body );
		}
		return fetch( D.restRoot + path, opts )
			.catch( function () {
				throw new Error( T.error );
			} )
			.then( function ( r ) {
				return r.json().catch( function () {
					throw new Error( T.error );
				} );
			} )
			.then( function ( j ) {
				// A WordPress REST error carries a string code; this plugin's envelope a number.
				if ( ! j || 'number' !== typeof j.code || j.code >= 400 ) {
					throw new Error( ( j && j.message ) || T.error );
				}
				return j.data;
			} );
	}

	function nid( prefix ) {
		return prefix + Math.random().toString( 36 ).slice( 2, 9 );
	}

	function announce( msg ) {
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = msg;
		}, 50 );
	}

	function typeMeta( key ) {
		return D.types.filter( function ( t ) {
			return t.key === key;
		} )[ 0 ];
	}

	function isPitch( key ) {
		var t = typeMeta( key );
		return !! ( t && t.pitch && ! registry[ key ] );
	}

	function offeredTypes() {
		return D.types.filter( function ( t ) {
			return registry[ t.key ] || isPitch( t.key );
		} );
	}

	function formTypes() {
		return D.types.filter( function ( t ) {
			return registry[ t.key ];
		} );
	}

	function pitchFor( typeId ) {
		return D.types.filter( function ( t ) {
			return t.pitch && ! registry[ t.key ] && Number( t.id ) === Number( typeId );
		} )[ 0 ];
	}

	function proChip() {
		return '<span class="wffn-bundles-pt__pro">' + esc( T.pro ) + '</span>';
	}

	// Buy the add-on, activate the copy already installed, or say who can.
	function proAction( cls ) {
		if ( 'activate' === D.pro.step ) {
			return '<button type="button" class="' + cls + '" data-act="activate-pro">' + esc( T.activatePro ) + '</button>';
		}
		return 'ask' === D.pro.step ? '' : upgradeLink( cls );
	}

	function proNote( t ) {
		var copy = ( t && t.copy ) || {};
		var note = { activate: copy.inactive || T.proInactive, ask: T.proAskAdmin }[ D.pro.step ];
		return note ? fmt( note, D.pro.name ) : copy.note || T.pitchNote;
	}

	function activatePro( btn ) {
		btn.disabled = true;
		btn.lastChild.textContent = T.activatingPro;
		var body = new URLSearchParams( { action: 'wffn_bundles_product_tab_activate_pro', _wpnonce: D.listNonce } );
		fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( ( j && j.data && j.data.message ) || T.error );
				}
				try {
					window.sessionStorage.setItem( 'wffn-bundles-pt-open-' + D.product.id, '1' );
				} catch ( err ) {}
				// The add-on's form script only loads on the next page view.
				window.location.reload();
			} )
			.catch( function ( e ) {
				btn.disabled = false;
				btn.lastChild.textContent = T.activatePro;
				setError( e.message || T.error );
			} );
	}

	function upgradeLink( cls ) {
		return '<a class="' + cls + '" href="' + esc( D.upgradeUrl ) + '" target="_blank" rel="noopener">' + esc( T.upgrade ) + '</a>';
	}

	function errorHtml() {
		return '<p class="wffn-bundles-pt__error" id="wffn-bundles-pt-error" role="alert"' + ( state.error ? '' : ' hidden' ) + '>' + esc( state.error ) + '</p>';
	}

	// Validation names the field it is about; a server error stays with the button.
	function fieldFor( e ) {
		if ( e && e.field ) {
			return e.field;
		}
		var map = {};
		map[ T.needName ] = '#wffn-bundles-pt-f-name';
		map[ T.needProducts ] = '#wffn-bundles-pt-products';
		map[ T.badPercent ] = map[ T.badAmount ] = '#wffn-bundles-pt-f-discountValue';
		return map[ e && e.message ];
	}

	function clearFieldError() {
		root.querySelectorAll( '.wffn-bundles-pt__field.is-invalid' ).forEach( function ( field ) {
			field.classList.remove( 'is-invalid' );
			var note = field.querySelector( '.wffn-bundles-pt__field-error' );
			if ( note ) {
				note.parentNode.removeChild( note );
			}
			field.querySelectorAll( '[aria-invalid]' ).forEach( function ( el ) {
				el.removeAttribute( 'aria-invalid' );
				el.removeAttribute( 'aria-describedby' );
			} );
		} );
	}

	function fieldError( e ) {
		clearFieldError();
		var target = root.querySelector( fieldFor( e ) || '#wffn-bundles-pt-none' );
		var field = target && target.closest( '.wffn-bundles-pt__field' );
		if ( ! field ) {
			setError( e.message || T.error );
			return;
		}
		setError( '' );
		field.classList.add( 'is-invalid' );
		var hint = field.querySelector( '.wffn-bundles-pt__field-hint' );
		if ( hint ) {
			hint.parentNode.removeChild( hint );
		}
		var note = document.createElement( 'p' );
		note.className = 'wffn-bundles-pt__field-error';
		note.id = 'wffn-bundles-pt-field-error';
		note.setAttribute( 'role', 'alert' );
		note.textContent = e.message;
		field.appendChild( note );
		var control = field.querySelector( '.select2-selection' ) || field.querySelector( 'input:not([type="radio"])' );
		if ( control ) {
			control.setAttribute( 'aria-invalid', 'true' );
			control.setAttribute( 'aria-describedby', 'wffn-bundles-pt-field-error' );
			control.focus();
		}
	}

	function setError( msg ) {
		state.error = msg || '';
		var el = document.getElementById( 'wffn-bundles-pt-error' );
		if ( ! el ) {
			render();
			return;
		}
		el.textContent = state.error;
		el.hidden = ! state.error;
	}

	function scopeText( b ) {
		return { product: T.scopeProduct, category: T.scopeCategory }[ b.scope ] || T.scopeShared;
	}

	function rowHtml( b ) {
		var draft = 'draft' === b.state;
		var pitch = pitchFor( b.type );
		var nameId = 'wffn-bundles-pt-name-' + esc( b.id );
		// A bundle on this product alone needs no label; one placed by category or shared with other products says so.
		var sub = 'product' === b.scope ? '' : esc( scopeText( b ) );
		if ( pitch ) {
			sub = '<span class="wffn-bundles-pt__warn">' + esc( T.needsProSub ) + '</span>';
		} else if ( 'upcoming' === b.state ) {
			sub = '<span class="wffn-bundles-pt__warn">' + esc( fmt( T.startsOn, b.when ) ) + '</span>';
		} else if ( 'ended' === b.state ) {
			sub = '<span class="wffn-bundles-pt__danger">' + esc( fmt( T.endedOn, b.when ) ) + '</span>';
		} else if ( 'nodates' === b.state ) {
			sub = '<span class="wffn-bundles-pt__warn">' + esc( T.noDates ) + '</span>';
		} else if ( 'scheduled' === b.state ) {
			sub = '<span class="wffn-bundles-pt__warn">' + esc( T.offSchedule ) + '</span>';
		} else if ( 'live' === b.state && b.when ) {
			sub += ( sub ? ' · ' : '' ) + esc( fmt( T.runsUntil, b.when ) );
		} else if ( 'unsupported' === b.state ) {
			sub = '<span class="wffn-bundles-pt__warn">' + esc( T.typeUnavailable ) + '</span>';
		}
		// Only the bundle the product page renders is "Showing"; the rest, whatever the reason, are not.
		var showing = ! pitch && 'live' === b.state;
		var tag = '<span class="wffn-bundles-pt__tag wffn-bundles-pt__tag--' + ( showing ? 'on' : 'off' ) + '"' + ( draft ? ' title="' + esc( T.tagDraftWhy ) + '"' : '' ) + '>' + esc( showing ? T.tagShowing : T.tagNotShowing ) + '</span>';
		var preview = showing && D.permalink ? '<a href="' + esc( D.permalink ) + '" target="_blank" rel="noopener" aria-describedby="' + nameId + '">' + esc( T.preview ) + '</a>' : '';
		var busy = state.rowBusy ? ' disabled' : '';
		var removeBtn = '<button type="button" class="wffn-bundles-pt__link" data-act="unassign" data-id="' + esc( b.id ) + '" aria-describedby="' + nameId + '"' + busy + '>' + esc( T.remove ) + '</button>';
		// A bundle placed by category cannot lose one product, so it has no remove link.
		var actions = 'category' === b.scope ? '' : removeBtn;
		return (
			'<tr class="wffn-bundles-pt__row' + ( b.id === state.created ? ' is-new' : '' ) + ( b.id === state.rowBusy ? ' is-busy' : '' ) + '" data-id="' + esc( b.id ) + '">' +
			( canSort()
				? '<td class="wffn-bundles-pt__col-grip"><button type="button" class="wffn-bundles-pt__grip" data-act="grip" data-id="' + esc( b.id ) + '" aria-label="' + esc( fmt( T.moveRow, b.name ) ) + '" aria-describedby="wffn-bundles-pt-sort-help"><span class="wffn-bundles-pt__ico wffn-bundles-pt__ico--grip" aria-hidden="true"></span></button></td>'
				: '' ) +
			'<td class="wffn-bundles-pt__col-name"><div class="wffn-bundles-pt__row-name" id="' + nameId + '">' + esc( b.name ) + '</div><div class="wffn-bundles-pt__row-tag">' + tag + '</div>' + ( sub ? '<div class="wffn-bundles-pt__row-sub">' + sub + '</div>' : '' ) + '</td>' +
			'<td class="wffn-bundles-pt__col-type">' + ( pitch ? '<span class="wffn-bundles-pt__type-name">' + esc( pitch.label ) + proChip() + '</span>' : esc( b.typeLabel ) ) + '</td>' +
			'<td class="wffn-bundles-pt__col-actions">' + preview +
			'<a href="' + esc( b.editUrl ) + '" aria-describedby="' + nameId + '">' + esc( T.edit ) + '</a>' +
			actions +
			'</td>' +
			'</tr>'
		);
	}

	// Existing bundles this product can be added to, under a rule below the list.
	function assignView() {
		var notes = state.assignErr.map( function ( f ) {
			return '<p class="wffn-bundles-pt__field-error" role="alert">' + esc( fmt( T.assignFailed, f.name || f.id, f.message ) ) + '</p>';
		} ).join( '' );
		var msg = state.assignMsg ? '<p class="wffn-bundles-pt__notice" role="status">' + esc( state.assignMsg ) + '</p>' : '';
		if ( ! state.assignable.length && ! notes && ! msg ) {
			return '';
		}
		var options = state.assignable.map( function ( c ) {
			return '<option value="' + esc( c.id ) + '">' + esc( c.name + ' · ' + c.typeLabel + ( c.draft ? ' · ' + T.assignDraft : '' ) ) + '</option>';
		} ).join( '' );
		return (
			'<div class="wffn-bundles-pt__assign"><div class="wffn-bundles-pt__field"><label class="wffn-bundles-pt__label" for="wffn-bundles-pt-assign">' + esc( T.assignTitle ) + '</label>' +
			( state.assignable.length
				? '<p class="wffn-bundles-pt__muted" id="wffn-bundles-pt-assign-help">' + esc( T.assignHelp ) + '</p>' +
					'<div class="wffn-bundles-pt__assign-row"><select id="wffn-bundles-pt-assign" class="wffn-bundles-pt__assign-select" multiple aria-describedby="wffn-bundles-pt-assign-help">' + options + '</select>' +
					'<button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--primary" data-act="assign"' + ( state.assigning ? ' disabled' : '' ) + '>' + esc( T.assignButton ) + '</button></div>'
				: '' ) + msg + notes + '</div></div>'
		);
	}

	function initAssign() {
		var el = root.querySelector( '#wffn-bundles-pt-assign' );
		if ( el && $ && $.fn.selectWoo ) {
			$( el ).selectWoo( { width: '100%', placeholder: T.assignPlaceholder, closeOnSelect: false, dropdownCssClass: 'wffn-bundles-pt-dropdown' } );
		}
	}

	function destroyAssign() {
		var el = root.querySelector( '#wffn-bundles-pt-assign' );
		if ( el && $ && $( el ).data( 'select2' ) ) {
			$( el ).selectWoo( 'close' ).selectWoo( 'destroy' );
		}
	}

	function post( action, fields ) {
		var body = new URLSearchParams( { action: action, product_id: D.product.id, _wpnonce: D.listNonce } );
		Object.keys( fields ).forEach( function ( k ) {
			[].concat( fields[ k ] ).forEach( function ( v ) {
				body.append( Array.isArray( fields[ k ] ) ? k + '[]' : k, v );
			} );
		} );
		return fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.catch( function () {
				throw new Error( T.error );
			} )
			.then( function ( r ) {
				return r.json().catch( function () {
					throw new Error( T.error );
				} );
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( ( j && j.data && j.data.message ) || T.error );
				}
				return j.data;
			} );
	}

	// Bundles just added go live and take the top, the last one added first, so the newest is the one shown.
	function showAdded( added ) {
		var byId = {};
		state.bundles.forEach( function ( b ) {
			byId[ b.id ] = b;
		} );
		var fresh = added.filter( function ( id ) {
			return byId[ id ];
		} );
		if ( ! fresh.length ) {
			return Promise.resolve();
		}
		var chain = Promise.resolve();
		fresh.forEach( function ( id ) {
			if ( 'draft' === byId[ id ].state && ! pitchFor( byId[ id ].type ) ) {
				chain = chain.then( function () {
					return api( 'PUT', '/bundles/' + encodeURIComponent( id ) + '/status', { status: 1 } );
				} );
			}
		} );
		var order = fresh.slice().reverse().concat( state.bundles.map( function ( b ) {
			return b.id;
		} ).filter( function ( id ) {
			return -1 === fresh.indexOf( id );
		} ) );
		return chain.then( function () {
			return api( 'POST', '/bundles/reorder', { order: order } );
		} ).then( reload );
	}

	function assign() {
		var el = root.querySelector( '#wffn-bundles-pt-assign' );
		var ids = el ? [].map.call( el.selectedOptions, function ( o ) {
			return o.value;
		} ) : [];
		if ( ! ids.length || state.assigning ) {
			return;
		}
		state.assigning = true;
		state.assignMsg = '';
		state.assignErr = [];
		render();
		post( 'wffn_bundles_product_tab_assign', { bundle_ids: ids } )
			.then( function ( r ) {
				state.assignErr = r.failed || [];
				state.assignMsg = r.added.length ? ( 1 === r.added.length ? T.assignedOne : fmt( T.assignedMany, r.added.length ) ) : '';
				return reload().then( function () {
					return showAdded( r.added );
				} );
			} )
			.catch( function ( e ) {
				state.assignErr = [ { id: '', name: '', message: e.message || T.error } ];
			} )
			.then( function () {
				state.assigning = false;
				render();
				fadeNotice();
				focusBack( '[data-act="assign"]' );
			} );
	}

	// The notice confirms an action, so it clears itself without redrawing the form.
	function fadeNotice() {
		window.clearTimeout( state.noticeTimer );
		if ( ! state.assignMsg ) {
			return;
		}
		state.noticeTimer = window.setTimeout( function () {
			state.assignMsg = '';
			var notice = root.querySelector( '.wffn-bundles-pt__notice' );
			if ( ! notice ) {
				return;
			}
			var box = root.querySelector( '.wffn-bundles-pt__assign' );
			if ( box && ! state.assignable.length && ! state.assignErr.length ) {
				box.remove();
			} else {
				notice.remove();
			}
		}, NOTICE_MS );
	}

	function unassign( id ) {
		var b = state.bundles.filter( function ( x ) {
			return x.id === id;
		} )[ 0 ];
		if ( ! b || state.rowBusy || ! window.confirm( fmt( T.removeConfirm, b.name ) ) ) {
			return;
		}
		state.rowBusy = id;
		state.created = '';
		state.error = '';
		state.assignMsg = '';
		state.assignErr = [];
		render();
		post( 'wffn_bundles_product_tab_unassign', { bundle_id: id } )
			.then( function ( r ) {
				state.assignMsg = r.draft ? T.removedDraft : T.removed;
				return reload();
			} )
			.catch( function ( e ) {
				state.error = e.message || T.error;
			} )
			.then( function () {
				state.rowBusy = '';
				// Without bundles left the tab goes back to creating one.
				if ( ! state.bundles.length ) {
					state.view = 'form';
				}
				render();
				fadeNotice();
				focusBack( '[data-act="unassign"]', '[data-act="assign"]' );
			} );
	}

	function listView() {
		var manage = fmt( esc( T.manageAll ), '<a href="' + esc( D.listUrl ) + '">' + esc( T.manageLink ) + '</a>' );
		if ( ! state.bundles.length ) {
			return errorHtml() + '<p class="wffn-bundles-pt__muted">' + esc( T.empty ) + '</p><p class="wffn-bundles-pt__muted">' + manage + '</p>';
		}
		return (
			errorHtml() +
			'<table class="wffn-bundles-pt__table' + ( canSort() ? ' is-sortable' : '' ) + '"><thead><tr>' +
			( canSort() ? '<th scope="col" class="wffn-bundles-pt__col-grip"><span class="screen-reader-text">' + esc( T.priority ) + '</span></th>' : '' ) +
			'<th scope="col" class="wffn-bundles-pt__col-name">' + esc( T.bundle ) + '</th>' +
			'<th scope="col" class="wffn-bundles-pt__col-type">' + esc( T.type ) + '</th>' +
			'<th scope="col" class="wffn-bundles-pt__col-actions">' + esc( T.actionCol ) + '</th>' +
			'</tr></thead><tbody>' + state.bundles.map( rowHtml ).join( '' ) + '</tbody></table>' +
			( canSort()
				? '<p class="wffn-bundles-pt__muted wffn-bundles-pt__sort-help" id="wffn-bundles-pt-sort-help">' + esc( T.sortHelp ) + '</p>'
				: '' ) +
			// One bundle shows per product, so more are added by assigning existing ones below, not created from here.
			'<div class="wffn-bundles-pt__listfoot"><p class="wffn-bundles-pt__muted">' + manage + '</p></div>' +
			assignView()
		);
	}

	// A tier list for a tiered type, the box grid for a pick-your-own one.
	function pitchPreview( t, c ) {
		var copy = t.copy || {};
		if ( 'tiers' === t.mock ) {
			var price = Number( c.product.priceRaw ) || 0;
			return (
				'<div class="wffn-bundles-pt__pv-heading">' + esc( copy.heading || '' ) + '</div>' +
				[ [ 1, 0 ], [ 3, 20 ], [ 6, 35 ] ]
					.map( function ( r, i ) {
						var full = price * r[ 0 ];
						var now = discounted( full, 'percentage', r[ 1 ] );
						return option( fmt( T.buyN, r[ 0 ] ), r[ 1 ] > 0 ? fmt( T.saveX, r[ 1 ] + '%' ) : T.regularPrice, '<span>' + esc( money( now ) ) + '</span>' + ( r[ 1 ] > 0 ? '<s>' + esc( money( full ) ) + '</s>' : '' ), 1 === i, '', null );
					} )
					.join( '' )
			);
		}
		var cards = [ c.product, null, null ]
			.map( function ( p ) {
				return (
					'<div class="wffn-bundles-pt__pv-card' + ( p ? ' is-on' : ' is-ghost' ) + '">' + thumbImg( p, 'wffn-bundles-pt__pv-img' ) +
					'<div class="wffn-bundles-pt__pv-card-name">' + esc( p ? p.name || T.thisProduct : T.nextProduct ) + '</div>' +
					'<div class="wffn-bundles-pt__pv-sub">' + ( p ? esc( money( p.priceRaw ) ) : '&nbsp;' ) + '</div>' +
					( p ? stepper( 1 ) : '<div class="wffn-bundles-pt__pv-card-btn">' + esc( T.add ) + '</div>' ) + '</div>'
				);
			} )
			.join( '' );
		return (
			'<div class="wffn-bundles-pt__pv-option-row"><span class="wffn-bundles-pt__pv-heading wffn-bundles-pt__pv-text">' + esc( copy.heading || T.pitchHeading ) + '</span><span class="wffn-bundles-pt__pv-sub">1/3</span></div>' +
			'<div class="wffn-bundles-pt__pv-progress"><span class="wffn-bundles-pt__pv-progress-third"></span></div>' +
			'<div class="wffn-bundles-pt__pv-grid">' + cards + '</div>'
		);
	}

	function pitchView( t, cta ) {
		var c = ctx();
		var copy = t.copy || {};
		var points = ( copy.points || [ T.pitchPoint1, T.pitchPoint2 ] )
			.map( function ( p ) {
				return '<li>' + esc( p ) + '</li>';
			} )
			.join( '' );
		return (
			'<div class="wffn-bundles-pt__pitch"><p>' + esc( copy.text || T.pitchText ) + '</p><ul class="wffn-bundles-pt__pitch-points">' + points + '</ul>' + cta + '</div>' +
			'<div class="wffn-bundles-pt__previewcol"><span class="wffn-bundles-pt__label" id="wffn-bundles-pt-preview-label">' + esc( T.preview_h ) + '</span>' +
			'<div class="wffn-bundles-pt__preview" id="wffn-bundles-pt-preview" role="group" aria-labelledby="wffn-bundles-pt-preview-label">' + pitchPreview( t, c ) + '</div></div>'
		);
	}

	function formView() {
		var pitch = isPitch( state.typeKey ) ? typeMeta( state.typeKey ) : null;
		var type = registry[ state.typeKey ];
		var c = ctx();
		var hasList = state.bundles.length > 0;
		var head = hasList
			? '<button type="button" class="wffn-bundles-pt__back" data-act="back"><span class="wffn-bundles-pt__ico wffn-bundles-pt__ico--back" aria-hidden="true"></span>' + esc( T.back ) + '</button>' +
				'<h3 class="wffn-bundles-pt__title">' + esc( T.newBundle ) + '</h3>'
			: '<h3 class="wffn-bundles-pt__title">' + esc( T.bundles ) + '</h3>';
		var types = offeredTypes()
			.map( function ( t ) {
				var on = t.key === state.typeKey;
				return (
					'<label class="wffn-bundles-pt__type' + ( on ? ' is-on' : '' ) + '">' +
					'<input type="radio" name="wffn-bundles-pt-type" form="' + NOFORM + '" value="' + esc( t.key ) + '"' + ( on ? ' checked' : '' ) + '>' +
					'<span class="wffn-bundles-pt__type-radio" aria-hidden="true"></span><span>' + esc( t.label ) + '</span>' + ( isPitch( t.key ) ? proChip() : '' ) + '</label>'
				);
			} )
			.join( '' );
		// Pitch screens render the CTA inside the copy column.
		var note = pitch ? proNote( pitch ) : D.product.saved ? '' : T.saveFirst;
		var cta =
			'<div class="wffn-bundles-pt__cta"><div class="wffn-bundles-pt__cta-row">' +
			( pitch
				? proAction( 'wffn-bundles-pt__btn wffn-bundles-pt__btn--pro' )
				: '<button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--primary" data-act="create"' + ( state.busy || ! D.product.saved ? ' disabled' : '' ) + '>' + esc( state.busy ? T.creating : T.create ) + '</button>' ) +
			( hasList ? '<button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--ghost" data-act="cancel">' + esc( T.cancel ) + '</button>' : '' ) +
			// Before the merchant has chosen, they may turn Bundles off from here instead.
			( D.state ? '' : '<button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--secondary" data-act="decline"' + ( state.declining || state.busy ? ' disabled' : '' ) + '>' + esc( state.declining ? T.declining : T.decline ) + '</button>' ) +
			'</div>' + ( note ? '<p class="wffn-bundles-pt__muted">' + esc( note ) + '</p>' : '' ) + errorHtml() + '</div>';
		return (
			'<div class="wffn-bundles-pt__head">' + head + '</div>' +
			'<div class="wffn-bundles-pt__types' + ( offeredTypes().length > 3 ? ' is-many' : '' ) + '" role="radiogroup" aria-label="' + esc( T.bundleType ) + '">' + types + '</div>' +
			'<div class="wffn-bundles-pt__cols">' +
			( pitch
				? pitchView( pitch, cta )
				: '<div class="wffn-bundles-pt__fields" id="wffn-bundles-pt-fields">' + type.fields( state.form, c ) + '</div>' +
					'<div class="wffn-bundles-pt__previewcol"><span class="wffn-bundles-pt__label" id="wffn-bundles-pt-preview-label">' + esc( T.preview_h ) + '</span>' +
					'<div class="wffn-bundles-pt__preview" id="wffn-bundles-pt-preview" role="group" aria-labelledby="wffn-bundles-pt-preview-label">' + type.preview( state.form, c ) + '</div></div>' ) +
			'</div>' +
			( pitch ? '' : cta )
		);
	}

	function ctx() {
		return { product: D.product, T: T, esc: esc, money: money, nid: nid, fmt: fmt, results: null, searching: false, pvOption: state.pvOption };
	}

	function freshForm( key ) {
		state.typeKey = key;
		state.formKey = key;
		state.form = registry[ key ].defaults( ctx() );
		state.error = '';
		state.picks = {};
		state.pvOption = null;
	}

	function ensureForm() {
		if ( isPitch( state.typeKey ) || ( state.form && registry[ state.typeKey ] ) ) {
			return true;
		}
		var first = formTypes()[ 0 ];
		if ( ! first ) {
			return false;
		}
		freshForm( first.key );
		return true;
	}

	// Switches Bundles on store-wide, then opens the tab as it is when on.
	function enable() {
		if ( state.enabling ) {
			return;
		}
		state.enabling = true;
		state.enableError = '';
		render();
		api( 'PUT', '/settings', { bundlesState: 'enabled' } )
			.then( function () {
				D.state = 'enabled';
				state.enabling = false;
				announce( T.activated );
				render();
				load();
			} )
			.catch( function ( err ) {
				state.enabling = false;
				state.enableError = err.message;
				render();
				focusBack( '[data-act="enable"]' );
			} );
	}

	// Switches Bundles off store-wide, then shows the tab as it is when off.
	function decline() {
		if ( state.declining ) {
			return;
		}
		state.declining = true;
		setError( '' );
		render();
		api( 'PUT', '/settings', { bundlesState: 'disabled' } )
			.then( function () {
				D.state = 'disabled';
				state.declining = false;
				announce( T.declined );
				render();
				focusBack( '[data-act="enable"]' );
			} )
			.catch( function ( err ) {
				state.declining = false;
				state.error = err.message || T.error;
				render();
				focusBack( '[data-act="decline"]' );
			} );
	}

	function offView() {
		return '<h3 class="wffn-bundles-pt__off-title">' + esc( T.bundles ) + '</h3>' +
			'<div class="wffn-bundles-pt__off-note" data-testid="wffn-bundles-pt-off">' +
			'<div class="wffn-bundles-pt__off-copy">' +
			'<p class="wffn-bundles-pt__off-text">' + esc( T.offText ) + '</p>' +
			'<p class="wffn-bundles-pt__off-desc">' + esc( T.offDesc ) + '</p>' +
			'</div>' +
			'<button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--primary wffn-bundles-pt__off-cta" data-act="enable"' + ( state.enabling ? ' disabled' : '' ) + '>' + esc( state.enabling ? T.activating : T.turnOn ) + '</button>' +
			'</div>' +
			( state.enableError ? '<p class="wffn-bundles-pt__error wffn-bundles-pt__off-error" role="alert">' + esc( state.enableError ) + '</p>' : '' );
	}

	function render() {
		root.classList.toggle( 'is-off', 'disabled' === D.state );
		// Not chosen yet works as on: the first bundle made here switches Bundles on.
		if ( 'disabled' === D.state ) {
			root.innerHTML = offView();
			return;
		}
		if ( ! state.loaded ) {
			root.innerHTML = state.loadError
				? '<p class="wffn-bundles-pt__error" role="alert">' + esc( T.error ) + '</p><div class="wffn-bundles-pt__cta-row"><button type="button" class="wffn-bundles-pt__btn wffn-bundles-pt__btn--primary" data-act="load">' + esc( T.retry ) + '</button></div>'
				: '<p class="wffn-bundles-pt__muted" role="status">' + esc( T.loading ) + '</p>';
			return;
		}
		if ( 'form' === state.view && ! ensureForm() ) {
			state.view = 'list';
		}
		destroyProducts();
		destroyAssign();
		destroySortable();
		root.innerHTML = 'list' === state.view ? listView() : formView();
		initProducts();
		initAssign();
		initSortable();
	}

	function refreshPreview() {
		var el = document.getElementById( 'wffn-bundles-pt-preview' );
		if ( el && registry[ state.typeKey ] ) {
			el.innerHTML = registry[ state.typeKey ].preview( state.form, ctx() );
		}
	}

	// Which offer the preview shows chosen; the form's own default is untouched.
	function pickOption( id ) {
		state.pvOption = id;
		refreshPreview();
		[].some.call( root.querySelectorAll( '[data-act="pvoption"]' ), function ( el ) {
			return id === el.getAttribute( 'data-id' ) && ( el.focus(), true );
		} );
	}

	function focusKey( el ) {
		if ( ! el || ! root.contains( el ) ) {
			return '';
		}
		if ( el.id ) {
			return '#' + el.id;
		}
		var act = el.getAttribute( 'data-act' ) || el.getAttribute( 'data-f' );
		if ( ! act ) {
			return '';
		}
		var attr = el.hasAttribute( 'data-act' ) ? 'data-act' : 'data-f';
		return '[' + attr + '="' + act + '"]' + [ 'data-i', 'data-v', 'data-id' ].map( function ( a ) {
			return el.hasAttribute( a ) ? '[' + a + '="' + el.getAttribute( a ) + '"]' : '';
		} ).join( '' ) + ( 'radio' === el.type ? '[value="' + el.value + '"]' : '' );
	}

	function focusBack( key, fallback ) {
		var el = ( key && root.querySelector( key ) ) || ( fallback && root.querySelector( fallback ) );
		if ( el && el.classList.contains( 'wffn-bundles-pt__product-search' ) ) {
			el = el.parentNode.querySelector( '.select2-selection' ) || el;
		}
		if ( el ) {
			el.focus();
		}
	}

	function refreshFields( fallback ) {
		var el = document.getElementById( 'wffn-bundles-pt-fields' );
		if ( ! el || ! registry[ state.typeKey ] ) {
			return;
		}
		var key = focusKey( document.activeElement );
		destroyProducts();
		el.innerHTML = registry[ state.typeKey ].fields( state.form, ctx() );
		initProducts();
		focusBack( key, fallback );
	}

	function switchType( key ) {
		if ( key === state.typeKey || ! ( registry[ key ] || isPitch( key ) ) ) {
			return;
		}
		// The pitch has no form, so the one being edited is kept for the way back.
		if ( isPitch( key ) || ( key === state.formKey && state.form ) ) {
			state.typeKey = key;
			state.error = '';
			render();
			focusBack( '', '.wffn-bundles-pt__type.is-on input' );
			return;
		}
		var prev = state.form || {};
		var untouched = state.formKey ? registry[ state.formKey ].defaults( ctx() ) : {};
		freshForm( key );
		[ 'name', 'extra', 'includeSelf', 'discountType', 'discountValue' ].forEach( function ( k ) {
			// A value the merchant never changed gives way to the new type's own default.
			if ( k in state.form && k in prev && prev[ k ] !== untouched[ k ] ) {
				state.form[ k ] = prev[ k ];
			}
		} );
		render();
		focusBack( '', '.wffn-bundles-pt__type.is-on input' );
	}

	function showList() {
		state.view = 'list';
		state.error = '';
		render();
		focusBack( '', '[data-act="unassign"]' );
	}

	// A select2 whose markup is replaced while open leaves its dropdown on the body, stealing focus.
	function canSort() {
		return state.bundles.length > 1 && !! ( $ && $.fn.sortable );
	}

	function destroySortable() {
		var body = root.querySelector( '.wffn-bundles-pt__table.is-sortable tbody' );
		if ( body && $( body ).sortable( 'instance' ) ) {
			$( body ).sortable( 'destroy' );
		}
	}

	function initSortable() {
		var body = root.querySelector( '.wffn-bundles-pt__table.is-sortable tbody' );
		if ( ! body || state.rowBusy ) {
			return;
		}
		$( body ).sortable( {
			handle: '.wffn-bundles-pt__grip',
			// The default refuses drags that start on a button, and the handle is one.
			cancel: '',
			axis: 'y',
			items: '> tr',
			placeholder: 'wffn-bundles-pt__row-ph',
			// Table cells collapse once their row is lifted out of the flow.
			helper: function ( e, row ) {
				var cells = row.children();
				var clone = row.clone();
				clone.children().each( function ( i ) {
					$( this ).width( cells.eq( i ).width() );
				} );
				return clone.addClass( 'is-dragging' );
			},
			start: function ( e, ui ) {
				ui.placeholder.html( '<td colspan="' + ui.item.children().length + '"></td>' );
			},
			update: function ( e, ui ) {
				var ids = $( body ).children( 'tr' ).map( function () {
					return this.getAttribute( 'data-id' );
				} ).get();
				saveOrder( ids, ui.item.attr( 'data-id' ) );
			},
		} );
	}

	function moveRow( id, step ) {
		if ( state.rowBusy ) {
			return;
		}
		var ids = state.bundles.map( function ( b ) {
			return b.id;
		} );
		var from = ids.indexOf( id );
		var to = from + step;
		if ( from < 0 || to < 0 || to >= ids.length ) {
			return;
		}
		ids.splice( to, 0, ids.splice( from, 1 )[ 0 ] );
		saveOrder( ids, id );
	}

	function saveOrder( ids, movedId ) {
		var before = state.bundles;
		var byId = {};
		before.forEach( function ( b ) {
			byId[ b.id ] = b;
		} );
		state.bundles = ids.map( function ( id ) {
			return byId[ id ];
		} ).filter( Boolean );
		state.rowBusy = movedId;
		state.created = '';
		state.error = '';
		render();
		var grip = '[data-act="grip"][data-id="' + movedId + '"]';
		focusBack( grip );
		var moved = byId[ movedId ];
		// A draft dragged above the bundle that is showing goes live, so the drag is what makes it show.
		var firstLive = before.filter( function ( b ) {
			return 'live' === b.state;
		} )[ 0 ];
		var activate = moved && 'draft' === moved.state && ! pitchFor( moved.type ) && ( ! firstLive || ids.indexOf( movedId ) < ids.indexOf( firstLive.id ) );
		( activate ? api( 'PUT', '/bundles/' + encodeURIComponent( movedId ) + '/status', { status: 1 } ) : Promise.resolve() )
			.then( function () {
				return api( 'POST', '/bundles/reorder', { order: ids } );
			} )
			.then( reload )
			.then( function () {
				state.rowBusy = '';
				render();
				focusBack( grip );
				var shown = state.bundles.filter( function ( b ) {
					return 'live' === b.state;
				} )[ 0 ];
				announce( fmt( T.movedTo, moved ? moved.name : '', ids.indexOf( movedId ) + 1, ids.length ) + ( shown ? ' ' + fmt( T.nowShows, shown.name ) : '' ) );
			} )
			.catch( function ( e ) {
				state.bundles = before;
				state.rowBusy = '';
				state.error = e.message || T.error;
				render();
				focusBack( grip );
			} );
	}

	function destroyProducts() {
		var el = root.querySelector( '.wffn-bundles-pt__product-search' );
		if ( el && $ && $( el ).data( 'select2' ) ) {
			$( el ).selectWoo( 'close' ).selectWoo( 'destroy' );
		}
	}

	function initProducts() {
		var el = root.querySelector( '.wffn-bundles-pt__product-search' );
		if ( ! el || ! $ || ! $.fn.selectWoo ) {
			return;
		}
		var seq = 0;
		$( el )
			.selectWoo( {
				width: '100%',
				placeholder: T.searchProducts,
				minimumInputLength: 2,
				dropdownCssClass: 'wffn-bundles-pt-dropdown',
				language: {
					noResults: function () {
						return T.noResults;
					},
					searching: function () {
						return T.searching;
					},
					errorLoading: function () {
						return T.error;
					},
					inputTooShort: function ( a ) {
						var n = a.minimum - a.input.length;
						return 1 === n ? T.typeOneMore : fmt( T.typeMore, n );
					},
				},
				ajax: {
					delay: 250,
					transport: function ( params, success, failure ) {
						var mine = ++seq;
						var term = ( params.data && ( params.data.term || params.data.q ) ) || '';
						api( 'GET', '/products?per_page=20&include_variations=false&search=' + encodeURIComponent( term ) )
							.then( function ( data ) {
								if ( mine === seq ) {
									success( data );
								}
							} )
							.catch( failure );
						return { abort: function () {} };
					},
					processResults: function ( data ) {
						var taken = ( false === state.form.includeSelf ? [] : [ String( D.product.id ) ] ).concat(
							( state.form.extra || [] ).map( function ( x ) {
								return String( x.id );
							} )
						);
						return {
							results: ( ( data && data.items ) || [] )
								.filter( function ( p ) {
									return taken.indexOf( String( p.id ) ) === -1;
								} )
								.map( function ( p ) {
									return { id: String( p.id ), text: p.name, name: p.name, type: p.type || '', priceRaw: Number( p.priceRaw ) || 0, image: p.image || '' };
								} ),
						};
					},
				},
				templateResult: function ( item ) {
					if ( item.loading || ! item.id ) {
						return item.text;
					}
					return $( '<span class="wffn-bundles-pt-result">' + thumbImg( item, 'wffn-bundles-pt-result__thumb' ) + '<span class="wffn-bundles-pt-result__name">' + esc( item.name ) + '</span><span class="wffn-bundles-pt-result__price">' + esc( money( item.priceRaw ) ) + '</span></span>' );
				},
			} )
			.on( 'select2:select', function ( e ) {
				var type = registry[ state.typeKey ];
				var p = e.params.data;
				if ( String( p.id ) === String( D.product.id ) ) {
					state.form.includeSelf = true;
				} else if ( type && type.pick ) {
					type.pick( state.form, { id: p.id, name: p.name, type: p.type, priceRaw: p.priceRaw, image: p.image } );
					loadOptions( state.form, p );
				}
				setError( '' );
				// After select2 has finished closing its own dropdown.
				window.setTimeout( function () {
					refreshFields();
					refreshPreview();
					focusBack( '', '.wffn-bundles-pt__product-search' );
				}, 0 );
			} );
	}

	// Search skips variations for speed; a picked variable product fetches its own.
	function loadOptions( f, p ) {
		if ( 'variable' !== p.type ) {
			return;
		}
		api( 'GET', '/products?include=' + encodeURIComponent( p.id ) )
			.then( function ( data ) {
				var row = ( ( data && data.items ) || [] )[ 0 ];
				var item = row && f.extra.filter( function ( x ) {
					return String( x.id ) === String( row.id );
				} )[ 0 ];
				if ( item ) {
					readVariants( item, row );
					refreshPreview();
				}
			} )
			.catch( function () {} );
	}

	function create() {
		if ( state.busy || isPitch( state.typeKey ) ) {
			return;
		}
		if ( ! D.product.saved ) {
			setError( T.saveFirst );
			return;
		}
		var type = registry[ state.typeKey ];
		var meta = typeMeta( state.typeKey );
		var built;
		try {
			built = type.payload( state.form, ctx() );
		} catch ( e ) {
			fieldError( e );
			return;
		}
		var payload = Object.assign(
			{
				discountType: meta.id,
				status: 1,
				schedule: { alwaysAvailable: true, startDate: '', endDate: '' },
				productSelection: { mode: 1, products: [ { id: String( D.product.id ) } ], selectedCategories: [], excludeEnabled: false, excludedProducts: [], excludedCategories: [] },
			},
			built
		);
		state.busy = true;
		clearFieldError();
		setError( '' );
		var btn = root.querySelector( '[data-act="create"]' );
		if ( btn ) {
			btn.disabled = true;
			btn.textContent = T.creating;
		}
		api( 'POST', '/bundles', payload ).then(
			function ( item ) {
				state.created = String( ( item && item.id ) || '' );
				state.busy = false;
				// The first bundle switches Bundles on.
				D.state = D.state || 'enabled';
				state.form = null;
				state.view = 'list';
				// Created already, so a failed refresh must not offer the form again.
				reload()
					.catch( function () {
						state.error = T.refreshFailed;
					} )
					.then( function () {
						render();
						announce( T.created );
						focusBack( '', '.wffn-bundles-pt__row.is-new [data-act="unassign"]' );
					} );
			},
			function ( e ) {
				state.busy = false;
				state.error = e.message || T.error;
				render();
				focusBack( '', '[data-act="create"]' );
			}
		);
	}

	// Bundles and a variable product's options are fetched when the tab first opens, not with the page.
	function load() {
		if ( 'disabled' === D.state || state.loaded || state.loading ) {
			return;
		}
		state.loading = true;
		state.loadError = false;
		render();
		reload( true )
			.then( function ( variants ) {
				if ( variants && variants.attributes ) {
					readVariants( D.product, variants );
				}
				state.loaded = true;
				state.view = state.bundles.length ? 'list' : 'form';
			} )
			.catch( function () {
				state.loadError = true;
			} )
			.then( function () {
				state.loading = false;
				render();
			} );
	}

	function reload( withVariants ) {
		var body = new URLSearchParams( { action: 'wffn_bundles_product_tab_bundles', product_id: D.product.id, _wpnonce: D.listNonce, variants: withVariants ? 1 : '' } );
		return fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.catch( function () {
				throw new Error( T.error );
			} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( T.error );
				}
				state.bundles = j.data.bundles;
				state.assignable = j.data.assignable || [];
				state.linked = j.data.linked || state.linked;
				return j.data.variants;
			} );
	}

	root.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '[data-act]' );
		if ( ! el || ! root.contains( el ) ) {
			return;
		}
		var act = el.getAttribute( 'data-act' );
		if ( 'A' === el.tagName ) {
			e.preventDefault();
		}
		if ( 'load' === act ) {
			load();
		} else if ( 'pvoption' === act ) {
			if ( ! e.target.closest( 'select' ) ) {
				pickOption( el.getAttribute( 'data-id' ) );
			}
		} else if ( 'back' === act || 'cancel' === act ) {
			showList();
		} else if ( 'unassign' === act ) {
			unassign( el.getAttribute( 'data-id' ) );
		} else if ( 'assign' === act ) {
			assign();
		} else if ( 'create' === act ) {
			create();
		} else if ( 'activate-pro' === act ) {
			activatePro( el );
		} else if ( 'enable' === act ) {
			enable();
		} else if ( 'decline' === act ) {
			decline();
		} else if ( registry[ state.typeKey ] && registry[ state.typeKey ].click ) {
			var fallback = { unpick: '.wffn-bundles-pt__product-search', addself: '.wffn-bundles-pt__product-search', 'unpick-self': '[data-act="addself"]' }[ act ] || '[data-act="addtier"]';
			if ( registry[ state.typeKey ].click( state.form, el, act ) ) {
				refreshFields( fallback );
				refreshPreview();
			}
		}
	} );

	root.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key && 'INPUT' === e.target.tagName ) {
			e.preventDefault();
		}
		if ( ( 'Enter' === e.key || ' ' === e.key ) && 'pvoption' === e.target.getAttribute( 'data-act' ) ) {
			e.preventDefault();
			pickOption( e.target.getAttribute( 'data-id' ) );
		}
		var grip = e.target.closest && e.target.closest( '[data-act="grip"]' );
		if ( grip && ( 'ArrowUp' === e.key || 'ArrowDown' === e.key ) ) {
			e.preventDefault();
			moveRow( grip.getAttribute( 'data-id' ), 'ArrowUp' === e.key ? -1 : 1 );
		}
	} );

	root.addEventListener( 'input', function ( e ) {
		var el = e.target;
		if ( el.closest( '.wffn-bundles-pt__field.is-invalid' ) ) {
			clearFieldError();
		}
		var type = registry[ state.typeKey ];
		if ( type && type.input && el.hasAttribute( 'data-f' ) && 'radio' !== el.type ) {
			type.input( state.form, el );
			refreshPreview();
		}
	} );

	root.addEventListener( 'change', function ( e ) {
		var el = e.target;
		if ( 'wffn-bundles-pt-type' === el.name ) {
			switchType( el.value );
			return;
		}
		var pv = el.getAttribute( 'data-pv' );
		if ( pv ) {
			state.picks[ pv ] = el.value;
			refreshPreview();
			[].some.call( root.querySelectorAll( '[data-pv]' ), function ( next ) {
				return pv === next.getAttribute( 'data-pv' ) && ( next.focus(), true );
			} );
			return;
		}
		var type = registry[ state.typeKey ];
		if ( type && type.change && el.hasAttribute( 'data-f' ) ) {
			if ( type.change( state.form, el ) ) {
				refreshFields();
			}
			refreshPreview();
		}
	} );

	function discounted( price, mode, value ) {
		var v = 'no_discount' === mode ? 0 : Number( value ) || 0;
		return Math.max( 0, 'fixed' === mode ? price - v : price * ( 1 - v / 100 ) );
	}

	function labelled( label, id ) {
		return '<label class="wffn-bundles-pt__label" for="' + id + '">' + esc( label ) + '</label>';
	}

	function unitField( key, value, unit, o ) {
		o = o || {};
		var attrs = ' data-f="' + esc( key ) + '"' + ( null != o.i ? ' data-i="' + o.i + '"' : '' ) + ( o.id ? ' id="' + esc( o.id ) + '"' : '' ) +
			( null != o.min ? ' min="' + o.min + '"' : '' ) + ( null != o.max && '' !== o.max ? ' max="' + o.max + '"' : '' ) + ' step="' + ( o.step || 'any' ) + '"' +
			( o.label ? ' aria-label="' + esc( o.label ) + '"' : '' );
		return '<span class="wffn-bundles-pt__unit' + ( o.narrow ? ' wffn-bundles-pt__unit--narrow' : '' ) + '"><input type="number"' + attrs + ' value="' + esc( value ) + '"><em aria-hidden="true">' + esc( unit ) + '</em></span>';
	}

	// A discount type dropdown joined to its value, as the bundle editor draws it.
	function typedDiscount( type, value, o ) {
		var none = 'no_discount' === type;
		var types = [ [ 'percentage', '%', T.percentage ], [ 'fixed', D.currency.symbol, T.fixedAmount ] ];
		var at = function ( key ) {
			return ' data-f="' + key + '"' + ( null != o.i ? ' data-i="' + o.i + '"' : '' );
		};
		return (
			'<span class="wffn-bundles-pt__unit wffn-bundles-pt__unit--typed"><select' + at( o.typeKey ) + ' aria-label="' + esc( T.discountType ) + '">' +
			types
				.map( function ( t ) {
					return '<option value="' + t[ 0 ] + '" title="' + esc( t[ 2 ] ) + '"' + ( t[ 0 ] === type ? ' selected' : '' ) + '>' + esc( t[ 1 ] ) + '</option>';
				} )
				.join( '' ) +
			'</select><input type="number"' + at( o.valueKey ) + ( o.id ? ' id="' + o.id + '"' : '' ) + ' min="0"' + ( 'percentage' === type ? ' max="100"' : '' ) + ' step="any"' +
			( o.id ? '' : ' aria-label="' + esc( T.discount ) + '"' ) + ' value="' + esc( value ) + '"' + ( none ? ' disabled' : '' ) + '></span>'
		);
	}

	function discountField( f, c ) {
		return (
			'<div class="wffn-bundles-pt__field">' + labelled( c.T.discount, 'wffn-bundles-pt-f-discountValue' ) +
			typedDiscount( f.discountType, f.discountValue, { typeKey: 'discountType', valueKey: 'discountValue', id: 'wffn-bundles-pt-f-discountValue' } ) + '</div>'
		);
	}

	function textField( label, key, value, c ) {
		var id = 'wffn-bundles-pt-f-' + key;
		return '<div class="wffn-bundles-pt__field">' + labelled( label, id ) + '<input type="text" class="wffn-bundles-pt__input" id="' + id + '" data-f="' + key + '" value="' + c.esc( value ) + '"></div>';
	}

	function thumbImg( p, cls ) {
		return p && p.image ? '<img class="' + cls + '" src="' + esc( p.image ) + '" alt="">' : '<span class="' + cls + '"></span>';
	}

	// The product being edited is in the bundle until the merchant removes it.
	function members( f, c ) {
		return ( false === f.includeSelf ? [] : [ c.product ] ).concat( f.extra );
	}

	function itemRow( p, i, c, locked ) {
		var self = i < 0;
		var name = p.name || c.T.thisProduct;
		return (
			'<li class="wffn-bundles-pt__item">' + thumbImg( p, 'wffn-bundles-pt__item-thumb' ) +
			'<span class="wffn-bundles-pt__item-text"><span class="wffn-bundles-pt__item-name" title="' + c.esc( name ) + '">' + c.esc( name ) + '</span>' +
			'<span class="wffn-bundles-pt__item-meta">' + ( self && p.name ? '<em class="wffn-bundles-pt__item-tag">' + c.esc( c.T.thisProduct ) + '</em>' : '' ) +
			'<span class="wffn-bundles-pt__item-price">' + c.esc( c.money( p.priceRaw ) ) + '</span></span></span>' +
			( locked
				? '<span class="wffn-bundles-pt__x wffn-bundles-pt__x--spacer"></span>'
				: '<button type="button" class="wffn-bundles-pt__x" data-act="' + ( self ? 'unpick-self' : 'unpick' ) + '"' + ( self ? '' : ' data-i="' + i + '"' ) + ' aria-label="' + c.esc( fmt( c.T.removeItem, name ) ) + '"></button>' ) +
			'</li>'
		);
	}

	// How many products the bundle still needs, in words; empty once it has two.
	function shortfall( f, c ) {
		var missing = 2 - members( f, c ).length;
		if ( missing <= 0 ) {
			return '';
		}
		return 1 === missing ? c.T.needOneMore : fmt( c.T.needMore, missing );
	}

	function checkProducts( f, c ) {
		var short = shortfall( f, c );
		if ( short ) {
			var err = new Error( short );
			err.field = '#wffn-bundles-pt-products';
			throw err;
		}
	}

	// `o.lockSelf` keeps the edited product in the list; `o.max` caps the products picked beside it; `o.hint` replaces the two-product hint.
	function productsField( f, c, o ) {
		o = o || {};
		var self = false !== f.includeSelf;
		var hint = null == o.hint ? shortfall( f, c ) : o.hint;
		var full = !! o.max && f.extra.length >= o.max;
		var rows = ( self ? [ itemRow( c.product, -1, c, o.lockSelf ) ] : [] ).concat(
			f.extra.map( function ( p, i ) {
				return itemRow( p, i, c );
			} )
		);
		return (
			'<div class="wffn-bundles-pt__field">' + labelled( c.T.products, 'wffn-bundles-pt-products' ) +
			'<select class="wffn-bundles-pt__product-search" id="wffn-bundles-pt-products"' + ( full ? ' disabled' : '' ) + '><option></option></select>' +
			( rows.length ? '<ul class="wffn-bundles-pt__items">' + rows.join( '' ) + '</ul>' : '' ) +
			( self ? '' : '<button type="button" class="wffn-bundles-pt__addrow" data-act="addself">' + PLUS + c.esc( c.T.addSelfBack ) + '</button>' ) +
			( hint ? '<p class="wffn-bundles-pt__field-hint">' + c.esc( hint ) + '</p>' : '' ) +
			( full ? '<p class="wffn-bundles-pt__muted">' + c.esc( fmt( c.T.fbtFull, o.max ) ) + '</p>' : '' ) +
			'</div>'
		);
	}

	function common( f, e ) {
		var key = e.getAttribute( 'data-f' );
		f[ key ] = e.value;
		var value = 'discountType' === key && document.getElementById( 'wffn-bundles-pt-f-discountValue' );
		if ( value ) {
			value.max = 'percentage' === e.value ? '100' : '';
			value.disabled = 'no_discount' === e.value;
		}
	}

	function commonClick( f, el, act ) {
		if ( 'unpick' === act ) {
			f.extra.splice( Number( el.getAttribute( 'data-i' ) ), 1 );
			return true;
		}
		if ( 'unpick-self' === act || 'addself' === act ) {
			f.includeSelf = 'addself' === act;
			return true;
		}
		return false;
	}

	function pickExtra( f, p ) {
		if ( ! f.extra.some( function ( x ) { return String( x.id ) === String( p.id ); } ) ) {
			f.extra.push( { id: String( p.id ), name: p.name, type: p.type || '', priceRaw: Number( p.priceRaw ) || 0, image: p.image || '' } );
		}
	}

	function slots( f, c, total ) {
		var list = members( f, c );
		var out = [];
		for ( var i = 0; i < Math.max( total, list.length ); i++ ) {
			out.push( list[ i ] || null );
		}
		return out;
	}

	function gross( f, c ) {
		return members( f, c ).reduce( function ( s, p ) {
			return s + ( Number( p.priceRaw ) || 0 );
		}, 0 );
	}

	// A variable product's dearest variation; its own price otherwise.
	function priceTop( p ) {
		return Math.max( Number( p.priceRaw ) || 0, Number( p.priceMax ) || 0 );
	}

	// Takes a variable product's options and variation prices from its product row.
	function readVariants( p, row ) {
		p.axes = ( row.attributes || [] ).map( function ( a ) {
			var options = a.options || [];
			return {
				label: a.name,
				def: a.default || options[ 0 ] || '',
				options: options.map( function ( o ) {
					return { value: o, label: ( a.labels && a.labels[ o ] ) || o };
				} ),
			};
		} );
		p.variations = ( row.variations || [] ).map( function ( v ) {
			return { attributes: v.attributes || {}, priceRaw: Number( v.priceRaw ) || 0, purchasable: false !== v.purchasable };
		} );
		p.priceMax = p.variations.reduce( function ( m, v ) {
			return Math.max( m, v.priceRaw );
		}, 0 );
	}

	function chosen( p, unit, axis ) {
		var v = state.picks[ p.id + '|' + unit + '|' + axis.label ];
		return null == v ? axis.def : v;
	}

	// A unit's price for the options chosen in the preview; the full range until one matches.
	function unitRange( p, unit ) {
		var axes = ( p && p.axes ) || [];
		if ( ! axes.length ) {
			return [ Number( p && p.priceRaw ) || 0, priceTop( p || {} ) ];
		}
		var hits = ( p.variations || [] ).filter( function ( v ) {
			return axes.every( function ( a ) {
				var val = v.attributes[ a.label ];
				return ! val || val === chosen( p, unit, a );
			} );
		} );
		var hit = hits.filter( function ( v ) {
			return v.purchasable;
		} )[ 0 ] || hits[ 0 ];
		return hit ? [ hit.priceRaw, hit.priceRaw ] : [ Number( p.priceRaw ) || 0, priceTop( p ) ];
	}

	function sumRange( list ) {
		return list.reduce( function ( s, r ) {
			return [ s[ 0 ] + r[ 0 ], s[ 1 ] + r[ 1 ] ];
		}, [ 0, 0 ] );
	}

	// Real dropdowns, one per attribute; a choice reprices the preview and is never saved.
	function variantSelects( p, unit ) {
		var axes = ( p && p.axes ) || [];
		if ( ! axes.length ) {
			return '';
		}
		return (
			'<span class="wffn-bundles-pt__pv-selects">' +
			axes
				.map( function ( a ) {
					var cur = chosen( p, unit, a );
					return (
						'<select class="wffn-bundles-pt__pv-select" data-pv="' + esc( p.id + '|' + unit + '|' + a.label ) + '" aria-label="' + esc( a.label ) + '">' +
						a.options
							.map( function ( o ) {
								return '<option value="' + esc( o.value ) + '"' + ( o.value === cur ? ' selected' : '' ) + '>' + esc( o.label ) + '</option>';
							} )
							.join( '' ) +
						'</select>'
					);
				} )
				.join( '' ) +
			'</span>'
		);
	}

	// The preview no longer opens with the product; add-on form scripts built before that still call it.
	function head() {
		return '';
	}

	function thumb( p, c ) {
		return '<span class="wffn-bundles-pt__pv-img">' + ( p && p.image ? '<img src="' + c.esc( p.image ) + '" alt="">' : '' ) + '</span>';
	}

	function saveText( f, c ) {
		var v = Number( f.discountValue ) || 0;
		if ( 'no_discount' === f.discountType || v <= 0 ) {
			return c.T.regularPrice;
		}
		return fmt( c.T.saveX, 'percentage' === f.discountType ? trimNum( v ) + '%' : c.money( v ) );
	}

	function checkDiscount( f, T ) {
		if ( 'no_discount' === f.discountType ) {
			return;
		}
		var pct = 'percentage' === f.discountType;
		var raw = String( f.discountValue == null ? '' : f.discountValue ).trim();
		var v = Number( raw );
		if ( '' === raw || isNaN( v ) || v < 0 || ( pct && v > 100 ) ) {
			throw new Error( pct ? T.badPercent : T.badAmount );
		}
	}

	// Stores no discount as 0% off, the only form these bundle types read.
	function savedDiscount( f ) {
		var none = 'no_discount' === f.discountType;
		return { type: none ? 'percentage' : f.discountType, value: none ? 0 : Number( f.discountValue ) || 0 };
	}

	// The price snapshot lets the server's rules check a fixed discount; it is stripped on save.
	function ref( p ) {
		return { id: String( p.id ), priceRaw: Number( p.priceRaw ) || 0 };
	}

	function option( title, sub, price, on, extra, id ) {
		var pick = null == id ? '' : ' data-act="pvoption" data-id="' + esc( id ) + '" role="radio" aria-checked="' + !! on + '" tabindex="0"';
		return (
			'<div class="wffn-bundles-pt__pv-option' + ( on ? ' is-on' : '' ) + '"' + pick + '><div class="wffn-bundles-pt__pv-option-row"><span class="wffn-bundles-pt__pv-radio" aria-hidden="true"></span>' +
			'<div class="wffn-bundles-pt__pv-text"><div class="wffn-bundles-pt__pv-name">' + esc( title ) + '</div>' + ( sub ? '<div class="wffn-bundles-pt__pv-sub">' + esc( sub ) + '</div>' : '' ) + '</div>' +
			'<div class="wffn-bundles-pt__pv-price">' + price + '</div></div>' + ( extra || '' ) + '</div>'
		);
	}

	function line( p ) {
		return (
			'<div class="wffn-bundles-pt__pv-line' + ( p ? '' : ' is-ghost' ) + '">' + thumbImg( p, 'wffn-bundles-pt__pv-line-thumb' ) +
			'<span class="wffn-bundles-pt__pv-line-text"><span class="wffn-bundles-pt__pv-line-name">' + esc( p ? p.name || T.thisProduct : T.nextProduct ) + '</span>' + variantSelects( p, 0 ) + '</span>' +
			( p ? '<span class="wffn-bundles-pt__pv-sub">' + esc( range.apply( null, unitRange( p, 0 ) ) ) + '</span>' : '' ) + '</div>'
		);
	}

	// Drawn like the storefront's quantity stepper on a picked card.
	function stepper( qty ) {
		return (
			'<div class="wffn-bundles-pt__pv-stepper"><span aria-hidden="true">\u2212</span>' +
			'<span class="wffn-bundles-pt__pv-step-qty"><span class="screen-reader-text">' + esc( T.added ) + ' </span>' + esc( qty ) + '</span><span aria-hidden="true">+</span></div>'
		);
	}

	// Back to the preview's default option, after an edit that moves it.
	function resetPreview() {
		state.pvOption = null;
	}

	window.wffnBundlesProductTab = {
		registerType: function ( key, def ) {
			registry[ key ] = def;
		},
		helpers: {
			esc: esc, money: money, nid: nid, fmt: fmt, trimNum: trimNum, discounted: discounted, discountField: discountField, textField: textField,
			unitField: unitField, productsField: productsField, common: common, commonClick: commonClick, pickExtra: pickExtra, slots: slots,
			gross: gross, head: head, thumb: thumb, thumbImg: thumbImg, saveText: saveText, checkDiscount: checkDiscount, savedDiscount: savedDiscount, ref: ref, option: option, line: line, stepper: stepper, range: range, units: units, priceTop: priceTop, unitRange: unitRange, sumRange: sumRange, variantSelects: variantSelects, members: members, shortfall: shortfall, checkProducts: checkProducts,
			typedDiscount: typedDiscount, noForm: NOFORM, plus: PLUS, typeMeta: typeMeta, resetPreview: resetPreview,
		},
		data: D,
	};

	window.wffnBundlesProductTab.registerType( 'fixed', {
		defaults: function ( c ) {
			return { name: c.product.name ? fmt( c.T.kitName, c.product.name ) : '', heading: c.T.fixedHeading, discountType: 'percentage', discountValue: 20, extra: [], includeSelf: true };
		},
		fields: function ( f, c ) {
			return textField( c.T.bundleName, 'name', f.name, c ) + productsField( f, c ) + discountField( f, c ) + textField( c.T.heading, 'heading', f.heading, c );
		},
		preview: function ( f, c ) {
			var span = sumRange( members( f, c ).map( function ( p ) {
				return unitRange( p, 0 );
			} ) );
			var total = span[ 0 ];
			var totalTop = span[ 1 ];
			var now = discounted( total, f.discountType, f.discountValue );
			var nowTop = discounted( totalTop, f.discountType, f.discountValue );
			var list = members( f, c );
			var ghosts = list.length >= 2 ? 0 : 3 - list.length;
			var lines = list.map( line ).join( '' ) + new Array( ghosts + 1 ).join( line( null ) );
			return (
				'<div class="wffn-bundles-pt__pv-heading">' + c.esc( f.heading ) + '</div>' +
				option( fmt( c.T.buyN, 1 ), c.T.regularPrice, c.esc( range( Number( c.product.priceRaw ) || 0, priceTop( c.product ) ) ), 'single' === c.pvOption, '', 'single' ) +
				option( f.name, saveText( f, c ), '<span>' + c.esc( range( now, nowTop ) ) + '</span>' + ( now < total ? '<s>' + c.esc( range( total, totalTop ) ) + '</s>' : '' ), 'single' !== c.pvOption, '<div class="wffn-bundles-pt__pv-lines">' + lines + '</div>', 'bundle' ) +
				'<div class="wffn-bundles-pt__pv-cta">' + c.esc( c.T.addToCart ) + '</div>'
			);
		},
		input: common,
		change: function () {
			return false;
		},
		click: commonClick,
		pick: pickExtra,
		payload: function ( f, c ) {
			if ( ! String( f.name || '' ).trim() ) {
				throw new Error( c.T.needName );
			}
			checkProducts( f, c );
			checkDiscount( f, c.T );
			var items = members( f, c ).map( function ( p ) {
				return { id: c.nid( 'p' ), product: ref( p ), quantity: 1, discountType: 'percentage', discountValue: 0 };
			} );
			return {
				name: f.name.trim(),
				discountConfig: {
					bundleHeading: f.heading,
					fixedBundleTitle: f.name.trim(),
					fixedBundleDiscountScope: 'whole_bundle',
					fixedBundleDiscountType: savedDiscount( f ).type,
					fixedBundleDiscountValue: savedDiscount( f ).value,
					fixedBundleItems: items,
					fixedBundleShowSingle: true,
					fixedBundleSingleTitle: fmt( c.T.buyN, 1 ),
					fixedBundleSingleSubtitle: c.T.regularPrice,
				},
			};
		},
	} );

	var UNITS_SHOWN = 6;

	// One option box per unit, as the storefront asks a variable product's shopper.
	function units( qty, c ) {
		var axes = c.product.axes || [];
		if ( ! axes.length ) {
			return '';
		}
		var rows = [];
		for ( var i = 0; i < Math.min( qty, UNITS_SHOWN ); i++ ) {
			rows.push( '<div class="wffn-bundles-pt__pv-unit"><span class="wffn-bundles-pt__pv-unit-no">#' + ( i + 1 ) + '</span>' + variantSelects( c.product, i ) + '</div>' );
		}
		if ( qty > UNITS_SHOWN ) {
			rows.push( '<div class="wffn-bundles-pt__pv-sub">' + esc( fmt( c.T.moreUnits, qty - UNITS_SHOWN ) ) + '</div>' );
		}
		return (
			'<div class="wffn-bundles-pt__pv-units"><span class="wffn-bundles-pt__pv-sub">' + esc( axes.map( function ( a ) {
				return a.label;
			} ).join( ' \u00b7 ' ) ) + '</span>' + rows.join( '' ) + '</div>'
		);
	}

	// Prices a set as the storefront does: the discount on the added items, or on every line, rounded a line at a time.
	function setPrices( spans, scope, mode, value ) {
		var pow = Math.pow( 10, D.currency.decimals );
		var cut = function ( i ) {
			return i > 0 || 'set' === scope;
		};
		var side = function ( k ) {
			var total = spans.reduce( function ( s, r, i ) {
				return s + ( cut( i ) ? r[ k ] : 0 );
			}, 0 );
			var ratio = 1;
			if ( spans.length > 1 && value > 0 && total > 0 ) {
				ratio = Math.max( 0, 'fixed' === mode ? ( total - value ) / total : 1 - value / 100 );
			}
			return spans.map( function ( r, i ) {
				return cut( i ) ? Math.round( ( r[ k ] * ratio + Number.EPSILON ) * pow ) / pow : r[ k ];
			} );
		};
		return [ side( 0 ), side( 1 ) ];
	}

	function setRow( p, span, now, c ) {
		var host = p && String( p.id ) === String( c.product.id );
		var price = p ? ( now[ 0 ] < span[ 0 ] ? '<s>' + c.esc( range( span[ 0 ], span[ 1 ] ) ) + '</s>' : '' ) + '<span>' + c.esc( range( now[ 0 ], now[ 1 ] ) ) + '</span>' : '';
		return (
			'<div class="wffn-bundles-pt__pv-fbt-row' + ( p ? '' : ' is-ghost' ) + '"><span class="wffn-bundles-pt__pv-check' + ( p ? ' is-on' : '' ) + '" aria-hidden="true"></span>' +
			'<span class="wffn-bundles-pt__pv-line-text"><span class="wffn-bundles-pt__pv-line-name">' + ( host ? '<strong>' + c.esc( c.T.fbtThis ) + '</strong> ' : '' ) + c.esc( p ? p.name || c.T.thisProduct : c.T.nextProduct ) + '</span>' + variantSelects( p, 0 ) + '</span>' +
			'<span class="wffn-bundles-pt__pv-fbt-price">' + price + '</span></div>'
		);
	}

	function setMax( f ) {
		return Math.min( D.fbtMax, Math.max( 1, parseInt( f.max, 10 ) || 1 ) );
	}

	// The products the page shows with this one: the listed ones when set up by hand, else its linked products.
	function companions( f ) {
		var list = 'none' === f.fallback ? f.extra : state.linked[ f.fallback ] || [];
		return list.slice( 0, setMax( f ) );
	}

	// A hand-picked set with nothing picked shows nothing on the page.
	function emptySet( f, c ) {
		return 'none' === f.fallback && ! f.extra.length ? c.T.fbtNeedProduct : '';
	}

	window.wffnBundlesProductTab.registerType( 'fbt', {
		defaults: function ( c ) {
			return {
				name: c.product.name ? fmt( c.T.typedName, c.product.name, typeMeta( 'fbt' ).label ) : '',
				heading: c.T.fbtHeading,
				discountType: 'percentage',
				discountValue: 10,
				scope: 'companions',
				// Hand-picked products first; the other two read the product's own cross-sells and upsells.
				fallback: 'none',
				max: 3,
				extra: [],
			};
		},
		fields: function ( f, c ) {
			var off = 'no_discount' === f.discountType;
			var mode = off ? 'none' : f.scope;
			var scopes = [ [ 'companions', c.T.fbtCompanions ], [ 'set', c.T.fbtSet ], [ 'none', c.T.fbtNoDiscount ] ]
				.map( function ( o ) {
					return (
						'<label class="wffn-bundles-pt__choice"><input type="radio" name="wffn-bundles-pt-scope" form="' + NOFORM + '" data-f="discountMode" value="' + o[ 0 ] + '"' + ( o[ 0 ] === mode ? ' checked' : '' ) + '>' +
						'<span>' + c.esc( o[ 1 ] ) + '</span></label>'
					);
				} )
				.join( '' );
			var sources = [ [ 'cross_sells', c.T.fbtCross ], [ 'cross_sells_upsells', c.T.fbtCrossUp ], [ 'none', c.T.fbtManual ] ]
				.map( function ( o ) {
					return (
						'<label class="wffn-bundles-pt__choice"><input type="radio" name="wffn-bundles-pt-fallback" form="' + NOFORM + '" data-f="fallback" value="' + o[ 0 ] + '"' + ( o[ 0 ] === f.fallback ? ' checked' : '' ) + '>' +
						'<span>' + c.esc( o[ 1 ] ) + '</span></label>'
					);
				} )
				.join( '' );
			var manual = 'none' === f.fallback;
			return (
				textField( c.T.bundleName, 'name', f.name, c ) +
				'<div class="wffn-bundles-pt__field"><span class="wffn-bundles-pt__label" id="wffn-bundles-pt-fallback-label">' + c.esc( c.T.fbtFallback ) + '</span>' +
				'<div class="wffn-bundles-pt__choices wffn-bundles-pt__choices--list" role="radiogroup" aria-labelledby="wffn-bundles-pt-fallback-label">' + sources + '</div></div>' +
				( manual ? productsField( f, c, { lockSelf: true, max: D.fbtMax, hint: emptySet( f, c ) } ) : '' ) +
				'<div class="wffn-bundles-pt__field"><span class="wffn-bundles-pt__label" id="wffn-bundles-pt-scope-label">' + c.esc( c.T.discount ) + '</span>' +
				'<div class="wffn-bundles-pt__choices" role="radiogroup" aria-labelledby="wffn-bundles-pt-scope-label">' + scopes + '</div></div>' +
				( off ? '' : '<div class="wffn-bundles-pt__field">' + labelled( c.T.discountAmount, 'wffn-bundles-pt-f-discountValue' ) +
					typedDiscount( f.discountType, f.discountValue, { typeKey: 'discountType', valueKey: 'discountValue', id: 'wffn-bundles-pt-f-discountValue' } ) + '</div>' ) +
				textField( c.T.heading, 'heading', f.heading, c )
			);
		},
		preview: function ( f, c ) {
			var list = [ c.product ].concat( companions( f ) );
			var spans = list.map( function ( p ) {
				return unitRange( p, 0 );
			} );
			var saved = savedDiscount( f );
			var now = setPrices( spans, f.scope, saved.type, saved.value );
			var total = sumRange( spans );
			var final = [ 0, 1 ].map( function ( k ) {
				return now[ k ].reduce( function ( s, n ) {
					return s + n;
				}, 0 );
			} );
			// One empty slot until a product is added, so the set reads as a set.
			var shown = list.length > 1 ? list : list.concat( [ null ] );
			var strip = shown
				.map( function ( p, i ) {
					return '<span class="wffn-bundles-pt__pv-fbt-unit">' + ( i ? '<span class="wffn-bundles-pt__ico wffn-bundles-pt__ico--plus" aria-hidden="true"></span>' : '' ) + thumbImg( p, 'wffn-bundles-pt__pv-img' + ( p ? '' : ' is-ghost' ) ) + '</span>';
				} )
				.join( '' );
			var rows = shown
				.map( function ( p, i ) {
					return setRow( p, spans[ i ] || [ 0, 0 ], p ? [ now[ 0 ][ i ], now[ 1 ][ i ] ] : [ 0, 0 ], c );
				} )
				.join( '' );
			var off = final[ 0 ] < total[ 0 ];
			return (
				'<div class="wffn-bundles-pt__pv-heading">' + c.esc( f.heading ) + '</div>' +
				'<div class="wffn-bundles-pt__pv-fbt"><div class="wffn-bundles-pt__pv-fbt-strip">' + strip + '</div>' +
				'<div class="wffn-bundles-pt__pv-fbt-rows">' + rows + '</div>' +
				'<div class="wffn-bundles-pt__pv-fbt-sum"><div class="wffn-bundles-pt__pv-fbt-total"><span>' + c.esc( c.T.fbtTotal ) + '</span>' +
				( off ? '<s>' + c.esc( range( total[ 0 ], total[ 1 ] ) ) + '</s>' : '' ) + '<strong>' + c.esc( range( final[ 0 ], final[ 1 ] ) ) + '</strong></div>' +
				( off ? '<div class="wffn-bundles-pt__pv-fbt-save">' + c.esc( fmt( c.T.fbtSave, c.money( total[ 0 ] - final[ 0 ] ) ) ) + '</div>' : '' ) +
				'<div class="wffn-bundles-pt__pv-cta">' + c.esc( fmt( 1 === list.length ? c.T.fbtAddOne : c.T.fbtAddMany, list.length ) ) + '</div></div></div>'
			);
		},
		input: common,
		change: function ( f, e ) {
			var key = e.getAttribute( 'data-f' );
			if ( 'fallback' === key ) {
				f[ key ] = e.value;
			}
			if ( 'discountMode' === key ) {
				if ( 'none' === e.value ) {
					f.lastType = 'no_discount' === f.discountType ? f.lastType : f.discountType;
					f.discountType = 'no_discount';
				} else {
					f.scope = e.value;
					f.discountType = 'no_discount' === f.discountType ? f.lastType || 'percentage' : f.discountType;
				}
			}
			// The products hint depends on the fallback, and the amount box on the discount mode.
			return 'fallback' === key || 'discountMode' === key;
		},
		click: commonClick,
		pick: function ( f, p ) {
			if ( f.extra.length < D.fbtMax ) {
				pickExtra( f, p );
			}
		},
		payload: function ( f, c ) {
			if ( ! String( f.name || '' ).trim() ) {
				throw new Error( c.T.needName );
			}
			var err;
			if ( ! f.extra.length && 'none' === f.fallback ) {
				err = new Error( c.T.fbtNeedProduct );
			} else if ( 'none' === f.fallback && f.extra.length > D.fbtMax ) {
				err = new Error( fmt( c.T.fbtFull, D.fbtMax ) );
			}
			if ( err ) {
				err.field = '#wffn-bundles-pt-products';
				throw err;
			}
			// Cross-sells and upsells come off this product, so none set means its page shows no set.
			if ( 'none' !== f.fallback && ! ( state.linked[ f.fallback ] || [] ).length ) {
				err = new Error( fmt( 'cross_sells_upsells' === f.fallback ? c.T.fbtNoLinkedUp : c.T.fbtNoLinked, c.product.name || c.T.thisProduct ) );
				err.field = '#wffn-bundles-pt-fallback-label';
				throw err;
			}
			var max = Number( String( f.max ).trim() || 'x' );
			if ( Math.floor( max ) !== max || max < 1 || max > D.fbtMax ) {
				err = new Error( fmt( c.T.fbtBadMax, D.fbtMax ) );
				err.field = '#wffn-bundles-pt-f-max';
				throw err;
			}
			checkDiscount( f, c.T );
			var saved = savedDiscount( f );
			return {
				name: f.name.trim(),
				discountConfig: {
					bundleHeading: f.heading,
					fbtItems: ( 'none' === f.fallback ? f.extra : [] ).map( function ( p ) {
						return { id: c.nid( 'f' ), product: ref( p ), checked: true };
					} ),
					fbtFallback: f.fallback,
					fbtMaxItems: max,
					fbtDiscountScope: 'no_discount' === f.discountType ? 'none' : f.scope,
					fbtDiscountType: saved.type,
					fbtDiscountValue: saved.value,
					fbtMinCompanions: 1,
					fbtLayout: 'row',
					fbtButtonLabel: '',
				},
			};
		},
	} );

	// Add-on type scripts load after this file, so the first paint waits for them.
	$( render );

	$( document ).on( 'click', '.product_data_tabs a[href="' + PANEL + '"]', load );

	var remember = 'wffn-bundles-pt-open-' + D.product.id;
	function reopen() {
		var tabs = $( '.product_data_tabs' );
		tabs.on( 'click', 'a', function () {
			try {
				if ( PANEL === $( this ).attr( 'href' ) ) {
					window.sessionStorage.setItem( remember, '1' );
				} else {
					window.sessionStorage.removeItem( remember );
				}
			} catch ( err ) {}
		} );
		var again = false;
		try {
			again = '1' === window.sessionStorage.getItem( remember );
		} catch ( err ) {}
		if ( again || PANEL === window.location.hash ) {
			tabs.find( 'a[href="' + PANEL + '"]' ).trigger( 'click' );
		}
	}
	// After WooCommerce's own ready handler has picked the first tab.
	if ( 'complete' === document.readyState ) {
		reopen();
	} else {
		window.addEventListener( 'load', reopen );
	}
} )( window.jQuery );
