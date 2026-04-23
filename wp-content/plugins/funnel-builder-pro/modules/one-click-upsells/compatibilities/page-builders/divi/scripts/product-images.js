import WFOCU_Component from "./abs-component";

class WFOCU_Product_Image extends WFOCU_Component {
    static  slug = 'et_wfocu_product_image';

    constructor() {
        super();
        this.ajax = true;
        this.c_slug = 'et_wfocu_product_image';
    }

    static css(props) {

        const utils = window.ET_Builder.API.Utils;
        let wfacp_divi_style = [];
        if (window.hasOwnProperty(WFOCU_Product_Image.slug + '_fields')) {
            wfacp_divi_style = window[WFOCU_Product_Image.slug + '_fields'](utils, props);
        }
        return [wfacp_divi_style];
    }

    ajaxSuccess(rsp, jqxhr, status) {
        setTimeout(() => {
            this.enableSlider();
        }, 1000);
    }

    enableSlider() {
        if (jQuery('.wfocu-product-carousel').length > 0) {
            jQuery('.wfocu-product-carousel').each(function () {
                var flickity_attr = jQuery(this).attr('data-flickity');
                if (undefined !== flickity_attr) {
                    jQuery(this).flickity(JSON.parse(flickity_attr));
                }
            });
        }

        if (jQuery('.wfocu-product-carousel-nav').length > 0) {
            jQuery('.wfocu-product-carousel-nav').each(function () {
                var flickity_attr = jQuery(this).attr('data-flickity');
                if (undefined !== flickity_attr) {
                    jQuery(this).flickity(JSON.parse(flickity_attr));
                }
            });
        }
    }

    render() {
        return super.render();
    }
}

export default WFOCU_Product_Image;