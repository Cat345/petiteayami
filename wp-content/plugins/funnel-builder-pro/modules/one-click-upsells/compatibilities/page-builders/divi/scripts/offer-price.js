import WFOCU_Component from "./abs-component";

class WFOCU_Offer_Price extends WFOCU_Component {
    static  slug = 'et_wfocu_offer_price';

    constructor() {
        super();
        this.ajax = true;
        this.c_slug = 'et_wfocu_offer_price';
    }

    static css(props) {
        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Offer_Price.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Offer_Price.slug + '_fields'](utils, props);
        }

        if ('on' == props['offer_slider_enabled']) {
            wfacp_divi_style.push({'selector': '%%order_class%% .wfocu-price-wrapper .reg_wrapper', 'declaration': "display: block;"});
        }
        return [wfacp_divi_style];
    }

    render() {
        return super.render();
    }
}

export default WFOCU_Offer_Price;