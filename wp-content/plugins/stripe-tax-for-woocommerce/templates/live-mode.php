<?php
/**
 * Template file for Stripe Tax settings page.
 *
 * @package Stripe\StripeTaxForWooCommerce\templates
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_Filesystem_Base' ) ) {
	require ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-wp-filesystem-base.php';
}

if ( ! class_exists( '\WP_Filesystem_Direct' ) ) {
	require ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-wp-filesystem-direct.php';
}

use Stripe\StripeTaxForWooCommerce\SDK\lib\Stripe;
use Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxPluginHelper;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxCodeList;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxSettings;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxHealthCheck;
use Stripe\StripeTaxForWooCommerce\WooCommerce\Connect;
use Stripe\StripeTaxForWooCommerce\WooCommerce\ErrorRenderer;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use Stripe\StripeTaxForWooCommerce\WordPress\TaxRegistrationsListTable;

$stfwc_key                       = (string) Options::get_current_mode_key();
$stfwc_live_key                  = Options::get_live_mode_key();
$stfwc_test_key                  = Options::get_test_mode_key();
$stfwc_mode_type                 = Options::get_mode_type();
$stfwc_masked_live_key           = Options::get_live_mode_masked_key();
$stfwc_live_mode_enabled         = Options::is_live_mode_enabled();
$stfwc_skip_cart_tax_calculation = Options::is_skip_cart_tax_calculation();
$stfwc_wc_tax_enabled            = wc_tax_enabled();
$stfwc_filesystem_direct         = new \WP_Filesystem_Direct( array() );

if ( $stfwc_live_mode_enabled ) {
	$notice_message = __( 'By checking the checkbox below, you have enabled the Stripe Tax plugin for tax calculation and collection. This means that the tax rates applicable to the customer orders are now provided by Stripe. It also means that the tax rates specified in the WooCommerce tax classes in the Tax tab are ignored.', 'stripe-tax-for-woocommerce' );
} else {
	$notice_message = __( 'If you enable the Stripe Tax calculation and collection, the tax rates specified in WooCommerce tax classes in the Tax tab will be ignored.', 'stripe-tax-for-woocommerce' );
}

$stripe_tax_api_keys_message = sprintf(
	/* translators: %s: URL to Stripe Dashboard API keys page */
	__( 'You can generate your API key from the <a href="%s" target="_blank" rel="noopener" class="js-target-blank">Woo Commerce Tax Stripe App</a>', 'stripe-tax-for-woocommerce' ),
	esc_url( 'https://marketplace.stripe.com/apps/install/link/com.stripe.woocommerce-tax' )
);

$allowed = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
);


ErrorRenderer::set_error_object( 'notice_message', $notice_message, 'info' );
ErrorRenderer::set_error_object( 'stripe_tax_include_in_price_tab', 'You can select if your set prices are inclusive or exclusive of tax in the "<a href="' . admin_url( 'admin.php?page=wc-settings&tab=tax' ) . '">Tax</a>" settings tab above.', 'info' );
ErrorRenderer::set_error_object( 'stripe_tax_registration_tab', 'Taxes will only be applied when registrations are added in the relevant jurisdictions.', 'info' );
ErrorRenderer::set_error_object( 'stripe_tax_api_keys_message', wp_kses( $stripe_tax_api_keys_message, $allowed ), 'info' );

$stfwc_has_shipping_zones = false;
if ( class_exists( '\\WC_Shipping_Zones' ) ) {
	$stfwc_has_shipping_zones = ! empty( \WC_Shipping_Zones::get_zones() );
}

$stfwc_stripe_gateway_plugin_file      = 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';
$stfwc_stripe_gateway_plugin_installed = defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . $stfwc_stripe_gateway_plugin_file );
$stfwc_stripe_gateway_plugin_active    = false;

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( function_exists( 'is_plugin_active' ) ) {
	$stfwc_stripe_gateway_plugin_active = is_plugin_active( $stfwc_stripe_gateway_plugin_file );
}

if ( $stfwc_live_mode_enabled && ! $stfwc_has_shipping_zones && $stfwc_stripe_gateway_plugin_installed && $stfwc_stripe_gateway_plugin_active ) {
	ErrorRenderer::set_error_object(
		'stripe_tax_shipping_zone_required',
		'No shipping zones are configured. To avoid unexpected tax behavior for Express Checkout, go to <a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . '">Shipping zones</a> and add at least one zone.',
		'warning'
	);
}

// Init with default values, if settings are not able to receive correctly.
$html_list = StripeTaxPluginHelper::get_admin_allowed_html();

$stfwc_tip_mode_live              = __( 'Live mode uses production API keys. Tax transactions will be recorded in Stripe. Use Live mode for production.', 'stripe-tax-for-woocommerce' );
$stfwc_tip_mode_sandbox           = __( 'Sandbox mode uses test API keys and does not create real tax transactions. Use Sandbox mode for testing.', 'stripe-tax-for-woocommerce' );
$stfwc_tip_secret_key             = __( 'Insert your restricted API key from the Stripe Dashboard. Generate one from the WooCommerce Tax Stripe App.', 'stripe-tax-for-woocommerce' );
$stfwc_mode_label_live            = __( 'Currently in Live mode. Toggle to switch to Sandbox', 'stripe-tax-for-woocommerce' );
$stfwc_mode_label_test            = __( 'Currently in Sandbox mode. Toggle to switch to Live', 'stripe-tax-for-woocommerce' );
$stfwc_mode_unsaved_label_live    = __( 'Save changes to switch to Live mode.', 'stripe-tax-for-woocommerce' );
$stfwc_mode_unsaved_label_sandbox = __( 'Save changes to switch to Sandbox mode.', 'stripe-tax-for-woocommerce' );
$stfwc_mode_label                 = Options::MODE_TEST === $stfwc_mode_type ? $stfwc_mode_label_test : $stfwc_mode_label_live;
?>
	<div class="stripe_tax_for_woocommerce_step">
		<h2><?php echo esc_html( __( 'Stripe Tax', 'stripe-tax-for-woocommerce' ) ); ?></h2>
		<div>
			<p>
				<?php /* translators: 1. URL link. */ ?>
				<?php printf( esc_html( __( 'Version %s', 'stripe-tax-for-woocommerce' ) ), esc_html( Stripe::getAppInfo()['version'] ) ); ?>
			</p>
		</div>
		<div>
			<p>
				<?php echo esc_html( __( 'The Stripe Tax plugin allows for easy tax calculations and reporting in your shop. Enable the plugin to start automatically calculating sales tax.', 'stripe-tax-for-woocommerce' ) ); ?>
				<br>
				<?php /* translators: 1. URL link. */ ?>
				<?php printf( esc_html( __( 'Review the %s docs for troubleshooting tips and more information about how to configure this connector.', 'stripe-tax-for-woocommerce' ) ), '<a href="https://docs.stripe.com/connectors/woocommerce/configuration" target="_blank">Stripe Tax for WooCommerce</a>' ); ?>
				<br>
				<?php /* translators: 1. URL link. */ ?>
				<?php printf( esc_html( __( 'To set up automatic filing with a Stripe integrated partner, review the %s doc.', 'stripe-tax-for-woocommerce' ) ), '<a href="https://docs.stripe.com/tax/filing#filing-with-stripe" target="_blank">Filing with Stripe</a>' ); ?>
			</p>
		</div>
	</div>

	<?php echo wp_kses( ErrorRenderer::get_rendered_error( 'stripe_tax_shipping_zone_required', true ), $html_list ); ?>

	<?php
	// Display diagnostics banner whenever health checks detect issues.
	$health_report = TaxHealthCheck::run( true );
	if ( ! $health_report['is_healthy'] && isset( $health_report['status'] ) && 'connected_with_issues' === $health_report['status'] ) {
		// Show the simple banner.
		include STRIPE_TAX_FOR_WOOCOMMERCE_TEMPLATES_DIR . 'tax-diagnostics-notice.php';
	}

	echo '<div id="stripe_tax_for_woocommerce_diagnostics_details_container">';
	if ( ! $health_report['is_healthy'] && isset( $health_report['status'] ) && 'disconnected' === $health_report['status'] && ! TaxHealthCheck::has_only_missing_restricted_key_issue( $health_report ) ) {
		include STRIPE_TAX_FOR_WOOCOMMERCE_TEMPLATES_DIR . 'tax-diagnostics-details.php';
	}
	// Fallback for non-JS behavior.
	$stfwc_run_diagnostics_nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
	if ( '' !== $stfwc_run_diagnostics_nonce && wp_verify_nonce( $stfwc_run_diagnostics_nonce, 'stripe_tax_run_diagnostics' ) && ! TaxHealthCheck::has_only_missing_restricted_key_issue( $health_report ) ) {
		include STRIPE_TAX_FOR_WOOCOMMERCE_TEMPLATES_DIR . 'tax-diagnostics-details.php';
	}
	echo '</div>';
	?>

	<div id="stripe_tax_for_woocommerce_unsaved_changes_notice" class="notice notice-warning inline stripe_tax_for_woocommerce_unsaved_notice">
		<p><?php echo wp_kses( __( 'You have unsaved changes. Click "Save changes" at the bottom of the page to apply them.', 'stripe-tax-for-woocommerce' ), $html_list ); ?></p>
	</div>

	<div class="stripe_tax_for_woocommerce_step stripe_tax_for_woocommerce_step_1">

		<h2><?php echo wp_kses( __( 'Step 1. Connect Stripe account', 'stripe-tax-for-woocommerce' ), $html_list ); ?></h2>
		<?php echo wp_kses( ErrorRenderer::get_rendered_error( 'notice_message' ), $html_list ); ?>
		<table class="form-table stripe_tax_for_woocommerce_settings">
			<tbody>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label>
						<?php echo wp_kses( __( 'Enable tax collection', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
					</label>
				</th>
				<td class="forminp forminp-checkbox">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses( __( 'Enable Stripe Tax. When checked, tax will automatically be calculated and collected on items in your shop. ', 'stripe-tax-for-woocommerce' ), $html_list ); ?><?php echo esc_textarea( __( 'Use live mode Stripe API key for Tax Calculations.', 'stripe-tax-for-woocommerce' ) ); ?></span>
						</legend>
						<label for="stripe_tax_for_woocommerce_id_enable_live_mode">
							<input autocomplete="off" type="hidden" name="stripe_tax_for_woocommerce_enable_live_mode"
									value="0">
							<input autocomplete="off" name="stripe_tax_for_woocommerce_enable_live_mode"
									id="stripe_tax_for_woocommerce_id_enable_live_mode" type="checkbox" class=""
									value="1"<?php checked( $stfwc_live_mode_enabled && $stfwc_wc_tax_enabled ); ?>
							>
							<?php echo wp_kses( __( 'Enable Stripe Tax', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						</label>
						<p class="description"><?php echo wp_kses( __( 'When checked, tax will automatically be calculated and collected on items in your shop.', 'stripe-tax-for-woocommerce' ), $html_list ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row"
					class="titledesc"><?php echo wp_kses( __( 'Tax calculation on cart page', 'stripe-tax-for-woocommerce' ), $html_list ); ?></th>
				<td class="forminp forminp-checkbox">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses( __( 'When checked, tax will not be calculated on the cart page, no Stripe Tax API calls will be made on the cart page. Tax calculations will proceed normally on the checkout page.', 'stripe-tax-for-woocommerce' ), $html_list ); ?></span>
						</legend>
						<label for="stripe_tax_for_woocommerce_id_skip_cart_tax_calculation">
							<input autocomplete="off" type="hidden" name="stripe_tax_for_woocommerce_skip_cart_tax_calculation"
									value="0">
							<input autocomplete="off" name="stripe_tax_for_woocommerce_skip_cart_tax_calculation"
									id="stripe_tax_for_woocommerce_id_skip_cart_tax_calculation" type="checkbox" class=""
									value="1"<?php checked( $stfwc_skip_cart_tax_calculation ); ?>
							>
							<?php echo wp_kses( __( 'Disable tax calculation on cart page', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						</label>
						<p class="description"><?php echo wp_kses( __( 'When checked, tax will not be calculated on the cart page and no API calls will be made at cart level. Tax will still be calculated normally at checkout.', 'stripe-tax-for-woocommerce' ), $html_list ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_tax_for_woocommerce_id_toggle_mode">
						Mode
						<span class="woocommerce-help-tip stfwc-help-tip stfwc-help-mode-tip js-mode-tip" data-tip-live="<?php echo esc_attr( $stfwc_tip_mode_live ); ?>" data-tip-sandbox="<?php echo esc_attr( $stfwc_tip_mode_sandbox ); ?>" data-tip="<?php echo esc_attr( Options::MODE_TEST === $stfwc_mode_type ? $stfwc_tip_mode_sandbox : $stfwc_tip_mode_live ); ?>"></span>
					</label>
				</th>
				<td>
					<label class="stripe_tax_for_woocommerce_switch">
						<input id="stripe_tax_for_woocommerce_id_toggle_mode" type="checkbox" name="stripe_tax_for_woocommerce_mode" value="1" aria-label="<?php echo esc_attr( $stfwc_mode_label ); ?>" data-label-live="<?php echo esc_attr( $stfwc_mode_label_live ); ?>" data-label-sandbox="<?php echo esc_attr( $stfwc_mode_label_test ); ?>" data-label-unsaved-live="<?php echo esc_attr( $stfwc_mode_unsaved_label_live ); ?>" data-label-unsaved-sandbox="<?php echo esc_attr( $stfwc_mode_unsaved_label_sandbox ); ?>" <?php checked( 1, $stfwc_mode_type ); ?> />
						<span class="slider round"></span>
						<span class="switch-text js-live-text"><?php echo wp_kses( __( 'Live', 'stripe-tax-for-woocommerce' ), $html_list ); ?></span>
						<span class="switch-text js-sandbox-text"><?php echo wp_kses( __( 'Sandbox', 'stripe-tax-for-woocommerce' ), $html_list ); ?></span>
					</label>
					<p class="description js-mode-current-label"><?php echo esc_html( $stfwc_mode_label ); ?></p>
				</td>
			</tr>
			<tr>
				<th ></th>
				<td>
					<?php echo wp_kses_post( ErrorRenderer::get_rendered_error( 'stripe_tax_api_keys_message', true ) ); ?>
				</td>
			</tr>
		
			<tr valign="top" class="js-stripe_tax_for_woocommerce_test_mode" <?php echo ( Options::MODE_TEST !== $stfwc_mode_type ? 'style="display: none"' : '' ); ?>>
				<th scope="row" class="titledesc"><label
							for="stripe_tax_for_woocommerce_id_test_mode_secret_key"><?php echo wp_kses( __( 'Restricted key (test mode)', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<span class="woocommerce-help-tip stfwc-help-tip" data-tip="<?php echo esc_attr( $stfwc_tip_secret_key ); ?>"></span>
						</label>
				</th>
				<td class="forminp forminp-text">
					<div class="stripe_tax_for_woocommerce_key_field_row">
						<input name="stripe_tax_for_woocommerce_test_mode_secret_key"
								id="stripe_tax_for_woocommerce_id_test_mode_secret_key"
								class="js_stripe_tax_for_woocommerce_test_mode js_stripe_tax_for_woocommerce_sec_key"
								type="password" autocomplete="off"
								value="<?php echo esc_attr( $stfwc_test_key ); ?>"
								placeholder="<?php echo wp_kses( __( 'Restricted key (test mode)', 'stripe-tax-for-woocommerce' ), $html_list ); ?>">
						<input autocomplete="off" type="button" <?php echo ( '' === $stfwc_test_key ? 'disabled="disabled"' : '' ); ?> 
								id="stripe_tax_for_woocommerce_id_btn_test_connection_test_key"
								name="stripe_tax_for_woocommerce_button_test_connection_test_key" 
								class="button action js_stripe_tax_for_woocommerce_button_test_connection"
								data-mode="test"
								value="<?php echo esc_attr( __( 'Test connection', 'stripe-tax-for-woocommerce' ) ); ?>">
					</div>
					<input autocomplete="off" type="hidden"
							name="stripe_tax_for_woocommerce_nonce_test_connection_test_key"
							value="<?php echo esc_attr( wp_create_nonce( 'stripe_tax_for_woocommerce_test_connection_live_key' ) ); ?>">
				</td>
			</tr>

			<tr valign="top" class="js-stripe_tax_for_woocommerce_live_mode" 
			<?php echo ( Options::MODE_TEST === $stfwc_mode_type ? 'style="display: none"' : '' ); ?>>
				<th scope="row" class="titledesc"><label
							for="stripe_tax_for_woocommerce_id_live_mode_secret_key"><?php echo wp_kses( __( 'Restricted key (live mode)', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<span class="woocommerce-help-tip stfwc-help-tip" data-tip="<?php echo esc_attr( $stfwc_tip_secret_key ); ?>"></span>
						</label>
				</th>
				<td class="forminp forminp-text">
					<div class="stripe_tax_for_woocommerce_key_field_row">
						<input name="stripe_tax_for_woocommerce_live_mode_secret_key"
								id="stripe_tax_for_woocommerce_id_live_mode_secret_key"
								class="js_stripe_tax_for_woocommerce_live_mode js_stripe_tax_for_woocommerce_sec_key"
								type="password" autocomplete="off"
								value="<?php echo esc_attr( $stfwc_live_key ); ?>"
								placeholder="<?php echo wp_kses( __( 'Restricted key (live mode)', 'stripe-tax-for-woocommerce' ), $html_list ); ?>">
						<input autocomplete="off" type="button" <?php echo ( '' === $stfwc_live_key ? 'disabled="disabled"' : '' ); ?>
								id="stripe_tax_for_woocommerce_id_btn_test_connection_live_key"
								name="stripe_tax_for_woocommerce_button_test_connection_live_key" 
								class="button action js_stripe_tax_for_woocommerce_button_test_connection"
								data-mode="live"
								value="<?php echo esc_attr( __( 'Test connection', 'stripe-tax-for-woocommerce' ) ); ?>">
					</div>
					<input autocomplete="off" type="hidden"
							name="stripe_tax_for_woocommerce_nonce_test_connection_live_key"
							value="<?php echo esc_attr( wp_create_nonce( 'stripe_tax_for_woocommerce_test_connection_live_key' ) ); ?>">
				</td>
			</tr>

			</tbody>
		</table>

	</div>

<?php
if ( $stfwc_key ) :
	// Init Tax Settings with defaults.
	$settings_tax_code                     = '';
	$settings_tax_non_taxable_fee_tax_code = '';
	$settings_tax_fee_tax_code             = '';
	$settings_tax_shipping_tax_code        = '';
	$tax_code_list                         = ( new TaxCodeList( $stfwc_key ) )->get_as_key_value_formatted();
	try {
		$settings_tax_code         = Options::get_tax_code();
		$settings_tax_fee_tax_code = Options::get_fee_tax_code();
	} catch ( \Throwable $e ) {
		ErrorRenderer::set_error_object( 'get_tax_settings', $e->getMessage(), 'error' );
		echo wp_kses( '<div class="stripe_tax_for_woocommerce_message_span_id_message_wrapper">' . ErrorRenderer::get_rendered_error( 'get_tax_settings' ) . '</div>', $html_list );
	}
	?>
	<?php // @phpstan-ignore-next-line ?>
	<div class="stripe_tax_for_woocommerce_step stripe_tax_for_woocommerce_step_2 <?php echo ( '' !== $stfwc_key ) ? '' : 'disable_stripe_tax_for_woocommerce_step_2'; ?>
" >
		<h2><?php echo wp_kses( __( 'Step 2. Configure your sales tax settings', 'stripe-tax-for-woocommerce' ), $html_list ); ?></h2>

		<h3><?php echo wp_kses( __( 'Product tax category', 'stripe-tax-for-woocommerce' ), $html_list ); ?></h3>
		<table class="form-table stripe_tax_for_woocommerce_settings">
			<tbody>
			<tr valign="top" class="">
				<th scope="row" class="titledesc"><label
							for="stripe_tax_for_woocommerce_id_live_mode_tax_code"><?php echo wp_kses( __( 'Default product tax code', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<span class="required"> *</span></label>
				</th>
				<td class="forminp forminp-select">
				<?php
					Options::stripe_tax_for_woocommerce_output_rendered_select(
						$tax_code_list,
						'stripe_tax_for_woocommerce_live_mode_tax_code',
						$settings_tax_code,
						'stripe_tax_for_woocommerce_id_live_mode_tax_code'
					);
				?>
					<p class="description"><?php echo wp_kses( __( 'Set the default tax code applied to new products. You can override this setting on individual product pages.', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<a href="https://stripe.com/docs/tax/tax-codes"
							target="_blank"><?php echo wp_kses( __( 'Learn more', 'stripe-tax-for-woocommerce' ), $html_list ); ?></a></p>
				</td>
			</tr>

			<tr valign="top" class="">
				<th scope="row" class="titledesc"><label
							for="stripe_tax_for_woocommerce_id_tax_class_taxable_fees"><?php echo wp_kses( __( 'Tax class for taxable fees', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<span class="required"> *</span></label>
				</th>
				<td class="forminp forminp-select">
				<?php
					Options::stripe_tax_for_woocommerce_output_rendered_select(
						$tax_code_list,
						'stripe_tax_for_woocommerce_fee_tax_code',
						$settings_tax_fee_tax_code,
						'stripe_tax_for_woocommerce_id_tax_class_taxable_fees'
					);
				?>
					<p class="description"><?php echo wp_kses( __( 'Define the tax code used to calculate tax on WooCommerce fees that are marked as taxable.', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
						<a href="https://stripe.com/docs/tax/tax-codes"
							target="_blank"><?php echo wp_kses( __( 'Learn more', 'stripe-tax-for-woocommerce' ), $html_list ); ?></a></p>
				</td>
			</tr>

			</tbody>
		</table>
		<?php
		echo wp_kses( ErrorRenderer::get_rendered_error( 'stripe_tax_include_in_price_tab', true ), $html_list );
		echo wp_kses( ErrorRenderer::get_rendered_error( 'setting_tax_behavior_error' ), $html_list );
		?>
	</div>

	<div class="stripe_tax_for_woocommerce_step stripe_tax_for_woocommerce_step_3">
		<h2><?php echo wp_kses( __( 'Step 3. Tax Registrations', 'stripe-tax-for-woocommerce' ), $html_list ); ?></h2>
		<div>
			<p>
				<?php echo wp_kses( __( 'Locations where you have a registration and are currently collecting tax with Stripe. When you add a new address and save it, tax calculation works immediately. When you delete the address, it stops immediately. You can add one or multiple tax registrations.', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
			</p>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %1$s: opening link tag, %2$s: closing link tag */
						__( 'If you need to start collecting tax in a US state, but have not yet registered with the state government, Stripe Tax can register for you. Visit %1$sStripe Docs%2$s to learn more.', 'stripe-tax-for-woocommerce' ),
						'<a href="https://docs.stripe.com/tax/use-stripe-to-register" target="_blank">',
						'</a>'
					),
					$html_list
				);
				?>
			</p>
			<?php echo wp_kses( ErrorRenderer::get_rendered_error( 'stripe_tax_registration_tab' ), $html_list ); ?>
		</div>

		<?php
		global $current_section;

		$stripe_tax_for_woocommerce_key        = Options::get_current_mode_key();
		$stripe_tax_for_woocommerce_mode_label = __( 'Add New', 'stripe-tax-for-woocommerce' );

		try {

			$tax_registrations_list_table = new TaxRegistrationsListTable();
			$tax_registrations_list_table->set_api_key( $stripe_tax_for_woocommerce_key );
			$tax_registrations_list_table->prepare_items();

			// Add "Refresh" button next to the "Add New" to allow admins to clear cached Stripe registrations.
			$add_new_url   = admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&section=' . rawurlencode( $current_section ) . '&add_tax_registration' );
			$add_new_label = $stripe_tax_for_woocommerce_mode_label;
			// Output the heading and actions with proper escaping.
			printf(
				'<h2>%s <a href="%s" class="page-title-action">%s</a> <button type="button" id="stripe_tax_for_woocommerce_force_refresh_btn" class="page-title-action">%s</button><input type="hidden" id="stripe_tax_for_woocommerce_nonce_force_refresh" value="%s">',
				wp_kses( __( 'Tax Registrations', 'stripe-tax-for-woocommerce' ), $html_list ),
				esc_url( $add_new_url ),
				esc_html( $add_new_label ),
				esc_html__( 'Refresh', 'stripe-tax-for-woocommerce' ),
				esc_attr( wp_create_nonce( 'stripe_tax_for_woocommerce_force_refresh_tax_registrations' ) )
			);

			// Render WP_List_Table directly so native controls/markup are preserved.
			$tax_registrations_list_table->display();

		} catch ( \Throwable $e ) {
			$stripe_tax_error_message = 'Error fetching tax registrations: ' . $e->getMessage();
		}
		?>
	</div>

	<div class="stripe_tax_for_woocommerce_step stripe_tax_for_woocommerce_step_4">
		<h2><?php echo wp_kses( __( 'Step 4. Reporting and Filing', 'stripe-tax-for-woocommerce' ), $html_list ); ?><span class="woocommerce-help-tip stfwc-help-tip" data-tip="<?php echo esc_attr( __( 'View tax reports and file returns directly from the Stripe Dashboard or via Stripe Apps.', 'stripe-tax-for-woocommerce' ) ); ?>"></span></h2>
		<div>
			<p>
				<?php echo wp_kses( __( 'Stripe\'s financial reports help you understand and reconcile the activity in your account. You can view summary reports in the', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
				<a href="<?php echo esc_url( StripeTaxPluginHelper::get_report_link() ); ?>" target="_blank"><?php echo wp_kses( __( 'Stripe Dashboard', 'stripe-tax-for-woocommerce' ), $html_list ); ?></a>
				<?php echo wp_kses( __( 'by clicking "View report" in the "Tax report" column.', 'stripe-tax-for-woocommerce' ), $html_list ); ?>
			</p>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %1$s: opening link tag, %2$s: closing link tag */
						__( 'You can use Stripe Apps to file your tax returns automatically. View %1$sStripe Docs%2$s to learn more.', 'stripe-tax-for-woocommerce' ),
						'<a href="https://docs.stripe.com/tax/filing#filing-with-stripe" target="_blank">',
						'</a>'
					),
					$html_list
				);
				?>
			</p>
		</div>
	</div>
	<?php

endif;
?>
