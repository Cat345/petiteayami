import React from "react";

class WFOCU_Reject_Link extends React.Component {
    static slug = 'et_wfocu_reject_link';

    constructor() {
        super();
        this.c_slug = 'et_wfocu_reject_link';
    }



    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Reject_Link.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Reject_Link.slug + '_fields'](utils, props);
        }

        return [wfacp_divi_style];
    }

    render() {
        return (
            <div id={this.c_slug}>
                <div className="wfocu-button-wrapper">
                    <a className="wfocu-reject">{this.props.text}</a>
                </div>
            </div>
        );
    }

}

export default WFOCU_Reject_Link;