<?php
/**
 * Plugin Name: FunnelKit Funnel Builder Pro
 * Plugin URI: https://funnelkit.com/wordpress-funnel-builder/
 * Description: Extend Funnel Builder with One Click Upsells, Order Bumps, Optin Modals, In-depth Funnel Reporting and much more!
 * Version: 3.12.1
 * Author: FunnelKit
 * Author URI: https://funnelkit.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /languages/
 * Requires Plugins: funnel-builder
 *
 * Requires at least: 4.9.0
 * Tested up to: 6.8.2
 * WooFunnels: true
 *
 * FunnelKit Funnel Builder Pro is free software.
 * You can redistribute it and/or modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * FunnelKit Funnel Builder Pro is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Funnel Builder Pro. If not, see <http://www.gnu.org/licenses/>.
 */


/**
 * Defining necessary constants
 */
define( 'WFFN_PRO_FILE', __FILE__ );
define( 'WFFN_PRO_BUILD_VERSION', '3.12.1' );


/**
 * Making sure to flush permalink on activation so that all posts works fine
 */
add_action( 'activated_plugin', 'wfn_pro_maybe_flush_permalink' );
register_activation_hook( __FILE__, 'wfn_pro_maybe_flush_permalink' );

function wfn_pro_maybe_flush_permalink() {
	update_option( 'bwf_needs_rewrite', 'yes', true );
}

/**
 * Delete wizard transient on pro activation so avoid after fill wizard redirection again
 */
add_action( 'activated_plugin', 'wfn_pro_delete_wizard_transient', 10, 1 );
function wfn_pro_delete_wizard_transient( $plugin ) {
	if ( 'funnel-builder-pro/funnel-builder-pro.php' === $plugin ) {
		delete_transient( 'wffn_wizard_steps' );
	}
}

try {

	require_once __DIR__ . '/modules/funnel-builder-powerpack/funnel-builder-powerpack.php';
} catch ( Exception|Error $e ) {

	if ( strpos( $e->getMessage(), 'Failed opening required' ) !== false ) {
		wffn_pro_show_missing_file_notice();
	}
}



/**
 * once all modules files included, loading full modules
 */
add_action( 'wffn_pro_modules_loaded', function () {
	$modules = apply_filters( 'wffn_pro_modules', array(
		'one-click-upsells'           => 'one-click-upsells/woofunnels-upstroke-one-click-upsell.php',
		'checkout'                    => 'checkout/woofunnels-aero-checkout.php',
		'order-bumps'                 => 'order-bumps/woofunnels-order-bump.php',
		'one-click-upsells-powerpack' => 'one-click-upsells-powerpack/woofunnels-upstroke-power-pack.php',
		'woofunnels-ab-tests'         => 'woofunnels-ab-tests/woofunnels-ab-tests.php'
	) );


	try {
		if ( WFFN_Pro_Core()->is_dependency_exists ) {
			foreach ( $modules as $module ) {
				WFFN_Pro_Modules::maybe_load( $module );
			}
		}
	} catch ( Exception|Error $e ) {

		if ( strpos( $e->getMessage(), 'Failed opening required' ) !== false ) {
			wffn_pro_show_missing_file_notice();
		}
	}
} );

function wffn_pro_show_missing_file_notice() {
	add_action( 'admin_notices', function () {
		?>
        <div class="notice notice-error">
            <h3><?php echo esc_html__( 'Error: Missing Files Detected', 'funnel-builder-pro' ); ?></h3>
            <p><?php
				printf( wp_kses( /* translators: %s is a placeholder for the account link */ __( 'It appears that some critical PHP files for the Funnel Builder Pro plugin are missing. Please re-install the plugin from your <a target="_blank" href="%s">account</a> to restore full functionality.', 'funnel-builder-pro' ), array( 'a' => array( 'href' => array() ) ) // Allow only the 'a' tag with 'href' attribute
				), esc_url( 'https://myaccount.funnelkit.com' ) );
				?></p>
        </div>
		<?php
	} );


}


/**
 * add funnelkit cart pro functionality
 */
add_action( 'plugins_loaded', function () {
	if ( defined( 'FKCART_VERSION' ) ) {
		require_once __DIR__ . '/modules/cart-for-woocommerce-pro/plugin.php';
	}
}, 1 );
