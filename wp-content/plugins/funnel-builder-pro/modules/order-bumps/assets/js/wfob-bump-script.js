(function ($) {
    if($('.wfob_read_more_link').length > 0){
        $(document.body).on('click', '.wfob_read_more_link', function (e) {
            e.preventDefault();
            let key = $(this).data('product-key');
            let main_description = $('.wfob_bump_r_outer_wrap[data-product-key=' + key + '] .wfob_l3_s_desc');
            let choose_options = $('.wfob_bump_r_outer_wrap[data-product-key=' + key + '] .wfob_l3_c_sub_desc_choose_option');
            main_description.slideToggle();

            if (main_description.is(":visible")) {
                $(this).parents('.wfob_l3_c_sub_desc').addClass('wfob_remove_read_more');
            }else{
                $(this).parents('.wfob_l3_c_sub_desc').removeClass('wfob_remove_read_more');
            }
            if (choose_options.length > 0) {
                if (choose_options.is(":visible")) {
                    choose_options.slideUp();
                } else {
                    choose_options.slideDown();
                }
            }
        });
    }

    $(document.body).on('click', '.wfob_btn_add', function (e) {
        e.preventDefault();
        $(this).hide();
        let key = $(this).data('key');
        $(this).removeClass('wfob_product_show_btn');
        $('.wfob_btn_remove[data-key=' + key + ']').addClass('wfob_product_show_btn');
    });
    $(document.body).on('click', '.wfob_btn_remove', function (e) {
        e.preventDefault();
        let key = $(this).data('key');
        $(this).hide();
        $(this).removeClass('wfob_product_show_btn');
        $('.wfob_btn_add[data-key=' + key + ']').addClass('wfob_product_show_btn');
    });
})(jQuery);


