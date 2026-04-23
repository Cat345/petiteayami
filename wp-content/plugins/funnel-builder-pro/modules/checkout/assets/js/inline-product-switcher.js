(function ($) {
    'use strict';

    if (typeof wfacp_frontend === 'undefined') {
        return;
    }

    /**
     * Inline Product Switcher - Single Variation Dropdown
     *
     * Handles a single combined dropdown where each option is a full variation
     * (e.g. "Color: Blue, Size: Large"). The dropdown value is the variation_id.
     */
    var wfacp_inline_ps = {
        init: function () {
            $(document.body).on('change', '.wfacp_inline_variation_select', this.on_variation_change);
        },

        /**
         * Handle variation dropdown change
         */
        on_variation_change: function () {
            var $select = $(this);
            var $container = $select.closest('.wfacp_inline_variations');
            var $row = $select.closest('.wfacp_product_row');

            if ($container.length === 0 || $row.length === 0) {
                return;
            }

            var variation_id = parseInt($select.val(), 10);
            if (!variation_id) {
                return;
            }

            var item_key = $container.data('item-key');
            var variations_json = $container.data('product_variations');

            if (!variations_json || !Array.isArray(variations_json)) {
                return;
            }

            // Find the selected variation by ID
            var matching_variation = null;
            for (var i = 0; i < variations_json.length; i++) {
                if (variations_json[i].variation_id === variation_id) {
                    matching_variation = variations_json[i];
                    break;
                }
            }

            if (!matching_variation) {
                return;
            }

            // Update product image if variation has one
            wfacp_inline_ps.update_image($row, matching_variation);

            // Update price display
            wfacp_inline_ps.update_price($row, matching_variation);

            // Trigger cart update
            wfacp_inline_ps.update_cart($row, item_key, matching_variation);
        },

        /**
         * Update product image with variation image
         */
        update_image: function ($row, variation) {
            if (!variation.image || !variation.image.thumb_src) {
                return;
            }

            var $img = $row.find('.wfacp_inline_product_image .wfacp-pro-thumb img, .product-image .wfacp-pro-thumb img');
            if ($img.length > 0) {
                $img.attr('src', variation.image.thumb_src);
                if (variation.image.alt) {
                    $img.attr('alt', variation.image.alt);
                }
            }
        },

        /**
         * Update price display with variation price
         */
        update_price: function ($row, variation) {
            if (!variation.display_price && variation.display_price !== 0) {
                return;
            }

            var $price_sec = $row.find('.wfacp_inline_price_sec');
            if ($price_sec.length > 0 && variation.price_html) {
                $price_sec.html(variation.price_html);
            }
        },

        /**
         * Update cart with selected variation via AJAX
         */
        update_cart: function ($row, item_key, variation) {
            var cart_key = $row.attr('cart_key');
            var field_type = $row.find('[name=wfacp_product_choosen]').attr('type');
            var variation_id = variation.variation_id;
            var selected_attrs = variation.attributes;

            var vars_data = {
                'variation_id': variation_id,
                'quantity': 1,
                'attributes': selected_attrs
            };

            if (cart_key !== undefined && cart_key !== '') {
                // Product already in cart - update variation
                var data = {};
                data.wfacp_id = $('._wfacp_post_id').val();
                data.cart_key = cart_key;
                data.quantity = 1;
                data.variation_id = 0;

                if (typeof window.set_variation_data === 'function') {
                    data = window.set_variation_data(data, vars_data);
                } else {
                    data.variation_id = variation_id;
                    data.attributes = selected_attrs;
                }

                if (typeof window.wfacp_update_variation_data === 'function') {
                    window.wfacp_update_variation_data(data);
                }
            } else if (item_key !== undefined && item_key !== '') {
                // Product not yet in cart - add with variation
                var $checkbox = $row.find('[name=wfacp_product_choosen]');
                $row.attr('variation_id', variation_id);

                if (field_type === 'hidden' || field_type === 'checkbox') {
                    var data = {};
                    data.wfacp_id = $('._wfacp_post_id').val();
                    data.new_item = item_key;
                    data.quantity = 1;
                    data.variation_id = 0;
                    data.field_type = field_type;

                    if (typeof window.set_variation_data === 'function') {
                        data = window.set_variation_data(data, vars_data);
                    } else {
                        data.variation_id = variation_id;
                        data.attributes = selected_attrs;
                    }

                    if (typeof window.wfacp_product_switch === 'function') {
                        window.wfacp_product_switch(data);
                    }
                } else if (field_type === 'radio') {
                    $checkbox.prop('checked', true).trigger('change', vars_data);
                }
            }
        }
    };

    $(document).ready(function () {
        wfacp_inline_ps.init();
    });

    // Re-init after AJAX fragments update
    $(document.body).on('wfacp_update_fragments', function () {
        // Dropdowns are re-rendered via AJAX, event delegation handles this
    });

})(jQuery);
