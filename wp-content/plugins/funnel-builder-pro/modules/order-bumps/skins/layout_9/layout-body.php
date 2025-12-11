<div class="bwf_display_flex">
    <div class="bwf_display_col_flex wfob_pro_image_wrap">
		<?php include WFOB_SKIN_DIR . '/template-parts/wfob-image.php' ?>
    </div>
    <div class="bwf_display_col_flex wfob_pro_txt_wrap">
        <div class="wfob_pro_txt_wrap ">


			<?php


			include WFOB_SKIN_DIR . '/template-parts/wfob-title.php';

			/**
			 * Display Special Offer above description
			 */

			$special_offer_position = 'wfob_exclusive_above_description';
			include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';

			include WFOB_SKIN_DIR . '/template-parts/wfob-desciption.php';
			include WFOB_SKIN_DIR . "/template-parts/wfob-variation.php";

			/**
			 * Display Special Offer below description
			 */
			$special_offer_position = 'wfob_exclusive_below_description';
			include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';


			include WFOB_SKIN_DIR . "/template-parts/wfob-price.php";

			include WFOB_SKIN_DIR . '/template-parts/wfob-add-to-cart.php';


			$title_class = [ 'wfob_title_wrap' ];



			?>

        </div>

    </div>


</div>


