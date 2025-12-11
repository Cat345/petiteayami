<div class="bwf_full_width bwf_title_wrap bwf_mb_15">
	<?php

	$title_class = [ 'wfob_title_wrap' ];
	include WFOB_SKIN_DIR . '/template-parts/wfob-checkbox.php';

	?>
</div>
<div class="bwf_display_flex">

    <div class="bwf_display_col_flex wfob_pro_image_wrap">
		<?php include WFOB_SKIN_DIR . '/template-parts/wfob-image.php' ?>
    </div>
    <div class="bwf_display_col_flex wfob_pro_txt_wrap">

        <div class="wfob_pro_txt_wrap ">
            <div class="wfob_l3_s_desc wfob_description_wrap">
				<?php
				$special_offer_position = 'wfob_exclusive_above_description';
				include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';

				include WFOB_SKIN_DIR . '/template-parts/wfob-desciption.php';
				include WFOB_SKIN_DIR . "/template-parts/wfob-variation.php";

				$special_offer_position = 'wfob_exclusive_below_description';
				include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';
				?>
            </div>
            <div class="wfob_add_to_cart_button">

		        <?php include WFOB_SKIN_DIR . '/template-parts/wfob-add-to-cart.php'; ?>
            </div>

        </div>


    </div>


</div>






