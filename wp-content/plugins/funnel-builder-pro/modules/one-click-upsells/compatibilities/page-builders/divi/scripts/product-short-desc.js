import WFOCU_Component from "./abs-component";

class WFOCU_Product_Short_Desc extends WFOCU_Component {
    static slug = 'et_wfocu_product_short_desc';

    constructor() {
        super();
        this.ajax = true;
        this.c_slug = WFOCU_Product_Short_Desc.slug;
    }

    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Product_Short_Desc.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Product_Short_Desc.slug + '_fields'](utils, props);
        }
        return [wfacp_divi_style];
    }

    render() {
        return super.render();
    }
}

export default WFOCU_Product_Short_Desc;