(function ($) {
    // Defensive check for required global object
    if (typeof wfacp_frontend === 'undefined' || !wfacp_frontend) {
        return;
    }

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
            $(document).on("click", "#funnelkitLoginModalToggler", (e) => this.handleLoginClick(e))
                .on("click", `${this.modalSelector} .wfacp-quickv-close`, (e) => this.hideModal(e),)
                .on("click", "body", (e) => this.hideModalOnOutsideClick(e));


            $(window).on('load', function () {
                $(document.body).off("click", "a.wfacp_display_smart_login");
                $(document.body).on("click", "a.wfacp_display_smart_login", (e) => {
                    self.handleLoginClick(e);
                });
            });
        }

        handleLoginClick(e) {
            e.preventDefault();

            // Check login form style: 'true' = Overlay, 'false' = Inline
            const loginFormStyle = wfacp_frontend.hasOwnProperty('display_smart_login')
                ? wfacp_frontend.display_smart_login
                : 'true';

            if (loginFormStyle === 'false') {
                this.scrollToInlineLogin();
            } else {
                this.showModal(e);
            }
        }

        // Helper method to autofill username field
        autofillUsernameField(container) {
            let emailToFill = $('#funnelkitLoginModalToggler').attr('data-email-id');
            if (!emailToFill || emailToFill.trim() === '') {
                emailToFill = $(".wfacp-section #billing_email").val();
            }

            if (emailToFill && emailToFill.trim() !== '') {
                const usernameField = container.find('input[name="username"]');
                if (usernameField.length > 0) {
                    usernameField.val(emailToFill);
                    usernameField.parents('.wfacp-form-control-wrapper').addClass('wfacp-anim-wrap');
                }
            }
        }

        scrollToInlineLogin() {
            // Find login section (try multiple selectors)
            let loginSection = $('.wfacp-login-wrapper').first();
            if (loginSection.length === 0) {
                loginSection = $('.wfacp-coupon-section').first();
            }
            if (loginSection.length === 0) {
                loginSection = $('.woocommerce-account-fields').first();
            }
            if (loginSection.length === 0) {
                loginSection = $('.showlogin').closest('.wfacp-coupon-section');
            }

            if (loginSection.length === 0) {
                return;
            }

            // Reveal form if hidden
            const showLoginLink = $('.showlogin');
            const loginForm = loginSection.find('form.woocommerce-form-login');

            if (showLoginLink.length > 0 && loginForm.is(':hidden')) {
                showLoginLink.trigger('click');
            }

            if (loginForm.length > 0 && loginForm.is(':hidden')) {
                loginForm.slideDown(300);
            }

            if (loginSection.is(':hidden')) {
                loginSection.slideDown(300);
            }

            // Autofill username
            this.autofillUsernameField(loginSection);

            // Scroll to section
            setTimeout(() => {
                const offsetTop = loginSection.offset().top - 100;
                $('html, body').animate({ scrollTop: offsetTop }, 500);
            }, 300);
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
            this.emailCheckInProgress = false;
            this.userFoundButNotLoggedIn = false;
            this.lastCheckedEmail = ''; // Track last checked email to prevent unnecessary checks
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
                const $target = $(event.target);
                const currentEmail = $target.val();

                // Only remove login prompt if email is being modified (different from last checked)
                if (currentEmail !== this.lastCheckedEmail) {
                    $("#funnelkitLoginAction").remove();
                    this.userFoundButNotLoggedIn = false;
                }

                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    // Get fresh value at timeout execution (user may have continued typing)
                    this.validate_email($target.val());
                }, this.debounceDelay);
            });

            // Prevent form submission on email field Enter key press if login prompt is visible
            this.field_key.on("keydown", (event) => {
                if (event.key === "Enter" || event.keyCode === 13) {
                    const email = $(event.target).val();

                    // Only validate complete emails
                    if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        // Only block if guest checkout is DISABLED
                        // When disabled, users MUST login - so enforce it
                        const guestCheckoutDisabled = wfacp_frontend.enable_guest_checkout !== 'yes';

                        // If login prompt is visible and guest checkout is disabled, prevent submission
                        if (guestCheckoutDisabled && $("#funnelkitLoginAction").length > 0) {
                            event.preventDefault();
                            this.shakeLoginPrompt();
                            return false;
                        }
                    }
                }
            });
        }

        validate_email(email) {
            // Only check if email is valid and different from last checked email
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                // Prevent duplicate checks for the same email
                if (email === this.lastCheckedEmail) {
                    return;
                }
                this.checkUserByEmail(email);
            }
        }

        // Check user by email
        checkUserByEmail(email) {
            if (true === this.rate_limit) {
                return;
            }

            this.emailCheckInProgress = true;

            // Temporarily disable next button during AJAX call
            $('.wfacp_next_page_button').addClass('wfacp-checking-user');

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
                complete: () => {
                    this.emailCheckInProgress = false;

                    // Re-enable next button after AJAX completes
                    $('.wfacp_next_page_button').removeClass('wfacp-checking-user');
                }
            });
        }

        // Placeholder methods for callbacks, or you can define actual logic here
        beforeSendHandler() {
            // Only remove if it exists (will be replaced by new one if user found)
            $("#funnelkitLoginAction").remove();
            this.userFoundButNotLoggedIn = false;
        }

        successHandler(response) {
            if (response.data.hasOwnProperty('rate_limit') && 'yes' === response.data.rate_limit) {
                this.rate_limit = true;
            }

            // Update last checked email after successful check
            const currentEmail = this.field_key.val();
            this.lastCheckedEmail = currentEmail;

            // Store email_id for inline form autofill
            const emailId = response.data.email_id;
            if (emailId) {
                $('#funnelkitLoginModalToggler').attr('data-email-id', emailId);
            }

            // Autofill overlay modal username field
            const usernameField = $('#funnelkitLoginModal input[name="username"]');
            if (emailId) {
                usernameField.val(emailId);
                usernameField.parents('.wfacp-form-control-wrapper').addClass('wfacp-anim-wrap');
            } else {
                usernameField.val('');
                usernameField.parents('.wfacp-form-control-wrapper').removeClass('wfacp-anim-wrap');
            }

            if (response.success === true) {
                this.userFoundButNotLoggedIn = true;

                // Print the HTML (login prompt)
                $(".wfacp-section #billing_email_field").after(response.data.html);

                setTimeout(function () {
                    $(".wfacp-section .wfacp-search-wrap").addClass("wfacp-show-field");
                }, 30);

                // Button remains enabled - event capture will handle blocking
            } else {

                this.userFoundButNotLoggedIn = false;
                $(".wfacp-section .wfacp-search-wrap").removeClass("wfacp-show-field");
                setTimeout(function () {
                    $(".wfacp-section .wfacp-search-wrap").remove();
                }, 30);

            }
        }


        errorHandler(jqXHR, textStatus, errorThrown) {
            $("#funnelkitLoginAction").remove();
            this.userFoundButNotLoggedIn = false;
            // Reset last checked email on error
            this.lastCheckedEmail = '';
        }

        shakeLoginPrompt() {
            const loginPrompt = $("#funnelkitLoginAction");
            if (loginPrompt.length > 0) {
                // Add shake animation
                loginPrompt.addClass('wfacp-shake-animation');

                // Add persistent red color class
                loginPrompt.addClass('wfacp-login-required');

                // Announce error to screen readers (accessibility)
                this.announceToScreenReader(wfacp_frontend.loginRequiredMessage || 'Please log in to continue with checkout');

                // Remove shake animation class after animation completes, but keep red color
                setTimeout(function () {
                    loginPrompt.removeClass('wfacp-shake-animation');
                    // wfacp-login-required class stays to keep red color
                }, 400);

                // Scroll to the login prompt and set focus for keyboard users
                $('html, body').animate({
                    scrollTop: loginPrompt.offset().top - 100
                }, 300, function() {
                    // Set focus to login button for keyboard accessibility
                    const loginButton = loginPrompt.find('button, a').first();
                    if (loginButton.length > 0) {
                        loginButton.focus();
                    }
                });
            }
        }

        // Announce message to screen readers via ARIA live region
        announceToScreenReader(message) {
            let liveRegion = $('#wfacp-sr-announcer');
            if (liveRegion.length === 0) {
                liveRegion = $('<div id="wfacp-sr-announcer" role="alert" aria-live="assertive" aria-atomic="true" class="screen-reader-text" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></div>');
                $('body').append(liveRegion);
            }
            // Clear and set message (clearing first ensures announcement even for same message)
            liveRegion.text('');
            setTimeout(function() {
                liveRegion.text(message);
            }, 100);
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


        // Login user - now uses PHP form submission (no AJAX)
        handleLogin(e) {
            const form = $(e.target);
            let username = form.find('input[name="username"]').val();
            let password = form.find('input[name="password"]').val();
            let live_validation = wfacp_frontend.wfacp_enable_live_validation;
            
            // Only do client-side validation, then allow form to submit normally
            if ((username == '' || password == '') && (live_validation == "true" || live_validation == true)) {
                e.preventDefault();
                form.find('.wfacp-form-control:visible').trigger('focusout', {'inline_validation': true})
                return false;
            }

            // Show loading state
            this.login_form.find("button").addClass("wfacp_btn_clicked");
            
            // Allow normal form submission - PHP will handle login and redirect
            return true;
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
                // Handle successful login - page will reload and user will be logged in
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
            const emailChecker = new WfacpUserEmailChecker();

            // Only enable force login validation if guest checkout is DISABLED
            // When guest checkout is disabled, users MUST login - enforce it with blocking
            const guestCheckoutDisabled = wfacp_frontend.enable_guest_checkout !== 'yes';

            if (guestCheckoutDisabled) {
                // Add validation before next step navigation - use capture phase for higher priority
                document.addEventListener('click', function (e) {
                    const target = e.target;

                    // Check if clicked element is next button or its child
                    if (target.closest('.wfacp_next_page_button')) {
                        // Check if login prompt is visible and user is not logged in
                        if ($('#funnelkitLoginAction').length > 0 && 'yes' !== wfacp_frontend.is_user_logged_in) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();

                            // Shake the login prompt
                            emailChecker.shakeLoginPrompt();

                            return false;
                        }
                    }
                }, true); // Use capture phase to run before other handlers

                // Add validation before form submission
                $(document).on('submit', 'form.checkout', function (e) {
                    // Check if login prompt is visible and user is not logged in
                    if ($('#funnelkitLoginAction').length > 0 && 'yes' !== wfacp_frontend.is_user_logged_in) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        // Shake the login prompt
                        emailChecker.shakeLoginPrompt();

                        return false;
                    }
                });
            }
        }


        if ('true' === wfacp_frontend.display_smart_login) {
            if ($('.showlogin').length > 0) {
                $('.showlogin').addClass('wfacp_display_smart_login');
                $('.wfacp_display_smart_login').removeClass('showlogin');
            }
        }

    }


})(jQuery);
