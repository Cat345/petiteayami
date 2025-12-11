(function ($, doc, win) {
	'use strict';


	window.wfocuBuilderCommons.addFilter('wfocu_offer_settings', function (e) {
		e.unshift({
			type: "label",
			label: "Dynamic Shipping",
			model: "label_shipping"
		}, {
			type: "checkbox",
			label: "Check this box to charge the user separately for the shipping of this item. The cost will be calculated on the fly based on your store's configuration and shown to the user upon clicking ‘accept’." +
				" Any Flat Shipping charges set above will be overridden by dynamic shipping.",
			model: "ship_dynamic",
			inputName: 'ship_dynamic',
			styleClasses: "wfocu_ship_dynamic",
		});


		return e;
	});

	window.wfocuBuilderCommons.addAction('wfocu_offer_loaded', function (e) {
		var elem = document.getElementsByName('ship_dynamic');

		$(doc).on('change', 'input[name="ship_dynamic"]', function () {

			handle_input_for_prices(this.checked);
			handle_offer_display_prices();
		});


		if (typeof elem[0] !== "undefined") {
			handle_input_for_prices(elem[0].checked);
		}
		handle_offer_display_prices();
	});

	window.wfocuBuilderCommons.addAction('wfocu_offer_switched', function (e) {
		var elem = document.getElementsByName('ship_dynamic');
		handle_checkboxes(elem);
		handle_input_for_prices(elem.checked);

	});

	function handle_checkboxes(elem) {

		if (typeof elem === "undefined") {
			return;
		}
		if (true === elem.checked) {

			$('input[name="ask_confirmation"]').prop('checked', true);
			window.wfocuBuilder.offer_setting.model.ask_confirmation = true;
		}

	}

	function handle_input_for_prices(isDynamicShipping) {

		if (true === isDynamicShipping) {

			$('.wfocu-offer-flat-shipping-input').prop('readonly', true);
		} else {
			$('.wfocu-offer-flat-shipping-input').prop('readonly', false);
		}

	}

	function handle_offer_display_prices() {
		for (var key in window.wfocuBuilder.offer_product_settings.products) {
			window.wfocuBuilder.offer_product_settings.update_offer_price(null, key);
		}

	}


})(jQuery, document, window);