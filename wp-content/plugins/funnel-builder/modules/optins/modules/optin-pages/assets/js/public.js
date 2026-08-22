/*global wffnfunnelVars */
/*global wfffOptinVars */
/*global fbq */
/*global pintrk */
(function ($) {
    var wffnOptin = {
        init: function () {

            let self = this;
            $(document).ready(function () {
                self.renderForm();
                self.handleSubmit();
                self.renderPBPopUps();
                self.attachCloseBtn();
                self.initPhoneFlag();
            });
            $(document).on('wffn_reload_popups', function () {
                self.renderPBPopUps();
            });
            $(document).on('wffn_reload_phone_field', function () {
                self.initPhoneFlag();
            });

        },
        getItiLib: function () {
            // snapshot taken right after our bundle loads; other plugins may overwrite window.intlTelInput with an incompatible version
            return window.wfopIntlTelInput || window.intlTelInput;
        },
        initPhoneFlag: function () {
            // v17 country names (with native scripts) — v29 data ships English-only names
            var WFOP_INTL_COUNTRY_NAMES = {"af":"Afghanistan (‫افغانستان‬‎)","al":"Albania (Shqipëri)","dz":"Algeria (‫الجزائر‬‎)","as":"American Samoa","ad":"Andorra","ao":"Angola","ai":"Anguilla","ag":"Antigua and Barbuda","ar":"Argentina","am":"Armenia (Հայաստան)","aw":"Aruba","ac":"Ascension Island","au":"Australia","at":"Austria (Österreich)","az":"Azerbaijan (Azərbaycan)","bs":"Bahamas","bh":"Bahrain (‫البحرين‬‎)","bd":"Bangladesh (বাংলাদেশ)","bb":"Barbados","by":"Belarus (Беларусь)","be":"Belgium (België)","bz":"Belize","bj":"Benin (Bénin)","bm":"Bermuda","bt":"Bhutan (འབྲུག)","bo":"Bolivia","ba":"Bosnia and Herzegovina (Босна и Херцеговина)","bw":"Botswana","br":"Brazil (Brasil)","io":"British Indian Ocean Territory","vg":"British Virgin Islands","bn":"Brunei","bg":"Bulgaria (България)","bf":"Burkina Faso","bi":"Burundi (Uburundi)","kh":"Cambodia (កម្ពុជា)","cm":"Cameroon (Cameroun)","ca":"Canada","cv":"Cape Verde (Kabu Verdi)","bq":"Caribbean Netherlands","ky":"Cayman Islands","cf":"Central African Republic (République centrafricaine)","td":"Chad (Tchad)","cl":"Chile","cn":"China (中国)","cx":"Christmas Island","cc":"Cocos (Keeling) Islands","co":"Colombia","km":"Comoros (‫جزر القمر‬‎)","cd":"Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)","cg":"Congo (Republic) (Congo-Brazzaville)","ck":"Cook Islands","cr":"Costa Rica","ci":"Côte d’Ivoire","hr":"Croatia (Hrvatska)","cu":"Cuba","cw":"Curaçao","cy":"Cyprus (Κύπρος)","cz":"Czech Republic (Česká republika)","dk":"Denmark (Danmark)","dj":"Djibouti","dm":"Dominica","do":"Dominican Republic (República Dominicana)","ec":"Ecuador","eg":"Egypt (‫مصر‬‎)","sv":"El Salvador","gq":"Equatorial Guinea (Guinea Ecuatorial)","er":"Eritrea","ee":"Estonia (Eesti)","sz":"Eswatini","et":"Ethiopia","fk":"Falkland Islands (Islas Malvinas)","fo":"Faroe Islands (Føroyar)","fj":"Fiji","fi":"Finland (Suomi)","fr":"France","gf":"French Guiana (Guyane française)","pf":"French Polynesia (Polynésie française)","ga":"Gabon","gm":"Gambia","ge":"Georgia (საქართველო)","de":"Germany (Deutschland)","gh":"Ghana (Gaana)","gi":"Gibraltar","gr":"Greece (Ελλάδα)","gl":"Greenland (Kalaallit Nunaat)","gd":"Grenada","gp":"Guadeloupe","gu":"Guam","gt":"Guatemala","gg":"Guernsey","gn":"Guinea (Guinée)","gw":"Guinea-Bissau (Guiné Bissau)","gy":"Guyana","ht":"Haiti","hn":"Honduras","hk":"Hong Kong (香港)","hu":"Hungary (Magyarország)","is":"Iceland (Ísland)","in":"India (भारत)","id":"Indonesia","ir":"Iran (‫ایران‬‎)","iq":"Iraq (‫العراق‬‎)","ie":"Ireland","im":"Isle of Man","il":"Israel (‫ישראל‬‎)","it":"Italy (Italia)","jm":"Jamaica","jp":"Japan (日本)","je":"Jersey","jo":"Jordan (‫الأردن‬‎)","kz":"Kazakhstan (Казахстан)","ke":"Kenya","ki":"Kiribati","xk":"Kosovo","kw":"Kuwait (‫الكويت‬‎)","kg":"Kyrgyzstan (Кыргызстан)","la":"Laos (ລາວ)","lv":"Latvia (Latvija)","lb":"Lebanon (‫لبنان‬‎)","ls":"Lesotho","lr":"Liberia","ly":"Libya (‫ليبيا‬‎)","li":"Liechtenstein","lt":"Lithuania (Lietuva)","lu":"Luxembourg","mo":"Macau (澳門)","mk":"North Macedonia (Македонија)","mg":"Madagascar (Madagasikara)","mw":"Malawi","my":"Malaysia","mv":"Maldives","ml":"Mali","mt":"Malta","mh":"Marshall Islands","mq":"Martinique","mr":"Mauritania (‫موريتانيا‬‎)","mu":"Mauritius (Moris)","yt":"Mayotte","mx":"Mexico (México)","fm":"Micronesia","md":"Moldova (Republica Moldova)","mc":"Monaco","mn":"Mongolia (Монгол)","me":"Montenegro (Crna Gora)","ms":"Montserrat","ma":"Morocco (‫المغرب‬‎)","mz":"Mozambique (Moçambique)","mm":"Myanmar (Burma) (မြန်မာ)","na":"Namibia (Namibië)","nr":"Nauru","np":"Nepal (नेपाल)","nl":"Netherlands (Nederland)","nc":"New Caledonia (Nouvelle-Calédonie)","nz":"New Zealand","ni":"Nicaragua","ne":"Niger (Nijar)","ng":"Nigeria","nu":"Niue","nf":"Norfolk Island","kp":"North Korea (조선 민주주의 인민 공화국)","mp":"Northern Mariana Islands","no":"Norway (Norge)","om":"Oman (‫عُمان‬‎)","pk":"Pakistan (‫پاکستان‬‎)","pw":"Palau","ps":"Palestine (‫فلسطين‬‎)","pa":"Panama (Panamá)","pg":"Papua New Guinea","py":"Paraguay","pe":"Peru (Perú)","ph":"Philippines","pl":"Poland (Polska)","pt":"Portugal","pr":"Puerto Rico","qa":"Qatar (‫قطر‬‎)","re":"Réunion (La Réunion)","ro":"Romania (România)","ru":"Russia (Россия)","rw":"Rwanda","bl":"Saint Barthélemy","sh":"Saint Helena","kn":"Saint Kitts and Nevis","lc":"Saint Lucia","mf":"Saint Martin (Saint-Martin (partie française))","pm":"Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)","vc":"Saint Vincent and the Grenadines","ws":"Samoa","sm":"San Marino","st":"São Tomé and Príncipe (São Tomé e Príncipe)","sa":"Saudi Arabia (‫المملكة العربية السعودية‬‎)","sn":"Senegal (Sénégal)","rs":"Serbia (Србија)","sc":"Seychelles","sl":"Sierra Leone","sg":"Singapore","sx":"Sint Maarten","sk":"Slovakia (Slovensko)","si":"Slovenia (Slovenija)","sb":"Solomon Islands","so":"Somalia (Soomaaliya)","za":"South Africa","kr":"South Korea (대한민국)","ss":"South Sudan (‫جنوب السودان‬‎)","es":"Spain (España)","lk":"Sri Lanka (ශ්‍රී ලංකාව)","sd":"Sudan (‫السودان‬‎)","sr":"Suriname","sj":"Svalbard and Jan Mayen","se":"Sweden (Sverige)","ch":"Switzerland (Schweiz)","sy":"Syria (‫سوريا‬‎)","tw":"Taiwan (台灣)","tj":"Tajikistan","tz":"Tanzania","th":"Thailand (ไทย)","tl":"Timor-Leste","tg":"Togo","tk":"Tokelau","to":"Tonga","tt":"Trinidad and Tobago","tn":"Tunisia (‫تونس‬‎)","tr":"Turkey (Türkiye)","tm":"Turkmenistan","tc":"Turks and Caicos Islands","tv":"Tuvalu","vi":"U.S. Virgin Islands","ug":"Uganda","ua":"Ukraine (Україна)","ae":"United Arab Emirates (‫الإمارات العربية المتحدة‬‎)","gb":"United Kingdom","us":"United States","uy":"Uruguay","uz":"Uzbekistan (Oʻzbekiston)","vu":"Vanuatu","va":"Vatican City (Città del Vaticano)","ve":"Venezuela","vn":"Vietnam (Việt Nam)","wf":"Wallis and Futuna (Wallis-et-Futuna)","eh":"Western Sahara (‫الصحراء الغربية‬‎)","ye":"Yemen (‫اليمن‬‎)","zm":"Zambia","zw":"Zimbabwe","ax":"Åland Islands"};
            let intlconfig = {
                separateDialCode: true,
                countryNameOverrides: WFOP_INTL_COUNTRY_NAMES,
                // v17 pinned us/gb on top by default (preferredCountries); v29 equivalent
                countryOrder: ["us", "gb"],
            };

            // v29 split build: the core bundle ships without utils (validation/formatting),
            // so lazy-load them from the URL the premium plugin exposes. Absent on v17.
            if (window.wfopIntlUtilsSrc) {
                intlconfig.loadUtils = function () {
                    // dynamic import via Function keeps ES5 transpilers/minifiers from choking on the import keyword
                    return new Function('u', 'return import(u)')(window.wfopIntlUtilsSrc);
                };
            }

            /**
             * Country auto-detection is supplied by the premium plugin, which
             * defines this before the script runs. Without it we stay on the
             * country the server chose and contact nobody.
             */
            if (typeof window.wffnOptinGeoLookup === 'function') {
                intlconfig.initialCountryLookup = function () {
                    return new Promise(function (resolve) {
                        window.wffnOptinGeoLookup(resolve);
                    });
                };
            }

            // the only v29 UI string a sighted user can see in our build (search box is hidden,
            // but typing while open still filters — gibberish shows the no-results card)
            let ui = window.wfffOptinVars.op_intl_i18n || null;
            if (ui && ui.searchEmptyState) {
                intlconfig.uiTranslations = {
                    searchEmptyState: ui.searchEmptyState
                };
            }

            var flag_country = window.wfffOptinVars.op_flag_country;
            if (flag_country && 'auto' !== flag_country) {
                intlconfig.initialCountry = String(flag_country).toLowerCase();
            }

            if (typeof window.wfffOptinVars.onlyCountries !== "undefined" && window.wfffOptinVars.onlyCountries.length > 0) {
                var validIso2 = null;
                var itiLibForFilter = this.getItiLib();
                if (itiLibForFilter && typeof itiLibForFilter.getAllCountries === 'function') {
                    validIso2 = {};
                    itiLibForFilter.getAllCountries().forEach(function (c) {
                        validIso2[c.iso2] = true;
                    });
                }
                intlconfig.onlyCountries = window.wfffOptinVars.onlyCountries.map(function (c) {
                    return String(c).toLowerCase();
                }).filter(function (c) {
                    return null === validIso2 || !!validIso2[c];
                });
            }
            var itiLib = this.getItiLib();
            var elems = document.querySelectorAll(".phone_flag_code input[type='tel']");
            for (var i in elems) {
                if (typeof elems[i] === 'object' && undefined !== itiLib) {
                    if (itiLib.getInstance && itiLib.getInstance(elems[i])) {
                        continue;
                    }
                    itiLib(elems[i], intlconfig);
                }

            }
        },
        attachCloseBtn: function () {
            jQuery(document).on('click', '.bwf_pp_close', function (e) {
                e.preventDefault();
                jQuery('.bwf_pp_overlay').removeClass('show_popup_form');
                jQuery('body').css('overflow', '');
            });
        },
        renderPBPopUps: function () {
            jQuery('.wfop_pb_widget_wrap').each(function () {
                let elem = this;
                jQuery(this).find(".bwf-custom-button a").click(function (e) {
                    e.preventDefault();
                    jQuery(elem).find('.bwf_pp_overlay').addClass('show_popup_form');
                    jQuery('body').css('overflow', 'hidden');
                });

            });
        },
        renderForm: function () {

            if (jQuery('.bwf_pp_overlay').length > 0) {
                jQuery('a[href*="wfop-popup=yes"]').on('click', function (e) {
                    e.preventDefault();
                    jQuery('.bwf_pp_overlay').addClass('show_popup_form');
                });

            }
        },

        DoValidation: function (formElem) {
            var valid = true;

            jQuery(formElem).find('.wfop_required').each(function () {

                var self = jQuery(this);
                var message = null;
                var error_msg = window.wfffOptinVars.op_valid_text;
                var error_email = window.wfffOptinVars.op_valid_email;
                if (jQuery.trim(self.val()) === '') {
                    message = error_msg;
                } else if ('checkbox' === self.attr('type')) {
                    if (!self.prop('checked')) {
                        message = error_msg;
                    }
                } else if ('radio' === self.attr('type')) {
                    var radioName = self.attr("name");
                    if (jQuery(formElem).find("input:radio[name=" + radioName + "]:checked").length === 0) {
                        message = error_msg;
                    }
                }

                var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
                if (jQuery.trim(self.val()) !== '' && 'wfop_optin_email' === self.attr('name')) {
                    if (!jQuery.trim(self.val()).match(pattern)) {
                        message = error_email;
                    }
                }
                if (message !== null) {
                    self.parents('.bwfac_form_sec').addClass('bwfac_error');
                    if (self.parents('.bwfac_form_sec').find('.error').length === 0) {
                        self.parents('.bwfac_form_sec').append('<span class="error">' + message + '</span>');
                    }
                    valid = false;
                }
            });

            jQuery(formElem).find('.wfop_phone_validation .wffn-optin-input').each(function () {
                var inst = jQuery(this);
                var error_message = null;
                var error_phone = window.wfffOptinVars.op_valid_phone;
                if (jQuery.trim(inst.val()) !== '' && 'wfop_optin_phone' === inst.attr('name')) {
                    try {
                        var itiLib = wffnOptin.getItiLib();
                        var itis = itiLib && itiLib.getInstance ? itiLib.getInstance(inst.get(0)) : null;
                        var is_valid = itis ? (typeof itis.isValidNumberPrecise === 'function' ? itis.isValidNumberPrecise() : itis.isValidNumber()) : true;
                        if (false === is_valid) {
                            if (Array.isArray(error_phone)) {
                                var errorCode = itis.getValidationError();
                                // v28+ returns string names instead of the old integer enum
                                var errorMap = {'INVALID_COUNTRY_CODE': 1, 'TOO_SHORT': 2, 'TOO_LONG': 3, 'IS_POSSIBLE_LOCAL_ONLY': 4, 'INVALID_LENGTH': 5};
                                var errorIdx = (typeof errorCode === 'number') ? errorCode : errorMap[errorCode];
                                error_message = (undefined !== errorIdx && error_phone[errorIdx]) ? error_phone[errorIdx] : error_phone[0];
                            } else {
                                error_message = error_phone;
                            }
                        }
                    } catch (e) {
                        error_message = null;
                    }
                }
                if (error_message !== null) {
                    inst.parents('.bwfac_form_sec').addClass('bwfac_error');
                    if (inst.parents('.bwfac_form_sec').find('.error').length === 0) {
                        inst.parents('.bwfac_form_sec').append('<span class="error">' + error_message + '</span>');
                    }
                    valid = false;
                }
            });



            window.wffnFormValid = valid;


            jQuery(document).trigger('wffn_custom_optin_form_validate', [formElem, valid]);
            return window.wffnFormValid;
        },
        setUpClick: function (FormElem) {
            let inst = this;
            jQuery(FormElem).find('#wffn_custom_optin_submit').on('click', function (e) {
                var valid = true;

                jQuery(this).removeAttr('disabled');
                var $this = jQuery(this);

                var bwf_form = FormElem;
                jQuery(bwf_form).find('.bwfac_form_sec').removeClass('bwfac_error');
                jQuery(bwf_form).find('.bwfac_form_sec .error').remove();
                let is_admin = jQuery(bwf_form).find('input[name=optin_is_admin]').val();
                let is_ajax = jQuery(bwf_form).find('input[name=optin_is_ajax]').val();
                let is_preview = jQuery(bwf_form).find('input[name=optin_is_preview]').val();

                if (is_admin || is_ajax || is_preview) {

                    valid = false;
                }


                valid = inst.DoValidation(FormElem);
                e.preventDefault();
                if (valid) {
                    jQuery(this).attr('disabled', 'disabled');
                    let submitting_text = jQuery(this).attr('data-subitting-text');
                    jQuery(FormElem).find("button.wfop_submit_btn .bwf_heading").html(submitting_text);
                    jQuery(FormElem).find("button.wfop_submit_btn .bwf_subheading").hide();


                    var wfopItiLib = wffnOptin.getItiLib();
                    if (wfopItiLib && wfopItiLib.getInstance && undefined !== jQuery(FormElem).find('input[name="wfop_optin_phone"]').get(0)) {
                        var iti = wfopItiLib.getInstance(jQuery(FormElem).find('input[name="wfop_optin_phone"]').get(0));
                        var getCountryData = iti ? (typeof iti.getSelectedCountry === 'function' ? iti.getSelectedCountry() : iti.getSelectedCountryData()) : null;
                        if (getCountryData) {
                            jQuery(FormElem).find('input[name="wfop_optin_phone_dialcode"]').eq(0).val('+' + getCountryData.dialCode);
                            jQuery(FormElem).find('input[name="wfop_optin_phone_countrycode"]').eq(0).val(getCountryData.iso2);
                        }

                    }

                    /* Add overlay Class when clicked on the button after validation */
                    $this.parents('.wffn-custom-optin-from').addClass("wffn-form-overlay");

                    inst.handleLeadEvent(FormElem);
                    /**
                     * XHR synchronous requests on the main threads are deprecated. We need to make it async, and after that trigger the form submission
                     */
                    var wffnHash = '';
                    if (typeof window.wffnPublicVars !== "undefined" && Object.hasOwnProperty.call(window.wffnfunnelData, 'hash')) {
                        wffnHash = window.wffnfunnelData.hash;
                    }
                    jQuery.ajax({
                        url: window.wffnfunnelVars.ajaxUrl + '?action=wffn_submit_custom_optin_form&lead_event_id=' + wffnfunnelVars.op_lead_tracking.fb.event_ID +'&wffn-si=' + wffnHash,
                        data: jQuery(FormElem).serialize(),
                        dataType: 'json',
                        type: 'post',
                    }).always(function (resp) {
                        /* Remove overlay Class after succuss  */
                        $this.parents('.wffn-custom-optin-from').addClass("wffn-form-overlay");
                        /* When there is no action for the form we reload the page manually so we won't mess up the redirects from WP */
                        if (Object.prototype.hasOwnProperty.call(resp, 'mapped')) {
                            for (var k in resp.mapped) {
                                jQuery(".wfop_integration_form input[name='" + k + "']").val(resp.mapped[k]);
                            }
                            jQuery(".wfop_integration_form").trigger('submit');
                            return;
                        }

                        if (Object.prototype.hasOwnProperty.call(resp, 'next_url') && '' !== resp.next_url) {
                            window.location.href = resp.next_url;
                            return;
                        }
                    });
                } else {
                    console.log('form validation failed');
                }
            });
        },
        handleSubmit: function () {
            let inst = this;
            jQuery("form.wffn-custom-optin-from").each(function () {
                inst.setUpClick(this);
            });

        },
        handleLeadEvent: function (formElem) {
            if (1 != wffnfunnelVars.op_should_render) {
                return;
            }
            
            // Extract email from form for Pinterest tracking
            let formEmail = null;
            if (formElem) {
                // Try common email field names
                const emailInput = jQuery(formElem).find('input[type="email"], input[name*="email"], input[name*="Email"], input[name*="EMAIL"]').first();
                if (emailInput.length > 0) {
                    formEmail = emailInput.val();
                }
            }
            if ('object' === typeof wffnfunnelVars.op_lead_tracking.fb.enable && 'yes' === wffnfunnelVars.op_lead_tracking.fb.enable[0] && false !== wffnfunnelVars.op_lead_tracking.fb.fb_pixels) {
                if (typeof fbq === 'undefined') {
                    (function (f, b, e, v, n, t, s) {
                        if (f.fbq) return;
                        n = f.fbq = function () {
                            let pl = n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
                        };
                        if (!f._fbq) f._fbq = n;
                        n.push = n;
                        n.loaded = !0;
                        n.version = '2.0';
                        n.queue = [];
                        t = b.createElement(e);
                        t.async = !0;
                        t.src = v;
                        s = b.getElementsByTagName(e)[0];
                        s.parentNode.insertBefore(t, s);
                    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

                    /** iterate loop **/
                    const pixelIds = wffnfunnelVars.op_lead_tracking.fb.fb_pixels.split(',');
                    $(pixelIds).each(function (k, v) {
                        fbq('init', v);
                    });
                }

                var data = {
                    'value': '0.00',
                    'currency': wffnfunnelVars.op_lead_tracking.fb.currency || 'USD'
                };
                data = (typeof wffnAddTrafficParamsToEvent !== "undefined") ? wffnAddTrafficParamsToEvent(data) : data;
                fbq('track', 'Lead', data, {'eventID': wffnfunnelVars.op_lead_tracking.fb.event_ID});
            }

            if ('object' === typeof wffnfunnelVars.op_lead_tracking.pint.enable && 'yes' === wffnfunnelVars.op_lead_tracking.pint.enable[0] && false !== wffnfunnelVars.op_lead_tracking.pint.pixels) {
                !function (e) {
                    if (!window.pintrk) {
                        window.pintrk = function () {
                            window.pintrk.queue.push(Array.prototype.slice.call(arguments))
                        };
                        var n = window.pintrk;
                        n.queue = [], n.version = "3.0";
                        var t = document.createElement("script");
                        t.async = !0, t.src = e;
                        var r = document.getElementsByTagName("script")[0];
                        r.parentNode.insertBefore(t, r)
                    }
                }("https://s.pinimg.com/ct/core.js");


                /** iterate loop **/
                const pixelIds = wffnfunnelVars.op_lead_tracking.pint.pixels.split(',');
                let data = (typeof wffnAddTrafficParamsToEvent !== "undefined") ? wffnAddTrafficParamsToEvent({}) : {};
            
                // Helper function to hash email with SHA256
                const hashEmail = async (email) => {
                    if (!email || typeof email !== 'string') return null;
                    const normalizedEmail = email.toLowerCase().trim();
                    const msgBuffer = new TextEncoder().encode(normalizedEmail);
                    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                };
                
                // Add email (em) and external_id for Pinterest Enhanced Match
                if (typeof wffnfunnelVars.op_lead_tracking.pint.advanced !== "undefined" && typeof wffnfunnelVars.op_lead_tracking.pint.advanced === "object" && wffnfunnelVars.op_lead_tracking.pint.advanced !== null && !Array.isArray(wffnfunnelVars.op_lead_tracking.pint.advanced) && Object.keys(wffnfunnelVars.op_lead_tracking.pint.advanced).length > 0) {
                    if (wffnfunnelVars.op_lead_tracking.pint.advanced.em) {
                        data.em = wffnfunnelVars.op_lead_tracking.pint.advanced.em;
                    }
                    if (wffnfunnelVars.op_lead_tracking.pint.advanced.external_id) {
                        data.external_id = wffnfunnelVars.op_lead_tracking.pint.advanced.external_id;
                    }
                }
                
                // Generate external_id if not available
                // Pinterest requires external_id to be a user identifier, not a timestamp
                // Try to get from cookie first (wffn_flt), then generate a unique ID
                if (!data.external_id) {
                    // Helper function to get cookie value
                    const getCookie = (name) => {
                        const value = `; ${document.cookie}`;
                        const parts = value.split(`; ${name}=`);
                        if (parts.length === 2) return parts.pop().split(';').shift();
                        return null;
                    };
                    
                    const cookieExternalId = getCookie('wffn_flt');
                    if (cookieExternalId) {
                        data.external_id = cookieExternalId;
                    } else {
                        const sessionId = Date.now().toString() + '_' + Math.random().toString(36).substr(2, 9);
                        data.external_id = sessionId;
                    }
                }
                
                // If email not in pint.advanced but available from form, hash and add it
                if (!data.em && formEmail) {
                    hashEmail(formEmail).then(hashedEmail => {
                        if (hashedEmail) {
                            data.em = hashedEmail;
                        }
                      
                        $(pixelIds).each(function (k, v) {
                            pintrk('load', v, {np: 'woofunnels'});
                            pintrk('track', 'Lead', data);
                        });
                    }).catch(err => {
                        console.log('Pinterest Optin Lead - Error hashing email:', JSON.stringify(err, null, 2));
                        // Fallback: track without email
                        $(pixelIds).each(function (k, v) {
                            pintrk('load', v, {np: 'woofunnels'});
                            pintrk('track', 'Lead', data);
                        });
                    });
                } else {
                    
                    $(pixelIds).each(function (k, v) {
                        pintrk('load', v, {np: 'woofunnels'});
                        pintrk('track', 'Lead', data);
                    });
                }
            }


            if (typeof gtag !== "undefined" && 'object' === typeof wffnfunnelVars.op_lead_tracking.ga.enable && 'yes' === wffnfunnelVars.op_lead_tracking.ga.enable[0] && false !== wffnfunnelVars.op_lead_tracking.ga.ids) {
                const pixelIds = wffnfunnelVars.op_lead_tracking.ga.ids.split(',');
                let data = (typeof wffnAddTrafficParamsToEvent !== "undefined") ? wffnAddTrafficParamsToEvent({}) : {};
                $(pixelIds).each(function (k, v) {
                    data.send_to = v;
                    gtag('event', 'Lead', data);
                });
            }

            if (typeof gtag !== "undefined" && 'object' === typeof wffnfunnelVars.op_lead_tracking.gad.enable && 'yes' === wffnfunnelVars.op_lead_tracking.gad.enable[0] && false !== wffnfunnelVars.op_lead_tracking.gad.ids) {
                const pixelIds = wffnfunnelVars.op_lead_tracking.gad.ids.split(',');
                let pixelLabels = [];

                if (typeof wffnfunnelVars.op_lead_tracking.gad.labels === "string") {
                    pixelLabels = wffnfunnelVars.op_lead_tracking.gad.labels.split(',');
                }
                let data = (typeof wffnAddTrafficParamsToEvent !== "undefined") ? wffnAddTrafficParamsToEvent({}) : {};
                $(pixelIds).each(function (k, v) {
                    if ( "undefined" !== typeof pixelLabels[k] && pixelLabels[k] !== "") {
                        data.send_to = v+'/'+pixelLabels[k];
                    }else{
                        data.send_to = v;
                    }
                    gtag('event', 'Lead', data);
                });
            }
        }
    };
    wffnOptin.init();
})(jQuery);
