"use strict";

function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
/*global wffnfunnelVars */
/*global wfffOptinVars */
/*global fbq */
/*global pintrk */
(function ($) {
  var wffnOptin = {
    init: function init() {
      var self = this;
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
    initPhoneFlag: function initPhoneFlag() {
      var intlconfig = {
        initialCountry: window.wfffOptinVars.op_flag_country,
        separateDialCode: true,
        geoIpLookup: function geoIpLookup(callback) {
          $.get('https://ipinfo.io', function () {}, "jsonp").always(function (resp) {
            var countryCode = resp && resp.country ? resp.country : "us";
            callback(countryCode);
          });
        }
      };
      if (typeof window.wfffOptinVars.onlyCountries !== "undefined" && window.wfffOptinVars.onlyCountries.length > 0) {
        intlconfig.onlyCountries = window.wfffOptinVars.onlyCountries;
      }
      var elems = document.querySelectorAll(".phone_flag_code input[type='tel']");
      for (var i in elems) {
        if (_typeof(elems[i]) === 'object' && undefined !== window.intlTelInput) {
          window.intlTelInput(elems[i], intlconfig);
        }
      }
    },
    attachCloseBtn: function attachCloseBtn() {
      jQuery(document).on('click', '.bwf_pp_close', function (e) {
        e.preventDefault();
        jQuery('.bwf_pp_overlay').removeClass('show_popup_form');
        jQuery('body').css('overflow', '');
      });
    },
    renderPBPopUps: function renderPBPopUps() {
      jQuery('.wfop_pb_widget_wrap').each(function () {
        var elem = this;
        jQuery(this).find(".bwf-custom-button a").click(function (e) {
          e.preventDefault();
          jQuery(elem).find('.bwf_pp_overlay').addClass('show_popup_form');
          jQuery('body').css('overflow', 'hidden');
        });
      });
    },
    renderForm: function renderForm() {
      if (jQuery('.bwf_pp_overlay').length > 0) {
        jQuery('a[href*="wfop-popup=yes"]').on('click', function (e) {
          e.preventDefault();
          jQuery('.bwf_pp_overlay').addClass('show_popup_form');
        });
      }
    },
    DoValidation: function DoValidation(formElem) {
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
          if ("undefined" !== typeof window.intlTelInputGlobals) {
            var itis = window.intlTelInputGlobals.getInstance(inst.get(0));
            if (!itis.isValidNumber()) {
              if (Array.isArray(error_phone)) {
                var errorCode = itis.getValidationError();
                error_message = error_phone[errorCode];
              } else {
                error_message = error_phone;
              }
            }
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
    setUpClick: function setUpClick(FormElem) {
      var inst = this;
      jQuery(FormElem).find('#wffn_custom_optin_submit').on('click', function (e) {
        var valid = true;
        jQuery(this).removeAttr('disabled');
        var $this = jQuery(this);
        var bwf_form = FormElem;
        jQuery(bwf_form).find('.bwfac_form_sec').removeClass('bwfac_error');
        jQuery(bwf_form).find('.bwfac_form_sec .error').remove();
        var is_admin = jQuery(bwf_form).find('input[name=optin_is_admin]').val();
        var is_ajax = jQuery(bwf_form).find('input[name=optin_is_ajax]').val();
        var is_preview = jQuery(bwf_form).find('input[name=optin_is_preview]').val();
        if (is_admin || is_ajax || is_preview) {
          valid = false;
        }
        valid = inst.DoValidation(FormElem);
        e.preventDefault();
        if (valid) {
          jQuery(this).attr('disabled', 'disabled');
          var submitting_text = jQuery(this).attr('data-subitting-text');
          jQuery(FormElem).find("button.wfop_submit_btn .bwf_heading").html(submitting_text);
          jQuery(FormElem).find("button.wfop_submit_btn .bwf_subheading").hide();
          if ("undefined" !== typeof window.intlTelInputGlobals && undefined !== jQuery(FormElem).find('input[name="wfop_optin_phone"]').get(0)) {
            var iti = window.intlTelInputGlobals.getInstance(jQuery(FormElem).find('input[name="wfop_optin_phone"]').get(0));
            var getCountryData = iti.getSelectedCountryData();
            jQuery(FormElem).find('input[name="wfop_optin_phone_dialcode"]').eq(0).val('+' + getCountryData.dialCode);
            jQuery(FormElem).find('input[name="wfop_optin_phone_countrycode"]').eq(0).val(getCountryData.iso2);
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
            url: window.wffnfunnelVars.ajaxUrl + '?action=wffn_submit_custom_optin_form&lead_event_id=' + wffnfunnelVars.op_lead_tracking.fb.event_ID + '&wffn-si=' + wffnHash,
            data: jQuery(FormElem).serialize(),
            dataType: 'json',
            type: 'post'
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
    handleSubmit: function handleSubmit() {
      var inst = this;
      jQuery("form.wffn-custom-optin-from").each(function () {
        inst.setUpClick(this);
      });
    },
    handleLeadEvent: function handleLeadEvent(formElem) {
      if (1 != wffnfunnelVars.op_should_render) {
        return;
      }

      // Extract email from form for Pinterest tracking
      var formEmail = null;
      if (formElem) {
        // Try common email field names
        var emailInput = jQuery(formElem).find('input[type="email"], input[name*="email"], input[name*="Email"], input[name*="EMAIL"]').first();
        if (emailInput.length > 0) {
          formEmail = emailInput.val();
        }
      }
      if ('object' === _typeof(wffnfunnelVars.op_lead_tracking.fb.enable) && 'yes' === wffnfunnelVars.op_lead_tracking.fb.enable[0] && false !== wffnfunnelVars.op_lead_tracking.fb.fb_pixels) {
        if (typeof fbq === 'undefined') {
          (function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () {
              var pl = n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
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
          var pixelIds = wffnfunnelVars.op_lead_tracking.fb.fb_pixels.split(',');
          $(pixelIds).each(function (k, v) {
            fbq('init', v);
          });
        }
        var data = {
          'value': '0.00',
          'currency': wffnfunnelVars.op_lead_tracking.fb.currency || 'USD'
        };
        data = typeof wffnAddTrafficParamsToEvent !== "undefined" ? wffnAddTrafficParamsToEvent(data) : data;
        fbq('track', 'Lead', data, {
          'eventID': wffnfunnelVars.op_lead_tracking.fb.event_ID
        });
      }
      if ('object' === _typeof(wffnfunnelVars.op_lead_tracking.pint.enable) && 'yes' === wffnfunnelVars.op_lead_tracking.pint.enable[0] && false !== wffnfunnelVars.op_lead_tracking.pint.pixels) {
        !function (e) {
          if (!window.pintrk) {
            window.pintrk = function () {
              window.pintrk.queue.push(Array.prototype.slice.call(arguments));
            };
            var n = window.pintrk;
            n.queue = [], n.version = "3.0";
            var t = document.createElement("script");
            t.async = !0, t.src = e;
            var r = document.getElementsByTagName("script")[0];
            r.parentNode.insertBefore(t, r);
          }
        }("https://s.pinimg.com/ct/core.js");

        /** iterate loop **/
        var _pixelIds = wffnfunnelVars.op_lead_tracking.pint.pixels.split(',');
        var _data = typeof wffnAddTrafficParamsToEvent !== "undefined" ? wffnAddTrafficParamsToEvent({}) : {};

        // Helper function to hash email with SHA256
        var hashEmail = /*#__PURE__*/function () {
          var _ref = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee(email) {
            var normalizedEmail, msgBuffer, hashBuffer, hashArray;
            return _regenerator().w(function (_context) {
              while (1) switch (_context.n) {
                case 0:
                  if (!(!email || typeof email !== 'string')) {
                    _context.n = 1;
                    break;
                  }
                  return _context.a(2, null);
                case 1:
                  normalizedEmail = email.toLowerCase().trim();
                  msgBuffer = new TextEncoder().encode(normalizedEmail);
                  _context.n = 2;
                  return crypto.subtle.digest('SHA-256', msgBuffer);
                case 2:
                  hashBuffer = _context.v;
                  hashArray = Array.from(new Uint8Array(hashBuffer));
                  return _context.a(2, hashArray.map(function (b) {
                    return b.toString(16).padStart(2, '0');
                  }).join(''));
              }
            }, _callee);
          }));
          return function hashEmail(_x) {
            return _ref.apply(this, arguments);
          };
        }();

        // Add email (em) and external_id for Pinterest Enhanced Match
        if (typeof wffnfunnelVars.op_lead_tracking.pint.advanced !== "undefined" && _typeof(wffnfunnelVars.op_lead_tracking.pint.advanced) === "object" && wffnfunnelVars.op_lead_tracking.pint.advanced !== null && !Array.isArray(wffnfunnelVars.op_lead_tracking.pint.advanced) && Object.keys(wffnfunnelVars.op_lead_tracking.pint.advanced).length > 0) {
          if (wffnfunnelVars.op_lead_tracking.pint.advanced.em) {
            _data.em = wffnfunnelVars.op_lead_tracking.pint.advanced.em;
          }
          if (wffnfunnelVars.op_lead_tracking.pint.advanced.external_id) {
            _data.external_id = wffnfunnelVars.op_lead_tracking.pint.advanced.external_id;
          }
        }

        // Generate external_id if not available
        // Pinterest requires external_id to be a user identifier, not a timestamp
        // Try to get from cookie first (wffn_flt), then generate a unique ID
        if (!_data.external_id) {
          // Helper function to get cookie value
          var getCookie = function getCookie(name) {
            var value = "; ".concat(document.cookie);
            var parts = value.split("; ".concat(name, "="));
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
          };
          var cookieExternalId = getCookie('wffn_flt');
          if (cookieExternalId) {
            _data.external_id = cookieExternalId;
          } else {
            var sessionId = Date.now().toString() + '_' + Math.random().toString(36).substr(2, 9);
            _data.external_id = sessionId;
          }
        }

        // If email not in pint.advanced but available from form, hash and add it
        if (!_data.em && formEmail) {
          hashEmail(formEmail).then(function (hashedEmail) {
            if (hashedEmail) {
              _data.em = hashedEmail;
            }
            $(_pixelIds).each(function (k, v) {
              pintrk('load', v, {
                np: 'woofunnels'
              });
              pintrk('track', 'Lead', _data);
            });
          })["catch"](function (err) {
            console.log('Pinterest Optin Lead - Error hashing email:', JSON.stringify(err, null, 2));
            // Fallback: track without email
            $(_pixelIds).each(function (k, v) {
              pintrk('load', v, {
                np: 'woofunnels'
              });
              pintrk('track', 'Lead', _data);
            });
          });
        } else {
          $(_pixelIds).each(function (k, v) {
            pintrk('load', v, {
              np: 'woofunnels'
            });
            pintrk('track', 'Lead', _data);
          });
        }
      }
      if (typeof gtag !== "undefined" && 'object' === _typeof(wffnfunnelVars.op_lead_tracking.ga.enable) && 'yes' === wffnfunnelVars.op_lead_tracking.ga.enable[0] && false !== wffnfunnelVars.op_lead_tracking.ga.ids) {
        var _pixelIds2 = wffnfunnelVars.op_lead_tracking.ga.ids.split(',');
        var _data2 = typeof wffnAddTrafficParamsToEvent !== "undefined" ? wffnAddTrafficParamsToEvent({}) : {};
        $(_pixelIds2).each(function (k, v) {
          _data2.send_to = v;
          gtag('event', 'Lead', _data2);
        });
      }
      if (typeof gtag !== "undefined" && 'object' === _typeof(wffnfunnelVars.op_lead_tracking.gad.enable) && 'yes' === wffnfunnelVars.op_lead_tracking.gad.enable[0] && false !== wffnfunnelVars.op_lead_tracking.gad.ids) {
        var _pixelIds3 = wffnfunnelVars.op_lead_tracking.gad.ids.split(',');
        var pixelLabels = [];
        if (typeof wffnfunnelVars.op_lead_tracking.gad.labels === "string") {
          pixelLabels = wffnfunnelVars.op_lead_tracking.gad.labels.split(',');
        }
        var _data3 = typeof wffnAddTrafficParamsToEvent !== "undefined" ? wffnAddTrafficParamsToEvent({}) : {};
        $(_pixelIds3).each(function (k, v) {
          if ("undefined" !== typeof pixelLabels[k] && pixelLabels[k] !== "") {
            _data3.send_to = v + '/' + pixelLabels[k];
          } else {
            _data3.send_to = v;
          }
          gtag('event', 'Lead', _data3);
        });
      }
    }
  };
  wffnOptin.init();
})(jQuery);
