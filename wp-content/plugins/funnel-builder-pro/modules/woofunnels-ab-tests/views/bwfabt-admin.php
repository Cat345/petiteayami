<?php defined( 'ABSPATH' ) || exit; //Exit if accessed directly

$experiments = BWFABT_Core()->admin->get_experiments( array( 'screen' => 'old' ) );
$experiment_section = filter_input( INPUT_GET, 'section', FILTER_UNSAFE_RAW );

if ( true === BWFABT_Core()->admin->has_filter() || isset( $experiments['found_posts'] ) && $experiments['found_posts'] > 0 ) {
	include_once( __DIR__ . '/bwfabt-experiment-listing-view.php' );
} else {
	include_once( __DIR__ . '/bwfabt-new-experiment-view.php' );
}
