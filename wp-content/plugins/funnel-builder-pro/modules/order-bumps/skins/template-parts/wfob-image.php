<?php
if ( wc_string_to_bool( $featured_image ) ) {
	echo '<div class="wfob_pro_img_wrap">';
	echo wp_kses_post( $image_html );
	echo '</div>';
}
