import WFOCU_Component from "./abs-component";

class WFOCU_Quantity_Selector extends WFOCU_Component {
    static  slug = 'et_wfocu_quantity_selector';

    constructor() {
        super();
        this.ajax = true;
        this.c_slug = WFOCU_Quantity_Selector.slug;
    }


    static css(props) {
        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Quantity_Selector.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Quantity_Selector.slug + '_fields'](utils, props);
        }

        if ('on' == props['slider_enabled']) {

            wfacp_divi_style.push([{'selector': '%%order_class%% .wfocu-prod-qty-wrapper label', 'declaration': 'display: block; background: transparent; font-weight: normal;'}]);
        }

        return [wfacp_divi_style];
    }


    render() {
        return super.render();
    }
}

export default WFOCU_Quantity_Selector;