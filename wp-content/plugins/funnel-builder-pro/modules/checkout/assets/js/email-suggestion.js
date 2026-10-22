/**
 * FunnelKit Checkout: "Did you mean name@gmail.com?" suggestion for the billing email field.
 * Browser only, no network requests, never changes the order.
 */
(function ( window, document ) {
	'use strict';

	var frontend = window.wfacp_frontend || {};
	if ( 'yes' !== frontend.enable_email_suggestion ) {
		return;
	}

	// Popular mailbox providers a typo is matched against.
	var DOMAINS = (
		'gmail.com googlemail.com yahoo.com ymail.com rocketmail.com hotmail.com outlook.com live.com ' +
		'msn.com aol.com icloud.com me.com mac.com protonmail.com proton.me pm.me zoho.com zohomail.com ' +
		'mail.com gmx.com gmx.net gmx.de web.de yandex.com yandex.ru mail.ru inbox.com fastmail.com ' +
		'hushmail.com tutanota.com tuta.io hey.com aim.com comcast.net verizon.net att.net sbcglobal.net ' +
		'bellsouth.net cox.net charter.net earthlink.net juno.com optonline.net roadrunner.com rr.com ' +
		'frontier.com windstream.net centurylink.net shaw.ca sympatico.ca rogers.com telus.net ' +
		'videotron.ca yahoo.ca hotmail.ca live.ca btinternet.com sky.com virginmedia.com talktalk.net ' +
		'ntlworld.com blueyonder.co.uk hotmail.co.uk yahoo.co.uk live.co.uk outlook.co.uk bigpond.com ' +
		'bigpond.net.au optusnet.com.au iinet.net.au yahoo.com.au hotmail.com.au outlook.com.au ' +
		'yahoo.co.in rediffmail.com rediff.com hotmail.co.in outlook.in yahoo.in sify.com vsnl.net ' +
		'yahoo.fr orange.fr wanadoo.fr free.fr laposte.net sfr.fr hotmail.fr live.fr yahoo.de t-online.de ' +
		'freenet.de arcor.de hotmail.de live.de yahoo.es terra.com hotmail.es telefonica.net yahoo.it ' +
		'libero.it virgilio.it alice.it tin.it tiscali.it hotmail.it live.it yahoo.com.br uol.com.br ' +
		'bol.com.br terra.com.br ig.com.br globo.com hotmail.com.br yahoo.com.mx prodigy.net.mx naver.com ' +
		'hanmail.net daum.net nate.com qq.com 163.com 126.com sina.com sina.cn foxmail.com yeah.net ' +
		'aliyun.com yahoo.co.jp docomo.ne.jp ezweb.ne.jp softbank.ne.jp nifty.com live.jp yahoo.com.sg ' +
		'yahoo.com.ph yahoo.com.hk yahoo.com.tw seznam.cz o2.pl wp.pl interia.pl onet.pl abv.bg ' +
		'bluewin.ch'
	).split( ' ' );

	// Extra providers added through the wfacp_email_suggestion_domains PHP filter.
	( frontend.email_suggestion_domains || [] ).forEach( function ( domain ) {
		domain = String( domain ).trim().toLowerCase();
		if ( domain.indexOf( '.' ) > 0 && -1 === DOMAINS.indexOf( domain ) ) {
			DOMAINS.push( domain );
		}
	} );

	// Two-part public suffixes, so "yahoo.co.uk" splits into "yahoo" + "co.uk".
	var TWO_PART_SUFFIXES = (
		'co.uk org.uk ac.uk gov.uk net.uk me.uk ltd.uk plc.uk sch.uk com.au net.au org.au edu.au gov.au ' +
		'id.au co.in net.in org.in gen.in firm.in ind.in ac.in edu.in gov.in co.nz net.nz org.nz ac.nz ' +
		'govt.nz com.br net.br org.br gov.br edu.br com.mx org.mx com.ar com.co com.sg com.hk com.tw ' +
		'com.my com.ph com.vn com.tr com.ua com.pl co.za org.za net.za co.ke co.il co.th or.th in.th ' +
		'co.jp ne.jp or.jp ac.jp go.jp com.cn net.cn org.cn gov.cn edu.cn co.kr or.kr ne.kr com.ru net.ru ' +
		'org.ru com.es com.pt com.gr'
	).split( ' ' );

	// Common mistyped endings.
	var SUFFIX_FIXES = {
		con: 'com', cpm: 'com', cm: 'com', om: 'com', comm: 'com', ocm: 'com', cmo: 'com', vom: 'com', xom:
		'com', clm: 'com', copm: 'com', comn: 'com', coom: 'com', cvom: 'com', cim: 'com', cok: 'com', col:
		'com', comg: 'com', 'com.com': 'com', 'co.com': 'com', c0m: 'com', cnm: 'com', conm: 'com', ner:
		'net', ne: 'net', nte: 'net', bet: 'net', met: 'net', nett: 'net', ogr: 'org', rog: 'org', orgg:
		'org', og: 'org', prg: 'org', ord: 'org', 'co.ik': 'co.uk', 'co.ul': 'co.uk', 'co.ku': 'co.uk',
		couk: 'co.uk', 'com.au.com': 'com.au', edi: 'edu', eud: 'edu', gpv: 'gov'
	};

	// Fix keys that are also real TLDs; only corrected on a known provider (e.g. gmail.cm, not shop.cm).
	var REAL_TLDS = { cm: true, om: true, ne: true, bet: true, 'co.com': true };

	// Dotted fix keys span two labels, so they are matched against the end of the domain, longest first.
	var DOTTED_FIXES = Object.keys( SUFFIX_FIXES ).filter( function ( key ) {
		return key.indexOf( '.' ) > -1;
	} ).sort( function ( a, b ) {
		return b.length - a.length;
	} );

	var KEYBOARD = [ '1234567890', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm' ];

	var isKnownDomain = {};
	var isKnownName = {};
	var isTwoPartSuffix = {};
	var isValidSuffix = {};
	TWO_PART_SUFFIXES.forEach( function ( suffix ) {
		isTwoPartSuffix[ suffix ] = true;
		isValidSuffix[ suffix ] = true;
	} );
	var providers = DOMAINS.map( function ( domain ) {
		var parts = splitDomain( domain );
		isKnownDomain[ domain ] = true;
		isKnownName[ parts.name ] = true;
		isValidSuffix[ parts.suffix ] = true;
		return { domain: domain, name: parts.name, suffix: parts.suffix };
	} );

	function splitDomain( domain ) {
		var parts = domain.split( '.' );
		var lastTwo = parts.slice( -2 ).join( '.' );
		if ( parts.length > 2 && isTwoPartSuffix[ lastTwo ] ) {
			return { name: parts.slice( 0, -2 ).join( '.' ), suffix: lastTwo };
		}
		return { name: parts.slice( 0, -1 ).join( '.' ), suffix: parts.length > 1 ? parts[ parts.length - 1 ] : '' };
	}

	function splitTypedDomain( domain ) {
		for ( var i = 0; i < DOTTED_FIXES.length; i++ ) {
			var ending = '.' + DOTTED_FIXES[ i ];
			if ( domain.length > ending.length && domain.slice( -ending.length ) === ending ) {
				return { name: domain.slice( 0, -ending.length ), suffix: DOTTED_FIXES[ i ] };
			}
		}
		return splitDomain( domain );
	}

	function isNeighbourKey( a, b ) {
		for ( var r = 0; r < KEYBOARD.length; r++ ) {
			var c = KEYBOARD[ r ].indexOf( a );
			if ( c > -1 ) {
				for ( var dr = -1; dr <= 1; dr++ ) {
					var row = KEYBOARD[ r + dr ] || '';
					if ( row.slice( Math.max( 0, c - 1 ), c + 2 ).indexOf( b ) > -1 ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	// Edit distance where a neighbouring-key slip or swapped letters cost 0.6 instead of 1.
	function distance( a, b ) {
		var prev = [];
		var twoBack = null;
		for ( var j = 0; j <= b.length; j++ ) {
			prev.push( j );
		}
		for ( var i = 1; i <= a.length; i++ ) {
			var cur = [ i ];
			for ( j = 1; j <= b.length; j++ ) {
				var cost = a[ i - 1 ] === b[ j - 1 ] ? 0 : ( isNeighbourKey( a[ i - 1 ], b[ j - 1 ] ) ? 0.6 : 1 );
				var value = Math.min( prev[ j ] + 1, cur[ j - 1 ] + 1, prev[ j - 1 ] + cost );
				if ( twoBack && i > 1 && j > 1 && a[ i - 1 ] === b[ j - 2 ] && a[ i - 2 ] === b[ j - 1 ] ) {
					value = Math.min( value, twoBack[ j - 2 ] + 0.6 );
				}
				cur.push( value );
			}
			twoBack = prev;
			prev = cur;
		}
		return prev[ b.length ];
	}

	// Closest provider for a mistyped domain; null unless it is a clear, close match (company domains stay untouched).
	function closestProvider( name, suffix ) {
		if ( name.length < 4 ) {
			return null;
		}
		var limit = name.length <= 5 ? 1.0 : ( name.length <= 9 ? 1.6 : 2.2 );
		var best = null;
		var bestScore = 99;
		var secondScore = 99;
		providers.forEach( function ( provider ) {
			if ( Math.abs( provider.name.length - name.length ) > 2 ) {
				return;
			}
			var score = distance( name, provider.name ) + ( provider.suffix === suffix ? 0 : 0.5 );
			if ( score < bestScore ) {
				secondScore = bestScore;
				bestScore = score;
				best = provider;
			} else if ( score < secondScore ) {
				secondScore = score;
			}
		} );
		return best && bestScore <= limit && secondScore - bestScore >= 0.4 ? best.domain : null;
	}

	// A known provider whose ending was cut short: gmail.co -> gmail.com, bigpond.co -> bigpond.com.
	function completeSuffix( name, suffix ) {
		if ( ! suffix || ! isKnownName[ name ] ) {
			return null;
		}
		for ( var i = 0; i < providers.length; i++ ) {
			var provider = providers[ i ];
			if ( provider.name === name && 0 === provider.suffix.indexOf( suffix ) && provider.suffix.length - suffix.length <= 2 ) {
				return provider.domain;
			}
		}
		return null;
	}

	function fixDomain( domain ) {
		if ( isKnownDomain[ domain ] ) {
			return domain;
		}
		var parts = splitTypedDomain( domain );
		var suffix = parts.suffix;
		if ( SUFFIX_FIXES[ suffix ] && ( ! REAL_TLDS[ suffix ] || isKnownName[ parts.name ] ) ) {
			suffix = SUFFIX_FIXES[ suffix ];
		} else if ( isKnownName[ parts.name ] && isValidSuffix[ suffix ] ) {
			// A provider on another real ending is a real mailbox (protonmail.ch, icloud.co.uk), not a typo.
			return domain;
		}
		var fixed = parts.name + ( suffix ? '.' + suffix : '' );
		if ( isKnownDomain[ fixed ] ) {
			return fixed;
		}
		return completeSuffix( parts.name, suffix ) || closestProvider( parts.name, suffix ) || fixed;
	}

	// Returns the corrected address, or null when the email already looks right or can't be fixed with confidence.
	function getSuggestion( rawEmail ) {
		var email = String( rawEmail || '' ).trim().replace( /\s+/g, '' ).replace( /\.{2,}/g, '.' );
		if ( ! email ) {
			return null;
		}
		var at = email.split( '@' );
		var i;

		// Missing "@": geel43gmail.com -> geel43@gmail.com. The longest matching provider wins (gmail.com over mail.com);
		// short provider names are skipped, or johnhome.com would become johnho@me.com.
		if ( 1 === at.length ) {
			var lower = email.toLowerCase();
			var match = '';
			for ( i = 0; i < providers.length; i++ ) {
				var candidate = providers[ i ].domain;
				if ( providers[ i ].name.length >= 4 && candidate.length > match.length && lower.length > candidate.length && lower.slice( -candidate.length ) === candidate ) {
					match = candidate;
				}
			}
			return match ? email.slice( 0, -match.length ) + '@' + match : null;
		}
		if ( 2 !== at.length || ! at[ 0 ] || ! at[ 1 ] ) {
			return null;
		}
		at[ 1 ] = at[ 1 ].toLowerCase();

		// Missing dot: name@gmailcom -> name@gmail.com.
		if ( -1 === at[ 1 ].indexOf( '.' ) ) {
			for ( i = 0; i < DOMAINS.length; i++ ) {
				if ( DOMAINS[ i ].replace( /\./g, '' ) === at[ 1 ] ) {
					return at[ 0 ] + '@' + DOMAINS[ i ];
				}
			}
			return null;
		}

		var fixed = fixDomain( at[ 1 ] );
		return fixed !== at[ 1 ] ? at[ 0 ] + '@' + fixed : null;
	}

	function getHint( field, create ) {
		var row = field.closest( '.form-row' ) || field.parentNode;
		var hint = row.querySelector( '.wfacp-email-suggestion' );
		if ( ! hint && create ) {
			hint = document.createElement( 'div' );
			hint.className = 'wfacp-email-suggestion';
			hint.setAttribute( 'role', 'status' );
			hint.setAttribute( 'aria-live', 'polite' );
			row.appendChild( hint );
		}
		return hint;
	}

	function hideHint( field ) {
		var hint = getHint( field, false );
		if ( hint ) {
			hint.className = 'wfacp-email-suggestion';
			hint.textContent = '';
		}
	}

	function showHint( field, suggestion ) {
		var hint = getHint( field, true );
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'wfacp-email-suggestion__value';
		button.textContent = suggestion;
		button.addEventListener( 'click', function () {
			field.value = suggestion;
			// Native events also reach jQuery handlers; the jQuery blur runs Aero's inline validation.
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			if ( window.jQuery ) {
				window.jQuery( field ).trigger( 'blur' );
			}
			hideHint( field );
		} );
		// "Did you mean %s?", with the button in place of %s; a custom text without %s gets the email at the end.
		var text = String( frontend.email_suggestion_prompt || '' ).trim() || 'Did you mean %s?';
		var at = text.indexOf( '%s' );
		if ( -1 === at ) {
			text += ' %s';
			at = text.length - 2;
		}
		hint.textContent = text.slice( 0, at );
		hint.appendChild( button );
		hint.appendChild( document.createTextNode( text.slice( at + 2 ) ) );
		hint.className = 'wfacp-email-suggestion wfacp-email-suggestion--visible';
	}

	function isEmailField( element ) {
		return element && 'billing_email' === element.id;
	}

	// Delegated listeners keep working when Aero re-renders the form or switches steps.
	document.addEventListener( 'focusout', function ( event ) {
		var field = event.target;
		if ( ! isEmailField( field ) ) {
			return;
		}
		var suggestion = getSuggestion( field.value );
		if ( ! suggestion ) {
			hideHint( field );
			return;
		}
		// Already suggested for this value: leave the hint as it is, so it stays clickable after a refocus
		// and is not repeated once the buyer edited it away and typed their own spelling again.
		if ( field.getAttribute( 'data-wfacp-email-suggested' ) === field.value ) {
			return;
		}
		field.setAttribute( 'data-wfacp-email-suggested', field.value );
		showHint( field, suggestion );
	}, true );

	document.addEventListener( 'input', function ( event ) {
		if ( isEmailField( event.target ) ) {
			hideHint( event.target );
		}
	}, true );

	window.wfacpEmailSuggestion = { getSuggestion: getSuggestion };
})( window, document );
