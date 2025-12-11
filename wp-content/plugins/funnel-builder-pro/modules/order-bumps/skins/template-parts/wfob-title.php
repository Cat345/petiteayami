<?php

if ( true === $print_bump && true === wc_string_to_bool( $exclusive_content_enable ) && "wfob_exclusive_above_title" == $exclusive_content_position ) {

	echo '<div class="wfob_exclusive_content wfob_exclusive_above_title"><span>' . $exclusive_content . '</span></div>';
} elseif ( false === $print_bump ) {
	echo '<div class="wfob_exclusive_content wfob_exclusive_above_title"><span>' . $exclusive_content . '</span></div>';
}

?>
<div class="wfob_content_sec <?php echo $enable_pointer; ?>">
    <label for="<?php echo $product_key; ?>" class="wfob_title"><?php echo do_shortcode( $titleHeading ); ?></label>
</div>