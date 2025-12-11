function runFlickityInitialization() {
    function initializeFlickityCarousel(selector) {
        jQuery(selector).each(function () {
            var flickityAttr = jQuery(this).attr('data-flickity');
            if (flickityAttr !== undefined) {
                jQuery(this).flickity(JSON.parse(flickityAttr));
            }
        });
    }

    jQuery(document).ready(function () {
        initializeFlickityCarousel('.wfocu-product-carousel');
        initializeFlickityCarousel('.wfocu-product-carousel-nav');
    });
}