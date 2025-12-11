/*eslint-env jquery*/
/*global wfocu*/
/*global wfocuParams*/
/*global Vue*/
/*global VueFormGenerator*/
/*global wp_admin_ajax*/
(function ($) {
    'use strict';

    $(window).on('load', function () {
            /***/
            jQuery(document.body).on('click', '.have_variation_expand', function () {
                jQuery(this).parent().siblings("#variant_product_id").slideDown(200, 'linear');
            });
            jQuery(document.body).on('click', '.have_variation_close', function () {
                jQuery(this).parent().siblings("#variant_product_id").slideUp(200);
            });

            jQuery(document.body).on('click', '.have_scheme_expand', function () {
                jQuery(this).parent().siblings("#scheme_product_id").slideDown(200, 'linear');
            });
            jQuery(document.body).on('click', '.have_scheme_close', function () {
                jQuery(this).parent().siblings("#scheme_product_id").slideUp(200);
            });

            /**/

            let add_funnel = false;
            let add_funnel_setting = false;

            function get_vuw_fields() {
                let update_step_settings = [{
                    type: "input",
                    inputType: "text",
                    label: "",
                    model: "funnel_name",
                    inputName: 'funnel_name',
                    featured: true,
                    // required: true,
                    placeholder: "",
                    validator: VueFormGenerator.validators.string
                }, {
                    type: "textArea",
                    label: "",
                    model: "funnel_desc",
                    inputName: 'funnel_desc',
                    featured: true,
                    rows: 3,
                    placeholder: ""
                }];

                for (let keyfields in update_step_settings) {
                    let model = update_step_settings[keyfields].model;

                    $.extend(update_step_settings[keyfields], wfocu.add_funnel.label_texts[model]);

                }
                return update_step_settings;
            }

            let vue_add_funnel = function (modal) {
                if (add_funnel === true) {
                    return add_funnel_setting;
                }
                add_funnel = true;
                add_funnel_setting = new Vue({
                    el: "#part-add-funnel",
                    components: {
                        "vue-form-generator": VueFormGenerator.component
                    },
                    data: {
                        modal: modal,
                        model: {
                            funnel_name: "",
                            funnel_desc: "",
                        },
                        schema: {
                            fields: get_vuw_fields(),
                        },
                        formOptions: {
                            validateAfterLoad: false,
                            validateAfterChanged: true,
                        }
                    }
                });
                return add_funnel_setting;
            };
            if ($("#modal-add-funnel").length > 0) {
                $("#modal-add-funnel").iziModal({
                    title: 'New Upsell Funnel',
                    headerColor: '#f9fdff',
                    background: '#efefef',
                    borderBottom: false,
                    history: false,
                    radius: 6,
                    width: 600,
                    overlayColor: 'rgba(0, 0, 0, 0.35)',
                    transitionIn: 'fadeInUp',
                    transitionOut: 'fadeOut',
                    navigateCaption: true,
                    navigateArrows: "false",
                    onOpening: function (modal) {
                        vue_add_funnel(modal);
                    },
                    onOpened: function (modal) {

                    },
                });
            }

            if ($(".wfocu_add_funnel").length > 0) {
                new wp_admin_ajax('.wfocu_add_funnel', true, function (ajax) {
                    ajax.before_send = function () {
                        if (ajax.action === 'wfocu_add_new_funnel') {
                            $('.wfocu_submit_btn_style').text(wfocu.add_funnel.creating);

                        }
                    };
                    ajax.success = function (rsp) {
                        if (typeof rsp === "string") {
                            rsp = JSON.parse(rsp);
                        }
                        if (ajax.action === 'wfocu_add_new_funnel') {

                            if (rsp.status === true) {
                                $('form.wfocu_add_funnel').hide();
                                $('.wfocu-funnel-create-success-wrap').show();
                                // $('.wfocu_form_response').html(rsp.msg);
                                setTimeout(function () {
                                    window.location.href = rsp.redirect_url;
                                }, 3000);

                            } else {
                                $('.wfocu_form_response').html(rsp.msg);
                            }

                        }
                    };
                });
            }
            $(".wfocu-preview").on("click", function () {
                let funnel_id = $(this).attr('data-funnel-id');
                let elem = $(this);
                elem.addClass('disabled');
                if (funnel_id > 0) {
                    let wp_ajax = new wp_admin_ajax();
                    wp_ajax.ajax("preview_details", {'funnel_id': funnel_id, "_nonce": wfocuParams.ajax_nonce_preview_details});
                    wp_ajax.success = function (rsp) {

                        $(this).WCBackboneModal({
                            template: 'wfocu-funnel-popup',
                            variable: rsp
                        });
                        elem.removeClass('disabled');
                    };
                }
                return false;
            });

            $(".wfocu-duplicate").on("click", function () {
                let funnel_id = $(this).attr('data-funnel-id');

                let elem = $(this);
                elem.addClass('disabled');
                if (funnel_id > 0) {
                    let wp_ajax = new wp_admin_ajax();
                    wp_ajax.ajax("duplicate_funnel", {'funnel_id': funnel_id, "_nonce": wfocuParams.ajax_nonce_duplicate_funnel});

                    $("#modal-duplicate-funnel").iziModal({
                            title: 'Duplicating funnel...',
                            icon: 'icon-check',
                            headerColor: '#f9fdff',
                            background: '#f9fdff',
                            borderBottom: false,
                            width: 600,
                            timeoutProgressbar: true,
                            transitionIn: 'fadeInUp',
                            transitionOut: 'fadeOut',
                            bottom: 400,
                            loop: true,
                            closeButton: false,
                            pauseOnHover: false,
                            overlay: true
                        }
                    );
                    $('#modal-duplicate-funnel').iziModal('open');
                    wp_ajax.success = function (rsp) {
                        if (typeof rsp === "string") {
                            rsp = JSON.parse(rsp);
                        }
                        if (rsp.status === true) {
                            $("#modal-duplicate-funnel").iziModal('setTitle', wfocu.funnel_duplicate.success);
                            setTimeout(function () {
                                $('#modal-duplicate-funnel').iziModal('close');
                                location.reload();
                            }, 3000);
                        } else {
                            $("#modal-duplicate-funnel").iziModal('setTitle', rsp.msg);
                            setTimeout(function () {
                                $('#modal-duplicate-funnel').iziModal('close');
                                location.reload();
                            }, 3000);
                        }
                        elem.removeClass('disabled');
                    };
                }
                return false;
            });

            function handleNotices() {
                $('div.updated, div.error, div.notice').not('.inline, .below-h2').insertBefore($("#poststuff"));
            }

            /**
             * Sometime having issues in Mac/Safari about loader sticking infinitely
             */
            if (document.readyState == 'complete' || document.readyState == 'loading') {
                if ($('.wfocu-loader').length > 0) {
                    $('.wfocu-loader').each(function () {
                        let $this = $(this);
                        if ($this.is(":visible")) {
                            $('#wfocu_step_design .wfocu_template').removeClass('wfocu-hide');
                            $this.remove();
                        }
                    });

                }
                handleNotices();
            } else {

                $(window).on('load', function () {

                    if ($('.wfocu-loader').length > 0) {
                        $('.wfocu-loader').each(function () {
                            let $this = $(this);

                            if ($this.is(":visible")) {

                                setTimeout(function ($this) {

                                    $this.remove();
                                }, 400, $this);

                            }
                        });
                    }
                    handleNotices();
                });
            }
            /** Metabox panel close */
            $(".wfocu_allow_panel_close .hndle").on("click", function () {
                var parentPanel = $(this).parents(".wfocu_allow_panel_close");
                parentPanel.toggleClass("closed");
            });

            function wfocu_toggle_table() {
                if ($(".wfocu-instance-table .wfocu-tgl,.funnel_state_toggle .wfocu-tgl").length > 0) {
                    $(".wfocu-instance-table .wfocu-tgl,.funnel_state_toggle .wfocu-tgl").on(
                        'click', function () {
                            let checkedVal = this.checked.toString();
                            if (true === this.checked) {
                                if ($('.wfocu_head_funnel_state_on').length > 0) {
                                    $('.wfocu_head_funnel_state_on').show();
                                }
                                if ($('.wfocu_head_funnel_state_off').length > 0) {
                                    $('.wfocu_head_funnel_state_off').hide();
                                }
                                if ($('.wfocu_head_mr').length) {
                                    $('.wfocu_head_mr').attr('data-status', 'live');
                                }

                            } else {
                                if ($('.wfocu_head_funnel_state_on').length > 0) {
                                    $('.wfocu_head_funnel_state_on').hide();
                                }
                                if ($('.wfocu_head_funnel_state_off').length > 0) {
                                    $('.wfocu_head_funnel_state_off').show();
                                }
                                if ($('.wfocu_head_mr').length > 0) {
                                    $('.wfocu_head_mr').attr('data-status', 'sandbox');
                                }
                            }
                            let ajaxRequest = $.post(
                                wfocuParams.ajax_url, {
                                    'state': checkedVal, 'id': $(this).attr('data-id'), 'action': 'wfocu_toggle_funnel_state',
                                    '_nonce': wfocuParams.ajax_nonce_toggle_funnel_state
                                }
                            );

                            ajaxRequest.done(function() {
                                $("#modal-global-settings_success").iziModal( 'open' );
                            });

                            let statusWrapper = this.closest('.wffn-ellipsis-menu');
                            if( statusWrapper ) {
                                if( this.checked ) {
                                    $(statusWrapper).find('.bwfan-tag-rounded').removeClass('clr-orange').addClass('clr-green');
                                    $(statusWrapper).find('.bwfan-funnel-status').text('Published');
                                    $(statusWrapper).find('.bwfan-status-toggle').text('Draft');
                                    
                                } else {
                                    $(statusWrapper).find('.bwfan-tag-rounded').removeClass('clr-green').addClass('clr-orange');
                                    $(statusWrapper).find('.bwfan-funnel-status').text('Draft');
                                    $(statusWrapper).find('.bwfan-status-toggle').text('Publish');
                                }
                            }
                        }
                    );

                    if( $('#modal-global-settings_success').length > 0 ) {
                        $("#modal-global-settings_success").iziModal(
                            {
                                title: 'Changes saved',
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
                }
            }

            wfocu_toggle_table();

            function init_update_funnel_ajax(modal) {
                new wp_admin_ajax(
                    '.wfocu_forms_update_funnel', true, function (ajax) {
                        ajax.before_send = function () {
                            if( modal.$wrap ) {
                                let submitButton = modal.$wrap.find( '.wfocu_btn_primary.wfocu_btn' );
                                $( submitButton ).addClass( 'is_busy' );
                            }
                        };
                        ajax.data = function (data) {
                            data.append('funnel_id', wfocu.id);

                            return data;
                        };
                        ajax.success = function (rsp) {
                            if (typeof rsp === "string") {
                                rsp = JSON.parse(rsp);
                            }
                            if (ajax.action === 'wfocu_update_funnel') {
                                if (rsp.status === true) {

                                    if (rsp.data.name !== '') {
                                        wfocu.funnel_name = rsp.data.name;
                                        $(".bwf_breadcrumb ul li:last-child").text(rsp.data.name);
                                        $(".bwf_breadcrumb > span:last-of-type").text(rsp.data.name);
                                        $('.bwfan_page_header .bwfan_page_title').text(rsp.data.name);
                                        wfocu.funnel_desc = rsp.data.desc;
                                    }


                                }
                                $("#modal-global-settings_success").iziModal( 'open' );
                                if( modal.$wrap ) {
                                    let submitButton = modal.$wrap.find( '.wfocu_btn_primary.wfocu_btn' );
                                    $( submitButton ).removeClass( 'is_busy' );
                                }
                                $("#modal-update-funnel").iziModal('close');
                            }
                        }
                    }
                );
            }


            let funnel_div = $("#modal-update-funnel");
            if (funnel_div.length > 0) {
                funnel_div.iziModal(
                    {
                        title: wfocuParams.modal_funnel_div,
                        headerColor: '#f9fdff',
                        background: '#efefef',
                        borderBottom: false,
                        history: false,
                        width: 600,
                        radius: 6,
                        overlayColor: 'rgba(0, 0, 0, 0.35)',
                        transitionIn: 'fadeInUp',
                        transitionOut: 'fadeOut',
                        navigateCaption: true,
                        navigateArrows: "false",
                        onOpening: function (modal) {
                            $('#funnel-name').val(wfocu.funnel_name);
                            $('#funnel-desc').val(wfocu.funnel_desc);
                            init_update_funnel_ajax(modal);
                        },
                    }
                );
            }


            $('.woofunnels_page_upstroke .icl_translations a').on('click', function (e) {
                let otgs_add = $(this).find('.otgs-ico-add');
                if (otgs_add.length > 0) {
                    let href = $(this).attr('href');
                    href = href.replace('post-new.php?', '');
                    e.preventDefault();
                    href = $.parseParams(href);
                    if (typeof href === 'object' && $.ol(href) > 0) {
                        e.preventDefault();
                        let wp_ajax = new wp_admin_ajax();
                        wp_ajax.ajax("make_wpml_duplicate", {'href': href, "_nonce": wfocuParams.ajax_nonce_make_wpml_duplicate});
                        wp_ajax.success = function (rsp) {
                            if (rsp.status === true) {
                                window.location.href = rsp.redirect_url;
                            } else {
                                alert(rsp.msg);
                            }
                        };
                    }
                }

                let otgs_edit = $(this).find('.otgs-ico-edit');
                if (otgs_edit.length > 0) {
                    let href_base = $(this).attr('href');
                    let href = href_base.replace('post.php?', '');
                    e.preventDefault();
                    href = $.parseParams(href);
                    if (typeof href === 'object' && $.ol(href) > 0) {
                        e.preventDefault();
                        let wp_ajax = new wp_admin_ajax();
                        wp_ajax.ajax("get_wpml_edit_url", {'href': href, "_nonce": wfocuParams.ajax_nonce_get_wpml_edit_url});
                        wp_ajax.success = function (rsp) {
                            if (rsp.status === true) {
                                window.location.href = rsp.redirect_url;
                            } else {
                                window.location.href = href_base;
                            }
                        };
                    }
                }
            });

            $.parseParams = function (query) {
                var decodeRE = /\+/g;  // Regex for replacing addition symbol with a space
                var decode = function (str) {
                    return decodeURIComponent(str.replace(decodeRE, " "));
                };
                var params = {}, e;
                var re = /([^&=]+)=?([^&]*)/g;
                while ((e = re.exec(query))) {
                    var k = decode(e[1]), v = decode(e[2]);
                    if (k.substring(k.length - 2) === '[]') {
                        k = k.substring(0, k.length - 2);
                        (params[k] || (params[k] = [])).push(v);
                    } else params[k] = v;
                }
                return params;
            };
            $.ol = function (obj) {
                let c = 0;
                if (obj != null && typeof obj === "object") {
                    c = Object.keys(obj).length;
                }
                return c;
            };

            $(document).on('click', '.bwf-ellipsis-menu.bwf-ellipsis--alter-alter .bwf-ellipsis-menu__toggle', function () {
                $(this).parents('.bwf-ellipsis--alter-alter').toggleClass('show-menu');
            });
            $(document).on('click', function (e) {
                if ($('.bwf-ellipsis-menu.bwf-ellipsis--alter-alter').length) {
                    if (!e.target.matches('.bwf-ellipsis-menu__toggle') && !e.target.closest('.bwf-ellipsis-menu__toggle')) {
                        $('.bwf-ellipsis-menu.bwf-ellipsis--alter-alter').removeClass('show-menu');
                    }
                }

                if( e.target.matches( '.wffn-ellipsis-menu' ) ) {
                    $(e.target).toggleClass('is-opened');
                } else if ( e.target.closest('.wffn-ellipsis-menu') ) {
                    if( e.target.closest( '.wffn-ellipsis-menu-dropdown' ) ) {
                        $(e.target.closest('.wffn-ellipsis-menu')).removeClass('is-opened');
                    } else {
                        $(e.target.closest('.wffn-ellipsis-menu')).toggleClass('is-opened');
                    }
                } else {
                    if( $('.wffn-ellipsis-menu').length ) {
                        $('.wffn-ellipsis-menu').removeClass('is-opened');
                    }
                }
            });

            if ($('#e-admin-top-bar-root').length > 0) {
                $('#e-admin-top-bar-root').addClass('e-admin-top-bar--inactive');
            }

        } //window load
    )


})(jQuery);

