<label for="<?php echo esc_attr( $product_key ); ?>" class="wfob_title"> <?php echo wp_kses_post( do_shortcode( $titleHeading ) ); ?> </label>
<input type="checkbox" name="<?php echo esc_attr( $product_key ); ?>" id="<?php echo esc_attr( $product_key ); ?>" data-value="<?php echo esc_attr( $product_key ); ?>" class="wfob_checkbox wfob-switch wfob_bump_product <?php echo esc_attr( $checkbox_class ); ?>" <?php echo '' != $cart_item_key ? 'checked' : ''; ?> <?php echo esc_attr( $disabled ); ?>>
<label class="wfob_toggle_label" for="<?php echo esc_attr( $product_key ); ?>"><span class="sw"></span></label>
<?php require WFOB_SKIN_DIR . '/template-parts/wfob-social-proof-tool-tip.php'; ?>
