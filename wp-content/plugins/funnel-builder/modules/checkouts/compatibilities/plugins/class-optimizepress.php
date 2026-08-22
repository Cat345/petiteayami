<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
/**
 * OptimizePress
 */
add_filter( 'wfacp_do_not_allow_shortcode_printing', '__return_true' );