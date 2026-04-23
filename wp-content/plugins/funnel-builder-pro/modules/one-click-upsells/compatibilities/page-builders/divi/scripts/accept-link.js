import WFOCU_Component from "./abs-component";

class WFOCU_Accept_Link extends WFOCU_Component {
    static  slug = 'et_wfocu_accept_link';

    constructor() {
        super();
        this.c_slug = 'et_wfocu_accept_link';
    }


    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Accept_Link.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Accept_Link.slug + '_fields'](utils, props);
        }
        return [wfacp_divi_style];
    }


    render() {
        //const Content = this.props.content;
        return (
            <div id={this.c_slug}>
                <div className="wfocu-button-wrapper test">
                    <a className="wfocu-wfocu-accept " href="javascript:void(0);" >{this.props.text}</a>
                </div>
            </div>
        );
    }


}

export default WFOCU_Accept_Link;