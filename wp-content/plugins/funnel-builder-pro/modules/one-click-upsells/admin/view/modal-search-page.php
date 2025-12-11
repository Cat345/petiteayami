<div style="display: none" id="modal-prev-template_custom-page" class="modal-prev-temp wfocu_izimodal_default" data-iziModal-title="<?php echo $custom_page['name']; ?>" data-iziModal-icon="icon-home">

	<div class="sections wfocu_custom_preview">
		<form class="wfocu_add_funnel" id="modal-page-search-form" data-wfoaction="get_custom_page" v-on:submit.prevent="onSubmit">
			<div class="wfocu_vue_forms">
				<fieldset>
					<div class="form-group ">
						<div id="part-custom-template">
							<label><?php _e( 'Select a Page', 'woofunnels-upstroke-one-click-upsell' ); ?></label>
							<multiselect v-model="selectedProducts" id="ajax" label="page_name" track-by="page_name" placeholder="Type to search" open-direction="bottom" :options="products" :multiple="false" :searchable="true" :loading="isLoading" :internal-search="true" :clear-on-select="true" :close-on-select="true" :options-limit="300" :limit="3" :max-height="600" :show-no-results="true" :hide-selected="true" @search-change="asyncFind">
								<template slot="clear" slot-scope="props">
									<div class="multiselect__clear" v-if="selectedProducts.length" @mousedown.prevent.stop="clearAll(props.search)"></div>
								</template>
								<span slot="noResult"><?php echo __( 'Oops! No elements found. Consider changing the search query.', 'woofunnels-upstroke-one-click-upsell' ); ?></span>
							</multiselect>
							<input type="hidden" name="funnel_id" v-bind:value="funnel_id">
						</div>
					</div>
				</fieldset>
				<fieldset>
					<div class="wfocu_form_submit wfocu_swl_btn">
						<button data-iziModal-close type="submit" class="wf_cancel_btn wfocu_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
						<button type="submit" class="wfocu_btn_primary wfocu_btn" value="save_page"><?php echo __( 'Select this page', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
					</div>
					<div class="wfocu_form_response">
					</div>
				</fieldset>
			</div>
		</form>
	</div>
</div>