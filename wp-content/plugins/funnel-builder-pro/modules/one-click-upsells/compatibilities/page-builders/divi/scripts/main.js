require('./divi.js');
import WFOCU_Accept_Button from "./accept-button";
import WFOCU_Reject_Button from "./reject-button";
import WFOCU_Accept_Link from "./accept-link";
import WFOCU_Offer_Price from "./offer-price";
import WFOCU_Product_Image from "./product-images";
import WFOCU_Product_Short_Desc from "./product-short-desc";
import WFOCU_Product_Title from "./product-title";
import WFOCU_Quantity_Selector from "./qty-selector";
import WFOCU_Reject_Link from "./reject-link";
import WFOCU_Variation_Selector from "./variation-selector";

(function ($) {
    $(window).on('et_builder_api_ready', (event, API) => {
        API.registerModules(
            [
                WFOCU_Accept_Button, WFOCU_Reject_Button, WFOCU_Accept_Link, WFOCU_Offer_Price, WFOCU_Product_Image,
                WFOCU_Product_Short_Desc, WFOCU_Product_Title,
                WFOCU_Quantity_Selector, WFOCU_Reject_Link, WFOCU_Variation_Selector
            ]
        );
    });
})(jQuery);