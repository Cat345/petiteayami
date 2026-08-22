<?php
/**
 * License module.
 *
 * The live glue between the license classes (this folder) and the WooFunnels
 * core hooks: daily license check, update-transient clearing, stale cron
 * cleanup, plugins-screen unlicensed notice and the pro_status settings data.
 * Relocated from the free plugin's core (WP.org review) — PowerPack is the
 * only place the license system loads from.
 *
 * Legacy-dashboard-only pieces (licenses tab data provider, licenses-page
 * early init) live separately in the free plugin's woofunnels/legacy/ folder,
 * which is excluded from the .org build.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BWF_LICENSE_MODULE' ) ) {
	return;
}
define( 'BWF_LICENSE_MODULE', true );

/**
 * Daily license check.
 */
add_action( 'fk_fb_every_day', function () {
	if ( class_exists( 'WooFunnels_License_Controller' ) ) {
		WooFunnels_License_Controller::license_check();
	}
} );

/**
 * Clear plugin update transients when a license changes.
 * `funnelkit_license_update` is fired only by WooFunnels_License_Check.
 */
add_action( 'funnelkit_license_update', function () {
	if ( class_exists( 'WooFunnels_Process' ) ) {
		WooFunnels_Process::get_instance()->maybe_clear_plugin_update_transients();
	}
} );

/**
 * Clear the stale legacy license cron schedule.
 */
add_action( 'admin_init', function () {
	if ( wp_next_scheduled( 'woofunnels_license_check' ) ) {
		wp_clear_scheduled_hook( 'woofunnels_license_check' );
	}
} );

if ( ! function_exists( 'bwf_legacy_add_notice_unlicensed_product' ) ) {
	/**
	 * Register the "activate your license" notice on the plugins screen for
	 * every licensed product whose license is invalid.
	 * Called by the WooFunnels_Dashboard::add_notice_unlicensed_product() stub
	 * in the core (the function name is that stub's contract).
	 */
	function bwf_legacy_add_notice_unlicensed_product() {
		if ( ! class_exists( 'WooFunnels_licenses' ) ) {
			return;
		}

		/**
		 * Getting necessary data
		 */
		$licenses = WooFunnels_licenses::get_instance()->get_data();

		/**
		 * Looping over to check how many licenses are invalid and pushing notification and error accordingly
		 */
		if ( $licenses && count( $licenses ) > 0 ) {
			foreach ( $licenses as $key => $license ) {
				if ( $license['product_status'] === 'invalid' ) {
					add_action( 'in_plugin_update_message-' . $key, array( 'WooFunnels_Dashboard', 'need_license_message' ), 10, 2 );
				}
			}
		}
	}
}

/**
 * The `bwf_general_settings_pro_status` filter is served by
 * WFFN_License_Admin_UI::pro_status(), which derives it from the same populated
 * licence rows the settings screen renders -- see includes/class-wffn-license-admin-ui.php.
 */
