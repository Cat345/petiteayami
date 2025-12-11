<div class="wfocu_izimodal_default" id="modal-add-offer-step">
	<div class="sections">
		<form class="wfocu_forms_wrap" data-wfoaction="add_offer" novalidate>
			<div class="wfocu_vue_forms" id="part3">
				<vue-form-generator :schema="schema" :model="model" :options="formOptions" ref="addOfferForm"></vue-form-generator>
				<fieldset>
					<div class="wfocu_form_submit wfocu_swl_btn">
						<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( 'wfocu_add_offer' ); ?>"/>
						<input data-iziModal-close type="button" class="wfocu_btn wf_cancel_btn" value="<?php _e( 'Cancel', 'woofunnels-upstroke-one-click-upsell' ) ?>"/>
						<input type="submit" value="<?php _e( 'Add', 'woofunnels-upstroke-one-click-upsell' ) ?>" class="wfocu_btn_primary wfocu_btn"/>
					</div>
				</fieldset>
			</div>
		</form>
	</div>
</div>