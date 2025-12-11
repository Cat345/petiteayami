(function ($) {
    if ('yes' === wfacp_frontend.is_user_logged_in) {
        return;
    }

    class WfacpModalHandler {
        constructor() {

            this.modalSelector = "#funnelkitLoginModal";
            this.initializeModal();
        }

        initializeModal() {
            const self = this; // Store 'this' in 'self'
            $(document).on("click", "#funnelkitLoginModalToggler", (e) => this.showModal(e))
                .on("click", `${this.modalSelector} .wfacp-quickv-close`, (e) => this.hideModal(e),)
                .on("click", "body", (e) => this.hideModalOnOutsideClick(e));


            $(window).on('load', function () {
                $(document.body).off("click", "a.wfacp_display_smart_login");
                $(document.body).on("click", "a.wfacp_display_smart_login", (e) => {
                    self.showModal(e);
                });
            });
        }


        showModal(e) {
            e.preventDefault();

            $('html').attr('style', 'overflow: hidden !important');
            $('body').addClass('wfacp-quickv-login-active');
            $('.wfacp-quickv-opacity').show();
            var wf_quick_view_panel = $('.wfacp-quickv-panel');
            wf_quick_view_panel.addClass('wfacp-quickv-panel-active');
            wf_quick_view_panel.find('.wfacp-quickv-opl').addClass('wfacp-quickv-pl-active');

            $("#funnelkitResetPasswordForm").hide();
            $(".reset-password-success").remove();
        }

        hideModal(e) {
            e.preventDefault();
            $('html').attr('style', 'overflow: auto !important');
            $('.wfacp-quickv-opacity').hide();
            $('.wfacp-quickv-panel').removeClass('wfacp-quickv-panel-active');
            $("#funnelkitLoginForm").show();
            $("#funnelkitResetPasswordForm").show();
            $('body').removeClass('wfacp-quickv-login-active');
            $('.wfacp-quickv-content_wrap').removeClass('wfacp-reset-pass-message');
            /* empty error list */
            $(".wfacp_notice_list").html('');

        }

        hideModalOnOutsideClick(e) {
            const modal = $(this.modalSelector)[0];
            if (e.target === modal) {
                $('.wfacp-quickv-opacity').hide();
                $('.wfacp-quickv-panel').removeClass('wfacp-quickv-panel-active');
            }
        }
    }


    class WfacpUserEmailChecker {
        constructor() {
            this.rate_limit = false;
            this.field_key = $(".wfacp-section #billing_email");
            this.ajaxUrl = wfacp_frontend.admin_ajax;
            this.nonce = wfacp_frontend.nonce;
            this.debounceDelay = 1000;
            this.timeout = null;
            $(document).ready(this.init.bind(this));
        }

        // Initialize event listeners
        init() {
            if (this.field_key.length > 0) {
                let email_field_value = this.field_key.val();
                if (email_field_value != '') {

                    this.validate_email(email_field_value);
                }

            }

            this.field_key.on("keyup", (event) => {
                $("#funnelkitLoginAction").remove();
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    const email = $(event.target).val();
                    this.validate_email(email);
                }, this.debounceDelay);
            });
        }

        validate_email(email) {
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.checkUserByEmail(email);
            }
        }

        // Check user by email
        checkUserByEmail(email) {
            if (true === this.rate_limit) {
                return;
            }

            $.ajax({
                url: this.ajaxUrl,
                type: "post",
                data: {
                    action: "funnelkit_search_customer",
                    nonce: this.nonce,
                    email: email,
                    page_id: wfacp_frontend.id,
                },
                beforeSend: () => this.beforeSendHandler(),
                success: (response) => this.successHandler(response),
                error: (jqXHR, textStatus, errorThrown) =>
                    this.errorHandler(jqXHR, textStatus, errorThrown),
            });
        }

        // Placeholder methods for callbacks, or you can define actual logic here
        beforeSendHandler() {
            $("#funnelkitLoginAction").remove();
        }

        successHandler(response) {
            if (response.data.hasOwnProperty('rate_limit') && 'yes' === response.data.rate_limit) {
                this.rate_limit = true;
            }
            let username_field = $('#funnelkitLoginModal input[name="username"]');
            if (undefined !== response.data.email_id) {
                username_field.val(response.data.email_id);
                username_field.parents('.wfacp-form-control-wrapper').addClass('wfacp-anim-wrap');
            } else {
                $('#funnelkitLoginModal input[name="username"]').val('');
                username_field.parents('.wfacp-form-control-wrapper').removeClass('wfacp-anim-wrap');
            }

            if (response.success == true) {
                $(".wfacp-section #billing_email_field").after(response.data.html);

                setTimeout(function () {
                    $(".wfacp-section .wfacp-search-wrap").addClass("wfacp-show-field");
                }, 30);

                $("#wfacp-sec-wrapper .woocommerce-account-fields").addClass('wfacp-hide-element');
            } else {


                $("#wfacp-sec-wrapper .woocommerce-account-fields").removeClass('wfacp-hide-element');
                $(".wfacp-section .wfacp-search-wrap").removeClass("wfacp-show-field");
                setTimeout(function () {
                    $(".wfacp-section .wfacp-search-wrap").remove();
                }, 30);

            }
        }


        errorHandler(jqXHR, textStatus, errorThrown) {
            $("#funnelkitLoginAction").remove();
        }

        debounce(func, wait) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    }

    class WfacpLoginHandler {
        constructor() {
            this.login_form = $("#funnelkitLoginForm");
            this.ajaxUrl = wfacp_frontend.admin_ajax;
            this.nonce = wfacp_frontend.nonce;
            this.init();
        }


        // Initialize event listeners
        init() {
            $("#funnelkitLoginForm").on("submit", (e) => this.handleLogin(e));
            $(".funnelkit-LostPassword").on("click", function (e) {
                e.preventDefault();
                $("#funnelkitLoginForm").hide();
                $("#funnelkitResetPasswordForm").show();
            });
            $(".funnelkit-LoginLink").on("click", function (e) {
                e.preventDefault();
                $("#funnelkitLoginForm").show();
                $("#funnelkitResetPasswordForm").hide();
            });
        }


        // Login user
        handleLogin(e) {
            e.preventDefault();
            const form = $(e.target);
            let username = form.find('input[name="username"]').val();
            let password = form.find('input[name="password"]').val();
            let live_validation = wfacp_frontend.wfacp_enable_live_validation;
            if ((username == '' || password == '') && (live_validation == "true" || live_validation == true)) {
                form.find('.wfacp-form-control:visible').trigger('focusout', {'inline_validation': true})
                return;
            }

            $.ajax({
                url: this.ajaxUrl,
                type: "POST",
                dataType: "json",
                data: form.serialize(),
                beforeSend: () => this.beforeSendHandler(),
                success: (response) => this.successHandler(response),
                error: (jqXHR, textStatus, errorThrown) =>
                    this.errorHandler(jqXHR, textStatus, errorThrown),
                complete: () => this.completeHandler(),
            });
        }

        beforeSendHandler() {
            // Optionally handle the before send event, e.g., show loader
            // Clear any previous error messages
            this.login_form.find(" .wfacp_error").remove();

            // Disable the form elements
            this.login_form.find("button").addClass("wfacp_btn_clicked");

        }


        successHandler(response) {
            // Check if the response contains errors
            if (response.success === false) {
                // If there is an error, prepend it to the login container
                $("#funnelkitLoginForm .wfacp_notice_list").prepend(
                    '<div class="wfacp_error">' + response.data.error + "</div>",
                );


                this.login_form.find("button").removeClass("wfacp_btn_clicked");


            } else {
                // Handle successful login here (e.g., redirect to a new page)
                window.location.reload();
            }
        }

        errorHandler(jqXHR, textStatus, errorThrown) {
            // Handle AJAX errors here
            this.login_form.find('.wfacp_notice_list').prepend(
                '<div class="wfacp_error">' +
                textStatus +
                ": " +
                errorThrown +
                "</div>",
            );


        }

        completeHandler() {
            // Re-enable the form elements and reset button text after a short delay
            setTimeout(function () {
                $("#funnelkitLoginForm").find(":input").prop("disabled", false);
                $('#funnelkitLoginForm button[type="submit"]').prop(
                    "disabled",
                    false,
                );
            }, 500); // 0.5 seconds delay to show the outcome before reset
        }
    }

    class WfacpPasswordResetHandler {
        constructor() {
            this.login_reset_password = $("#funnelkitResetPasswordForm");
            this.ajaxUrl = wfacp_frontend.admin_ajax;
            this.init();
        }

        init() {
            // Bind the reset password form submission event
            $("#funnelkitResetPasswordForm").on("submit", (e) =>
                this.handleResetPassword(e),
            );
        }

        handleResetPassword(e) {
            e.preventDefault();
            const form = $(e.target);
            let data = form.serializeArray();


            let user_login = form.find('input[name="user_login"]').val();
            let live_validation = wfacp_frontend.wfacp_enable_live_validation;
            if (user_login == '' && (live_validation == "true" || live_validation == true)) {
                form.find('.wfacp-form-control:visible').trigger('focusout', {'inline_validation': true})
                return;
            }
            if (undefined !== data) {
                data.push({'name': 'wfacp_source', 'value': $('#wfacp_source').val()});
                data.push({'name': '_wfacp_post_id', 'value': $('._wfacp_post_id').val()});
            }


            $.ajax({
                url: this.ajaxUrl, // Set this to the URL of your endpoint
                type: "POST",
                dataType: "json",
                data: $.param(data),
                beforeSend: () => this.beforeSendHandler(),
                success: (response) => this.successHandler(response),
                error: (jqXHR, textStatus, errorThrown) =>
                    this.errorHandler(jqXHR, textStatus, errorThrown),
                complete: () => this.completeHandler(),
            });
        }

        beforeSendHandler() {
            // Clear previous errors
            this.login_reset_password.find('.wfacp_error').remove();
            this.login_reset_password.find("button").addClass("wfacp_btn_clicked");
        }


        successHandler(response) {

            let reset_form = this.login_reset_password;

            let reset_form_notice = $("#funnelkitResetPasswordForm .wfacp_notice_list");
            let reset_message = $("#funnelkitResetPasswordMessage");
            let reset_message_notice = $("#funnelkitResetPasswordMessage .wfacp_notice_list");

            if ($('.wfacp-quickv-content_wrap').hasClass('wfacp-reset-pass-message')) {
                $('.wfacp-quickv-content_wrap').removeClass('wfacp-reset-pass-message');

            }
            if (!response.success) {
                reset_form_notice.prepend(`<div class="wfacp_error">${response.data.message}</div>`);
            } else {
                $('.wfacp-quickv-content_wrap').addClass('wfacp-reset-pass-message');
                reset_form.hide();
                reset_message_notice.prepend(`<div class="wfacp-success">${response.data.message}</div>`);
            }

            this.login_reset_password.find("button").removeClass("wfacp_btn_clicked");

        }

        errorHandler(jqXHR, textStatus, errorThrown) {

            this.login_reset_password.find('.wfacp_notice_list').prepend(
                `<div class="wfacp_error tester">${textStatus}:${errorThrown}</div>`,
            );

        }


        completeHandler() {
            // Re-enable the form elements and reset button text after a short delay
            setTimeout(function () {
                $("#funnelkitResetPasswordForm").find(":input").prop("disabled", false);
                $('#funnelkitResetPasswordForm button[type="submit"]').prop("disabled", false);
            }, 500); // 0.5 seconds delay to show the outcome before reset
        }
    }

    if ((wfacp_frontend.hasOwnProperty('display_smart_login') && 'true' === wfacp_frontend.display_smart_login) || wfacp_frontend.hasOwnProperty('display_prompt_returning_user') && 'true' === wfacp_frontend.display_prompt_returning_user) {
        new WfacpModalHandler();
        new WfacpLoginHandler();
        new WfacpPasswordResetHandler();


        if ('true' === wfacp_frontend.display_prompt_returning_user) {
            new WfacpUserEmailChecker();
        }


        if ('true' === wfacp_frontend.display_smart_login) {
            if ($('.showlogin').length > 0) {
                $('.showlogin').addClass('wfacp_display_smart_login');
                $('.wfacp_display_smart_login').removeClass('showlogin');
            }
        }

    }


})(jQuery);
