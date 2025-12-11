<div class="wfob_l3_s_btn">
	<?php
	$icon_list = [
		'wfob_cta_cursor'   => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" clip-rule="evenodd" d="M12.3142 9.90121L16.509 14.096C16.7188 14.3058 16.7188 14.6728 16.509 14.8826L14.8835 16.5081C14.6738 16.7178 14.3068 16.7178 14.097 16.5081L9.90219 12.3132L7.59503 15.5642C7.22799 16.0361 6.49389 15.9313 6.33659 15.3545L3.34777 4.18576C3.2429 3.71384 3.71482 3.24193 4.18674 3.3468L15.3555 6.33561C15.9322 6.49292 16.0371 7.22701 15.5652 7.59406L12.3142 9.90121Z" fill="currentColor"/>
</svg>',
		'wfob_cta_cart' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1 1.6427C1 1.28775 1.28775 1 1.6427 1H2.20617C3.14058 1 3.67708 1.60809 3.98785 2.21426C4.20081 2.62963 4.35417 3.13638 4.48088 3.57519H17.7138C18.5667 3.57519 19.1831 4.39066 18.9505 5.21123L17.0281 11.9911C16.7145 13.0972 15.7046 13.8606 14.5548 13.8606H8.02598C6.8668 13.8606 5.851 13.0848 5.54585 11.9665L4.72009 8.94029C4.71501 8.92654 4.71036 8.91252 4.70615 8.89824L3.38304 4.40221C3.33764 4.25304 3.29586 4.10848 3.25553 3.9689C3.12718 3.52476 3.01344 3.13115 2.84402 2.80068C2.63918 2.40113 2.45109 2.2854 2.20617 2.2854H1.6427C1.28775 2.2854 1 1.99765 1 1.6427ZM8.07347 19C9.13833 19 10.0016 18.1368 10.0016 17.0719C10.0016 16.007 9.13833 15.1438 8.07347 15.1438C7.00861 15.1438 6.14537 16.007 6.14537 17.0719C6.14537 18.1368 7.00861 19 8.07347 19ZM14.5005 19C15.5653 19 16.4286 18.1368 16.4286 17.0719C16.4286 16.007 15.5653 15.1438 14.5005 15.1438C13.4356 15.1438 12.5724 16.007 12.5724 17.0719C12.5724 18.1368 13.4356 19 14.5005 19Z" fill="currentColor"/>
</svg>'


	];



	if ( $print_bump == true && isset( $icon_list[ $icon_on_button ] ) ) {
		$add_btn_text .= $icon_list[ $icon_on_button ];
	} elseif ( $print_bump == false ) {
		$tmp          = '<span>' . $add_btn_text . '</span>' . $icon_list['wfob_cta_cursor'] . $icon_list['wfob_cta_cart'];
		$add_btn_text = $tmp;
	}


	?>

    <a data-key="<?php echo $product_key; ?>" href="#" class="wfob_l3_f_btn wfob_btn_add <?php echo $checkbox_class; ?>" style="<?php echo '' !== $cart_item_key ? 'display:none' : '' ?>">
		<?php echo $add_btn_text; ?>
    </a>
    <a data-key="<?php echo $product_key; ?>" href="#" class="wfob_l3_f_btn wfob_btn_add wfob_btn_remove <?php echo '' !== $cart_item_key ? 'wfob_item_present' : '' ?>">
        <span class="wfob_btn_text_added"><?php echo $added_btn_text ?></span>
        <span class="wfob_btn_text_remove"><?php echo $remove_btn_text ?></span>
    </a>
	<?php

	include WFOB_SKIN_DIR . '/template-parts/wfob-social-proof-tool-tip.php';
	?>


</div>

