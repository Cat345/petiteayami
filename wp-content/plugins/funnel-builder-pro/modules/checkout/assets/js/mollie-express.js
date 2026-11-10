/**
 * FunnelKit Checkout x Mollie express checkout. Loaded by compatibilities/template-found/class-mollie-express.php.
 */
(function ($) {
	'use strict';
	var data = window.wfacpMollieExpress || {};

	/* Tell the express area a slot has a button: inserting into its wrapper is what the express area watches. */
	function markReady(slug, late) {
		try {
			var wrap = document.getElementById('wfacp_' + slug + '_buttons');
			if (!wrap) {
				return;
			}
			if (!wrap.querySelector('.wfacp-mollie-express-ready')) {
				var marker = document.createElement('span');
				marker.className = 'wfacp-mollie-express-ready';
				wrap.appendChild(marker);
			}
			var slot = document.getElementById('wfacp_smart_button_' + slug);
			var row = document.getElementById('wfacp_smart_buttons');
			if (slot) {
				slot.style.display = 'block';
			}
			/* The Express Component can become ready after the row settled without it: reveal the row then. */
			if (late && row && 'none' === row.style.display && false === row.classList.contains('wfacp_smart_buttons_active')) {
				row.style.display = '';
				row.classList.add('wfacp_smart_buttons_active');
			}
		} catch (e) {
		}
	}

	function markGone(slug) {
		try {
			var wrap = document.getElementById('wfacp_' + slug + '_buttons');
			var marker = wrap ? wrap.querySelector('.wfacp-mollie-express-ready') : null;
			if (marker) {
				marker.parentNode.removeChild(marker);
			}
			var slot = document.getElementById('wfacp_smart_button_' + slug);
			if (slot) {
				slot.style.display = 'none';
			}
		} catch (e) {
		}
	}

	function legacyReady() {
		try {
			if (document.querySelector('#wfacp_mollie_applepay_buttons #mollie_applepay_button')) {
				markReady('mollie_applepay', false);
			}
			if (document.querySelector('#wfacp_mollie_paypal_buttons #mollie-PayPal-button')) {
				markReady('mollie_paypal', false);
			}
		} catch (e) {
		}
	}

	/* Express Component: the same calls Mollie's block checkout makes. */
	var component = data.component;
	var mount = document.getElementById('wfacp_mollie_express_component');
	var state = {checkout: null, element: null, token: '', fingerprint: null, generation: 0, timer: null};

	function post(route, body) {
		return window.wp.apiFetch({url: component.restUrl + route, method: 'POST', data: $.extend({}, body || {}, {nonce: component.nonce}), parse: false}).then(function (res) {
			return res.json().then(function (json) {
				return res.ok && json && json.ok !== false ? {ok: true, data: json} : {ok: false, code: json && json.code, message: json && json.message};
			}, function () {
				return {ok: false, code: 'http_' + res.status, message: ''};
			});
		}, function (res) {
			if (res && typeof res.json === 'function') {
				return res.json().then(function (json) {
					return {ok: false, code: json && json.code, message: json && json.message};
				}, function () {
					return {ok: false, code: 'network', message: ''};
				});
			}
			return {ok: false, code: 'network', message: ''};
		});
	}

	function release() {
		var checkout = state.checkout, element = state.element;
		state.checkout = null;
		state.element = null;
		state.token = '';
		try {
			if (checkout && typeof checkout.off === 'function') {
				checkout.off('submit', onSubmit);
			}
		} catch (e) {
		}
		if (element) {
			try {
				var unmounted = element.unmount();
				if (unmounted && typeof unmounted.catch === 'function') {
					unmounted.catch(function () {
					});
				}
			} catch (e) {
			}
		}
	}

	function onSubmit(event) {
		try {
			event.defer();
			post('express/order', {token: state.token}).then(function (result) {
				if (result.ok) {
					event.resolve();
					return;
				}
				var messages = component.messages || {};
				event.reject('rest_cookie_invalid_nonce' === result.code ? messages.reload : (result.message || messages.unavailable));
				if (['cart_changed', 'session_expired', 'session_missing', 'session_replaced', 'shipping_incomplete'].indexOf(result.code) > -1) {
					state.fingerprint = null;
					schedule();
				}
			}, function () {
				event.reject((component.messages || {}).unavailable);
			});
		} catch (e) {
		}
	}

	function fingerprint() {
		var parts = [];
		try {
			parts.push($('#order_review .order-total .amount, .wfacp_order_summary .order-total .amount').first().text());
			$('input[name^="shipping_method"]:checked, select[name^="shipping_method"], input[type="hidden"][name^="shipping_method"]').each(function () {
				parts.push(this.value);
			});
			['country', 'state', 'postcode', 'city', 'address_1'].forEach(function (key) {
				var shipToDifferent = $('#ship-to-different-address-checkbox').is(':checked') || $('#shipping_same_as_billing').length > 0;
				var field = $('#shipping_' + key);
				if (!field.length || (!shipToDifferent && !field.is(':visible'))) {
					field = $('#billing_' + key);
				}
				parts.push(field.val() || '');
			});
		} catch (e) {
		}
		return parts.join('|');
	}

	function start() {
		var current = fingerprint();
		/* Nothing changed: keep the current (or still mounting) component. */
		if (current === state.fingerprint && state.element) {
			return;
		}
		var generation = ++state.generation;
		release();
		state.fingerprint = current;
		post('express/session', {}).then(function (result) {
			if (generation !== state.generation) {
				return;
			}
			if (!result.ok || !result.data || !result.data.clientAccessToken || typeof window.Mollie2 !== 'object' && typeof window.Mollie2 !== 'function') {
				markGone('mollie_express');
				return;
			}
			try {
				var checkout = window.Mollie2.Checkout(result.data.clientAccessToken, {locale: component.locale});
				checkout.on('submit', onSubmit);
				var element = checkout.create('express-component', {buttons: component.buttons});
				state.checkout = checkout;
				state.element = element;
				state.token = result.data.clientAccessToken;
				Promise.resolve(element.mount(mount)).then(function () {
					if (generation === state.generation) {
						markReady('mollie_express', true);
					}
				}, function () {
					markGone('mollie_express');
				});
			} catch (e) {
				release();
				markGone('mollie_express');
			}
		}, function () {
			markGone('mollie_express');
		});
	}

	function schedule() {
		clearTimeout(state.timer);
		state.timer = setTimeout(function () {
			try {
				start();
			} catch (e) {
			}
		}, 1000);
	}

	function init() {
		try {
			legacyReady();
			if (component && mount && window.wp && window.wp.apiFetch) {
				$(document.body).on('updated_checkout', schedule);
				schedule();
			}
		} catch (e) {
		}
	}

	/* Run after the express area starts watching its slots, so it sees the insert. */
	var started = false;
	function once() {
		if (!started) {
			started = true;
			init();
		}
	}
	$(document.body).on('wfacp_smart_buttons_dom_loaded', once);
	$(window).on('load', function () {
		setTimeout(once, 1500);
	});
})(jQuery);
