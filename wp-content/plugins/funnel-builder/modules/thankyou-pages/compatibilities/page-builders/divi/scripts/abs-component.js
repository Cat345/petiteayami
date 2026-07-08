import React from "react";

class WFTY_Component extends React.Component {
    static style_data = [];

    constructor() {
        super();
        this.c_slug = '';
        this.state = {formData: 'Loading ....'}
    }

    static css(props) {
        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(this.c_slug + '_fields')) {
            wfacp_divi_style = window[this.c_slug + '_fields'](utils, props);
        }

        return wfacp_divi_style;
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

export default WFTY_Component;
