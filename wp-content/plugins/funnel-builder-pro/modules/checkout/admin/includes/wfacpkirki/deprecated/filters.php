<?php
// @codingStandardsIgnoreFile

add_filter( 'wfacpkirki_config', function( $args ) {
	return apply_filters( 'wfacpkirki/config', $args );
}, 99 );

add_filter( 'wfacpkirki_control_types', function( $args ) {
	return apply_filters( 'wfacpkirki/control_types', $args );
}, 99 );

add_filter( 'wfacpkirki_section_types', function( $args ) {
	return apply_filters( 'wfacpkirki/section_types', $args );
}, 99 );

add_filter( 'wfacpkirki_section_types_exclude', function( $args ) {
	return apply_filters( 'wfacpkirki/section_types/exclude', $args );
}, 99 );

add_filter( 'wfacpkirki_control_types_exclude', function( $args ) {
	return apply_filters( 'wfacpkirki/control_types/exclude', $args );
}, 99 );

add_filter( 'wfacpkirki_controls', function( $args ) {
	return apply_filters( 'wfacpkirki/controls', $args );
}, 99 );

add_filter( 'wfacpkirki_fields', function( $args ) {
	return apply_filters( 'wfacpkirki/fields', $args );
}, 99 );

add_filter( 'wfacpkirki_modules', function( $args ) {
	return apply_filters( 'wfacpkirki/modules', $args );
}, 99 );

add_filter( 'wfacpkirki_panel_types', function( $args ) {
	return apply_filters( 'wfacpkirki/panel_types', $args );
}, 99 );

add_filter( 'wfacpkirki_setting_types', function( $args ) {
	return apply_filters( 'wfacpkirki/setting_types', $args );
}, 99 );

add_filter( 'wfacpkirki_variable', function( $args ) {
	return apply_filters( 'wfacpkirki/variable', $args );
}, 99 );

add_filter( 'wfacpkirki_values_get_value', function( $arg1, $arg2 ) {
	return apply_filters( 'wfacpkirki/values/get_value', $arg1, $arg2 );
}, 99, 2 );

add_action( 'init', function() {
	$config_ids = WFACPKirki_Config::get_config_ids();
	global $wfacpkirki_deprecated_filters_iteration;
	foreach ( $config_ids as $config_id ) {
		foreach( array(
			'/dynamic_css',
			'/output/control-classnames',
			'/css/skip_hidden',
			'/styles',
			'/output/property-classnames',
			'/webfonts/skip_hidden',
		) as $filter_suffix ) {
			$wfacpkirki_deprecated_filters_iteration = array( $config_id, $filter_suffix );
			add_filter( "wfacpkirki_{$config_id}_{$filter_suffix}", function( $args ) {
				global $wfacpkirki_deprecated_filters_iteration;
				$wfacpkirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfacpkirki_deprecated_filters_iteration[1] );
				return apply_filters( "wfacpkirki/{$wfacpkirki_deprecated_filters_iteration[0]}/{$wfacpkirki_deprecated_filters_iteration[1]}", $args );
			}, 99 );
			if ( false !== strpos( $wfacpkirki_deprecated_filters_iteration[1], '-' ) ) {
				$wfacpkirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfacpkirki_deprecated_filters_iteration[1] );
				add_filter( "wfacpkirki_{$config_id}_{$filter_suffix}", function( $args ) {
					global $wfacpkirki_deprecated_filters_iteration;
					$wfacpkirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfacpkirki_deprecated_filters_iteration[1] );
					return apply_filters( "wfacpkirki/{$wfacpkirki_deprecated_filters_iteration[0]}/{$wfacpkirki_deprecated_filters_iteration[1]}", $args );
				}, 99 );
			}
		}
	}
}, 99 );

add_filter( 'wfacpkirki_enqueue_google_fonts', function( $args ) {
	return apply_filters( 'wfacpkirki/enqueue_google_fonts', $args );
}, 99 );

add_filter( 'wfacpkirki_styles_array', function( $args ) {
	return apply_filters( 'wfacpkirki/styles_array', $args );
}, 99 );

add_filter( 'wfacpkirki_dynamic_css_method', function( $args ) {
	return apply_filters( 'wfacpkirki/dynamic_css/method', $args );
}, 99 );

add_filter( 'wfacpkirki_postmessage_script', function( $args ) {
	return apply_filters( 'wfacpkirki/postmessage/script', $args );
}, 99 );

add_filter( 'wfacpkirki_fonts_all', function( $args ) {
	return apply_filters( 'wfacpkirki/fonts/all', $args );
}, 99 );

add_filter( 'wfacpkirki_fonts_standard_fonts', function( $args ) {
	return apply_filters( 'wfacpkirki/fonts/standard_fonts', $args );
}, 99 );

add_filter( 'wfacpkirki_fonts_backup_fonts', function( $args ) {
	return apply_filters( 'wfacpkirki/fonts/backup_fonts', $args );
}, 99 );

add_filter( 'wfacpkirki_fonts_google_fonts', function( $args ) {
	return apply_filters( 'wfacpkirki/fonts/google_fonts', $args );
}, 99 );

add_filter( 'wfacpkirki_googlefonts_load_method', function( $args ) {
	return apply_filters( 'wfacpkirki/googlefonts_load_method', $args );
}, 99 );
