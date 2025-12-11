<div class="wfocu_secondary_top_bar">
    <div class="wfocu_steps">
        <div class="wfocu_steps_sortable">
            <?php include __DIR__ . '/steps/offer-ladder.php';

            $offers = WFOCU_Core()->funnels->get_funnel_offers_admin();
			$class_default = '';
            $steps = $offers['steps'];
            if (  empty( $steps ) ) {
	            $class_default = 'wfocu_hide';
			}
            ?>
            <div class="wfocu_step_add wfocu_button_add wfocu_modal_open ui-state-disabled <?php echo esc_attr($class_default); ?>" data-izimodal-open="#modal-add-offer-step">
                <?php echo __( "Add New Offer",'woofunnels-upstroke-one-click-upsell' ); ?>
            </div>
        </div>
    </div>
</div>

<div class="wfocu_wrap_r">
	<?php include __DIR__ . "/steps/section-product.php"; ?>
    <div class="wfocu_p20" style="display: none;">
        <form class="wfocu_forms_wrap" data-wfoaction="save_funnel_description">
            <div class="wfocu_vue_forms" id="part1">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
            </div>
            <fieldset>
                <div class="wfocu_form_submit">
                    <input type="submit" class="wfocu_submit_btn_style"/>
                </div>
            </fieldset>
        </form>
        <div class="wfocu_clear_30"></div>

        <form class="wfocu_forms_wrap">
            <div class="wfocu_vue_forms" id="part5">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
            </div>
            <fieldset>
                <div class="wfocu_form_submit">
                    <input type="submit" class="wfocu_submit_btn_style"/>
                </div>
            </fieldset>
        </form>
    </div>

</div>