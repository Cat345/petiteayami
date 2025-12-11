<form class="wfocu_forms_wrap" data-wfoaction="save_funnel_offer_products" v-bind:data-offer_id="current_offer_id">
	<input type="hidden" value="<?php echo wp_create_nonce( 'wfocu_save_funnel_offer_products' ); ?>" name="_nonce"/>
	<div v-if="current_offer_id>0 && selected_product>0">

		<?php include __DIR__ . "/top-save-button.php"; ?>
		<div class="wfocu_product_list_wrap " v-if="Object.keys(products).length>0">

			<div class="product_list" style="">

				<table class="product_section_table" width="100%">
					<thead class="listing_table_head">
					<tr class="product_tb_row">
						<td>
							<table class="main_products">
								<tbody class="listing_table_head">
								<tr>
									<th class="product_th"><?php esc_html_e( "Product", 'woofunnels-upstroke-one-click-upsell' ) ?></th>
									<th><?php esc_html_e( "Discount", 'woofunnels-upstroke-one-click-upsell' ); ?> </th>
									<th><?php esc_html_e( "Quantity", 'woofunnels-upstroke-one-click-upsell' ); ?></th>
									<th><?php esc_html_e( "Flat Shipping", 'woofunnels-upstroke-one-click-upsell' ); ?></th>
									<th><?php esc_html_e( "Action", 'woofunnels-upstroke-one-click-upsell' ); ?></th>
								</tr>
								</tbody>
							</table>
						</td>
					</tr>
					</thead>
					<tbody class="tb_body">
					<tr v-for="(product, index) in products" v-bind:id="current_offer_id+index" v-bind:data-proid="index" class="product_tb_row">
						<td>
							<table class="main_products">
								<tbody>
								<tr class="wfocu-product-row">
									<td>
										<input type="hidden" v-bind:value="product.id" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][id]'" style="width: 51px;">
										<div class="product_image" style="">
											<img v-bind:src="product.image" style="max-width: 100%">
										</div>
										<div class="product_details">
											<div class="product_name">{{product.name}}</div>
											<div class="product_options">
												<div class="product_type"><p><?php esc_html_e( 'Type: ', 'woofunnels-upstroke-one-click-upsell' ); ?>{{product.type != undefined ? product.type :
														`simple`}}</p></div>
												<p class="product-price" v-if="typeof product.regular_price!=='undefined'"><?php esc_html_e( 'Regular Price', 'woofunnels-upstroke-one-click-upsell' ); ?>
													:
													<span v-html="product.regular_price"></span>
												</p>
												<p v-if="typeof product.price!=='undefined'"><?php esc_html_e( 'Sale Price', 'woofunnels-upstroke-one-click-upsell' ); ?>:
													<span v-html="product.price"></span>
												</p>

												<div v-if="product.type !== 'variable' && product.type !== 'variable-subscription'">
													<p><?php _e( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' ); ?>:
														<span v-bind:class="'wfocu_of_price_'+index" v-html="offer_price_html(product)"></span>
													</p> <?php WFOCU_Core()->admin->tooltip( 'Prices are <span v-bind:class="\'wfocu_of_price_data_\'+index" v-html="prepare_price_help_html(product)"></span>' ); ?>
												</div>

											</div>
										</div>
										<div class="clear"></div>
									</td>
									<!-- Discount inputs -->
									<td>
										<input type="number" v-model="product.discount_amount" step="0.01" min="0" v-bind:value="!product.discount_amount?0:product.discount_amount" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][discount_amount]'" style="width: 60px;" v-on:keyup="set_variation_discount($event,index); update_offer_price($event,index)" oninput="this.value = Math.abs(this.value)" class="discount_number">

										<select class="product_discount_type" v-model="product.discount_type" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][discount_type]'" class="discount_number" v-on:change="update_offer_price($event,index)">
											<option value="percentage_on_sale"><?php _e( 'Percentage % on Sale Price', 'woofunnels-upstroke-one-click-upsell' ); ?></option>
											<option value="percentage_on_reg"><?php _e( 'Percentage % on Regular Price', 'woofunnels-upstroke-one-click-upsell' ); ?></option>
                                            <option value="fixed_on_sale"><?php _e( 'Fixed Amount on Sale Price', 'woofunnels-upstroke-one-click-upsell' ); ?></option>
                                            <option value="fixed_on_reg"><?php _e( 'Fixed Amount on Regular Price', 'woofunnels-upstroke-one-click-upsell' ); ?></option>
										</select>

									</td>

									<!-- Quantity input -->
									<td>
										<input type="number" v-model="product.quantity" min="1" v-bind:value="!product.quantity?1:product.quantity" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][quantity]'" style="width: 60px;" oninput="this.value = Math.abs(this.value)" v-on:keyup="update_offer_price($event,index)">
									</td>
									<td>
										<input type="number" class="wfocu-offer-flat-shipping-input" v-model="product.shipping_cost_flat" step="0.01" min="0" v-bind:value="!product.shipping_cost_flat?0:product.shipping_cost_flat" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][shipping_cost_flat]'" style="width: 65px;" oninput="this.value = Math.abs(this.value)" v-on:keyup="update_offer_price($event,index)">
									</td>
									<td>
										<button type="button" class="wfocu_form_remove_product" v-on:click="remove_product && remove_product(current_offer_id+index)">
											<?php echo file_get_contents( plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/assets/img/icons/delete.svg' ) ?>
										</button>
									</td>
								</tr>
								<tr v-if="undefined!==typeof product.status&&'publish'!==product.status" class="no-top-border">
									<td colspan="5">
										<div class="have_variation red_notice">
											<?php esc_html_e( 'Product no longer exists. Replace this product with an available product.', 'woofunnels-upstroke-one-click-upsell' ); ?>
										</div>
									</td>
								</tr>

								</tbody>
							</table>
							<?php
							do_action('wfocu_add_custom_html_in_offer_admin_area');
							include __DIR__ . "/product-variations.php";
							?>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
			<div style="clear: both;"></div>
		</div>
	</div>
</form>