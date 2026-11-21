/**
 * FunnelKit PayPal — its product-page express buttons and Pay Later message on a bundle.
 *
 * The gateway keys both off the native variation form: it greys its buttons while
 * a variable product's form has no variation and lifts that only on
 * found_variation, and its message re-prices only from a variation. A widget that
 * owns the pickers never drives that form, so on a variable product the buttons
 * stayed dead and the message quoted the page product. Both follow the widget here,
 * or the bought-together set where that is what the buttons buy.
 */
(function () {
	'use strict';

	var OFF = 'fkwcppcp-button-disabled';
	var HOST = '.fkwcppcp-express-button-container';
	var MESSAGE = '.fkwcppcp-paylater-message--product';
	var queued = false;
	var amount = '';

	/** True or false for a bundle the widget would post; null when the add stays native. */
	function ready(form) {
		var api = window.wffnBundlesSkins;
		var raw = api && api.expressPayload ? api.expressPayload(form) : '';
		if (!raw) return null;
		try {
			return JSON.parse(raw).ready !== false;
		} catch (e) {
			return null;
		}
	}

	function sync() {
		queued = false;
		Array.prototype.forEach.call(document.querySelectorAll('form.cart'), function (form) {
			var hosts = form.querySelectorAll(HOST);
			if (!hosts.length) return;
			var state = ready(form);
			if (null === state) {
				// Nothing of the widget's to buy any more (a Mix & Match shopper chose the
				// product alone): hand back to the gateway's own rule, so buttons this
				// script greyed are not left that way.
				if (!form._wffnBundlesHeld) return;
				var vid = form.querySelector('[name="variation_id"]');
				state = !(form.classList.contains('variations_form') && !(vid && vid.value && '0' !== vid.value));
			}
			form._wffnBundlesHeld = true;
			Array.prototype.forEach.call(hosts, function (host) {
				// Only on a difference, or the observer below would never settle.
				if (host.classList.contains(OFF) === state) host.classList.toggle(OFF, !state);
			});
		});
		// The gateway's SDK re-renders the message when this attribute changes.
		var node = document.querySelector(MESSAGE);
		if (node && amount && node.getAttribute('data-pp-amount') !== amount) {
			node.setAttribute('data-pp-amount', amount);
		}
	}

	function schedule() {
		if (queued) return;
		queued = true;
		window.setTimeout(sync, 0);
	}

	function start() {
		if (!window.MutationObserver || !window.FormData) return;
		// The gateway re-greys from its own events and adds a button per funding source.
		var observer = new MutationObserver(schedule);
		Array.prototype.forEach.call(document.querySelectorAll('form.cart'), function (form) {
			observer.observe(form, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
		});
		var message = document.querySelector(MESSAGE);
		if (message) {
			observer.observe(message, { attributes: true, attributeFilter: ['data-pp-amount'] });
		}
		['change', 'wffn-bundles:tier-change', 'wffn-bundles:fb-change', 'wffn-bundles:addon-change', 'wffn-bundles:upsell-change'].forEach(function (name) {
			document.addEventListener(name, schedule);
		});
		var bus = window.wffnBundlesSkins && window.wffnBundlesSkins.bus;
		if (bus) {
			bus.addEventListener('wffn-bundles:price-quoted', function (e) {
				var data = e && e.detail && e.detail.data;
				var widget = e && e.detail && e.detail.widget;
				// A bought-together set beside a bundle is not what the buttons buy.
				if (widget && widget.classList.contains('wffn-bundles-fbt') && document.querySelector('form.cart input[name="wffn_bundles_bundle_id"]')) return;
				if (data && data.hasItems && data.expressAmount) {
					amount = String(data.expressAmount);
					schedule();
				}
			});
		}
		schedule();
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
