<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Funnel Builder-integral framework bootstrap (subcore/).
 *
 * These classes are NOT part of the shared, arbitrated WooFunnels core -- Funnel
 * Builder owns them -- but they still boot through the core kernel like any other
 * framework class. This whole folder lives at the FB plugin root (subcore/), OUTSIDE
 * the woofunnels/ submodule: nothing plugin-specific remains in the shared core, and
 * no other FunnelKit plugin references it. Funnel Builder's own bootstrap
 * (funnel-builder.php) requires this file; here we register these classes into the
 * kernel's load lists via the generic bwf_kernel_* filters, and the actual class
 * loading happens here, off those filters, rather than from the shared core. Every path
 * below is __DIR__-relative, so the folder can sit anywhere FB ships it.
 *
 * Two kinds of class live here:
 *
 * 1. On-demand classes that used to sit in the shared kernel classmap and are FB-owned,
 *    never referenced by CRM or Cart (BWF_Admin_Breadcrumbs, BWF_Admin_General_Settings,
 *    BWF_Data_Tags, BWF_Facebook_Sdk_Factory, BWF_Facebook_Sdk). They resolve through the
 *    subcore autoloader below -- registered at file-load, so it wins for these names
 *    whenever Funnel Builder is active, regardless of which core copy won arbitration.
 *
 * 2. Eager / static-init classes (BWF_Ecomm_Tracking_Common, WooFunnels_Deactivate) that
 *    the core kernel boots via the bwf_kernel_* filters. Those callbacks only fire when
 *    FB's own dismantled core is the copy that loaded; an un-dismantled full core carries
 *    those classes itself, so subcore/ is neither loaded nor needed there.
 */

/**
 * Autoloader for the FB-owned on-demand framework classes moved out of the shared
 * kernel classmap. Paths are subcore-local (no dependency on WooFunnel_Loader::$ultimate_path),
 * so they resolve the same no matter which core set that seam.
 */
spl_autoload_register(
	static function ( $class ) {
		$map = array(
			'bwf_admin_breadcrumbs'      => 'class-bwf-admin-breadcrumbs.php',
			'bwf_admin_general_settings' => 'class-bwf-admin-general-settings.php',
			'bwf_data_tags'              => 'class-bwf-data-tags.php',
			'bwf_facebook_sdk_factory'   => 'class-bwf-facebook-sdk-factory.php',
			'bwf_facebook_sdk'           => 'class-bwf-facebook-sdk.php',
		);
		$key = strtolower( $class );
		if ( ! isset( $map[ $key ] ) ) {
			return;
		}
		$file = __DIR__ . '/' . $map[ $key ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * wf_get_cookie / wf_get_url_parameter shortcodes (BWF_Data_Tags). Moved here from the
 * shared kernel's wire_lazy_classes(): the data-tag feature is FB's, so its wiring and
 * its class travel together. Handlers resolve BWF_Data_Tags lazily (via the autoloader
 * above) only when a tag actually renders.
 */
foreach ( array( 'get_cookie', 'get_url_parameter' ) as $wf_data_tag_code ) {
	add_shortcode(
		'wf_' . $wf_data_tag_code,
		static function ( $attr = array() ) use ( $wf_data_tag_code ) {
			/** Same escaping as the real handler: these are cookie / query values. */
			return esc_html( (string) BWF_Data_Tags::get_instance()->{$wf_data_tag_code}( $attr ) );
		}
	);
}

/**
 * Conversion / ecomm tracking. Kept behind class_exists( 'WFFN_Core' ) -- it is
 * inert without Funnel Builder -- matching the pre-dismantle eager-load gate.
 */
add_filter(
	'bwf_kernel_eager_classes',
	static function ( $classes ) {
		if ( class_exists( 'WFFN_Core' ) && ! in_array( 'BWF_Ecomm_Tracking_Common', $classes, true ) ) {
			require_once __DIR__ . '/class-bwf-ecomm-tracking-common.php';
			if ( class_exists( 'BWF_Ecomm_Tracking_Common' ) ) {
				$classes[] = 'BWF_Ecomm_Tracking_Common';
			}
		}

		return $classes;
	}
);

/**
 * FB-owned deactivation survey. Admin-only (its hooks are admin_footer / admin_init /
 * wp_ajax), and skipped if some other loaded core already supplies the class -- so a
 * mixed site never shows two survey modals.
 */
add_filter(
	'bwf_kernel_static_init_classes',
	static function ( $classes ) {
		if ( is_admin() && ! class_exists( 'WooFunnels_Deactivate' ) ) {
			require_once __DIR__ . '/class-woofunnels-deactivate.php';
			if ( class_exists( 'WooFunnels_Deactivate' ) ) {
				$classes[] = 'WooFunnels_Deactivate';
			}
		}

		return $classes;
	}
);
