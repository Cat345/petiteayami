import WFOCU_Component from "./abs-component";

class WFOCU_Variation_Selector extends WFOCU_Component {
    static slug = 'et_wfocu_variation_selector';

    constructor() {
        super();

        this.c_slug = WFOCU_Variation_Selector.slug;
        this.ajax = true;
    }

    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Variation_Selector.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Variation_Selector.slug + '_fields'](utils, props);
        }


        if ('on' == props['attr_value_block']) {
            wfacp_divi_style.push({'selector': '%%order_class%% .variations td', 'declaration': "display: block;"});
            wfacp_divi_style.push({
                'selector': '%%order_class%% #wfocu_variation_selector .variations td label, %%order_class%% .variations td select option',
                'declaration': "font-weight: normal;"
            });
        }

        return [wfacp_divi_style];
    }

    render() {
        return super.render();
    }
}

export default WFOCU_Variation_Selector;