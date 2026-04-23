(function ($) {
    class WFACP_Intl {
        constructor() {
            this.timeout1 = null;
            this.timeout2 = null;
            this.billing_country_field = $('#billing_country_field');
            this.intl_inputs = {'billing': null, 'shipping': null};
            this.phone_data = {"billing": {'code': '', 'number': '', 'hidden': ''}, "shipping": {'code': '', 'number': '', 'hidden': ''}};
            this.events();
        }

        events() {
            if ('no' === wfacp_frontend.enable_phone_flag) {
                return;
            }
            let self = this;
            $(document.body).on('wfacp_step_switching', function (e, v) {
                setTimeout(function () {
                    let visible_step = 'single_step';
                    if (v.current_step === 'two_step') {
                        visible_step = 'single_step';
                    } else if (v.current_step === 'third_step') {
                        visible_step = 'two_step';
                    }
                    if ($("." + visible_step + ' #billing_country:visible').length > 0) {
                        $('#billing_country:visible').trigger('change');
                    }
                    if ($("." + visible_step + ' #shipping_country:visible').length > 0) {
                        $('#shipping_country:visible').trigger('change');
                    }
                }, 600);
            });
            $(document.body).on('change', '#billing_country', function (e, v) {
                if (typeof v === "object" && v.hasOwnProperty('wfacp_step_switch')) {
                    return;
                }
                self.setCountry('billing', $(this).val());
            });
            $(document.body).on('change', '#shipping_country', function (e, v) {
                if (typeof v === "object" && v.hasOwnProperty('wfacp_step_switch')) {
                    return;
                }
                self.setCountry('shipping', $(this).val());
                if (self.billing_country_field.length === 0) {
                    self.setCountry('billing', $(this).val());
                }
                if (self.billing_country_field.length >= 1 && !self.billing_country_field.is(":visible")) {
                    self.setCountry('billing', $(this).val());
                }
            });
            self.enablePhoneField('billing');
            self.enablePhoneField('shipping');
            $(document.body).on('wfacp_intl_setup', function () {
                self.enablePhoneField('billing');
                self.enablePhoneField('shipping');
            });
            $(document).ready(function () {
                self.AllowPropagation();
            });
            if ('no' === wfacp_frontend.enable_phone_validation) {
                return;
            }
            this.loadUtils();
            $(document.body).on('focusout', '#billing_phone', function () {
                self.inline_validate($(this));
            });
            $(document.body).on('focusout', '#shipping_phone', function () {
                self.inline_validate($(this), 'shipping');
            });
            $(document.body).on('focusin', '#billing_phone', function () {
                $(`.wfacp_billing_phone_field_error`).remove();
                let parent = $(this).parents('.wfacp-form-control-wrapper');
                parent.removeClass('woocommerce-invalid-required-field woocommerce-invalid-phone-field');
            });
            $(document.body).on('focusin', '#shipping_phone', function () {
                $(`.wfacp_shipping_phone_field_error`).remove();
                let parent = $(this).parents('.wfacp-form-control-wrapper');
                parent.removeClass('woocommerce-invalid-required-field woocommerce-invalid-phone-field');
            });
            if ('yes' == wfacp_frontend.edit_mode) {
                return;
            }
            wfacp_frontend.hooks.addFilter('wfacp_field_validated', this.validate_field.bind(this));
        }

        loadUtils() {
            let script = document.createElement('script');
            script.className = "iti-load-utils";
            script.async = true;
            script.src = wfacp_frontend.intl_util_scripts;
            document.body.appendChild(script);
        }

        AllowPropagation() {
            let wrapper = $('.woocommerce-input-wrapper');
            wrapper.off('click');
            wrapper.on('click', function (event) {
                if ($('.iti__country-list:visible').length > 0) {
                    return;
                }
                event.stopPropagation();
            });
        }

        getCountries(type) {
            let data = [];
            let country = $('#' + type + '_country');
            if (country.length == 0) {
                return data;
            }
            if (country.find('option').length > 0) {
                let options = country.find('option');
                options.each(function () {
                    let vl = $(this).attr('value');
                    if ('' == vl) {
                        return;
                    }
                    data.push(vl);
                });
            } else {
                data.push(country.val());
            }
            return data;
        }

        getInitialCountry(type = 'billing') {
            let country = (type == 'shipping' ? wfacp_frontend.base_country.shipping_country : wfacp_frontend.base_country.billing_country);
            if ("" !== country) {
                return country;
            }
            return wfacp_frontend.base_country.store_country;
        }

        /**
         * return shop Or Geolocate Countries
         * @returns {*[]}
         */
        preferredCountries(type = 'billing') {
            // Billing address not present in form then we use shippping country data
            if ('billing' === type && this.billing_country_field.length === 0) {
                type = 'shipping';
            }
            return this.getCountries(type);
        }

        enablePhoneField(type = 'billing', country = '') {
            let billing_phone = $(`#${type}_phone`);
            if (billing_phone.length === 0) {
                return;
            }
            let billing_input = billing_phone[0];
            let field_tag = $(`#${type}_phone_field`);
            field_tag.addClass('wfacp-intl-phone-flag-field');
            if (field_tag.find('.iti__country-list .iti__country').length === 0) {
                this.destroy(this.intl_inputs[type]);
                this.intl_inputs[type] = this.enableInput(billing_input, type);
            }
            let self = this;
            billing_input.addEventListener("input change", function () {
                self.fill_valid_number(type);
            });
            billing_input.addEventListener("focusout", function () {
                self.fill_valid_number(type);
            });

            self.fill_valid_number(type);
            if ('' !== billing_phone.val()) {
                billing_phone.trigger('change');
            }
        }

        /**
         * Here we enable intl phone flag field
         * @param input
         * @param type
         * @returns {*}
         */
        enableInput(input, type = 'billing') {
            let preferredCountries = this.preferredCountries(type);
            let initial_country = this.getInitialCountry(type);
            if (preferredCountries.length > 0 && preferredCountries.indexOf(initial_country) < 0) {
                initial_country = '';
            }

            let int_obj = {
                initialCountry: initial_country,
                separateDialCode: true,
                formatOnDisplay: false,
                nationalMode: false,
                preferredCountries: [],
                onlyCountries: this.preferredCountries(type),
                utilsScript: wfacp_frontend.intl_util_scripts,
            };


            if (this.preferredCountries(type).length < 5 && window.innerWidth <= 767) {
                int_obj.dropdownContainer = document.querySelector("#" + type + "_phone_field");
                $('body').addClass('wfacp_intl_country_less');
            } else {
                $('body').removeClass('wfacp_intl_country_less');
            }
            let intl = window.intlTelInput(input, int_obj);


            let self = this;
            input.removeEventListener("countrychange", function () {
                self.field_position(intl);
            });
            input.addEventListener("countrychange", function () {
                self.field_position(intl);
            });
            (function (type, self) {
                let timeout = setInterval((type, self) => {
                    if (typeof window.intlTelInputUtils == 'object') {
                        self.fill_valid_number(type);
                        clearInterval(timeout);
                    }
                }, 500, type, self);
            })(type, self);
            return intl;
        }

        /**
         * Set Phone Flag Country when billing or shipping country is changed.
         * @param type
         * @param country
         */
        setCountry(type = 'billing', country = '') {
            if ('' === country || undefined === country) {
                return;
            }
            if (['aq', 'AQ', 'HM', 'UM'].indexOf(country) > -1) {
                //  console.log('No Country Code ', country);
                return;
            }
            if (typeof this.intl_inputs[type] == "object" && null !== this.intl_inputs[type] && null !== country) {
                this.intl_inputs[type].setCountry(country);
                setTimeout((type) => {
                    this.fill_valid_number(type);
                }, 300, type);
            }
        }

        /**
         * Destroy the intl Object
         * @param obj
         */
        destroy(obj) {
            if (typeof obj == "object" && null !== obj) {
                obj.destroy();
            }
        }


        validatePhoneNumber(code, phoneNumber) {
            try {
                // Added Lebanon validation for valid mobile formats like 79077283
                let dialcodes = {
                    "56": /^(?:\+?56)?(?:[ -]?9[ -]?\d{4}[ -]?\d{4})$/,
                    "961": /^(?:\+?961)?[ -]?[378]\d{7}$/
                };
                if (dialcodes.hasOwnProperty(code)) {
                    return dialcodes[code].test(phoneNumber);
                }
            } catch (e) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[wfacp] validatePhoneNumber', e);
                }
            }

            return false;


        }

        /**
         * Strict validation that runs even when intl.isValidNumber() returns true.
         * Catches false positives where the library accepts a mistyped number
         * because it matches a different number type (e.g. fixed-line vs mobile).
         * Returns false to reject, null if no override for that country (trust library).
         * @param code Dial code string
         * @param phoneNumber Full phone number string
         * @returns {boolean|null}
         */
        strictValidatePhoneNumber(code, phoneNumber) {
            try {
                if (String(code) === '359') {
                    const cleaned = phoneNumber.replace(/[\s\-().]/g, '');
                    // Bulgarian mobiles start with 8 or 9 after the country code.
                    // Landlines use area codes 2 (Sofia) or 3x–7x (regional) and are trusted to the library.
                    // Strict check only applies to mobile-prefix numbers to reject incomplete entries
                    // (e.g. 8-digit numbers the library mistakenly accepts as landlines).
                    if (/^(?:\+?359)?0?[89]/.test(cleaned)) {
                        return /^(?:\+?359)?0?[89]\d{8}$/.test(cleaned);
                    }
                    // Non-mobile BG number — defer to the library.
                    return null;
                }
            } catch (e) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[wfacp] strictValidatePhoneNumber', e);
                }
            }

            return null;
        }

        /**
         * intlTelInput often returns isValidNumber() false for valid BG 8/9 mobiles while typing
         * or before full E.164; getNumber() can be empty. Build +359… from the visible input.
         *
         * @param intl
         * @returns {string}
         */
        normalizeBulgariaPhoneForStrict(intl) {
            if (intl.a && intl.a.value) {
                const raw = intl.a.value.replace(/[\s\-().]/g, '');
                if (raw) {
                    if (raw.charAt(0) === '+') {
                        return raw;
                    }
                    if (raw.indexOf('359') === 0) {
                        return '+' + raw;
                    }
                    return '+359' + raw.replace(/^0/, '');
                }
            }
            return (intl.getNumber() || '').replace(/[\s\-().]/g, '');
        }

        /**
         * Override INTL Is Valid Phone number
         * @param intl
         * @returns {*|boolean}
         */
        isValidNumber(intl) {
            const result = intl.isValidNumber();
            const country_data = intl.getSelectedCountryData();
            const dial = country_data && country_data.dialCode != null ? String(country_data.dialCode) : '';

            let phone_number = intl.getNumber() || '';
            if (dial === '359') {
                phone_number = this.normalizeBulgariaPhoneForStrict(intl);
            }

            if (true === result) {
                const strict_result = this.strictValidatePhoneNumber(dial, phone_number);
                if (strict_result !== null) {
                    return strict_result;
                }
                return result;
            }

            // validatePhoneNumber only knows 56 / 961 — never 359, so this always failed for BG before.
            if (dial === '359') {
                return this.strictValidatePhoneNumber(dial, phone_number) === true;
            }

            return this.validatePhoneNumber(dial, phone_number);
        }

        /**
         * Fill Valid number to hidden field this field use to replace original number field data.
         * @param type
         */
        fill_valid_number(type = 'billing') {
            let intl = this.intl_inputs[type];
            if (null == intl) {
                return;
            }
            let is_valid = this.isValidNumber(intl);
            this.field_position(intl);
            let hidden_phone_field = $('#wfacp_input_phone_field');
            if (false === is_valid || null == is_valid) {
                this.phone_data[type].number = '';
                this.phone_data[type].code = '';
                hidden_phone_field.val('{}');
            } else {
                let selected_data = intl.getSelectedCountryData();
                this.phone_data[type].number = intl.getNumber().replace('+' + selected_data.dialCode, '');
                this.phone_data[type].code = selected_data.dialCode;
            }
            let el = $(`#${type}_phone`);
            if (el.length > 0) {
                this.phone_data[type].hidden = el.is(':visible') ? 'no' : 'yes';
            }
            hidden_phone_field.val(JSON.stringify(this.phone_data));
        }

        /**
         * this validation function runs when user focus out the input field.
         * @param $this  Input element like billing_phone
         * @param type  Billing or Shipping
         */
        inline_validate($this, type = 'billing', timeout = 300) {
            clearTimeout(this.timeout2);
            this.timeout2 = setTimeout(($this) => {
                let intl = this.intl_inputs[type];
                let is_valid = this.isValidNumber(intl);
                let parent = $this.parents('.wfacp-form-control-wrapper');
                if ('' == $this.val()) {
                    return;
                }
                $(`.wfacp_${type}_phone_field_error`).remove();
                let error_msg = wfacp_frontend.settings.phone_inline_number_number;
                if (false === is_valid || null == is_valid) {
                    $(`#wfacp_${type}_phone`).val('');
                    $(`<span class='wfacp_${type}_phone_field_error wfacp_inline_field_error'>${error_msg}</span>`).insertAfter($this);
                    parent.addClass('woocommerce-invalid-required-field woocommerce-invalid-phone-field wfacp-inline-error-action');
                } else {
                    $(`#wfacp_${type}_phone`).val(intl.getNumber());
                    parent.removeClass('woocommerce-invalid-required-field woocommerce-invalid-phone-field');
                }
            }, timeout, $this);
        }

        /**
         * this validation run when next button click
         * @param validated
         * @param $this
         * @returns {*}
         */
        validate_field(validated, $this) {
            if ($this.length > 0 && '' !== $this.val() && true === validated) {
                let id = $this.attr('id');
                if (id === 'shipping_phone' || id === 'billing_phone') {
                    let type = (id == 'shipping_phone' ? 'shipping' : 'billing');
                    let intl = this.intl_inputs[type];
                    validated = this.isValidNumber(intl);
                    this.inline_validate($this, type, 0);
                    if (false === validated) {
                        $("#" + id + '_field').addClass('woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-phone-field');
                    }
                }
            }
            return validated;
        }

        mobileValidation() {
            return $('.woocommerce-invalid-phone-field').length > 0;
        }

        countrychange() {
        }
        field_position(intl) {
            let selected_flag = $(intl.selectedFlag);
            let flag_w = 0;
            flag_w = selected_flag.parent('.iti__flag-container').innerWidth();
            if (typeof flag_w !== "undefined" && '' != flag_w) {
                flag_w = parseInt(flag_w) + 12;
                if ($('.wfacp-top').length == 0) {
                    if (true === wfacp_frontend.is_rtl || "1" === wfacp_frontend.is_rtl) {
                        $(intl.a).parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('right', flag_w + 8);
                    } else {
                        $(intl.a).parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('left', flag_w + 8);
                    }
                }
                if (true === wfacp_frontend.is_rtl || "1" === wfacp_frontend.is_rtl) {
                    $(intl.a).css('cssText', 'padding-right: ' + flag_w + 'px !important');
                } else {
                    $(intl.a).css('cssText', 'padding-left: ' + flag_w + 'px !important');
                }
            }
        }
    }

    if (typeof window.intlTelInput == "function") {
        new WFACP_Intl();
    }
})(jQuery);
