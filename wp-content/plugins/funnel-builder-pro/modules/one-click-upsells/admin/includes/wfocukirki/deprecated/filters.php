<?php
// @codingStandardsIgnoreFile

add_filter( 'wfocukirki_config', function( $args ) {
	return apply_filters( 'wfocukirki/config', $args );
}, 99 );

add_filter( 'wfocukirki_control_types', function( $args ) {
	return apply_filters( 'wfocukirki/control_types', $args );
}, 99 );

add_filter( 'wfocukirki_section_types', function( $args ) {
	return apply_filters( 'wfocukirki/section_types', $args );
}, 99 );

add_filter( 'wfocukirki_section_types_exclude', function( $args ) {
	return apply_filters( 'wfocukirki/section_types/exclude', $args );
}, 99 );

add_filter( 'wfocukirki_control_types_exclude', function( $args ) {
	return apply_filters( 'wfocukirki/control_types/exclude', $args );
}, 99 );

add_filter( 'wfocukirki_controls', function( $args ) {
	return apply_filters( 'wfocukirki/controls', $args );
}, 99 );

add_filter( 'wfocukirki_fields', function( $args ) {
	return apply_filters( 'wfocukirki/fields', $args );
}, 99 );

add_filter( 'wfocukirki_modules', function( $args ) {
	return apply_filters( 'wfocukirki/modules', $args );
}, 99 );

add_filter( 'wfocukirki_panel_types', function( $args ) {
	return apply_filters( 'wfocukirki/panel_types', $args );
}, 99 );

add_filter( 'wfocukirki_setting_types', function( $args ) {
	return apply_filters( 'wfocukirki/setting_types', $args );
}, 99 );

add_filter( 'wfocukirki_variable', function( $args ) {
	return apply_filters( 'wfocukirki/variable', $args );
}, 99 );

add_filter( 'wfocukirki_values_get_value', function( $arg1, $arg2 ) {
	return apply_filters( 'wfocukirki/values/get_value', $arg1, $arg2 );
}, 99, 2 );

add_action( 'init', function() {
	$config_ids = WFOCUKirki_Config::get_config_ids();
	global $wfocukirki_deprecated_filters_iteration;
	foreach ( $config_ids as $config_id ) {
		foreach( array(
			'/dynamic_css',
			'/output/control-classnames',
			'/css/skip_hidden',
			'/styles',
			'/output/property-classnames',
			'/webfonts/skip_hidden',
		) as $filter_suffix ) {
			$wfocukirki_deprecated_filters_iteration = array( $config_id, $filter_suffix );
			add_filter( "wfocukirki_{$config_id}_{$filter_suffix}", function( $args ) {
				global $wfocukirki_deprecated_filters_iteration;
				$wfocukirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfocukirki_deprecated_filters_iteration[1] );
				return apply_filters( "wfocukirki/{$wfocukirki_deprecated_filters_iteration[0]}/{$wfocukirki_deprecated_filters_iteration[1]}", $args );
			}, 99 );
			if ( false !== strpos( $wfocukirki_deprecated_filters_iteration[1], '-' ) ) {
				$wfocukirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfocukirki_deprecated_filters_iteration[1] );
				add_filter( "wfocukirki_{$config_id}_{$filter_suffix}", function( $args ) {
					global $wfocukirki_deprecated_filters_iteration;
					$wfocukirki_deprecated_filters_iteration[1] = str_replace( '-', '_', $wfocukirki_deprecated_filters_iteration[1] );
					return apply_filters( "wfocukirki/{$wfocukirki_deprecated_filters_iteration[0]}/{$wfocukirki_deprecated_filters_iteration[1]}", $args );
				}, 99 );
			}
		}
	}
}, 99 );

add_filter( 'wfocukirki_enqueue_google_fonts', function( $args ) {
	return apply_filters( 'wfocukirki/enqueue_google_fonts', $args );
}, 99 );

add_filter( 'wfocukirki_styles_array', function( $args ) {
	return apply_filters( 'wfocukirki/styles_array', $args );
}, 99 );

add_filter( 'wfocukirki_dynamic_css_method', function( $args ) {
	return apply_filters( 'wfocukirki/dynamic_css/method', $args );
}, 99 );

add_filter( 'wfocukirki_postmessage_script', function( $args ) {
	return apply_filters( 'wfocukirki/postmessage/script', $args );
}, 99 );

add_filter( 'wfocukirki_fonts_all', function( $args ) {
	return apply_filters( 'wfocukirki/fonts/all', $args );
}, 99 );

add_filter( 'wfocukirki_fonts_standard_fonts', function( $args ) {
	return apply_filters( 'wfocukirki/fonts/standard_fonts', $args );
}, 99 );

add_filter( 'wfocukirki_fonts_backup_fonts', function( $args ) {
	return apply_filters( 'wfocukirki/fonts/backup_fonts', $args );
}, 99 );

add_filter( 'wfocukirki_fonts_google_fonts', function( $args ) {
	return apply_filters( 'wfocukirki/fonts/google_fonts', $args );
}, 99 );

add_filter( 'wfocukirki_googlefonts_load_method', function( $args ) {
	return apply_filters( 'wfocukirki/googlefonts_load_method', $args );
}, 99 );
