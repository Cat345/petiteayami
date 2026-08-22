<div class="wfob_l12_card bwf_display_flex wfob_text_center">

	<?php
	/**
	 * Product image tile (respects the featured-image toggle)
	 */
	?>
	<div class="bwf_display_col_flex wfob_pro_image_wrap">
		<?php require WFOB_SKIN_DIR . '/template-parts/wfob-image.php'; ?>
	</div>

	<?php
	/**
	 * Product Tag ("MOST POPULAR") - per-product exclusive content, rendered once above the title.
	 */
	$special_offer_position = 'wfob_exclusive_above_title';
	require WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';
	?>

	<div class="bwf_full_width wfob_title_wrap_center bwf_mb_15">
		<?php
		$title_class = array( 'wfob_title_wrap' );
		require WFOB_SKIN_DIR . '/template-parts/wfob-checkbox.php';
		?>
	</div>

	<div class="wfob_l12_s_desc wfob_description_wrap">
		<?php
		require WFOB_SKIN_DIR . '/template-parts/wfob-desciption.php';
		require WFOB_SKIN_DIR . '/template-parts/wfob-variation.php';
		?>
	</div>

	<div class="wfob_l12_action_row bwf_display_flex">
		<div class="wfob_l12_price">
			<?php $this->print_bump_price( $final_data, $product_key ); ?>
		</div>
		<div class="wfob_add_to_cart_button">
			<?php require WFOB_SKIN_DIR . '/template-parts/wfob-add-to-cart.php'; ?>
		</div>
	</div>

</div>
