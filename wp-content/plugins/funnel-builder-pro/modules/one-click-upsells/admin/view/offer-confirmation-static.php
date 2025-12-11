<?php
$template_ins     = WFOCU_Core()->template_loader->get_template_ins();
$head_text        = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_header_text' );
$yes_text         = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cta_yes_text' );
$no_text          = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cta_no_text' );
$cart_opener_text = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_text' );

$img_product                                                     = wc_placeholder_img_src();
$template_ins->internal_css['offer_confirm_yes_color']           = array();
$template_ins->internal_css['offer_confirm_yes_color']['hover']  = array();
$template_ins->internal_css['offer_confirm_yes_color']['bg']     = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_bg_color' );
$template_ins->internal_css['offer_confirm_yes_color']['shadow'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_shadow_color' );
$template_ins->internal_css['offer_confirm_yes_color']['text']   = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_text_color' );

$template_ins->internal_css['offer_confirm_yes_color']['hover']['bg']     = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_color' );
$template_ins->internal_css['offer_confirm_yes_color']['hover']['shadow'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_shadow_color' );
$template_ins->internal_css['offer_confirm_yes_color']['hover']['text']   = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_yes_btn_hover_color_text' );


$template_ins->internal_css['offer_confirm_no_color']       = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_no_btn_color' );
$template_ins->internal_css['offer_confirm_no_color_hover'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_no_btn_color_hover' );


$template_ins->internal_css['cart_opener']            = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_color' );
$template_ins->internal_css['cart_opener_text_color'] = WFOCU_Common::get_option( 'wfocu_offer_confirmation_offer_confirmation_cart_opener_text_color' );
?>

<div class="wfocu-woocommerce-cart-tab-container  wfocu-right-off-canvas-menu wfocu-sidebar-cart">
	<div class="wfocu-mc-wrapper">
		<div class="wfocu-mc-headingarea">
			<div class="wfocu-mc-heading">
				<div class="wfocu-mc-head-text">    <?php echo $head_text; ?> </div>
				<div class="wfocu-mc-close"><a href="javascript:void(0)"><i class="dashicons dashicons-no-alt"></i></a></div>
			</div>
		</div>
		<div class="wfocu-cart-components">
			<div class="wfocu-sidecart-content">
				<div class="wfocu-mc-loader">
					<img src="" alt="" title="">
				</div>
				<div class="wfocu-sidecart-items">


					<div class="wfocu-cart-items-row">

						<div class="wfocu-item-img">
							<img src="<?php echo $img_product; ?>" class="" alt=" <?php _e( 'A test product', 'woofunnels-upstroke-one-click-upsell' ); ?>">
						</div>
						<div class="wfocu-item-details">
							<div class="wfocu-item-info-top ">
								<div class="wfocu-item-name">
									<?php _e( 'Dummy product x 1', 'woofunnels-upstroke-one-click-upsell' ); ?>
								</div>
								<div class="wfocu-item-cross-img">
									<a href="javascript:void(0);" class="wfocu_remove_item" aria-label="Remove this item" data-index="0" data-cart_item_key="f62cf1722596b8d90c6f7bf7a6ddbb23" data-product_sku="">
										<img src="<?php echo WFOCU_PLUGIN_URL; ?>/assets/img/mc-close-icon.png" alt="Remove">
									</a>
								</div>
							</div>
							<div class="wfocu-item-info-btm wfocu-clearfix">


								<div class="wfocu-item-price-side">
									$0.00
								</div>
							</div>
						</div>
					</div>


				</div>
			</div>
			<div class="wfocu-sidecart-footer-content">
				<div class="wfocu-sidecart-footer-top">
					<div class="wfocu-mc-loader">
						<img src="" alt="" title="">
					</div>
					<div class="wfocu-mc-footer-row wfocu-mc-subtotal">
						<div class="wfocu-mc-footer-col wfocu-mc-col-left">
							<?php _e( 'Subtotal', 'woocommerce' ); ?>
						</div>
						<div class="wfocu-mc-footer-col wfocu-mc-col-right">
							$0.00
						</div>
					</div>


					<div class="wfocu-mc-footer-row wfocu-mc-shipping  wfocu-mc-shipping-def-mode">
						<div class="wfocu-mc-footer-col wfocu-mc-col-left">
							<?php _e( 'Shipping', 'woocommerce' ); ?>
						</div>
						<div class="wfocu-mc-footer-col wfocu-mc-col-right">
							<div class="wfocu-mc-shipping-price">$0.00<span>  <?php _e( '(Flat Rate)', 'woocommerce' ); ?></span>
							</div>
						</div>
					</div>


					<div class="wfocu-mc-footer-row wfocu-mc-total">
						<div class="wfocu-mc-footer-col wfocu-mc-col-left">
							<?php _e( 'Total', 'woocommerce' ); ?>
						</div>
						<div class="wfocu-mc-footer-col wfocu-mc-col-right">
							$0.00
						</div>
					</div>
				</div>
				<div class="wfocu-sidecart-footer-bottom">
					<div class="wfocu-mc-footer-row wfocu-text-center">
						<div class="wfocu-mc-footer-btn ">
							<a href="javascript:void(0);" class="wfocu-mc-button"><?php echo $yes_text; ?></a>
						</div>
						<div class="wfocu-mc-footer-btm-text">
							<a href="javascript:void(0);" class="wfocu_skip_offer_mc"><?php echo $no_text; ?>
							</a>
						</div>

					</div>
				</div>


			</div>
		</div>

	</div>

</div>

<script type="text/html" id="tmpl-wfocu-body-next-template">
	<div class="wfocu-offer-btn-wrap wfocu-def-hide">
	       <a class="wfocu-confirm-order-btn" href="javascript:void(0)" >
	<span class="wfocu-left-arrow"></span>
	<span class="wfocu-opener-btn-bg"><?php echo $cart_opener_text; ?></span>
    </a>
		
	</div>
	<div class="wfocu-black-overlay"></div></div> </script>
