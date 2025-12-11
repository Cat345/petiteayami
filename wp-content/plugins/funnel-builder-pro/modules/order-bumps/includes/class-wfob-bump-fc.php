<?php
include __DIR__ . '/skins-classes/abstract.php';
include __DIR__ . '/skins-classes/bump.php';

$bump_folder = [ 'bump-skins' ];


foreach ( $bump_folder as $folder ) {


	$files = glob( plugin_dir_path( WFOB_PLUGIN_FILE ) . '/includes/skins-classes/' . $folder . '/*.php' );

	foreach ( $files as $_field_filename ) {
		$basename = basename( $_field_filename );

		if ( false !== strpos( $basename, 'index.php' ) || ! file_exists( $_field_filename ) ) {

			continue;
		}
		require_once( $_field_filename );
	}
}

do_action( 'wfob_setup_order_bump' );
