<div class="bwf_display_flex">
    <div class="bwf_display_col_flex wfob_pro_image_wrap">
		<?php include WFOB_SKIN_DIR . '/template-parts/wfob-image.php' ?>
    </div>
    <div class="bwf_display_col_flex wfob_pro_txt_wrap">
        <div class="wfob_pro_txt_wrap ">


			<?php
			include WFOB_SKIN_DIR . '/template-parts/wfob-title.php';

			include WFOB_SKIN_DIR . '/template-parts/wfob-sub-title.php';


			include WFOB_SKIN_DIR . '/template-parts/wfob-small-description.php';


			?>

            <div class="wfob_l3_s_desc wfob_description_wrap" style="<?php echo $description_display_none; ?>">
				<?php

				include WFOB_SKIN_DIR . '/template-parts/wfob-desciption.php';
				include WFOB_SKIN_DIR . "/template-parts/wfob-variation.php";


				?>

            </div>
            <a href="#" class="wfob_read_more_link wfob_read_more_des_link"> less...</a>
			<?php

			$title_class = [ 'wfob_title_wrap' ];


			include WFOB_SKIN_DIR . "/template-parts/wfob-variation.php";


			?>

        </div>
        <div class="bwf_display_col_flex wfob_add_to_cart_button">

			<?php
			include WFOB_SKIN_DIR . "/template-parts/wfob-price.php";

			include WFOB_SKIN_DIR . '/template-parts/wfob-add-to-cart.php';
			?>



        </div>
    </div>


</div>


