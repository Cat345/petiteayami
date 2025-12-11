wp.customize.controlConstructor['wfocu-responsive-font'] = wp.customize.Control.extend({

    // When we're finished loading continue processing.
    ready: function () {

        'use strict';

        let control = this,
            value;

        control.responsiveInit();

        /**
         * Save on change / keyup / paste
         */
        this.container.on('change keyup paste', 'input.wfocu-responsive-input, select.wfocu-responsive-select', function () {

            value = jQuery(this).val();

            // Update value on change.
            control.updateValue();
        });

        /**
         * Refresh preview frame on blur
         */
        this.container.on('blur', 'input', function () {

            value = jQuery(this).val() || '';

            if (value == '') {
                wp.customize.previewer.refresh();
            }

        });
        let btn_container = jQuery(' .wp-full-overlay-footer .devices button ')

        if (btn_container.length > 0) {
            let device = btn_container.attr('data-device');
            jQuery('.customize-control-wfocu-responsive-font .input-wrapper input.' + device + ', .customize-control .wfocu-responsive-btns > li.' + device).addClass('active');
            jQuery('.customize-control-wfocu-responsive-font .wfocu-responsive-select.' + device).addClass('active');
        }
    },

    /**
     * Updates the sorting list
     */
    updateValue: function () {

        'use strict';

        let control = this,
            newValue = {};

        // Set the spacing container.
        control.responsiveContainer = control.container.find('.wfocu-responsive-wrapper').first();

        control.responsiveContainer.find('input.wfocu-responsive-input').each(function () {
            let responsive_input = jQuery(this),
                item = responsive_input.data('id'),
                item_value = responsive_input.val();

            newValue[item] = item_value;

        });

        control.responsiveContainer.find('select.wfocu-responsive-select').each(function () {
            let responsive_input = jQuery(this),
                item = responsive_input.data('id'),
                item_value = responsive_input.val();

            newValue[item] = item_value;
        });

        control.setting.set(newValue);
    },

    responsiveInit: function () {

        'use strict';
        this.container.find('.wfocu-responsive-btns button').on('click', function (event) {

            let device = jQuery(this).attr('data-device');
            if ('desktop' == device) {
                device = 'tablet';
            } else if ('tablet' == device) {
                device = 'mobile';
            } else {
                device = 'desktop';
            }

            jQuery('.wp-full-overlay-footer .devices button[data-device="' + device + '"]').trigger('click');
        });
    },
});

jQuery(' .wp-full-overlay-footer .devices button ').on('click', function () {
    let device = jQuery(this).attr('data-device');
    jQuery('.customize-control-wfocu-responsive-font .input-wrapper input, .customize-control .wfocu-responsive-btns > li').removeClass('active');
    jQuery('.customize-control-wfocu-responsive-font .wfocu-responsive-select').removeClass('active');

    jQuery('.customize-control-wfocu-responsive-font .input-wrapper input.' + device + ', .customize-control .wfocu-responsive-btns > li.' + device).addClass('active');
    jQuery('.customize-control-wfocu-responsive-font .wfocu-responsive-select.' + device).addClass('active');

});
