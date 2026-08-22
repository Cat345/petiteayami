// Define wfacp_frontend if not already defined
if (typeof wfacp_frontend === 'undefined') {
    window.wfacp_frontend = {
        is_mobile_phone: 'no',
        is_desktop: 'yes',
        stripe_smart_show_on_desktop: 'no',
        fkwcs_legacy_express: 'no',
        smart_button_hide_timeout: 200,
        smart_button_hide_timeout_m: 200,
        smart_button_wrappers: {
            "dynamic_buttons": {
                "#wfacp_smart_button_stripe_gpay_apay": "#wfacp_smart_button_stripe_gpay_apay",
                "#wfacp_smart_button_wc_payment_gpay_apay #wcpay-payment-request-button": "#wfacp_smart_button_wc_payment_gpay_apay",
                "#wfacp_smart_button_wc_payment_gpay_apay #wcpay-express-checkout-element": "#wfacp_smart_button_wc_payment_gpay_apay",
                "#wfacp_smart_button_wc_payment_woo_pay #wcpay-woopay-button": "#wfacp_smart_button_wc_payment_woo_pay",
                "#wfacp_smart_button_woo_braintree_braintree_paypal .wc_braintree_banner_gateway_braintree_paypal": "#wfacp_smart_button_woo_braintree_braintree_paypal",
                "#wfacp_smart_button_woo_braintree_braintree_googlepay .wc_braintree_banner_gateway_braintree_googlepay": "#wfacp_smart_button_woo_braintree_braintree_googlepay",
                "#wfacp_smart_button_woo_braintree_braintree_applepay .wc_braintree_banner_gateway_braintree_applepay": "#wfacp_smart_button_woo_braintree_braintree_applepay",
                "#wfacp_smart_button_paymentplugins_wc_stripe .banner_payment_method_stripe_googlepay": "#wfacp_smart_button_paymentplugins_wc_stripe",
                "#wfacp_smart_button_paymentplugins_wc_stripe .banner_payment_method_stripe_applepay": "#wfacp_smart_button_paymentplugins_wc_stripe",
                "#wfacp_smart_button_paymentplugins_wc_stripe .banner_payment_method_stripe_payment_request": "#wfacp_smart_button_paymentplugins_wc_stripe",
                "#wfacp_smart_button_paypal_express #checkout_paypal_message .angelleye_smart_button_checkout_top": "#wfacp_smart_button_paypal_express",
                "#wfacp_smart_button_angelleye_ppcp #angelleye_ppcp_checkout_top": "#wfacp_smart_button_angelleye_ppcp",
                "#wfacp_smart_button_wc_braintree .sv-wc-apple-pay-button": "#wfacp_smart_button_wc_braintree",
                "#wfacp_smart_button_fkwcs_google_pay": "#wfacp_smart_button_fkwcs_google_pay"
            },
            "no_conflict_buttons": [
                "#wfacp_smart_button_amazon_pay #pay_with_amazon",
                "#wfacp_smart_button_wffn_fake_cc",
                "#wfacp_smart_button_wffn_fake_paypal",
                "#wfacp_smart_button_paymentplugins_wc_ppcp",
                "#wfacp_smart_button_funnelkit_paypal"
            ]
        }
    };
}

(function ($) {

    class WFACP_Smart_Button_JS {
        constructor() {
            this.smart_button_id = '#wfacp_smart_buttons';
            this.loading_gif = 'wfacp-dynamic-checkout-loading';
            this.button_displayed = false;
			this.allpromise_rendered=false;
			this.promises=[];
			this.dynamic_promises=[];
			this.evaluated_selectors={};
			this._showHideTimer=null;
			this._amazonTimer=null;
			// Single-reveal state. The express row is revealed exactly once, when
			// the final button set is known, so the flex layout is sized at its
			// final column count from the very first paint (no full-width->1/N
			// flicker, no shuffle as gateways trickle in).
			//
			// "Final set is known" = every candidate button container the server
			// rendered has either produced a button (ready) or been determined
			// unavailable (rejected). This is fully generic - the candidate count
			// is read from the DOM, no gateway is special-cased.
			//
			// The stall timer is NOT a fixed wall-clock cut-off: it RESETS on every
			// settlement (a button becoming ready or being rejected), so it waits
			// through slow, staggered arrivals on throttled networks. It only fires
			// when detection has genuinely stalled for `_revealStall` ms with no new
			// progress - a safety net for a gateway that never resolves at all.
			//
			// Because it only has to bridge the GAP BETWEEN arrivals (not the total
			// load time), it must exceed the slowest realistic gap. The slowest is
			// the Stripe wallet buttons (Apple/Google Pay), which need an async
			// canMakePayment() check - Stripe itself allows 8s per button before
			// retrying. Observed ~10.7s gap on a heavily throttled connection, so we
			// use a generous window; on any normal network every button settles in a
			// second or two and "all candidates settled" reveals immediately anyway.
			this._revealStall = 20000; // ms of NO new settlement before revealing whatever rendered
			this._revealMaxTimer = null;
			// Some gateways stay completely silent when a wallet is unavailable
			// (no mutation, no reject marker, no event) - e.g. FunnelKit Stripe's
			// express slots with no usable wallet in the browser, or FunnelKit
			// PayPal skipping Pay Later. Such candidates can never settle, which
			// would hold the reveal hostage until the stall timer. This deadline
			// (armed ONCE at detection start, never reset) force-settles whatever
			// is still undecided so the first reveal has a hard upper bound; a
			// genuinely slow button that mounts later still appears through the
			// post-reveal straggler path in showButton()/onButtonReady().
			this._slotDeadline = 5000; // ms before never-settling candidates are settled as unavailable
			this._slotDeadlineTimer = null;
			this._deadlinePassed = false;
			this._revealed = false;
			this._unavailable = {}; // candidate selectors confirmed rejected/absent
			this._tracked = []; // container nodes the detector is actually watching
            this.available_buttons = {};
            this.wcEvents();


        }

		wcEvents() {

			window.addEventListener('keydown', (e) => {
                if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.metaKey && e.key === 'r')) {
                    this.showShimmer()
                }
            });
            window.addEventListener('load', (e) => {
                const navEntries = performance.getEntriesByType("navigation");
                const navType = navEntries.length > 0 ? navEntries[0].type : performance.navigation.type;

                // On a reload the 'load' event can fire AFTER the buttons have
                // already been revealed (the batched reveal settles on
                // DOMContentLoaded). Re-showing the shimmer over already-rendered
                // buttons just flashes them, so only shimmer while they are still
                // pending (not yet revealed).
                if ((navType === 'reload' || navType === 1) && false === this._revealed) {
                    this.showShimmer()
                }
            });

            window.addEventListener('unload', (e) => {
                this.showShimmer()
            });

            if (document.readyState !== 'loading') {
                this.DOMLoaded();
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    this.DOMLoaded();
                });
            }
        }

        isMobilePhone() {
            return ('yes' === wfacp_frontend.is_mobile_phone);
        }

        showShimmer() {
            $('#wfacp_smart_buttons').removeClass('wfacp_smart_buttons_active');
            $('.wfacp_single_btn_shimmer').show();
            $('.fkwcs_smart_button_trigger').removeClass('hide').show();
        }

        DOMLoaded() {
            $(document.body).on('updated_checkout', async() => {
                await this.runButtons();
            });
            $(document.body).on('fkwcs_generate_smart_buttons', (e, ...args) => {
                this.fkwcs_smart_buttons_shown();
            });
			// For old stripe express checkout
			$(document.body).on('fkwcs_smart_buttons_showed', (e, ...args) => {
                this.promoteFkwcsAppleSlots(args);
                this.promoteFkwcsExpressSlot();
                this.fkwcs_smart_buttons_shown();
            });
			$(document.body).on('fkwcs_google_ready_pay', (e, ...args) => {
                this.demoteFkwcsExpressSlot();
                this.fkwcs_smart_buttons_shown();
            });
			// The legacy payment-request flow's explicit "no wallet" outcome
			// (canMakePayment() resolved empty). The gateway keeps its wrapper
			// hidden and never mutates the container, so this event is the only
			// negative signal it emits - settle the express slot on it instead
			// of letting the slot sit undecided.
			$(document.body).on('fkwcs_smart_buttons_not_available', () => {
                this.demoteFkwcsExpressSlot();
                this.fkwcs_smart_buttons_shown();
            });

			this.checkButtons();
			// Bound the first reveal: candidates whose gateway never signals an
			// outcome are force-settled after _slotDeadline ms.
			this._slotDeadlineTimer = setTimeout(() => {
				this.settleSilentCandidates();
			}, this._slotDeadline);
			// Arm the stall-timer backstop once; from here on it resets only on
			// genuine settlements (showButton/markUnavailable), never on
			// checkout-update churn.
			this.bumpStallTimer();
			$(document.body).trigger('wfacp_smart_buttons_dom_loaded');
        }

        fkwcs_smart_buttons_shown() {
            this.handleCheckout();
        }

        /**
         * FunnelKit Stripe versions below 1.14.1 render the express (payment
         * request) button before DOMContentLoaded and never mutate its container
         * afterwards, so mutation-based detection alone can never settle it.
         */
        isLegacyFkwcs() {
            return 'yes' === wfacp_frontend.fkwcs_legacy_express;
        }

        /**
         * fkwcs_google_ready_pay means the individual Google Pay integration is
         * live; a Google Pay flavoured payment-request (gpay_apay) slot would
         * duplicate it, so settle that candidate as unavailable so it neither
         * blocks the reveal until the stall timer nor claims an empty flex
         * column. Apple Pay takes precedence: on Apple Pay devices the express
         * slot renders Apple Pay, which does not duplicate the individual
         * Google Pay button - both must stay visible, so an Apple Pay flavoured
         * slot is never demoted.
         */
        demoteFkwcsExpressSlot() {
            if (false === this.isLegacyFkwcs()) {
                return;
            }
            let express = '#wfacp_smart_button_fkwcs_gpay_apay';
            let el = document.querySelector(this.smart_button_id + ' ' + express);
            if (null === el) {
                return;
            }
            // Skip only when the slot hosts a LIVE Apple Pay wrapper (both should
            // stay visible - Apple Pay does not duplicate the individual Google
            // Pay button).
            if (this.fkwcsSlotShowsLiveApplePay(el)) {
                return;
            }
            if (this.available_buttons.hasOwnProperty(express)) {
                delete this.available_buttons[express];
            }
            $(express).hide();
            this.markUnavailable(express);
            this.updateWrapperClass(true);
        }

        /**
         * Whether an express slot currently hosts a LIVE Apple Pay button. The
         * wallet wrapper divs (.fkwcs_apple_pay_button etc.) are static mount
         * targets present in the markup regardless of availability, so presence
         * alone means nothing. The new express-element flow stamps
         * data-fkwcs-available="yes" on an available wallet's wrapper; the
         * legacy payment-request flow instead classes the resolved button with
         * fkwcs_express_apple_pay / fkwcs_ec_applepay_button-<theme>.
         */
        fkwcsSlotShowsLiveApplePay(el) {
            return null !== el.querySelector('.fkwcs_apple_pay_button[data-fkwcs-available="yes"], .fkwcs_express_apple_pay, [class*="fkwcs_ec_applepay_button"]');
        }

        /**
         * Whether a legacy express slot currently hosts a LIVE payment-request
         * button. The gateway prints its wrapper markup server-side on every
         * page load (hidden), so children alone prove nothing; makePayment()
         * flips the wrapper to display:block only after canMakePayment() found
         * a wallet. Read the wrapper's OWN computed display (display is not
         * inherited, so the WFACP container being hidden does not mask it).
         */
        fkwcsSlotShowsLiveButton(el) {
            let wrapper = el.querySelector('.fkwcs_stripe_smart_button_wrapper');
            if (null === wrapper) {
                return false;
            }
            return 'none' !== window.getComputedStyle(wrapper).display;
        }

        /**
         * Runs on fkwcs_smart_buttons_showed: the legacy payment-request flow
         * confirmed a wallet and made its wrapper visible. Settle the express
         * slot as available - including restoring it if the slot deadline
         * already settled it as unavailable because canMakePayment() resolved
         * late (showButton()'s straggler path shows it even post-reveal).
         */
        promoteFkwcsExpressSlot() {
            if (false === this.isLegacyFkwcs()) {
                return;
            }
            let express = '#wfacp_smart_button_fkwcs_gpay_apay';
            let el = document.querySelector(this.smart_button_id + ' ' + express);
            if (null === el || false === this.fkwcsSlotShowsLiveButton(el)) {
                return;
            }
            if (true === this._unavailable[express]) {
                delete this._unavailable[express];
            }
            $(express).show();
            this.showButton(express);
        }

        /**
         * Runs on fkwcs_smart_buttons_showed. The event payload carries the
         * express availability map ({applePay: true, googlePay: ...} - both the
         * legacy payment-request result and the express element buttons map
         * have this shape), and Stripe marks each live wrapper with a
         * per-wallet class (fkwcs_apple_pay_button). When Apple Pay is
         * available its slot must be shown even if it was previously settled
         * as unavailable - by the untracked-candidate pass (the dedicated
         * apple_pay container has no wrapper registration) or by a demote that
         * ran before Stripe classified the slot.
         */
        promoteFkwcsAppleSlots(args) {
            if (false === this.isLegacyFkwcs()) {
                return;
            }
            let map = null;
            for (let a = 0; a < args.length; a++) {
                if (null !== args[a] && 'object' === typeof args[a] && 'applePay' in args[a]) {
                    map = args[a];
                    break;
                }
            }
            let appleAvailable = null !== map && true === map.applePay;
            if (false === appleAvailable) {
                return;
            }
            let slots = [ '#wfacp_smart_button_fkwcs_apple_pay', '#wfacp_smart_button_fkwcs_gpay_apay' ];
            for (let c = 0; c < slots.length; c++) {
                let slot = slots[c];
                let el = document.querySelector(this.smart_button_id + ' ' + slot);
                if (null === el) {
                    continue;
                }
                // Promote only slots hosting a LIVE Apple Pay wrapper; a Google
                // Pay flavoured express slot stays demoted next to the individual
                // Google Pay button.
                let liveApple;
                if ('#wfacp_smart_button_fkwcs_apple_pay' === slot) {
                    liveApple = 'yes' === el.getAttribute('data-fkwcs-available') || null !== el.querySelector('[data-fkwcs-available="yes"]');
                } else {
                    liveApple = this.fkwcsSlotShowsLiveApplePay(el);
                }
                if (false === liveApple) {
                    continue;
                }
                if (true === this._unavailable[slot]) {
                    delete this._unavailable[slot];
                }
                $(slot).show();
                this.showButton(slot);
            }
        }

        updateWrapperClass(update_count = false) {



            let btn_counts = Object.keys(this.available_buttons).length;
            if (btn_counts > 0) {
                $('.wfacp_smart_button_outer_buttons').attr('count', btn_counts);
            }
        }


        handleCheckout() {
			if(!this.allpromise_rendered){
				return;
			}

  			let smart_buttons = document.querySelector(this.smart_button_id);

            if (null == smart_buttons) {
                return;
            }
            this.handleAmazonShadowButton();
            this.updateWrapperClass();
            // Re-check whether the final set is now complete. Deliberately does
            // NOT touch the stall timer: updated_checkout churn (field edits,
            // shipping recalcs) is not settlement progress, and resetting the
            // deadline here let it be pushed back indefinitely.
            this.maybeReveal();
        }

        /**
         * Number of candidate button slots the server rendered for this checkout.
         * One `.wfacp_smart_button_container` is output per configured gateway, so
         * this is the maximum number of express buttons that can appear - derived
         * from the DOM, not hardcoded.
         */
        candidateCount() {
            return document.querySelectorAll(this.smart_button_id + ' .wfacp_smart_button_container').length;
        }

        readyCount() {
            return Object.keys(this.available_buttons).length;
        }

        /**
         * A candidate is "settled" once we know its outcome: it either rendered a
         * button (counted in available_buttons) or was rejected/absent (recorded
         * in _unavailable). When every candidate is settled we know the final
         * column count and can reveal the row a single time.
         */
        settledCount() {
            return this.readyCount() + Object.keys(this._unavailable).length;
        }

        markUnavailable(selector) {
            if (true === this._unavailable[selector]) {
                return;
            }
            this._unavailable[selector] = true;
            // A rejection is progress too - reset the stall timer and re-check.
            this.bumpStallTimer();
            this.maybeReveal();
        }

        /**
         * (Re)start the stall timer. Called when detection starts and again on
         * every settlement, so the timer only fires after `_revealStall` ms with
         * NO new progress. This lets us wait through slow, staggered arrivals on
         * throttled networks instead of cutting off buttons that are simply late.
         */
        bumpStallTimer() {
            if (true === this._revealed) {
                return;
            }
            if (null !== this._revealMaxTimer) {
                clearTimeout(this._revealMaxTimer);
            }
            this._revealMaxTimer = setTimeout(() => {
                this.onStall();
            }, this._revealStall);
        }

        onStall() {
            this._deadlinePassed = true;
            this._revealMaxTimer = null;
            if (true === this._revealed) {
                return;
            }
            if (this.readyCount() > 0) {
                this.finalizeReveal();
            }
            // If nothing rendered yet, stay hidden; a late arrival will reveal
            // immediately via onButtonReady() (stall window has now elapsed).
        }

        /**
         * Called whenever a candidate button becomes ready. Reveals only when the
         * final set is known (all candidates settled); otherwise it resets the
         * stall timer and keeps waiting. If the stall window already elapsed, a
         * straggler is shown at once.
         */
        onButtonReady() {
            if (true === this._revealed) {
                // Row already shown; showButton() has made the new container
                // visible and refreshed the count. (Rare post-stall straggler.)
                return;
            }
            if (true === this._deadlinePassed) {
                this.finalizeReveal();
                return;
            }
            // A new button is progress - keep waiting for the rest.
            this.bumpStallTimer();
            this.maybeReveal();
        }

        maybeReveal() {
            if (true === this._revealed) {
                return;
            }
            this.settleUntrackedCandidates();
            let candidates = this.candidateCount();
            if (candidates > 0 && this.settledCount() >= candidates) {
                this.finalizeReveal();
            }
        }

        /**
         * Reveal the whole express row in one layout pass at its final column
         * count. Runs at most once.
         */
        finalizeReveal() {
            if (true === this._revealed) {
                return;
            }
            if (null !== this._revealMaxTimer) {
                clearTimeout(this._revealMaxTimer);
                this._revealMaxTimer = null;
            }
            if (null !== this._slotDeadlineTimer) {
                clearTimeout(this._slotDeadlineTimer);
                this._slotDeadlineTimer = null;
            }
            this.updateWrapperClass();
            if (this.readyCount() === 0) {
                this.hideButtons();
                return;
            }
            // Note: a single-button visibility guard used to live here. It called
            // `.is(":visible")` on the wrapper while its ancestor #wfacp_smart_buttons was
            // still display:none (loading), so it always read false and deadlocked the
            // single-express-button case (e.g. only Apple Pay enabled) — the row never
            // revealed. readyCount() > 0 above already guarantees an available button,
            // so that guard was both redundant and incorrect and has been removed.
            this.showButtons();
            $('#wfacp_smart_buttons').addClass('wfacp_smart_buttons_active');
            this._revealed = true;
        }


        getAvailableButtons() {
            return wfacp_frontend.smart_button_wrappers.dynamic_buttons;
        }

        noConflictButton() {
            return wfacp_frontend.smart_button_wrappers.no_conflict_buttons;
        }

        async checkButtons() {
			let btn_promise=[];

            let buttons = this.getAvailableButtons();
            for (let i in buttons) {
                let parent = buttons[i];
				let btn=this.domInsert(parent,i);

				if(null!==btn){
					this.dynamic_promises.push(btn);
				}
            }

			// Track no-conflict containers now (their probes only run on
			// updated_checkout) so the untracked-candidate pass does not settle
			// them as unavailable before they have been probed.
			let noConflict = this.noConflictButton();
			for (let c = 0; c < noConflict.length; c++) {
				this.trackContainer(document.querySelector(noConflict[c]));
			}
        }

		trackContainer(el) {
			if (null !== el && -1 === this._tracked.indexOf(el)) {
				this._tracked.push(el);
			}
		}

		/**
		 * Candidates with no detection path (no observer armed, no probe pending)
		 * can never settle - e.g. a container rendered by a gateway whose wrapper
		 * is not registered, like the fkwcs individual Apple Pay slot. Settle them
		 * as unavailable so they do not block the reveal until the stall timer.
		 */
		settleUntrackedCandidates() {
			if (false === this.isLegacyFkwcs()) {
				return;
			}
			let containers = document.querySelectorAll(this.smart_button_id + ' .wfacp_smart_button_container');
			Array.prototype.forEach.call(containers, (el, idx) => {
				let tracked = this._tracked.some(function (node) {
					return node === el || el.contains(node) || node.contains(el);
				});
				if (false === tracked) {
					this.markUnavailable('#' + (el.id || 'wfacp-untracked-candidate-' + idx));
				}
			});
		}
		/**
		 * Force-settle every candidate still undecided when the detection
		 * deadline fires. Not legacy-gated: silent-on-unavailable gateways exist
		 * on current plugin versions too, and the fast path is unaffected - if
		 * everything settled already this is a no-op (and the reveal has
		 * cancelled the timer).
		 */
		settleSilentCandidates() {
			let containers = document.querySelectorAll(this.smart_button_id + ' .wfacp_smart_button_container');
			Array.prototype.forEach.call(containers, (el, idx) => {
				let id = '#' + (el.id || 'wfacp-silent-candidate-' + idx);
				if ('yes' !== this.available_buttons[id] && true !== this._unavailable[id]) {
					this.markUnavailable(id);
				}
			});
		}

		_noConflictButton(){
			let noConflictButtons = this.noConflictButton();
            for (let c = 0; c < noConflictButtons.length; c++) {
				let btn=this.findButtonElements(noConflictButtons[c]);
				if(null!==btn){

					this.promises.push(btn);
				}
            }

		}
		async runButtons() {
			// Reset the promise set each run from the cached dynamic-button promises so
			// the array does not grow unbounded across repeated updated_checkout events.
			this.promises = this.dynamic_promises.slice();
			// Invalidate the per-selector availability cache each run so a gateway that
			// became unavailable mid-session (e.g. shipping zone change) is re-evaluated
			// instead of being shown from a stale `true` result.
			this.evaluated_selectors = {};
			this._noConflictButton();
			const TIMEOUT_MS = 1200 * this.promises.length;
			const minTimeout = Math.min(3000, TIMEOUT_MS);
			const timeoutResult = { result: 'timeout', noMethods: true, availablePaymentMethods: false, button_type: 'unknown' };
			const timeoutPromise = new Promise((resolve) => {
				setTimeout(() => {
                    resolve(timeoutResult);
                }, minTimeout);
			});
			const withTimeout = (p) => Promise.race([ p, timeoutPromise ]);
			await Promise.all(this.promises.map(withTimeout));
			this.allpromise_rendered = true;
			this.showButtonOnMobile();
			this.handleCheckout();
		}
        findButtonElements(id) {
            let container = document.querySelector(id);
            if (null == container) {
				return null;
			}
			this.trackContainer(container);
			// Do not re-arm a fresh 2500ms timer for a selector already evaluated;
			// reuse the cached result so repeated events do not re-probe/re-show.
			if (this.evaluated_selectors.hasOwnProperty(id)) {
				let cached = this.evaluated_selectors[id];
				if (true === cached) {
					this.showButton(id);
				} else {
					this.markUnavailable(id);
				}
				return Promise.resolve({'parent':id,success:cached});
			}
			return  new Promise((resolve)=>{
				setTimeout(()=>{
					if($(id).find('.wfacp_reject_button').length>0){
						this.evaluated_selectors[id]=false;
						this.markUnavailable(id);
						resolve({'parent':id,success:false});
					}else{
						this.evaluated_selectors[id]=true;
						resolve({'parent':id,success:true});
						this.showButton(id);
					}
				},2500);
			});

        }


        domInsert(parent, selector) {
			let container = document.querySelector(selector);
			if (null == container) {
                return null;
            }
			this.trackContainer(container);
			return  new Promise((resolve,reject)=>{
				if (window.MutationObserver) {
					window.fkwcs_smart_buttons_shown_t1 = true;
					const observer = new MutationObserver((mutationsList) => {
						for (let mutation of mutationsList) {
							if (mutation.type === 'childList') {
								observer.disconnect();
								if($(parent).find('.wfacp_reject_button').length>0){
									this.markUnavailable(parent);
									resolve({'parent':parent,success:false});
								}else{
									resolve({'parent':parent,success:true});
									this.showButton(parent);
								}
								return;
							}
						}
					});
					observer.observe(container, {childList: true, subtree: true});
					// The legacy FunnelKit Stripe express checkout renders before
					// DOMContentLoaded and never mutates the container again, so the
					// observer alone would wait forever. Settle from OBSERVED state
					// only: the pre-existing children are static mount markup the
					// gateway prints wallet-or-not (and it never prints
					// .wfacp_reject_button), so "has children + no reject marker"
					// proves nothing - it must not settle the slot as available.
					// A slot with no live button yet stays undecided here; its
					// outcome arrives via fkwcs_smart_buttons_showed /
					// fkwcs_smart_buttons_not_available, or the slot deadline
					// settles it as unavailable. Scoped to fkwcs containers on
					// legacy plugin versions only.
					if (container.children.length > 0 && -1 !== parent.indexOf('fkwcs') && this.isLegacyFkwcs()) {
						if ($(parent).find('.wfacp_reject_button').length > 0) {
							this.markUnavailable(parent);
							resolve({'parent': parent, success: false});
						} else if (this.fkwcsSlotShowsLiveButton(container)) {
							resolve({'parent': parent, success: true});
							this.showButton(parent);
						}
					}
				} else {
					// Fallback code for older browsers that do not support MutationObserver
					container.addEventListener('DOMNodeInserted', () => {
						if($(parent).find('.wfacp_reject_button').length > 0){
							this.markUnavailable(parent);
							resolve({'parent':parent,success:false});
						}else{

							resolve({'parent':parent,success:true});
							this.showButton(parent);
						}
					});
				}
			});
        }

        showButtonOnMobile() {
            if (this.isMobilePhone() && Object.keys(this.available_buttons).length > 0) {
                this.maybeReveal();
            }
        }

        showButton(button_id) {
            let p = document.querySelector(button_id);
            if (null != p) {
                this.available_buttons[button_id] = 'yes';
                p.style.display = 'block';
                this.updateWrapperClass(true);
                this.button_displayed = true;
                // Evaluate the reveal: shows the row only once the final set is
                // known (or immediately if the deadline has already passed).
                this.onButtonReady();
            } else {
            }
        }

        fkwcs_smart_buttons_ready(force = false) {
            return true;
        }

        showButtons(force = false) {


            if (false === this.fkwcs_smart_buttons_ready(force) && force === false) {
                return;
            }

			let smart_buttons = document.querySelector(this.smart_button_id);

            // Reveal the wrapper only here, once a usable button has been confirmed.
            if (null != smart_buttons) {
                smart_buttons.style.display = 'block';
                smart_buttons.classList.remove(this.loading_gif);
            }

            this.button_displayed = true;
            $('span.wfacp_single_btn_shimmer').hide();
            $('.fkwcs_smart_button_trigger').addClass('hide');
            this.handleAmazonButton(smart_buttons);

        }


        handleAmazonButton() {

            let amazon_id = document.querySelector('#pay_with_amazon .amazonpay-button-inner-image');
            if (null == amazon_id) {
                return;
            }
            document.querySelector('#pay_with_amazon').classList.add("wfacp-amazon-active-image");

        }

        handleAmazonShadowButton() {

            let smart_buttons_wrapper = null;
            if ($('.wfacp_smart_button_container .wc-amazon-payments-advanced-populated').length > 0) {
                smart_buttons_wrapper = $('#wfacp_smart_buttons');
                smart_buttons_wrapper.addClass('wfacp_amazon_blocked');
            }


            if (null !== this._amazonTimer) {
                clearTimeout(this._amazonTimer);
            }

            this._amazonTimer = setTimeout(() => {
                let element = document.getElementById('pay_with_amazon');
                if (null === element) {
                    return;
                }

                if (typeof element.shadowRoot == "object" && null !== element.shadowRoot) {
                    element.shadowRoot.innerHTML = '';
                    $('#pay_with_amazon').css('opacity', '1');
                }
                this.findButtonElements('#wfacp_smart_button_amazon_pay #pay_with_amazon');
                if (null !== smart_buttons_wrapper) {
                    smart_buttons_wrapper.removeClass('wfacp_amazon_blocked');
                }
            }, 500);


        }

        hideButtons() {
            let smart_buttons = document.querySelector(this.smart_button_id);
            if (null == smart_buttons) {
                return;
            }
            smart_buttons.style.display = 'none';
            this.button_displayed = false;
        }
    }

    new WFACP_Smart_Button_JS();
})(jQuery);
