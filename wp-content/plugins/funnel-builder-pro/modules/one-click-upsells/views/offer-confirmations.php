<?php
$template_group = WFOCU_Core()->template_loader->current_template_group;

if ( ! empty( $template_group ) && $template_group instanceof WFOCU_Template_Group_Customizer ) {

	$head_text = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_header_text' );
	$yes_text  = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cta_yes_text' );
	$no_text   = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cta_no_text' );

	$img_product                                                                                       = wc_placeholder_img_src();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']           = array();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']  = array();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['bg']     = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_bg_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['shadow'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_shadow_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['text']   = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_text_color' );

	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['bg']     = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['shadow'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_shadow_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['text']   = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_color_text' );


	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_no_color']       = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_no_btn_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_no_color_hover'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_no_btn_color_hover' );


	WFOCU_Core()->template_loader->current_template->internal_css['cart_opener']            = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['cart_opener_text_color'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_text_color' );
	$cart_opener_text                                                                       = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_text' );

} else {
	if ( is_null( WFOCU_Core()->template_loader->current_template ) ) {
		WFOCU_Core()->template_loader->current_template = new stdClass();
	}
	$head_text        = WFOCU_Core()->data->get_option( 'offer_header_text' );
	$yes_text         = WFOCU_Core()->data->get_option( 'offer_yes_btn_text' );
	$no_text          = WFOCU_Core()->data->get_option( 'offer_skip_link_text' );
	$cart_opener_text = WFOCU_Core()->data->get_option( 'cart_opener_text' );

	$img_product                                                                                       = wc_placeholder_img_src();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']           = array();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']  = array();
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['bg']     = WFOCU_Core()->data->get_option( 'offer_yes_btn_bg_cl' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['shadow'] = WFOCU_Core()->data->get_option( 'offer_yes_btn_sh_cl' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['text']   = WFOCU_Core()->data->get_option( 'offer_yes_btn_txt_cl' );

	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['bg']     = WFOCU_Core()->data->get_option( 'offer_yes_btn_bg_cl_h' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['shadow'] = WFOCU_Core()->data->get_option( 'offer_yes_btn_sh_cl_h' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_yes_color']['hover']['text']   = WFOCU_Core()->data->get_option( 'offer_yes_btn_txt_cl_h' );


	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_no_color']       = WFOCU_Core()->data->get_option( 'offer_no_btn_txt_cl' );
	WFOCU_Core()->template_loader->current_template->internal_css['offer_confirm_no_color_hover'] = WFOCU_Core()->data->get_option( 'offer_no_btn_txt_cl_h' );


	WFOCU_Core()->template_loader->current_template->internal_css['cart_opener']            = WFOCU_Core()->data->get_option( 'cart_opener_background_color' );
	WFOCU_Core()->template_loader->current_template->internal_css['cart_opener_text_color'] = WFOCU_Core()->data->get_option( 'cart_opener_text_color' );

}

?>
<div class="wfocu-woocommerce-cart-tab-container  wfocu-right-off-canvas-menu wfocu-sidebar-cart">
	<div class="wfocu-mc-wrapper">
		<div class="wfocu-mc-headingarea">
			<div class="wfocu-mc-heading">
				<div class="wfocu-mc-head-text">  <?php echo esc_html($head_text); ?> </div>
				<div class="wfocu-mc-close"><a href="javascript:void(0)"><i class="dashicons dashicons-no-alt"></i></a></div>

			</div>
		</div>
		<div class="wfocu-cart-components"></div>

	</div>

</div>


<script type="text/html" id="tmpl-wfocu-bucket-template">
	<div class="wfocu-sidecart-content">
		<div class="wfocu-mc-loader">
			<img class="skip-lazy" src="<?php echo esc_url(WFOCU_PLUGIN_URL); ?>/assets/img/mc-spinner.gif" alt="" title=""/>
		</div>
		<div class="wfocu-sidecart-items">
			<# var i = 0; #>
			<# var getItemsDataDislay = data.Bucket.getItemsDataDislay(); #>
			<# var getItemPrices = data.Bucket.getItemsPrices(); #>

			<# if(data.Bucket.getItems().length > 0 ) { #>
			<# _(data.Bucket.getItems()).each(function(it) { #>
			<div class="wfocu-cart-items-row">
				<# _(data.Bucket.globalVars.offer_data.products[it]) #>
				<div class="wfocu-item-img">
					<img src="{{data.Bucket.globalVars.offer_data.products[it].image}}" class="" alt="{{data.Bucket.globalVars.offer_data.products[it].name}}">
				</div>
				<div class="wfocu-item-details">
					<div class="wfocu-item-info-top ">
						<div class="wfocu-item-name">
							{{data.Bucket.globalVars.offer_data.products[it].name}} x {{data.Bucket.getItemDisplayQuantity(it,i)}}
						</div>
						<div class="wfocu-item-cross-img">
							<a href="javascript:void(0);" class="wfocu_remove_item" aria-label="Remove this item" data-index="<# print(i); #>" data-cart_item_key="<# print(it); #>" data-product_sku="">
								<img class="skip-lazy" src="<?php echo esc_url(WFOCU_PLUGIN_URL); ?>/assets/img/mc-close-icon.png" alt="Remove">
							</a>
						</div>
					</div>
					<div class="wfocu-item-info-btm wfocu-clearfix">
						<# if(getItemsDataDislay[i].length > 0 ) { #>
						<div class="wfocu-item-desc">
							<# _(getItemsDataDislay[i]).each(function(it) { #>
							<div class="wfocu-item-m ">
								<span class="wfocu-desc-m-label">{{it.title}}: </span>
								<span class="wfocu-desc-m-val">{{(it.val)}}</span>

							</div>
							<# }); #>
						</div>
						<# } #>
						<?php do_action( 'wfocu_offer_confirmation_item_desc' ); ?>

						<div class="wfocu-item-price-side">
							<# print(data.Bucket.formatMoney(data.Bucket.getItemDisplayPrice(i))); #>
						</div>
					</div>
				</div>
			</div>

			<# i++; #>
			<# }) #>
		</div>
	</div>
	<div class="wfocu-sidecart-footer-content">
		<div class="wfocu-sidecart-footer-top">
			<div class="wfocu-mc-loader">
				<img class="skip-lazy" src="<?php echo esc_url(WFOCU_PLUGIN_URL); ?>/assets/img/mc-spinner.gif" alt="" title=""/>
			</div>
			<div class="wfocu-mc-footer-row wfocu-mc-subtotal">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<# print(data.Bucket.formatMoney( data.Bucket.getSubtotalPrice())); #>
				</div>
			</div>

			<# if(data.Bucket.shippingOptions.length > 0 ) { #>
			<# if(data.Bucket.shippingOptions.length === 1) { #>
			<div class="wfocu-mc-footer-row wfocu-mc-shipping  wfocu-mc-shipping-def-mode">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<div class="wfocu-mc-shipping-price"><# print(data.Bucket.formatMoney( data.Bucket.getShippingPrintDiff(data.Bucket.shippingOptions[0].value))); #><span><# print(data.Bucket.shippingOptions[0].label) #></span>
					</div>
				</div>
			</div>

			<# }else{ #>
			<div class="wfocu-mc-footer-row wfocu-mc-shipping  wfocu-mc-shipping-edit-mode">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<div class="wfocu-mc-shipping-options wfocu-radio-wrapper">
						<ul class="wfocu-clearfix wfocu_ship_selector">
							<# var i = 1; #>
							<# _(data.Bucket.shippingOptions).each(function(it) { #>
							<li>
								<input type="radio" value="{{it.value}}" id="wfocu-shipping-<# print(i); #>" name="shipping-method" {{it.selected}}/>
								<label for="wfocu-shipping-<# print(i); #>">{{it.label}} (<# print(data.Bucket.formatMoney( data.Bucket.getShippingPrintDiff(it.value))); #>)</label>
							</li>
							<# i++; #>
							<# }) #>
						</ul>
					</div>
				</div>
			</div>
			<# } #>
			<# }else if( data.Bucket.itemsNeedShipping > 0 ){ #>
			<div class="wfocu-mc-footer-row wfocu-mc-shipping  wfocu-mc-shipping-def-mode">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<div class="wfocu-mc-shipping-price">
						<# print(data.Bucket.formatMoney( 0)); #>
					</div>
				</div>
			</div>
			<# }#>

			<# if(true === data.Bucket.globalVars.global.include_taxes) { #>
			<div class="wfocu-mc-footer-row wfocu-mc-tax">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<# print(data.Bucket.globalVars.tax_nice_name); #>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<# print(data.Bucket.formatMoney( data.Bucket.getTaxTotal())); #>
				</div>
			</div>
			<# } #>
			<div class="wfocu-mc-footer-row wfocu-mc-total">
				<div class="wfocu-mc-footer-col wfocu-mc-col-left">
					<?php esc_attr_e( 'Total', 'woocommerce' ); ?>
				</div>
				<div class="wfocu-mc-footer-col wfocu-mc-col-right">
					<# print(data.Bucket.formatMoney( data.Bucket.getTotal())); #>
				</div>
			</div>
		</div>
		<div class="wfocu-sidecart-footer-bottom">
			<div class="wfocu-mc-footer-row wfocu-text-center">
				<?php echo do_action('wfocu_offer_confirmation_before_btn');  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
				<div class="wfocu-mc-footer-btn ">
					<a href="javascript:void(0);" class="wfocu-mc-button" <?php $this->add_attributes_to_confirmation_button(); ?>><?php echo $yes_text; ?></a> <?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable ?>
				</div>
				<div class="wfocu-mc-footer-btm-text">
					<a href="javascript:void(0);" class="wfocu_skip_offer_mc"><?php echo $no_text; ?> <?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>

			</div>
		</div>

		<# } else { #>
		<div class="wfocu-mc-empty-msg"><?php esc_attr_e( 'No products found', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
		<# } #>
	</div>
</script>
<script type="text/html" id="tmpl-wfocu-body-next-template">
	<div class="wfocu-offer-btn-wrap wfocu-def-hide">
		<a class="wfocu-confirm-order-btn" href="javascript:void(0)">
			<span class="wfocu-left-arrow"></span>
			<span class="wfocu-opener-btn-bg"><?php echo $cart_opener_text; ?></span> <?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>
	<div class="wfocu-black-overlay"></div></div> </script>
