(function ($) {
    // snapshot taken via inline script right after our bundle loads; other plugins may overwrite window.intlTelInput with an incompatible version
    let itiLib = window.wfacpIntlTelInput || window.intlTelInput;
    // WooCommerce sells to territories the phone library has no dial codes for (aq, bv, tf, …);
    // passing them to onlyCountries triggers console warnings, so filter against the lib's list
    let iti_valid_iso2 = null;
    function wfacpGetCountry(intl) {
        return (typeof intl.getSelectedCountry === 'function' ? intl.getSelectedCountry() : intl.getSelectedCountryData()) || null;
    }

    function wfacpValidIso2(code) {
        if (null === iti_valid_iso2 && itiLib && typeof itiLib.getAllCountries === 'function') {
            iti_valid_iso2 = {};
            itiLib.getAllCountries().forEach(function (c) {
                iti_valid_iso2[c.iso2] = true;
            });
        }

        return null === iti_valid_iso2 ? true : !!iti_valid_iso2[code];
    }
    // v17 country names (with native scripts) — v29 data ships English-only names
    const WFACP_INTL_COUNTRY_NAMES = {"af":"Afghanistan (‫افغانستان‬‎)","al":"Albania (Shqipëri)","dz":"Algeria (‫الجزائر‬‎)","as":"American Samoa","ad":"Andorra","ao":"Angola","ai":"Anguilla","ag":"Antigua and Barbuda","ar":"Argentina","am":"Armenia (Հայաստան)","aw":"Aruba","ac":"Ascension Island","au":"Australia","at":"Austria (Österreich)","az":"Azerbaijan (Azərbaycan)","bs":"Bahamas","bh":"Bahrain (‫البحرين‬‎)","bd":"Bangladesh (বাংলাদেশ)","bb":"Barbados","by":"Belarus (Беларусь)","be":"Belgium (België)","bz":"Belize","bj":"Benin (Bénin)","bm":"Bermuda","bt":"Bhutan (འབྲུག)","bo":"Bolivia","ba":"Bosnia and Herzegovina (Босна и Херцеговина)","bw":"Botswana","br":"Brazil (Brasil)","io":"British Indian Ocean Territory","vg":"British Virgin Islands","bn":"Brunei","bg":"Bulgaria (България)","bf":"Burkina Faso","bi":"Burundi (Uburundi)","kh":"Cambodia (កម្ពុជា)","cm":"Cameroon (Cameroun)","ca":"Canada","cv":"Cape Verde (Kabu Verdi)","bq":"Caribbean Netherlands","ky":"Cayman Islands","cf":"Central African Republic (République centrafricaine)","td":"Chad (Tchad)","cl":"Chile","cn":"China (中国)","cx":"Christmas Island","cc":"Cocos (Keeling) Islands","co":"Colombia","km":"Comoros (‫جزر القمر‬‎)","cd":"Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)","cg":"Congo (Republic) (Congo-Brazzaville)","ck":"Cook Islands","cr":"Costa Rica","ci":"Côte d’Ivoire","hr":"Croatia (Hrvatska)","cu":"Cuba","cw":"Curaçao","cy":"Cyprus (Κύπρος)","cz":"Czech Republic (Česká republika)","dk":"Denmark (Danmark)","dj":"Djibouti","dm":"Dominica","do":"Dominican Republic (República Dominicana)","ec":"Ecuador","eg":"Egypt (‫مصر‬‎)","sv":"El Salvador","gq":"Equatorial Guinea (Guinea Ecuatorial)","er":"Eritrea","ee":"Estonia (Eesti)","sz":"Eswatini","et":"Ethiopia","fk":"Falkland Islands (Islas Malvinas)","fo":"Faroe Islands (Føroyar)","fj":"Fiji","fi":"Finland (Suomi)","fr":"France","gf":"French Guiana (Guyane française)","pf":"French Polynesia (Polynésie française)","ga":"Gabon","gm":"Gambia","ge":"Georgia (საქართველო)","de":"Germany (Deutschland)","gh":"Ghana (Gaana)","gi":"Gibraltar","gr":"Greece (Ελλάδα)","gl":"Greenland (Kalaallit Nunaat)","gd":"Grenada","gp":"Guadeloupe","gu":"Guam","gt":"Guatemala","gg":"Guernsey","gn":"Guinea (Guinée)","gw":"Guinea-Bissau (Guiné Bissau)","gy":"Guyana","ht":"Haiti","hn":"Honduras","hk":"Hong Kong (香港)","hu":"Hungary (Magyarország)","is":"Iceland (Ísland)","in":"India (भारत)","id":"Indonesia","ir":"Iran (‫ایران‬‎)","iq":"Iraq (‫العراق‬‎)","ie":"Ireland","im":"Isle of Man","il":"Israel (‫ישראל‬‎)","it":"Italy (Italia)","jm":"Jamaica","jp":"Japan (日本)","je":"Jersey","jo":"Jordan (‫الأردن‬‎)","kz":"Kazakhstan (Казахстан)","ke":"Kenya","ki":"Kiribati","xk":"Kosovo","kw":"Kuwait (‫الكويت‬‎)","kg":"Kyrgyzstan (Кыргызстан)","la":"Laos (ລາວ)","lv":"Latvia (Latvija)","lb":"Lebanon (‫لبنان‬‎)","ls":"Lesotho","lr":"Liberia","ly":"Libya (‫ليبيا‬‎)","li":"Liechtenstein","lt":"Lithuania (Lietuva)","lu":"Luxembourg","mo":"Macau (澳門)","mk":"North Macedonia (Македонија)","mg":"Madagascar (Madagasikara)","mw":"Malawi","my":"Malaysia","mv":"Maldives","ml":"Mali","mt":"Malta","mh":"Marshall Islands","mq":"Martinique","mr":"Mauritania (‫موريتانيا‬‎)","mu":"Mauritius (Moris)","yt":"Mayotte","mx":"Mexico (México)","fm":"Micronesia","md":"Moldova (Republica Moldova)","mc":"Monaco","mn":"Mongolia (Монгол)","me":"Montenegro (Crna Gora)","ms":"Montserrat","ma":"Morocco (‫المغرب‬‎)","mz":"Mozambique (Moçambique)","mm":"Myanmar (Burma) (မြန်မာ)","na":"Namibia (Namibië)","nr":"Nauru","np":"Nepal (नेपाल)","nl":"Netherlands (Nederland)","nc":"New Caledonia (Nouvelle-Calédonie)","nz":"New Zealand","ni":"Nicaragua","ne":"Niger (Nijar)","ng":"Nigeria","nu":"Niue","nf":"Norfolk Island","kp":"North Korea (조선 민주주의 인민 공화국)","mp":"Northern Mariana Islands","no":"Norway (Norge)","om":"Oman (‫عُمان‬‎)","pk":"Pakistan (‫پاکستان‬‎)","pw":"Palau","ps":"Palestine (‫فلسطين‬‎)","pa":"Panama (Panamá)","pg":"Papua New Guinea","py":"Paraguay","pe":"Peru (Perú)","ph":"Philippines","pl":"Poland (Polska)","pt":"Portugal","pr":"Puerto Rico","qa":"Qatar (‫قطر‬‎)","re":"Réunion (La Réunion)","ro":"Romania (România)","ru":"Russia (Россия)","rw":"Rwanda","bl":"Saint Barthélemy","sh":"Saint Helena","kn":"Saint Kitts and Nevis","lc":"Saint Lucia","mf":"Saint Martin (Saint-Martin (partie française))","pm":"Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)","vc":"Saint Vincent and the Grenadines","ws":"Samoa","sm":"San Marino","st":"São Tomé and Príncipe (São Tomé e Príncipe)","sa":"Saudi Arabia (‫المملكة العربية السعودية‬‎)","sn":"Senegal (Sénégal)","rs":"Serbia (Србија)","sc":"Seychelles","sl":"Sierra Leone","sg":"Singapore","sx":"Sint Maarten","sk":"Slovakia (Slovensko)","si":"Slovenia (Slovenija)","sb":"Solomon Islands","so":"Somalia (Soomaaliya)","za":"South Africa","kr":"South Korea (대한민국)","ss":"South Sudan (‫جنوب السودان‬‎)","es":"Spain (España)","lk":"Sri Lanka (ශ්‍රී ලංකාව)","sd":"Sudan (‫السودان‬‎)","sr":"Suriname","sj":"Svalbard and Jan Mayen","se":"Sweden (Sverige)","ch":"Switzerland (Schweiz)","sy":"Syria (‫سوريا‬‎)","tw":"Taiwan (台灣)","tj":"Tajikistan","tz":"Tanzania","th":"Thailand (ไทย)","tl":"Timor-Leste","tg":"Togo","tk":"Tokelau","to":"Tonga","tt":"Trinidad and Tobago","tn":"Tunisia (‫تونس‬‎)","tr":"Turkey (Türkiye)","tm":"Turkmenistan","tc":"Turks and Caicos Islands","tv":"Tuvalu","vi":"U.S. Virgin Islands","ug":"Uganda","ua":"Ukraine (Україна)","ae":"United Arab Emirates (‫الإمارات العربية المتحدة‬‎)","gb":"United Kingdom","us":"United States","uy":"Uruguay","uz":"Uzbekistan (Oʻzbekiston)","vu":"Vanuatu","va":"Vatican City (Città del Vaticano)","ve":"Venezuela","vn":"Vietnam (Việt Nam)","wf":"Wallis and Futuna (Wallis-et-Futuna)","eh":"Western Sahara (‫الصحراء الغربية‬‎)","ye":"Yemen (‫اليمن‬‎)","zm":"Zambia","zw":"Zimbabwe","ax":"Åland Islands"};
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
            // v29 fullscreen popup (mobile) keeps the country list outside the field wrapper,
            // so detect an existing instance on this exact input instead of sniffing markup
            if (!itiLib.getInstance || !itiLib.getInstance(billing_input)) {
                this.destroy(this.intl_inputs[type]);
                this.intl_inputs[type] = this.enableInput(billing_input, type);
            }
            let self = this;
            // "input" also fires for autofill/password managers and paste, which produce no key
            // events — the template's keyup-based floating-label logic misses those, so keep the
            // wrapper's anim state in sync here (v17 never hit this: it blocked autofill entirely)
            billing_input.addEventListener("input", function () {
                let parent = $(this).parents('.wfacp-form-control-wrapper');
                if ('' === this.value) {
                    if ($('.wfacp-modern-label').length > 0) {
                        parent.removeClass('wfacp-anim-wrap');
                    }
                } else {
                    parent.addClass('wfacp-anim-wrap');
                }
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
                initialCountry: initial_country.toLowerCase(),
                separateDialCode: true,
                numberDisplayFormat: 'E164',
                formatAsYouType: false,
                strictMode: false,
                // v17 sized the dropdown to its content, not the input width
                matchDropdownWidth: false,
                countryNameOverrides: WFACP_INTL_COUNTRY_NAMES,
                // v17 validated against every number type; keep that behaviour (v26+ defaults to mobile + fixed-line only)
                allowedNumberTypes: ['MOBILE', 'FIXED_LINE', 'FIXED_LINE_OR_MOBILE', 'TOLL_FREE', 'PREMIUM_RATE', 'SHARED_COST', 'VOIP', 'PERSONAL_NUMBER', 'PAGER', 'UAN', 'VOICEMAIL'],
                onlyCountries: this.preferredCountries(type).map(function (c) {
                    return c.toLowerCase();
                }).filter(wfacpValidIso2),
            };

            if ('no' !== wfacp_frontend.enable_phone_validation) {
                int_obj.loadUtils = function () {
                    return new Function( 'u', 'return import(u)' )( wfacp_frontend.intl_util_scripts );
                };
            }



            // the only v29 UI string a sighted user can see in our build (search box is hidden,
            // but typing while open still filters — gibberish shows the no-results card)
            let ui = wfacp_frontend.intl_i18n || null;
            if (ui && ui.searchEmptyState) {
                int_obj.uiTranslations = {
                    searchEmptyState: ui.searchEmptyState
                };
            }

            if (this.preferredCountries(type).length < 5 && window.innerWidth <= 767) {
                int_obj.dropdownParent = document.querySelector("#" + type + "_phone_field");
                $('body').addClass('wfacp_intl_country_less');
            } else {
                $('body').removeClass('wfacp_intl_country_less');
            }
            let intl = itiLib(input, int_obj);
            // minified v29 build mangles instance props (no .telInput) — keep our own reference
            intl.wfacpInput = input;


            let self = this;
            input.removeEventListener("countrychange", function () {
                self.field_position(intl);
            });
            input.addEventListener("countrychange", function () {
                self.field_position(intl);
            });
            self.fill_valid_number(type);
            // Poll for utils and rewrite the payload once it lands, as before the v29 migration.
            // A promise chained to the import settles once and only for that one attempt: a
            // rejected import leaves the payload as it was, and nothing retries. The poll picks
            // utils up however it arrives, and has no rejection to swallow.
            (function (type, self) {
                let timer = setInterval(function () {
                    if (itiLib.utils) {
                        self.fill_valid_number(type);
                        clearInterval(timer);
                    }
                }, 500);
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
                try {
                    let inst = this.intl_inputs[type];
                    let iso2 = String(country).toLowerCase();
                    if (typeof inst.setSelectedCountry === 'function') {
                        inst.setSelectedCountry(iso2);
                    } else {
                        inst.setCountry(iso2);
                    }
                } catch (e) {
                    return;
                }
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
                // Added Singapore validation for valid 8-digit mobile formats like 89588699 / 91234567
                let dialcodes = {
                    "56": /^(?:\+?56)?(?:[ -]?9[ -]?\d{4}[ -]?\d{4})$/,
                    "961": /^(?:\+?961)?[ -]?[378]\d{7}$/,
                    "65": /^(?:\+?65)?[ -]?[89]\d{7}$/
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
            if (intl.wfacpInput && intl.wfacpInput.value) {
                const raw = intl.wfacpInput.value.replace(/[\s\-().]/g, '');
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
            try {
                return this.runValidation(intl);
            } catch (e) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[wfacp] phone validation failed open', e);
                }

                return true;
            }
        }

        runValidation(intl) {
            // v29 throws for every utils-backed call (v17 returned null/'' silently);
            // until the lazy utils import lands, report "unvalidated" instead
            if (!itiLib.utils) {
                return null;
            }
            const result = typeof intl.isValidNumberPrecise === 'function' ? intl.isValidNumberPrecise() : intl.isValidNumber();
            const country_data = wfacpGetCountry(intl);
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
            if (null == is_valid) {
                // No verdict: utils.js has not loaded, so nothing checked this number. The server
                // reads an empty number as a definite "invalid", so keep what the customer typed —
                // a check that never ran must not surface as an error at place order.
                let selected_data = wfacpGetCountry(intl) || {};
                let national = ($(`#${type}_phone`).val() || '').replace(/[\s\-().]/g, '');
                if (selected_data.dialCode && '' !== national) {
                    this.phone_data[type].code = String(selected_data.dialCode);
                    this.phone_data[type].number = national.replace('+' + selected_data.dialCode, '').replace(/^0+/, '');
                } else {
                    // no country to split on — hand the digits over whole rather than blank them
                    this.phone_data[type].code = '';
                    this.phone_data[type].number = national;
                }
            } else if (false === is_valid) {
                this.phone_data[type].number = '';
                this.phone_data[type].code = '';
                hidden_phone_field.val('{}');
            } else {
                let selected_data = wfacpGetCountry(intl) || {dialCode: ''};
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
                if (null == is_valid) {
                    // utils not loaded yet — fail open, leave the field untouched
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
                    let is_valid = this.isValidNumber(intl);
                    if (null == is_valid) {
                        // utils not loaded yet — fail open, keep the caller's verdict
                        return validated;
                    }
                    validated = is_valid;
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
            let tel_input = $(intl.wfacpInput);
            let flag_w = tel_input.closest('.iti').find('.iti__country-container').innerWidth();
            if (typeof flag_w !== "undefined" && '' != flag_w) {
                flag_w = parseInt(flag_w) + 12;
                if ($('.wfacp-top').length == 0) {
                    if (true === wfacp_frontend.is_rtl || "1" === wfacp_frontend.is_rtl) {
                        tel_input.parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('right', flag_w + 8);
                    } else {
                        tel_input.parents('.wfacp-form-control-wrapper').find('.wfacp-form-control-label').css('left', flag_w + 8);
                    }
                }
                // the library rewrites style.paddingLeft on its own updates (dropping any inline
                // !important), and templates use !important paddings too — a CSS variable consumed
                // by an !important rule in intlTelInput.css wins over both
                let iti_wrap = tel_input.closest('.iti');
                if (iti_wrap.length > 0) {
                    iti_wrap[0].style.setProperty('--wfacp-intl-pad', flag_w + 'px');
                }
            }
        }
    }

    if (typeof itiLib == "function") {
        new WFACP_Intl();
    }
})(jQuery);
