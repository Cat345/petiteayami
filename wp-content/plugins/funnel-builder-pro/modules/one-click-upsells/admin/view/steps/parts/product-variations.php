<div v-if="product.vars_count>0" class="have_variation">
	<?php _e( "{{product.vars_count}} Variants <a class='have_variation_expand'>(Expand</a> / <a class='have_variation_close'>Close)</a> ", 'woofunnels-upstroke-one-click-upsell' ) ?>
</div>
<div v-if="product.vars_count>0 && isEmpty(allselectedVars[index])" class="have_variation red_notice">
	<?php _e( "In case of no variants selected, offer will skip during funnel.", 'woofunnels-upstroke-one-click-upsell' ) ?>
</div>
<table width="100%" class="variation_products" id="variant_product_id" v-bind:data-index="index" v-if="product.vars_count>0" border="1">
    <thead>
    <tr>
        <th>
            <input type="checkbox" v-on:change="disable_enable_variation(index,$event)" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][variations_enable]'" class="disable_enable_variation">
        </th>
        <th><?php _e( 'Default', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
        <th><?php _e( 'Attributes', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
        <th><?php _e( 'Price', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
        <th><?php _e( 'Discount', 'woofunnels-upstroke-one-click-upsell' ); ?></th>
    </tr>
    </thead>
    <tbody>
    <tr v-for="(variation, var_index) in product.variations" v-bind:id="variation.vid" class="product_variation_row">
        <td>
            <input type="checkbox" v-model="variation.is_enable" v-on:change="disable_enable_variation_row(index,$event,var_index)" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][variations]['+var_index+'][is_enable]'" class="variation_check" v-bind:data-variation="var_index">
        </td>
        <td>
            <input type="radio" v-model="product.default_variation" name="'offers['+current_offer_id+'][products]['+index+'][default_variation]'" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][default_variation]'" v-bind:value="var_index" v-bind:data-variation="var_index" class="default_variation">
        </td>
        <td>
            <div class="variation_attributes">
                <p v-for="(attr_i ,attribute) in variation.attributes"> {{attribute}} : {{attr_i}}</p>
            </div>
        </td>
        <td>
            <div class=" product_options">
                <p v-if="variation.regular_price!=='undefined'"><?php _e( 'Regular Price', 'woofunnels-upstroke-one-click-upsell' ); ?>: <span v-html="variation.regular_price"></span>
                </p>

                <br/>
                <p v-if="variation.hasOwnProperty('price') && variation.price != ''"><?php _e( 'Sale Price', 'woofunnels-upstroke-one-click-upsell' ); ?>: <span v-html="variation.price"></span></p>
                <br v-if="variation.hasOwnProperty('price') && variation.price != ''"/>
                <p><?php _e( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' ); ?>: <span v-bind:class="'wfocu_of_price_var_'+index+'_'+var_index" v-html="offer_price_html_var(variation,product)"></span>
                </p><?php WFOCU_Core()->admin->tooltip( 'Prices are <span v-bind:class="\'wfocu_of_price_data_var_\'+index+\'_\'+var_index" v-html="offer_price_tooltip_var(variation,product)"></span>' ); ?>

            </div>
        </td>
        <td>

            <input name="variation_discount" v-model="products[index].variations[var_index].discount_amount" type="number" step="0.01" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][variations]['+var_index+'][discount_amount]'" v-bind:data-variation="var_index" :readonly="(!variation.is_enable)" class="variation_discount" oninput="this.value = Math.abs(this.value)" v-on:keyup="update_offer_price($event,index)">

            <!-- This Hidden input placed here just to make sure the vue instance update himself on change of the modal data -->
            <input style="display:none;" name="hidden_v" disabled v-model="hidden_v" type="number" step="0.01">
        </td>
    </tr>
    </tbody>
</table>

