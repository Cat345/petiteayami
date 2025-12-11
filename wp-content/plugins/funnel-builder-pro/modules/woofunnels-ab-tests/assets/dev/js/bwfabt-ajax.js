/*global ajaxurl*/
(
    function ($) {
        "use strict";
        window.wp_admin_ajax = function (cls, is_form, cb) {
            const self = this;
            let element = null;
            let handler = {};
            let prefix = "bwfabt_";
            this.action = null;

            this.data = function (form_data) {

                return form_data;
            };
            this.before_send = function () {
            };
            this.async = function (bool) {
                return bool;
            };
            this.method = function (method) {
                return method;
            };
            this.success = function () {
            };
            this.complete = function () {
            };
            this.error = function () {
            };
            this.action = function (action) {
                return action;
            };

            function reset_form(action, fieldset, loader, rsp, jqxhr, status) {
                if (fieldset.length > 0) {
                    fieldset.prop('disabled', false);
                }
                loader.remove();
                if (Object.prototype.hasOwnProperty.call(self, action) === true && typeof self[action] === 'function') {
                    self[action](rsp, fieldset, loader, jqxhr, status);
                }
            }

            function form_post(action) {
                let formEl = element;
                let ajax_loader = null;
                let form_data = new FormData(formEl);

                form_data.append('action', action);

                let fieldset = $(formEl).find("fieldset");
                if (fieldset.length > 0) {
                    fieldset.prop('disabled', true);
                }
                self.before_send(formEl, action);

                let data = self.data(form_data, formEl);

                let request = {
                    url: ajaxurl,
                    async: self.async(true),
                    method: self.method('POST'),
                    data: data,
                    processData: false,
                    contentType: false,
                    //       contentType: self.content_type(false),
                    success: function (rsp, jqxhr, status) {

                        reset_form(action + "_ajax_success", fieldset, ajax_loader, rsp, jqxhr, status);
                        self.success(rsp, jqxhr, status, fieldset, ajax_loader);
                    },
                    complete: function (rsp, jqxhr, status) {

                        reset_form(action + "_ajax_complete", fieldset, ajax_loader, rsp, jqxhr, status);
                        self.complete(rsp, jqxhr, status, fieldset, ajax_loader);
                    },
                    error: function (rsp, jqxhr, status) {
                        reset_form(action + "_ajax_error", fieldset, ajax_loader, rsp, jqxhr, status);
                        self.error(rsp, jqxhr, status, fieldset, ajax_loader);
                    }
                };
                Object.prototype.hasOwnProperty.call(handler, action) ? clearTimeout(handler[action]) : handler[action] = null;
                handler[action] = setTimeout(
                    function (request) {
                        $.ajax(request);
                    }, 200, request
                );
            }

            function send_json(action) {
                let formEl = element;
                let data = self.data({}, formEl);
                typeof data === 'object' ? (data.action = action) : (data = {'action': action});

                self.before_send(formEl, action);

                let request = {
                    url: ajaxurl,
                    async: self.async(true),
                    method: self.method('POST'),
                    data: data,
                    success: function (rsp, jqxhr, status) {
                        self.success(rsp, jqxhr, status, element);
                    },
                    complete: function (rsp, jqxhr, status) {
                        self.complete(rsp, jqxhr, status, element);
                    },
                    error: function (rsp, jqxhr, status) {
                        self.error(rsp, jqxhr, status, element);
                    }
                };
                Object.prototype.hasOwnProperty.call(handler, action) ? clearTimeout(handler[action]) : handler[action] = null;
                handler[action] = setTimeout(
                    function (request) {
                        $.ajax(request);
                    }, 200, request
                );
            }

            this.ajax = function (action, data) {
                typeof data === 'object' ? (data.action = action) : (data = {'action': action});

                data.action = prefix + action;
                self.before_send(document.body, action);

                let request = {
                    url: ajaxurl,
                    async: self.async(true),
                    method: self.method('POST'),
                    data: data,
                    success: function (rsp, jqxhr, status) {
                        self.success(rsp, jqxhr, status, action);
                    },
                    complete: function (rsp, jqxhr, status) {
                        self.complete(rsp, jqxhr, status, action);
                    },
                    error: function (rsp, jqxhr, status) {
                        self.error(rsp, jqxhr, status, action);
                    }
                };
                Object.prototype.hasOwnProperty.call(handler, action) ? clearTimeout(handler[action]) : handler[action] = null;
                handler[action] = setTimeout(
                    function (request) {
                        $.ajax(request);
                    }, 200, request
                );
            };

            function form_init(cls) {
                if ($(cls).length > 0) {
                    $(cls).off("submit");
                    $(cls).on(
                        "submit", function (e) {
                            e.preventDefault();
                            let action = $(this).data('bwfabtaction');
                            console.log(action);

                            if (action !== 'undefined') {
                                action = prefix + action;
                                action = action.trim();
                                element = this;
                                self.action = action;
                                form_post(action);
                            }
                        }
                    );

                    if (typeof cb === 'function') {

                        cb(self);
                    }
                }
            }

            function click_init(cls) {
                if ($(cls).length > 0) {
                    $(cls).on(
                        "click", function (e) {
                            e.preventDefault();
                            let action = $(this).data('bwfabtaction');
                            if (action !== 'undefined') {
                                action = prefix + action;
                                action = action.trim();
                                element = this;
                                self.action = action;
                                send_json(action);
                            }
                        }
                    );

                    if (typeof cb === 'function') {
                        cb(self);
                    }
                }
            }

            if (is_form === true) {
                form_init(cls, cb);
                return this;
            }

            if (is_form === false) {
                click_init(cls, cb);
                return this;
            }

            return this;
        }
    })(jQuery);