/*eslint-env jquery*/
/*global Backbone*/
/*global _*/
/*global wfocu*/
/*global wfocuParams*/
var wfocu_app = {};
wfocu_app.Helpers = {};
wfocu_app.Views = {};
wfocu_app.Events = {};

_.extend(wfocu_app.Events, Backbone.Events);


wfocu_app.Helpers.uniqid = function (prefix, more_entropy) {

    if (typeof prefix == 'undefined') {
        prefix = "";
    }

    var retId;
    var formatSeed = function (seed, reqWidth) {
        seed = parseInt(seed, 10).toString(16); // to hex str
        if (reqWidth < seed.length) { // so long we split
            return seed.slice(seed.length - reqWidth);
        }
        if (reqWidth > seed.length) { // so short we pad
            return Array(1 + (reqWidth - seed.length)).join('0') + seed;
        }
        return seed;
    };

    // BEGIN REDUNDANT
    if (!this.php_js) {
        this.php_js = {};
    }
    // END REDUNDANT
    if (!this.php_js.uniqidSeed) { // init seed with big random int
        this.php_js.uniqidSeed = Math.floor(Math.random() * 0x75bcd15);
    }
    this.php_js.uniqidSeed++;

    retId = prefix; // start with prefix, add current milliseconds hex string
    retId += formatSeed(parseInt(new Date().getTime() / 1000, 10), 8);
    retId += formatSeed(this.php_js.uniqidSeed, 5); // add seed hex string
    if (more_entropy) {
        // for more entropy we add a float lower to 10
        retId += (Math.random() * 10).toFixed(8).toString();
    }

    return retId;

};


jQuery(function ($) {


    $(".wfocu_funnel_rule_add_settings").on(
        "click", function () {
            $("#wfocu_funnel_rule_add_settings").attr("data-is_rules_saved", "yes");
            $("#wfocu_funnel_rule_settings").removeClass('wfocu-tgl');
        }
    );

    if ($('#modal-rules-settings_success').length > 0) {

        $("#modal-rules-settings_success").iziModal(
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
                transitionOut: 'fadeOutDown',
                bottom: 0,
                loop: true,
                pauseOnHover: true,
                overlay: false
            }
        );
    }
    $('#wfocu_settings_location').change(function () {
        if ($(this).val() == 'custom:custom') {
            $('.wfocu-settings-custom').show();
        } else {
            $('.wfocu-settings-custom').hide();
        }
    });
    $(document).on('wfocu_rules_updated', function () {
            $('#modal-rules-settings_success').iziModal('open');
            if ($('.wfocu_rules_container').attr('data-is_rules_saved') !== 'yes') {
                setTimeout(function () {
                    window.location = wfocu.offers_link;
                }, 500);
            }
        }
    );
    $('.wfocu_save_funnel_rules').on('click', function () {
        let data = {"data": $('.wfocu_rules_form').serialize()};
        data.action = 'wfocu_save_rules_settings';
        data._nonce = wfocuParams.ajax_nonce_save_rules_settings;
        $('.wfocu_save_funnel_rules').addClass('is_busy');
        $.post(wfocuParams.ajax_url, data, function () {
            $('.wfocu_save_funnel_rules').removeClass('is_busy');
            $(document).trigger('wfocu_rules_updated');
        });

        return false;
    });
    $('#wfocu_settings_location').trigger('change');

    // Ajax Chosen Product Selectors
    var bind_ajax_chosen = function () {

        $(".wfocu-date-picker-field").datepicker({
            dateFormat: "yy-mm-dd",
            numberOfMonths: 1,
            showButtonPanel: true,
            beforeShow: function (input, inst) {
                $(inst.dpDiv).addClass('xl-datepickers');
            }
        });
        $('select.chosen_select').xlChosen();

        $("select.ajax_chosen_select_products").xlAjaxChosen({
            method: 'GET',
            url: wfocuParams.ajax_url,
            dataType: 'json',
            afterTypeDelay: 100,
            data: {
                action: 'woocommerce_json_search_products_and_variations',
                security: wfocuParams.search_products_nonce
            }
        }, function (data) {
            var terms = {};

            $.each(data, function (i, val) {
                terms[i] = val;
            });

            return terms;
        });
        /*Note: WooCommerce all thing subscription plugin*/
        $("select.ajax_chosen_subs_select_products").xlAjaxChosen({
            method: 'GET',
            url: wfocuParams.ajax_url,
            dataType: 'json',
            afterTypeDelay: 100,
            data: {
                action: 'wfocu_subs_product_search',
                security: wfocuParams.search_subs_products_nonce
            }
        }, function (data) {
            var terms = {};

            $.each(data, function (i, val) {
                terms[i] = val;
            });

            return terms;
        });

        $("select.ajax_chosen_select_users").xlAjaxChosen({
            method: 'GET',
            url: wfocuParams.ajax_url,
            dataType: 'json',
            afterTypeDelay: 100,
            data: {
                action: 'woocommerce_json_search_customers',
                security: wfocuParams.search_customers_nonce
            }
        }, function (data) {
            var terms = {};

            $.each(data, function (i, val) {
                terms[i] = val;
            });

            return terms;
        });

        $("select.ajax_chosen_select_coupons").xlAjaxChosen({
            method: 'GET',
            url: wfocuParams.ajax_url,
            dataType: 'json',
            afterTypeDelay: 100,
            data: {
                action: 'wfocu_rule_json_search_coupons',
                security: wfocuParams.search_coupons_nonce
            }
        }, function (data) {
            var terms = {};

            $.each(data, function (i, val) {
                terms[i] = val;
            });

            return terms;
        });


        $("select.ajax_chosen_select").each(function (element) {
            $(element).xlAjaxChosen({
                method: 'GET',
                url: wfocuParams.ajax_url,
                dataType: 'json',
                afterTypeDelay: 100,
                data: {
                    action: 'wfocu_json_search',
                    method: $(element).data('method'),
                    security: wfocuParams.ajax_chosen
                }
            }, function (data) {

                var terms = {};

                $.each(data, function (i, val) {
                    terms[i] = val;
                });

                return terms;
            });
        });

    };

    bind_ajax_chosen();

    //Note - this section will eventually be refactored into the backbone views themselves.  For now, this is more efficent. 
    $('.wfocu_rules_common').on('change', 'select.rule_type', function () {


        // vars
        var tr = $(this).closest('tr');
        var rule_id = tr.data('ruleid');
        var group_id = tr.closest('table').data('groupid');

        var ajax_data = {
            action: "wfocu_change_rule_type",
            security: wfocuParams.ajax_nonce,
            rule_category: $(this).parents(".wfocu-rules-builder").eq(0).attr('data-category'),
            group_id: group_id,
            rule_id: rule_id,
            rule_type: $(this).val()
        };

        tr.find('td.condition').html('').remove();
        tr.find('td.operator').html('').remove();

        tr.find('td.loading').show();
        tr.find('td.rule-type select').prop("disabled", true);
        // load location html
        $.ajax({
            url: window.ajaxurl,
            data: ajax_data,
            type: 'post',
            dataType: 'html',
            success: function (html) {
                tr.find('td.loading').hide().before(html);
                tr.find('td.rule-type select').prop("disabled", false);
                bind_ajax_chosen();
            }
        });
    });

    //Backbone views to manage the UX.
    var wfocu_Rule_Builder = Backbone.View.extend({
        groupCount: 0,
        el: '.wfocu-rules-builder[data-category="basic"]',
        events: {
            'click .wfocu-add-rule-group': 'addRuleGroup',
        },
        render: function () {

            this.$target = this.$('.wfocu-rule-group-target');
            this.category = 'basic';
            wfocu_app.Events.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            this.views = {};
            var groups = this.$('div.wfocu-rule-group-container');
            _.each(groups, function (group) {
                this.groupCount++;
                var id = $(group).data('groupid');
                var view = new wfocu_Rule_Group(
                    {
                        el: group,
                        model: new Backbone.Model(
                            {
                                groupId: id,
                                groupCount: this.groupCount,
                                headerText: this.groupCount > 1 ? wfocuParams.text_or : wfocuParams.text_apply_when,
                                removeText: wfocuParams.remove_text,
                                category: this.category,
                            })
                    });

                this.views[id] = view;
                view.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            }, this);

            if (this.groupCount > 0) {
                $('.rules_or').show();
            }
        },
        addRuleGroup: function (event) {
            event.preventDefault();

            var newId = 'group' + wfocu_app.Helpers.uniqid();
            this.groupCount++;

            var view = new wfocu_Rule_Group({
                model: new Backbone.Model({
                    groupId: newId,
                    groupCount: this.groupCount,
                    headerText: this.groupCount > 1 ? wfocuParams.text_or : wfocuParams.text_apply_when,
                    removeText: wfocuParams.remove_text,
                    category: this.category,
                })
            });

            this.$target.append(view.render().el);
            this.views[newId] = view;

            view.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            if (this.groupCount > 0) {
                $('.rules_or').show();
            }

            bind_ajax_chosen();

            return false;
        },
        removeRuleGroup: function (sender) {

            delete (this.views[sender.model.get('groupId')]);
            sender.remove();
        }
    });

    //Backbone views to manage the UX.
    var wfocu_Rule_Builder2 = Backbone.View.extend({
        groupCount: 0,
        el: '.wfocu-rules-builder[data-category="product"]',
        events: {
            'click .wfocu-add-rule-group': 'addRuleGroup',
        },
        render: function () {

            this.$target = this.$('.wfocu-rule-group-target');
            this.category = 'product';
            wfocu_app.Events.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            this.views = {};
            var groups = this.$('div.wfocu-rule-group-container');
            _.each(groups, function (group) {
                this.groupCount++;
                var id = $(group).data('groupid');
                var view = new wfocu_Rule_Group(
                    {
                        el: group,
                        model: new Backbone.Model(
                            {
                                groupId: id,
                                groupCount: this.groupCount,
                                headerText: this.groupCount > 1 ? wfocuParams.text_or : wfocuParams.text_apply_when,
                                removeText: wfocuParams.remove_text,
                                category: this.category,
                            })
                    });

                this.views[id] = view;
                view.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            }, this);

            if (this.groupCount > 0) {
                $('.rules_or').show();
            }
        },
        addRuleGroup: function (event) {
            event.preventDefault();

            var newId = 'group' + wfocu_app.Helpers.uniqid();
            this.groupCount++;

            var view = new wfocu_Rule_Group({
                model: new Backbone.Model({
                    groupId: newId,
                    groupCount: this.groupCount,
                    headerText: this.groupCount > 1 ? wfocuParams.text_or : wfocuParams.text_apply_when,
                    removeText: wfocuParams.remove_text,
                    category: this.category,
                })
            });

            this.$target.append(view.render().el);
            this.views[newId] = view;

            view.bind('wfocu:remove-rule-group', this.removeRuleGroup, this);

            if (this.groupCount > 0) {
                $('.rules_or').show();
            }

            bind_ajax_chosen();

            return false;
        },
        removeRuleGroup: function (sender) {

            delete (this.views[sender.model.get('groupId')]);
            sender.remove();
        }
    });

    var wfocu_Rule_Group = Backbone.View.extend({
        tagName: 'div',
        className: 'wfocu-rule-group-container',
        template: _.template('<div class="wfocu-rule-group-header"><h4 class="rules_or"><%= headerText %></h4><a href="#" class="wfocu-remove-rule-group button"><%= removeText %></a></div><table class="wfocu-rules" data-groupid="<%= groupId %>"><tbody></tbody></table>'),
        events: {
            'click .wfocu-remove-rule-group': 'onRemoveGroupClick'
        },
        initialize: function () {
            this.views = {};
            this.$rows = this.$el.find('table.wfocu-rules tbody');

            var rules = this.$('tr.wfocu-rule');
            _.each(rules, function (rule) {
                var id = $(rule).data('ruleid');
                var view = new wfocu_Rule_Item(
                    {
                        el: rule,
                        model: new Backbone.Model({
                            groupId: this.model.get('groupId'),
                            ruleId: id,
                            category: this.model.get('category'),
                        })
                    });

                view.delegateEvents();

                view.bind('wfocu:add-rule', this.onAddRule, this);
                view.bind('wfocu:remove-rule', this.onRemoveRule, this);

                this.views.ruleId = view;

            }, this);
        },
        render: function () {

            this.$el.html(this.template(this.model.toJSON()));

            this.$rows = this.$el.find('table.wfocu-rules tbody');
            this.$el.attr('data-groupid', this.model.get('groupId'));

            this.onAddRule(null);

            return this;
        },
        onAddRule: function (sender) {
            var newId = 'rule' + wfocu_app.Helpers.uniqid();

            var view = new wfocu_Rule_Item({
                model: new Backbone.Model({
                    groupId: this.model.get('groupId'),
                    ruleId: newId,
                    category: this.model.get('category')
                })
            });

            if (sender == null) {
                this.$rows.append(view.render().el);
            } else {
                sender.$el.after(view.render().el);
            }
            view.bind('wfocu:add-rule', this.onAddRule, this);
            view.bind('wfocu:remove-rule', this.onRemoveRule, this);

            bind_ajax_chosen();

            this.views.ruleId = view;
        },
        onRemoveRule: function (sender) {

            var ruleId = sender.model.get('ruleId');
            const cat = sender.model.get('category');
            var countRules = $(".wfocu-rules-builder[data-category='" + cat + "'] .wfocu_rules_common .wfocu-rule-group-container table tr.wfocu-rule").length;

            if (countRules == 1) {
                var selectedNull = 'general_always';
                if ('product' === cat) {
                    selectedNull = 'general_always_2';
                }
                $(".wfocu-rules-builder[data-category='" + cat + "'] .wfocu_rules_common .wfocu-rule-group-container table tr.wfocu-rule .rule_type").val(selectedNull).trigger('change');

                return;
            }
            delete (this.views[ruleId]);
            sender.remove();


            if ($("table[data-groupid='" + this.model.get('groupId') + "'] tbody tr").length == 0) {
                wfocu_app.Events.trigger('wfocu:removing-rule-group', this);

                this.trigger('wfocu:remove-rule-group', this);
            }
        },
        onRemoveGroupClick: function (event) {
            event.preventDefault();
            wfocu_app.Events.trigger('wfocu:removing-rule-group', this);
            this.trigger('wfocu:remove-rule-group', this);
            return false;
        }
    });

    var wfocu_Rule_Item = Backbone.View.extend({
        tagName: 'tr',
        className: 'wfocu-rule',
        events: {
            'click .wfocu-add-rule': 'onAddClick',
            'click .wfocu-remove-rule': 'onRemoveClick'
        },
        render: function () {
            const base = this.model.get('category');

            const html = $('#wfocu-rule-template-' + base).html();
            const template = _.template(html);
            this.$el.html(template(this.model.toJSON()));
            this.$el.attr('data-ruleid', this.model.get('ruleId'));
            return this;
        },
        onAddClick: function (event) {
            event.preventDefault();

            wfocu_app.Events.trigger('wfocu:adding-rule', this);
            this.trigger('wfocu:add-rule', this);

            return false;
        },
        onRemoveClick: function (event) {
            event.preventDefault();

            wfocu_app.Events.trigger('wfocu:removing-rule', this);
            this.trigger('wfocu:remove-rule', this);

            return false;
        }
    });

    var ruleBuilder = new wfocu_Rule_Builder();
    ruleBuilder.render();
    var ruleBuilder2 = new wfocu_Rule_Builder2();
    ruleBuilder2.render();


});