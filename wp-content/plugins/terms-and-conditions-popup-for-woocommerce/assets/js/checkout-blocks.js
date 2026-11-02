(function ($) {
    'use strict';

    var settings = window.brTermsBlocksSettings;
    var termsSelector = '.wp-block-woocommerce-checkout-terms-block';
    var changingCheckbox = false;

    window.brTermsBlocks = {
        setChecked: function (checked) {
            document.querySelectorAll(termsSelector + ' input[type="checkbox"]').forEach(function (input) {
                if (!input.disabled && input.checked !== checked) {
                    // Let React update both its state and WooCommerce validation.
                    changingCheckbox = true;
                    try {
                        input.click();
                    } finally {
                        changingCheckbox = false;
                    }
                }
            });
        }
    };

    function normalizeUrl(url) {
        var parsed = new URL(url, document.baseURI);
        return parsed.origin + parsed.pathname.replace(/\/+$/, '') + parsed.search;
    }

    if (settings.hideCheckbox) {
        var style = document.createElement('style');
        style.textContent = termsSelector + ' input[type="checkbox"], ' +
            termsSelector + ' .wc-block-components-checkbox__mark { display: none !important; }';
        document.head.appendChild(style);
    }

    // Capture before checkbox labels and keep working after React replaces links.
    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }
        var block = event.target.closest(termsSelector);
        if (!block) {
            return;
        }
        var link = event.target.closest('a[href]');
        if (link) {
            var popup = settings.popups.find(function (item) {
                return normalizeUrl(item.url) === normalizeUrl(link.href);
            });
            if (popup && document.getElementById(popup.id) && $.fn.br_popup) {
                event.preventDefault();
                event.stopPropagation();
                $('#' + popup.id).br_popup().open_popup();
            }
        } else if (settings.hideCheckbox && !changingCheckbox &&
            event.target.closest('label, input[type="checkbox"]')) {
            // Hidden agreement is controlled only by Accept / Decline.
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);
})(jQuery);
