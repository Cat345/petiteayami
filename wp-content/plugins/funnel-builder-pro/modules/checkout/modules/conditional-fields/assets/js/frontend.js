/**
 * Frontend JavaScript
 *
 * Handles conditional field visibility on checkout.
 *
 * @package FunnelKit_Checkout_Conditional_Fields
 */

(function($) {
	'use strict';

	var FKCF_Frontend = {
		rules: {},
		sectionRules: {},
		visibilityMap: {
			sections: {},
			fields: {}
		},
		debounceTimer: null,
		useServerMapForSections: false,
		hiddenFields: {},
		hiddenSections: {},
		// True once init() has found fkcfData; guards handlers bound outside init().
		initialized: false,
		// Caches a field's value before it is cleared by hideField() so it can be
		// restored if the field is re-shown within the same evaluation cycle.
		cachedValues: {},

		init: function() {
			if (typeof fkcfData === 'undefined') {
				return;
			}

			this.initialized = true;
			this.rules = fkcfData.rules || {};
			this.sectionRules = fkcfData.sectionRules || {};
			this.visibilityMap = fkcfData.visibilityMap || {sections: {}, fields: {}};

			// Backwards compatibility: if visibilityMap is flat (old format), convert to new format
			if (!this.visibilityMap.sections && !this.visibilityMap.fields) {
				this.visibilityMap = {sections: {}, fields: this.visibilityMap};
			}

			this.debugLog('Initialized with ' + Object.keys(this.rules).length + ' field rules, ' + Object.keys(this.sectionRules).length + ' section rules');

			this.bindEvents();
			// Apply visibility from server-computed map (cart rules evaluated at page load).
			this.applyVisibility();
			// Re-apply when DOM is ready (handles async/dynamic checkout content).
			$(document).ready(function() {
				this.applyVisibility();
			}.bind(this));
		},

		bindEvents: function() {
			var self = this;

			self.debugLog('Binding events...');

			// Field change events (debounced for performance)
			$(document.body).on('change input', 'input, select, textarea', function() {
				clearTimeout(self.debounceTimer);
				self.debounceTimer = setTimeout(function() {
					self.debugLog('Field changed, re-applying visibility');
					self.applyVisibility();
				}, 150);
			});

			// Country/state change (used by some plugins; Select2 may not always fire standard change)
			$(document.body).on('country_to_state_changing change', '.country_to_state, #billing_country, #shipping_country', function() {
				clearTimeout(self.debounceTimer);
				self.debounceTimer = setTimeout(function() {
					self.debugLog('Country/state changed, re-applying visibility');
					self.applyVisibility();
				}, 200);
			});

			// Listen for order review / checkout updates (cart rules re-evaluated server-side, new map in fragments).
			$(document.body).on('updated_checkout', function(e, data) {
				self.debugLog('=== updated_checkout event fired ===');
				self.debugLog('Event data exists: ' + (data ? 'YES' : 'NO'));
				self.debugLog('Fragments exist: ' + (data && data.fragments ? 'YES' : 'NO'));

				if (data && data.fragments && data.fragments.fkcf_visibility_map) {
					self.debugLog('Found fkcf_visibility_map, applying cart-based visibility from update_order_review...');
					var cartInfo = data.fragments.fkcf_cart_info || null;
					self.useServerMapForSections = true;
					self.handleFragmentUpdate(data.fragments.fkcf_visibility_map, cartInfo);
				} else if (data && data.fragments) {
					self.debugLog('fkcf_visibility_map not in fragments; re-applying current map to refreshed DOM');
					self.applyVisibility();
				} else {
					self.debugLog('No fragments in event');
				}
			});

			self.debugLog('Events bound successfully');
		},

		applyVisibility: function() {
			this.debugLog('applyVisibility called');
			this.debugLog('Visibility map: ' + JSON.stringify(this.visibilityMap));

			// Apply section visibility first (sections take precedence)
			this.applySectionVisibility();

			// Then apply field visibility
			this.applyFieldVisibility();

			// Handle cross-group same_as checkbox: when all fields of one address group
			// are hidden, hide the other group's same_as checkbox and force-show its fields.
			this.handleCrossGroupSameAs();

			this.debugLog('applyVisibility completed');

			// Update the anti-flicker CSS style tag so hidden fields stay hidden
			// even when WooCommerce replaces fragment DOM.
			this.updateHideCss();

			// Keep WooCommerce's "ship to a different address" checkbox in sync with the
			// shipping section's visibility.
			this.syncShipToDifferent();
		},

		/**
		 * What the conditional rules decided about the shipping address this cycle.
		 *
		 * Returns true (rules show it), false (rules hide it), or null when no rule touches
		 * the shipping address at all.
		 *
		 * Reads ONLY the decisions this module recorded during applyVisibility(); it never
		 * inspects the DOM. Rendered visibility cannot tell "a rule hid this" apart from
		 * "this sits on an inactive multi-step step" (#9438) or "WFACP's own same-as-billing
		 * toggle collapsed it" — which is how every previous version of this got it wrong.
		 *
		 * The presence of the entry IS the confirmation: the server emits a `shipping` section
		 * entry only when a section rule exists for it (class-rule-engine.php:406-412), and a
		 * `shipping_*` field entry only for fields a rule owns.
		 */
		shippingRuleState: function() {
			// A section-level rule governs the whole address block, so it wins.
			if (this.hiddenSections.hasOwnProperty('shipping')) {
				return ! this.hiddenSections.shipping;
			}

			// Otherwise fall back to field-level rules. Shipping counts as shown while at
			// least one field we hold a decision for is still shown.
			var self     = this;
			var owned    = false;
			var anyShown = false;

			Object.keys(this.hiddenFields).forEach(function(fieldId) {
				if (fieldId.indexOf('shipping_') !== 0 || fieldId.indexOf('same_as') !== -1) {
					return;
				}
				owned = true;
				if (! self.hiddenFields[fieldId]) {
					anyShown = true;
				}
			});

			return owned ? anyShown : null;
		},

		/**
		 * Drive WooCommerce's "Ship to a different address" checkbox from that decision.
		 *
		 * While the box is unticked `ship_to_different_address` does not post, so WooCommerce
		 * skips the whole shipping fieldset — validating nothing and copying billing over
		 * shipping (#9314) — and WFACP keeps the billing address required, because
		 * handle_billing_field_required_settings() is gated on the same flag (#9413).
		 *
		 * The box is therefore only ever touched when a conditional rule actually owns the
		 * shipping address. When none does, WFACP's server-rendered state (form.php:322) and
		 * its own toggle handlers (checkout.js:2799/2838) are already correct and we stay out.
		 */
		syncShipToDifferent: function() {
			if (! this.initialized) {
				return;
			}

			var $cb = $('#ship-to-different-address-checkbox');
			if ($cb.length === 0) {
				return;
			}

			var ruleShowsShipping = this.shippingRuleState();
			if (null === ruleShowsShipping) {
				// No conditional rule owns the shipping address; not ours to touch.
				return;
			}

			// #9413: when the cart needs no shipping address at all (all-virtual, local pickup,
			// ship-to-billing-only) keep the box ticked even though the rules hid the section —
			// unticking there leaves billing required while those fields are off screen and empty.
			// When the cart DOES need shipping, a rule-hidden section must untick, or WooCommerce
			// demands an address the customer cannot see. `is_virtual` is the fallback for
			// cart_info cached before needs_shipping existed.
			var cartInfo         = (typeof fkcfData !== 'undefined' && fkcfData.cartInfo) ? fkcfData.cartInfo : {};
			var noShippingNeeded = (cartInfo.needs_shipping === false) ||
				(typeof cartInfo.needs_shipping === 'undefined' && !! cartInfo.is_virtual);

			// A rule making the shipping section available is not the same thing as the customer
			// choosing to enter a separate shipping address. In layouts where billing is the primary
			// address, WFACP renders its own "ship to a different address" toggle; while that toggle
			// is off the shipping fields stay collapsed and empty, and ticking the box anyway makes
			// WooCommerce validate an address that is not on screen.
			//
			// Reading the toggle's checked state is reading an explicit customer decision, not
			// rendered visibility — so unlike the :visible test this stays correct on multi-step,
			// where the toggle may sit on an inactive step. Only #shipping_same_as_billing counts;
			// #billing_same_as_shipping governs whether BILLING is separate and says nothing about
			// the shipping fieldset. Absent toggle = this layout always collects shipping.
			var $sameAs          = $('#shipping_same_as_billing');
			var separateShipping = $sameAs.length ? $sameAs.prop('checked') : true;

			// The toggle gates the whole decision, including the virtual-cart case: a rule that
			// reveals shipping on a no-shipping-needed cart must still not tick while the customer
			// has the toggle off, or we are back to validating fields they cannot see.
			var shouldCheck = separateShipping && (ruleShowsShipping || noShippingNeeded);

			if ($cb.prop('checked') !== shouldCheck) {
				$cb.prop('checked', shouldCheck);
			}
		},

		/**
		 * Build CSS selectors for a single field ID.
		 * Mirrors the selectors from getFieldElements so the style tag
		 * covers the same wrappers that JS would target.
		 */
		getFieldCssSelectors: function(fieldId) {
			var s = [];
			// Standard WooCommerce field wrapper
			s.push('#' + fieldId + '_field');
			// Class-based selector
			s.push('.' + fieldId);
			// FunnelKit file upload wrappers
			s.push('#' + fieldId + '_field_file_upload');
			s.push('.wfacp_' + fieldId + '_field');
			// Data attribute selectors
			s.push('[data-field-id="' + fieldId + '"]');
			s.push('[data-field-key="' + fieldId + '"]');
			// Birthday date field wrappers (Autonami + Aero Checkout Pro)
			if (fieldId === 'bwfan_birthday_date') {
				s.push('.bwfan-birthday-wrap');
				s.push('.wfacp-dob-field-sec');
			}
			return s;
		},

		/**
		 * Build CSS selectors for a single section ID.
		 * Mirrors the selectors from getSectionElement.
		 */
		getSectionCssSelectors: function(sectionId) {
			return [
				'.fkcf-section-' + sectionId,
				'.wfacp_' + sectionId + '_fields',
				'.woocommerce-' + sectionId + '-fields',
				'#' + sectionId + '_fields'
			];
		},

		/**
		 * Maintain a <style> tag in <head> with CSS selectors for all currently hidden
		 * fields/sections. Because this tag lives outside the fragment-replaced DOM,
		 * newly inserted elements are hidden instantly by CSS — no JS delay/flicker.
		 */
		updateHideCss: function() {
			var self = this;
			var selectors = [];

			// Field selectors
			var hiddenFields = this.hiddenFields || {};
			Object.keys(hiddenFields).forEach(function(fieldId) {
				if (!hiddenFields[fieldId]) {
					return;
				}
				selectors = selectors.concat(self.getFieldCssSelectors(fieldId));
			});

			// Section selectors
			var hiddenSections = this.hiddenSections || {};
			Object.keys(hiddenSections).forEach(function(sectionId) {
				if (!hiddenSections[sectionId]) {
					return;
				}
				selectors = selectors.concat(self.getSectionCssSelectors(sectionId));
			});

			var $style = $('#fkcf-hide-css');
			if (selectors.length === 0) {
				$style.remove();
				return;
			}

			var css = selectors.join(',') + '{display:none !important}';
			if ($style.length) {
				$style.html(css);
			} else {
				$('<style id="fkcf-hide-css">').html(css).appendTo('head');
			}
		},

		applySectionVisibility: function() {
			var self = this;
			var sections = this.visibilityMap.sections || {};
			var sectionRules = this.sectionRules || {};

			self.debugLog('applySectionVisibility: Processing ' + Object.keys(sections).length + ' sections from map, ' + Object.keys(sectionRules).length + ' section rules');

			// First, apply visibility from server map for sections without rules
			Object.keys(sections).forEach(function(sectionId) {
				// If we have a section rule, we'll evaluate it client-side instead
				if (sectionRules[sectionId]) {
					return;
				}

				var isVisible = sections[sectionId];
				self.debugLog('Section ' + sectionId + ' (from map): ' + (isVisible ? 'SHOW' : 'HIDE'));

				if (isVisible) {
					self.showSection(sectionId);
				} else {
					self.hideSection(sectionId);
				}
			});

			// Now evaluate section rules: use server map when fresh from fragments (has correct field values), else client-side
			Object.keys(sectionRules).forEach(function(sectionId) {
				var shouldShow;
				if (self.useServerMapForSections && sections[sectionId] !== undefined) {
					shouldShow = sections[sectionId];
					self.debugLog('Section ' + sectionId + ' (from server map): ' + (shouldShow ? 'SHOW' : 'HIDE'));
				} else {
					shouldShow = self.evaluateSectionRule(sectionRules[sectionId]);
					self.debugLog('Section ' + sectionId + ' (evaluated client-side): ' + (shouldShow ? 'SHOW' : 'HIDE'));
				}

				if (shouldShow) {
					self.showSection(sectionId);
				} else {
					self.hideSection(sectionId);
				}
			});
			self.useServerMapForSections = false;
		},

		/**
		 * Evaluate a section rule considering all condition types.
		 * Field conditions are evaluated client-side.
		 * Cart/user conditions use the last known server-side result.
		 */
		evaluateSectionRule: function(rule) {
			if (!rule || !rule.groups || rule.groups.length === 0) {
				return true; // No rule = visible
			}

			var self = this;
			var groupResults = [];

			rule.groups.forEach(function(group) {
				groupResults.push(self.evaluateSectionGroup(group));
			});

			// Apply group logic (OR between groups by default)
			var groupLogic = (rule.group_logic || 'OR').toUpperCase();
			var ruleSatisfied;

			if (groupLogic === 'AND') {
				ruleSatisfied = groupResults.every(function(result) { return result; });
			} else {
				ruleSatisfied = groupResults.some(function(result) { return result; });
			}

			// Apply action (show/hide)
			var action = (rule.action || 'show').toLowerCase();
			return (action === 'show') ? ruleSatisfied : !ruleSatisfied;
		},

		/**
		 * Evaluate a condition group (AND logic within group).
		 */
		evaluateSectionGroup: function(group) {
			if (!group || !group.conditions || group.conditions.length === 0) {
				return false;
			}

			var self = this;

			// All conditions in a group must be true (AND logic)
			return group.conditions.every(function(condition) {
				return self.evaluateSectionCondition(condition);
			});
		},

		/**
		 * Evaluate a single condition.
		 * Field conditions are evaluated client-side.
		 * Cart/user conditions use cached server result.
		 */
		evaluateSectionCondition: function(condition) {
			if (!condition || !condition.type) {
				return false;
			}

			if (condition.type === 'field') {
				return this.evaluateFieldCondition(condition);
			}

			if (condition.type === 'cart') {
				return this.evaluateCartConditionClientSide(condition);
			}

			if (condition.type === 'user') {
				return this.evaluateUserConditionClientSide(condition);
			}

			// Unknown condition type - assume true
			return true;
		},

		/**
		 * Evaluate cart condition client-side using cached cart data from fkcfData.
		 */
		evaluateCartConditionClientSide: function(condition) {
			// Use cart data from page load (sent via wp_localize_script)
			// For real-time cart changes, rely on update_order_review AJAX
			var cartInfo = fkcfData.cartInfo || {};

			// If cart info is absent/empty client-side (e.g. not yet hydrated from fragments on a
			// change event), do not force the condition to false. Doing so would fail the AND-group
			// and spuriously hide (and clear) fields whose other legs are satisfied — the radio-reset
			// symptom. Assume satisfied until fresh cart data arrives via update_order_review.
			if (!fkcfData.cartInfo || (typeof cartInfo === 'object' && Object.keys(cartInfo).length === 0)) {
				this.debugLog('Cart info absent/stale client-side; assuming cart condition satisfied');
				return true;
			}
			var operator = condition.operator || '';
			var value = condition.value;
			var operand = condition.operand || [];
			var operandType = condition.operand_type || '';

			switch (operator) {
				// Cart total conditions
				case 'cart_total_gt':
					return parseFloat(cartInfo.total || 0) > parseFloat(value);
				case 'cart_total_gte':
					return parseFloat(cartInfo.total || 0) >= parseFloat(value);
				case 'cart_total_lt':
					return parseFloat(cartInfo.total || 0) < parseFloat(value);
				case 'cart_total_lte':
					return parseFloat(cartInfo.total || 0) <= parseFloat(value);
				case 'cart_total_eq':
					return Math.abs(parseFloat(cartInfo.total || 0) - parseFloat(value)) < 0.01;
				case 'cart_total_ne':
					return Math.abs(parseFloat(cartInfo.total || 0) - parseFloat(value)) >= 0.01;

				// Cart subtotal conditions
				case 'cart_subtotal_gt':
					return parseFloat(cartInfo.subtotal || 0) > parseFloat(value);
				case 'cart_subtotal_gte':
					return parseFloat(cartInfo.subtotal || 0) >= parseFloat(value);
				case 'cart_subtotal_lt':
					return parseFloat(cartInfo.subtotal || 0) < parseFloat(value);
				case 'cart_subtotal_lte':
					return parseFloat(cartInfo.subtotal || 0) <= parseFloat(value);
				case 'cart_subtotal_eq':
					return Math.abs(parseFloat(cartInfo.subtotal || 0) - parseFloat(value)) < 0.01;
				case 'cart_subtotal_ne':
					return Math.abs(parseFloat(cartInfo.subtotal || 0) - parseFloat(value)) >= 0.01;

				// Cart is virtual conditions
				case 'cart_is_virtual_eq':
					return (cartInfo.is_virtual ? 'yes' : 'no') === value;
				case 'cart_is_virtual_ne':
					return (cartInfo.is_virtual ? 'yes' : 'no') !== value;

				// Cart item count conditions
				case 'cart_item_count_eq':
					return parseInt(cartInfo.item_count || 0) === parseInt(value);
				case 'cart_item_count_ne':
					return parseInt(cartInfo.item_count || 0) !== parseInt(value);
				case 'cart_item_count_gt':
					return parseInt(cartInfo.item_count || 0) > parseInt(value);
				case 'cart_item_count_lt':
					return parseInt(cartInfo.item_count || 0) < parseInt(value);
				case 'cart_item_count_gte':
					return parseInt(cartInfo.item_count || 0) >= parseInt(value);
				case 'cart_item_count_lte':
					return parseInt(cartInfo.item_count || 0) <= parseInt(value);

				// Shipping weight conditions
				case 'shipping_weight_eq':
					return Math.abs(parseFloat(cartInfo.shipping_weight || 0) - parseFloat(value)) < 0.01;
				case 'shipping_weight_gt':
					return parseFloat(cartInfo.shipping_weight || 0) > parseFloat(value);
				case 'shipping_weight_lt':
					return parseFloat(cartInfo.shipping_weight || 0) < parseFloat(value);

				// Cart coupon conditions
				case 'cart_coupon_contains':
					return this.evaluateCouponContains(cartInfo, operand);
				case 'cart_coupon_not_contains':
					return !this.evaluateCouponContains(cartInfo, operand);

				// Cart items/category/tag conditions (matches any, matches none, matches all)
				case 'cart_contains':
					return this.evaluateCartContains(cartInfo, operandType, operand);
				case 'cart_not_contains':
					return !this.evaluateCartContains(cartInfo, operandType, operand);
				case 'cart_only_contains':
					return this.evaluateCartOnlyContains(cartInfo, operandType, operand);

				default:
					this.debugLog('Cart condition ' + operator + ' not recognized, assuming false');
					return false;
			}
		},

		/**
		 * Check if cart contains any of the specified items (products, categories, tags, shipping_class, product_type).
		 */
		evaluateCartContains: function(cartInfo, operandType, operand) {
			var cartItems = this.getCartItemsByType(cartInfo, operandType);
			if (!Array.isArray(operand)) {
				operand = [operand];
			}
			var useNumeric = (operandType === 'product' || operandType === 'category' || operandType === 'tag' || operandType === 'shipping_class');
			var normalizedOperand = useNumeric ? operand.map(function(id) { return parseInt(id, 10); }) : operand.map(function(id) { return String(id); });
			var intersection = cartItems.filter(function(item) {
				var normalizedItem = useNumeric ? parseInt(item, 10) : String(item);
				return normalizedOperand.indexOf(normalizedItem) !== -1;
			});
			return intersection.length > 0;
		},

		/**
		 * Check if cart only contains items from the specified list.
		 */
		evaluateCartOnlyContains: function(cartInfo, operandType, operand) {
			var cartItems = this.getCartItemsByType(cartInfo, operandType);
			if (!Array.isArray(operand)) {
				operand = [operand];
			}
			var useNumeric = (operandType === 'product' || operandType === 'category' || operandType === 'tag' || operandType === 'shipping_class');
			var normalizedOperand = useNumeric ? operand.map(function(id) { return parseInt(id, 10); }) : operand.map(function(id) { return String(id); });
			return cartItems.every(function(item) {
				var normalizedItem = useNumeric ? parseInt(item, 10) : String(item);
				return normalizedOperand.indexOf(normalizedItem) !== -1;
			});
		},

		/**
		 * Get cart items array by type (product, category, tag, shipping_class, product_type).
		 */
		getCartItemsByType: function(cartInfo, operandType) {
			var key = 'products';
			if (operandType === 'category') {
				key = 'categories';
			} else if (operandType === 'tag') {
				key = 'tags';
			} else if (operandType === 'shipping_class') {
				key = 'shipping_classes';
			} else if (operandType === 'product_type') {
				key = 'product_types';
			}
			var items = cartInfo[key] || [];
			return Array.isArray(items) ? items : [];
		},

		/**
		 * Check if any of the specified coupons are applied to the cart.
		 */
		evaluateCouponContains: function(cartInfo, couponCodes) {

			var appliedCoupons = cartInfo.applied_coupons || [];

			if (!Array.isArray(couponCodes)) {
				couponCodes = [couponCodes];
			}
			if(!Array.isArray(appliedCoupons) && Object.keys(appliedCoupons).length>0){
				appliedCoupons=Object.values(appliedCoupons);
			}

			// Normalize to lowercase for comparison
			couponCodes = couponCodes.map(function(code) {
				return String(code).toLowerCase();
			});

			// Check if any specified coupon is in the applied coupons
			for (var i = 0; i < couponCodes.length; i++) {
				if (appliedCoupons.indexOf(couponCodes[i]) !== -1) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Evaluate user condition client-side using cached user data from fkcfData.
		 */
		evaluateUserConditionClientSide: function(condition) {
			var userInfo = fkcfData.userInfo || {};
			var operator = condition.operator || '';
			var value = condition.value;

			// Normalize value for logged_in: extract from object/array, support yes/logged_in/1/true
			var valueStr = (value && typeof value === 'object' && value.key) ? String(value.key) : (Array.isArray(value) ? (value[0] || '') : String(value || ''));
			var expectLoggedIn = ['yes', 'logged_in', '1', 'true'].indexOf(valueStr.toLowerCase()) !== -1;
			var isLoggedIn = !!userInfo.is_logged_in;

			switch (operator) {
				case 'user_logged_in_eq':
					return isLoggedIn === expectLoggedIn;
				case 'user_logged_in_ne':
					return isLoggedIn !== expectLoggedIn;
				case 'user_role_eq':
					return this.evaluateUserRoleContains(userInfo, value);
				case 'user_role_ne':
					return !this.evaluateUserRoleContains(userInfo, value);
				default:
					this.debugLog('User condition ' + operator + ' not recognized, assuming false');
					return false;
			}
		},

		/**
		 * Check if user has any of the specified roles.
		 */
		evaluateUserRoleContains: function(userInfo, roles) {
			var userRoles = userInfo.roles || [];

			if (!Array.isArray(roles)) {
				roles = [roles];
			}
			roles = roles.map(function(r) { return (r && typeof r === 'object' && r.key) ? r.key : String(r); });

			for (var i = 0; i < roles.length; i++) {
				if (userRoles.indexOf(roles[i]) !== -1) {
					return true;
				}
			}

			return false;
		},

		applyFieldVisibility: function() {
			var self = this;
			var fields = this.visibilityMap.fields || {};
			var rules = this.rules || {};
			// Compute the FINAL per-field visibility before touching the DOM. This guarantees
			// a field that ends up "show" by the client rule is never left in the cleared state
			// produced by an earlier server-map "hide" pass (the cause of radio/checkbox resets).
			var decisions = {};

			self.debugLog('applyFieldVisibility: Processing ' + Object.keys(fields).length + ' fields from map, ' + Object.keys(rules).length + ' field rules');

			// First, record server visibility map decisions (cart/user rules evaluated server-side).
			// Server has correct cart state; client cartInfo can be stale on initial load or before fragments.
			Object.keys(fields).forEach(function(fieldId) {
				decisions[fieldId] = fields[fieldId];
			});

			// Then, evaluate field rules client-side, overriding the server decision only when the
			// rule has field-based conditions (real-time updates) or the field is not in the server map.
			Object.keys(rules).forEach(function(fieldId) {
				if (decisions.hasOwnProperty(fieldId) && !self.ruleHasFieldConditions(rules[fieldId])) {
					// Already decided by server map and rule has no field conditions; keep server decision.
					return;
				}

				self.debugLog('Evaluating rule for field: ' + fieldId);
				decisions[fieldId] = self.evaluateFieldRule(rules[fieldId]);
			});

			// Apply final decisions. Only fields whose FINAL outcome is "hide" are cleared.
			Object.keys(decisions).forEach(function(fieldId) {
				self.debugLog('Field ' + fieldId + ' (final): ' + (decisions[fieldId] ? 'SHOW' : 'HIDE'));
				if (decisions[fieldId]) {
					self.showField(fieldId);
				} else {
					self.hideField(fieldId);
				}
			});
		},

		/**
		 * When all fields of one address group are hidden by conditional rules,
		 * hide the other group's same_as checkbox and force-expand its fields.
		 */
		handleCrossGroupSameAs: function() {
			var fields = this.visibilityMap.fields || {};

			var allBillingHidden = this.areAllAddressFieldsHidden(fields, 'billing_');
			var allShippingHidden = this.areAllAddressFieldsHidden(fields, 'shipping_');

			if (allBillingHidden) {
				this.debugLog('All billing address fields hidden - hiding shipping same_as_billing checkbox');
				this.hideField('shipping_same_as_billing');
				// Force-show shipping fields using WFACP's class-based toggle mechanism.
				$('.wfacp_shipping_fields').each(function() {
					$(this).removeClass('wfacp_shipping_field_hide');
					$(this).addClass('wfacp_shipping_field_show');
				});
				$('#shipping_same_as_billing_field').addClass('wfacp_billing_field_active');
				$('.ship_to_different_address').val(1).prop('checked', true);
			}

			if (allShippingHidden) {
				this.debugLog('All shipping address fields hidden - hiding billing same_as_shipping checkbox');
				this.hideField('billing_same_as_shipping');
				// Force-show billing fields using WFACP's class-based toggle mechanism.
				$('.wfacp_billing_fields').each(function() {
					$(this).removeClass('wfacp_billing_field_hide');
					$(this).addClass('wfacp_billing_field_show');
				});
				$('#billing_same_as_shipping_field').addClass('wfacp_billing_field_active');
				$('#wfacp_billing_same_as_shipping').val(1);
			}
		},

		/**
		 * Check if all address fields in a group are hidden.
		 * Uses WFACP's own .wfacp_billing_fields / .wfacp_shipping_fields classes
		 * to identify the exact address sub-fields controlled by the same_as toggle.
		 */
		areAllAddressFieldsHidden: function(fields, prefix) {
			var cssClass = prefix === 'billing_' ? '.wfacp_billing_fields' : '.wfacp_shipping_fields';
			var addressFieldIds = [];

			$(cssClass).each(function() {
				var id = $(this).attr('id');
				if (id) {
					// Convert wrapper ID (e.g. billing_first_name_field) to field ID (billing_first_name).
					var fieldId = id.replace(/_field$/, '');
					if (fieldId.indexOf('same_as') === -1) {
						addressFieldIds.push(fieldId);
					}
				}
			});

			if (addressFieldIds.length === 0) {
				return false;
			}

			// All address fields must be explicitly hidden in the visibility map.
			// Fields not in the map have no rule and are visible by default.
			return addressFieldIds.every(function(fieldId) {
				return fields[fieldId] === false;
			});
		},

		/**
		 * Check if a rule has any field-based conditions (needs client-side evaluation for real-time updates).
		 */
		ruleHasFieldConditions: function(rule) {
			if (!rule || !rule.groups || rule.groups.length === 0) {
				return false;
			}
			for (var g = 0; g < rule.groups.length; g++) {
				var conditions = rule.groups[g].conditions || [];
				for (var c = 0; c < conditions.length; c++) {
					if (conditions[c].type === 'field') {
						return true;
					}
				}
			}
			return false;
		},

		/**
		 * Evaluate a field rule considering all condition types.
		 * Field conditions are evaluated client-side.
		 * Cart/user conditions use the last known server-side result.
		 */
		evaluateFieldRule: function(rule) {
			var self = this;

			if (!rule || !rule.groups || rule.groups.length === 0) {
				self.debugLog('evaluateFieldRule: No rule or empty groups, returning true');
				return true; // No rule = visible
			}

			var groupResults = [];

			rule.groups.forEach(function(group, index) {
				var groupResult = self.evaluateFieldRuleGroup(group);
				self.debugLog('evaluateFieldRule: Group ' + index + ' result: ' + groupResult);
				groupResults.push(groupResult);
			});

			// Apply group logic (OR between groups by default)
			var groupLogic = (rule.group_logic || 'OR').toUpperCase();
			var ruleSatisfied;

			if (groupLogic === 'AND') {
				ruleSatisfied = groupResults.every(function(result) { return result; });
			} else {
				ruleSatisfied = groupResults.some(function(result) { return result; });
			}

			// Apply action (show/hide)
			var action = (rule.action || 'show').toLowerCase();
			var finalResult = (action === 'show') ? ruleSatisfied : !ruleSatisfied;

			self.debugLog('evaluateFieldRule: groupLogic=' + groupLogic + ', ruleSatisfied=' + ruleSatisfied + ', action=' + action + ', finalResult=' + finalResult);

			return finalResult;
		},

		/**
		 * Evaluate a condition group for field rules (AND logic within group).
		 */
		evaluateFieldRuleGroup: function(group) {
			var self = this;

			if (!group || !group.conditions || group.conditions.length === 0) {
				self.debugLog('evaluateFieldRuleGroup: No group or empty conditions, returning false');
				return false;
			}

			self.debugLog('evaluateFieldRuleGroup: Evaluating ' + group.conditions.length + ' conditions');

			// All conditions in a group must be true (AND logic)
			return group.conditions.every(function(condition, index) {
				var result = self.evaluateFieldRuleCondition(condition);
				self.debugLog('evaluateFieldRuleGroup: Condition ' + index + ' (' + condition.type + ') result: ' + result);
				return result;
			});
		},

		/**
		 * Evaluate a single condition for field rules.
		 */
		evaluateFieldRuleCondition: function(condition) {
			if (!condition || !condition.type) {
				return false;
			}

			if (condition.type === 'field') {
				return this.evaluateFieldCondition(condition);
			}

			if (condition.type === 'cart') {
				return this.evaluateCartConditionClientSide(condition);
			}

			if (condition.type === 'user') {
				return this.evaluateUserConditionClientSide(condition);
			}

			// Unknown condition type - assume true
			return true;
		},

		evaluateFieldCondition: function(condition) {
			if (!condition || !condition.field_id) {
				return false;
			}
			var fieldValue = this.getFieldValue(condition.field_id);
			var operator = condition.operator;
			var compareValue = condition.value;
			var isArray = Array.isArray(fieldValue);
			var strVal = isArray ? '' : ((fieldValue !== undefined && fieldValue !== null) ? String(fieldValue) : '');
			var arrVal = isArray ? fieldValue.map(function(v) { return String(v); }) : [];

			this.debugLog('evaluateFieldCondition: field_id=' + condition.field_id + ', fieldValue="' + (isArray ? arrVal.join(',') : strVal) + '", operator=' + operator + ', compareValue="' + compareValue + '"');

			switch (operator) {
				case 'value_eq':
					if (isArray) {
						return arrVal.indexOf(String(compareValue)) !== -1;
					}
					if (compareValue === 'checked' || compareValue === 1 || compareValue === '1') {
						return !!(fieldValue && (String(fieldValue) === '1' || fieldValue === true));
					}
					return strVal === String(compareValue);

				case 'value_ne':
					if (isArray) {
						return arrVal.indexOf(String(compareValue)) === -1;
					}
					return strVal !== String(compareValue);

				case 'value_gt':
					return parseFloat(strVal || 0) > parseFloat(compareValue);

				case 'value_lt':
					return parseFloat(strVal || 0) < parseFloat(compareValue);

				case 'value_empty':
					return isArray ? arrVal.length === 0 : (!strVal || strVal.trim() === '');

				case 'value_not_empty':
					return isArray ? arrVal.length > 0 : strVal.trim() !== '';

				case 'value_contains':
					if (isArray) {
						return arrVal.indexOf(String(compareValue)) !== -1;
					}
					return (strVal || '').indexOf(String(compareValue)) !== -1;

				case 'value_in':
					var allowed = Array.isArray(compareValue) ? compareValue.map(function(v) { return String(v && typeof v === 'object' && v.key !== undefined ? v.key : v); }) : [String(compareValue && typeof compareValue === 'object' && compareValue.key !== undefined ? compareValue.key : compareValue)];
					if (isArray) {
						return arrVal.some(function(v) { return allowed.indexOf(v) !== -1; });
					}
					return allowed.indexOf(strVal) !== -1;

				case 'value_none':
					var excluded = Array.isArray(compareValue) ? compareValue.map(function(v) { return String(v && typeof v === 'object' && v.key !== undefined ? v.key : v); }) : [String(compareValue && typeof compareValue === 'object' && compareValue.key !== undefined ? compareValue.key : compareValue)];
					if (isArray) {
						return !arrVal.some(function(v) { return excluded.indexOf(v) !== -1; });
					}
					return excluded.indexOf(strVal) === -1;

				default:
					return false;
			}
		},

		getFieldValue: function(fieldId) {
			if (!fieldId) {
				return '';
			}
			var $scope = $('#wfacp_checkout_form').length ? $('#wfacp_checkout_form') : $(document);
			var $field = $scope.find('#' + fieldId);

			// Fallback: Aero Checkout / themes may use name instead of id for form fields.
			if ($field.length === 0) {
				$field = $scope.find('select[name="' + fieldId + '"], input[name="' + fieldId + '"], textarea[name="' + fieldId + '"], select[name="' + fieldId + '[]"], input[name="' + fieldId + '[]"], textarea[name="' + fieldId + '[]"]').first();
			}

			if ($field.length === 0) {
				return '';
			}

			if ($field.is(':checkbox')) {
				var name = $field.attr('name');
				var $checkboxes = $scope.find('input[name="' + name + '"]');
				// Check if it's a checkbox group (multiple checkboxes with same name or name[])
				if ($checkboxes.length > 1 || (name && name.indexOf('[]') !== -1)) {
					var checkedValues = [];
					$checkboxes.filter(':checked').each(function() {
						checkedValues.push($(this).val());
					});
					return checkedValues.length ? checkedValues : '';
				}
				return $field.is(':checked') ? '1' : '';
			}

			if ($field.is(':radio')) {
				var radioVal = $scope.find('input[name="' + fieldId + '"]:checked').val();
				return (radioVal !== undefined && radioVal !== null) ? String(radioVal) : '';
			}

			var val = $field.val();
			if (val === undefined || val === null) {
				return '';
			}
			// Multiselect returns array; preserve for value_in/value_none/value_eq
			if (Array.isArray(val)) {
				return val;
			}
			return String(val);
		},

		showSection: function(sectionId) {
			this.hiddenSections[sectionId] = false;
			this.debugLog('Showing section: ' + sectionId);

			var selectors = this.getSectionCssSelectors(sectionId);
			$(selectors.join(',')).removeClass('fkcf-section-hidden');
		},

		hideSection: function(sectionId) {
			this.hiddenSections[sectionId] = true;
			this.debugLog('Hiding section: ' + sectionId);

			var selectors = this.getSectionCssSelectors(sectionId);
			$(selectors.join(',')).addClass('fkcf-section-hidden');

			// Clear all field values in hidden section
			var $section = this.getSectionElement(sectionId);
			if ($section.length > 0) {
				$section.find('input, select, textarea').each(function() {
					var $field = $(this);
					if ($field.is(':checkbox') || $field.is(':radio')) {
						$field.prop('checked', false);
					} else {
						$field.val('');
					}
				});
			}
		},

		getSectionElement: function(sectionId) {
			// Primary: FKCF section class (matches add_section_identifier_class: {step}_fieldset_{index})
			var $section = $('.fkcf-section-' + sectionId);

			// Fallback: FunnelKit Checkout section wrapper
			if ($section.length === 0) {
				$section = $('.wfacp_' + sectionId + '_fields');
			}

			// Fallback: Standard WooCommerce section wrapper
			if ($section.length === 0) {
				$section = $('.woocommerce-' + sectionId + '-fields');
			}

			// Fallback: Section by ID
			if ($section.length === 0) {
				$section = $('#' + sectionId + '_fields');
			}

			return $section;
		},

		/**
		 * Resolve the input element(s) for a field id, falling back from id to name-based lookup.
		 */
		getFieldInputs: function(fieldId) {
			var $scope = $('#wfacp_checkout_form').length ? $('#wfacp_checkout_form') : $(document);
			var $input = $scope.find('#' + fieldId);
			if ($input.length === 0) {
				$input = $scope.find('select[name="' + fieldId + '"], input[name="' + fieldId + '"], textarea[name="' + fieldId + '"], select[name="' + fieldId + '[]"], input[name="' + fieldId + '[]"], textarea[name="' + fieldId + '[]"]');
			}
			return $input;
		},

		/**
		 * Snapshot a field's current value before it is cleared, so a hidden-then-reshown
		 * field within one evaluation cycle keeps the user's selection. Only stores a
		 * meaningful (non-empty) value and never overwrites an existing snapshot.
		 */
		cacheFieldValue: function(fieldId, $input) {
			if (this.cachedValues.hasOwnProperty(fieldId)) {
				return;
			}

			var snapshot = null;

			if ($input.is(':radio')) {
				var $checkedRadio = $input.filter(':checked');
				if ($checkedRadio.length > 0) {
					snapshot = {kind: 'radio', value: $checkedRadio.val()};
				}
			} else if ($input.is(':checkbox')) {
				var checkedVals = [];
				$input.filter(':checked').each(function() {
					checkedVals.push($(this).val());
				});
				if (checkedVals.length > 0) {
					snapshot = {kind: 'checkbox', values: checkedVals};
				}
			} else {
				var val = $input.val();
				if (val !== undefined && val !== null && val !== '') {
					snapshot = {kind: 'value', value: val};
				}
			}

			if (snapshot !== null) {
				this.cachedValues[fieldId] = snapshot;
			}
		},

		/**
		 * Restore a previously cached field value when the field is re-shown, then drop the cache.
		 */
		restoreFieldValue: function(fieldId) {
			if (!this.cachedValues.hasOwnProperty(fieldId)) {
				return;
			}

			var snapshot = this.cachedValues[fieldId];
			var $input = this.getFieldInputs(fieldId);

			if ($input.length) {
				if (snapshot.kind === 'radio') {
					$input.filter(function() {
						return String($(this).val()) === String(snapshot.value);
					}).prop('checked', true);
				} else if (snapshot.kind === 'checkbox') {
					$input.each(function() {
						if (snapshot.values.indexOf($(this).val()) !== -1) {
							$(this).prop('checked', true);
						}
					});
				} else {
					$input.val(snapshot.value);
				}
			}

			delete this.cachedValues[fieldId];
		},

		showField: function(fieldId) {
			this.hiddenFields[fieldId] = false;
			this.debugLog('Showing field: ' + fieldId);

			var selectors = this.getFieldCssSelectors(fieldId);
			$(selectors.join(',')).removeClass('fkcf-hidden');

			// Restore any value cleared by a prior hideField() in the same cycle (non-destructive).
			if (this.cachedValues.hasOwnProperty(fieldId)) {
				this.restoreFieldValue(fieldId);
			} else {
				// First-time show with no cached value: apply the PHP-defined default if present.
				this.applyFieldDefault(fieldId);
			}
		},

		applyFieldDefault: function(fieldId) {
			// Prefer the data-fkcf-default attribute embedded in the wrapper element at render time
			// (most reliable — comes directly from the PHP $value passed to wfacp_radio renderer).
			var $wrapper   = $('#' + fieldId + '_field');
			var defaultVal = $wrapper.length ? $wrapper.data('fkcf-default') : undefined;

			// Fallback to fkcfData.fieldDefaults for non-radio fields or if attribute is absent.
			if (defaultVal === undefined || defaultVal === '') {
				var defaults = (typeof fkcfData !== 'undefined' && fkcfData.fieldDefaults) ? fkcfData.fieldDefaults : {};
				defaultVal   = defaults[fieldId];
			}

			if (!defaultVal) {
				return;
			}

			var $input = this.getFieldInputs(fieldId);
			if (!$input.length) {
				return;
			}

			if ($input.is(':radio')) {
				if ($input.filter(':checked').length === 0) {
					$input.filter(function() {
						return String($(this).val()) === String(defaultVal);
					}).prop('checked', true);
				}
			} else if ($input.is(':checkbox')) {
				if ($input.filter(':checked').length === 0) {
					var defaultVals = String(defaultVal).split(',');
					$input.each(function() {
						if (defaultVals.indexOf($(this).val()) !== -1) {
							$(this).prop('checked', true);
						}
					});
				}
			} else {
				if (!$input.val()) {
					$input.val(defaultVal);
				}
			}
		},

		hideField: function(fieldId) {
			this.hiddenFields[fieldId] = true;
			this.debugLog('Hiding field: ' + fieldId);

			var selectors = this.getFieldCssSelectors(fieldId);
			$(selectors.join(',')).addClass('fkcf-hidden');

			// Clear field value when hiding, but cache it first so it can be restored if the
			// field is re-shown within the same evaluation cycle.
			var $input = this.getFieldInputs(fieldId);

			if ($input.length) {
				this.cacheFieldValue(fieldId, $input);
				if ($input.is(':checkbox') || $input.is(':radio')) {
					$input.prop('checked', false);
				} else {
					$input.val('');
				}
			}

			// Birthday date field has 3 sub-selects instead of a single input
			if (fieldId === 'bwfan_birthday_date') {
				$('#bwfan_birthday_date_dd, #bwfan_birthday_date_mm, #bwfan_birthday_date_yy').val('');
			}
		},

		handleFragmentUpdate: function(visibilityMapJson, cartInfoJson) {
			this.debugLog('handleFragmentUpdate called');

			try {
				var visibilityMap = JSON.parse(visibilityMapJson);
				this.debugLog('Parsed visibility map: ' + JSON.stringify(visibilityMap));

				// Backwards compatibility: convert old format to new format
				if (!visibilityMap.sections && !visibilityMap.fields) {
					visibilityMap = {sections: {}, fields: visibilityMap};
				}

				// Update our visibility map
				this.visibilityMap = visibilityMap;

				// Update cart info if provided
				if (cartInfoJson) {
					try {
						var cartInfo = JSON.parse(cartInfoJson);
						fkcfData.cartInfo = cartInfo;
						this.debugLog('Updated cart info: ' + JSON.stringify(cartInfo));
					} catch (e) {
						this.debugLog('Could not parse cart info: ' + e.message);
					}
				}

				// Re-apply visibility with new map
				this.applyVisibility();

				this.debugLog('Fragment update completed successfully');
			} catch (e) {
				this.debugLog('ERROR parsing visibility map: ' + e.message);
				console.error('Error parsing visibility map:', e);
			}
		},

		debugLog: function(message) {
			// Debug logging disabled.
		}
	};

	// Initialize
	$(document).ready(function() {
		FKCF_Frontend.init();
	});

	// Re-initialize on WFACP events.
	// Guarded by `initialized`: init() bails when fkcfData is absent (no conditional rules on
	// this checkout), but this handler lives outside init(), so without the guard an inactive
	// module still mutated the form on every step switch.
	$(document.body).on('wfacp_step_switching', function() {
		if (!FKCF_Frontend.initialized) {
			return;
		}
		FKCF_Frontend.applyVisibility();
	});

})(jQuery);
