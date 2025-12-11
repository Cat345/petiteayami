<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wfob_welcome_wrap" v-if="isEmpty()">

    <div class="bwf-zero-state">
        <div class="bwf-zero-state-wrap">
            <div class="bwf-zero-sec bwf-zero-sec-icon bwf-pb-gap">
                <img src="<?php echo esc_url( WFOB_PLUGIN_URL ) ?>/admin/assets/img/zero-state/funnel.svg" alt="" title=""/>
            </div>
            <div class="bwf-zero-sec bwf-zero-sec-content bwf-h2 bwf-pb-10">
                <div><?php _e( 'Add a product as order bump', 'woofunnels-order-bump' ); ?></div>
            </div>
            <div class="bwf-zero-sec bwf-zero-sec-content bwf-pb-gap">
                <div class="bwf-h4-1"><?php _e( 'Choose a complimentary product to increase the uptake rate', 'woofunnels-order-bump' ); ?></div>
            </div>
            <div class="bwf-zero-sec bwf-zero-sec-buttons">
                <button type="button" class="wfob_btn wfob_btn_primary wfob_modal_open" data-izimodal-open="#modal-add-product" data-iziModal-title="Create New Funnel Step" data-izimodal-transitionin="fadeInDown">
                    <?php esc_html_e( 'Add Product', 'woofunnels-order-bump' ); ?>
                </button>
            </div>
        </div>
    </div>

</div>
