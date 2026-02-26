<?php
do_action('wfob_before_product_description');
echo '<div class="wfob_text_inner wfob_skin_description ffff3333">' . do_shortcode( $decode_merge_tags ) . '</div>';
do_action('wfob_after_product_description',$wc_product,$cart_item_key);
echo '<div class="wfob_error_message">' . $wfob_error_message . '</div>';
