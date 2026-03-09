<?php
/**
 * Install WordPress Hooks.
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

namespace Stripe\StripeTaxForWooCommerce\WordPress;

// Exit if script started not from WordPress.
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\NumberUtil;
use Exception;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\ApiErrorException;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Service\AccountService;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Stripe;
use Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient;
use Stripe\StripeTaxForWooCommerce\Stripe\CalculateTax;
use Stripe\StripeTaxForWooCommerce\Stripe\Exception\CountryStateException;
use Stripe\StripeTaxForWooCommerce\Stripe\Exception\CountrySupportException;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeCalculationTracker;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxPluginHelper;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxCodeList;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxExemptions;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxRegistrations;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxSettings;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxTransaction;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxTransactionReversal;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxLogger;
use Stripe\StripeTaxForWooCommerce\WooCommerce\Connect;
use Stripe\StripeTaxForWooCommerce\WooCommerce\ErrorRenderer;
use Stripe\StripeTaxForWooCommerce\WooCommerce\ExtendedProduct;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeOrderItemTax;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTax;
use WC_Data;
use WP_CLI;
use WP_REST_Request;
use WP_REST_Response;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateMemRepo;
use Stripe\StripeTaxForWooCommerce\WordPress\StringTaxRateIdFixerScheduledAction;
use Stripe\StripeTaxForWooCommerce\WordPress\StripeTax_Plugin;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Data;

/**
 * Class for adding WordPress actions, filter, registering styles and scripts
 */
class Hooks {

	/**
	 * Contains WP Hooks initialization state.
	 *
	 * @var bool $hooks_initialized
	 */
	protected static $hooks_initialized;

	/**
	 * Tax exemptions object.
	 *
	 * @var TaxExemptions|null $tax_exemptions
	 */
	protected static $tax_exemptions;

	/**
	 * Map line item type name to line item group name.
	 *
	 * @var array $type_to_group
	 */
	protected static $type_to_group = array(
		'line_item' => 'line_items',
		'tax'       => 'tax_lines',
		'shipping'  => 'shipping_lines',
		'fee'       => 'fee_lines',
		'coupon'    => 'coupon_lines',
	);

	/**
	 * Tax transaction object.
	 *
	 * @var TaxTransaction|null
	 */
	protected static $tax_transaction;

	/**
	 * If true then the errors are collected and added to the REST response if $error_reporting_collect_enabled is true.
	 *
	 * @var bool $error_reporting_collect
	 */
	protected static $error_reporting_collect = false;

	/**
	 * If true then the collected errors are added to the REST response.
	 *
	 * @var bool $error_reporting_collect
	 */
	protected static $error_reporting_collect_enabled = false;

	/**
	 * Create and return tax transaction object.
	 *
	 * @return TaxTransaction Returns the tax transaction object.
	 */
	protected static function get_tax_transaction() {
		if ( is_null( static::$tax_transaction ) ) {
			// @phpstan-ignore-next-line
			static::$tax_transaction = new TaxTransaction( Options::get_current_mode_key() );
		}
		return static::$tax_transaction;
	}

	/**
	 * Register plugin's CSS styles.
	 */
	public static function action_admin_enqueue_styles() {
		wp_enqueue_style(
			'stripe_tax_for_woocommerce_admin',
			STRIPE_TAX_FOR_WOOCOMMERCE_ASSETS_CSS_URL . 'stripe_tax_for_woocommerce_admin.css',
			array(),
			filemtime( STRIPE_TAX_FOR_WOOCOMMERCE_ASSETS_CSS_DIR . 'stripe_tax_for_woocommerce_admin.css' )
		);
	}

	/**
	 * Register JS and CSS files required for the admin panel.
	 */
	protected static function admin_enqueue_styles() {
		add_action( 'admin_enqueue_scripts', array( static::class, 'action_admin_enqueue_styles' ), 10, 0 );
	}

	/**
	 * Register JS files required for WP Admin(backend page) and add settings names localizations.
	 */
	public static function action_admin_enqueue_scripts() {

		wp_enqueue_script(
			'stripe_tax_for_woocommerce_admin',
			STRIPE_TAX_FOR_WOOCOMMERCE_ASSETS_JS_URL . 'stripe_tax_for_woocommerce_admin.js',
			array( 'jquery' ),
			filemtime( STRIPE_TAX_FOR_WOOCOMMERCE_ASSETS_JS_DIR . 'stripe_tax_for_woocommerce_admin.js' ),
			true
		);

		global $current_section;

		$localize_script = array(
			'ajax_url'                                    => admin_url( 'admin-ajax.php' ),
			'current_section_url'                         => admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&section=' . $current_section ),
			'city_label'                                  => __( 'City', 'stripe-tax-for-woocommerce' ),
			'city_is_district_label'                      => __( 'District', 'stripe-tax-for-woocommerce' ),
			'city_is_district_countries'                  => array_keys( StripeTaxPluginHelper::get_city_is_district_countries() ),
			'city_is_town_or_city_label'                  => __( 'Town or City', 'stripe-tax-for-woocommerce' ),
			'city_is_town_or_city_countries'              => array_keys( StripeTaxPluginHelper::get_city_is_town_or_city_countries() ),
			'postal_code_label'                           => __( 'Postal code', 'stripe-tax-for-woocommerce' ),
			'postal_code_is_eircode_label'                => __( 'Eircode', 'stripe-tax-for-woocommerce' ),
			'postal_code_is_eircode_countries'            => array_keys( StripeTaxPluginHelper::get_postal_code_is_eircode_countries() ),
			'postal_code_is_zip_label'                    => __( 'ZIP', 'stripe-tax-for-woocommerce' ) . StripeTaxPluginHelper::get_required_field_mark_html(),
			'postal_code_is_zip_countries'                => array_keys( StripeTaxPluginHelper::get_postal_code_is_zip_countries() ),
			'postal_code_no_city_countries'               => array_keys( StripeTaxPluginHelper::get_no_city_countries() ),
			'postal_code_no_postal_code_countries'        => array_keys( StripeTaxPluginHelper::get_no_postal_code_countries() ),
			'tax_registrations_lease_and_amusement_tax_use_states' => StripeTaxPluginHelper::get_tax_registration_lease_and_amusement_tax_us_states(),
			'tax_registrations_local_communications_tax_us_states' => StripeTaxPluginHelper::get_tax_registration_local_communications_tax_us_states(),
			'tax_registrations_no_sales_tax_us_states'    => StripeTaxPluginHelper::get_tax_registration_no_sales_tax_us_states(),
			'tax_registrations_eu_countries'              => StripeTaxPluginHelper::get_tax_registration_eu_countries(),
			/* translators: %s: country code, eg. 'US' */
			'tax_registrations_localize_domestic'         => __( 'Domestic (registered in %s)', 'stripe-tax-for-woocommerce' ),
			/* translators: %s: country code, eg. 'US' */
			'tax_registrations_localize_domestic_description' => __( 'Common for businesses selling goods and services to customers in %s.', 'stripe-tax-for-woocommerce' ),
			/* translators: %s: country code, eg. 'US' */
			'tax_registrations_localize_local_communications' => __( '%s State and Local Communications Tax', 'stripe-tax-for-woocommerce' ),
			/* translators: %s: country code, eg. 'US' */
			'tax_registrations_localize_local_communications_description' => __( 'Common for businesses selling video or audio streaming to customers in %s. This includes the Communications Services Tax, Communications Services Gross Receipts Tax and Local Communications Services Tax.', 'stripe-tax-for-woocommerce' ),
			/* translators: %s: country code, eg. 'US' */
			'tax_registrations_localize_no_sales_description' => __( 'You don’t need to add a registration in %s because there’s no sales tax in this state.', 'stripe-tax-for-woocommerce' ),
			'tax_ids_html'                                => CalculateTax::get_tax_ids_admin_html(),
			'disconnect_from_stripe_message_confirmation' => __( 'Are you sure you want to disconnect Stripe Tax plugin from Stripe Account?', 'stripe-tax-for-woocommerce' ),
		);

		if ( self::is_stripe_tab_selected() ) {
			$api_key = Options::get_current_mode_key();

			try {
				$tax_registrations = new TaxRegistrations( $api_key );
				// $locks array contains prepared for easy check list of already added tax registrations.
				// In this case it used to lock "on-the-fly" checkboxes as checked and disabled by JavaScript.
				$locks                                      = $tax_registrations->get_locks();
				$localize_script['tax_registrations_locks'] = $locks;
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Throwable $e ) {
				$stripe_tax_error_message = 'Error fetching tax registrations: ' . $e->getMessage();
			}
			wp_localize_script( 'stripe_tax_for_woocommerce_admin', 'stripe_tax_for_woocommerce', $localize_script );
		}
	}

	/**
	 * Register WP Admin JS.
	 */
	protected static function admin_enqueue_scripts() {
		add_action( 'admin_enqueue_scripts', array( static::class, 'action_admin_enqueue_scripts' ), 10, 0 );
	}

	/**
	 * Register AJAX requests handlers for connection testing and disconnection functionality.
	 */
	protected static function admin_ajax() {
		add_action(
			'wp_ajax_stripe_tax_for_woocommerce_test_connection',
			array(
				AdminAjax::class,
				'test_connection',
			),
			10,
			0
		);
		add_action(
			'wp_ajax_stripe_tax_for_woocommerce_disconnect_from_stripe',
			array(
				AdminAjax::class,
				'disconnect_from_stripe',
			),
			10,
			0
		);

		add_action(
			'woocommerce_admin_order_items_after_fees',
			array( static::class, 'render_admin_error_message' ),
			10,
			1
		);
	}

	/**
	 * Filter adds Stripe Tax tab into WooCommerce Settings.
	 *
	 * @param array $settings Settings.
	 */
	public static function filter_add_stripe_tax_settings( $settings ) {
		$settings[] = new StripeTax();

		return $settings;
	}

	/**
	 * Saves additional fields for WooCommerce Product on save action.
	 *
	 * @param WC_Data $wc_data WooCommerce data.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public function action_woocommerce_after_product_object_save( WC_Data $wc_data ) {
		$stripe_wc_product = new ExtendedProduct( $wc_data->get_id() );

		$posted_tax_code = $stripe_wc_product->get_on_save_post_parameter_tax_code( $wc_data );

		$stripe_wc_product->save_extended_product(
			array(
				'tax_code' => $posted_tax_code,
			)
		);
	}

	/**
	 *  Admin init action.
	 */
	public static function action_admin_init() {
		// Because there is no "classic" WordPress "nonce" - we disable CodeSniffer "NonceVerification" check.
		// Actual CSRF checks here made by using "wcs_stripe_state" GET parameter and validated in method get_stripe_oauth_keys().
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$wcs_stripe_code  = isset( $_GET['wcs_stripe_code'] ) ? sanitize_text_field( wp_unslash( $_GET['wcs_stripe_code'] ) ) : '';
		$wcs_stripe_state = isset( $_GET['wcs_stripe_state'] ) ? sanitize_text_field( wp_unslash( $_GET['wcs_stripe_state'] ) ) : '';
		// phpcs:enable
		if ( 'stripe_tax_for_woocommerce' === $current_tab ) {
			if ( ! empty( $wcs_stripe_code ) ) {
				try {
					$secret_key = Connect::get_stripe_oauth_keys( $wcs_stripe_code, $wcs_stripe_state );
					Options::update_option( Options::OPTION_LIVE_MODE_SECRET_KEY, $secret_key );
					Connect::set_woocommerce_connect_last_error( __( 'Connect with Stripe successful', 'stripe-tax-for-woocommerce' ) );
				} catch ( \Throwable $e ) {
					Connect::set_woocommerce_connect_last_error( $e->getMessage() );
				}
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce' ) );
				static::do_exit();
			}
		}
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Wrapper around the PHP's built-in die function.
	 *
	 * @return never-return
	 */
	protected static function do_exit() {
		exit;
	}
	// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Adds additional fields on WooCommerce Product page.
	 */
	public static function action_woocommerce_product_options_tax() {
		global $product_object;
		try {
			$tax_settings      = new TaxSettings( Options::get_current_mode_key() );
			$stripe_wc_product = new ExtendedProduct( $product_object->get_id() );
			$default_tax_code  = Options::get_tax_code();
			$tax_codes         = ( new TaxCodeList( Options::get_current_mode_key() ) )->get_as_key_value_formatted();
			woocommerce_wp_select(
				array(
					'id'          => '_stripe_tax_for_woocommerce_tax_code',
					'value'       => isset( $stripe_wc_product->get_extended_product()['tax_code'] ) ? $stripe_wc_product->get_extended_product()['tax_code'] : 'stfwc_inherit',
					'label'       => __( 'Stripe Tax - Product tax code', 'stripe-tax-for-woocommerce' ),
					'options'     => array_merge( array( 'stfwc_inherit' => __( 'Default' ) . ' (' . $default_tax_code . ' - ' . TaxCodeList::format_single( $default_tax_code, Options::get_current_mode_key() ) . ')' ), $tax_codes ),
					'desc_tip'    => 'true',
					'description' => __( 'Choose a stripe tax code for this product.', 'stripe-tax-for-woocommerce' ),
				)
			);
		} catch ( \Throwable $e ) {
			ErrorRenderer::set_error_object( 'product_tax_code_retrieve_error', esc_html( 'Stripe Tax: ' . $e->getMessage() ), 'error' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo esc_html( ErrorRenderer::get_rendered_error( 'product_tax_code_retrieve_error' ) );
		}
	}

	/**
	 * Anything needed to be done on WooCommerce meta box render event.
	 */
	public static function action_add_meta_boxes() {
		add_action(
			'woocommerce_product_options_tax',
			array(
				static::class,
				'action_woocommerce_product_options_tax',
			),
			10,
			0
		);
	}

	/**
	 * Anything needed to be done, when we are on admin page.
	 */
	public static function action_init() {
		add_action( 'add_meta_boxes', array( static::class, 'action_add_meta_boxes' ), 30, 0 );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce' ) . '" aria-label="' . esc_attr__( 'View Stripe Tax settings', 'stripe_tax_for_woocommerce' ) . '">' . esc_html__( 'Settings', 'stripe-tax-for-woocommerce' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Add plugin actions.
	 *
	 * @return void
	 */
	protected function add_actions(): void {

		static::add_action_tax_exemptions( static::$tax_exemptions );
		add_action(
			'woocommerce_after_product_object_save',
			array(
				$this,
				'action_woocommerce_after_product_object_save',
			),
			10,
			1
		);
		add_action( 'init', array( static::class, 'action_init' ), 10, 0 );
		add_action(
			'rest_dispatch_request',
			array(
				static::class,
				'detect_error_reporting_collect',
			),
			10,
			0
		);
		add_action(
			'woocommerce_hydration_dispatch_request',
			array(
				static::class,
				'detect_error_reporting_collect',
			),
			10,
			0
		);
		add_action(
			'woocommerce_hydration_request_after_callbacks',
			array(
				static::class,
				'add_collected_errors_to_response',
			),
			10,
			3
		);
		add_action(
			'rest_request_after_callbacks',
			array(
				static::class,
				'add_collected_errors_to_response',
			),
			10,
			3
		);
		add_action(
			'woocommerce_order_partially_refunded',
			array(
				static::class,
				'action_woocommerce_order_partially_refunded',
			),
			20,
			2
		);
		add_action(
			'woocommerce_order_fully_refunded',
			array(
				static::class,
				'action_woocommerce_order_fully_refunded',
			),
			20,
			1
		);

		add_action( 'admin_notices', array( static::class, 'render_admin_notices' ) );

		add_action(
			'update_option_woocommerce_prices_include_tax',
			function ( $old_value, $value ) {
				if ( 'yes' === $value ) {
					update_option( 'woocommerce_tax_display_shop', 'incl' );
					update_option( 'woocommerce_tax_display_cart', 'incl' );
				} else {
					update_option( 'woocommerce_tax_display_shop', 'excl' );
					update_option( 'woocommerce_tax_display_cart', 'excl' );
				}
			},
			10,
			2
		);
		add_action(
			'admin_footer',
			function () {
				global $pagenow, $post;

				if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type( $post ) ) {
					?>
				<script>
					jQuery(document).ready(function($) {
						$('#_tax_status').closest('p.form-field').remove();
						$('#_tax_class').closest('p.form-field').remove();

						function removeTaxClassFromVariations() {
							$('.form-row.form-row-full[class*="tax_class"]').remove();
						}

						// Initial call (in case variations already visible)
						removeTaxClassFromVariations();

						// Also call when new variation fields are loaded (after clicking "Add variation", etc.)
						$(document).on('woocommerce_variations_loaded woocommerce_variations_added', function() {
							removeTaxClassFromVariations();
						});
					});
				</script>
					<?php
				}
			}
		);
		add_action(
			'admin_footer-edit.php',
			function () {
				global $typenow;

				if ( 'product' === $typenow ) {
					?>
				<script>
					jQuery(document).ready(function($) {
						// Remove the tax class field from quick and bulk edit UI
						$('fieldset.inline-edit-col-right .tax_status').closest('label').remove();
						$('fieldset.inline-edit-col-left .tax_status').closest('label').remove();

						$('fieldset.inline-edit-col-right .tax_class').closest('label').remove();
						$('fieldset.inline-edit-col-left .tax_class').closest('label').remove();
					});
				</script>
					<?php
				}
			}
		);
	}

	/**
	 * Check if current tab is Stripe tab.
	 *
	 * @return bool If is on Stripe tab.
	 */
	private static function is_stripe_tab_selected(): bool {
		// There are no nonce exists or needed, because it is just a regular page view without any changes made by user input.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'stripe_tax_for_woocommerce' === $_GET['tab'];
	}

	/**
	 * Add plugin filters.
	 *
	 * @return void
	 */
	protected static function add_filters(): void {
		add_filter(
			'woocommerce_order_type_to_group',
			array(
				static::class,
				'filter_woocommerce_order_type_to_group',
			),
			100,
			1
		);

		add_filter(
			'woocommerce_get_order_item_classname',
			array(
				static::class,
				'filter_woocommerce_get_order_item_classname',
			),
			5,
			2
		);
		add_filter(
			'woocommerce_cart_hide_zero_taxes',
			'__return_false',
			20,
			0
		);

		// Using this to ignore Woocommerce rates to be displayed in shop.
		add_filter(
			'woocommerce_find_rates',
			array(
				static::class,
				'filter_woocommerce_find_rates',
			),
			10,
			0
		);
		add_filter( 'pre_option_wc_connect_taxes_enabled', fn () => Options::is_live_mode_enabled() );
		add_filter(
			'woocommerce_rest_prepare_shop_order_object',
			array(
				static::class,
				'filter_rest_prepare_shop_order_object',
			),
			10,
			1
		);
		// @phpstan-ignore-next-line
		add_filter(
			'woocommerce_order_item_get_formatted_meta_data',
			function ( $formatted_meta ) {
				$meta_to_hide = array(
					'_stripe_not_subtotal_include_tax',
					'__stripe_tax_item_subtotal_tax',
					'__stripe_tax_price_inclusive_tax',
					'__stripe_tax_behavior',
					Data::CART_ITEM_REFERENCE_META_NAME,
					Data::TAX_EXCLUDED_META_NAME,
				);

				foreach ( $formatted_meta as $key => $meta ) {
					if ( in_array( $meta->key, $meta_to_hide, true ) ) {
						unset( $formatted_meta[ $key ] );
					}
				}

				return $formatted_meta;
			},
			10,
			2
		);

		add_filter(
			'woocommerce_get_sections_tax',
			function ( $classes ) {
				return array( '' => $classes[''] );
			},
			10,
			1
		);

		add_filter(
			'option_woocommerce_tax_based_on',
			array(
				static::class,
				'action_option_woocommerce_tax_based_on',
			),
			10,
			1
		);

		add_filter(
			'option_woocommerce_tax_round_at_subtotal',
			function () {
				return 'no';
			},
			10,
			0
		);

		add_filter(
			'woocommerce_tax_settings',
			function ( $settings ) {
				return array_filter(
					$settings,
					function ( $setting ) {
						if ( array_key_exists( 'id', $setting ) && in_array( $setting['id'], array( 'woocommerce_tax_display_shop', 'woocommerce_tax_display_cart' ), true ) ) {
							return false;
						}

						if ( array_key_exists( 'type', $setting ) && 'conflict_error' === $setting['type'] ) {
							return false;
						}

						return true;
					}
				);
			}
		);
	}

	/**
	 * "option_woocommerce_tax_based_on" hook handler
	 */
	public static function action_option_woocommerce_tax_based_on() {
		return 'shipping';
	}

	/**
	 * Register wp cli commands.
	 */
	protected static function register_commands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'stripe-tax-transaction-reverse', TaxTransactionReversal::class );
	}

	/**
	 * Using this to ignore Woocommerce rates to be displayed in shop.
	 */
	public static function filter_woocommerce_find_rates() {
		return array();
	}

	/**
	 * Handles exceptions.
	 * If the request is REST then it adds the error message to ErrorRenderer,
	 * otherwise it formats and outputs the error message.
	 *
	 * @param Exception $e Handled exception.
	 */
	public static function handle_calculate_tax_error( $e ) {
		$message_id = 'calculate_tax_error';
		if ( $e instanceof CountrySupportException ) {
			$message_id = 'setting_country_error';
		}

		if ( $e instanceof CountryStateException ) {
			$message_id = 'setting_state_error';
		}

		if ( ! ErrorRenderer::get_error_object( $message_id )->message ) {
			ErrorRenderer::set_error_object( $message_id, 'Stripe Tax: ' . $e->getMessage(), 'error' );
		} else {
			// Error already reported.
			return;
		}

		if ( ! static::$error_reporting_collect ) {
			echo wp_kses( ErrorRenderer::get_rendered_error( $message_id ), StripeTaxPluginHelper::get_admin_allowed_html() );
		} else {
			static::$error_reporting_collect_enabled = true;
		}
	}

	/**
	 * Preserve original WooCommerce order types to group plus any added by another plugin.
	 *
	 * @param array $type_to_group Type to group.
	 */
	public static function filter_woocommerce_order_type_to_group( $type_to_group ) {
		static::$type_to_group = $type_to_group;

		return $type_to_group;
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	public static function render_admin_notices() {
		if ( get_transient( 'stripe_tax_for_woocommerce_activate' ) ) {
			include STRIPE_TAX_FOR_WOOCOMMERCE_TEMPLATES_DIR . 'plugin-activate-notice.php';
			delete_transient( 'stripe_tax_for_woocommerce_activate' );
		}
	}

	/**
	 * WooCommerce order partially refunded action.
	 *
	 * @param int $order_id Order id.
	 * @param int $refund_id Refund id.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public static function action_woocommerce_order_partially_refunded( $order_id, $refund_id ) {
		try {
			TaxTransaction::create_order_refund_reversal( $refund_id );
		} catch ( \Throwable $err ) {
			StripeTaxLogger::log_error( $err->getMessage() );
		}
	}

	/**
	 * WooCommerce order fully refunded action.
	 *
	 * @param int $order_id Order id.
	 *
	 * @throws ApiErrorException In case of API error.
	 */
	public static function action_woocommerce_order_fully_refunded( $order_id ) {
		try {
			TaxTransaction::reverse_order_last_transaction( $order_id );
		} catch ( \Throwable $err ) {
			StripeTaxLogger::log_error( $err->getMessage() );
		}
	}

	/**
	 * Replace WooCommerce Order Item Tax class to ours StripeOrderItemTax class
	 *
	 * @param class-string $classname The classname.
	 * @param string       $item_type The item type.
	 *
	 * @return class-string
	 */
	public static function filter_woocommerce_get_order_item_classname( $classname, $item_type ) {
		if ( 'tax' === $item_type ) {
			return StripeOrderItemTax::class;
		}

		return $classname;
	}

	/**
	 * Checks whether WooCommerce is installed and active
	 *
	 * @return bool
	 */
	public static function is_woocommerce_activated() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Sets app info for Stripe Tax
	 *
	 * @return void
	 */
	public static function set_app_info() {
		if ( ! Stripe::getAppInfo() ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( file_exists( __DIR__ . '/../stripe-tax-for-woocommerce.php' ) ) {
				$plugin_data = get_plugin_data( __DIR__ . '/../stripe-tax-for-woocommerce.php' );

				Stripe::setAppInfo(
					$plugin_data['Name'],
					$plugin_data['Version'],
					null,
					STRIPE_TAX_PARTNER_ID
				);
			}
		}
	}

	/**
	 * Entry point for adding WordPress actions, filter, registering styles and scripts
	 *
	 * @param bool $force Force flag.
	 *
	 * @return void
	 */
	public function init( bool $force = false ): void {

		static::admin_ajax();

		add_action( 'admin_init', array( static::class, 'action_admin_init' ), 5, 0 );
		add_action( 'woocommerce_system_status_report', array( static::class, 'system_status_report' ) );

		add_filter( 'woocommerce_get_settings_pages', array( static::class, 'filter_add_stripe_tax_settings' ), 10, 1 );
		add_filter(
			'plugin_action_links_' . STRIPE_TAX_FOR_WOOCOMMERCE_PLUGIN_BASENAME,
			array(
				static::class,
				'plugin_action_links',
			)
		);
		static::admin_enqueue_styles();
		static::admin_enqueue_scripts();

		if ( self::can_init( $force ) ) {
			static::$tax_exemptions = new TaxExemptions();

			static::$hooks_initialized = true;

			$this->add_actions();
			static::add_filters();
			static::register_commands();
			StripeCalculationTracker::init();

			static::check_migrations();
			static::check_string_tax_rate_id_fixer();
		}

		StripeTax_Plugin::load();
	}

	/**
	 * Check if the plugin can perform init action.
	 *
	 * @param bool $force Force flag.
	 *
	 * @return bool
	 */
	private static function can_init( bool $force = false ): bool {
		if ( ! empty( static::$hooks_initialized ) && ! $force ) {
			return false;
		}

		if ( ! static::is_woocommerce_activated() ) {
			add_action(
				'admin_notices',
				function () {
					/* translators: 1. URL link. */
					echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe Tax requires WooCommerce to be installed and active. You can download %s here.', 'stripe-tax-for-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
				}
			);

			return false;
		}

		return ! empty( Options::get_current_mode_key() );
	}

	/**
	 * Check if migrations needed.
	 */
	public static function check_migrations() {
		$plugin_migration = get_option( 'stripe_tax_migration' );

		if ( '1' !== $plugin_migration ) {
			PluginActivate::maybe_migrate_tax_transactions_table();
			update_option( 'stripe_tax_migration', '1' );
		}
	}

	/**
	 * Starts string tax rate ids fixer if needed
	 */
	public static function check_string_tax_rate_id_fixer() {
		StringTaxRateIdFixerScheduledAction::register();
	}

	/**
	 * Adds tax exemptions action.
	 *
	 * @param object $instance The instance.
	 */
	protected static function add_action_tax_exemptions( $instance ) {
		add_action( 'show_user_profile', array( $instance, 'custom_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $instance, 'custom_user_profile_fields' ) );

		add_action( 'personal_options_update', array( $instance, 'save_custom_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $instance, 'save_custom_user_profile_fields' ) );
	}

	/**
	 * Calculates an order or cart line item totals
	 *
	 * @param array  $line_items Orde or cart item lines.
	 * @param object $response API Response.
	 */
	public static function get_totals_from_response( $line_items, $response ) {
		$subtotal            = 0;
		$subtotal_tax        = 0;
		$cart_contents_total = 0;
		$cart_contents       = array();
		$cart_contents_tax   = 0;
		$cart_contents_taxes = array();
		$discount_total      = 0;
		$discount_tax        = 0;

		foreach ( $line_items as $item_key => $line_item ) {
			$line_tax_data = array(
				'subtotal'   => array(),
				'total'      => array(),
				'percentage' => array(),
			);

			foreach ( $response->line_items->data as $datum ) {
				if ( $datum->reference !== $line_item['reference'] ) {
					continue;
				}

				$prices_include_tax = ! ( 'exclusive' === $datum->tax_behavior );
				$has_discount       = $line_item['line_subtotal'] !== $line_item['line_total'];

				$line_total_rate_percentage = 0;
				$line_subtotal              = $line_item['line_subtotal'];
				$line_total                 = $datum->amount - ( ! $prices_include_tax ? 0 : $datum->amount_tax );
				$line_tax                   = $datum->amount_tax;

				foreach ( $datum->tax_breakdown as $tax_breakdown ) {
					if ( ! isset( $tax_breakdown->tax_rate_details ) && ! is_object( $tax_breakdown->tax_rate_details ) ) {
						continue;
					}

					$rate_name       = $tax_breakdown->jurisdiction->display_name . ' ' . $tax_breakdown->tax_rate_details->display_name;
					$rate_percentage = $tax_breakdown->tax_rate_details->percentage_decimal;
					$tax_type        = $tax_breakdown->tax_rate_details->tax_type;

					$rate_key = StripeTaxTaxRateMemRepo::find_or_create(
						$tax_breakdown->jurisdiction->country,
						$tax_breakdown->jurisdiction->state,
						(float) $tax_breakdown->tax_rate_details->percentage_decimal,
						$tax_breakdown->jurisdiction->display_name . ' ' . $tax_breakdown->tax_rate_details->display_name
					);

					if ( ! array_key_exists( $rate_key, $cart_contents_taxes ) ) {
						$cart_contents_taxes[ $rate_key ] = 0;
					}

					if ( ! array_key_exists( $rate_key, $line_tax_data['subtotal'] ) ) {
						$line_tax_data['subtotal'][ $rate_key ]   = 0;
						$line_tax_data['total'][ $rate_key ]      = 0;
						$line_tax_data['percentage'][ $rate_key ] = 0;
					}

					$line_total_rate_percentage += $rate_percentage;

					$cart_contents_taxes[ $rate_key ] += $tax_breakdown->amount;

					$line_tax_data['total'][ $rate_key ]      += $tax_breakdown->amount;
					$line_tax_data['percentage'][ $rate_key ] += $rate_percentage;
				}

				if ( ! $prices_include_tax ) {
					$line_subtotal = $line_item['line_subtotal'];
				} else {
					$line_subtotal = ! $has_discount ? NumberUtil::round( wc_remove_number_precision( $line_total ), wc_get_price_decimals() ) : $line_item['line_subtotal'] * 100 / ( 100 + $line_total_rate_percentage );
				}

				foreach ( $line_tax_data['total'] as $rate_key => $line_tax_data_total ) {
					if ( $has_discount ) {
						$line_tax_data['subtotal'][ $rate_key ] = $line_subtotal * $line_tax_data['percentage'][ $rate_key ];
					} else {
						$line_tax_data['subtotal'][ $rate_key ] = $line_tax_data_total;
					}
				}

				$cart_contents[ $item_key ]['line_tax_data'] = array(
					'total'    => wc_remove_number_precision_deep( $line_tax_data['total'] ),
					'subtotal' => wc_remove_number_precision_deep( $line_tax_data['subtotal'] ),
				);

				$line_subtotal_tax = NumberUtil::round( wc_remove_number_precision( array_sum( $line_tax_data['subtotal'] ) ), wc_get_price_decimals() );
				$line_tax          = NumberUtil::round( wc_remove_number_precision( array_sum( $line_tax_data['total'] ) ), wc_get_price_decimals() );

				$line_total = ! $prices_include_tax ? $line_item['line_total'] : $line_item['line_total'] - $line_tax;

				$cart_contents[ $item_key ]['line_total']        = $line_total;
				$cart_contents[ $item_key ]['line_subtotal']     = $line_subtotal;
				$cart_contents[ $item_key ]['line_subtotal_tax'] = $line_subtotal_tax;
				$cart_contents[ $item_key ]['line_tax']          = $line_tax;

				$subtotal_tax        += $line_subtotal_tax;
				$cart_contents_tax   += $line_tax;
				$cart_contents_total += $line_total;
				$subtotal            += $line_subtotal;

				if ( $has_discount ) {
					$discount_total += ( $line_subtotal - $line_total );
					$discount_tax   += ( $line_subtotal_tax - $line_tax );
				}
			}
		}

		return array(
			'cart_contents'       => $cart_contents,
			'cart_contents_taxes' => wc_remove_number_precision_deep( $cart_contents_taxes ),
			'subtotal'            => $subtotal,
			'cart_contents_total' => $cart_contents_total,
			'cart_contents_tax'   => $cart_contents_tax,
			'subtotal_tax'        => $subtotal_tax,
			'discount_total'      => $discount_total,
			'discount_tax'        => $discount_tax,
		);
	}

	/**
	 * On REST requests enables error collecting mechanism.
	 */
	public static function detect_error_reporting_collect() {
		static::$error_reporting_collect = true;
	}

	/**
	 * Adds collected errors to the REST response.
	 *
	 * @param WP_REST_Response $response REST response.
	 * @param array            $handler  REST handler.
	 * @param WP_REST_Request  $request  REST request.
	 */
	public static function add_collected_errors_to_response( $response, $handler, $request ) {
		$request_route = $request->get_route();

		if ( substr( $request_route, -6 ) === '/batch' ) {
			return $response;
		}
		if ( ! static::$error_reporting_collect_enabled ) {
			return $response;
		}
		$calculate_tax_error_message = ErrorRenderer::get_error_object( 'calculate_tax_error' )->message;

		if ( ! $calculate_tax_error_message ) {
			return $response;
		}

		$response->data['errors'][] = array(
			'code'    => 'calculate_tax_error',
			'message' => $calculate_tax_error_message,
		);

		return $response;
	}

	/**
	 * Hooks extra necessary sections into the system status report template
	 */
	public static function system_status_report(): void {
		?>
			<table class="wc_status_table widefat">
				<thead>
				<tr>
					<th colspan="5" data-export-label="Stripe">
						<h2>
							<?php esc_html_e( 'Stripe', 'stripe_tax_for_woocommerce' ); ?>
							<?php echo wp_kses_post( wc_help_tip( esc_html__( 'This section shows details of Stripe.', 'stripe_tax_for_woocommerce' ) ) ); ?>
						</h2>
					</th>
				</tr>
				</thead>
				<tbody>
					<tr>
						<td data-export-label="Account ID">
							<?php esc_html_e( 'Account ID', 'stripe_tax_for_woocommerce' ); ?>:
						</td>
						<td class="help">
							<?php echo wp_kses_post( wc_help_tip( esc_html__( 'Stripe Account ID.', 'stripe_tax_for_woocommerce' ) ) ); ?>
						</td>
						<td>
							<?php
							try {
								if ( ! empty( Options::get_current_mode_key() ) ) {
									if ( ! empty( Options::get_option( Options::OPTION_LIVE_MODE_ACCOUNT_ID ) ) ) {
										echo esc_html( Options::get_option( Options::OPTION_LIVE_MODE_ACCOUNT_ID ) );
									} else {
										$stripe_client   = new StripeClient( Options::get_current_mode_key() );
										$account_service = new AccountService( $stripe_client );
										$api_response    = $account_service->retrieve();
										if ( ! isset( $api_response->object ) || 'account' !== $api_response->object ) {
											echo esc_html__( 'Account ID could not be retrieved, please try to reconnect to Stripe.', 'stripe_tax_for_woocommerce' );
										} else {
											Options::update_option( Options::OPTION_LIVE_MODE_ACCOUNT_ID, $api_response->{ 'id' } );
											echo esc_html( Options::get_option( Options::OPTION_LIVE_MODE_ACCOUNT_ID ) );
										}
									}
								} else {
									echo esc_html__( 'Account ID could not be retrieved, please try to reconnect to Stripe.', 'stripe_tax_for_woocommerce' );
								}
							} catch ( \Throwable $e ) {
								echo esc_html__( 'Account ID could not be retrieved, please try to reconnect to Stripe.', 'stripe_tax_for_woocommerce' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		<?php
	}

	/**
	 * Converts tax rate ids from string to integer.
	 *
	 * @param WP_REST_Response $response REST response.
	 */
	public static function filter_rest_prepare_shop_order_object( $response ) {
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
		$data = $response->get_data();

		$counter = 5000000000;

		if ( isset( $data['line_items'] ) ) {
			foreach ( $data['line_items'] as $idx_line => $line_item ) {
				if ( isset( $line_item['taxes'] ) ) {
					$order_id = $data['id'];

					foreach ( $line_item['taxes'] as $idx_tax => $line_item_tax ) {
						++$counter;
						$rate_id = $line_item_tax['id'];

						if ( is_numeric( $rate_id ) || strpos( $rate_id, 'stripe_tax_for_woocommerce__' ) === false ) {
							break 2;
						}

						$new_rate_id = $order_id * 1000 + $counter;

						$idx_tax_2 = null;

						foreach ( $data['tax_lines'] as $idx_tax_2 => $tax_line ) {
							if ( $tax_line['rate_id'] === $rate_id ) {
								break;
							}
						}

						$data['line_items'][ $idx_line ]['taxes'][ $idx_tax ]['id'] = $new_rate_id;
						if ( ! is_null( $idx_tax_2 ) ) {
							$data['tax_lines'][ $idx_tax_2 ]['rate_id'] = $new_rate_id;
						}
					}
				}
			}
		}

		if ( isset( $data['shipping_lines'] ) ) {
			foreach ( $data['shipping_lines'] as $idx_line => $line_item ) {
				if ( isset( $line_item['taxes'] ) ) {
					$order_id = $data['id'];
					foreach ( $line_item['taxes'] as $idx_tax => $line_item_tax ) {
						++$counter;
						$rate_id = $line_item_tax['id'];
						if ( is_numeric( $rate_id ) || strpos( $rate_id, 'stripe_tax_for_woocommerce__' ) === false ) {
							break 2;
						}

						$new_rate_id = $order_id * 1000 + $counter;

						$idx_tax_2 = null;
						foreach ( $data['tax_lines'] as $idx_tax_2 => $tax_line ) {
							if ( $tax_line['rate_id'] === $rate_id ) {
								break;
							}
						}

						$data['shipping_lines'][ $idx_line ]['taxes'][ $idx_tax ]['id'] = $new_rate_id;

						if ( ! is_null( $idx_tax_2 ) ) {
							$data['tax_lines'][ $idx_tax_2 ]['rate_id'] = $new_rate_id;
						}
					}
				}
			}
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Renders a stored admin error message for a WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public static function render_admin_error_message( $order_id ): void {
		$msg = get_post_meta( $order_id, '_stripe_tax_last_error', true );

		if ( $msg ) {
			delete_post_meta( $order_id, '_stripe_tax_last_error' );

			echo '<span class="stripe_tax_for_woocommerce_message_span_id_" id="stripe_tax_for_woocommerce_message_id_"> </span>'
				. '<div class="stripe_tax_for_woocommerce_message stripe_tax_for_woocommerce_message_" id="stripe_tax_for_woocommerce_message_id_">'
				. '<p>Error: <strong>' . esc_html( $msg ) . '</strong></p>'
				. '</div>';
		}
	}
}
