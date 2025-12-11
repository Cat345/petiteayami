/*eslint-env jquery*/
/*global Vue*/
/*global VueFormGenerator*/
/*global wfocu*/
/*global wfocuParams*/
/*global wp_admin_ajax*/
/*global wfocuSweetalert2*/

(function ($, doc, win) {
    'use strict';
    let show_logs = true;
    let wfocu_logs = [];
    let my_logging_system = console.log;

    $(window).on('load', function () {
        setTimeout(function () {
            $('.wfocu_global_loader').hide();
        }, 600);
    });

    window.console.log1 = function () {
        let arg = arguments;
        if (arg.length > 0) {
            for (let i = 0; i < arg.length; i++) {
                wfocu_logs.push(arg[i]);
                if (show_logs) {
                    my_logging_system(arg[i]);
                }
            }
        }
    };

    Vue.component("field-custom_skip_purchase_html", {
        mixins: [VueFormGenerator.abstractField],
        template: '<span class="wfoc-desc">' + window.wfocu.indexing_texts.help_text + ' <a class="anch" target="_blank" href="' + wfocu.site_url + '/wp-admin/admin.php?page=woofunnels&tab=tools">' + window.wfocu.indexing_texts.link + '</a> ' + window.wfocu.indexing_texts.after_text + '</span>',

        mounted: function () {
        },
    });

    let wfocuBuilderCommons = {

        hooks: {action: {}, filter: {}},
        addAction: function (action, callable, priority, tag) {
            this.addHook('action', action, callable, priority, tag);
        },
        addFilter: function (action, callable, priority, tag) {
            this.addHook('filter', action, callable, priority, tag);
        },
        doAction: function (action) {
            this.doHook('action', action, arguments);
        },
        applyFilters: function (action) {
            return this.doHook('filter', action, arguments);
        },
        removeAction: function (action, tag) {
            this.removeHook('action', action, tag);
        },
        removeFilter: function (action, priority, tag) {
            this.removeHook('filter', action, priority, tag);
        },
        addHook: function (hookType, action, callable, priority, tag) {
            if (undefined == this.hooks[hookType][action]) {
                this.hooks[hookType][action] = [];
            }
            var hooks = this.hooks[hookType][action];
            if (undefined == tag) {
                tag = action + '_' + hooks.length;
            }
            if (priority == undefined) {
                priority = 10;
            }

            this.hooks[hookType][action].push({tag: tag, callable: callable, priority: priority});
        },
        doHook: function (hookType, action, args) {

            // splice args from object into array and remove first index which is the hook name
            args = Array.prototype.slice.call(args, 1);
            if (undefined != this.hooks[hookType][action]) {
                var hooks = this.hooks[hookType][action], hook;
                //sort by priority
                hooks.sort(
                    function (a, b) {
                        return a["priority"] - b["priority"]
                    }
                );
                for (var i = 0; i < hooks.length; i++) {
                    hook = hooks[i].callable;
                    if (typeof hook != 'function') {
                        hook = window[hook];
                    }
                    if ('action' == hookType) {
                        hook.apply(null, args);
                    } else {
                        args[0] = hook.apply(null, args);
                    }
                }
            }
            if ('filter' == hookType) {
                return args[0];
            }
        },
        removeHook: function (hookType, action, priority, tag) {
            if (undefined != this.hooks[hookType][action]) {
                var hooks = this.hooks[hookType][action];
                for (var i = hooks.length - 1; i >= 0; i--) {
                    if ((undefined == tag || tag == hooks[i].tag) && (undefined == priority || priority == hooks[i].priority)) {
                        hooks.splice(i, 1);
                    }
                }
            }
        }
    };

    let wfo_builder = function () {
        if (typeof wfocu === 'undefined') {
            return false;
        }

        const self = this;

        const default_offer_state = '0';
        let update_funnel_model = false;
        let step_container = '.wfocu_steps_sortable';
        let design_container = '#wfocu_step_design';
        let settings_container = '#wfocu_funnel_setting_vue';
        let global_settings_container = '#wfocu_global_setting';
        let step_list = '.wfocu_steps_sortable .wfocu_step';
        let product_search_is_open = false;
        let page_search_is_open = false;
        let add_new_step_is_open = false;
        let product_search_timeout = null;

        let current_offer_id = 0;
        let funnel_id = 0;
        let offer_steps = [];
        let offer_forms = {};
        let current_index = 0;
        let selected_product = '';
        let step_change_timeout = null;
        let update_step_settings = [
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "funnel_step_name",
                featured: true,
                inputName: 'step_name',
                required: true,
                placeholder: "",
                validator: VueFormGenerator.validators.string,
            }, {
                type: "radios",
                label: "",
                model: "step_type",
                inputName: 'step_type',
                help: "",
                styleClasses: ["wfocu_form_button"],
                values: [
                    {name: "", value: "upsell"},
                    {name: "", value: "downsell"}
                ],
            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "funnel_step_slug",
                featured: true,
                styleClasses: ["wfocu_step_slug"],
                inputName: 'funnel_step_slug',
                required: true,
                placeholder: "",
                validator: VueFormGenerator.validators.string,
            }
        ];
        let update_step_is_open = false;
        let add_new_offer_setting_fields = [
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "funnel_step_name",
                featured: true,
                inputName: 'step_name',
                required: true,
                placeholder: "",
                validator: VueFormGenerator.validators.string,
            }, {
                type: "radios",
                label: "",
                model: "step_type",
                inputName: 'step_type',
                help: "",
                styleClasses: ["wfocu_form_button"],
                values: [
                    {name: "", value: "upsell"},
                    {name: "", value: "downsell"}
                ],
                visible: function (model) {
                    if (model.show_select_btn) {
                        return true;
                    }
                    return false;
                }
            }];


        let funnel_setting_fields = [
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "funnel_name",
                inputName: 'funnel_name',
                featured: true,
                required: true,
                placeholder: "Enter Name",
                validator: VueFormGenerator.validators.string
            }, {
                type: "textArea",
                label: "",
                model: "funnel_desc",
                inputName: 'funnel_desc',
                featured: true,
                rows: 3,
                placeholder: "Enter Description (Optional)"
            }];
        let global_settings_order_statuses_fields = [
            /** Order section started **/
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "primary_order_status_title",
                inputName: 'primary_order_status_title',

            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "ttl_funnel",
                inputName: 'ttl_funnel',

            },
            {
                type: "select",
                label: "",
                model: "create_new_order_status_fail",
                inputName: 'create_new_order_status_fail',
                selectOptions: {hideNoneSelectedText: true},

            },

        ];
        let global_settings_gateway_fields = [
            {
                type: "checklist",
                listBox: true,
                label: "",
                styleClasses: "wfocu_gsettings_sec_chlist",
                model: "gateways",
                inputName: 'gateways',
                visible: function () {
                    return !wfocuParams.isNOGateway;
                }
            },
            {
                type: "label",
                label: "",
                styleClasses: "wfocu_gsettings_sec_no_gateways",
                model: "no_gateways",
                inputName: 'no_gateways',
                visible: function () {

                    return wfocuParams.isNOGateway;
                }
            },
            {
                type: "radios",
                label: "",
                styleClasses: "wfocu_gsettings_paypal_ref_trans",
                model: "paypal_ref_trans",
                inputName: 'paypal_ref_trans',
                visible: function (model) {
                    for (var k in wfocuParams.forms_labels.global_settings.wfocu_gateways.fields) {
                        if (wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].key === 'gateways') {
                            if (typeof wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values !== "undefined" && wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values) {
                                var havePayPalGateway = false;
                                for (var vk in wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values) {
                                    if (-1 !== ["ppec_paypal", "paypal"].indexOf(wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values[vk].value)) {
                                        havePayPalGateway = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    return wfocuBuilderCommons.applyFilters('wfocu_global_settings_gateway_fields', (havePayPalGateway && model.gateways.indexOf('paypal') !== -1) || (havePayPalGateway && model.gateways.indexOf('ppec_paypal') !== -1), model.gateways);
                }
            },
            {
                type: "radios",
                label: "",
                styleClasses: "wfocu_gsettings_sepa_gateway_trans",
                model: "sepa_gateway_trans",
                inputName: 'sepa_gateway_trans',

                visible: function (model) {
                    var haveMollieGateway = false;
                    for (var k in wfocuParams.forms_labels.global_settings.wfocu_gateways.fields) {
                        if (wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].key === 'gateways') {
                            if (typeof wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values !== "undefined" && wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values) {
                                 haveMollieGateway = false;
                                for (var vk in wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values) {
                                    if (-1 !== ["mollie_wc_gateway_ideal", "mollie_wc_gateway_bancontact","mollie_wc_gateway_sofort"].indexOf(wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].values[vk].value)) {
                                        haveMollieGateway = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    return  (haveMollieGateway && model.gateways.indexOf('mollie_wc_gateway_ideal') !== -1) || (haveMollieGateway && model.gateways.indexOf('mollie_wc_gateway_bancontact') !== -1) || (haveMollieGateway && model.gateways.indexOf('mollie_wc_gateway_sofort') !== -1);
                }
            },
            {
                type: "checklist",
                listBox: true,
                label: "",
                styleClasses: "wfocu_gsettings_sec_chlist",
                model: "gateway_test",
                inputName: 'gateway_test',

            }

        ];
        let global_settings_tan_fields = [

            {
                type: "custom_html_tracking_generalgad",
                styleClasses: "bwf_wrap_custom_html_tracking_general",
                model: "custom_html_tracking_general_",
            },

        ];
        let global_settings_scripts_fields = [
            {
                type: "textArea",
                label: "",
                model: "scripts",
                inputName: 'scripts',

            },
            {
                type: "textArea",
                label: "",
                model: "scripts_head",
                inputName: 'scripts_head',

            },
        ];
        let global_settings_emails_fields = [
            /** Head Emails starts **/
            {
                type: "radios",
                label: "",
                model: "send_processing_mail_on",
                inputName: 'send_processing_mail_on',

            },
            {
                type: "radios",
                label: "",
                model: "send_processing_mail_on_no_batch",
                inputName: 'send_processing_mail_on_no_batch',

            },
            {
                type: "radios",
                label: "",
                model: "send_processing_mail_on_no_batch_cancel",
                inputName: 'send_processing_mail_on_no_batch_cancel',

            },
            {
                type: "label",
                label: "",
                styleClasses: "wfocu_gsettings_sec_note",
                model: "send_emails_label",
                inputName: 'send_emails_label',

            },

            /** Head Emails Ends **/];
        let global_settings_misc_fields = [
            /** Head Misc starts **/

            {
                type: "input",
                inputType: "text",
                label: "",
                model: "flat_shipping_label",
                inputName: 'flat_shipping_label',

            },


            {
                type: "checkbox",
                label: "",
                model: "enable_log",
                inputName: 'enable_log',

            },

            {
                type: "textArea",
                label: "",
                model: "order_copy_meta_keys",
                inputName: 'order_copy_meta_keys',

            },

            {
                type: "checkbox",
                label: "",
                model: "treat_variable_as_simple",
                inputName: 'treat_variable_as_simple',

            },

            {
                type: "checkbox",
                label: "",
                model: "enable_noconflict_mode",
                inputName: 'enable_noconflict_mode',

            },

            /** Head Misc ends **/];

        let global_settings_offer_confirmation_fields = [

            {
                type: "label",
                label: "",
                styleClasses: ["wfocu_gsettings_sec_note", "wfocu_to_html"],
                model: "offer_header_label",
                inputName: 'offer_header_label',
            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_header_text",
                inputName: 'offer_header_text',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_text",
                inputName: 'offer_yes_btn_text',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_skip_link_text",
                inputName: 'offer_skip_link_text',
            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_bg_cl",
                inputName: 'offer_yes_btn_bg_cl',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_sh_cl",
                inputName: 'offer_yes_btn_sh_cl',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_txt_cl",
                inputName: 'offer_yes_btn_txt_cl',
            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_bg_cl_h",
                inputName: 'offer_yes_btn_bg_cl_h',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_sh_cl_h",
                inputName: 'offer_yes_btn_sh_cl_h',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_yes_btn_txt_cl_h",
                inputName: 'offer_yes_btn_txt_cl_h',
            },
            {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_no_btn_txt_cl",
                inputName: 'offer_no_btn_txt_cl',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "offer_no_btn_txt_cl_h",
                inputName: 'offer_no_btn_txt_cl_h',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "cart_opener_text",
                inputName: 'cart_opener_text',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "cart_opener_text_color",
                inputName: 'cart_opener_text_color',
            }, {
                type: "input",
                inputType: "text",
                label: "",
                model: "cart_opener_background_color",
                inputName: 'cart_opener_background_color',
            },
        ];

        this.notLastStep = function () {
            let offerSteps = self.get_offer_steps();
            let nolastStep = true;
            for (let offer_index in offerSteps) {
                if ((parseInt(offer_index) === (parseInt(offerSteps.length) - parseInt(1))) && parseInt(self.offer_setting.current_offer_id) === parseInt(offerSteps[offer_index].id)) {
                    nolastStep = false;
                }
            }
            return nolastStep;
        };
        this.nextOffers = function () {

            let options = [];
            let current_offer_index = 0;
            let offerSteps = self.get_offer_steps();
            let upsells = [];
            let downsells = [];
            let offer_on_accepted = self.offer_setting.model.jump_to_offer_on_accepted;
            let offer_on_rejected = self.offer_setting.model.jump_to_offer_on_rejected;
            let accepted_exist = (wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id === offer_on_accepted) ? true : ((wfocuParams.forms_labels.offer_settings.jump_to_thankyou.id === offer_on_accepted) ? true : false);
            let rejected_exist = (wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id === offer_on_rejected) ? true : ((wfocuParams.forms_labels.offer_settings.jump_to_thankyou.id === offer_on_rejected) ? true : false);

            for (let offer_index in offerSteps) {
                if (parseInt(self.offer_setting.current_offer_id) === parseInt(offerSteps[offer_index].id)) {
                    current_offer_index = offer_index;
                    break;
                }
            }
            for (let offer_index in self.get_offer_steps()) {
                if ('0' === offer_index) {
                    options.push({
                        'id': wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                        'name': wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.name
                    });
                }
                if (parseInt(offer_index) > parseInt(current_offer_index)) {
                    if ('upsell' === offerSteps[offer_index].type) {
                        upsells.push({'id': offerSteps[offer_index].id, 'name': offerSteps[offer_index].name});
                    } else {
                        downsells.push({'id': offerSteps[offer_index].id, 'name': offerSteps[offer_index].name});
                    }
                }
            }

            if (upsells.length > 0 || downsells.length > 0) {
                for (let up_index in upsells) {
                    options.push({'id': upsells[up_index].id, 'name': upsells[up_index].name, 'group': wfocuParams.forms_labels.offer_settings.jump_optgroups.upsells});
                    accepted_exist = (false === accepted_exist) ? (parseInt(offer_on_accepted) === parseInt(upsells[up_index].id)) : accepted_exist;
                    rejected_exist = (false === rejected_exist) ? (parseInt(offer_on_rejected) === parseInt(upsells[up_index].id)) : rejected_exist;
                }
                for (let dn_index in downsells) {
                    options.push({'id': downsells[dn_index].id, 'name': downsells[dn_index].name, 'group': wfocuParams.forms_labels.offer_settings.jump_optgroups.downsells});
                    accepted_exist = (false === accepted_exist) ? (parseInt(offer_on_accepted) === parseInt(downsells[dn_index].id)) : accepted_exist;
                    rejected_exist = (false === rejected_exist) ? (parseInt(offer_on_rejected) === parseInt(downsells[dn_index].id)) : rejected_exist;
                }
            }

            if (undefined === offer_on_accepted || false === accepted_exist) {
                self.offer_setting.model.jump_to_offer_on_accepted = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
            }
            if (undefined === offer_on_rejected || false === rejected_exist) {
                self.offer_setting.model.jump_to_offer_on_rejected = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
            }
            options.push({
                'id': wfocuParams.forms_labels.offer_settings.jump_to_thankyou.id,
                'name': wfocuParams.forms_labels.offer_settings.jump_to_thankyou.name,
                'group': wfocuParams.forms_labels.offer_settings.jump_optgroups.terminate
            });

            return options;
        };

        let offer_settings_schema = [


            {
                type: "label",
                label: "",
                model: "jump_to_offer",
                visible: self.notLastStep,
            },
            {
                type: "checkbox",
                label: "",
                model: "jump_on_accepted",
                inputName: 'jump_on_accepted',
                visible: self.notLastStep,
            },
            {
                type: 'select',
                label: "",
                model: "jump_to_offer_on_accepted",
                inputName: 'jump_to_offer_on_accepted',
                selectOptions: {hideNoneSelectedText: true},
                values: self.nextOffers,
                visible: self.notLastStep,
                disabled: function (model) {
                    return model && model.jump_on_accepted == false;
                }
            },
            {
                type: "checkbox",
                label: "",
                model: "jump_on_rejected",
                inputName: 'jump_on_rejected',
                visible: self.notLastStep,
            },
            {
                type: "select",
                label: "",
                model: "jump_to_offer_on_rejected",
                inputName: 'jump_to_offer_on_rejected',
                selectOptions: {hideNoneSelectedText: true},
                visible: self.notLastStep,
                values: self.nextOffers,
                disabled: function (model) {
                    return model && model.jump_on_rejected == false;
                }
            },

            {
                type: "label",
                label: "",
                model: "label_confirmation",
                id: "ask_confirmation_label",
            },
            {
                type: "checkbox",
                label: "",
                model: "ask_confirmation",
                inputName: 'ask_confirmation',
                styleClasses: "wfocu_ask_confirmation",
            },

            {
                type: "label",
                label: "Skip Offer",
                model: "label_order"
            },
            {
                type: "checkbox",
                label: "",
                model: "skip_exist",
                inputName: 'skip_exist',
                styleClasses: "wfocu_skip_exist",
            },
            {
                type: "checkbox",
                label: "",
                //hint: "<i>Skip this offer if product(s) <a href=''>ever purchased</a></i>",
                model: "skip_purchased",
                inputName: 'skip_purchased',
                styleClasses: "wfocu_skip_purchased",
            }, {
                type: "custom_skip_purchase_html",
                visible: function (model) {
                    return model && model.skip_purchased === true && (true === wfocu.bwf_needs_indexning);
                }
            },

            {
                type: "label",
                label: "",
                model: "upsell_page_track_code_label"
            },
            {
                type: "checkbox",
                label: "",
                model: "check_add_offer_script",
                inputName: 'check_add_offer_script',
            },
            {
                type: "textArea",
                label: "",
                model: "upsell_page_track_code",
                styleClasses: "wfocu_offer_settings_textarea",
                placeholder: '',
                rows: 5,
                visible: function (model) {
                    return model && model.check_add_offer_script == true && model.check_add_offer_script == true;
                }
            },
            {
                type: "checkbox",
                label: "",
                model: "check_add_offer_purchase",
                inputName: 'check_add_offer_purchase',
            },

            {
                type: "textArea",
                label: "",
                model: "upsell_page_purchase_code",
                styleClasses: "wfocu_offer_settings_textarea",
                placeholder: '',
                rows: 5,
                visible: function (model) {
                    return model && model.check_add_offer_purchase == true && model.check_add_offer_purchase == true;
                }
            },


            {
                type: "label",
                label: "",
                model: "qty_selector_label"
            },
            {
                type: "checkbox",
                label: "",
                model: "qty_selector",
                inputName: 'qty_selector',
            },
            {
                type: "label",
                label: "",
                model: "qty_max_label",
                visible: function (model) {
                    return model && model.qty_selector == true;
                }
            },
            {
                type: "input",
                label: "",
                inputType: "number",
                inputName: 'qty_max',
                model: "qty_max",
                styleClasses: "wfocu_offer_settings_textarea",
                placeholder: '',
                visible: function (model) {
                    return model && model.qty_selector == true;
                }
            },


        ];

        this.ol = function (obj) {
            let c = 0;
            if (obj != null && typeof obj === "object") {
                c = Object.keys(obj).length;
            }
            return c;
        };
        this.isEmpty = function (obj) {
            for (let key in obj) {
                if (Object.prototype.hasOwnProperty.call(obj, key)) {
                    return false;
                }
            }
            return true;
        };

        this.hp = function (obj, key) {
            let c = false;
            if (typeof obj === "object" && key !== undefined) {
                c = Object.prototype.hasOwnProperty.call(obj, key)
            }
            return c;
        };

        this.jsp = function (obj) {
            if (typeof obj === 'object') {
                let doc = JSON.stringify(obj);
                doc = JSON.parse(doc);
                return doc;
            } else {
                return obj;
            }
        };
        this.kys = function (obj) {
            if (typeof obj === 'object' && obj != null && this.ol(obj) > 0) {
                return Object.keys(obj);
            }
            return [];
        };

        let wfo_ajax = function () {
            let ajax = new wp_admin_ajax();
            return ajax;

        };
        this.offer_product_settings = null;
        this.offer_settings_btn_bottom = null;
        this.funnel_setting = null;
        this.product_search_setting = null;
        this.page_search_setting = null;
        this.add_new_offer_setting = null;
        this.update_offer_setting = null;
        this.offer_setting = null;
        this.funnel_advanced_setting = null;
        this.design_settings = null;

        this.get_current_url = function () {
            return window.location.href;
        };
        this.get_customizer_url = function () {
            return wfocu.customize_url;
        };

        this.build_customize_url = function (template) {
            if (typeof template !== 'undefined' && template !== 'undefined') {
                let params = {};
                let index_id = this.get_current_index();
                let steps = this.get_offer_step(index_id);
                let step = JSON.stringify(steps);
                step = JSON.parse(step);
                let customize_url = this.get_customizer_url();

                params.wfocu_customize = 'loaded';
                params.offer_id = step.id;

                if ('' === wfocuParams.permalinkStruct || undefined === wfocuParams.permalinkStruct) {
                    step.url += "&wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                } else {
                    step.url += "?wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                }

                params.url = decodeURIComponent(step.url);
                params.return = decodeURIComponent(this.get_current_url());
                let query_string = $.param(params);
                return customize_url + "?" + query_string;

            }


            return "";
        };

        this.get_funnel_name = function () {
            return wfocu.funnel_name;
        };
        this.get_funnel_desc = function () {
            return wfocu.funnel_desc;
        };


        this.create_step = function (rsp) {
            if (this.ol(rsp) > 0 && this.hp(rsp, 'id') && this.hp(rsp, "title")) {
                let non_draggble = "";
                if (this.get_offer_id() === 0) {
                    this.set_offer_id(rsp.id);

                    this.offer_product_settings.current_offer = rsp.title;

                    this.offer_product_settings.url = rsp.url;

                    this.offer_product_settings.current_offer_name = rsp.slug;

                    // non_draggble = "ui-state-disabled";
                }

                self.offer_setting.product_count = 0;
                self.add_new_offer_setting.model.show_select_btn = true;

                let div = document.createElement('div');
                div.className = 'wfocu_step_container ' + non_draggble;
                div.setAttribute('data-offer_id', rsp.id);
                div.setAttribute('data-offer_url', rsp.url);
                div.setAttribute('data-offer_title', rsp.title);
                div.setAttribute('data-offer_type', rsp.type);
                div.setAttribute('data-offer_state', default_offer_state);

                div.setAttribute('data-offer-slug', rsp.slug);
                let step_el = document.createElement('a');
                step_el.setAttribute('data-offer_id', rsp.id);
                step_el.setAttribute('data-offer_title', rsp.title);
                step_el.setAttribute('data-offer_type', rsp.type);
                step_el.className = "wfocu_step";
                let i = document.createElement('i');
                if (rsp.type === 'upsell') {
                    i.className = "wfocu_icon_fixed dashicons dashicons-arrow-up";
                } else {
                    i.className = "wfocu_icon_fixed dashicons dashicons-arrow-down";
                }
                step_el.appendChild(i);

                let sp1 = document.createElement('span');

                sp1.className = "step_name_card";
                let sp122 = document.createElement('span');
                sp122.className = "step_name";
                let t1 = document.createTextNode(rsp.title);

                sp122.appendChild(t1);
                sp1.appendChild(sp122);
                step_el.appendChild(sp1);

                let sp2 = document.createElement('span');
                sp2.className = "wfocu_up_arrow";
                step_el.appendChild(sp2);

                let sp3 = document.createElement('span');
                sp3.className = "wfocu_down_arrow";
                step_el.appendChild(sp3);

                let sp4 = document.createElement('span');
                sp4.className = "wfocu_step_offer_state";
                step_el.appendChild(sp4);

                let sp5 = document.createElement('span');
                sp5.className = "wfocu_remove_step";
                sp5.setAttribute('onClick', "wfocuBuilder.offer_settings_btn_bottom.delete_offer(this, " + rsp.id + ")");
                let sp5i = document.createElement('i');
                sp5i.className = "dashicons dashicons-no-alt";
                sp5.appendChild(sp5i);
                step_el.appendChild(sp5);

                div.appendChild(step_el);
                let step_dom = document.querySelector('.wfocu_steps_sortable');
                let step_node_ref = document.querySelector('.wfocu_steps_sortable .wfocu_button_add');
                if (undefined !== step_dom && undefined !== step_node_ref) {

                    step_dom.insertBefore(div, step_node_ref);
                    sortable();
                    offer_build_layout();
                    offer_steps_event();
                    $('div.wfocu_step_container a[data-offer_id="' + rsp.id + '"]').trigger('click');
                }
            }
        };
        this.step_change = function () {
            clearTimeout(this.step_change_timeout);

            this.step_change_timeout = setTimeout(
                function () {
                    let offer_id = self.get_offer_id();
                    let data = {"funnel_id": self.get_funnel_id(), "_nonce": wfocuParams.ajax_nonce_save_funnel_steps, "offer_id": offer_id, "steps": self.get_offer_steps()};

                    let wp_ajax = wfo_ajax();
                    wp_ajax.ajax("save_funnel_steps", data);
                }, 300
            );
        };
        this.get_current_index = function () {
            return current_index;
        };
        this.set_current_index = function (index) {
            current_index = index;
            return current_index;
        };
        this.get_offer_id = function () {
            return current_offer_id;
        };
        this.set_offer_id = function (offer_id) {
            if (offer_id > 0) {
                this.offer_product_settings.current_offer_id = offer_id;
                this.offer_setting.current_offer_id = offer_id;
                current_offer_id = offer_id;
                return current_offer_id;
            }
            return 0;
        };
        this.get_selected_product = function () {
            return selected_product;
        };

        this.get_funnel_id = function () {
            return funnel_id;
        };
        this.get_offer_forms = function () {
            return offer_forms;
        };
        this.get_offer_form = function (id) {
            let forms = this.get_offer_forms();
            if (this.hp(forms, id)) {
                return forms[id];
            }
            return get_default_offer_forms();
        };
        this.delete_offer_form = function (offer_id) {
            if (this.hp(offer_forms, offer_id)) {
                delete offer_forms[offer_id];
            }
        };
        this.update_offer_form = function (offer_id, data) {
            if (offer_id > 0 && this.ol(data) > 0) {

                if (this.ol(data) > 0) {
                    offer_forms[offer_id] = data;

                }
            }
        };
        this.get_offer_steps = function () {
            return offer_steps;
        };
        this.get_offer_step = function (id) {
            let forms = this.get_offer_steps();

            return (this.ol(forms) > 0 && this.hp(forms, id)) ? forms[id] : {};
        };
        this.delete_offer_step = function (index_id) {
            if (this.hp(offer_steps, index_id)) {
                delete offer_steps.splice(index_id, 1);

            }
        };
        this.delete_offer_product = function (product_id, callback) {
            if (product_id !== "") {
                let offer_id = self.get_offer_id();
                let field_data = {}, product_data = {};
                if (offer_id > 0 && Object.prototype.hasOwnProperty.call(offer_forms, offer_id)) {
                    product_data = JSON.stringify(offer_forms[offer_id]['products'][product_id]);
                    field_data = JSON.stringify(offer_forms[offer_id]['fields'][product_id]);
                    delete offer_forms[offer_id]['products'][product_id];
                    delete offer_forms[offer_id]['fields'][product_id];

                }
                if (this.isEmpty(offer_forms[offer_id]['products'])) {
                    self.offer_setting.product_count = 0;
                    self.offer_settings_btn_bottom.products = {};
                }

                if (typeof callback === "function") {
                    callback(product_data, field_data);
                }
            }

        };
        this.update_offer_state = function (state) {

            if ('1' == state || true == state) {
                state = '1';
            } else {
                state = '0';
            }
            let offer_id = this.get_offer_id();
            let current_index = this.get_current_index();
            let element = $('.wfocu_step_container[data-offer_id=' + offer_id + ']');
            if (element.length > 0) {
                offer_steps[current_index].state = state;

                element.attr('data-offer_state', state);

                if (state === '0') {
                    element.find('.wfocu_step_offer_state').addClass('state_off');
                    element.find('.wfocu_step_offer_state').attr('title', 'Inactive');
                } else {
                    element.find('.wfocu_step_offer_state').removeClass('state_off');
                    element.find('.wfocu_step_offer_state').attr('title', 'Active');
                }
            }
        };
        this.update_current_step = function (key, value) {

            if (typeof key !== 'undefined' && key !== "undefined" && typeof value !== 'undefined' && value !== 'undefined') {
                let index_id = this.get_current_index();
                let offer_id = this.get_offer_id();
                let steps = this.get_offer_step(index_id);

                if (Object.prototype.hasOwnProperty.call(steps, key)) {
                    steps[key] = value;
                    $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.step_name').text(steps.name);
                    $('.wfocu_step_container[data-offer_id=' + offer_id + ']').attr("data-offer_title", steps.name);
                    if (key === 'type') {
                        $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.wfocu_icon_fixed').removeClass('dashicons-arrow-down');
                        $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.wfocu_icon_fixed').removeClass('dashicons-arrow-up');

                        if (value === 'downsell') {
                            $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.wfocu_icon_fixed').addClass('dashicons-arrow-down');

                        } else {
                            $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.wfocu_icon_fixed').addClass('dashicons-arrow-up');

                        }
                    }
                    $('.wfocu_step_container[data-offer_id=' + offer_id + ']').find('.step_name').text(steps.name);

                    this.offer_product_settings.current_offer = steps.name;
                    this.offer_product_settings.url = steps.url;
                    this.offer_product_settings.current_offer_name = steps.slug;
                    this.offer_product_settings.offer_type = steps.type;

                    this.step_change();
                }
            }
        };
        /**
         * Multiple product added once
         * @param data
         */
        this.add_products = function (data) {
            if (this.ol(data) > 0) {

                let offer_id = this.get_offer_id();
                let offer = this.get_offer_form(offer_id);

                if (this.ol(offer) > 0) {
                    if (this.hp(data, 'products') && this.ol(data['products']) > 0) {
                        let products = data['products'];
                        for (let p in products) {
                            offer['products'][p] = products[p];
                        }
                    }
                    if (this.hp(data, 'fields') && this.ol(data['fields']) > 0) {
                        let fields = data['fields'];
                        for (let p in fields) {
                            offer['fields'][p] = fields[p];
                        }
                    }
                    if (this.hp(data, 'variations') && this.ol(data['variations']) > 0) {
                        let variations = data['variations'];
                        for (let p in variations) {
                            offer['variations'][p] = variations[p];
                        }
                    }
                    if (this.hp(data, 'schemes') && this.ol(data['schemes']) > 0) {
                        let schemes = data['schemes'];
                        for (let p in schemes) {
                            offer['schemes'][p] = schemes[p];
                        }
                    }
                }
                initialize_offer(offer_id);
                this.update_offer_form(offer_id, offer);
            }
        };

        const get_default_offer_forms = function () {
            return {products: {}, fields: {}, settings: {}, variations: {}, schemes: {}, template: "sp-classic", have_multiple_product: 1};
        };


        // Offer step section start here
        const offer_build_layout = function () {

            let sort_el = $(".wfocu_steps_sortable");
            let step_els = sort_el.find('.wfocu_step_container');
            offer_steps = [];
            if (step_els.length > 0) {
                step_els.each(
                    function (i, e) {
                        let id = $(this).data('offer_id');
                        if (typeof id !== 'undefined') {
                            let title = $(this).attr('data-offer_title');
                            let type = $(this).attr('data-offer_type');
                            let state = $(this).attr('data-offer_state');
                            let url = $(this).attr('data-offer_url');
                            let slug = $(this).attr('data-offer-slug');
                            $(this).children('.wfocu_icon_delete').attr('data-index_id', i);
                            $(this).children('.wfocu_step').attr('data-index_id', i);
                            $(this).attr('data-index_id', i);

                            offer_steps.push({'id': id, "name": title, "type": type, 'state': state, "url": url, "slug": slug})

                            if (Object.prototype.hasOwnProperty.call(offer_forms, id) === false) {
                                offer_forms[id] = get_default_offer_forms();
                            }
                        }
                    }
                );
                $(".wfocu_step_add").removeClass('wfocu_hide');
            } else {
                reset_offer();
                $(".wfocu_step_add").addClass('wfocu_hide');
            }
            self.step_change();
        };
        const sortable = function () {

            if ($(design_container).length > 0) {
                return;
            }

            if ($(step_container).length == 0) {
                return;
            }
            $(step_container).off('sortable');
            let container_wrap = $('.wfocu_step_container');
            $(step_container).sortable(
                {
                    items: ".wfocu_step_container:not(.ui-state-disabled)",
                    start: function (event, ui) {
                        ui.item.addClass("wfocu_start_sortable");
                        if (container_wrap.length > 0) {
                            container_wrap.addClass("wfocu_start_drag_on");
                        }
                    },
                    stop: function (event, ui) {
                        ui.item.removeClass("wfocu_start_sortable");
                        if (container_wrap.length > 0) {
                            container_wrap.removeClass("wfocu_start_drag_on");
                        }
                        offer_build_layout();
                    },
                }
            ).disableSelection();

        };
        const offer_steps_event = function () {

            if ($(step_list).length > 0) {
                $(step_list).off('click');
                $(step_list).on(
                    'click', function (e) {
                        e.preventDefault();
                        let index_id = $(this).attr('data-index_id');
                        let current_index = self.get_current_index();

                        if (index_id !== current_index) {
                            $('.wfocu_step').removeClass('current_offer');
                            $(this).addClass('current_offer');
                            let offer_id = $(this).data('offer_id');
                            self.set_current_index(index_id);
                            let step_data = self.get_offer_step(index_id);

                            self.offer_product_settings.current_offer = step_data.name;
                            self.offer_product_settings.current_offer_id = step_data.id;
                            self.offer_product_settings.current_offer_name = step_data.slug;
                            self.offer_product_settings.offer_type = step_data.type;
                            self.offer_product_settings.url = step_data.url;

                            self.set_offer_id(offer_id);
                            initialize_offer(offer_id);
                        }
                    }
                );
            }
        };
        const initialize_offer = function (offer_id) {
            let offer = self.get_offer_form(offer_id);
            if (self.ol(offer, 'products') === 0) {
                self.offer_product_settings.selected_product = '';
                self.offer_setting.product_count = 0;
                return;
            }

            build_offer();
        };

        const prepare_variations = function (i) {
            if (self.hp(self.offer_product_settings.products, i)) {
                let parent_discount = 0;
                let settings = self.offer_product_settings.products[i]['settings'];
                if (self.hp(settings, 'discount_amount')) {
                    parent_discount = parseFloat(settings.discount_amount);
                }

                let variations = self.offer_product_settings.products[i]['variations'];

                var allvari = [];
                for (let v in variations) {


                    if (self.hp(variations[v], 'is_enable') && true === variations[v].is_enable) {
                        allvari.push(v);
                        self.offer_product_settings.selected_variations[v] = true;

                    }
                    if (variations[v].discount_amount === 0) {
                        variations[v].discount_amount = parent_discount;
                    }
                }

                self.offer_product_settings.$set(self.offer_product_settings.allselectedVars, i, allvari);

            }
        };
        const prepare_schemes = function (i) {
            if (self.hp(self.offer_product_settings.products, i)) {
                let parent_discount = 0;
                let settings = self.offer_product_settings.products[i]['settings'];
                if (self.hp(settings, 'discount_amount')) {
                    parent_discount = parseFloat(settings.discount_amount);
                }

                let schemes = self.offer_product_settings.products[i]['schemes'];

                var allvari = [];
                for (let v in schemes) {

                    if (self.hp(schemes[v], 'is_enable') && true === schemes[v].is_enable) {
                        allvari.push(v);
                        self.offer_product_settings.selected_variations[v] = true;

                    }
                    if (schemes[v].discount_amount === 0) {
                        schemes[v].discount_amount = parent_discount;
                    }
                }

                self.offer_product_settings.$set(self.offer_product_settings.allselectedVars, i, allvari);

            }
        };
        const reset_offer = function () {
            // reset offer setting
            self.offer_setting.current_offer_id = 0;
            self.offer_setting.product_count = 0;
            self.offer_setting.model = {
                ship_dynamic: false,
                ask_confirmation: false,
                allow_free_ship_select: false,
                skip_exist: false,
                skip_purchased: false,
                jump_on_accepted: false,
                jump_on_rejected: false,
                jump_to_offer_on_accepted: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                jump_to_offer_on_rejected: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
            };

            // reset offer product setting
            self.offer_product_settings.current_offer_id = 0;
            self.offer_product_settings.current_offer_name = '';
            self.offer_product_settings.offer_type = 'upsell';
            self.offer_product_settings.products = {};
            self.offer_product_settings.current_template = '';

            if (null == self.offer_settings_btn_bottom) {
                return;
            }
            self.offer_settings_btn_bottom.products = {};
            self.offer_settings_btn_bottom.current_offer_id = 0;

        };
        const make_offer = function () {

            reset_offer();
            let offer_id = self.get_offer_id();
            let index_id = self.get_current_index();
            let offer_forms = self.get_offer_form(offer_id);
            let offer_step = self.get_offer_step(index_id);
            if (self.ol(offer_forms) > 0) {
                self.offer_setting.product_count = self.ol(offer_forms.products);
                self.offer_setting.current_offer_id = offer_id;
                self.offer_setting.model = {
                    ship_dynamic: false,
                    ask_confirmation: false,
                    allow_free_ship_select: false,
                    skip_exist: false,
                    skip_purchased: false,
                    jump_on_accepted: false,
                    jump_on_rejected: false,
                    jump_to_offer_on_accepted: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                    jump_to_offer_on_rejected: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                };
                self.offer_product_settings.selected_variations = {};
                self.offer_product_settings.allselectedVars = {};
                if (self.ol(offer_forms.settings) > 0) {
                    for (let k in offer_forms.settings) {
                        let fVal;
                        let st = offer_forms.settings[k];
                        if (st === 'false') {
                            fVal = false;
                        } else if (st === 'true') {
                            fVal = true;
                        } else {
                            fVal = st;
                        }
                        self.offer_setting.model[k] = fVal;
                    }
                }


                if (self.offer_setting.product_count > 0) {
                    for (let i in offer_forms.products) {
                        self.offer_product_settings.products[i] = {};
                        self.offer_product_settings.products[i] = offer_forms.products[i];
                        let vars_count = self.ol(offer_forms.variations[i]);
                        self.offer_product_settings.products[i]['vars_count'] = vars_count;
                        if (vars_count > 0) {
                            self.offer_product_settings.products[i]['settings'] = offer_forms.fields[i];
                            self.offer_product_settings.products[i]['variations'] = offer_forms.variations[i];
                            prepare_variations(i);
                        }

                        if (typeof offer_forms.schemes !== "undefined") {
                            let vars_subs_count = self.ol(offer_forms.schemes[i]);
                            self.offer_product_settings.products[i]['vars_subs_count'] = vars_subs_count;
                            if (vars_subs_count > 0) {
                                self.offer_product_settings.products[i]['settings'] = offer_forms.fields[i];
                                self.offer_product_settings.products[i]['schemes'] = offer_forms.schemes[i];
                                prepare_schemes(i);
                            }
                        }
                    }
                    self.offer_settings_btn_bottom.products = self.offer_product_settings.products;
                }

                self.offer_product_settings.current_offer_id = offer_id;
                self.offer_product_settings.current_offer_name = offer_step.slug;
                self.offer_product_settings.current_offer = offer_step.name;
                self.offer_product_settings.offer_type = offer_step.type;
                self.offer_settings_btn_bottom.current_offer_id = offer_id;

                if ((0 !== offer_id)) {
                    if (offer_step.state == '1') {
                        self.offer_product_settings.offer_state = true;
                    } else {
                        self.offer_product_settings.offer_state = false;
                    }
                }

                self.update_offer_state(offer_step.state);
                return true;
            }
            return false;
        };
        const build_offer = function () {
            let offer_ready = make_offer();
            if (offer_ready) {
                self.offer_product_settings.selected_product = true;

                let step_el = $(".wfocu_s_menu");
                if (self.ol(self.offer_product_settings.products) > 0) {
                    if (step_el.length > 0) {
                        step_el.each(
                            function () {
                                let t_href = $(this).data('href');
                                if (t_href !== "") {
                                    $(this).attr("href", t_href);
                                }
                            }
                        );
                    }
                }
            }

        };
        const offer_settings = function () {
            self.offer_setting = new Vue(
                {
                    el: "#offer_settings",
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    methods: {
                        save_offer() {
                            $('#wfocu_offer_saving_btn').trigger('click');
                        },
                        setting_updated: function () {

                            let tempSetting = JSON.stringify(this.model);
                            tempSetting = JSON.parse(tempSetting);
                            this.setting_changes();
                            let data = {
                                "test": true,
                                "funnel_id": self.get_funnel_id(),
                                "offer_id": self.get_offer_id(),
                                "settings": tempSetting,
                                '_nonce': wfocuParams.ajax_nonce_save_funnel_offer_settings
                            };
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax("save_funnel_offer_settings", data);

                        },
                        setting_changes: function () {

                            let tempSetting = JSON.stringify(this.model);
                            tempSetting = JSON.parse(tempSetting);
                            let offer_id = self.get_offer_id();
                            let offer = self.get_offer_form(offer_id);
                            offer.settings = tempSetting;
                            self.update_offer_form(offer_id, offer);
                        },
                    },

                    updated: function () {

                        window.wfocuBuilderCommons.doAction('wfocu_offer_loaded');
                    },
                    data: {
                        current_offer_id: 0,
                        product_count: 0,
                        model: {
                            ship_dynamic: false,
                            ask_confirmation: false,
                            allow_free_ship_select: false,
                            skip_exist: false,
                            skip_purchased: false,
                            jump_on_accepted: false,
                            jump_on_rejected: false,
                            jump_to_offer_on_accepted: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                            jump_to_offer_on_rejected: wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id,
                        },
                        schema: {
                            fields: window.wfocuBuilderCommons.applyFilters(
                                'wfocu_offer_settings', offer_settings_schema
                            ),
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true
                        },
                    }
                }
            );
        };

        const offer_settings_btn_bottom = function () {
            self.offer_settings_btn_bottom = new Vue(
                {
                    el: "#offer_settings_btn_bottom",
                    created: function () {
                        let index = self.get_current_index();

                        let step = self.get_offer_step(index);

                        if (Object.prototype.hasOwnProperty.call(step, 'name')) {
                            this.current_offer = step.name;
                            this.current_offer_id = step.id;
                            this.products = self.offer_product_settings.products;
                        }
                    },
                    methods: {
                        submit: function () {
                            $('form[data-wfoaction="save_funnel_offer_products"]').trigger('submit');
                        },
                        deleteOfferAjax: function (cb) {
                            let index_id = self.get_current_index();
                            let offer_id = self.get_offer_id();

                            /* When offer tried to remove from ladder */
                            if (typeof offerID != 'undefined') {
                                index_id = $(elem).parents(".wfocu_step_container").attr("data-index_id");
                                offer_id = offerID;
                            }

                            let ladder = $(".wfocu_step_container[data-index_id=" + index_id + "]");
                            if (ladder.length === 0) {
                                return;
                            }
                            ladder.remove();
                            self.delete_offer_form(offer_id);
                            self.delete_offer_step(index_id);

                            self.set_current_index(0);
                            let offer = self.get_offer_step(0);
                            self.set_offer_id(offer.id);
                            let step = self.get_offer_id();
                            $('.wfocu_step').removeClass('current_offer');
                            $('.wfocu_step[data-index_id=0]').addClass('current_offer');

                            for (let offr_ID in wfocu.offers) {
                                if (parseInt(offer_id) === parseInt(wfocu.offers[offr_ID].settings.jump_to_offer_on_accepted)) {
                                    wfocu.offers[offr_ID].settings.jump_to_offer_on_accepted = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
                                    wfocu.offers[offr_ID].settings.jump_on_accepted = false;
                                }
                                if (parseInt(offer_id) === parseInt(wfocu.offers[offr_ID].settings.jump_to_offer_on_rejected)) {
                                    wfocu.offers[offr_ID].settings.jump_to_offer_on_rejected = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
                                    wfocu.offers[offr_ID].settings.jump_on_rejected = false;
                                }
                            }

                            initialize_offer(step);
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax("remove_offer_from_funnel", {'offer_id': offer_id, 'funnel_id': self.get_funnel_id(), '_nonce': wfocuParams.ajax_nonce_remove_offer_from_funnel});
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    offer_build_layout();
                                }
                                if (typeof cb == "function") {
                                    cb(rsp);
                                }
                            };
                        },
                        delete_offer: function (elem, offerID) {
                            wfocuSweetalert2(
                                $.extend(
                                    {
                                        title: "",
                                        text: "",
                                        icon: "",
                                        showCancelButton: true,
                                        confirmButtonColor: '#0073aa',
                                        cancelButtonColor: '#e33b3b',
                                        confirmButtonText: '',
                                        reverseButtons: true,
                                        showCloseButton: true,
                                        onOpen: () => {
                                            const swalModalTitle = document.querySelector('.wfocuswal-header');
                                            swalModalTitle.insertAdjacentHTML('beforebegin', '<div class="wffn-swal-modal-title">' + wfocuParams.alerts.delete_offer.modal_title + '</div>');
                                        },
                                    }, {
                                        html: `<div class="wf_delete_modal_content">${wfocuParams.alerts.delete_offer.text}</div>`,
                                        confirmButtonText: wfocuParams.alerts.delete_offer.confirmButtonText,
                                        preConfirm: () => {
                                            $('button.wfocuswal-cancel.wfocuswal-styled').removeAttr('disabled');
                                            return new Promise((resolve) => {
                                                this.deleteOfferAjax((rsp) => {
                                                    resolve(rsp);
                                                }, elem, offerID);
                                            });
                                        },
                                    }
                                )
                            ).catch(
                                (e) => {
                                    console.log("Remove offer from list error", e);
                                }
                            );

                        },
                        isEmpty: function isEmpty(obj) {
                            for (let key in obj) {
                                if (Object.prototype.hasOwnProperty.call(obj, key)) {
                                    return false;
                                }
                            }
                            return true;
                        }
                    },
                    data: {
                        current_offer: '',
                        current_offer_id: 0,
                        products: {},
                    }
                }
            );
        };
        const offer_product_settings = function () {
            self.offer_product_settings = new Vue(
                {
                    el: "#wfocu_offer_area",
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    methods: {
                        prepare_price_help_html: function (product) {
                            let discount_str = '';
                            let shipping_cost_str = '';
                            let discount = 0;
                            let total_str = '';
                            let finalStr = '';
                            let init_price_str = '';
                            let total = 0;

                            if ('percentage_on_sale' === product.discount_type || 'fixed_on_sale' === product.discount_type) {
                                init_price_str = this.formatMoney(product.price_raw) + " x" + product.quantity;
                                total = [parseFloat(product.price_raw * product.quantity)];
                            } else {
                                init_price_str = this.formatMoney(product.regular_price_raw) + " x" + product.quantity;
                                total = [parseFloat(product.regular_price_raw * product.quantity)];
                            }

                            switch (product.discount_type) {
                                case "percentage_on_sale":
                                    discount = (parseFloat(product.price_raw * product.quantity) * (product.discount_amount / 100));
                                    discount_str = this.formatMoney(discount) + ' (' + product.discount_amount + '% ' + wfocu.price_tooltip_texts.of + ' ' + this.formatMoney(product.price_raw * product.quantity) + ')';
                                    break;
                                case "percentage_on_reg":
                                    discount = (parseFloat(product.regular_price_raw * product.quantity) * (product.discount_amount / 100));
                                    discount_str = this.formatMoney(discount) + ' (' + product.discount_amount + '% ' + wfocu.price_tooltip_texts.of + ' ' + this.formatMoney(product.regular_price_raw * product.quantity) + ')';
                                    break;
                                case "fixed_on_sale":
                                case "fixed_on_reg":
                                    discount = parseFloat(product.discount_amount * product.quantity);
                                    discount_str = this.formatMoney(discount) + ' ' + wfocu.price_tooltip_texts.fixed_amount;
                                    break;
                            }

                            if (false === self.offer_setting.model.ship_dynamic) {
                                total.push(parseFloat(product.shipping_cost_flat));
                                shipping_cost_str = this.formatMoney(product.shipping_cost_flat) + ' ' + wfocu.price_tooltip_texts.shipping;
                            } else {
                                shipping_cost_str = ' ' + wfocu.price_tooltip_texts.dynamic_ship;
                            }

                            total_str = this.formatMoney(this.sum(total) - discount);
                            finalStr = [init_price_str, shipping_cost_str].join(' + ') + ' - ' + discount_str + ' = ' + total_str;

                            return finalStr;
                        },
                        update_offer_price: function (e, index) {
                            let product = this.products[index];
                            if (product.type === 'variable' || product.type === 'variable-subscription') {
                                for (var key in product.variations) {
                                    $('.wfocu_of_price_var_' + index + '_' + key).html(this.offer_price_html_var(product.variations[key], product));
                                    $('.wfocu_of_price_data_var_' + index + '_' + key).html(this.offer_price_tooltip_var(product.variations[key], product));
                                }
                            } else {
                                let gethtml = this.offer_price_html(product);
                                $('.wfocu_of_price_' + index).html(gethtml);
                                $('.wfocu_of_price_data_' + index).html(this.prepare_price_help_html(product));
                            }

                            if (typeof product.schemes !== "undefined") {
                                for (var skey in product.schemes) {
                                    $('.wfocu_of_price_subs_' + index + '_' + skey).html(this.offer_price_html_subs(product.schemes[skey], product));
                                }

                            }
                        },
                        offer_price_html_subs: function (scheme, product) {
                            let new_sale_price = parseFloat(scheme.org_sale_price * product.quantity);
                            let new_reg_price = parseFloat(scheme.org_regular_price * product.quantity);
                            switch (product.discount_type) {
                                case "percentage_on_sale":
                                    new_sale_price = new_sale_price - (new_sale_price * (scheme.discount_amount / 100));
                                    break;
                                case "percentage_on_reg":
                                    new_reg_price = new_reg_price - (new_reg_price * (scheme.discount_amount / 100));
                                    break;

                                case "fixed_on_sale":
                                    new_sale_price = (parseFloat(new_sale_price) - (parseFloat(scheme.discount_amount) * product.quantity));
                                    break;

                                case "fixed_on_reg":
                                    new_reg_price = (parseFloat(new_reg_price) - (parseFloat(scheme.discount_amount) * product.quantity));
                                    break;
                            }
                            if (product.shipping_cost_flat > 0 && product.shipping_cost_flat !== '') {
                                new_sale_price = parseFloat(new_sale_price) + parseFloat(product.shipping_cost_flat);
                                new_reg_price = parseFloat(new_reg_price) + parseFloat(product.shipping_cost_flat);
                            }
                            let subs_html = $('<div="subs_html"><span class="subs_update"></span>' + scheme.description + '</div>');
                            if (new_reg_price > new_sale_price) {
                                subs_html.find('.reg_txt').replaceWith('<del aria-hidden="true"><span class="woocommerce-Price-amount amount"><bdi><span class="subs_reg">' + this.formatMoney(new_reg_price) + '</span></bdi></span></del>');

                            }

                            subs_html.find('ins').replaceWith('');
                            subs_html.find('del').replaceWith('');

                            let price_html = '';
                            if (new_sale_price <= 0 || new_sale_price >= new_reg_price) {
                                subs_html.find('.reg_txt').replaceWith('<del aria-hidden="true"><span class="woocommerce-Price-amount amount"><bdi><span class="subs_reg">' + this.formatMoney(new_reg_price) + '</span></bdi></span></del>');
                                price_html = '<ins><span class="woocommerce-Price-amount amount"><bdi>' + this.formatMoney(new_reg_price) + '</bdi></span></ins>';
                            } else {
                                price_html = '<del aria-hidden="true"><span class="woocommerce-Price-amount amount"><bdi>' + this.formatMoney(new_reg_price) + '</bdi></span></del> <ins><span class="woocommerce-Price-amount amount"><bdi>' + this.formatMoney(new_sale_price) + '</bdi></span></ins>';

                            }
                            subs_html.find('.subs_update').replaceWith(price_html);
                            return subs_html.prop('innerHTML');
                        },
                        offer_price_html: function (product) {

                            let discount = 0;
                            let total_str = '';
                            let total = 0;
                            if ('percentage_on_sale' === product.discount_type || 'fixed_on_sale' === product.discount_type) {
                                total = [parseFloat(product.price_raw * product.quantity)];
                            } else {
                                total = [parseFloat(product.regular_price_raw * product.quantity)];
                            }
                            switch (product.discount_type) {
                                case "percentage_on_sale":
                                    discount = (parseFloat(product.price_raw * product.quantity) * (product.discount_amount / 100));
                                    break;
                                case "percentage_on_reg":

                                    discount = (parseFloat(product.regular_price_raw * product.quantity) * (product.discount_amount / 100));

                                    break;
                                case "fixed_on_sale":
                                case "fixed_on_reg":
                                    discount = parseFloat(product.discount_amount * product.quantity);
                                    break;
                            }

                            if (false === self.offer_setting.model.ship_dynamic) {
                                total.push(parseFloat(product.shipping_cost_flat));
                            }

                            total_str = this.formatMoney(this.sum(total) - discount);

                            return total_str;
                        },
                        offer_price_html_var: function (variation, product) {
                            let discount = 0;
                            let total_str = '';
                            let total = [parseFloat(variation.regular_price_raw * product.quantity)];

                            if ('percentage_on_sale' === product.discount_type || 'fixed_on_sale' === product.discount_type) {
                                total = [parseFloat(variation.price_raw * product.quantity)];
                            } else {
                                total = [parseFloat(variation.regular_price_raw * product.quantity)];
                            }

                            switch (product.discount_type) {
                                case "percentage_on_sale":
                                    discount = (parseFloat(variation.price_raw * product.quantity) * (variation.discount_amount / 100));
                                    break;
                                case "percentage_on_reg":

                                    discount = (parseFloat(variation.regular_price_raw * product.quantity) * (variation.discount_amount / 100));

                                    break;

                                case "fixed_on_sale":
                                case "fixed_on_reg":
                                    discount = parseFloat(variation.discount_amount * product.quantity);
                                    break;
                            }

                            if (false === self.offer_setting.model.ship_dynamic) {
                                total.push(parseFloat(product.shipping_cost_flat));
                            }
                            let finalPrice = this.sum(total) - discount;
                            if (finalPrice < 0) {
                                finalPrice = 0;
                            }
                            total_str = '<strong>' + this.formatMoney(finalPrice) + '</strong>';

                            return total_str;
                        },
                        offer_price_tooltip_var: function (variation, product) {

                            let discount_str = '';
                            let shipping_cost_str = '';
                            let discount = 0;
                            let total_str = '';
                            let finalStr = '';
                            let init_price_str = '';
                            let total = 0;

                            if ('percentage_on_sale' === product.discount_type || 'fixed_on_sale' === product.discount_type) {
                                init_price_str = this.formatMoney(variation.price_raw) + " x" + product.quantity;
                                total = [parseFloat(variation.price_raw * product.quantity)];
                            } else {
                                init_price_str = this.formatMoney(variation.regular_price_raw) + " x" + product.quantity;
                                total = [parseFloat(variation.regular_price_raw * product.quantity)];
                            }

                            switch (product.discount_type) {
                                case "percentage_on_sale":
                                    discount = (parseFloat(variation.price_raw * product.quantity) * (variation.discount_amount / 100));
                                    discount_str = this.formatMoney(discount) + ' (' + variation.discount_amount + '% ' + wfocu.price_tooltip_texts.of + this.formatMoney(variation.price_raw * product.quantity) + ')';

                                case "percentage_on_reg":
                                    discount = (parseFloat(variation.regular_price_raw * product.quantity) * (variation.discount_amount / 100));
                                    discount_str = this.formatMoney(discount) + ' (' + variation.discount_amount + '% ' + wfocu.price_tooltip_texts.of + this.formatMoney(variation.regular_price_raw * product.quantity) + ')';

                                    break;
                                case "fixed_on_sale":
                                case "fixed_on_reg":
                                    discount = parseFloat(variation.discount_amount * product.quantity);
                                    discount_str = this.formatMoney(discount) + ' ' + wfocu.price_tooltip_texts.fixed_amount;

                                    break;
                            }

                            if (false === self.offer_setting.model.ship_dynamic) {
                                total.push(parseFloat(product.shipping_cost_flat));
                                shipping_cost_str = this.formatMoney(product.shipping_cost_flat) + ' ' + wfocu.price_tooltip_texts.shipping;
                            } else {
                                shipping_cost_str = ' ' + wfocu.price_tooltip_texts.dynamic_ship;
                            }
                            let finalPrice = this.sum(total) - discount;
                            if (finalPrice < 0) {
                                finalPrice = 0;
                            }
                            total_str = '<strong>' + this.formatMoney(finalPrice ) + '</strong>';
                            finalStr = [init_price_str, shipping_cost_str].join(' + ') + ' - ' + discount_str + ' = ' + total_str;

                            return finalStr;
                        },
                        sum: function (thing) {
                            return thing.reduce(
                                function (a, b) {
                                    return a + b;
                                }, 0
                            );
                        },
                        formatMoney: function (amt) {

                            return window.accounting.formatMoney(
                                amt, {
                                    symbol: window.wfocu_wc_params.currency_format_symbol,
                                    decimal: window.wfocu_wc_params.currency_format_decimal_sep,
                                    thousand: window.wfocu_wc_params.currency_format_thousand_sep,
                                    precision: window.wfocu_wc_params.currency_format_num_decimals,
                                    format: window.wfocu_wc_params.currency_format
                                }
                            )
                        },
                        prettyJSON: function (json) {
                            if (json) {
                                json = JSON.stringify(json, undefined, 4);
                                json = json.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
                                return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                                    var cls = 'number';
                                    if (/^"/.test(match)) {
                                        if (/:$/.test(match)) {
                                            cls = 'key';
                                        } else {
                                            cls = 'string';
                                        }
                                    } else if (/true|false/.test(match)) {
                                        cls = 'boolean';
                                    } else if (/null/.test(match)) {
                                        cls = 'null';
                                    }
                                    return '<span class="' + cls + '">' + match + '</span>';
                                });
                            }
                        },
                        set_product: function () {
                            build_offer();
                        },

                        product_changes: function () {
                            let offer_id = self.get_offer_id();
                            let offer = self.get_offer_form(offer_id);
                            offer.state = this.offer_state;
                            self.update_offer_state(offer.state);
                            self.update_offer_form(offer_id, offer);
                        },
                        update_offer_state: function (event) {

                            let offer_id = self.get_offer_id();
                            let offer = self.get_offer_form(offer_id);
                            if (event.target.checked) {
                                offer.state = '1';
                            } else {
                                offer.state = 0;
                            }
                            self.update_offer_state(offer.state);
                            self.update_offer_form(offer_id, offer);
                        },
                        removeProduct: function (cb, unique_id, vthis) {
                            let removeProduct = document.getElementById(unique_id);
                            let product_id = removeProduct.getAttribute('data-proid');

                            if (typeof product_id === 'undefined' || product_id === "") {
                                return;
                            }

                            if (!self.hp(vthis.products, product_id)) {
                                return;
                            }
                            let wp_ajax = wfo_ajax();
                            let add_query = {
                                'funnel_id': self.get_funnel_id(),
                                'offer_id': self.get_offer_id(),
                                'product_key': product_id,
                                '_nonce': wfocuParams.ajax_nonce_remove_product
                            };
                            wp_ajax.ajax('remove_product', add_query);
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                delete vthis.products[product_id];
                                let keys = self.kys(vthis.products);
                                if (keys.length > 0) {
                                    let index = keys[0];
                                    setTimeout(
                                        function (index) {
                                            vthis.set_product(index);
                                        }, 100, index
                                    )
                                } else {

                                    vthis.selected_product = 0;
                                }
                                self.delete_offer_product(product_id);
                                cb(rsp);
                            }
                        },
                        remove_product: function (unique_id) {
                            let vthis = this;
                            wfocuSweetalert2(
                                $.extend(
                                    {
                                        title: '',
                                        text: "",
                                        type: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#0073aa',
                                        cancelButtonColor: '#e33b3b',
                                        confirmButtonText: '',
                                        reverseButtons: true,
                                        showCloseButton: true,
                                        onOpen: () => {
                                            const swalModalTitle = document.querySelector('.wfocuswal-header');
                                            swalModalTitle.insertAdjacentHTML('beforebegin', '<div class="wffn-swal-modal-title">' + wfocuParams.alerts.remove_product.modal_title + '</div>');
                                        },
                                        preConfirm: function () {
                                            $('button.wfocuswal-cancel.wfocuswal-styled').removeAttr('disabled');
                                            return new Promise((resolve) => {
                                                vthis.removeProduct((rsp) => {
                                                    resolve(rsp);
                                                }, unique_id, vthis);
                                            });
                                        }
                                    }, {
                                        html: `<div class="wf_delete_modal_content">${wfocuParams.alerts.remove_product.text}</div>`,
                                        confirmButtonText: wfocuParams.alerts.remove_product.confirmButtonText,

                                    }
                                )
                            ).catch((e) => {
                                console.log("Remove product error", e);
                            });
                        },
                        set_variation_discount: function (event, index) {

                            if (typeof this.products[index].variations !== "undefined" && false === this.isEmpty(this.products[index].variations)) {

                                for (var varID in this.products[index].variations) {

                                    this.products[index].variations[varID].discount_amount = this.products[index].discount_amount;

                                }
                                this.hidden_v = Math.random();
                            }
                            if (typeof this.products[index].schemes !== "undefined" && false === this.isEmpty(this.products[index].schemes)) {

                                for (var varID in this.products[index].schemes) {

                                    this.products[index].schemes[varID].discount_amount = this.products[index].discount_amount;

                                }
                                this.hidden_v = Math.random();
                            }

                        },
                        disable_enable_variation_row: function (index, event, var_index) {
                            if ($(event.target).length > 0) {
                                let parent = $(event.target).parents(".variation_products");
                                if (event.target && event.target.checked) {
                                    let variations_length = parent.find(".variation_check").length;
                                    let checked_length = parent.find(".variation_check:checked").length;
                                    if (variations_length > 0 && (variations_length === checked_length)) {
                                        parent.find(".disable_enable_variation").prop("checked", true);
                                    }
                                    parent.find("[data-variation=" + var_index + "]").not(".variation_check").prop("readonly", false);
                                    parent.find(".default_variation[data-variation=" + var_index + "]").not(".variation_check").prop("disabled", false);

                                    let have_checke_variation = parent.find('.default_variation:checked');

                                    if (have_checke_variation.length === 0) {
                                        parent.find(".default_variation[data-variation=" + var_index + "]").prop("checked", true);
                                    }

                                    this.selected_variations[var_index] = true;
                                } else {
                                    parent.find(".disable_enable_variation").prop("checked", false);

                                    parent.find("[data-variation=" + var_index + "]").not(".variation_check").prop("readonly", true);
                                    parent.find(".default_variation[data-variation=" + var_index + "]").prop("checked", false);
                                    parent.find(".default_variation[data-variation=" + var_index + "]").prop("disabled", true);

                                    let have_default_variation = parent.find('.default_variation:checked');

                                    if (have_default_variation.length === 0) {
                                        let have_checke_variation = parent.find('.variation_check:checked');
                                        if (have_checke_variation.length > 0) {
                                            have_checke_variation.eq(0).parents(".product_variation_row").find(".default_variation").prop("checked", true);
                                        }
                                    }

                                    delete this.selected_variations[var_index];
                                }


                            }

                            this.resetSelectedVariation(index);
                        },

                        resetSelectedVariation: function (var_index) {
                            this.$set(self.offer_product_settings.allselectedVars, var_index, []);
                            var allselected = [];
                            $(".variation_products[data-index='" + var_index + "'] .product_variation_row .variation_check").each(function () {
                                if (true === this.checked) {
                                    allselected.push($(this).attr('data-variation'));
                                }
                            });
                            this.$set(self.offer_product_settings.allselectedVars, var_index, allselected);

                        },
                        disable_enable_variation: function (index, event) {

                            if ($(event.target).length > 0) {
                                let parent = $(event.target).parents(".variation_products");
                                if (event.target && event.target.checked) {
                                    let checked_length = parent.find(".default_variation:checked").length;
                                    if (checked_length === 0) {
                                        parent.find(".default_variation").eq(0).prop("checked", true);
                                    }
                                    parent.find(".default_variation").prop('disabled', false);
                                    parent.find(".variation_check").prop('checked', true);
                                    parent.find("[data-variation]").not(".variation_check").prop('readonly', false);

                                } else {
                                    parent.find(".default_variation").prop("checked", false).prop('disabled', true);
                                    parent.find(".variation_check").prop('checked', false);
                                    parent.find("[data-variation]").not(".variation_check").prop('readonly', true);

                                }
                            }


                            this.resetSelectedVariation(index);

                        },

                        disable_enable_scheme_row: function (index, event, var_index) {
                            if ($(event.target).length > 0) {
                                let parent = $(event.target).parents(".scheme_products");
                                if (event.target && event.target.checked) {
                                    let schemes_length = parent.find(".scheme_check").length;
                                    let checked_length = parent.find(".scheme_check:checked").length;
                                    if (schemes_length > 0 && (schemes_length === checked_length)) {
                                        parent.find(".disable_enable_scheme").prop("checked", true);
                                    }
                                    parent.find("[data-scheme=" + var_index + "]").not(".scheme_check").prop("readonly", false);
                                    parent.find(".default_scheme[data-scheme=" + var_index + "]").not(".scheme_check").prop("disabled", false);

                                    let have_checke_scheme = parent.find('.default_scheme:checked');

                                    if (have_checke_scheme.length === 0) {
                                        parent.find(".default_scheme[data-scheme=" + var_index + "]").prop("checked", true);
                                    }

                                    this.selected_schemes[var_index] = true;
                                } else {
                                    parent.find(".disable_enable_scheme").prop("checked", false);

                                    parent.find("[data-scheme=" + var_index + "]").not(".scheme_check").prop("readonly", true);
                                    parent.find(".default_scheme[data-scheme=" + var_index + "]").prop("checked", false);
                                    parent.find(".default_scheme[data-scheme=" + var_index + "]").prop("disabled", true);

                                    let have_default_scheme = parent.find('.default_scheme:checked');

                                    if (have_default_scheme.length === 0) {
                                        let have_checke_scheme = parent.find('.scheme_check:checked');
                                        if (have_checke_scheme.length > 0) {
                                            have_checke_scheme.eq(0).parents(".product_scheme_row").find(".default_scheme").prop("checked", true);
                                        }
                                    }

                                    delete this.selected_schemes[var_index];
                                }


                            }

                            this.resetSelectedScheme(index);
                        },
                        resetSelectedScheme: function (var_index) {
                            this.$set(self.offer_product_settings.allselectedVars, var_index, []);
                            var allselected = [];
                            $(".scheme_products[data-index='" + var_index + "'] .product_scheme_row .scheme_check").each(function () {
                                if (true === this.checked) {
                                    allselected.push($(this).attr('data-scheme'));
                                }
                            });
                            this.$set(self.offer_product_settings.allselectedVars, var_index, allselected);

                        },
                        disable_enable_scheme: function (index, event) {

                            if ($(event.target).length > 0) {
                                let parent = $(event.target).parents(".scheme_products");
                                if (event.target && event.target.checked) {
                                    let checked_length = parent.find(".default_scheme:checked").length;
                                    if (checked_length === 0) {
                                        parent.find(".default_scheme").eq(0).prop("checked", true);
                                    }
                                    parent.find(".default_scheme").prop('disabled', false);
                                    parent.find(".scheme_check").prop('checked', true);
                                    parent.find("[data-scheme]").not(".scheme_check").prop('readonly', false);

                                } else {
                                    parent.find(".default_scheme").prop("checked", false).prop('disabled', true);
                                    parent.find(".scheme_check").prop('checked', false);
                                    parent.find("[data-scheme]").not(".scheme_check").prop('readonly', true);

                                }
                            }


                            this.resetSelectedScheme(index);

                        },
                        isEmpty: function isEmpty(obj) {
                            for (let key in obj) {
                                if (Object.prototype.hasOwnProperty.call(obj, key)) {
                                    return false;
                                }
                            }
                            return true;
                        },
                        deleteOfferAjax: function (cb, elem, offerID) {
                            let index_id = self.get_current_index();
                            let offer_id = self.get_offer_id();

                            /* When offer tried to remove from ladder */
                            if (typeof offerID != 'undefined') {
                                index_id = $(elem).parents(".wfocu_step_container").attr("data-index_id");
                                offer_id = offerID;
                            }

                            let ladder = $(".wfocu_step_container[data-index_id=" + index_id + "]");
                            if (ladder.length === 0) {
                                return;
                            }
                            ladder.remove();
                            self.delete_offer_form(offer_id);
                            self.delete_offer_step(index_id);

                            self.set_current_index(0);
                            let offer = self.get_offer_step(0);
                            self.set_offer_id(offer.id);
                            let step = self.get_offer_id();
                            $('.wfocu_step').removeClass('current_offer');
                            $('.wfocu_step[data-index_id=0]').addClass('current_offer');

                            for (let offr_ID in wfocu.offers) {
                                if (parseInt(offer_id) === parseInt(wfocu.offers[offr_ID].settings.jump_to_offer_on_accepted)) {
                                    wfocu.offers[offr_ID].settings.jump_to_offer_on_accepted = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
                                    wfocu.offers[offr_ID].settings.jump_on_accepted = false;
                                }
                                if (parseInt(offer_id) === parseInt(wfocu.offers[offr_ID].settings.jump_to_offer_on_rejected)) {
                                    wfocu.offers[offr_ID].settings.jump_to_offer_on_rejected = wfocuParams.forms_labels.offer_settings.jump_to_offer_default_option.id;
                                    wfocu.offers[offr_ID].settings.jump_on_rejected = false;
                                }
                            }

                            initialize_offer(step);
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax("remove_offer_from_funnel", {'offer_id': offer_id, 'funnel_id': self.get_funnel_id(), '_nonce': wfocuParams.ajax_nonce_remove_offer_from_funnel});
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    offer_build_layout();
                                }
                                if (typeof cb == "function") {
                                    cb(rsp);
                                }
                            };
                        },
                        delete_offer: function (elem, offerID) {
                            wfocuSweetalert2(
                                $.extend(
                                    {
                                        title: "",
                                        text: "",
                                        showCancelButton: true,
                                        cancelButtonColor: '#e33b3b',
                                        confirmButtonColor: '#0073aa',
                                        confirmButtonText: '',
                                        reverseButtons: true,
                                        showCloseButton: true,
                                        onOpen: () => {
                                            const swalModalTitle = document.querySelector('.wfocuswal-header');
                                            swalModalTitle.insertAdjacentHTML('beforebegin', '<div class="wffn-swal-modal-title">' + wfocuParams.alerts.remove_offer.modal_title + '</div>');
                                        },
                                    }, {
                                        html: `<div class="wf_delete_modal_content">${wfocuParams.alerts.remove_offer.text}</div>`,
                                        confirmButtonText: wfocuParams.alerts.remove_offer.confirmButtonText,
                                        preConfirm: () => {
                                            $('button.wfocuswal-cancel.wfocuswal-styled').removeAttr('disabled');
                                            return new Promise((resolve) => {
                                                this.deleteOfferAjax((rsp) => {
                                                    resolve(rsp);
                                                }, elem, offerID);
                                            });
                                        },
                                    }
                                )
                            ).catch(
                                (e) => {
                                    console.log("Remove offer from list error", e);
                                }
                            );

                        },
                        numberofproducts: function () {
                            return self.ol(this.products);
                        },
                    },
                    data: {
                        modal: false,
                        current_offer: '',
                        current_offer_id: 0,
                        current_offer_name: '',
                        offer_type: '',
                        products: {},
                        custom: {},
                        variations: {},
                        selected_offer_options: "",
                        selected_product: 0,
                        allselectedVars: {},
                        selected_variations: {},
                        offer_state: default_offer_state,
                        current_template: "",
                        is_multi_product_allow: false,
                        url: "",
                        hidden_v: 0,
                        schemes: {},
                        selected_schemes: {},
                    },

                }
            );
            let index = self.get_current_index();
            let step = self.get_offer_step(index);
            self.offer_product_settings.is_multi_product_allow = wfocu.is_multiple_product_search;

            if (Object.prototype.hasOwnProperty.call(step, 'name')) {
                self.offer_product_settings.current_offer = step.name;
                self.offer_product_settings.url = step.url;
                self.offer_product_settings.current_offer_name = step.slug;
                self.offer_product_settings.offer_type = step.type;

                $('.wfocu_step[data-index_id=' + index + ']').addClass('current_offer');
            }
        };
        const add_new_step_reset = function () {
            if (self.add_new_offer_setting !== null) {

                self.add_new_offer_setting.model.step_type = "upsell";
                self.add_new_offer_setting.model.funnel_step_name = "";
                self.add_new_offer_setting.$refs.addOfferForm.validate();
            }
        }
        const add_new_step = function () {
            if (add_new_step_is_open === true) {
                return;
            }
            add_new_step_is_open = true;

            self.add_new_offer_setting = new Vue(
                {
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    data: {

                        model: {
                            step_type: "upsell",
                            show_select_btn: false
                        },
                        schema: {
                            fields: add_new_offer_setting_fields,
                        },
                        formOptions: {
                            validateAfterLoad: true,
                            validateAfterChanged: true
                        }
                    }
                }
            ).$mount('#part3');

            if (self.ol(self.get_offer_forms()) > 0) {
                self.add_new_offer_setting.model.show_select_btn = true;
            }

        };

        const update_setting_populate = function () {
            if (typeof self.update_step_setting !== 'undefined') {

                self.update_step_setting.model.funnel_step_name = self.offer_product_settings.current_offer;
                self.update_step_setting.model.step_type = self.offer_product_settings.offer_type;
                self.update_step_setting.model.current_offer = self.offer_product_settings.current_offer_id;
                self.update_step_setting.model.funnel_step_slug = self.offer_product_settings.current_offer_name;

            }
        }
        const update_step = function () {

            if (update_step_is_open === true) {
                return;
            }
            update_step_is_open = true;

            self.update_step_setting = new Vue(
                {

                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    data: {

                        // modal: modal,
                        model: {
                            funnel_step_name: self.offer_product_settings.current_offer,
                            step_type: self.offer_product_settings.offer_type,
                            current_offer: self.offer_product_settings.current_offer_id,
                            url: '',
                            funnel_step_slug: self.offer_product_settings.current_offer_name,
                        },
                        schema: {
                            fields: update_step_settings,
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true,
                            validateBeforeSubmit: true
                        }
                    }
                }
            ).$mount('#part4');


        };
        /**
         * Product search model
         * @param modal
         */
        const product_search_setting = function (modal) {
            if (product_search_is_open === true) {
                return;
            }
            product_search_is_open = true;
            self.product_search_setting = new Vue(
                {
                    el: '#modal-add-product-form',
                    components: {
                        Multiselect: window.VueMultiselect.default
                    },
                    data: {
                        modal: modal,
                        isLoading: false,
                        funnel_id: self.get_funnel_id(),
                        products: [],
                        is_single: '',
                        include_variations: false,
                        selectedProducts: []
                    },
                    methods: {
                        onSubmit: function () {
                            let wp_ajax = wfo_ajax();
                            let vthis = this;

                            if (modal.$wrap) {
                                let submitButton = modal.$wrap.find('.wfocu_btn_primary.wfocu_btn');
                                $(submitButton).addClass('is_busy');
                            }

                            let selected_products = [];
                            let products = this.selectedProducts;
                            if (products.length < 1) {
                                if (modal.$wrap) {
                                    let submitButton = modal.$wrap.find('.wfocu_btn_primary.wfocu_btn');
                                    $(submitButton).removeClass('is_busy');
                                }
                                $('#product_search').find('.multiselect').addClass('wfocu-error');
                                $("#modal-add-product").iziModal('close');
                                return;
                            }


                            if (wfocu.is_multiple_product_search === true) {
                                if (self.ol(this.selectedProducts) > 0) {
                                    for (let pid in products) {
                                        selected_products.push(products[pid]["id"]);
                                    }
                                }
                            } else {
                                selected_products.push(products.id);
                            }
                            let add_query = {'funnel_id': this.funnel_id, 'offer_id': self.get_offer_id(), 'products': selected_products, '_nonce': wfocuParams.ajax_nonce_wfocu_add_product};
                            wp_ajax.ajax('add_product', add_query);
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (self.ol(rsp) > 0 && self.hp(rsp, 'data')) {
                                    self.add_products(rsp.data);
                                }
                                $("#modal-add-product").iziModal('close');
                            };
                            wp_ajax.complete = function () {
                                vthis.clearAll();

                                if (modal.$wrap) {
                                    let submitButton = modal.$wrap.find('.wfocu_btn_primary.wfocu_btn');
                                    $(submitButton).removeClass('is_busy');
                                }
                            };
                        },
                        asyncFind(query) {
                            let vthis = this;
                            vthis.isLoading = true
                            if (query !== "") {
                                clearTimeout(product_search_timeout);
                                product_search_timeout = setTimeout(
                                    function (query, vthis) {
                                        let wp_ajax = wfo_ajax();
                                        let product_query = {'term': query, 'variations': vthis.include_variations, '_nonce': wfocuParams.ajax_nonce_product_search};
                                        wp_ajax.ajax('product_search', product_query);
                                        wp_ajax.success = function (rsp) {
                                            if (typeof rsp === "string") {
                                                rsp = JSON.parse(rsp);
                                            }
                                            vthis.products = rsp;
                                            vthis.isLoading = false;
                                        };
                                        wp_ajax.complete = function () {
                                            vthis.isLoading = false;
                                        };
                                    }, 1000, query, vthis
                                );
                            } else {
                                vthis.isLoading = false;
                            }
                        },
                        clearAll() {
                            this.products = [];
                            this.selectedProducts = [];
                            this.isLoading = false;

                        }
                    }
                }
            );
        };
        /**
         * Page search model
         * @param modal
         */
        const page_search_setting = function (modal) {
            if (page_search_is_open === true) {
                return;
            }
            page_search_is_open = true;
            self.page_search_setting = new Vue(
                {
                    el: '#modal-page-search-form',
                    components: {
                        Multiselect: window.VueMultiselect.default
                    },
                    data: {
                        modal: modal,
                        isLoading: false,
                        funnel_id: self.get_funnel_id(),
                        page_id: 0,
                        products: [],
                        is_single: '',
                        include_variations: false,
                        selectedProducts: []
                    },
                    methods: {
                        onSubmit: function () {
                            let wp_ajax = wfo_ajax();
                            let vthis = this;
                            let selected_page = '0';
                            let products = this.selectedProducts;
                            if (Object.prototype.hasOwnProperty.call(products, 'id') && Object.prototype.hasOwnProperty.call(products, 'page_name')) {
                                selected_page = products.id;
                                self.design_settings.custom_url = products.url;
                                let offer_id = self.get_offer_id();
                                let add_query = {'funnel_id': this.funnel_id, offer_id: offer_id, 'page_id': selected_page, '_nonce': wfocuParams.ajax_nonce_get_custom_page};
                                wp_ajax.ajax('get_custom_page', add_query);
                                wp_ajax.success = function (rsp) {
                                    if (typeof rsp === "string") {
                                        rsp = JSON.parse(rsp);
                                    }
                                    wfocu.offers[offer_id].template_custom_path = rsp.data.link;
                                    wfocu.offers[offer_id].template_custom_name = rsp.data.title;
                                    wfocu.offers[offer_id].template_custom_id = rsp.data.id;

                                    self.design_settings.set_template('custom-page');

                                    self.design_settings.currentTemplateName = rsp.data.title;
                                    self.design_settings.currentTemplatePath = rsp.data.link;

                                    $("#modal-prev-template_custom-page").iziModal('close');
                                };
                                wp_ajax.complete = function () {
                                    vthis.clearAll();
                                    vthis.modal.stopLoading();
                                };
                            }
                        },
                        asyncFind(query) {
                            let vthis = this;
                            vthis.isLoading = true
                            if (query !== "") {
                                clearTimeout(product_search_timeout);
                                product_search_timeout = setTimeout(
                                    function (query, vthis) {
                                        let wp_ajax = wfo_ajax();
                                        let product_query = {'term': query, 'funnel_id': self.get_funnel_id()};
                                        wp_ajax.ajax('page_search', product_query);
                                        wp_ajax.success = function (rsp) {
                                            if (typeof rsp === "string") {
                                                rsp = JSON.parse(rsp);
                                            }
                                            vthis.products = rsp;
                                            vthis.isLoading = false;
                                        };
                                        wp_ajax.complete = function () {
                                            vthis.isLoading = false;
                                        };
                                    }, 1000, query, vthis
                                );
                            } else {
                                vthis.isLoading = false;
                            }
                        },
                        clearAll() {
                            this.products = [];
                            this.selectedProducts = [];
                            this.isLoading = false;

                        }
                    }
                }
            );
        };

        const inialize_offer_models = function () {

            let modal_add_offer_step = $("#modal-add-offer-step");
            if (modal_add_offer_step.length > 0) {
                modal_add_offer_step.iziModal(
                    {
                        title: wfocuParams.modal_add_offer_step_text,
                        headerColor: '#f9fdff',
                        background: '#efefef',
                        borderBottom: false,
                        radius: 6,
                        width: 600,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        transitionInOverlay: 'none',
                        navigateArrows: "false",
                        onOpening: function (modal) {
                            add_new_step();
                            add_new_step_reset();
                            if (modal.$wrap) {
                                let stepNameField = $(modal.$wrap).find('#step-name');
                                if (stepNameField.length) {
                                    stepNameField.val('');
                                }
                            }
                        },

                    }
                );
            }
            let modal_add_add_product = $("#modal-add-product");
            if (modal_add_add_product.length > 0) {
                modal_add_add_product.iziModal(
                    {
                        title: wfocuParams.modal_add_add_product,
                        headerColor: '#f9fdff',
                        background: '#efefef',
                        borderBottom: false,
                        width: 600,
                        radius: 6,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        transitionInOverlay: 'none',
                        navigateArrows: "false",
                        onOpening: function (modal) {
                            product_search_setting(modal);
                        },
                    }
                );
            }

            if ($('#modal-template_success').length > 0) {
                $("#modal-template_success").iziModal(
                    {
                        title: wfocu.texts.update_template,
                        icon: 'icon-check',
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        width: 600,
                        timeout: 1500,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }

            if ($('#modal-template_clear').length > 0) {
                $("#modal-template_clear").iziModal(
                    {
                        title: wfocu.texts.changesSaved,
                        icon: 'icon-check',
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        width: 600,
                        timeout: 1500,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }

            if ($('#modal-settings_success').length > 0) {

                $("#modal-settings_success").iziModal(
                    {
                        title: wfocu.texts.changesSaved,
                        icon: 'icon-check',
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        width: 600,
                        timeout: 1500,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }

            if ($('#modal-global-settings_success').length > 0) {

                $("#modal-global-settings_success").iziModal(
                    {
                        title: wfocu.texts.changesSaved,
                        icon: 'icon-check',
                        headerColor: '#6dbe45',
                        background: '#efefef',
                        borderBottom: false,
                        width: 600,
                        timeout: 4000,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }

            if ($("#modal-section_product_success").length > 0) {
                $("#modal-section_product_success").iziModal(
                    {
                        title: wfocu.texts.changesSaved,
                        icon: 'icon-check',
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        width: 600,
                        timeout: 1500,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }
            let modal_update_offer = $("#modal-update-offer");
            if (modal_update_offer.length > 0) {
                modal_update_offer.iziModal(
                    {
                        title: wfocuParams.modal_update_offer,
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        history: false,
                        width: 600,
                        radius: 6,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        navigateCaption: true,
                        navigateArrows: false,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        onOpening: function (modal) {
                            update_setting_populate();
                            update_step();
                        }

                    }
                );
            }
            $("input[name='update_step_type']").on(
                'change', function () {

                }
            );

            let shortcode_copy_modal = $("#modal-section-success_shortcodes6456");
            if (shortcode_copy_modal.length > 0) {
                shortcode_copy_modal.iziModal({
                        title: wfocu.texts.shortcode_copy_message,
                        icon: 'icon-check',
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        width: 600,
                        timeout: 1000,
                        timeoutProgressbar: true,
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOutDown',
                        bottom: 0,
                        loop: true,
                        pauseOnHover: true,
                        overlay: false
                    }
                );
            }
            let modal_prev_temp = $(".modal-prev-temp")
            if (modal_prev_temp.length > 0) {
                modal_prev_temp.iziModal(
                    {
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        history: false,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        navigateCaption: true,
                        navigateArrows: "false",
                        onOpening: function (modal) {
                            page_search_setting(modal);
                        },


                    }
                );

            }
            let modal_global_Settings_offer_help = $(".wfocu-global-settings-help-ofc");

            if (modal_global_Settings_offer_help.length > 0) {
                modal_global_Settings_offer_help.iziModal(
                    {
                        headerColor: '#f9fdff',
                        background: '#f9fdff',
                        borderBottom: false,
                        history: false,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        navigateCaption: true,
                        navigateArrows: "false",
                        width: 1000,


                    }
                );

            }
            let modal_funnel_Settings_help_messages = $(".wfocu-funnel-settings-help-messages");

            if (modal_funnel_Settings_help_messages.length > 0) {
                modal_funnel_Settings_help_messages.iziModal(
                    {
                        headerColor: '#f9fdff',
                        background: '#efefef',
                        borderBottom: false,
                        history: false,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        navigateCaption: true,
                        navigateArrows: "false",
                        width: 1000,


                    }
                );

            }
        };
        this.show_offer_design_help = function () {
            $(".wfocu-global-settings-help-ofc").iziModal('open');
            return false;
        }
        this.show_funnel_design_messages = function () {
            $(".wfocu-funnel-settings-help-messages").iziModal('open');
            return false;
        }
        const initialize_offer_ajax_handlers = function (modal) {
            return new wp_admin_ajax('.wfocu_forms_wrap', true, function (ajax) {
                    ajax.before_send = function () {
                        if (self.add_new_offer_setting && self.add_new_offer_setting.$el) {
                            let submitButton = self.add_new_offer_setting.$el.querySelector('.wfocu_btn_primary.wfocu_btn');
                            $(submitButton).addClass('is_busy');
                        }
                        if (self.update_step_setting && self.update_step_setting.$el) {
                            let submitButton = self.update_step_setting.$el.querySelector('.wfocu_btn_primary.wfocu_btn');
                            $(submitButton).addClass('is_busy');
                        }

                    };
                    ajax.data = function (data) {
                        data.append('funnel_id', self.get_funnel_id());
                        data.append('offer_id', self.get_offer_id());

                        return data;
                    };
                    ajax.error = function () {
                        if ('wfocu_save_funnel_offer_products' === ajax.action) {
                            wfocuSweetalert2(
                                $.extend(
                                    {
                                        title: "",
                                        text: "",
                                        type: 'error',
                                        confirmButtonColor: '#0073aa',
                                        confirmButtonText: '',
                                    }, wfocuParams.alerts.max_variation_error
                                )
                            );
                            return;
                        }
                    };
                    ajax.success = function (rsp) {
                        if (typeof rsp === "string") {
                            rsp = JSON.parse(rsp);
                        }

                        if (ajax.action === 'wfocu_update_funnel') {
                            if (rsp.status === true) {
                                self.update_funnel(rsp.data);
                            }
                            self.funnel_setting.modal.stopLoading();
                            $("#modal-update-funnel").iziModal('close');
                        }

                        if (ajax.action === 'wfocu_add_offer') {

                            self.create_step(rsp);
                            $("#modal-add-offer-step").iziModal('close');

                        }

                        if (ajax.action === 'wfocu_update_offer') {
                            if (rsp.status === true) {
                                let newname = rsp.name;
                                let url = rsp.url;
                                let slug = rsp.slug;
                                let type = rsp.type;
                                self.update_current_step('name', newname);
                                self.update_current_step('url', url);
                                self.update_current_step('slug', slug);
                                self.update_current_step('type', type);
                                $("#modal-update-offer").iziModal('close');
                            }

                        }
                        if (ajax.action === "wfocu_save_funnel_offer_products") {
                            if (rsp.status === true) {
                                self.offer_setting.setting_updated();
                            }
                        }
                        if (self.add_new_offer_setting && self.add_new_offer_setting.$el) {
                            let submitButton = self.add_new_offer_setting.$el.querySelector('.wfocu_btn_primary.wfocu_btn');
                            $(submitButton).removeClass('is_busy');
                        }
                        if (self.update_step_setting && self.update_step_setting.$el) {
                            let submitButton = self.update_step_setting.$el.querySelector('.wfocu_btn_primary.wfocu_btn');
                            $(submitButton).removeClass('is_busy');
                        }
                    }
                }
            );

        };
        const initialize_funnel_offer = function () {
            sortable();
            offer_steps_event();
            inialize_offer_models();

            offer_product_settings();

            offer_settings();
            offer_settings_btn_bottom();
            build_offer();

        };

        // Offer step section end here

        const design_settings = function () {
            self.design_settings = new Vue(
                {
                    el: "#wfocu_step_design",
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    created: function () {
                        let indexI = self.get_current_index();
                        let step = self.get_offer_step(indexI);
                        let current_offer = self.get_offer_form(step.id);
                        this.products = current_offer.products;
                        this.shortcodes = {};


                        let isSingle = true;
                        if (1 < Object.keys(this.products).length) {
                            isSingle = false;
                        }
                        let i = 1;
                        for (let key in current_offer.products) {

                            this.shortcodes[key] = {name: current_offer.products[key].name, 'shortcodes': {}};

                            for (let index1 in wfocuParams.shortcodes) {

                                this.shortcodes[key].shortcodes[index1] = {};
                                this.shortcodes[key].shortcodes[index1].label = wfocuParams.shortcodes[index1].label;
                                if (isSingle) {
                                    this.shortcodes[key].shortcodes[index1].value = wfocuParams.shortcodes[index1].code.single;
                                } else {
                                    this.shortcodes[key].shortcodes[index1].value = wfocuParams.shortcodes[index1].code.multi.replace('%s', i);
                                }

                            }
                            i++;
                        }
                    },
                    methods: {
                        show_template_dropdown: function (el) {
                            let elemParent = el.target.closest('.wfocu_field_select_dropdown');
                            elemParent.classList.toggle('active');
                            elemParent.querySelector('.wfocu_field_dropdown').classList.toggle('wfocu-hide');
                        },
                        hide_tempate_dropdown: function () {
                            let templateWrap = document.querySelector('.wfocu_template_editor');
                            if (null != templateWrap) {
                                templateWrap.querySelector('.wfocu_field_select_dropdown').classList.remove('active');
                                templateWrap.querySelector('.wfocu_field_dropdown').classList.add('wfocu-hide');
                            }
                        },
                        prettyJSON: function (json) {
                            if (json) {
                                json = JSON.stringify(json, undefined, 4);
                                json = json.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
                                return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                                    var cls = 'number';
                                    if (/^"/.test(match)) {
                                        if (/:$/.test(match)) {
                                            cls = 'key';
                                        } else {
                                            cls = 'string';
                                        }
                                    } else if (/true|false/.test(match)) {
                                        cls = 'boolean';
                                    } else if (/null/.test(match)) {
                                        cls = 'null';
                                    }
                                    return '<span class="' + cls + '">' + match + '</span>';
                                });
                            }
                        },
                        isEmpty: function (obj) {
                            for (let key in obj) {
                                if (Object.prototype.hasOwnProperty.call(obj, key)) {
                                    return false;
                                }
                            }
                            return true;
                        },
                        getOfferNameByID: function () {
                            let offerID = this.current_offer_id;

                            let steps = wfocu.steps;
                            for (var key in steps) {

                                if (steps[key].id == offerID) {
                                    return steps[key].name;
                                }
                            }
                            return '';
                        },
                        getTemplateNiceName: function (slug) {
                            if (typeof slug == 'undefined') {
                                slug = this.current_template;
                            }

                            if (slug !== 'custom-page') {

                                for (var tempSlug in wfocuParams.templates) {
                                    if (tempSlug === slug) {
                                        return wfocuParams.templates[tempSlug].name;
                                    }
                                }

                            } else {
                                return this.currentTemplateName;
                            }

                            return '';
                        },
                        shouldShowShortcodeUI: function () {
                            if (this.current_template === "custom-page") {
                                return true;
                            }
                            if (this.current_template !== "" && -1 !== ['oxy', 'beaver', 'custom'].indexOf(this.template_group)) {

                                return true;
                            }
                            return false;
                        },
                        getTemplateGroupNiceName: function (slug) {
                            if (typeof slug == 'undefined') {
                                slug = this.template_group;
                            }

                            if (this.current_template === "custom-page") {
                                return wfocu.template_groups['custom_page'];
                            }
                            return wfocu.template_groups[slug];
                        },
                        getButtonClass: function (template, loading) {

                            if ('' === this.settingTemplate && this.current_template === template) {
                                return "button-primary";
                            }
                            if (this.settingTemplate !== template) {
                                return '';
                            }
                            if( loading && this.isimporting === "yes" ) {
                                return 'is_busy';
                            }

                            if (this.isimporting === "yes") {
                                return "installing disabled is_busy";
                            } else if (this.isimporting === "no") {
                                return "updated-message";
                            }
                            return "";
                        },
                        getTemplateImage: function (slug) {
                            if (typeof slug == 'undefined') {
                                slug = this.current_template;
                            }
                            if (slug !== "custom-page" && typeof wfocu.alltemplates[slug] !== "undefined") {
                                return wfocu.alltemplates[slug].thumbnail;
                            }

                            return wfocu.custom_page_image;


                        },
                        copy: function (event) {
                            var getInput = event.target.parentNode.querySelector('.wfocu-scode-text input');
                            getInput.select();
                            document.execCommand("copy");
                            if ($("#modal-section-success_shortcodes6456").length > 0) {
                                $("#modal-section-success_shortcodes6456").iziModal('open');
                            }
                        },
                        update_template: function (template_id, cb) {
                            let self_this = this;

                            this.isimporting = "yes";
                            $('.wfocu_template_box[data-slug=' + template_id + ']').addClass('wfocu_template_importing');
                            // $('.wfocu_btn.wfocu_step_btn.wfocu_steps_btn_green, .wfocu_empty_template.wfocu_template_box .wfocu_vertical_mid').hide();
                            $('.wfocu_template_box[data-slug=' + template_id + '] .wfocu_btn.wfocu_step_btn.wfocu_steps_btn_green').show();
                            $('.wfocu_template_box[data-slug=' + template_id + '].wfocu_empty_template.wfocu_template_box .wfocu_vertical_mid').show();
                            let offer_id = self.get_offer_id();
                            wfocu.offers[offer_id].template = template_id;
                            wfocu.offers[offer_id].template_group = this.template_group;
                            let data = {
                                "id": funnel_id,
                                'offer_id': offer_id,
                                'template': template_id,
                                'template_group': ('custom-page' === template_id) ? "custom_page" : this.template_group,
                                '_nonce': wfocuParams.ajax_nonce_update_template
                            };
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax('update_template', data);
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }

                                if (false === rsp.status) {
                                    wfocuSweetalert2({
                                        'html': rsp.msg,
                                        'title': wfocu.texts.failed_import,
                                        'type': 'warning',
                                        'allowEscapeKey': true,
                                        'showCancelButton': false,
                                        'showCloseButton': true,
                                        'confirmButtonText': wfocu.texts.closeSwal,
                                        'showLoaderOnConfirm': false,
                                    });
                                    self.design_settings.isimporting = "no";
                                    self_this.previewClosed();
                                    return;
                                }
                                if (cb === 'install_plugin') {
                                    $(".wfocuswal-container.wfocuswal-center").css('display', 'none');
                                    $("html, body").removeClass("wfocuswal-shown");
                                } else if (typeof cb !== "undefined") {
                                    cb();
                                }


                                $('.wfocu_apply_template').css('display', 'inline-block');
                                $('.wfocu_customize_template').css('display', 'none');
                                if ($("#modal-template_success").length > 0) {
                                    $("#modal-template_success").iziModal('open');
                                    $("#modal-template_success").iziModal('setTitle', wfocu.texts.changesSaved);
                                }
                                setTimeout(function () {
                                    self.design_settings.isimporting = "no";
                                    self.design_settings.mode = "single";
                                    self.design_settings.current_template = template_id;
                                    self_this.previewClosed();

                                    $(document).scrollTop(0);
                                }, 500);


                            }
                        },
                        swalLoadingText: function (text) {
                            if ($(".wfocuswal-actions.wfocuswal-loading .loading-text").length === 0) {
                                $(".wfocuswal-actions.wfocuswal-loading").append("<div class='loading-text'></div>");

                            }
                            $(".wfocuswal-actions.wfocuswal-loading .loading-text").text(text);
                        },
                        maybeInstallPlugin: function (template_id, cb) {
                            let currentObj = this;
                            this.cb = cb;
                            let builder_slug = wfocuParams.pageBuildersOptions[this.template_group].slug;
                            let builder_status = wfocuParams.pageBuildersOptions[this.template_group].status;
                            let builder_init = wfocuParams.pageBuildersOptions[this.template_group].init;

                            if ('install' === builder_status) {
                                currentObj.swalLoadingText("Installing plugin...");
                                window.wp.updates.queue.push({
                                    action: 'install-plugin', // Required action.
                                    data: {
                                        slug: builder_slug
                                    }
                                });
                            }

                            // Required to set queue.
                            window.wp.updates.queueChecker();
                            if ('activate' === builder_status) {
                                currentObj.activatePlugin(template_id, builder_init);
                            }
                        },
                        afterInstall: function (event, response) {
                            let currentObj = this;
                            var builder_slug = wfocuParams.pageBuildersOptions[this.template_group].slug;
                            var builder_init = wfocuParams.pageBuildersOptions[this.template_group].init;
                            var template_id = this.settingTemplate;
                            if ('plugin' === response.install && response.slug === builder_slug) {
                                currentObj.activatePlugin(template_id, builder_init);
                            } else {
                                wfocuSweetalert2({
                                    'title': wfocuParams.pageBuildersTexts[currentObj.template_group].install_fail,
                                    'type': 'warning',
                                    'allowEscapeKey': true,
                                    'showCancelButton': false,
                                    'showCloseButton': true,
                                    'confirmButtonText': wfocuParams.pageBuildersTexts[currentObj.template_group].close_btn,
                                });
                            }
                        },
                        afterInstallError() {
                            let currentObj = this;
                            wfocuSweetalert2({
                                'title': wfocuParams.pageBuildersTexts[currentObj.template_group].install_fail,
                                'type': 'warning',
                                'allowEscapeKey': true,
                                'showCancelButton': false,
                                'showCloseButton': true,
                                'confirmButtonText': wfocuParams.pageBuildersTexts[currentObj.template_group].close_btn,
                            });
                        },

                        activatePlugin: function (template_id, plugin_slug) {
                            let currentObj = this;
                            let builder_slug = plugin_slug;
                            let builder_status = wfocuParams.pageBuildersOptions[this.template_group].status;
                            let builder_init = wfocuParams.pageBuildersOptions[this.template_group].init;
                            let add_plugin = {
                                'plugin_slug': builder_slug,
                                'plugin_status': builder_status,
                                'plugin_init': builder_init,
                                '_nonce': wfocuParams.ajax_nonce_activate_plugins
                            };

                            // currentObj.swalLoadingText("Activating plugin...");
                            // Add each plugin activate request in Ajax queue.
                            // @see wp-admin/js/updates.js
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax('activate_plugins', add_plugin);
                            wp_ajax.success = function (rsp) {
                                if (rsp.success === true) {
                                    if (wfocuSweetalert2.isVisible()) {
                                        wfocuSweetalert2.close();
                                    }
                                    currentObj.swalLoadingText(wfocu.texts.importing);
                                    currentObj.update_template(template_id);
                                } else {
                                    wfocuSweetalert2({
                                        'title': wfocuParams.pageBuildersTexts[currentObj.template_group].activate_fail,
                                        'type': 'warning',
                                        'allowEscapeKey': true,
                                        'showCancelButton': false,
                                        'showCloseButton': true,
                                        'confirmButtonText': wfocuParams.pageBuildersTexts[currentObj.template_group].close_btn,
                                    });
                                }
                            }
                        },
                        triggerPreview(slug, type) {
                            this.is_previewing = {'slug': slug, 'type': type};
                            $('body').addClass('hide_bscroll');
                            setTimeout(function () {
                                $('.wfocu_global_loader').hide();
                            }, 600);
                            setTimeout(function () {
                                $(".wfocu_template_preview_sidebar .wfocu_template_page_options").removeClass('active_preview');
                                $(".wfocu_template_preview_sidebar .wfocu_template_page_options[ pre_slug=" + slug + "]").addClass('active_preview');

                                var scrollTo = $(".wfocu_template_page_options.active_preview");
                                var container = $('.wfocu_template_preview_sidebar');
                                var position = scrollTo.offset().top - container.offset().top + container.scrollTop();
                                container.animate({scrollTop: position});

                            }, 100);
                        },
                        previewClosed() {
                            this.is_previewing = false;
                            $('body').removeClass('hide_bscroll');
                        },
                        setViewport(view_cl, el) {
                            let setClass = $(el.currentTarget);
                            $('.wfocu_template_viewport .wfocu_viewport_icons').removeClass('active');
                            $(setClass).addClass('active');
                            $('.wfocu_template_preview_wrap .wfocu_template_preview_frame').removeClass('desktop tablet mobile');
                            $('.wfocu_template_preview_wrap .wfocu_template_preview_frame').addClass(view_cl);
                        },
                        ShouldPreview(slug, type) {
                            if (false === this.is_previewing) {
                                return false;
                            }

                            if (this.is_previewing.slug !== slug) {
                                return false;
                            }

                            if (this.is_previewing.type !== type) {
                                return false;
                            }
                            $('.wfocu_template_box_single').find('.hide-preview-over').removeClass('hide-preview-over');

                            return true;
                        },
                        getPreviewUrl(prevslug, activeEditor) {
                          
                            if ('oxy' === activeEditor) {
                                activeEditor = 'oxygen';
                            }
                            if ( 'customizer' === activeEditor  ) {
                                activeEditor = 'elementor';
                            }
                         
                            return 'https://templates-' + activeEditor + '.funnelswp.com/offer/' + prevslug + '/';
                        },
                        set_template: function (template_id, skip_confirmation) {
                            if (typeof skip_confirmation === "undefined") {
                                skip_confirmation = true;
                            }

                            this.settingTemplate = template_id;
                            if (true === skip_confirmation) {
                                this.update_template(template_id);
                            } else {
                                let builder = '';
                                if (typeof wfocuParams.pageBuildersOptions[this.template_group] === "undefined") {
                                    builder = 'undefined'
                                } else {
                                    builder = wfocuParams.pageBuildersOptions[this.template_group].status;
                                }

                                if ('activate' === builder || 'install' === builder) {
                                    let currentObj = this;
                                    let showConfirmBtn = true;
                                    if ('no' === wfocuParams.pageBuildersTexts[currentObj.template_group].show_cancel_btn) {
                                        showConfirmBtn = false;
                                    }
                                    wfocuSweetalert2(
                                        $.extend(
                                            {
                                                title: "",
                                                text: "",
                                                type: '',
                                                confirmButtonColor: '#0073aa',
                                                cancelButtonColor: '#e33b3b',
                                                showConfirmButton: showConfirmBtn,
                                                confirmButtonText: wfocuParams.pageBuildersTexts[currentObj.template_group].confirmButtonText,
                                                showCancelButton: showConfirmBtn,
                                                cancelButtonText: wfocuParams.pageBuildersTexts[currentObj.template_group].close_btn,
                                                showLoaderOnConfirm: false,
                                                reverseButtons: true,
                                                'preConfirm': () => {
                                                    if ('no' === wfocuParams.pageBuildersTexts[currentObj.template_group].noInstall) {
                                                        $('button.wfocuswal-cancel.wfocuswal-styled').css({'display': 'none'});
                                                        return new Promise((resolve) => {
                                                            this.maybeInstallPlugin(template_id, resolve);
                                                        });
                                                    }
                                                    if( 'yes' === wfocuParams.pageBuildersTexts[currentObj.template_group].noInstall && 'install' === wfocuParams.pageBuildersTexts[currentObj.template_group].plugin_status ){
                                                        window.open( wfocuParams.pageBuildersTexts[currentObj.template_group].builder_link, '_blank' );
                                                    }
                                                }
                                            }, {
                                                showCloseButton: true,
                                                html:`<div class="wf_delete_modal_content">${wfocuParams.pageBuildersTexts[currentObj.template_group].text}</div>`,
                                                onOpen: (e) => {
                                                    if( e.closest('.wfocuswal-container') ) {
                                                        e.closest('.wfocuswal-container').classList.add('bwf-modal-zindex');
                                                    }
                                                    const swalModalTitle = document.querySelector('.wfocuswal-header');
                                                    swalModalTitle.insertAdjacentHTML('beforebegin', '<div class="wffn-swal-modal-title">'+wfocuParams.pageBuildersTexts[currentObj.template_group].title+'</div>');
                                                },
                                            }
                                        )
                                    )
                                } else {
                                    this.swalLoadingText(wfocu.texts.importing);
                                    this.update_template(template_id);
                                }
                            }


                        },
                        getButtonText: function (status, template, isEmpty) {
                            /**
                             * if we are setting up any template
                             */

                            if (this.settingTemplate == template) {

                                /**
                                 * Show wait text
                                 */
                                if (this.isimporting == "yes") {
                                    return wfocu.button_texts.importingtext;
                                }
                            }


                            if (this.current_template === template) {
                                return wfocu.button_texts.re_apply;
                            }

                            if (typeof isEmpty !== 'undefined' && isEmpty === true) {
                                return wfocu.button_texts.buildFromScratch;
                            }

                            if (typeof isEmpty !== 'undefined' && isEmpty === false) {
                                return wfocu.button_texts.import_template;
                            }

                            return wfocu.button_texts.import;

                        },
                        get_edit_link(template_id) {
                            let offer_id = self.get_offer_id();

                            let custom_url = (window.wfocu.editor_path);
                            let obj = this;
                            if ('oxy' === this.template_group && true === self.hp(wfocu.alltemplates[self.design_settings.current_template], 'build_from_scratch')) {
                                let data = {'id': offer_id, '_nonce': wfocuParams.ajax_nonce_update_edit_url};
                                let wp_ajax = wfo_ajax();
                                wp_ajax.ajax('update_edit_url', data);
                                wp_ajax.success = function (rsp) {
                                    if (typeof rsp === "string") {
                                        rsp = JSON.parse(rsp);
                                    }
                                    if (true === rsp.status) {
                                        obj.ct_inner = rsp.url;
                                    }
                                };
                            } else {
                                obj.ct_inner = '';
                            }

                            if (template_id === 'custom-page') {
                                custom_url = (window.wfocu.offers[offer_id].template_custom_path);
                                custom_url = custom_url.replace(/&amp;/g, '&');
                            }else {
                                custom_url = custom_url.replace('{{current_offer}}', offer_id);
                            }
                            return custom_url;

                        },
                        customize_template: function (template_id) {
                            let offer_id = self.get_offer_id();

                            let custom_url;

                            if (template_id == 'editor') {
                                custom_url = (window.wfocu.editor_path);

                                window.location.href = custom_url.replace('{{current_offer}}', offer_id);
                                return;
                            }
                            if (template_id == 'custom-page') {
                                custom_url = (window.wfocu.offers[offer_id].template_custom_path);

                                window.location.href = custom_url.replace(/&amp;/g, '&');
                                return;
                            } else {
                                custom_url = "";
                                this.custom_url = "";

                                if (typeof wfocu.edit_links[this.template_group] !== "undefined" && "" !== wfocu.edit_links[this.template_group]) {
                                    custom_url = wfocu.edit_links[this.template_group];
                                }
                                custom_url += this.ct_inner;
                                let index_id = self.get_current_index();
                                let steps = self.get_offer_step(index_id);
                                let step = JSON.stringify(steps);
                                step = JSON.parse(step);

                                if ('' === wfocuParams.permalinkStruct || undefined === wfocuParams.permalinkStruct) {
                                    step.url += "&wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                                } else {
                                    step.url += "?wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                                }


                                custom_url = custom_url.replace(/{{offer_id}}/g, offer_id);
                                custom_url = custom_url.replace('{{funnel_id}}', self.get_funnel_id());
                                custom_url = custom_url.replace('{{step_url}}', encodeURIComponent(step.url));
                                custom_url = custom_url.replace('{{return}}', encodeURIComponent(self.get_current_url()));

                                if (custom_url != "") {
                                    window.location.href = custom_url;
                                    return;
                                }
                            }

                        },
                        clear_template: function (cb) {
                            let self_this = this;
                            let offer_id = self.get_offer_id();
                            wfocu.offers[offer_id].template = '';
                            let data = {
                                "id": funnel_id,
                                'offer_id': offer_id,
                                'current_template': self.design_settings.current_template,
                                'template_group': this.template_group,
                                '_nonce': wfocuParams.ajax_nonce_clear_template
                            };
                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax('clear_template', data);
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if ($("#modal-template_clear").length > 0) {
                                    $("#modal-template_clear").iziModal('open');
                                    $("#modal-template_clear").iziModal('setTitle', wfocu.texts.clear_template);
                                }
                                setTimeout(function () {
                                    if (self.design_settings.template_group === 'custom_page') {
                                        self.design_settings.template_group = 'custom';
                                    }
                                    self.design_settings.mode = "choice";
                                    self.design_settings.current_template = '';
                                    self.design_settings.settingTemplate = '';
                                    self.design_settings.isimporting = "";
                                    self_this.previewClosed();

                                    $(document).scrollTop(0);
                                }, 500);
                                cb(rsp);

                            }
                        },
                        remove_template: function () {
                            wfocuSweetalert2(
                                $.extend(
                                    {
                                        html: `<div class="bwf-delete-modal-content">${wfocuParams.alerts.remove_template.text}</div>`,
                                        type: '',
                                        showCancelButton: true,
                                        confirmButtonColor: '#0073aa',
                                        cancelButtonColor: '#e33b3b',
                                        confirmButtonText: wfocuParams.alerts.remove_template.confirmButtonText,
                                        reverseButtons: true,
                                        showLoaderOnConfirm: false,
                                        showCloseButton: true,
                                        onOpen: () => {
                                            const swalModalTitle = document.querySelector('.wfocuswal-header');
                                            swalModalTitle.insertAdjacentHTML('beforebegin', '<div class="wffn-swal-modal-title">' + wfocuParams.alerts.remove_template.modal_title + '</div>');
                                        },
                                        preConfirm: function () {
                                            $('button.swal2-cancel.swal2-styled').removeAttr('disabled');
                                            return new Promise((resolve) => {
                                                self.design_settings.clear_template((rsp) => {
                                                    resolve(rsp);
                                                });
                                            });
                                        }
                                    },
                                    {
                                        html: `<div class="wf_delete_modal_content">${wfocuParams.alerts.remove_template.text}</div>`,
                                        confirmButtonText: wfocuParams.alerts.remove_template.confirmButtonText,
                                        showLoaderOnConfirm: false,

                                    }
                                ),
                            ).catch(
                                (e) => {
                                    console.log("Error during clearing template", e);
                                }
                            );
                        },
                        preview_template: function (template_id) {
                            let offer_id = self.get_offer_id();

                            let custom_url;

                            if (template_id == 'editor') {
                                custom_url = (window.wfocu.editor_path);

                                window.open(custom_url.replace('{{current_offer}}', offer_id));
                                return;
                            }

                            custom_url = "";
                            this.custom_url = "";

                            if (typeof wfocu.preview_links[this.template_group] !== "undefined" && "" !== wfocu.preview_links[this.template_group]) {

                                if ('custom-page' === wfocu.offers[offer_id].template) {
                                    custom_url = wfocu.preview_links['custom_page'];
                                } else {
                                    custom_url = wfocu.preview_links[this.template_group];
                                }

                            }

                            let index_id = self.get_current_index();
                            let steps = self.get_offer_step(index_id);
                            let step = JSON.stringify(steps);
                            step = JSON.parse(step);

                            if ('' === wfocuParams.permalinkStruct || undefined === wfocuParams.permalinkStruct) {
                                step.url += "&wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                            } else {
                                step.url += "?wfocu_customize=loaded&offer_id=" + step.id + "&funnel_id=" + self.get_funnel_id();
                            }

                            custom_url = custom_url.replace('{{offer_id}}', offer_id);
                            custom_url = custom_url.replace('{{offer_id}}', offer_id);
                            custom_url = custom_url.replace('{{funnel_id}}', self.get_funnel_id());

                            custom_url = custom_url.replace('{{step_url}}', encodeURIComponent(step.url));
                            custom_url = custom_url.replace('{{return}}', encodeURIComponent(self.get_current_url()));
                            custom_url = custom_url.replace('{{custom_page_id}}', window.wfocu.offers[offer_id].template_custom_id);

                            if (custom_url != "") {
                                window.open(custom_url);
                                return;
                            }
                        },
                    },
                    mounted: function () {
                        let self = this;
                        self.previewClosed();
                        $(document.body).click(function (e) {
                            if ($(e.target).attr('class') !== 'wfocu_editor_label wfocu_field_select_label' && $(e.target).parents().attr('class') !== 'wfocu_editor_label wfocu_field_select_label') {
                                if ($(e.target).attr('class') !== 'wfocu_dropdown_header_label' && $('.wfocu_field_select_dropdown').hasClass("active")) {
                                    self.hide_tempate_dropdown();
                                }
                            }
                        });
                    },
                    data: {
                        current_offer: '',
                        custom_url: "",
                        ct_inner: "",
                        current_offer_id: 0,
                        offer_state: default_offer_state,
                        current_template: 'sp-classic',
                        have_multiple_product: 1,
                        index_id: 0,
                        currentTemplateName: '',
                        currentTemplatePath: '',
                        shortcodes: {},
                        products: {},
                        oxyTags: wfocuParams.oxy_tags,
                        template_group: wfocuParams.default_builder,
                        mode: 'choice',
                        isimporting: '',
                        settingTemplate: '',
                        is_previewing: false,
                    }

                }
            );

            let index = self.get_current_index();
            let step = self.get_offer_step(index);
            $(document).on('wp-plugin-install-success', function (event, response) {
                self.design_settings.afterInstall(event, response);
            });

            $(document).on('wp-plugin-install-error', function () {

                self.design_settings.afterInstallError();

            });

            if (Object.prototype.hasOwnProperty.call(step, 'name')) {
                self.design_settings.current_offer = step.name;
                self.design_settings.current_offer_id = step.id;
                self.design_settings.index_id = step.index;
                let current_offer = self.get_offer_form(step.id);

                self.design_settings.current_template = self.hp(current_offer, 'template') ? current_offer.template : '';
                self.design_settings.currentTemplateName = self.hp(current_offer, 'template_custom_name') ? current_offer.template_custom_name : wfocu.template_groups.custom_page;
                self.design_settings.currentTemplatePath = self.hp(current_offer, 'template_custom_path') ? current_offer.template_custom_path : '';
                self.design_settings.have_multiple_product = self.ol(current_offer.products) > 1 ? 2 : 1;

                self.design_settings.template_group = ('' !== current_offer.template_group) ? current_offer.template_group : 'customizer';
                if (self.design_settings.current_template !== "") {
                    self.design_settings.mode = 'single';

                } else {
                    self.design_settings.template_group = wfocuParams.default_builder;
                }
                $('.wfocu_step[data-index_id=' + index + ']').addClass('current_offer');

            }
        };

        const design_setting_steps_events = function () {
            if ($(step_list).length > 0) {
                $(step_list).off('click');
                $(step_list).on('click', function (e) {
                        e.preventDefault();
                        let index_id = $(this).data('index_id');
                        let current_index = self.get_current_index();
                        if (index_id !== current_index) {
                            $('.wfocu_step').removeClass('current_offer');
                            $(this).addClass('current_offer');
                            let offer_id = $(this).data('offer_id');

                            let offer_title = $(this).data('offer_title');
                            self.set_current_index(index_id);
                            self.set_offer_id(offer_id);
                            let offer = self.get_offer_form(offer_id);

                            self.design_settings.current_offer = offer_title;
                            self.design_settings.index_id = index_id;
                            self.design_settings.current_offer_id = offer_id;
                            self.design_settings.current_template = self.hp(offer, 'template') ? offer.template : '';

                            self.design_settings.template_group = ('' !== offer.template_group) ? offer.template_group : 'customizer';

                            if (self.design_settings.current_template !== "") {
                                self.design_settings.mode = 'single';
                            } else {
                                self.design_settings.mode = 'choice';
                                self.design_settings.template_group = wfocuParams.default_builder;
                            }
                            self.design_settings.currentTemplateName = self.hp(offer, 'template_custom_name') ? offer.template_custom_name : wfocu.template_groups.custom_page;
                            self.design_settings.currentTemplatePath = self.hp(offer, 'template_custom_path') ? offer.template_custom_path : '';

                            self.design_settings.shortcodes = {};
                            self.design_settings.products = offer.products;
                            let isSingle = true;
                            if (1 < Object.keys(offer.products).length) {
                                isSingle = false;
                            }
                            let i = 1;
                            for (let key in offer.products) {
                                self.design_settings.shortcodes[key] = {name: offer.products[key].name, 'shortcodes': {}};
                                for (let index in wfocuParams.shortcodes) {
                                    self.design_settings.shortcodes[key].shortcodes[index] = {};
                                    self.design_settings.shortcodes[key].shortcodes[index].label = wfocuParams.shortcodes[index].label;

                                    if (isSingle) {
                                        self.design_settings.shortcodes[key].shortcodes[index].value = wfocuParams.shortcodes[index].code.single;

                                    } else {
                                        self.design_settings.shortcodes[key].shortcodes[index].value = wfocuParams.shortcodes[index].code.multi.replace('%s', i);

                                    }

                                }
                                i++;
                            }

                            let have_multiple_product = JSON.stringify(offer.have_multiple_product);
                            have_multiple_product = JSON.parse(have_multiple_product);
                            self.design_settings.have_multiple_product = have_multiple_product;
                            window.wfocuBuilderCommons.doAction('wfocu_offer_switched');
                        }
                    }
                );
            }
        };

        const funnel_settings_form_field = function () {
            let behavior_fields = [
                {
                    type: "radios",
                    label: "",
                    model: "order_behavior",
                    inputName: 'order_behavior',
                    values: [{name: '', value: 'batching'}, {name: '', value: 'create_order'}],

                },
                {
                    type: "radios",
                    label: "",
                    model: "is_cancel_order",
                    values: [{name: '', value: 'yes'}, {name: '', value: 'no'}],
                    inputName: 'is_cancel_order',
                    visible: function (model) {
                        //visible if business is selected
                        return model && model.order_behavior == 'create_order';
                    }
                },
            ];
            let priority_fields = [

                {
                    type: "input",
                    inputType: 'text',
                    label: "",
                    model: "funnel_priority"
                },
            ];
            let price_fields = [
                {
                    type: "radios",
                    label: "",
                    model: "is_tax_included",
                    values: [{name: '', value: 'yes'}, {name: '', value: 'no'}],
                    inputName: 'is_tax_included',

                },
            ];
            let message_fields = [
                {
                    type: "label",
                    label: "",
                    styleClasses: ["wfocu_gsettings_sec_note", "wfocu_to_html"],
                    model: "offer_messages_label_help",
                    inputName: 'offer_messages_label_help',
                },
                {
                    type: "textArea",
                    label: "",
                    model: "offer_success_message_pop",
                    inputName: 'offer_success_message_pop',

                },
                {
                    type: "textArea",
                    label: "",
                    model: "offer_failure_message_pop",
                    inputName: 'offer_failure_message_pop',

                },
                {
                    type: "textArea",
                    label: "",
                    model: "offer_wait_message_pop",
                    inputName: 'offer_wait_message_pop',

                },
            ];

            let external_fields = [
                {
                    type: "textArea",
                    label: "",
                    model: "funnel_success_script",
                    inputName: 'funnel_success_script',
                    rows: 10,

                },
            ];

            for (let keyfields in behavior_fields) {
                let model = behavior_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.settings, model)) {
                    $.extend(behavior_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                }
            }


            for (let keyfields in price_fields) {
                let model = price_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.settings, model)) {
                    $.extend(price_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                }
            }
            for (let keyfields in priority_fields) {
                let model = priority_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.settings, model)) {
                    $.extend(priority_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                }
            }
            for (let keyfields in message_fields) {
                let model = message_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.settings, model)) {
                    $.extend(message_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                }
            }

            for (let keyfields in external_fields) {
                let model = external_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.settings, model)) {
                    $.extend(external_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                }
            }

            return [
                {
                    legend: wfocuParams.forms_labels.settings.funnel_order_label.label,
                    fields: behavior_fields
                },
                {
                    legend: wfocuParams.forms_labels.settings.prices_settings.label,
                    fields: price_fields
                },
                {
                    legend: wfocuParams.forms_labels.settings.funnel_priority_label.label,
                    fields: priority_fields
                },
                {
                    legend: wfocuParams.forms_labels.settings.offer_messages_label.label,
                    fields: message_fields
                },
                {
                    legend: wfocuParams.forms_labels.settings.offer_scripts_label.label,
                    fields: external_fields
                }
            ]


        }

        const funnel_settings = function () {
            self.offer_setting = new Vue(
                {
                    el: "#wfocu_funnel_setting_vue",
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    methods: {
                        onSubmit: function () {
                            let tempSetting = JSON.stringify(this.model);
                            tempSetting = JSON.parse(tempSetting);
                            let data = {"funnel_id": self.get_funnel_id(), "data": tempSetting, "_nonce": wfocuParams.ajax_nonce_save_funnel_settings};

                            let wp_ajax = wfo_ajax();
                            let ajax_loader = $('#wfocu_funnel_setting_vue').find('.wfocu_save_funnel_setting_ajax_loader');
                            $('.wfocu_save_btn_style').addClass('is_busy');
                            wp_ajax.ajax("save_funnel_settings", data);
                            wp_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                $('.wfocu_save_btn_style').removeClass('is_busy');
                                if ($('#modal-settings_success').length > 0) {
                                    $('#modal-settings_success').iziModal('open');
                                }
                            };
                            return false;
                        },


                    },
                    data: {
                        current_offer_id: 0,
                        product_count: 0,
                        model:
                        wfocuParams.funnel_settings,
                        schema:
                            {
                                groups: funnel_settings_form_field(),

                            }
                        ,
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged:
                                true
                        }
                    }
                    ,
                    mounted: function () {
                        $('.wfocu_to_html label').each(
                            function () {
                                var html = this.innerHTML;
                                let newHtml = '<label>' + html + '</label>';
                                newHtml = newHtml.replace(/&lt;/g, '<');
                                newHtml = newHtml.replace(/&gt;/g, '>');

                                $(this).replaceWith(newHtml);
                            }
                        );
                        if (`1` !== wfocuParams.is_funnel_upsell) {
                            $('.wfocu-funnel-setting').find('.wfocu-tabs-style-line.wfocu-funnel-setting-tabs').removeClass('wfocu_hide');
                        }
                    },
                },
            )
            ;
        };

        function wfocu_funnel_setting_tabs() {
            if ($(".wfocu-funnel-setting-tabs").length > 0) {
                let wffst = $('.wfocu-funnel-setting .wfocu-tab-title');
                wffst.on(
                    'click', function () {

                        let $this = $(this).closest('.wfocu_funnel_setting_inner');
                        let tabindex = $(this).attr('data-tab');

                        $this.find('.wfocu-tab-title').removeClass('wfocu-active');

                        $this.find('.wfocu-tab-title[data-tab=' + tabindex + ']').addClass('wfocu-active');

                        $($this).find('.wfocu-content-tab').removeClass('wfocu-activeTab');
                        $($this).find('.wfocu-funnel-setting .wfocu-content-tab').hide();
                        $($this).find('.wfocu-funnel-setting .wfocu-content-tab').eq(tabindex - 1).addClass('wfocu-activeTab');
                        $($this).find('.wfocu-funnel-setting .wfocu-content-tab').eq(tabindex - 1).show();

                    }
                );
                wffst.eq(0).trigger('click');
            }
        }

        const global_settings = function () {

            self.global_settings_funnels = new Vue(
                {
                    el: "#wfocu_global_setting_vue",
                    components: {

                        "vue-form-generator": VueFormGenerator.component,
                    },
                    methods: {
                        onSubmit: function () {
                            $(".wfocu_save_btn_style").addClass('is_busy');
                            // $('.wfocu_loader_global_save').addClass('ajax_loader_show');
                            let tempSetting = JSON.stringify(this.model);
                            tempSetting = JSON.parse(tempSetting);
                            let data = {"data": tempSetting, '_nonce': wfocuParams.ajax_nonce_save_global_settings};

                            let wp_ajax = wfo_ajax();
                            wp_ajax.ajax("save_global_settings", data);
                            wp_ajax.success = function (rsp) {

                                if ($('#modal-global-settings_success').length > 0) {
                                    $('#modal-global-settings_success').iziModal('open');
                                }
                                $(".wfocu_save_btn_style").removeClass('is_busy');
                                // $('.wfocu_loader_global_save').removeClass('ajax_loader_show');
                                return false;
                            };

                        },

                    },
                    data: {
                        current_offer_id: 0,
                        product_count: 0,
                        colorPickerFields: [
                            'offer_yes_btn_bg_cl',
                            'offer_yes_btn_sh_cl',
                            'offer_yes_btn_txt_cl',
                            'offer_yes_btn_bg_cl_h',
                            'offer_yes_btn_sh_cl_h',
                            'offer_yes_btn_txt_cl_h',
                            'offer_no_btn_txt_cl',
                            'offer_no_btn_txt_cl_h',
                            'cart_opener_text_color',
                            'cart_opener_background_color'
                        ],
                        model: wfocuParams.global_settings,
                        schema: {
                            groups: [
                                {
                                    legend: wfocuParams.forms_labels.global_settings.wfocu_gateways.title,
                                    fields: global_settings_gateway_fields
                                },
                                {
                                    legend: wfocuParams.forms_labels.global_settings.order_statuses.title,
                                    fields: global_settings_order_statuses_fields
                                },
                                {
                                    legend: wfocuParams.forms_labels.global_settings.emails.title,
                                    fields: global_settings_emails_fields
                                },
                                {
                                    legend: wfocuParams.forms_labels.global_settings.wfocu_scripts.title,
                                    fields: global_settings_scripts_fields
                                },

                                {
                                    legend: wfocuParams.forms_labels.global_settings.offer_conf.title,
                                    fields: global_settings_offer_confirmation_fields
                                },
                                {
                                    legend: wfocuParams.forms_labels.global_settings.misc.title,
                                    fields: global_settings_misc_fields
                                },

                            ],

                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true
                        }
                    },

                    mounted: function () {
                        $('.hint').each(
                            function () {
                                var html = this.innerHTML;
                                let newHtml = '<span class="hint">' + html + '</span>';
                                newHtml = newHtml.replace(/&lt;/g, '<');
                                newHtml = newHtml.replace(/&gt;/g, '>');

                                $(this).replaceWith(newHtml);
                            }
                        );
                        $('.wfocu_to_html label').each(
                            function () {
                                var html = this.innerHTML;
                                let newHtml = '<label>' + html + '</label>';
                                newHtml = newHtml.replace(/&lt;/g, '<');
                                newHtml = newHtml.replace(/&gt;/g, '>');

                                $(this).replaceWith(newHtml);
                            }
                        );

                        for (let key in this.colorPickerFields) {
                            $('input[name="' + this.colorPickerFields[key] + '"]').wpColorPicker(
                                {
                                    change: function (event, ui) {

                                        var element = event.target;

                                        var name = element.name;

                                        self.global_settings_funnels.model[name] = ui.color.toString();
                                    }
                                }
                            );
                        }

                    }
                }
            );
        };

        const initialize_funnel_design = function () {
            design_settings();
            design_setting_steps_events();

        };

        let prepare_funnel_data = function () {
            current_offer_id = (Object.prototype.hasOwnProperty.call(wfocu, 'steps') && self.ol(wfocu.steps) > 0) ? wfocu.steps[0]['id'] : 0;

            funnel_id = Object.prototype.hasOwnProperty.call(wfocu, 'id') ? wfocu.id : 0;
            offer_steps = (Object.prototype.hasOwnProperty.call(wfocu, 'steps') && self.ol(wfocu.steps) > 0) ? wfocu.steps : [];
            offer_forms = (Object.prototype.hasOwnProperty.call(wfocu, 'offers') && self.ol(wfocu.offers) > 0) ? wfocu.offers : {};
            current_index = 0;

            if (self.hp(offer_forms, current_offer_id) && self.hp(offer_forms[current_offer_id], 'products')) {
                let offer_product_keys = self.kys(offer_forms[current_offer_id]['products']);
                selected_product = offer_product_keys.length > 0 ? offer_product_keys[0] : '';
            }
        };
        const init = function () {
            prepare_funnel_data();

            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in update_step_settings) {
                let model = update_step_settings[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.update_step, model)) {
                    $.extend(update_step_settings[keyfields], wfocuParams.forms_labels.update_step[model]);
                }
            }

            initialize_funnel_offer();


            if ($(design_container).length > 0) {
                initialize_funnel_design();
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */
            if ($(settings_container).length > 0) {
                // funnelsss();
                funnel_settings();
                /*for (let keyfields in funnel_settings_fields) {
                    let model = funnel_settings_fields[keyfields].model;
                    if (self.hp(wfocuParams.forms_labels.settings, model)) {
                        $.extend(funnel_settings_fields[keyfields], wfocuParams.forms_labels.settings[model]);
                    }
                }

                funnel_settings();*/
            }


            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_order_statuses_fields) {
                let model = global_settings_order_statuses_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.order_statuses.fields) {
                    if (wfocuParams.forms_labels.global_settings.order_statuses.fields[k].key === model) {
                        $.extend(global_settings_order_statuses_fields[keyfields], wfocuParams.forms_labels.global_settings.order_statuses.fields[k]);
                    }
                }
            }

            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_offer_confirmation_fields) {
                let model = global_settings_offer_confirmation_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.offer_conf.fields) {
                    if (wfocuParams.forms_labels.global_settings.offer_conf.fields[k].key === model) {
                        $.extend(global_settings_offer_confirmation_fields[keyfields], wfocuParams.forms_labels.global_settings.offer_conf.fields[k]);
                    }
                }
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_emails_fields) {
                let model = global_settings_emails_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.emails.fields) {
                    if (wfocuParams.forms_labels.global_settings.emails.fields[k].key === model) {
                        $.extend(global_settings_emails_fields[keyfields], wfocuParams.forms_labels.global_settings.emails.fields[k]);
                    }
                }
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_gateway_fields) {
                let model = global_settings_gateway_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.wfocu_gateways.fields) {
                    if (wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k].key === model) {
                        $.extend(global_settings_gateway_fields[keyfields], wfocuParams.forms_labels.global_settings.wfocu_gateways.fields[k]);
                    }
                }
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_scripts_fields) {
                let model = global_settings_scripts_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.wfocu_scripts.fields) {
                    if (wfocuParams.forms_labels.global_settings.wfocu_scripts.fields[k].key === model) {
                        $.extend(global_settings_scripts_fields[keyfields], wfocuParams.forms_labels.global_settings.wfocu_scripts.fields[k]);
                    }
                }
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in global_settings_misc_fields) {
                let model = global_settings_misc_fields[keyfields].model;
                for (var k in wfocuParams.forms_labels.global_settings.misc.fields) {
                    if (wfocuParams.forms_labels.global_settings.misc.fields[k].key === model) {
                        $.extend(global_settings_misc_fields[keyfields], wfocuParams.forms_labels.global_settings.misc.fields[k]);
                    }
                }
            }
            /**
             * handling of localized label/description coming from php to form fields in vue
             */
            if ($(global_settings_container).length > 0) {

                global_settings();
            }

            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in add_new_offer_setting_fields) {
                let model = add_new_offer_setting_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.add_new_offer_setting, model)) {
                    $.extend(add_new_offer_setting_fields[keyfields], wfocuParams.forms_labels.add_new_offer_setting[model]);
                }
            }

            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in funnel_setting_fields) {
                let model = funnel_setting_fields[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.funnel_setting, model)) {
                    $.extend(funnel_setting_fields[keyfields], wfocuParams.forms_labels.funnel_setting[model]);
                }
            }


            /**
             * handling of localized label/description coming from php to form fields in vue
             */

            for (let keyfields in offer_settings_schema) {
                let model = offer_settings_schema[keyfields].model;
                if (self.hp(wfocuParams.forms_labels.offer_settings, model)) {
                    $.extend(offer_settings_schema[keyfields], wfocuParams.forms_labels.offer_settings[model]);
                }
            }

            initialize_offer_ajax_handlers();
        };


        $(document).on('click', '.wfocu_apply_template', function () {
            let template = $(this);
            let template_slug = $(template).attr('data-slug');

            if (template_slug != "") {
                $(template).parent().find('.wfocu-ajax-apply-preset-loader').removeClass('wfocu_hide');
                $.ajax({
                    url: window.ajaxurl,
                    method: 'post',
                    data: {
                        'action': 'wfocu_apply_template',
                        'template_slug': template_slug,
                        'offer_id': self.get_offer_id(),
                        '_nonce': wfocuParams.ajax_nonce_apply_template
                    },
                    success: function (rsp) {
                        if (typeof rsp === "string") {
                            rsp = JSON.parse(rsp);
                        }
                        if (rsp.status == true) {
                            setTimeout(function () {
                                if ($('#modal-template_success').length > 0) {
                                    $("#modal-template_success").iziModal('open');
                                    $("#modal-template_success").iziModal('setTitle', wfocu.preset_texts.success);
                                }
                                $('.wfocu_customize_template').css('display', 'none');
                                $('.wfocu_apply_template').css('display', 'inline-block');
                                $(template).parent().find('.wfocu-ajax-apply-preset-loader').addClass('wfocu_hide');
                                $(template).parent().find('.wfocu_apply_template').css('display', 'none');
                                $(template).parent().find('.wfocu_customize_template').css('display', 'inline-block');
                                $(template).parent().find('.wfocu_customize_template').removeClass('button-primary');
                                $(template).parent().find('.wfocu_customize_template').addClass('button');
                                $(template).parent().find('.wfocu_customize_template').attr('disabled', true);
                            }, 1500);
                        }
                    }
                });
            }

        });


        init();
        wfocu_admin_tabs();
        wfocu_funnel_setting_tabs();

        if ($(".wfocu-widget-tabs").length > 0) {
            $('.wfocu_btm_save_wrap').show();
            let wfctb = $('.wfocu-widget-tabs .wfocu-tab-title');
            wfctb.on(
                'click', function (event) {
                    if ($(event.target).hasClass('class_hide_btn')) {
                        $('.wfocu-tabs-content-btn').addClass('wfocu_hide');
                    } else {
                        $('.wfocu-tabs-content-btn').removeClass('wfocu_hide');
                    }
                    let $this = $(this).closest('.wfocu-widget-tabs');
                    let tabindex = $(this).attr('data-tab');

                    $this.find('.wfocu-tab-title').removeClass('wfocu-active');

                    $this.find('.wfocu-tab-title[data-tab=' + tabindex + ']').addClass('wfocu-active');

                    $($this).find('.wfocu-tab-content').removeClass('wffn-activeTab');
                    $($this).find('.wfocu_forms_fields_settings .fieldsets fieldset').removeClass('wfocu_hide');
                    $($this).find('.wfocu_forms_fields_settings .fieldsets fieldset').hide();
                    $($this).find('.wfocu_forms_fields_settings .fieldsets fieldset').eq(tabindex - 1).addClass('wfocu-activeTab');
                    $($this).find('.wfocu_forms_fields_settings .fieldsets fieldset').eq(tabindex - 1).show();


                }
            );

            wfctb.eq(0).trigger('click');
        }

        return self;
    };
    $(win).on('load',
        function () {
            window.wfocuBuilder = new wfo_builder();
        }
    );

    $(function () {
        let modal = $(".modal-temp-iframe");
        if (modal.length > 0) {
            modal.iziModal({
                history: false,
                width: 620,
                iframe: true,
                iframeHeight: 500,
                loop: true,
                headerColor: '#f9fdff',
            });
        }
    })

    function wfocu_admin_tabs() {
        if ($(".wfocu-product-widget-tabs").length > 0) {
            let wfctb = $('.wfocu-product-widget-tabs .wfocu-tab-title');
            wfctb.on(
                'click', function () {
                    let $this = $(this).closest('.wfocu-product-widget-tabs');
                    let tabindex = $(this).attr('data-tab');

                    $this.find('.wfocu-tab-title').removeClass('wfocu-active');

                    $this.find('.wfocu-tab-title[data-tab=' + tabindex + ']').addClass('wfocu-active');

                    $($this).find('.wfocu-tab-content').removeClass('wfocu-activeTab');
                    $($this).find('.wfocu_forms_global_settings .vue-form-generator fieldset').hide();
                    $($this).find('.wfocu_forms_global_settings .vue-form-generator fieldset').eq(tabindex - 1).addClass('wfocu-activeTab');
                    $($this).find('.wfocu_forms_global_settings .vue-form-generator fieldset').eq(tabindex - 1).show();


                }
            );
            wfctb.eq(0).trigger('click');
        }
    }

    window.wfocuBuilderCommons = wfocuBuilderCommons;
})(jQuery, document, window);
