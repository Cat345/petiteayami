<?php
$display_pointer = false;

$blink_url = WFOB_PLUGIN_URL . '/assets/img/arrow-no-blink.gif';

$enable_pointer  = 'wfob_enable_pointer';
$title_class[]   = 'wfob_display_pointer';
$display_pointer = true;

?>
<div class="<?php echo implode( ' ', $title_class ) ?>">
    <div class="wfob_bgBox_table">
        <div class="wfob_bump_title_start">

            <div class="wfob_checkbox_input_wrap">
				<?php
				include WFOB_SKIN_DIR . '/template-parts/wfob-pointer.php';
				?>
                <span class="wfob_bump_checkbox">
                <input type="checkbox" name="<?php echo $product_key; ?>" id="<?php echo $product_key; ?>" data-value="<?php echo $product_key; ?>" class="wfob_checkbox wfob_bump_product <?php echo $checkbox_class; ?>" <?php echo '' != $cart_item_key ? 'checked' : ''; ?> <?php echo $disabled; ?>>

            </span>

            </div>


            <div class="wfob_label_wrap">
                <label for="<?php echo $product_key; ?>" class="wfob_title"> <?php echo do_shortcode( $titleHeading ); ?> </label>
            </div>

        </div>

        <!-- New tooltip element -->
		<?php

		include WFOB_SKIN_DIR . "/template-parts/wfob-social-proof-tool-tip.php";
		$this->print_bump_price( $final_data, $product_key ); ?>


    </div>

</div>
