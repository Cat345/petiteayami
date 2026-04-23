import WFOCU_Component from "./abs-component";

class WFOCU_Accept_button extends WFOCU_Component {

    static  slug = 'et_wfocu_accept_button';

    constructor() {
        super();
        this.c_slug = 'et_wfocu_accept_button';

    }

    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Accept_button.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Accept_button.slug + '_fields'](utils, props);
        }
        wfacp_divi_style.push({'selector': '%%order_class%% #' + WFOCU_Accept_button.slug + ' .et-pb-icon', 'declaration': 'font-family: ETmodules !important'});
        return [wfacp_divi_style];
    }


    getComponentClass() {
        return "wfocu-button wfocu-accept-button-link wfocu-animation-" + this.props.hover_animation + ' wfocu_upsell';
    }

    getIconClass() {
        return "wfocu-button-icon et-pb-icon";
    }

    render() {
        const utils = window.ET_Builder.API.Utils;
        let divi_icon = this.props.icon;

        if (typeof divi_icon == 'undefined' || typeof divi_icon == undefined) {
            divi_icon = '';
        }

        return (
            <div id={this.c_slug}>
                <div className="wfocu-button-wrapper">
                    <a id="wfocu-accept-button-link" className={this.getComponentClass()} href="javascript:void(0);" role="button">
                    <span className="wfocu-button-content-wrapper" style={{display: "block"}}>
                        {('left' == this.props.icon_align && '' !== divi_icon) ? (<span className={this.getIconClass()}>{utils.processFontIcon(this.props.icon)}</span>) : ''}
                        <span className={'wfocu-button-text'}>{this.props.text}</span>
                        {('right' == this.props.icon_align && '' !== divi_icon) ? (<span className={this.getIconClass()}>{utils.processFontIcon(this.props.icon)}</span>) : ''}
                    </span>
                        <span style={{display: "block"}} className={'wfocu-button-subtitle'}>{this.props.subtitle}</span>
                    </a>
                </div>
            </div>

        );
    }

}

export default WFOCU_Accept_button;