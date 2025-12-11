(function ($, doc, win) {
    'use strict';


    window.wfocuBuilderCommons.addFilter('wfocu_offer_settings', function (e) {
        e.unshift(
            {
                type: "label",
                label: "Subscription Discount",
                model: "subscription_discount_label",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }, {
                type: "checkbox",
                label: "By default discount applies to first charge for subscription. Check this box if you want to apply discount for all future recurring payments.",
                model: "subscription_discount",
                inputName: 'subscription_discount',
                styleClasses: "subscription_discount",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }, {
                type: "label",
                label: "SignUp Fee Discount",
                model: "subscription_signup_discount_label",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }, {
                type: "checkbox",
                label: "Check this box if you want to apply discount on sign up fees as well. By default discount applies to the regular price only. ",
                model: "subscription_signup_discount",
                inputName: 'subscription_signup_discount',
                styleClasses: "subscription_discount",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            }, {
                type: "label",
                label: "Override Free Trial",
                model: "subscription_signup_discount_label",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            },
            {
                type: "checkbox",
                label: "Check this box if you want to give free trial for this offer.",
                model: "is_override_free_trial",
                inputName: 'is_override_free_trial',
                styleClasses: "subscription_discount",
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
            },
            {
                type: 'input',
                inputType: 'number',
                label: "",
                model: "free_trial_length",
                inputName: 'free_trial_length',
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (model.is_override_free_trial === true && false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                },
            },
            {
                type: 'select',
                label: "",
                model: "free_trial_period",
                inputName: 'free_trial_period',
                selectOptions: {hideNoneSelectedText: true},
                values: [
                    {
                        'id': 'day',
                        'name': 'Day'
                    }, {
                        'id': 'week',
                        'name': 'Week'
                    }, {
                        'id': 'month',
                        'name': 'Month'
                    }, {
                        'id': 'year',
                        'name': 'Year'
                    },
                ],
                visible: function (model) {
                    let products = wfocuBuilder.offer_product_settings.products;
                    if (model.is_override_free_trial === true && false === wfocuBuilder.offer_product_settings.isEmpty(products)) {
                        for (var key in products) {

                            if (products[key].type === 'subscription' || products[key].type === 'variable-subscription' || products[key].type === 'subscription_variation') {
                                return true;
                            }
                        }
                    }
                    return false;
                },
            },
        );


        return e;
    });


})(jQuery, document, window);