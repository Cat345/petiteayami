<?php
$tmp = array(
	'wfob_exclusive_content',
	wp_kses_post( $special_offer_position ),
);
if ( true === $print_bump && true === wc_string_to_bool( $exclusive_content_enable ) ) {

	echo '<div class="' . implode( ' ', $tmp ) . '"><span>' . wp_kses_post( $exclusive_content ) . '</span></div>';
} elseif ( false === $print_bump ) {
	echo '<div class="' . implode( ' ', $tmp ) . '"><span>' . wp_kses_post( $exclusive_content ) . '</span></div>';
}
