<?php
$tmp = [
	'wfob_exclusive_content',
	$special_offer_position
];
if ( true === $print_bump && true === wc_string_to_bool( $exclusive_content_enable ) ) {

	echo '<div class="' . implode( ' ',$tmp ) . '"><span>' . $exclusive_content . '</span></div>';
} elseif ( false === $print_bump ) {
	echo '<div class="' . implode( ' ',$tmp ) . '"><span>' . $exclusive_content . '</span></div>';
}


