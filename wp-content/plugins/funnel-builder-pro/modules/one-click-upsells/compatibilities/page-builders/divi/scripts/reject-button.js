import React from "react";

class WFOCU_Reject_Button extends React.Component {
    static slug = 'et_wfocu_reject_button';

    constructor() {
        super();
        this.c_slug = WFOCU_Reject_Button.slug;
    }

    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Reject_Button.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Reject_Button.slug + '_fields'](utils, props);
        }
        wfacp_divi_style.push({'selector': '%%order_class%% #' + WFOCU_Reject_Button.slug + ' .et-pb-icon', 'declaration': 'font-family: ETmodules !important'});
        return [wfacp_divi_style];
    }

    getIconClass() {
        return "wfocu-button-icon et-pb-icon";
    }

    render() {
        const utils = window.ET_Builder.API.Utils;

        return (

            <div id={this.c_slug}>
                <div className="wfocu-button-wrapper wfocu-reject-button-wrap ">
                    <a className="wfocu-wfocu-reject" href="javascript:void(0);">
                        {('left' == this.props.icon_align && '' !== this.props.icon) ? (<span className={this.getIconClass()}>{utils.processFontIcon(this.props.icon)}</span>) : ''}
                        {this.props.text}
                        {('right' == this.props.icon_align && '' !== this.props.icon) ? (<span className={this.getIconClass()}>{utils.processFontIcon(this.props.icon)}</span>) : ''}

                    </a>
                </div>
            </div>
        );
    }

}

export default WFOCU_Reject_Button;