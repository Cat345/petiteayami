import React from "react";

class WFOCU_Component extends React.Component {
    static style_data=[];
    constructor() {
        super();
        this.timeout = null;
        this.ajax = false;
        this.c_slug = '';
        this.state = {formData: 'Loading ....'}
    }

    componentDidMount() {
        if (true != this.ajax) {
            return;
        }
        this.send_json();

    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (true != this.ajax) {
            return;
        }
        if (JSON.stringify(this.props) === JSON.stringify(prevProps)) {
            return;
        }
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.send_json();
        }, 600);
    }

    send_json() {
        let settings = JSON.stringify(this.props);
        settings = JSON.parse(settings);
        settings.action = this.c_slug;
        settings.post_id = et_pb_custom.page_id;
        settings.et_load_builder_modules = '1';
        settings._ajax_nonce = window.wfocu_divi_data ? window.wfocu_divi_data.nonce : '';
        let request = {
            url: et_pb_custom.ajaxurl,
            method: 'POST',
            data: settings,
            success: (rsp, jqxhr, status) => {
                this.setState({formData: rsp});
                this.ajaxSuccess(rsp, jqxhr, status)
            },
            complete: (rsp, jqxhr, status) => {


            },
            error: (rsp, jqxhr, status) => {
            }
        };

        jQuery.ajax(request);

    }

    ajaxSuccess(rsp, jqxhr, status) {

    }

    render() {
        return React.createElement("div", {
            className: this.c_slug + " wfacp_divi_loader",
            dangerouslySetInnerHTML: {
                __html: this.state.formData
            }
        });
    }

}

export default WFOCU_Component;
