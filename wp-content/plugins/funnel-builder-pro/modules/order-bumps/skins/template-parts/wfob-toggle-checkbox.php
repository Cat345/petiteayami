<label for="<?php echo $product_key; ?>" class="wfob_title"> <?php echo do_shortcode( $titleHeading ); ?> </label>
<input type="checkbox" name="<?php echo $product_key; ?>" id="<?php echo $product_key; ?>" data-value="<?php echo $product_key; ?>" class="wfob_checkbox wfob-switch wfob_bump_product <?php echo $checkbox_class; ?>" <?php echo '' != $cart_item_key ? 'checked' : ''; ?> <?php echo $disabled; ?>>
<label class="wfob_toggle_label" for="<?php echo $product_key; ?>"><span class="sw"></span></label>
<?php include WFOB_SKIN_DIR . "/template-parts/wfob-social-proof-tool-tip.php"; ?>
