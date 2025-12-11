/*global wp_admin_ajax*/
/*global bwfabtParams*/
/*global bwfabtChart*/
/*global Chart*/
Vue.config.devtools = true;
(function ($) {
    'use strict';
    Vue.component('multiselect', window.VueMultiselect.default);
    $(document).ready(function () {

            var wfabtHP = function (obj, key) {
                let c = false;
                if (typeof obj === "object" && key !== undefined) {
                    c = Object.prototype.hasOwnProperty.call(obj, key);
                }
                return c;
            };

            var wfabtIZIDefault = {
                headerColor: '#6dbe45',
                background: '#fff',
                borderBottom: false,
                history: false,
                overlayColor: 'rgba(0, 0, 0, 0.6)',
                navigateCaption: true,
                navigateArrows: "false",
                // padding:20,
                width: 680,
                timeoutProgressbar: true,
                radius: 10,
                bottom: 'auto',
                closeButton: true,
                pauseOnHover: false,
                overlay: true,
                transitionIn: 'fadeIn',
            };
            var wfabtVueMixin = {
                data: {
                    is_initialized: '1',
                },
                methods: {
                    openTrafficpop: function (selector) {
                        $(selector).iziModal('close');
                        self.variant_settings_vue.update_traffics();
                    },
                    decodeHtml: function (html) {
                        var txt = document.createElement("textarea");
                        txt.innerHTML = html;
                        return txt.value;
                    },
                    prettyJSON: function (json) {
                        if (json) {
                            json = JSON.stringify(json, undefined, 4);
                            json = json.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
                            /* eslint-disable no-useless-escape */
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
                    }
                }
            };
            //Initiating admin ajax object
            let admin_ajax = new wp_admin_ajax();
            let variant_settings_model = false;
            let settings_tab_model = false;

            this.experiment_settings_vue = null;
            this.update_experiment_vue = null;
            this.variant_settings_vue = null;
            this.settings_tab_vue = null;
            this.add_variant_vue = null;
            this.update_traffic_vue = null;
            this.check_readiness_vue = null;
            this.reset_stats_vue = null;
            this.stop_experiment_vue = null;

            VueFormGenerator.validators.is_existing = function (value, field) {
                if (typeof value.existing !== 'undefined' && 'experiment_control' === field.inputName && true === value.existing) {
                    return [bwfabt.add_experiment.existing];
                }
                return [];
            };
            VueFormGenerator.validators.resources.fieldIsRequired = "";

            /**
             * Creating experiment starts
             */
            function get_exp_vuw_fields() {
                let add_new_experiment = [
                    {
                        type: "vueMultiSelect",
                        label: "",
                        model: "experiment_control",
                        inputName: 'experiment_control',
                        required: true,
                        validator: ["required", "is_existing"],
                        selectOptions: {
                            multiSelect: false,
                            key: "id",
                            label: "name",
                            onSearch: function (searchQuery) {
                                let query = searchQuery;
                                $('.multiselect__tags .multiselect__spinner').show();
                                let no_page = bwfabt.custom_options.not_found;
                                if ($(".multiselect .multiselect__content li").length === 1) {
                                    $(".multiselect .multiselect__content").append('<li class="no_found"><span class="multiselect__option">' + no_page + '</span></li>');
                                }
                                $(".multiselect .multiselect__content li.no_found").hide();

                                if (query !== "") {

                                    clearTimeout(self.search_timeout);
                                    self.search_timeout = setTimeout((query) => {
                                        admin_ajax.ajax("page_search", {'term': query, 'type': self.experiment_settings_vue.experiment_type, "_nonce": bwfabtParams.ajax_nonce_page_search});
                                        admin_ajax.success = (rsp) => {
                                            if (typeof rsp !== 'undefined' && rsp.status === true) {
                                                self.experiment_settings_vue.model.allControls = rsp.controls;
                                                $('.multiselect__tags .multiselect__spinner').hide();
                                            } else {
                                                $(".multiselect .multiselect__content li:not(.multiselect__element)").hide();
                                                $(".multiselect .multiselect__content li.no_found").show();
                                            }
                                        };
                                        admin_ajax.complete = function () {
                                            $('.multiselect__tags .multiselect__spinner').hide();
                                        };
                                    }, 800, query);
                                } else {
                                    $('.multiselect__tags .multiselect__spinner').hide();
                                }

                            }
                        },
                        values: function () {
                            return this.model.allControls;
                        },
                    },
                    {
                        type: "input",
                        inputType: "text",
                        label: "",
                        model: "experiment_name",
                        inputName: 'experiment_name',
                        featured: true,
                        required: true,
                        placeholder: "",
                        validator: ["string", "required"],
                    },
                    {
                        type: "textArea",
                        label: "",
                        model: "experiment_desc",
                        inputName: 'experiment_desc',
                        featured: true,
                        rows: 3,
                        placeholder: ""
                    }
                ];

                for (let keyfields in add_new_experiment) {
                    let model = add_new_experiment[keyfields].model;
                    $.extend(add_new_experiment[keyfields], bwfabt.add_experiment.label_texts[model]);
                }
                return add_new_experiment;
            }

            /**
             * Defining constants experiemnt settings
             */
            const experiment_settings = function () {
                self.experiment_settings_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    el: "#bwfabt_experiments_area",
                    components: {
                        "vue-form-generator": VueFormGenerator.component,
                        Multiselect: window.VueMultiselect.default
                    },
                    data: {
                        modal: false,
                        model: {
                            experiment_control: "",
                            experiment_name: "",
                            experiment_desc: "",
                            allControls: [],
                        },
                        search_timeout: false,
                        schema: {
                            fields: get_exp_vuw_fields(),
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true,
                        },
                        exp_step: bwfabt.add_experiment.exp_step,
                        experiment_type: bwfabt.add_experiment.default_exp_type,
                        control_title: '',
                    },
                    methods: {
                        getEntitiesForType: function () {
                            if ("" === this.experiment_type) {
                                $('.bwfabt-type-error').removeClass('bwfabt-hidden');
                                setTimeout(function () {
                                    $('.bwfabt-type-error').addClass('bwfabt-hidden');
                                }, 2000);
                                return;
                            }
                            this.show_loader();
                            admin_ajax.ajax("get_experiment_controls", {'type': self.experiment_settings_vue.experiment_type, "_nonce": bwfabtParams.ajax_nonce_get_experiment_controls});
                            admin_ajax.success = function (rsp) {
                                if (rsp.status === true) {
                                    self.experiment_settings_vue.control_title = rsp.title;
                                    self.experiment_settings_vue.move_to_next_step(self.experiment_settings_vue.exp_step);
                                }
                            };
                            return [];
                        },
                        createExperiment: function () {
                            if (false === this.$refs.vfg.validate()) {
                                return;
                            }
                            self.experiment_settings_vue.move_to_next_step(self.experiment_settings_vue.exp_step);
                            admin_ajax.ajax("add_new_experiment", {
                                'experiment_control': self.experiment_settings_vue.model.experiment_control.id,
                                'experiment_type': self.experiment_settings_vue.experiment_type,
                                'experiment_name': self.experiment_settings_vue.model.experiment_name,
                                'experiment_desc': self.experiment_settings_vue.model.experiment_desc,
                                "_nonce": bwfabtParams.ajax_nonce_add_new_experiment
                            });
                            admin_ajax.success = function (rsp) {
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        self.experiment_settings_vue.control_title = rsp.msg;
                                        self.experiment_settings_vue.move_to_next_step(self.experiment_settings_vue.exp_step);
                                    }, 1000);
                                    setTimeout(function () {
                                        window.location.href = rsp.redirect_url;
                                    }, 2500);
                                }
                            };
                        },
                        show_loader: function () {
                            let exp_step;
                            exp_step = self.experiment_settings_vue.exp_step;
                            self.experiment_settings_vue.prev_step = exp_step;
                            self.experiment_settings_vue.exp_step = 0;

                        },
                        move_to_next_step: function (exp_step) {
                            if (0 === exp_step) {
                                exp_step = self.experiment_settings_vue.prev_step;
                            }
                            self.experiment_settings_vue.exp_step = parseInt(exp_step) + parseInt(1);
                        },
                        move_to_previous_step: function (exp_step) {
                            if (0 === exp_step) {
                                exp_step = self.experiment_settings_vue.prev_step;
                            }
                            self.experiment_settings_vue.exp_step = parseInt(exp_step) - parseInt(1);
                        },
                        set_controller_type: function (event, experiment_type) {
                            self.experiment_settings_vue.experiment_type = experiment_type;
                            $('.bwfabt-type-error').addClass('bwfabt-hidden');
                        },
                    },
                    updated: function () {
                        removeHiddenExpElem();
                    }
                });
            };
            experiment_settings();
            /** Creating experiment ends here **/

            /** Delete experiment started */
            $(".bwfabt-delete-experiment").on("click", function () {
                let experiment_id = $(this).attr('data-experiment-id');
                let elem = $(this);
                elem.addClass('disabled');

                if (experiment_id > 0) {
                    $("#modal-delete-experiment").iziModal(
                        $.extend({
                            icon: 'icon-check',
                            closeOnEscape: false,
                            overlayClose: false,
                        }, wfabtIZIDefault)
                    );
                    $('#modal-delete-experiment').iziModal('open');
                    $('.bwfabt_delete_exp').on('click', function () {
                        $('.bwfabt-exp-delete-confirmation').addClass('bwfabt-hide');
                        $('.bwfabt-experiment-deleting').removeClass('bwfabt-hide');
                        admin_ajax.ajax("delete_experiment", {'experiment_id': experiment_id, "_nonce": bwfabtParams.ajax_nonce_delete_experiment});
                        admin_ajax.success = function (rsp) {
                            if (typeof rsp === "string") {
                                rsp = JSON.parse(rsp);
                            }
                            if (rsp.status === true) {
                                setTimeout(function () {
                                    $('.bwfabt-experiment-deleting').addClass('bwfabt-hide');
                                    $('.bwfabt-experiment-deleted').removeClass('bwfabt-hide');
                                }, 2000);
                                setTimeout(function () {
                                    $('#modal-delete-experiment').iziModal('close');
                                    location.reload();
                                }, 4000);
                            } else {
                                setTimeout(function () {
                                    $('#modal-delete-experiment').iziModal('close');
                                    location.reload();
                                }, 3000);
                            }
                            elem.removeClass('disabled');
                        };
                    });
                }
            });
            /** Delete experiment ends here**/


            /**
             * Variants builder and listing starts here
             *
             * Variant settings
             */
            const variant_settings = function () {
                if (true === variant_settings_model) {
                    return;
                }
                variant_settings_model = true;
                self.variant_settings_vue = new Vue(
                    {
                        el: "#bwfabt_common_vue",
                        mixins: [wfabtVueMixin],
                        components: {
                            "vue-form-generator": VueFormGenerator.component
                        },
                        methods: {
                            getDefaultVariants: function () {
                                let variants = {};
                                for (var i in self.variant_settings_vue.variants) {

                                    if ((0 != self.variant_settings_vue.variants[i].status) && ( false != self.variant_settings_vue.variants[i].active)) {
                                        variants[i] = self.variant_settings_vue.variants[i];
                                    }

                                }
                                return variants;
                            }, getDefaultVariantsTraffic: function () {
                                let variants = {};
                                for (var i in self.variant_settings_vue.variants) {

                                    if ((0 != self.variant_settings_vue.variants[i].status) && ( false != self.variant_settings_vue.variants[i].active)) {
                                        variants[i] = self.variant_settings_vue.variants[i].traffic;
                                    }

                                }
                                return variants;
                            },
                            updateExperiment: function () {
                                if ($("#modal-update-experiment").length > 0) {
                                    $("#modal-update-experiment").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            $('.wfabt_bnt_rem').removeClass('bwfabt-hide');
                                            update_experiment(modal);
                                        },
                                        onClosed: function () {
                                            self.update_experiment_vue.$destroy();
                                            $('#modal-update-experiment').iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-update-experiment").iziModal('open');
                                }
                            },
                            wfabtHP: function (obj, key) {
                                return wfabtHP(obj, key);
                            },
                            addVariant: function () {
                                if ('2' === self.variant_settings_vue.experiment_status) {
                                    return false;
                                }
                                if ($("#modal-add-variant").length > 0) {
                                    $("#modal-add-variant").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            $('.wfabt_bnt_rem').removeClass('bwfabt-hide');
                                            add_variant(modal);
                                        },
                                        onClosed: function () {
                                            self.add_variant_vue.$destroy();
                                            $('#modal-add-variant').iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-add-variant").iziModal('open');
                                }
                            },
                            duplicateVariant: function (variant_id) {
                                this.CurrentDuplicateVariant = variant_id;
                                if ($("#modal-duplicate-variant").length > 0) {
                                    $("#modal-duplicate-variant").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            duplicate_variant(modal, self.variant_settings_vue.CurrentDuplicateVariant);
                                        },
                                        onClosed: function () {
                                            self.duplicate_variant_vue.$destroy();
                                            $("#modal-duplicate-variant").iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-duplicate-variant").iziModal('open');
                                }
                            },
                            deleteVarntConsent: function (variant_id) {
                                this.CurrentDeleteVariant = variant_id;
                                if ($("#modal-delete-variant").length > 0) {
                                    $("#modal-delete-variant").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpening: function (modal) {
                                            delete_variant(modal, self.variant_settings_vue.CurrentDeleteVariant);
                                        },
                                        onClosed: function () {
                                            self.delete_variant_vue.$destroy();
                                            $("#modal-delete-variant").iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-delete-variant").iziModal('open');
                                }
                            },
                            draftVariantConsent: function (variant_id) {
                                this.CurrentDraftVariant = variant_id;

                                if ($("#modal-draft-variant").length > 0) {
                                    $("#modal-draft-variant").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            draft_variant(modal, self.variant_settings_vue.CurrentDraftVariant);
                                        },
                                        onClosed: function () {
                                            self.draft_variant_vue.$destroy();
                                            $("#modal-draft-variant").iziModal('resetContent');
                                            $("#modal-draft-variant").iziModal('destroy');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-draft-variant").iziModal('open');
                                }
                            },
                            publishVariantConsent: function (variant_id) {
                                this.CurrentPublishVariant = variant_id;

                                if ($("#modal-publish-variant").length > 0) {
                                    $("#modal-publish-variant").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            publish_variant(modal, self.variant_settings_vue.CurrentPublishVariant);
                                        },
                                        onClosed: function () {
                                            self.publish_variant_vue.$destroy();
                                            $("#modal-publish-variant").iziModal('resetContent');
                                            $("#modal-publish-variant").iziModal('destroy');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-publish-variant").iziModal('open');
                                }
                            },
                            startExperiment: function () {
                                if (4 === self.variant_settings_vue.experiment_status) {
                                    return false;
                                }
                                if ($("#modal_start_experiment").length > 0) {

                                    $("#modal_start_experiment").iziModal($.extend({

                                        onOpening: function () {
                                            check_readiness();

                                            admin_ajax.ajax("check_readiness", {
                                                'experiment_id': bwfabt.list_variants.experiment_id,
                                                "_nonce": bwfabtParams.ajax_nonce_check_readiness,
                                            });
                                            admin_ajax.success = function (rsp) {
                                                if (rsp.status === true) {
                                                    self.check_readiness_vue.no_variant = rsp.no_variant;
                                                    self.check_readiness_vue.InValid_traffic = rsp.InValid_traffic;
                                                    self.check_readiness_vue.inactive_variant = rsp.inactive_variant;
                                                    self.check_readiness_vue.inactive_error = bwfabt.start_experiment.inactive_error;
                                                    self.check_readiness_vue.inactive_variants = rsp.inactive_variants;
                                                } else {
                                                    self.check_readiness_vue.unable_to_start = true;
                                                }
                                                setTimeout(function () {
                                                    self.check_readiness_vue.readiness_state = rsp.readiness_state;
                                                    self.check_readiness_vue.message = rsp.message;
                                                }, 2000);
                                            };
                                        },
                                        onClosed: function () {
                                            self.check_readiness_vue.$destroy();
                                            $("#modal_start_experiment").iziModal('resetContent');
                                            $("#modal_start_experiment").iziModal('destroy');
                                        }
                                    }, wfabtIZIDefault));
                                }
                                $('#modal_start_experiment').iziModal('open');
                            },
                            stopExperiment: function () {
                                console.log('clicked');
                                if ($("#modal_stop_experiment").length > 0) {
                                    console.log($("#modal_stop_experiment").length);
                                    $("#modal_stop_experiment").iziModal($.extend({
                                        onOpened: function (modal) {
                                            console.log('clicked on opened');
                                            stop_experiment(modal, self.variant_settings_vue.experiment_id);
                                        },
                                        onClosed: function () {
                                            self.stop_experiment_vue.$destroy();
                                            $("#modal_stop_experiment").iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                }
                                $("#modal_stop_experiment").iziModal('open');
                            },

                            update_traffics: function () {
                                if ($("#modal_update_traffic").length > 0) {
                                    $("#modal_update_traffic").iziModal($.extend({
                                        closeOnEscape: false,
                                        overlayClose: false,
                                        onOpened: function (modal) {
                                            update_traffic(modal);
                                        },
                                        onClosed: function () {
                                            self.update_traffic_vue.normalizeTraffic();
                                            self.update_traffic_vue.$destroy();
                                            $('#modal_update_traffic').iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                }
                                $('#modal_update_traffic').iziModal('open');
                            },
                            setTrafficCircle: function () {

                                for (var i in this.variants) {
                                    $('.circle[data-v-id="' + i + '"]').circleProgress({

                                        value: this.variants[i].traffic / 100, // Value displaying in Circle
                                        size: 21,
                                        startAngle: -3.14 / 2,

                                        fill: {
                                            color: '#80c63c',
                                        }
                                    });
                                }
                            },
                            getVariantColor: function (variant_id) {
                                let color_index = 0;
                                for (let varnt_index in self.variant_settings_vue.variants_order) {
                                    if (parseInt(variant_id) === parseInt(self.variant_settings_vue.variants_order[varnt_index])) {
                                        color_index = varnt_index;
                                        break;
                                    }
                                }
                                return self.variant_settings_vue.variant_colors[color_index];
                            },
                            getTotalVariantCount: function () {
                                return Object.keys(this.variants).length;
                            },
                            rearragneVariantsOrder: function () {
                                let variants_order = [];
                                for (let varnt_id in this.variants) {
                                    variants_order.push(varnt_id);
                                }
                                self.variant_settings_vue.variants_order = variants_order;
                            }
                        },
                        schema: {},
                        data: {
                            modal: false,
                            experiment_id: 0,
                            experiment_status: 0,
                            control_id: 0,
                            variant_id: 0,
                            variants: [],
                            update_traffic: [],
                            readiness_state: 0,
                            CurrentDeleteVariant: 0,
                            CurrentDraftVariant: 0,
                            CurrentDuplicateVariant: 0,
                            variant_colors: bwfabt.list_variants.colors,
                            variants_order: [],
                            message: '',
                        },
                        updated: function () {
                            if ('variants' === bwfabt.current_exp_section) {
                                this.setTrafficCircle();
                            }
                        }
                    });
                self.variant_settings_vue.experiment_id = bwfabt.list_variants.experiment_id;
                self.variant_settings_vue.control_id = bwfabt.list_variants.control_id;
                self.variant_settings_vue.variants = bwfabt.list_variants.variants;
                self.variant_settings_vue.variants_order = bwfabt.list_variants.variants_order;
                self.variant_settings_vue.update_traffic = bwfabt.update_traffic.update_data;
                self.variant_settings_vue.experiment_status = bwfabt.start_experiment.experiment_status;
                self.variant_settings_vue.readiness_state = bwfabt.start_experiment.readiness_state;
                self.variant_settings_vue.message = bwfabt.start_experiment.message;
            };
            variant_settings();

            /**
             * Settings tab vue starts here
             */
            const exp_settings_tab = function () {
                if (true === settings_tab_model) {
                    return;
                }
                settings_tab_model = true;
                self.settings_tab_vue = new Vue(
                    {
                        el: "#bwfabt_settings_area",
                        mixins: [wfabtVueMixin],
                        components: {
                            "vue-form-generator": VueFormGenerator.component
                        },
                        data: {
                            modal: false,
                            experiment_id: 0,
                            experiment_status: 0,
                            settings_tab: bwfabt.settings_tab.default_tab,
                        },
                        methods: {
                            resetStatsConsent: function () {
                                if ($("#modal-reset-stats").length > 0) {
                                    $("#modal-reset-stats").iziModal($.extend({

                                        onOpening: function (modal) {
                                            reset_stats(modal, self.variant_settings_vue.experiment_id);
                                        },
                                        onClosed: function () {
                                            self.reset_stats_vue.$destroy();
                                            $("#modal-reset-stats").iziModal('resetContent');
                                        }
                                    }, wfabtIZIDefault));
                                    $("#modal-reset-stats").iziModal('open');
                                }
                            },
                            setTab: function (tab_name) {
                                self.settings_tab_vue.settings_tab = tab_name;
                            }
                        },
                    });
                self.settings_tab_vue.experiment_id = bwfabt.list_variants.experiment_id;
                self.settings_tab_vue.experiment_status = self.variant_settings_vue.experiment_status;

            };
            exp_settings_tab();


            /** settings tab vue ends here **/

            /**
             * Updating experiment start
             * @type {boolean}
             */
            function get_exp_update_vuw_fields() {
                let update_the_experiment = [{
                    type: "input",
                    inputType: "text",
                    label: "",
                    model: "experiment_name",
                    inputName: 'experiment_name',
                    featured: true,
                    required: true,
                    placeholder: "",
                    validator: VueFormGenerator.validators.string
                }, {
                    type: "textArea",
                    label: "",
                    model: "experiment_desc",
                    inputName: 'experiment_desc',
                    featured: true,
                    rows: 3,
                    placeholder: ""
                }];

                for (let keyfields in update_the_experiment) {
                    let model = update_the_experiment[keyfields].model;

                    $.extend(update_the_experiment[keyfields], bwfabt.update_experiment.label_texts[model]);

                }
                return update_the_experiment;
            }

            const update_experiment = function (modal) {
                self.update_experiment_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    data: {
                        modal: modal,
                        update_status: bwfabt.update_experiment.update_status,
                        model: {
                            experiment_name: "",
                            experiment_desc: "",
                        },
                        state: 1,
                        schema: {
                            fields: get_exp_update_vuw_fields(this),
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true,
                        },
                        experiment_name: bwfabt.update_experiment.label_texts.experiment_name.value,
                        experiment_desc: bwfabt.update_experiment.label_texts.experiment_desc.value,
                    },
                    methods: {
                        updateExperiment: function () {
                            if (false === this.$refs.update_experiment_ref.validate()) {
                                return;
                            }
                            self.update_experiment_vue.update_status = 'updating';
                            admin_ajax.ajax("update_experiment", {
                                'experiment_id': bwfabt.list_variants.experiment_id,
                                'experiment_name': self.update_experiment_vue.model.experiment_name,
                                'experiment_desc': self.update_experiment_vue.model.experiment_desc,
                                "_nonce": bwfabtParams.ajax_nonce_update_experiment,
                            });
                            admin_ajax.success = function (rsp) {
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        let spantext = $("#bwfabt_common_vue .bwf_breadcrumb ul li:last-child span.wfabt_status_btn").text();
                                        $("#bwfabt_common_vue .bwf_breadcrumb ul li:last-child").html(self.update_experiment_vue.model.experiment_name + ' <span class="wfabt_status_btn">' + spantext + '</span>');
                                    }, 2000);
                                    self.update_experiment_vue.experiment_name = self.update_experiment_vue.model.experiment_name;
                                    self.update_experiment_vue.experiment_desc = self.update_experiment_vue.model.experiment_desc;
                                }
                                setTimeout(function () {
                                    self.update_experiment_vue.update_status = 'updated';
                                }, 2000);
                                setTimeout(function () {
                                    $('#modal-update-experiment').iziModal('close');
                                }, 4000);
                            };
                        }
                    },
                    mounted: function () {
                        this.model.experiment_name = (undefined === self.update_experiment_vue) ? bwfabt.update_experiment.label_texts.experiment_name.value : self.update_experiment_vue.experiment_name;
                        this.model.experiment_desc = (undefined === self.update_experiment_vue) ? bwfabt.update_experiment.label_texts.experiment_desc.value : self.update_experiment_vue.experiment_desc;
                    }
                }).$mount('#part_update_experiment_vue');
            };

            /** Updating experiment ends here **/

            /**
             * Adding variant
             * @type {boolean}
             */
            function get_add_variant_vuw_fields() {
                let add_new_variant = [{
                    type: "input",
                    inputType: "text",
                    label: "",
                    model: "variant_title",
                    inputName: 'variant_title',
                    featured: true,
                    required: true,
                    placeholder: "",
                    validator: VueFormGenerator.validators.string
                }, {
                    type: "textArea",
                    label: "",
                    model: "variant_desc",
                    inputName: 'variant_desc',
                    featured: true,
                    rows: 3,
                    placeholder: ""
                }];

                for (let keyfields in add_new_variant) {
                    let model = add_new_variant[keyfields].model;

                    $.extend(add_new_variant[keyfields], bwfabt.add_variant.label_texts[model]);

                }
                return add_new_variant;
            }

            const add_variant = function (modal) {

                self.add_variant_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    data: {
                        modal: modal,
                        model: {
                            variant_title: "",
                            variant_desc: "",
                        },
                        schema: {
                            fields: get_add_variant_vuw_fields(this),
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true,
                        },
                        experiment_id: 0,
                        state: 1,
                    },
                    methods: {
                        create_variant: function () {
                            if (false === this.$refs.add_variant_ref.validate()) {
                                return;
                            }
                            self.add_variant_vue.state = 2;
                            admin_ajax.ajax("add_variant", {
                                'experiment_id': self.add_variant_vue.experiment_id,
                                'variant_title': self.add_variant_vue.model.variant_title,
                                'variant_desc': self.add_variant_vue.model.variant_desc,
                                "_nonce": bwfabtParams.ajax_nonce_add_variant,
                            });
                            admin_ajax.success = function (rsp) {
                                if (rsp.status === true) {
                                    Vue.set(self.variant_settings_vue.variants, rsp.id, rsp.data);
                                    Vue.set(self.variant_settings_vue.variants_order, rsp.variant_order, rsp.id);
                                    self.add_variant_vue.state = 3;
                                } else {
                                    $('.bwfabt_disp_none .bwfabt_text_center').html('<div class="wfabt_h3">' + rsp.msg + '</div>');
                                    setTimeout(function () {
                                        $("#modal-add-variant").iziModal('close');
                                    }, 2000);
                                }
                            };
                        }
                    }
                }).$mount('#part_add_variant_vue');
                self.add_variant_vue.experiment_id = bwfabt.list_variants.experiment_id;
            };


            const duplicate_variant = function (modal, variant_id) {

                self.duplicate_variant_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {},
                    data: {
                        'myvar': '4444',
                        modal: modal,
                        model: {},
                        experiment_id: 0,
                        duplicate_status: bwfabt.duplicate_variant.duplicate_status
                    },
                    methods: {
                        duplicateVariant: function (variant_id) {
                            admin_ajax.ajax("duplicate_variant", {
                                'experiment_id': self.variant_settings_vue.experiment_id,
                                'variant_id': variant_id,
                                "_nonce": bwfabtParams.ajax_nonce_duplicate_variant
                            });
                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    self.duplicate_variant_vue.duplicate_status = 'duplicated';
                                    Vue.set(self.variant_settings_vue.variants, rsp.duplicated_variant_id, rsp.data);
                                    Vue.set(self.variant_settings_vue.variants_order, rsp.variant_order, rsp.duplicated_variant_id);
                                }  else {
                                    $('#part_duplicate_variant_vue .bwfabt_text_center').html('<div class="wfabt_h3">' + rsp.msg + '</div>');
                                    setTimeout(function () {
                                        $("#modal-duplicate-variant").iziModal('close');
                                    }, 2000);
                                }
                            };
                        }

                    },
                    mounted: function () {
                        this.duplicateVariant(variant_id);
                    }
                }).$mount('#part_duplicate_variant_vue');
                self.duplicate_variant_vue.experiment_id = bwfabt.list_variants.experiment_id;
            };


            /**
             * Remove Variant Vue starts here
             */
            const draft_variant = function (modal, variant_id) {

                self.draft_variant_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {},
                    data: {
                        modal: modal,
                        model: {},
                        experiment_id: 0,
                        state: 1,
                        remove_status: 'remove',
                        error: '',
                        message: '',
                    },
                    methods: {
                        draftVariant: function () {
                            let experiment_id = self.variant_settings_vue.experiment_id;
                            Vue.set(self.draft_variant_vue, 'remove_status', 'removing');
                            admin_ajax.ajax("draft_variant", {'experiment_id': experiment_id, variant_id: variant_id, "_nonce": bwfabtParams.ajax_nonce_draft_variant});
                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        Vue.set(self.draft_variant_vue, 'remove_status', 'removed');
                                        Vue.set(self.draft_variant_vue, 'error', rsp.error);
                                        Vue.set(self.draft_variant_vue, 'message', rsp.message);
                                    }, 3000);
                                    let existingVariant = self.variant_settings_vue.variants[variant_id];
                                    Vue.set(self.variant_settings_vue.variants, variant_id, existingVariant);
                                    Vue.set(self.variant_settings_vue.variants[variant_id], 'active', false);
                                    let publishVariant = self.variant_settings_vue.variants[variant_id].row_actions.draft;
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions, 'draft', {});
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions, 'publish', publishVariant);
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions.publish, 'text', bwfabt.variant_text.publish );
                                    Vue.set(self.variant_settings_vue.variants[variant_id], 'traffic', '0.00' );
                                } else {
                                    setTimeout(function () {
                                        Vue.set(self.draft_variant_vue, 'remove_status', 'remove_error');
                                        Vue.set(self.draft_variant_vue, 'error', rsp.error);
                                        Vue.set(self.draft_variant_vue, 'message', rsp.message);
                                    }, 3000);
                                }
                            };
                        }
                    },
                }).$mount('#part_draft_variant_vue');
                self.draft_variant_vue.experiment_id = bwfabt.list_variants.experiment_id;
            };


        /**
         * Remove Variant Vue starts here
         */
        const publish_variant = function (modal, variant_id) {

            self.publish_variant_vue = new Vue({
                mixins: [wfabtVueMixin],
                components: {},
                data: {
                    modal: modal,
                    model: {},
                    experiment_id: 0,
                    state: 1,
                    publish_status: 'publish',
                },
                methods: {
                    publishVariant: function () {
                        let experiment_id = self.variant_settings_vue.experiment_id;
                        Vue.set(self.publish_variant_vue, 'publish_status', 'publishing');
                        admin_ajax.ajax("publish_variant", {'experiment_id': experiment_id, variant_id: variant_id, "_nonce": bwfabtParams.ajax_nonce_publish_variant});
                        admin_ajax.success = function (rsp) {
                            if (typeof rsp === "string") {
                                rsp = JSON.parse(rsp);
                            }
                            if (rsp.status === true) {
                                setTimeout(function () {
                                    Vue.set(self.publish_variant_vue, 'publish_status', 'published');
                                    Vue.set(self.variant_settings_vue.variants[variant_id], 'active', true);
                                    let draftVariant = self.variant_settings_vue.variants[variant_id].row_actions.publish;
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions, 'publish', {});
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions, 'draft', draftVariant);
                                    Vue.set(self.variant_settings_vue.variants[variant_id].row_actions.draft, 'text', bwfabt.variant_text.draft );
                                }, 3000);

                            } else {
                                setTimeout(function () {
                                    Vue.set(self.publish_variant_vue, 'publish_status', 'publish_error');
                                }, 3000);
                            }
                        };
                    }
                },
            }).$mount('#part_publish_variant_vue');
            self.publish_variant_vue.experiment_id = bwfabt.list_variants.experiment_id;
        };
            /**
             * Delete Variant Vue starts here
             */
            const delete_variant = function (modal, variant_id) {

                self.delete_variant_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {},
                    data: {
                        modal: modal,
                        model: {},
                        experiment_id: 0,
                        variantID: variant_id,
                        state: 1,
                        control_only: false,
                        delete_status: bwfabt.delete_variant.delete_status,
                    },
                    methods: {
                        deleteVariant: function () {
                            self.delete_variant_vue.delete_status = 'deleting';
                            admin_ajax.ajax("delete_variant", {
                                'experiment_id': self.variant_settings_vue.experiment_id,
                                'variant_id': variant_id,
                                "_nonce": bwfabtParams.ajax_nonce_delete_variant
                            });
                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        self.delete_variant_vue.delete_status = 'deleted';
                                        self.delete_variant_vue.control_only = rsp.control_only;
                                        Vue.delete(self.variant_settings_vue.variants, variant_id);
                                        self.variant_settings_vue.rearragneVariantsOrder();
                                        if (rsp.control_only) {
                                            Vue.set(self.variant_settings_vue.variants[rsp.control_id], 'traffic', '100.00');
                                            setTimeout(function () {
                                                $("#modal-delete-variant").iziModal('close');
                                            }, 2000);
                                        }
                                    }, 200);
                                } else {
                                    //handle error state
                                }
                            };
                        }
                    },
                }).$mount('#part_delete_variant_vue');
                self.delete_variant_vue.experiment_id = bwfabt.list_variants.experiment_id;
            };

            /** Adding variant ends here **/

            /** Vue Update traffic starts **/
            const update_traffic = function (modal) {
                self.update_traffic_vue = new Vue(
                    {
                        mixins: [wfabtVueMixin],
                        components: {
                            "vue-form-generator": VueFormGenerator.component
                        },
                        data: {
                            modal: modal,
                            schema: {},
                            variants: self.variant_settings_vue.getDefaultVariants(),
                            update_traffic: self.variant_settings_vue.update_traffic,
                            trafficRestore: self.variant_settings_vue.getDefaultVariantsTraffic(),
                            variant_id: 0,
                            experiment_id: self.variant_settings_vue.experiment_id,
                            state: 1,
                        },
                        methods: {
                            set_equal_traffic: function () {
                                let variant_count = this.getTotalVariantCount();

                                if (variant_count > 0) {
                                    let avg = Math.trunc(100 / variant_count);
                                    let total = 0;
                                    let exWeight = 0;
                                    if (100 > (avg * variant_count)) {
                                        exWeight = 100 - (avg * variant_count);
                                        console.log(self.update_traffic_vue.variants);
                                    }
                                    for (let variant_id in self.update_traffic_vue.variants) {
                                        if (true === self.update_traffic_vue.variants[variant_id].control) {
                                            self.update_traffic_vue.variants[variant_id].traffic = (avg + exWeight).toFixed(2);
                                        } else {
                                            self.update_traffic_vue.variants[variant_id].traffic = avg.toFixed(2);
                                        }
                                        total = parseFloat(total) + parseFloat(self.update_traffic_vue.variants[variant_id].traffic);
                                    }

                                    this.update_traffic.total_trf_value = total;
                                    this.update_traffic.InValid_traffic = false;
                                }
                                return false;
                            },
                            update_variant_traffic: function () {

                                let total = 0;
                                for (let variant_id in self.update_traffic_vue.variants) {
                                    total = parseFloat(total) + parseFloat(self.update_traffic_vue.variants[variant_id].traffic);
                                }
                                this.update_traffic.total_trf_value = total;

                                if ( 100 !== total ) {
                                    alert(bwfabt.update_traffic.update_data.traffic_error);
                                    this.update_traffic.InValid_traffic = 2;
                                    return false;
                                } else {
                                    let traffics = [];
                                    for (let variant_id in self.update_traffic_vue.variants) {
                                        traffics.push({variant_id: variant_id, traffic: self.update_traffic_vue.variants[variant_id].traffic});
                                        this.trafficRestore[variant_id] = self.update_traffic_vue.variants[variant_id].traffic;
                                    }
                                    this.update_traffic.InValid_traffic = false;
                                    self.update_traffic_vue.state = 2;
                                    admin_ajax.ajax("update_traffic", {
                                        'experiment_id': self.update_traffic_vue.experiment_id,
                                        'traffics': traffics,
                                        "_nonce": bwfabtParams.ajax_nonce_update_traffic
                                    });

                                    admin_ajax.success = function (rsp) {
                                        if (typeof rsp === "string") {
                                            rsp = JSON.parse(rsp);
                                        }
                                        if (rsp.status === true) {
                                            setTimeout(function () {
                                                self.update_traffic_vue.state = 3;
                                            }, 2000);
                                            setTimeout(function () {
                                                $('#modal_update_traffic').iziModal('close');
                                                location.reload();
                                            }, 4000);
                                        } else {
                                            $(".bwfabt-update-traffic-resposne").html(rsp.msg);
                                        }
                                    };
                                }
                            },
                            controlTraffic: function (variant_id, event) { //On keyup to make sure traffic in always upto 100 while entering
                                let traffic = event.target.value;
                                if (variant_id > 0) {
                                    traffic = ('' === traffic || undefined === traffic) ? 0 : traffic;
                                    traffic = (traffic > 99.99) ? 100.00 : traffic;
                                    self.update_traffic_vue.variants[variant_id].traffic = traffic;
                                }
                            },
                            fixTraffic: function (variant_id, event) { //On focusout to parse traffic upto 2 decimal point while leaving the field
                                let traffic = event.target.value;
                                if (variant_id > 0) {
                                    traffic = ('' === traffic || undefined === traffic) ? parseFloat(0) : parseFloat(traffic);
                                    traffic = (traffic > 99.99) ? 100.00 : traffic;
                                    self.update_traffic_vue.variants[variant_id].traffic = traffic.toFixed(2);
                                }
                            },
                            calculate_traffic: function () { //On mounted and updated to show error and disable update button on error
                                let total = 0;
                                let hasError = false;

                                for (let variant_id in this.variants) {

                                    if (0 == this.variants[variant_id].traffic) {
                                        hasError = true;
                                    }
                                    total = parseFloat(total) + parseFloat(this.variants[variant_id].traffic);
                                }
                                this.update_traffic.total_trf_value = total.toFixed(2);

                                if (100 !== total) {
                                    Vue.set(this.update_traffic, 'InValid_traffic', 2);
                                } else if (true === hasError) {
                                    Vue.set(this.update_traffic, 'InValid_traffic', 1);
                                } else {
                                    Vue.set(this.update_traffic, 'InValid_traffic', false);
                                }
                            },
                            getVariantColor: function (variant_id) {
                                return self.variant_settings_vue.getVariantColor(variant_id);
                            },
                            getTotalVariantCount: function () {
                                return Object.keys(this.variants).length;
                            },
                            close_update_traffic: function () {
                                $('#modal_update_traffic').iziModal('close');
                            },
                            normalizeTraffic: function () { //On close to restore traffic to previous value if not updated
                                for (let variant_id in this.trafficRestore) {
                                    self.update_traffic_vue.variants[variant_id].traffic = this.trafficRestore[variant_id];
                                }
                            },
                        },
                        updated: function () {
                            this.calculate_traffic();
                        },
                        mounted: function () {
                            this.calculate_traffic();
                        }
                    }
                ).$mount("#modal_update_traffic_vue");

            };
            /** Vue Update traffic ends here **/

            /** Vue Checking readiness starts here **/
            const check_readiness = function () {

                self.check_readiness_vue = new Vue(
                    {
                        mixins: [wfabtVueMixin],
                        components: {
                            "vue-form-generator": VueFormGenerator.component
                        },
                        data: {
                            modal: false,
                            schema: {},
                            experiment_id: 0,
                            readiness_state: 0,
                            no_variant: false,
                            InValid_traffic: false,
                            inactive_variant: false,
                            unable_to_start: false,
                            starting_text: '',
                            message: '',
                        },
                        methods: {
                            closeReadiness: function () {
                                $('#modal_start_experiment').iziModal('resetContent');
                                $('#modal_start_experiment').iziModal('close');
                            },
                            goLive: function () {
                                self.check_readiness_vue.readiness_state = 1;
                                self.check_readiness_vue.starting_text = bwfabt.start_experiment.starting;

                                let status = self.variant_settings_vue.experiment_status;
                                let experiment_id = self.variant_settings_vue.experiment_id;

                                admin_ajax.ajax("start_experiment", {
                                    'experiment_id': experiment_id,
                                    'status': status,
                                    "_nonce": bwfabtParams.ajax_nonce_start_experiment
                                });

                                admin_ajax.success = function (rsp) {
                                    if (typeof rsp === "string") {
                                        rsp = JSON.parse(rsp);
                                    }

                                    if (rsp.status === true) {
                                        setTimeout(function () {
                                            self.check_readiness_vue.readiness_state = 4;
                                        }, 2000);
                                        setTimeout(function () {
                                            location.reload();
                                        }, 4000);
                                    } else {
                                        setTimeout(function () {
                                            self.check_readiness_vue.unable_to_start = true;
                                            self.check_readiness_vue.readiness_state = 2;
                                        }, 5000);
                                    }
                                };
                            },
                        },

                    }).$mount('#bwfabt-readiness-vue');
                self.check_readiness_vue.readiness_state = self.variant_settings_vue.readiness_state;
            };
            /** Vue Checking readiness ends here **/

            /**
             * Reset Stats Vue starts here
             */
            const reset_stats = function (modal, experiment_id) {

                self.reset_stats_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {},
                    data: {
                        modal: modal,
                        model: {},
                        experiment_id: 0,
                        reset_status: bwfabt.reset_stats.reset_status,
                        experiment_status: '',
                    },
                    methods: {
                        resetStats: function () {
                            self.reset_stats_vue.reset_status = 'resetting';
                            admin_ajax.ajax("reset_stats", {
                                'experiment_id': experiment_id,
                                "_nonce": bwfabtParams.ajax_nonce_reset_stats
                            });
                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        self.reset_stats_vue.reset_status = 'reset_complete';
                                    }, 2000);
                                    setTimeout(function () {
                                        window.location = rsp.redirect_url;
                                        $("#modal-reset-stats").iziModal('close');
                                    }, 4000);
                                } else {
                                    //handle error state
                                }
                            };
                        }
                    },
                }).$mount('#part_reset_stats_vue');
                self.reset_stats_vue.experiment_id = bwfabt.list_variants.experiment_id;
                self.reset_stats_vue.experiment_status = self.variant_settings_vue.experiment_status;
            };
            /** Reset Stats Vue ends here */

            /**
             * Stop Experiment Vue starts here
             */
            const stop_experiment = function (modal, experiment_id) {
                self.stop_experiment_vue = new Vue({
                    mixins: [wfabtVueMixin],
                    components: {},
                    data: {
                        modal: modal,
                        model: {},
                        experiment_id: 0,
                        stop_status: bwfabt.stop_experiment.stop_status,
                    },
                    methods: {
                        stoppingExperiment: function () {
                            console.log('clicked on stopping');
                            admin_ajax.ajax("stop_experiment", {
                                'experiment_id': experiment_id,
                                "_nonce": bwfabtParams.ajax_nonce_stop_experiment
                            });
                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        self.stop_experiment_vue.stop_status = 'paused';
                                    }, 2000);
                                    setTimeout(function () {
                                        location.reload();
                                        $("#modal_stop_experiment").iziModal('close');
                                    }, 4000);
                                } else {
                                    //handle error state
                                }
                            };
                        }
                    },
                    mounted: function () {
                        console.log('clicked on mounted');
                        this.stoppingExperiment();
                    }
                }).$mount('#part_stop_experiment_vue');
                self.stop_experiment_vue.experiment_id = bwfabt.list_variants.experiment_id;
            };
            /** Stop experiment Vue ends here */


            /***
             * For expanding/collapsing funnel offers on analytics screen
             */
            $(".accordionItemHeading").on('click', function () {
                let getElem = $(this).parents(".accordionItem").eq(0);
                if (true === getElem.hasClass('close')) {
                    getElem.addClass('open');
                    getElem.removeClass('close');
                } else {
                    getElem.addClass('close');
                    getElem.removeClass('open');
                }
            });
            /*For expanding/collapsing funnel offers on analytics screen ends here */

            /**
             * Analytics: choose winner starts here
             */
            if ($("#modal_choose_winner").length > 0) {
                $("#modal_choose_winner").iziModal({
                    padding: 0,
                    width: 680,
                    timeoutProgressbar: true,
                    radius: 10,
                    bottom: 'auto',
                    closeButton: false,
                    pauseOnHover: false,
                    overlay: true,
                    onOpened: function () {
                        let winner_id = 0;
                        let experiment_id = 0;
                        let winner_title;
                        $('.close_p.wfabt_btn').on('click', function () {
                            $("#modal_choose_winner").iziModal('resetContent');
                            $("#modal_choose_winner").iziModal('close');
                        });

                        $('.choose_ab_winner').on('click', function () {
                            $('.choose_ab_winner').removeClass('wfabt_bg_act');
                            $(this).addClass('wfabt_bg_act');
                            winner_id = $(this).attr('data-funnel_id');
                            experiment_id = $('.wfabt_make_winner').attr('data-experiment_id');
                            $(".wfabt_make_winner").removeClass("wfab_winner_disabled");
                            $(".wfabt_make_winner_help").hide();
                        });

                        $('.wfabt_make_winner').on('click', function () {
                            if ($(this).hasClass("wfab_winner_disabled")) {
                                return;
                            }
                            if (winner_id > 0) {
                                winner_title = bwfabt.list_variants.variants[winner_id].title;
                                $('#choose_winner').addClass('bwfabt-hide');
                                $('#confirm_winner').removeClass('bwfabt-hide');
                                $('.winner-name').html(winner_title);
                            }
                            $('.winner_error').removeClass('bwfabt-hide');
                            setTimeout(function () {
                                $('.winner_error').addClass('bwfabt-hide');
                            }, 2000);
                        });

                        $('.wfabt_make_confirm.wfabt_btn').on('click', function () {
                            $('#confirm_winner').addClass('bwfabt-hide');
                            $('#choosing_winner').removeClass('bwfabt-hide');
                            if (experiment_id < 1 || winner_id < 1) {
                                setTimeout(function () {
                                    $('#choosing_winner').addClass('bwfabt-hide');
                                    $('#winner_not_selected').removeClass('bwfabt-hide');
                                }, 2000);
                                return false;
                            }

                            admin_ajax.ajax("choose_winner", {
                                'experiment_id': experiment_id,
                                'winner': winner_id,
                                "_nonce": bwfabtParams.ajax_nonce_choose_winner
                            });

                            admin_ajax.success = function (rsp) {
                                if (typeof rsp === "string") {
                                    rsp = JSON.parse(rsp);
                                }
                                if (rsp.status === true) {
                                    setTimeout(function () {
                                        $('#choosing_winner').addClass('bwfabt-hide');
                                        $('#real_winner').removeClass('bwfabt-hide');
                                        $('#real_winner').find('.deleclared-winner').html(winner_title);
                                    }, 2000);
                                    setTimeout(function () {
                                        location.reload();
                                    }, 5000);
                                } else {
                                    setTimeout(function () {
                                        $('#choosing_winner').addClass('bwfabt-hide');
                                        $('#winner_not_selected').removeClass('bwfabt-hide');
                                    }, 3000);
                                }
                            };

                        });
                    },

                });
            }

            /**
             * Analytics: choose winner ends here
             */

            /**
             *To draw chart on anlaytics screen starts
             */

            if ($('#bwfabt_chart').length > 0) {

                let chart = DoCharts(bwfabtChart.defaults.stats, bwfabtChart.defaults.frequency);
                $('.bwfabt-params').on('change', function () {
                    const frequency = $('#bwfabt_frequency').val();
                    const stats = $('#bwfabt_stats').val();
                    DoCharts(stats, frequency, chart);
                });
            }


            function DoCharts(x_control, y_control, chart) {
                let bwfabt_chart = document.getElementById('bwfabt_chart').getContext('2d');

                let data_sets = [];
                let x_data = [];  //Labels on x-axis i.e dates
                let y_data = []; //Data on y-axis, like conversion rate, total conversion
                let frequency = y_control;
                let stats = x_control;

                if (bwfabtChart.dates[frequency].length === 1) {
                    x_data.push('0');
                }
                for (let date_index in bwfabtChart.dates[frequency]) {
                    x_data.push(bwfabtChart.dates[frequency][date_index]);
                }
                if (bwfabtChart.dates[frequency].length === 1) {
                    x_data.push('0');
                }

                for (let variant_id in bwfabtChart.variant_title) {
                    y_data = [];
                    if (1 === bwfabtChart.dates[frequency].length) {
                        y_data.push(NaN);
                    }
                    for (let date_index in bwfabtChart.dates[frequency]) {
                        y_data.push(bwfabtChart[stats][frequency][variant_id][bwfabtChart.dates[frequency][date_index]]);
                    }
                    if (1 === bwfabtChart.dates[frequency].length) {
                        y_data.push(NaN);
                    }

                    data_sets.push({
                        'data': y_data,
                        'label': self.variant_settings_vue.decodeHtml(bwfabtChart.variant_title[variant_id]),
                        'borderColor': bwfabtChart.variant_colors[variant_id],
                        'backgroundColor': bwfabtChart.variant_colors[variant_id],
                        'fill': false,
                        'lineTension': 0.4,
                    });
                }

                if (typeof chart === "undefined") {
                    chart = new Chart(bwfabt_chart, {
                        type: 'line',
                        data: {
                            labels: x_data,
                            datasets: data_sets,
                        },
                        options: getChartOptions(x_control, y_control),
                    });
                } else {
                    chart.data.labels = x_data;
                    chart.data.datasets = data_sets;
                    chart.options = getChartOptions(x_control, y_control);
                    chart.update();
                }
                return chart;
            }

            function getChartOptions(x_control, y_control) {
                let options = bwfabtChart.defaults.options;
                if (wfabtHP(options, 'scales') && wfabtHP(options.scales, 'xAxes') && wfabtHP(options.scales.xAxes[0], 'scaleLabel') && wfabtHP(options.scales.xAxes[0].scaleLabel, 'labelString')) {
                    options.scales.xAxes[0].scaleLabel.labelString = bwfabtChart.chart_frequencies_chart_labels[y_control];
                }
                if (wfabtHP(options, 'scales') && wfabtHP(options.scales, 'yAxes') && wfabtHP(options.scales.yAxes[0], 'scaleLabel') && wfabtHP(options.scales.yAxes[0].scaleLabel, 'labelString')) {

                    options.scales.yAxes[0].scaleLabel.labelString = bwfabtChart.stats_head_chart_labels[x_control];
                }

                return options;
            }

            /**
             * To draw chart on anlytics screen ends
             */

            /**
             * Sometime having issues inMac/Safari about loader sticking infinitely
             */
            if (document.readyState == 'complete') {
                if ($('.bwfabt-loader').length > 0) {
                    $('.bwfabt-loader').each(function () {
                        let $this = $(this);

                        if ($this.is(":visible")) {
                            setTimeout(function ($this) {
                                $this.remove();
                            }, 400, $this);
                        }
                    });
                }
            } else {
                $(window).bind('load', function () {
                    if ($('.bwfabt-loader').length > 0) {
                        $('.bwfabt-loader').each(function () {
                            let $this = $(this);

                            if ($this.is(":visible")) {
                                setTimeout(function ($this) {
                                    $this.remove();
                                }, 400, $this);
                            }
                        });
                    }
                });
            }

            function removeHiddenExpElem() {
                if ($('.wfabt_new_expr_sec').length > 0 && $('.wfabt_new_expr_sec').hasClass('bwfabt_hide')) {
                    $('.wfabt_new_expr_sec').removeClass('bwfabt_hide');
                }
                if ($('.wfabt_first_experiment_box').length > 0 && $('.wfabt_first_experiment_box').hasClass('bwfabt_hide')) {
                    $('.wfabt_first_experiment_box').removeClass('bwfabt_hide');
                }
            }

            if ($('#e-admin-top-bar-root').length > 0) {
               $('#e-admin-top-bar-root').addClass('e-admin-top-bar--inactive');
            }
            removeHiddenExpElem();

        }//document.ready
    );
})(jQuery);
