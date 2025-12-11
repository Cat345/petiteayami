<?php
if ( ! class_exists( 'WFOCU_Admin' ) ) {
	#[AllowDynamicProperties]
	class WFOCU_Admin {

		private static $ins = null;
		public $admin_path;
		public $admin_url;
		public $section_page = '';
		public $should_show_shortcodes = null;
		public $updater = null;
		public $thank_you_page_posts = null;

		public function __construct() {

			$this->admin_path = WFOCU_PLUGIN_DIR . '/admin';
			$this->admin_url  = WFOCU_PLUGIN_URL . '/admin';

			$this->section_page = ( $this->is_upstroke_page() ) ? filter_input( INPUT_GET, 'section', FILTER_UNSAFE_RAW ) : '';


			/**
			 * Admin enqueue scripts
			 */
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 99 );

			/**
			 * Admin customizer enqueue scripts
			 */
			add_action( 'customize_controls_print_styles', array( $this, 'admin_customizer_enqueue_assets' ), 10 );

			/**
			 * Admin footer text
			 */
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 9999, 1 );

			add_action( 'save_post', array( $this, 'maybe_reset_transients' ), 10, 2 );
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'js_variables' ), 0 );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'maybe_register_breadcrumbs' ), 10 );
			}
			if ( $this->is_upstroke_page() && isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

				add_action( 'admin_init', array( $this, 'maybe_set_funnel_id' ) );
			}
			add_action( 'delete_post', [ $this, 'clear_transients_on_delete' ], 10 );
			if ( class_exists( 'BWF_WC_Compatibility' ) && BWF_WC_Compatibility::is_hpos_enabled() ) {
				add_action( 'woocommerce_delete_order', [ $this, 'clear_session_record_on_shop_order_delete' ], 10 );

			} else {
				add_action( 'delete_post', [ $this, 'clear_session_record_on_shop_order_delete' ], 10 );
			}

			/**
			 * Hooks to check if activation and deactivation request for post.
			 */
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'wfocu-post-activate' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

				add_action( 'admin_init', array( $this, 'maybe_activate_post' ) );
			}
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'wfocu-post-deactivate' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

				add_action( 'admin_init', array( $this, 'maybe_deactivate_post' ) );
			}
			add_action( 'customize_controls_print_footer_scripts', array( $this, 'maybe_print_mergetag_helpbox' ) );
			add_filter( 'plugin_action_links_' . WFOCU_PLUGIN_BASENAME, array( $this, 'plugin_actions' ) );
			if ( $this->is_upstroke_page() && ! empty( $_REQUEST['_wp_http_referer'] ) && ! empty( $_REQUEST['REQUEST_URI'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				add_action( 'admin_init', array( $this, 'maybe_handle_http_referer' ) );
			}
			add_action( 'woocommerce_admin_field_payment_gateways', array( $this, 'hide_test_gateway_from_admin_list' ) );

			$get_db_version = get_option( '_wfocu_db_version', '0.0.0' );

			if ( version_compare( WFOCU_DB_VERSION, $get_db_version, '>' ) ) {
				add_action( 'admin_init', array( $this, 'check_db_version' ), 990 );
			}
			add_action( 'admin_bar_menu', array( $this, 'toolbar_link_to_xlplugins' ), 999 );

			add_filter( 'woocommerce_payment_gateways_setting_columns', array( $this, 'set_wc_payment_gateway_column' ) );

			add_action( 'woocommerce_payment_gateways_setting_column_wfocu', array( $this, 'wc_payment_gateway_column_content' ) );

			/**
			 * Initiate Background Database updaters
			 */
			add_action( 'init', array( $this, 'init_background_updater' ) );
			add_action( 'admin_head', array( $this, 'maybe_update_database_update' ) );
			$get_db_version = get_option( '_wfocu_plugin_version', '' );

			if ( version_compare( $get_db_version, WFOCU_VERSION, '<' ) ) {
				add_action( 'admin_init', array( $this, 'maybe_update_upstroke_version_in_option' ) );
			}

			/**
			 * Handling to prevent scripts and styles in our pages.
			 */
			if ( ! apply_filters( 'wfocu_no_conflict_mode', true ) ) {

				add_action( 'wp_print_scripts', array( $this, 'no_conflict_mode_script' ), 1000 );
				add_action( 'admin_print_footer_scripts', array( $this, 'no_conflict_mode_script' ), 9 );
			}
			if ( ! apply_filters( 'wfocu_no_conflict_mode', true ) ) {

				add_action( 'wp_print_styles', array( $this, 'no_conflict_mode_style' ), 1000 );
				add_action( 'admin_print_styles', array( $this, 'no_conflict_mode_style' ), 1 );
				add_action( 'admin_print_footer_scripts', array( $this, 'no_conflict_mode_style' ), 1 );
				add_action( 'admin_footer', array( $this, 'no_conflict_mode_style' ), 1 );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

                add_action( 'admin_head', function () {
                        echo "<div class='wfocu_builder_admin_head_wrap'>";

                }, - 1 );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

				add_action( 'admin_head', function () {
					echo "</div>";

			    }, 999 );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

				add_action( 'admin_footer', function () {
					echo "<div class='wfocu_builder_admin_foot_wrap'>";

			    }, - 1 );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

				add_action( 'admin_footer', function () {
					echo "</div>";

			    }, 999 );
			}
			add_filter( 'woofunnels_global_settings', function ( $menu ) {
				array_push( $menu, array(
					'title'    => __( 'One Click Upsells', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'upstroke',
					'link'     => admin_url( 'admin.php?page=upstroke&tab=settings' ),
					'priority' => 50,
					'pro_tab'  => true,
				) );

				return $menu;
			} );
			add_action( 'edit_form_after_title', [ $this, 'add_back_button' ] );

			/*** bwf general setting ***/
			add_filter( 'bwf_general_settings_link', function () {
				return admin_url( 'admin.php?page=upstroke&tab=bwf_settings' );
			} );

			add_action( 'admin_footer', function () {
				?>
                <script>
                    if (typeof window.bwfBuilderCommons !== "undefined") {
                        window.bwfBuilderCommons.addFilter('bwf_common_permalinks_fields', function (e) {
                            e.push(
                                {
                                    type: "input",
                                    inputType: "text",
                                    label: "",
                                    model: "wfocu_page_base",
                                    inputName: 'wfocu_page_base',
                                });
                            return e;
                        });
                    }

                </script>
				<?php
			}, 90 );

			add_filter( 'bwf_general_settings_fields', function ( $fields ) {
				$fields['wfocu_page_base'] = array(
					'type'      => 'input',
					'inputType' => 'text',
					'label'     => __( 'Upsell Page', 'woofunnels-upstroke-one-click-upsell' ),
					'hint'      => __( '', 'woofunnels-upstroke-one-click-upsell' ),
				);

				return $fields;
			}, 90 );
			add_filter( 'bwf_general_settings_default_config', function ( $fields ) {
				$fields['wfocu_page_base'] = 'offer';

				return $fields;
			} );

			/**
			 * Tell core to show these settings
			 */
			add_filter( 'bwf_enable_ecommerce_integration_pinterest', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_fb_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_ga_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_gad_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_pint_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_tiktok_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_snapchat_purchase', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_gad', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_tiktok', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_snapchat', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_pixel', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_ga', '__return_true' );
			add_filter( 'bwf_enable_ga4', '__return_true' );


			add_action( 'wfocu_loaded', array( $this, 'maybe_add_timeline_files' ), 999 );
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 90 );

			add_filter( 'wfocu_add_control_meta_query', array( $this, 'exclude_from_query' ) );
			add_filter( 'woofunnels_global_settings_fields', array( $this, 'add_global_settings_fields' ) );
			if (isset($_GET['page'] ) &&'upstroke' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_action( 'admin_init', array( $this, 'maybe_show_wizard' ) );
			}
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'show_advanced_field_order' ), 9 );

			add_action( 'wfocu_build_offer_product_before', array( $this, 'maybe_check_offer_data_for_qty_change' ), 10, 2 );


			add_action( 'wfocu_fkwcs_delete_duplicate_comments', array( $this, 'delete_duplicate_comments_function' ) );
			add_action( 'wfocu_fkwcs_clear_delete_duplicate_comments_schedule', array( $this, 'clear_delete_duplicate_comments_schedule_function' ) );
			add_action( 'admin_footer', array( $this, 'add_script_for_fire_normalize_order' ) );
		}


		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function get_admin_url() {
			return WFOCU_PLUGIN_URL . '/admin';
		}

		public function admin_enqueue_assets() {
			$is_min = 'min';
			$suffix = '.min';
			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				$is_min = '';
				$suffix = '';
			}

			wp_enqueue_style( 'woofunnels-admin-font', $this->get_admin_url() . '/assets/css/wfocu-admin-font.css', array(), WFOCU_VERSION_DEV );
			$gateways_list = [];
			if ( $this->is_upstroke_page() ) {
				WFOCU_Core()->funnels->setup_funnel_options( ( isset( $_GET['edit'] ) ? wc_clean( $_GET['edit'] ) : 0 ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( 'rules' === $this->section_page ) {
				wp_register_script( 'wfocu-chosen', $this->get_admin_url() . '/assets/js/chosen/chosen.jquery.min.js', array( 'jquery' ), WFOCU_VERSION_DEV );
				wp_register_script( 'wfocu-ajax-chosen', $this->get_admin_url() . '/assets/js/chosen/ajax-chosen.jquery.min.js', array(
					'jquery',
					'wfocu-chosen',
				), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'wfocu-ajax-chosen' );

				wp_enqueue_style( 'wfocu-chosen-app', $this->get_admin_url() . '/assets/css/chosen.css', array(), WFOCU_VERSION_DEV );
				wp_enqueue_style( 'wfocu-admin-app', $this->get_admin_url() . '/assets/css/wfocu-admin-app.css', array(), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'jquery-masked-input' );
				wp_enqueue_script( 'wfocu-admin-app', $this->get_admin_url() . '/assets/js/wfocu-admin-app.js', array(
					'jquery',
					'jquery-ui-datepicker',
					'underscore',
					'backbone',
				), WFOCU_VERSION_DEV );

			}
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {
				wp_enqueue_script( 'wfocu-admin-ajax', $this->get_admin_url() . '/assets/js/wfocu-ajax.js', [], WFOCU_VERSION_DEV );
			}
			/**
			 * Load Color Picker
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'settings' ) ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}

			/**
			 * Load Funnel Builder page assets
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				//wp_enqueue_style( 'wfocu-funnel-bg', $this->admin_url . '/assets/css/wfocu-funnel-bg.css', array(), WFOCU_VERSION_DEV );
				wp_enqueue_style( 'wfocu-opensans-font', '//fonts.googleapis.com/css?family=Open+Sans', array(), WFOCU_VERSION_DEV );

			}
			if ( 'shop_order' === get_current_screen()->post_type ) {
				wp_enqueue_style( 'wfocu-timeline-style', $this->get_admin_url() . '/assets/css/wfocu-timeline' . $suffix . '.css', array(), WFOCU_VERSION_DEV );
			}
			/**
			 * Including izimodal assets
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {
				wp_enqueue_style( 'wfocu-izimodal', $this->admin_url . '/includes/iziModal/iziModal.min.css', array(), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'wfocu-izimodal', $this->admin_url . '/includes/iziModal/iziModal.min.js', array(), WFOCU_VERSION_DEV );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'settings' ) ) {
				$gateways_list = WFOCU_Core()->gateways->get_gateways_list();
				wp_enqueue_script( 'jquery-tiptip' );

			}
			/**
			 * Including vuejs assets
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'settings' ) || ( WFOCU_Common::is_load_admin_assets( 'all' ) && false === $this->is_upstroke_page( 'rules' ) ) ) {
				wp_enqueue_style( 'wfocu-vue-multiselect', $this->admin_url . '/includes/vuejs/vue-multiselect.min.css', array(), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_script( 'wfocu-vuejs', $this->admin_url . '/includes/vuejs/vue.min.js', array(), '2.6.10' );
				wp_enqueue_script( 'wfocu-vue-vfg', $this->admin_url . '/includes/vuejs/vfg.min.js', array(), '2.3.4' );
				wp_enqueue_script( 'wfocu-vue-multiselect', $this->admin_url . '/includes/vuejs/vue-multiselect.min.js', array(), WFOCU_VERSION_DEV );
			}
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				wp_enqueue_script( 'accounting' );
				$price_args = apply_filters( 'wc_price_args', array(
					'ex_tax_label'       => false,
					'currency'           => '',
					'decimal_separator'  => wc_get_price_decimal_separator(),
					'thousand_separator' => wc_get_price_thousand_separator(),
					'decimals'           => wc_get_price_decimals(),
					'price_format'       => get_woocommerce_price_format(),
				) );

				wp_localize_script( 'accounting', 'wfocu_wc_params', array(
					'currency_format_num_decimals' => $price_args['decimals'],
					'currency_format_symbol'       => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'  => esc_attr( $price_args['decimal_separator'] ),
					'currency_format_thousand_sep' => esc_attr( $price_args['thousand_separator'] ),
					'currency_format'              => esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), $price_args['thousand_separator'] ) ),
				) );

			}

			if ( $this->is_upstroke_page( 'bwf_settings' ) ) {

				BWF_Admin_General_Settings::get_instance()->maybe_add_js();
			}
			/**
			 * Including One Click Upsell assets on all OCU pages.
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {
				wp_enqueue_style( 'woocommerce_admin_styles' );
				wp_enqueue_script( 'wc-backbone-modal' );

				if ( ! empty( $is_min ) ) {
					wp_enqueue_style( 'wfocu-admin', $this->admin_url . '/assets/css/' . $is_min . '/wfocu-admin' . $suffix . '.css', array(), ( defined( 'WFOCU_VERSION_ADMIN_DEV' ) ) ? WFOCU_VERSION_ADMIN_DEV : WFOCU_VERSION_DEV );

				} else {
					wp_enqueue_style( 'wfocu-admin', $this->admin_url . '/assets/css/wfocu-admin.css', array(), WFOCU_VERSION_DEV );

				}
				wp_enqueue_script( 'wfocu-admin', $this->admin_url . '/assets/js/wfocu-admin.js', array(), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'wfocu-swal', $this->admin_url . '/assets/js/wfocu-sweetalert.min.js', array(), WFOCU_VERSION_DEV );

				wp_enqueue_script( 'wfocu-admin-builder', $this->admin_url . '/assets/js/wfocu-admin-builder.js', array(
					'jquery',
					'wfocu-swal',
					'wfocu-vuejs',
					'wfocu-vue-vfg',
					'wfocu-admin'
				), WFOCU_VERSION_DEV );
				wp_enqueue_script( 'updates' );
			}

			/**
			 * deregister this script as its in the conflict with the vue JS
			 */
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {

				wp_dequeue_script( 'backbone-marionette' );
				wp_deregister_script( 'backbone-marionette' );
			}

			if ( WFOCU_Common::is_load_admin_assets( 'customizer' ) ) {

				wp_enqueue_script( 'wfocu-modal', WFOCU_PLUGIN_URL . '/admin/assets/js/wfocu-modal.js', array( 'jquery' ), WFOCU_VERSION );
				wp_enqueue_style( 'wfocu-modal', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-modal.css', null, WFOCU_VERSION );

			}

			$tags = WFOCU_Common::get_oxy_builder_shortcode();

			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {

				$data = array(
					'ajax_nonce'                            => wp_create_nonce( 'wfocuaction-admin' ),
					'ajax_nonce_toggle_funnel_state'        => wp_create_nonce( 'wfocu_toggle_funnel_state' ),
					'ajax_nonce_preview_details'            => wp_create_nonce( 'wfocu_preview_details' ),
					'ajax_nonce_duplicate_funnel'           => wp_create_nonce( 'wfocu_duplicate_funnel' ),
					'ajax_nonce_save_rules_settings'        => wp_create_nonce( 'wfocu_save_rules_settings' ),
					'ajax_nonce_remove_offer_from_funnel'   => wp_create_nonce( 'wfocu_remove_offer_from_funnel' ),
					'ajax_nonce_save_funnel_steps'          => wp_create_nonce( 'wfocu_save_funnel_steps' ),
					'ajax_nonce_product_search'             => wp_create_nonce( 'wfocu_product_search' ),
					'ajax_nonce_wfocu_add_product'          => wp_create_nonce( 'wfocu_add_product' ),
					'ajax_nonce_remove_product'             => wp_create_nonce( 'wfocu_remove_product' ),
					'ajax_nonce_save_funnel_settings'       => wp_create_nonce( 'wfocu_save_funnel_settings' ),
					'ajax_nonce_save_funnel_offer_settings' => wp_create_nonce( 'wfocu_save_funnel_offer_settings' ),
					'ajax_nonce_save_funnel_offer_product'  => wp_create_nonce( 'wfocu_save_funnel_offer_product' ),
					'ajax_nonce_save_global_settings'       => wp_create_nonce( 'wfocu_save_global_settings' ),
					'ajax_nonce_apply_template'             => wp_create_nonce( 'wfocu_apply_template' ),
					'ajax_nonce_update_template'            => wp_create_nonce( 'wfocu_update_template' ),
					'ajax_nonce_update_edit_url'            => wp_create_nonce( 'wfocu_update_edit_url' ),
					'ajax_nonce_activate_plugins'           => wp_create_nonce( 'wfocu_activate_plugins' ),
					'ajax_nonce_clear_template'             => wp_create_nonce( 'wfocu_clear_template' ),
					'ajax_nonce_get_custom_page'            => wp_create_nonce( 'wfocu_get_custom_page' ),
					'ajax_nonce_make_wpml_duplicate'        => wp_create_nonce( 'wfocu_make_wpml_duplicate' ),
					'ajax_nonce_get_wpml_edit_url'          => wp_create_nonce( 'wfocu_get_wpml_edit_url' ),
					'plugin_url'                            => WFOCU_PLUGIN_URL,
					'ajax_url'                              => admin_url( 'admin-ajax.php' ),
					'admin_url'                             => admin_url(),
					'ajax_chosen'                           => wp_create_nonce( 'json-search' ),
					'search_products_nonce'                 => wp_create_nonce( 'search-products' ),
					'search_customers_nonce'                => wp_create_nonce( 'search-customers' ),
					'search_coupons_nonce'                  => wp_create_nonce( 'search-coupons' ),
					'text_or'                               => __( 'or', 'woofunnels-upstroke-one-click-upsell' ),
					'text_apply_when'                       => __( 'Open this page when these conditions are matched', 'woofunnels-upstroke-one-click-upsell' ),
					'remove_text'                           => __( 'Remove', 'woofunnels-upstroke-one-click-upsell' ),
					'modal_add_offer_step_text'             => __( 'Add Offer', 'woofunnels-upstroke-one-click-upsell' ),
					'modal_add_add_product'                 => __( 'Add Products', 'woofunnels-upstroke-one-click-upsell' ),
					'modal_update_offer'                    => __( 'Offers', 'woofunnels-upstroke-one-click-upsell' ),
					'modal_funnel_div'                      => __( 'Upsell Funnel', 'woofunnels-upstroke-one-click-upsell' ),
					'section_page'                          => $this->section_page,
					'alerts'                                => array(
						'delete_offer'         => array(
							'title'             => __( 'Want to Remove this offer from your funnel?', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'You are about to delete this offer. This action cannot be undone. Cancel to stop, Delete to proceed.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Delete', 'woofunnels-upstroke-one-click-upsell' ),
							'type'              => 'error',
							'modal_title'       => __( 'Delete Offer', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'remove_offer'         => array(
							'title'             => __( 'Want to Remove this offer from your funnel?', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'You are about to remove this offer. This action cannot be undone. Cancel to stop, Remove to proceed.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Remove', 'woofunnels-upstroke-one-click-upsell' ),
							'type'              => 'error',
							'modal_title'       => __( 'Remove Offer', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'offer_edit'           => array(
							'title'             => __( 'Hey! A gentle reminder that this offer is inactive.', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'Do activate the offer when you have completed the setup.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Continue and Save!', 'woofunnels-upstroke-one-click-upsell' ),
							'img_url'           => WFOCU_PLUGIN_URL . '/admin/assets/img/set_active.gif'
						),
						'jump_error'           => array(
							'title'             => __( 'Sorry! we are unable to save this offer.', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'You have enabled dynamic offer path but no offer is selected. Please select an offer.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Close and Select!', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'no_variations_chosen' => array(
							'title'             => __( 'Oops! Unable to save this offer', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'This offer contains product(s) with no variation selected. Please select at least one variation', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Okay! Got it', 'woofunnels-upstroke-one-click-upsell' ),
							'type'              => 'error',
						),
						'max_variation_error'  => array(
							'title'             => __( 'Oops! Unable to save this offer', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'This offer contains extremely large variants. Please increase server\'s max_input_vars limit. Not sure? Contact support.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Okay! Got it', 'woofunnels-upstroke-one-click-upsell' ),
							'type'              => 'error',
						),
						'remove_product'       => array(
							'title'             => __( 'Want to remove this product from the offer?', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'You are about to remove this product. This action cannot be undone. Cancel to stop, Delete to proceed.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Remove', 'woofunnels-upstroke-one-click-upsell' ),
							'modal_title'       => __( 'Remove Product', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'remove_template'      => array(
							'title'             => __( 'Are you sure you want to remove this template?', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'You are about to remove this template. Any changes done to the current template will be lost. Cancel to stop, Remove to proceed.', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Remove', 'woofunnels-upstroke-one-click-upsell' ),
							'modal_title'       => __( 'Remove Template', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'import_template'      => array(
							'title'             => __( 'Are you sure you want to import this template?', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => '',
							'confirmButtonText' => __( 'Yes, import this template!', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'failed_import_beaver' => array(
							'title'             => __( 'Unable to import this template', 'woofunnels-upstroke-one-click-upsell' ),
							'text'              => __( 'Beaver Builder PRO version is required to import this template', 'woofunnels-upstroke-one-click-upsell' ),
							'confirmButtonText' => __( 'Yes, import this template!', 'woofunnels-upstroke-one-click-upsell' ),
						),
					),
					'forms_labels'                          => array(

						'funnel_setting'        => array(
							array(
								'funnel_name' => array(
									'label' => __( 'Name Of Funnel', 'woofunnels-upstroke-one-click-upsell' ),
								),
							),
						),
						'add_new_offer_setting' => array(
							'funnel_step_name' => array(
								'label'       => __( 'Offer Name', 'woofunnels-upstroke-one-click-upsell' ),
								'placeholder' => __( 'Enter Offer Name', 'woofunnels-upstroke-one-click-upsell' ),
							),

							'step_type' => array(
								'label'  => __( 'Type', 'woofunnels-upstroke-one-click-upsell' ),
								'help'   => __( '<strong>Upsell</strong> <br/>The upsell is when you present a new offer.<hr/><strong>Downsell</strong><br/>The downsell is when your Upsell offer was declined and you present a new offer usually at a lower price.', 'woofunnels-upstroke-one-click-upsell' ),
								'values' => array(

									array(
										'name'  => __( 'Upsell', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'upsell',
									),
									array(
										'name'  => __( 'Downsell', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'downsell',
									),

								),
							),
						),
						'update_step'           => array(
							'funnel_step_name' => array(
								'label'       => __( 'Offer Name', 'woofunnels-upstroke-one-click-upsell' ),
								'placeholder' => __( 'Enter Offer Name', 'woofunnels-upstroke-one-click-upsell' ),
							),

							'step_type'        => array(
								'label'  => __( 'Type', 'woofunnels-upstroke-one-click-upsell' ),
								'help'   => __( '<strong>Upsell</strong> <br/>The upsell is when you present a new offer.<hr/><strong>Downsell</strong><br/>The downsell is when your Upsell offer was declined and you present a new offer usually at a lower price.', 'woofunnels-upstroke-one-click-upsell' ),
								'values' => array(

									array(
										'name'  => __( 'Upsell', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'upsell',
									),
									array(
										'name'  => __( 'Downsell', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'downsell',
									),

								),
							),
							'funnel_step_slug' => array(
								'label'       => __( 'Offer URL', 'woofunnels-upstroke-one-click-upsell' ),
								'placeholder' => __( 'Enter Offer Slug', 'woofunnels-upstroke-one-click-upsell' ),
							),
						),
						'settings'              => array(
							'funnel_order_label'        => array(

								'label' => __( 'Order Settings', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'order_behavior'            => array(

								'label'  => __( 'Each accepted upsell will be', 'woofunnels-upstroke-one-click-upsell' ),
								'values' => array(

									array(
										'name'  => __( 'Merged with the main order', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'batching',
									),
									array(
										'name'  => __( 'Create a new order', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'create_order',
									),

								),
							),
							'is_cancel_order'           => array(

								'label'        => __( 'Cancel Main Order', 'woofunnels-upstroke-one-click-upsell' ),
								'values'       => array(

									array(
										'name'  => __( 'Yes', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'yes',
									),
									array(
										'name'  => __( 'No', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'no',
									),

								),
								'styleClasses' => [ 'wfocu_gsettings_cancel_primary' ],
								'hint'         => __( 'Enable this setting to cancel the main order when <i>first offer</i> is accepted.', 'woofunnels-upstroke-one-click-upsell' ),


							),
							'funnel_priority_label'     => array(

								'label' => __( 'Priority', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'funnel_priority'           => array(

								'label' => __( 'Priority Number', 'woofunnels-upstroke-one-click-upsell' ),
								'hint'  => __( "There maybe chance more than one Upsells can trigger.\n In such cases, Upsells Priority is used to determine which Upsell will trigger. Priority Number 1 is considered highest.", 'woofunnels-upstroke-one-click-upsell' ),

							),
							'prices_settings'           => array(

								'label' => __( 'Price Settings', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'is_tax_included'           => array(

								'label'  => __( 'Show Prices with Taxes', 'woofunnels-upstroke-one-click-upsell' ),
								'values' => array(

									array(
										'name'  => __( 'Yes (Recommended)', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'yes',
									),
									array(
										'name'  => __( 'No', 'woofunnels-upstroke-one-click-upsell' ),
										'value' => 'no',
									),

								),
							),
							'offer_messages_label_help' => array(
								'label' => __( 'These messages show when buyer\'s upsell order is charged & confirmed. If unable to charge user, a failure message will show.<a href="javascript:void(0);" onclick="window.wfocuBuilder.show_funnel_design_messages()">Click here to learn about these settings.</a> ', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'offer_messages_label'      => array(

								'label' => __( 'Upsell Confirmation Messages', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'offer_success_message_pop' => array(

								'label' => __( 'Upsell Success Message', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'offer_failure_message_pop' => array(

								'label' => __( 'Upsell Failure Message', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'offer_wait_message_pop'    => array(

								'label' => __( 'Upsell Processing Message', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'offer_scripts_label'       => array(

								'label' => __( 'External Tracking Code', 'woofunnels-upstroke-one-click-upsell' ),

							),
							'funnel_success_script'     => array(

								'label'       => __( 'Add tracking code to run, this upsells', 'woofunnels-upstroke-one-click-upsell' ),
								'placeholder' => __( 'Paste your code here', 'woofunnels-upstroke-one-click-upsell' ),

							),
						),
						'global_settings'       => $this->all_global_settings_fields(),
						'offer_settings'        => array(
							'label_confirmation' => array(
								'label' => __( 'Ask Confirmation', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'ask_confirmation'   => array(
								'label' => __( 'Ask for confirmation every time user accepts this offer. A new side cart will trigger and ask for confirmation if this option is enabled.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'label_order'        => array(
								'label' => __( 'Skip Offer', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'skip_exist'         => array(
								'label' => __( 'Skip this offer if product(s) exist in parent order', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'skip_purchased'     => array(
								'label' => __( 'Skip this offer if buyer had ever purchased this product(s)', 'woofunnels-upstroke-one-click-upsell' ),
							),


							'upsell_page_track_code_label' => array(
								'label' => __( 'Tracking Code', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'check_add_offer_script'       => array(
								'label' => __( 'Add tracking code if the buyer views this offer', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'upsell_page_track_code'       => array(
								'placeholder' => __( 'Paste your code here', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'check_add_offer_purchase'     => array(
								'label' => __( 'Add tracking code if the buyer accepts this offer', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'upsell_page_purchase_code'    => array(
								'placeholder' => __( 'Paste your code here', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'qty_selector_label'           => array(
								'label' => __( 'Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'qty_selector'                 => array(
								'label' => __( 'Allow buyer to choose the quantity while purchasing this upsell product(s)', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'qty_max_label'                => array(
								'label' => __( 'Maximum Quantity', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'qty_max'                      => array(
								'placeholder' => __( 'Input Max Quantity', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'jump_to_offer'                => array(
								'label' => __( 'Dynamic Offer Path', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'jump_on_accepted'             => array(
								'label' => __( 'On acceptance, redirect buyers to', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'jump_on_rejected'             => array(
								'label' => __( 'On rejection, redirect buyers to', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'jump_to_offer_default_option' => array(
								'id'   => 'automatic',
								'name' => __( 'Select an Offer', 'woofunnels-upstroke-one-click-upsell' )
							),
							'jump_to_thankyou'             => array(
								'id'   => 'terminate',
								'name' => __( 'Thank You Page', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'jump_optgroups'               => array(
								'upsells'   => __( 'Upsells', 'woofunnels-upstroke-one-click-upsell' ),
								'downsells' => __( 'Downsells', 'woofunnels-upstroke-one-click-upsell' ),
								'terminate' => __( 'Terminate Funnel', 'woofunnels-upstroke-one-click-upsell' ),
							),
						),
					),
					'funnel_settings'                       => WFOCU_Core()->funnels->get_funnel_option(),
					'global_settings'                       => WFOCU_Core()->data->get_option(),
					'shortcodes'                            => $this->get_shortcodes_list(),
					'oxy_tags'                              => $tags,
					'templates'                             => WFOCU_Core()->template_loader->get_templates(),
					'permalinkStruct'                       => get_option( 'permalink_structure' ),
					'funnel_setting_tabs'                   => array(
						'basic'    => __( 'Basic', 'woofunnels-upstroke-one-click-upsell' ),
						'advanced' => __( 'Advanced', 'woofunnels-upstroke-one-click-upsell' ),
					),
					'swal_delete_modal_title'               => __( 'Delete', 'woofunnels-upstroke-one-click-upsell' ),
					'swal_remove_modal_title'               => __( 'Remove', 'woofunnels-upstroke-one-click-upsell' ),
				);
			}
			if ( WFOCU_Common::is_load_admin_assets( 'settings' ) ) {
				$data['isNOGateway'] = true;
				if ( $gateways_list && is_array( $gateways_list ) && count( $gateways_list ) > 0 ) {
					$data['isNOGateway'] = false;
				}
			}
			$funnel_id = filter_input( INPUT_GET, 'edit', FILTER_SANITIZE_NUMBER_INT );
			if ( $funnel_id > 0 ) {
				$data['is_funnel_upsell'] = ( get_post_meta( $funnel_id, '_bwf_in_funnel', true ) > 0 );
			}

			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				if ( isset( $_GET['section'] ) && $_GET['section'] === 'design' ) { // phpcs:ignore WordPress.Security.NonceVerification
					$data['pageBuildersOptions'] = WFOCU_Core()->template_loader->get_plugins_groupby_page_builders();
					$data['pageBuildersTexts']   = WFOCU_Core()->template_loader->localize_page_builder_texts();
				}
			}
			$default_builder = BWF_Admin_General_Settings::get_instance()->get_option( 'default_selected_builder' );
			if ( 'wp_editor' === $default_builder ) {
				$default_builder = 'custom';
			}
			$data['default_builder'] = ( ! empty( $default_builder ) ) ? $default_builder : 'elementor';
			$data                    = apply_filters( 'wfocu_params_localize_script_data', $data );
			wp_localize_script( 'wfocu-admin', 'wfocuParams', $data );

		}

		public function all_global_settings_fields() {

			$gateways_list = [];

			$gateways_field = array(
				'key'          => 'no_gateways',
				'type'         => 'label',
				'label'        => __( 'Enable Gateways', 'woofunnels-upstroke-one-click-upsell' ),
				'styleClasses' => [ 'wfocu_gsettings_sec_no_gateways' ],
				'hint'         => sprintf( __( "No Gateways Found. Could not find your gateway in the list?<br> <a target='_blank' href='%s'>Check enabled Payment Methods</a>", 'woofunnels-upstroke-one-click-upsell' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) )
			);

			if ( 'woofunnels_global_settings_fields' === current_action() || WFOCU_Common::is_load_admin_assets( 'main' ) || ( class_exists( 'WFFN_Core' ) && WFFN_Core()->admin->is_wffn_flex_page( 'bwf_settings' ) ) ) {
				$gateways_list = WFOCU_Core()->gateways->get_gateways_list();
			}

			if ( $gateways_list && is_array( $gateways_list ) && count( $gateways_list ) > 0 ) {
				$gateways_field = array(
					'key'          => 'gateways',
					'type'         => 'checklist',
					'label'        => __( 'Enable Gateways', 'woofunnels-upstroke-one-click-upsell' ),
					'styleClasses' => [ 'wfocu_gsettings_sec_chlist' ],
					'hint'         => sprintf( __( "Could not find your gateway in the list? <a target='_blank' href='%s' >Check enabled Payment Methods</a>", 'woofunnels-upstroke-one-click-upsell' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
					'values'       => $gateways_list,
				);
			}


			$array = array(
				'wfocu_gateways' => array(
					'title'    => __( 'Gateways', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'Gateways', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'wfocu_gateways',
					'fields'   => array(
						$gateways_field,
						array(
							'key'          => 'paypal_ref_trans',
							'type'         => 'radios',
							'label'        => __( 'PayPal Reference Transactions', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wfocu_gsettings_paypal_ref_trans' ],
							'hint'         => '<div class="bwf-brown-light-notice bwf-style-3">' . $this->get_svg_image() . '<div class="bwf-text-style-2" >' . __( 'Note: Upsells works with or without reference transactions. If you have reference transactions enabled in your PayPal account select Yes otherwise No.', 'woofunnels-upstroke-one-click-upsell' ) . '</div></div>',
							'values'       => array(
								array(
									'name'  => __( 'Yes, Reference transactions are enabled on my PayPal account', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'yes',
								),
								array(
									'name'  => __( 'No, Reference transaction are not enabled on my PayPal account.', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'no',
								),
							),

							'toggler' => array(
								'key'   => 'gateways',
								'value' => apply_filters( 'wfocu_gateways_paypal_support_non_reference_trans', array( 'ppec_paypal', 'paypal', 'paypal_express', 'paypal_pro_payflow' ) )
							),

						),
						array(
							'key'          => 'sepa_gateway_trans',
							'type'         => 'radios',
							'label'        => __( 'Enable SEPA for Mollie Upsell Transactions', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wfocu_gsettings_paypal_ref_trans' ],
							'hint'         => '<div class="bwf-brown-light-notice bwf-style-3" >' . $this->get_svg_image() . '<div class="bwf-text-style-2">' . __( 'Note: Upsells work seamlessly with or without SEPA. Choosing Yes will process upsells with SEPA Direct Debit & it should be enabled in your Mollie account. If No, buyers will be redirected to bank sites.', 'woofunnels-upstroke-one-click-upsell' ) . '</div></div>',


							'values' => array(
								array(
									'name'  => __( 'Yes, Process upsells with SEPA Direct Debit', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'yes',
								),
								array(
									'name'  => __( 'No, Process upsells without SEPA Direct Debit', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'no',
								),
							),

							'toggler' => array(
								'key'   => 'gateways',
								'value' => apply_filters( 'wfocu_gateways_sepa_support_non_gateway_trans', array(
									'mollie_wc_gateway_ideal',
									'mollie_wc_gateway_bancontact',
									'mollie_wc_gateway_sofort'
								) )
							),

						),
						array(
							'key'          => 'gateway_test',
							'type'         => 'checklist',
							'label'        => __( 'Enable Test Gateway', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wfocu_gsettings_sec_chlist' ],
							'hint'         => __( 'To quickly test upsells , create a Test Gateway. This is only visible to Admin.', 'woofunnels-upstroke-one-click-upsell' ),
							'values'       => array(
								array(
									'name'  => __( 'Test Gateway By FunnelKit', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'yes',
								),
							),
						),
					),
					'priority' => 5,
				),
				'order_statuses' => array(
					'title'    => __( 'Order Statuses', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'Order Statuses', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'order_statuses',
					'fields'   => array(
						array(
							'key'   => 'primary_order_status_title',
							'type'  => 'input',
							'label' => __( 'Custom Order Status Label', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'  => __( '<br/><strong>What is custom order status?</strong><br/> It is an intermediary state when upsell is running. Once user has accepted or rejected or time of offer expired, order status is automatically switched to successful order status. <br/><br/> <strong>Why it is needed?</strong> <br/>There can be additional processes such as sending of data to external CRMs which can trigger when order is successful. By having an intermediate order status, we wait for users to go through all the upsells. Once it is done order status automatically moves to successful order status. This ensures external plugins to process order items reliably.', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'   => 'ttl_funnel',
							'type'  => 'input',
							'label' => __( 'Forcefully Switch Order Status (in minutes)', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'  => __( '<br/><strong> Why it is needed? </strong><br/>Sometimes users may keep Offer Page open and not take a decision. Set up a realistic time in minutes after which order status will be switched to processing/completed.
This setting will determine time of Order Confirmation emails if it set to "When Upsells End". <br/> If you are not sure keep it by default to 15 mins', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'    => 'create_new_order_status_fail',
							'type'   => 'select',
							'label'  => __( 'Order Status Of Failed Order When Upsell Is Accepted', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'   => __( '<br/><strong>Why it is needed?</strong> <br/>
Sometimes it may happen that due to failure of payment gateways, the user could not be charged for upsell right away.
In such scenarios a separate order is created for your record and is created and marked as failed.', 'woofunnels-upstroke-one-click-upsell' ),
							'values' => WFOCU_Common::get_order_status_settings(),
						),
					),
					'priority' => 10,
				),
				'emails'         => array(
					'title'    => __( 'Confirmation Email', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'Confirmation Email', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'emails',
					'fields'   => array(
						array(
							'key'          => 'send_emails_label',
							'type'         => 'label',
							'label'        => __( 'When user enters the upsell, you can decide whether to send an email right away or when upsells ends.', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wfocu_gsettings_sec_note', 'bwf_gsetting_note' ],
						),
						array(
							'key'    => 'send_processing_mail_on',
							'type'   => 'radios',
							'label'  => __( 'Send Order Confirmation Email When', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'   => __( 'Note: Applicable When Upsell Are To Be Merged With Original Order', 'woofunnels-upstroke-one-click-upsell' ),
							'values' => array(
								array(
									'name'  => __( 'Upsells Start', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'start',
								),
								array(
									'name'  => __( 'Upsells End (Recommended)', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'end',
								),
							),
						),
						array(
							'key'    => 'send_processing_mail_on_no_batch',
							'type'   => 'radios',
							'label'  => __( 'Send Order Confirmation Email When', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'   => __( 'Note: Applicable When Upsell Are To Be Created Separate Orders', 'woofunnels-upstroke-one-click-upsell' ),
							'values' => array(
								array(
									'name'  => __( 'Upsells Start (Recommended)', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'start',
								),
								array(
									'name'  => __( 'Upsells End', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'end',
								),
							),
						),
						array(
							'key'    => 'send_processing_mail_on_no_batch_cancel',
							'type'   => 'radios',
							'label'  => __( 'Send Order Confirmation Email When', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'   => __( 'Note: Applicable When Primary Order Is Cancelled & Upsell Is Accepted', 'woofunnels-upstroke-one-click-upsell' ),
							'values' => array(
								array(
									'name'  => __( 'Upsells Start', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'start',
								),
								array(
									'name'  => __( 'Upsells End (Recommended)', 'woofunnels-upstroke-one-click-upsell' ),
									'value' => 'end',
								),
							),
						),
					),
					'priority' => 15,
				),
				'wfocu_scripts'  => array(
					'title'    => __( 'External Scripts', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'External Scripts', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'wfocu_scripts',
					'fields'   => array(
						array(
							'key'         => 'scripts',
							'type'        => 'textArea',
							'label'       => __( 'External Scripts', 'woofunnels-upstroke-one-click-upsell' ),
							'placeholder' => __( 'Type here...', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'         => 'scripts_head',
							'type'        => 'textArea',
							'label'       => __( 'External Scripts in head tag', 'woofunnels-upstroke-one-click-upsell' ),
							'placeholder' => __( 'Type here...', 'woofunnels-upstroke-one-click-upsell' ),
						),
					),
					'priority' => 20,
				),
				'offer_conf'     => array(
					'title'    => __( 'Offer Confirmation', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'Offer Confirmation Settings', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'offer_conf',
					'fields'   => array(
						array(
							'key'          => 'offer_header_label',
							'type'         => 'label',
							'label'        => __( 'These settings are applicable when you use custom upsell offer pages and have enabled confirmation.Need help with these settings? <a href="https://funnelkit.com/docs/upstroke/global-settings/offer-confirmation/" target="_blank">Learn More</a> ', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wfocu_gsettings_sec_note', 'wfocu_to_html', 'bwf_gsetting_note' ],
						),
						array(
							'key'   => 'offer_header_text',
							'type'  => 'input',
							'label' => __( 'Header Text', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'   => 'offer_yes_btn_text',
							'type'  => 'input',
							'label' => __( 'Acceptance Button Text', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'   => 'offer_skip_link_text',
							'type'  => 'input',
							'label' => __( 'Skip Link Text', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'          => 'offer_yes_btn_bg_cl',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Background Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ]
						),
						array(
							'key'          => 'offer_yes_btn_sh_cl',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_yes_btn_txt_cl',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Text Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_yes_btn_bg_cl_h',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Background Color (Hover)', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_yes_btn_sh_cl_h',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Shadow Color (Hover)', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_yes_btn_txt_cl_h',
							'type'         => 'input',
							'label'        => __( 'Acceptance Button Text Color (Hover)', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_no_btn_txt_cl',
							'type'         => 'input',
							'label'        => __( 'Skip Link Text Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'offer_no_btn_txt_cl_h',
							'type'         => 'input',
							'label'        => __( 'Skip Link Hover Text Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'   => 'cart_opener_text',
							'type'  => 'input',
							'label' => __( 'Re-open Badge Text', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'          => 'cart_opener_text_color',
							'type'         => 'input',
							'label'        => __( 'Re-open Badge Text Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),
						array(
							'key'          => 'cart_opener_background_color',
							'type'         => 'input',
							'label'        => __( 'Re-open Badge Background Color', 'woofunnels-upstroke-one-click-upsell' ),
							'styleClasses' => [ 'wp-color-picker' ],
						),

					),
					'priority' => 25,
				),
				'misc'           => array(
					'title'    => __( 'Advance', 'woofunnels-upstroke-one-click-upsell' ),
					'heading'  => __( 'Advance', 'woofunnels-upstroke-one-click-upsell' ),
					'slug'     => 'misc',
					'fields'   => array(
						array(
							'key'   => 'flat_shipping_label',
							'type'  => 'input',
							'label' => __( 'Shipping Label For Custom Fixed Rates', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'  => __( 'When Fixed rate shipping is applied, what would be the label of the shipping method?', 'woofunnels-upstroke-one-click-upsell' ),
						),

						array(
							'key'   => 'enable_log',
							'type'  => 'checkbox',
							'label' => __( 'Enable Logging', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'         => 'order_copy_meta_keys',
							'type'        => 'textArea',
							'label'       => __( 'Meta Keys To Copy From Primary Order', 'woofunnels-upstroke-one-click-upsell' ),
							'placeholder' => __( 'Enter keys like utm_campaign|utm_source|...', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'        => __( 'Applicable in case of new upsell order is created.', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'   => 'treat_variable_as_simple',
							'type'  => 'checkbox',
							'label' => __( 'Treat variable products like simple products', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'  => __( 'Skip offer when any variant of a variable product is sold.', 'woofunnels-upstroke-one-click-upsell' ),
						),
						array(
							'key'   => 'enable_noconflict_mode',
							'type'  => 'checkbox',
							'label' => __( 'Enable no conflict mode', 'woofunnels-upstroke-one-click-upsell' ),
							'hint'  => __( 'Remove third party plugin scripts on plugin\'s admin pages. Usually used, when some external plugin conflicts on Upstroke\'s pages.', 'woofunnels-upstroke-one-click-upsell' ),
						),

					),
					'priority' => 30,
				),
			);

			if ( class_exists( 'WFFN_Core' ) ) {
				$array['misc']['fields'] = array_values( array_filter( $array['misc']['fields'], function ( $field ) {
					return $field['key'] !== 'enable_noconflict_mode';
				} ) );
			}

			$global_settings = WFOCU_Core()->data->get_option();

			foreach ( $array as &$arr ) {
				$values = [];
				foreach ( $arr['fields'] as &$field ) {
					if ( is_array( $global_settings ) && isset( $global_settings[ $field['key'] ] ) ) {
						if ( $field['key'] === 'gateways' ) {
							$saved_gateways = $global_settings[ $field['key'] ];
							$all_gateways   = [];

							if ( ! empty( $gateways_list ) ) {
								$all_gateways = wp_list_pluck( $gateways_list, 'value' );
							}

							$saved_gateways = array_filter( $saved_gateways, function ( $gateway ) use ( $all_gateways ) {
								return in_array( $gateway, $all_gateways, true );
							} );

							$values[ $field['key'] ] = array_values( $saved_gateways );
						} else {

							$values[ $field['key'] ] = $global_settings[ $field['key'] ];
						}
					}
				}
				$arr['values'] = $values;
			}

			return $array;
		}

		public function add_global_settings_fields( $fields ) {
			$fields["upstroke"] = $this->all_global_settings_fields();

			return $fields;
		}


		public function admin_customizer_enqueue_assets() {
			if ( WFOCU_Common::is_load_admin_assets( 'customizer' ) ) {

				wp_enqueue_style( 'wfocu-customizer', $this->admin_url . '/assets/css/wfocu-customizer.css', array(), WFOCU_VERSION_DEV );
			}
		}

		public function upstroke_page() {
			if ( isset( $_GET['page'] ) && 'upstroke' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					include_once( $this->admin_path . '/view/funnel-builder-view.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				} elseif ( isset( $_GET['tab'] ) && $_GET['tab'] === 'settings' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					include_once( $this->admin_path . '/view/global-settings.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				} elseif ( isset( $_GET['tab'] ) && $_GET['tab'] === 'import' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					include_once( $this->admin_path . '/view/flex-import.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				} elseif ( isset( $_GET['tab'] ) && $_GET['tab'] === 'export' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					include_once( $this->admin_path . '/view/flex-export.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				} elseif ( ( isset( $_GET['tab'] ) && $_GET['tab'] === 'bwf_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					BWF_Admin_General_Settings::get_instance()->__callback();
				} else {
					require_once( WFOCU_PLUGIN_DIR . '/admin/includes/class-wfocu-post-table.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
					include_once( $this->admin_path . '/view/funnel-admin.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				}
			}
			if ( 'yes' === filter_input( INPUT_GET, 'activated', FILTER_UNSAFE_RAW ) ) {
				flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
			}
		}

		public function js_variables() {
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {


				$bwb_admin_setting = BWF_Admin_General_Settings::get_instance();

				$data        = array(
					'site_url'    => site_url(),
					'editor_path' => admin_url( 'post.php?post={{current_offer}}&action=edit' ),
					'offer_slug'  => $bwb_admin_setting->get_option( 'wfocu_page_base' ),
					'texts'       => array(
						'closeSwal'              => __( 'Close', 'woofunnels-upstroke-one-click-upsell' ),
						'changesSaved'           => __( 'Changes Saved', 'woofunnels-upstroke-one-click-upsell' ),
						'update_template'        => __( 'Template Updated Successfully', 'woofunnels-upstroke-one-click-upsell' ),
						'clear_template'         => __( 'Template Removed Successfully', 'woofunnels-upstroke-one-click-upsell' ),
						'product_success'        => __( 'Offer Saved Successfully', 'woofunnels-upstroke-one-click-upsell' ),
						'shortcode_copy_message' => __( 'Shortcode Copied!', 'woofunnels-upstroke-one-click-upsell' ),
						'importing'              => __( 'Importing...', 'woofunnels-upstroke-one-click-upsell' ),
						'failed_import'          => __( 'Oops! Something went wrong.', 'woofunnels-upstroke-one-click-upsell' )
					),
				);
				$funnel_post = get_post( WFOCU_Core()->funnels->get_funnel_id() );

				if ( false === is_null( $funnel_post ) ) {
					$data['id']          = WFOCU_Core()->funnels->get_funnel_id();
					$data['funnel_name'] = html_entity_decode( trim( get_the_title( $funnel_post ) ) );
					$data['funnel_desc'] = $funnel_post->post_content;
					$data['offers_link'] = admin_url( 'admin.php?page=upstroke&section=offers&edit=' . $data['id'] );
				}


				if ( $this->is_upstroke_page( 'offers' ) || $this->is_upstroke_page( 'design' ) ) {
					$get_all_template_groups = WFOCU_Core()->template_loader->get_all_groups();
					$data['edit_links']      = [];
					$data['preview_links']   = [];
					$allTemplates            = WFOCU_Core()->template_loader->get_templates();
					$data['alltemplates']    = $allTemplates;
					foreach ( $get_all_template_groups as $key => $template_group ) {
						$data['edit_links'][ $key ]      = $template_group->get_edit_link();
						$data['preview_links'][ $key ]   = $template_group->get_preview_link();
						$data['template_groups'][ $key ] = $template_group->get_nice_name();
					}
					$data['preview_links']['custom_page']   = site_url() . '?p={{custom_page_id}}';
					$data['template_groups']['custom_page'] = __( 'Custom Page', 'woofunnels-upstroke-one-click-upsell' );
					$data['custom_page_image']              = WFOCU_PLUGIN_URL . '/admin/assets/img/thumbnail-custom-page.jpg';
					$data_funnels                           = WFOCU_Core()->funnels->get_funnel_offers_admin();

					$data = array_merge( $data, $data_funnels );

				}
				$data['button_texts'] = array(
					'importingtext'    => __( 'Importing...', 'woofunnels-upstroke-one-click-upsell' ),
					're_apply'         => __( 'Re-Apply', 'woofunnels-upstroke-one-click-upsell' ),
					'apply'            => __( 'Apply', 'woofunnels-upstroke-one-click-upsell' ),
					'import'           => __( 'Import', 'woofunnels-upstroke-one-click-upsell' ),
					'import_template'  => __( 'Import This Template', 'woofunnels-upstroke-one-click-upsell' ),
					'buildFromScratch' => __( 'Start from scratch', 'woofunnels-upstroke-one-click-upsell' ),
				);


				$state                       = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
				$data['bwf_needs_indexning'] = in_array( $state, array( 0, 1, 2, 3, 6 ), true );
				$help_text                   = __( 'This setting needs indexing of past orders. Go to', 'woofunnels-upstroke-one-click-upsell' );
				$link_text                   = __( 'Tools > Index Orders', 'woofunnels-upstroke-one-click-upsell' );
				$after_text                  = __( ' and click \'Start\' to index orders', 'woofunnels-upstroke-one-click-upsell' );

				if ( 3 === $state ) {
					$help_text  = __( 'Indexing of orders is underway. This setting will work once the process completes.', 'woofunnels-upstroke-one-click-upsell' );
					$link_text  = '';
					$after_text = '';
				}

				$data['indexing_texts']      = array(
					'link'       => $link_text,
					'help_text'  => $help_text,
					'after_text' => $after_text,
				);
				$data['preset_texts']        = array(
					'success' => __( 'Preset applied successfully.', 'woofunnels-upstroke-one-click-upsell' ),
				);
				$data['add_funnel']          = array(
					'creating'    => __( 'Creating...', 'woofunnels-upstroke-one-click-upsell' ),
					'label_texts' => array(
						'funnel_name' => array(
							'label'       => __( 'Name', 'woofunnels-upstroke-one-click-upsell' ),
							'placeholder' => __( 'Enter Name', 'woofunnels-upstroke-one-click-upsell' ),
						),
						'funnel_desc' => array(

							'label'       => __( 'Description', 'woofunnels-upstroke-one-click-upsell' ),
							'placeholder' => __( 'Enter Description (Optional)', 'woofunnels-upstroke-one-click-upsell' )
						),
					)
				);
				$data['price_tooltip_texts'] = array(
					'of'           => __( 'of', 'woofunnels-upstroke-one-click-upsell' ),
					'fixed_amount' => __( '(fixed discount)', 'woofunnels-upstroke-one-click-upsell' ),
					'shipping'     => __( '(shipping)', 'woofunnels-upstroke-one-click-upsell' ),
					'dynamic_ship' => __( '(Dynamic Shipping Cost)', 'woofunnels-upstroke-one-click-upsell' ),
				);
				$data['funnel_duplicate']    = array(
					'success' => __( 'Funnel duplicated.', 'woofunnels-upstroke-one-click-upsell' ),
				);


				?>
                <script>window.wfocu = <?php echo wp_json_encode( $data ) ?>;</script>
				<?php
			}
		}

		public function is_upstroke_page( $section = '' ) {
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'upstroke' && isset( $_GET['tab'] ) && $_GET['tab'] === 'bwf_settings' && 'bwf_settings' === $section ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return true;
			}


			if ( isset( $_GET['page'] ) && $_GET['page'] === 'upstroke' && '' === $section ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return true;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] === 'upstroke' && isset( $_GET['section'] ) && $_GET['section'] === $section ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return true;
			}

			return false;
		}

		public function admin_footer_text( $footer_text ) {
			if ( WFOCU_Common::is_load_admin_assets( 'all' ) ) {
				$user = WFOCU_Core()->role->user_access( 'funnel', 'read' );
				if ( false === $user ) {
					return $footer_text;
				}
				$footer_text = __( 'Thanks for creating with FunnelKit. Need Help? <a href="https://funnelkit.com/support" target="_blank">Contact Support</a>', 'woofunnels-upstroke-one-click-upsell' );

			}


			return $footer_text;
		}

		public function update_footer( $footer_text ) {
			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				return '';
			}

			return $footer_text;
		}

		public function maybe_reset_transients( $post_id, $post = null ) {
			//Check it's not an auto save routine
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			//Perform permission checks! For example:
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( class_exists( 'WooFunnels_Transient' ) && ( is_object( $post ) && $post->post_type === WFOCU_Common::get_funnel_post_type_slug() ) ) {
				$woofunnels_transient_obj = WooFunnels_Transient::get_instance();
				$woofunnels_transient_obj->delete_all_transients( 'upstroke' );
			}

		}


		public function maybe_set_funnel_id() {


				WFOCU_Core()->funnels->set_funnel_id( wc_clean( $_GET['edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		}

		/**
		 * @hooked over `delete_post`
		 *
		 * @param $post_id
		 */
		public function clear_transients_on_delete( $post_id ) {

			$get_post_type = get_post_type( $post_id );

			if ( WFOCU_Common::get_funnel_post_type_slug() === $get_post_type ) {
				if ( class_exists( 'WooFunnels_Transient' ) ) {
					$woofunnels_transient_obj = WooFunnels_Transient::get_instance();
					$woofunnels_transient_obj->delete_all_transients( 'upstroke' );
				}
				do_action( 'wfocu_funnel_admin_deleted', $post_id );
			}

		}


		/**
		 * @hooked over `delete_post`
		 * Delete the funnel record if any from the database on permanent deletion of order.
		 *
		 * @param mixed $order_id
		 */
		public function clear_session_record_on_shop_order_delete( $order_id ) {

			if ( empty( $order_id ) || absint( 0 === $order_id ) ) {
				return;
			}

			if ( 0 < did_action( 'delete_post' ) ) {
				$get_post_type = get_post_type( $order_id );
				if ( 'shop_order' !== $get_post_type ) {
					return;
				}
			}

			$sess_id = WFOCU_Core()->session_db->get_session_id_by_order_id( $order_id );
			if ( ! empty( $sess_id ) ) {
				WFOCU_Core()->session_db->delete( $sess_id );
			}
		}

		public function maybe_activate_post() {

				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( $_GET['_wpnonce'] ), 'wfocu-post-activate' ) ) {

					$postID = filter_input( INPUT_GET, 'postid', FILTER_UNSAFE_RAW );
					if ( $postID ) {
						wp_update_post( array(
							'ID'          => $postID,
							'post_status' => 'publish',
						) );
						wp_safe_redirect( admin_url( 'admin.php?page=upstroke' ) );
						exit;
					}
				} else {
					die( esc_attr__( 'Unable to Activate', 'woofunnels-upstroke-one-click-upsell' ) );
				}

		}

		public function maybe_deactivate_post() {

				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( $_GET['_wpnonce'] ), 'wfocu-post-deactivate' ) ) {

					$postID = filter_input( INPUT_GET, 'postid', FILTER_UNSAFE_RAW );
					if ( $postID ) {
						wp_update_post( array(
							'ID'          => $postID,
							'post_status' => WFOCU_SLUG . '-disabled',
						) );

						wp_safe_redirect( admin_url( 'admin.php?page=upstroke' ) );
						exit;
					}
				} else {
					die( esc_attr__( 'Unable to Deactivate', 'woofunnels-upstroke-one-click-upsell' ) );
				}

		}


		public function maybe_print_mergetag_helpbox() {


			if ( false === WFOCU_Common::is_load_admin_assets( 'customizer' ) ) {
				return;
			}
			$offer_data = WFOCU_Core()->offers->get_offer_meta( WFOCU_Core()->customizer->offer_id );
			?>

            <div class='' id="wfocu_shortcode_help_box" style="display: none;">

                <h3><?php esc_attr_e( 'Merge Tags', 'woofunnels-upstroke-one-click-upsell' ); ?></h3>
                <div style="font-size: 1.1em; margin: 5px;"><?php esc_attr_e( 'Here are are set of Merge Tags that can be used on this page.', 'woofunnels-upstroke-one-click-upsell' ); ?> </i> </div>
				<?php foreach ( $offer_data->products as $hash => $product_id ) { ?>
                    <h4><?php esc_attr_e( sprintf( 'Product: %1$s', wc_get_product( $product_id )->get_title() ), 'woofunnels-upstroke-one-click-upsell' ); ?></h4>

                    <table class="table widefat">
                        <thead>
                        <tr>
                            <td><?php esc_attr_e( 'Title', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                            <td style="width: 70%;"><?php esc_attr_e( 'Merge Tags', 'woofunnels-upstroke-one-click-upsell' ); ?></td>

                        </tr>
                        </thead>
                        <tbody>

                        <tr>
                            <td>
								<?php esc_attr_e( 'Product Offer Price', 'woofunnels-upstroke-one-click-upsell' ); ?>


                            </td>
                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()" value='<?php ( printf( '{{product_offer_price key="%s"}}', esc_attr( $hash ) ) ); ?>'/>
                            </td>

                        </tr>
                        <tr>
                            <td>
								<?php esc_attr_e( 'Product Regular Price', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            </td>
                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_regular_price key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>
                        <tr>
                            <td>

								<?php esc_attr_e( ' Product Price HTML', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            </td>
                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_price_full key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>

                        <tr>
                            <td>
								<?php esc_attr_e( 'Product Offer Save Value', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            </td>
                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_save_value key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>
                        <tr>
                            <td>
								<?php esc_attr_e( ' Product Offer Save Percentage', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            </td>

                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_save_percentage key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>

                        <tr>
                            <td>
								<?php esc_attr_e( ' Product Single Unit Price', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            </td>

                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_single_unit_price key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>

                        <tr>
                            <td>
								<?php esc_attr_e( 'Product Offer Save Value & Percentage', 'woofunnels-upstroke-one-click-upsell' ); ?>

                            </td>
                            <td>
                                <input type="text" style="width: 75%;" readonly onClick="this.select()"
                                       value='<?php printf( '{{product_savings key="%s"}}', esc_attr( $hash ) ); ?>'/>
                            </td>

                        </tr>


                        </tbody>


                    </table>
				<?php } ?>
                <br/>

                <h3>Order Merge Tags</h3>
                <table class="table widefat">
                    <thead>
                    <tr>
                        <td width="300">Name</td>
                        <td>Syntax</td>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ( WFOCU_Dynamic_Merge_Tags::get_all_tags() as $tag ) : ?>
                        <tr>
                            <td>
								<?php echo esc_html( $tag['name'] ); ?>
                            </td>
                            <td>
                                <input type="text" style="width: 75%;" onClick="this.select()" readonly
                                       value='<?php echo '{{' . esc_html( $tag['tag'] ) . '}}'; ?>'/>
								<?php
								if ( isset( $tag['desc'] ) && $tag['desc'] !== '' ) {
									echo '<p>' . wp_kses_post( $tag['desc'] ) . '</p>';
								}
								?>
                            </td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>
                <br/>

                <h3>Other Merge Tags</h3>
                <table class="table widefat">
                    <thead>
                    <tr>
                        <td width="300">Name</td>
                        <td>Syntax</td>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ( WFOCU_Dynamic_Merge_Tags::get_all_other_tags() as $tag ) : ?>
                        <tr>
                            <td>
								<?php echo esc_attr( $tag['name'] ); ?>
                            </td>
                            <td>
                                <input type="text" style="width: 75%;" onClick="this.select()" readonly
                                       value='<?php echo '{{' . esc_html( $tag['tag'] ) . '}}'; ?>'/>
								<?php
								if ( isset( $tag['desc'] ) && $tag['desc'] !== '' ) {
									echo '<p>' . wp_kses_post( $tag['desc'] ) . '</p>';
								}
								?>
                            </td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

			<?php
		}

		/**
		 * Hooked over 'plugin_action_links_{PLUGIN_BASENAME}' WordPress hook to add deactivate popup support
		 *
		 * @param array $links array of existing links
		 *
		 * @return array modified array
		 */
		public function plugin_actions( $links ) {
			if ( isset( $links['deactivate'] ) ) {
				$links['deactivate'] .= '<i class="woofunnels-slug" data-slug="' . WFOCU_PLUGIN_BASENAME . '"></i>';
			}

			return $links;
		}

		public function maybe_handle_http_referer() {

				wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'offer_state' ), wp_unslash( wc_clean( $_SERVER['REQUEST_URI'] ) ) ) );
				exit;

		}

		public function tooltip( $text ) {
			?>
            <span class="wfocu-help"><i class="icon"></i><div class="helpText"><?php echo( $text ); ?></div></span> <?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
		}

		public function hide_test_gateway_from_admin_list() {
			?>
            <style>
                table.wc_gateways tr[data-gateway_id="wfocu_test"] {
                    display: none !important;
                }
            </style>
			<?php
		}


		public function check_db_version() {

				//needs checking
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'db/tables.php';
				$tables = new WFOCU_DB_Tables();

				$tables->add_if_needed();


		}

		public function toolbar_link_to_xlplugins( $wp_admin_bar ) {
			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				if ( is_order_received_page() ) {
					global $wp;
					$args = array(
						'id'    => 'wfocu_admin_vorder',
						'title' => __( 'View Order', 'woofunnels-upstroke-one-click-upsell' ),
						'href'  => admin_url( 'post.php?post=' . $wp->query_vars['order-received'] . '&action=edit' ),

					);
					$wp_admin_bar->add_node( $args );
				}

			}
			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				$args = array(
					'id'    => 'wfocu_admin_logs',
					'title' => __( 'Logs', 'woofunnels-upstroke-one-click-upsell' ),
					'href'  => admin_url( 'admin.php?page=wc-status&tab=logs' ),
					'meta'  => array( 'class' => 'wfocu_admin_logs' ),
				);
				$wp_admin_bar->add_node( $args );

				$wp_admin_bar->add_node( array(
					'parent' => 'wfocu_admin_logs',
					'id'     => 'wfocu_wc_admin_logs',
					'title'  => __( 'WC Logs', 'woofunnels-upstroke-one-click-upsell' ),
					'href'   => admin_url( 'admin.php?page=wc-status&tab=logs' ),
					'meta'   => array( 'class' => 'wfocu_admin_logs' ),
				) );
				$wp_admin_bar->add_node( array(
					'parent' => 'wfocu_admin_logs',
					'id'     => 'wfocu_bwf_admin_logs',
					'title'  => __( 'BWF Logs', 'woofunnels-upstroke-one-click-upsell' ),
					'href'   => admin_url( 'admin.php?page=woofunnels&tab=logs' ),
					'meta'   => array( 'class' => 'wfocu_admin_logs' ),
				) );


				$arr = WC_Log_Handler_File::get_log_files();

				if ( count( $arr ) > 0 ) {
					$data = end( $arr );
					$wp_admin_bar->add_node( array(
						'parent' => 'wfocu_admin_logs',
						'id'     => 'wfocu_wfocu_admin_logs',
						'title'  => __( 'FunnelKit Upsell Log', 'woofunnels-upstroke-one-click-upsell' ),
						'href'   => admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . $data ),
						'meta'   => array( 'class' => 'wfocu_admin_logs' ),
					) );
				}
			}

			return $wp_admin_bar;

		}


		public function set_wc_payment_gateway_column( $header ) {

			$header_new = array_slice( $header, 0, count( $header ) - 1, true ) + array( 'wfocu' => __( 'Upsell Allowed', 'woocommerce-subscriptions' ) ) + // Ideally, we could add a link to the docs here, but the title is passed through esc_html()
			              array_slice( $header, count( $header ) - 1, count( $header ) - ( count( $header ) - 1 ), true );

			return $header_new;
		}

		public function wc_payment_gateway_column_content( $gateway ) {
			$supported_gateways = WFOCU_Core()->gateways->get_supported_gateways();
			echo '<td class="renewals">';
			$status_html = '-';
			if ( ( is_array( $supported_gateways ) && array_key_exists( $gateway->id, $supported_gateways ) ) ) {
				$status_html = '<span class="status-enabled tips" data-tip="' . esc_attr__( 'Supports UpSell payments with the FunnelKit\'s One Click Upsell.', 'woocommerce-subscriptions' ) . '">' . esc_html__( 'Yes', 'woocommerce-subscriptions' ) . '</span>';
			}

			$allowed_html                     = wp_kses_allowed_html( 'post' );
			$allowed_html['span']['data-tip'] = true;

			/**
			 * Automatic Renewal Payments Support Status HTML Filter.
			 *
			 * @param string $status_html
			 * @param \WC_Payment_Gateway $gateway
			 *
			 * @since 2.0
			 *
			 */
			echo wp_kses( apply_filters( 'woocommerce_payment_gateways_upstroke_support_status_html', $status_html, $gateway ), $allowed_html );

			echo '</td>';
		}

		/**
		 * Initiate WFOCU_Background_Updater class
		 * @see maybe_update_database_update()
		 */
		public function init_background_updater() {

			if ( class_exists( 'WFOCU_Background_Updater' ) ) {
				$this->updater = new WFOCU_Background_Updater();
			}

		}


		/**
		 * @hooked over `admin_head`
		 * This method takes care of database updating process.
		 * Checks whether there is a need to update the database
		 * Iterates over define callbacks and passes it to background updater class
		 */
		public function maybe_update_database_update() {

			if ( is_null( $this->updater ) ) {

				/**
				 * Update the option as tables are updated.
				 */
				update_option( '_wfocu_db_version', WFOCU_DB_VERSION, true );

				return;
			}
			$task_list          = array(
				'3.0' => array( 'wfocu_update_fullwidth_page_template' ),
				'3.3' => array( 'wfocu_update_general_setting_fields' ),
				'3.5' => array( 'wfocu_update_general_setting_fields_3_5' ),
				'3.7' => array( 'wfocu_update_delete_duplicate_comments_3_6' ),
				'3.8' => array( 'wfocu_update_sepa_trans_key_3_8' ),
				'3.9' => array( 'wfocu_set_default_value_in_autoload_option' ),
			);
			$current_db_version = get_option( '_wfocu_db_version', '0.0.0' );
			$update_queued      = false;

			foreach ( $task_list as $version => $tasks ) {
				if ( version_compare( $current_db_version, $version, '<' ) ) {
					foreach ( $tasks as $update_callback ) {

						$this->updater->push_to_queue( $update_callback );
						$update_queued = true;
					}
				}
			}

			if ( $update_queued ) {

				$this->updater->save()->dispatch();
			}

			update_option( '_wfocu_db_version', WFOCU_DB_VERSION, true );

		}

		public function maybe_update_upstroke_version_in_option() {


				update_option( '_wfocu_plugin_version', WFOCU_VERSION, true );
				update_option( '_wfocu_plugin_last_updated', time(), true );


		}


		/**
		 * Defines scripts needed for "no conflict mode".
		 *
		 * @since  Unknown
		 * @access public
		 * @global $wp_scripts
		 *
		 * @uses WFOCU_Admin::no_conflict_mode()
		 */
		public function no_conflict_mode_script() {
			if ( ! apply_filters( 'wfocu_no_conflict_mode', true ) ) {
				return;
			}

			global $wp_scripts;

			$wp_required_scripts    = array( 'admin-bar', 'common', 'jquery-color', 'utils', 'svg-painter' );
			$wfocu_required_scripts = apply_filters( 'wfocu_no_conflict_scripts', array(
				'common'       => array(
					'jquery-ui-sortable',
					'jquery-ui-sortable',
					'wfocu-admin-ajax',
					'wfocu-admin',
					'wc-backbone-modal',
					'accounting',
					'wfocu-izimodal',
					'wfocu-admin-builder',
					'sack'
				),
				'settings'     => array(
					'wfocu-vue-multiselect',
					'wfocu-vuejs',
					'wfocu-vue-vfg',
					'updates',
				),
				'rules'        => array( 'wfocu-chosen', 'wfocu-ajax-chosen', 'jquery-masked-input', 'wfocu-admin-app' ),
				'offers'       => array(
					'wfocu-vue-multiselect',
					'wfocu-vuejs',
					'wfocu-vue-vfg',
					'wfocu_autoship_admin_script',
					'wfocu_dynamic_shipping_script',
					'wfocu_subscription_admin_script'
				),
				'design'       => array(
					'wfocu-vue-multiselect',
					'wfocu-vuejs',
					'wfocu-vue-vfg',
				),
				'bwf_settings' => array( 'bwf-admin-settings' ),
			) );
			$this->no_conflict_mode( $wp_scripts, $wp_required_scripts, $wfocu_required_scripts, 'scripts' );
		}

		/**
		 * Defines styles needed for "no conflict mode"
		 *
		 * @since  Unknown
		 * @access public
		 * @global $wp_styles
		 *
		 * @uses   WFOCU_Admin::no_conflict_mode()
		 */
		public function no_conflict_mode_style() {

			global $wp_styles;
			$wp_required_styles    = array( 'common', 'admin-bar', 'colors', 'ie', 'wp-admin', 'editor-style' );
			$wfocu_required_styles = apply_filters( 'wfocu_no_conflict_styles', array(
				'common'   => array( 'wfocu-funnel-bg', 'woofunnels-admin-font', 'wfocu-izimodal', 'wfocu-modal', 'wfocu-admin', 'woocommerce_admin_styles', 'bwf-admin-font', 'bwf-admin-header' ),
				'settings' => array(
					'wfocu-modal',
					'wfocu-vue-multiselect',
				),
				'rules'    => array(
					'wfocu-chosen-app',
					'wfocu-admin-app',
					'wfocu-modal',
					'wfocu-vue-multiselect',
				),
				'offers'   => array(
					'wfocu-vue-multiselect',
				),
				'design'   => array(
					'wfocu-vue-multiselect',
				),
			) );

			$this->no_conflict_mode( $wp_styles, $wp_required_styles, $wfocu_required_styles, 'styles' );
		}

		/**
		 * Runs "no conflict mode".
		 *
		 * @param WP_Scripts $wp_objects WP_Scripts object.
		 * @param array $wp_required_objects Scripts required by WordPress Core.
		 * @param array $wfocu_required_objects Scripts required by WooFunnels Forms.
		 * @param string $type Determines if scripts or styles are being run through the function.
		 *
		 * @since   Unknown
		 * @access  private
		 *
		 * @used-by WFOCU_Admin::no_conflict_mode_style()
		 * @used-by WFOCU_Admin::no_conflict_mode_style()
		 *
		 */
		public function no_conflict_mode( &$wp_objects, $wp_required_objects, $wfocu_required_objects, $type = 'scripts' ) {


			$current_page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
			if ( is_null( $current_page ) || empty( $current_page ) ) {
				return;
			}

			$current_page = trim( strtolower( $current_page ) );

			if ( 'upstroke' !== $current_page ) {
				return;
			}

			$section    = filter_input( INPUT_GET, 'section', FILTER_UNSAFE_RAW );
			$tab        = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
			$is_section = isset( $wfocu_required_objects[ $section ] ) ? $wfocu_required_objects[ $section ] : false;
			$is_listing = ( ! $is_section && is_null( $tab ) ) ? true : false;
			//disable no-conflict if $page_objects is false
			if ( $is_section === false && $is_listing === false ) {
				return;
			}


			$enable_no_conflict_mode = WFOCU_Core()->data->get_option( 'enable_noconflict_mode' );
			if ( false === $enable_no_conflict_mode || empty( $enable_no_conflict_mode ) ) {
				return;
			}

			if ( ! is_array( $is_section ) ) {
				$is_section = array();
			}

			//merging wp scripts with gravity forms scripts
			$required_objects = array_merge( $wp_required_objects, $wfocu_required_objects['common'], $is_section );

			//allowing addons or other products to change the list of no conflict scripts
			$required_objects = apply_filters( "wfocu_noconflict_{$type}", $required_objects );


			$queue = array();
			foreach ( $wp_objects->queue as $object ) {
				if ( in_array( $object, $required_objects, true ) ) {
					$queue[] = $object;
				}
			}
			$wp_objects->queue = $queue;

			$required_objects = $this->add_script_dependencies( $wp_objects->registered, $required_objects );

			//unregistering scripts
			$registered = array();
			foreach ( $wp_objects->registered as $script_name => $script_registration ) {
				if ( in_array( $script_name, $required_objects, true ) ) {
					$registered[ $script_name ] = $script_registration;
				}
			}

			$wp_objects->registered = $registered;
		}

		/**
		 * Adds script dependencies needed.
		 *
		 * @param array $registered Registered scripts.
		 * @param array $scripts Required scripts.
		 *
		 * @return array $scripts Scripts including dependencies.
		 * @since   Unknown
		 *
		 * @used-by WFOCU_Admin::no_conflict_mode()
		 *
		 */
		public function add_script_dependencies( $registered, $scripts ) {

			//gets all dependent scripts linked to the $scripts array passed
			do {
				$dependents = array();
				foreach ( $scripts as $script ) {
					$deps = isset( $registered[ $script ] ) && is_array( $registered[ $script ]->deps ) ? $registered[ $script ]->deps : array();
					foreach ( $deps as $dep ) {
						if ( ! in_array( $dep, $scripts, true ) && ! in_array( $dep, $dependents, true ) ) {
							$dependents[] = $dep;
						}
					}
				}
				$scripts = array_merge( $scripts, $dependents );
			} while ( ! empty( $dependents ) );

			return $scripts;
		}

		public function get_selected_nav_class( $nav ) {
			if ( ! isset( $_GET['tab'] ) && 'upstroke' === $_GET['page'] ) {
				return 'nav-tab-active';
			}

			return '';
		}

		public function get_selected_nav_class_global( $nav ) {
			if ( isset( $_GET['tab'] ) && 'upstroke' === $_GET['page'] && 'settings' === $_GET['tab'] ) {
				return 'nav-tab-active';
			}

			return '';
		}

		public function get_selected_nav_class_tools( $nav ) {

			return '';
		}


		/**
		 * Check if its our builder page and registered required nodes to prepare a breadcrumb
		 */
		public function maybe_register_breadcrumbs() {

			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

				/**
				 * Only register primary node if not added yet
				 */
				if ( empty( BWF_Admin_Breadcrumbs::$nodes ) ) {
					BWF_Admin_Breadcrumbs::register_node( array( 'text' => __( 'One Click Upsells' ), 'link' => admin_url( 'admin.php?page=upstroke' ) ) );
				}
				$funnel_id = WFOCU_Core()->funnels->get_funnel_id();
				$title     = ! empty( get_the_title( $funnel_id ) ) ? get_the_title( $funnel_id ) : __( '(no title)', 'woofunnels-upstroke-one-click-upsell' );
				BWF_Admin_Breadcrumbs::register_node( array( 'text' => $title, 'link' => '' ) );
			}
		}

		public function get_shortcodes_list() {
			$list = array(
				array(
					'label' => __( 'Product Offer Accept Link', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => site_url() . '?wfocu-accept-link=yes',
						'multi'  => site_url() . '?wfocu-accept-link=yes&key=%s',
					),
				),
				array(
					'label' => __( 'Product Offer Skip Link', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => site_url() . '?wfocu-reject-link=yes',
						'multi'  => site_url() . '?wfocu-reject-link=yes',
					),
				),
				array(
					'label' => __( 'Product Offer Variation Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_variation_selector_form]',
						'multi'  => '[wfocu_variation_selector_form key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_qty_selector]',
						'multi'  => '[wfocu_qty_selector key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Image Slider', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_image_slider]',
						'multi'  => '[wfocu_product_image_slider key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Offer Price', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_offer_price]',
						'multi'  => '[wfocu_product_offer_price key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_title]',
						'multi'  => '[wfocu_product_title key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Short Description', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_short_description]',
						'multi'  => '[wfocu_product_short_description key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Regular Price', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_regular_price]',
						'multi'  => '[wfocu_product_regular_price key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Original Sale Price', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_original_sale_price]',
						'multi'  => '[wfocu_product_original_sale_price key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Price HTML', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_price_full]',
						'multi'  => '[wfocu_product_price_full key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Offer Save Value', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_save_value]',
						'multi'  => '[wfocu_product_save_value key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Offer Save Percentage', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_save_percentage]',
						'multi'  => '[wfocu_product_save_percentage key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Offer Save value & Percentage', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_savings]',
						'multi'  => '[wfocu_product_savings key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Single Unit Price', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_product_single_unit_price]',
						'multi'  => '[wfocu_product_single_unit_price key="%s"]'
					),
				),
				array(
					'label' => __( 'Product Offer Accept Link HTML', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_yes_link]' . __( 'Add to my Order', 'woofunnels-upstroke-one-click-upsell' ) . '[/wfocu_yes_link]',
						'multi'  => '[wfocu_yes_link key="%s"]' . __( 'Add to my Order', 'woofunnels-upstroke-one-click-upsell' ) . '[/wfocu_yes_link]'
					),
				),
				array(
					'label' => __( 'Product Offer Skip Link HTML', 'woofunnels-upstroke-one-click-upsell' ),
					'code'  => array(
						'single' => '[wfocu_no_link]' . __( 'No, thanks', 'woofunnels-upstroke-one-click-upsell' ) . '[/wfocu_no_link]',
						'multi'  => '[wfocu_no_link]' . __( 'No, thanks', 'woofunnels-upstroke-one-click-upsell' ) . '[/wfocu_no_link]'
					),
				),
			);

			return apply_filters( 'wfocu_shortcode_list', $list );
		}

		/**
		 * Adding metabox on editor page for 'Back to funnel' link.
		 */
		public function add_meta_boxes_for_back_button() {
			$post_type = WFOCU_Common::get_offer_post_type_slug();
			add_meta_box( 'wfocu-edit-offer', __( 'Offer Page', 'woofunnels-upstroke-one-click-upsell' ), [ $this, 'render_funnel_link_meta_box' ], $post_type, 'side', 'default' );
		}

		public function render_funnel_link_meta_box() {
			return;


		}

		public function add_back_button() {
			global $post;
			if ( ! is_object( $post ) || ! $post instanceof WP_Post ) {
				return;
			}
			$offer_id = ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) ? $post->ID : 0;
			if ( $offer_id > 0 ) {
				$upsell_id = get_post_meta( $offer_id, '_funnel_id', true );
				$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );

				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $funnel_id );
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
						'page' => 'bwf',
						'path' => "/funnel-offer/" . $offer_id . "/design",
					], admin_url( 'admin.php' ) ) );
				} else {
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
						'page'    => 'upstroke',
						'edit'    => $upsell_id,
						'section' => 'design',
					], admin_url( 'admin.php' ) ) );
				}

				if ( use_block_editor_for_post_type( WFOCU_Common::get_offer_post_type_slug() ) ) {
					add_action( 'admin_footer', array( $this, 'render_back_to_funnel_script_for_block_editor' ) );
				} else {
					?>
                    <div id="wf_funnel-switch-mode">
                        <a id="wf_funnel-back-button" class="button button-default button-large" href="<?php echo esc_url( $edit_link ); ?>">
							<?php esc_html_e( '&#8592; Back to Funnel Edit Page', 'woofunnels-upstroke-one-click-upsell' ); ?>
                        </a>
                    </div>
                    <script>
                        window.addEventListener('load', function () {
                            (function (window, wp) {
                                var link = document.querySelector('a.components-button.edit-post-fullscreen-mode-close');
                                if (link) {
                                    link.setAttribute('href', "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) );//phpcs:ignore ?>")
                                }

                            })(window, wp)
                        });

                    </script>
					<?php

				}
			}

		}

		public function render_back_to_funnel_script_for_block_editor() {
			global $post;
			if ( ! is_object( $post ) || ! $post instanceof WP_Post ) {
				return;
			}
			$offer_id = ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) ? $post->ID : 0;
			if ( $offer_id > 0 ) {
				$upsell_id = get_post_meta( $offer_id, '_funnel_id', true );
				$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );

				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $funnel_id );
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
						'page' => 'bwf',
						'path' => "/funnel-offer/" . $offer_id . "/design",
					], admin_url( 'admin.php' ) ) );
				} else {
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
						'page'    => 'upstroke',
						'edit'    => $upsell_id,
						'section' => 'design',
					], admin_url( 'admin.php' ) ) );
				}
				?>
                <script id="wf_funnel-back-button-template" type="text/html">
                    <div id="wf_funnel-switch-mode">
                        <a id="wf_funnel-back-button" class="button button-default button-large" href="<?php echo esc_url( $edit_link ); ?>">
							<?php echo esc_html_e( '&#8592; Back to Funnel Edit Page', 'woofunnels-upstroke-one-click-upsell' ); ?>
                        </a>
                    </div>
                </script>
                <script>
                    window.addEventListener('load', function () {


                        (function (window, wp) {

                            const {Toolbar, ToolbarButton} = wp.components;

                            var link_button = wp.element.createElement(
                                ToolbarButton,
                                {
                                    variant: 'secondary',
                                    href: "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) ); ?>",
                                    id: 'wf_funnel-back-button',
                                    className: 'button is-secondary',
                                    style: {
                                        display: 'flex',
                                        height: '33px'
                                    },
                                    text: "<?php esc_html_e( '← Back to Funnel Edit Page', 'woofunnels-upstroke-one-click-upsell' ); ?>",
                                    label: "<?php esc_html_e( 'Back to Funnel Edit Page', 'woofunnels-upstroke-one-click-upsell' ); ?>"
                                }
                            );
                            var linkWrapper = '<div id="wf_funnel-switch-mode"></div>';

                            // check if gutenberg's editor root element is present.
                            var editorEl = document.getElementById('editor');
                            if (!editorEl) { // do nothing if there's no gutenberg root element on page.
                                return;
                            }

                            var unsubscribe = wp.data.subscribe(function () {
                                setTimeout(function () {
                                    if (!document.getElementById('wf_funnel-switch-mode')) {
                                        var toolbalEl = editorEl.querySelector('.editor-header__toolbar .edit-post-header-toolbar') ?? editorEl.querySelector('.edit-post-header__toolbar .edit-post-header-toolbar');
                                        if (toolbalEl instanceof HTMLElement) {
                                            toolbalEl.insertAdjacentHTML('beforeend', linkWrapper);
                                            setTimeout(() => {
                                                wp.element.render(link_button, document.getElementById('wf_funnel-switch-mode'));
                                            }, 1);
                                        }
                                    }
                                }, 1)
                            });

                            var link = document.querySelector('a.components-button.edit-post-fullscreen-mode-close');
                            if (link) {
                                link.setAttribute('href', "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) );//phpcs:ignore ?>")
                            }

                        })(window, wp);
                    });

                </script>
				<?php
			}
		}

		public function maybe_add_timeline_files() {


			/**
			 * Apply a condition to handle activation of old reporting plugin that could break activation
			 */
			if ( 'activate' === filter_input( INPUT_GET, 'action', FILTER_UNSAFE_RAW ) && 'woofunnels-upstroke-reports/woofunnels-upstroke-reports.php' === filter_input( INPUT_GET, 'plugin', FILTER_UNSAFE_RAW ) ) {
				return;
			}

			/**
			 * Add timeline file and hooks
			 */
			require __DIR__ . '/includes/class-wfocu-upstroke-timeline.php';

			/**
			 * IF reporting plugin
			 */
			if ( is_callable( [ 'WFOCU_Admin_Reports', 'wfocu_add_licence_support_file' ] ) ) {
				$wfocu_upstroke_timeline = WFOCU_Upstroke_Timeline::instance( 'woofunnels-upstroke-one-click-upsell' );
			} else {
				$wfocu_upstroke_timeline = WFOCU_Upstroke_Timeline::instance();
			}


			add_action( 'add_meta_boxes', array( $wfocu_upstroke_timeline, 'wfocu_register_upstroke_reports_meta_boxes' ) );

		}

		public function register_admin_menu() {
			$user = WFOCU_Core()->role->user_access( 'menu', 'read' );
			if ( false !== $user ) {
				add_submenu_page( 'woofunnels', __( 'One Click Upsells', 'woofunnels-upstroke-one-click-upsell' ), __( 'One Click Upsells', 'woofunnels-upstroke-one-click-upsell' ), $user, 'upstroke', array(
					WFOCU_Core()->admin,
					'upstroke_page',
				) );
			}
		}

		/**
		 * @param $existing_args
		 * Exclude upsells create by funnel builder or AB testing
		 *
		 * @return mixed
		 */
		public function exclude_from_query( $existing_args ) {


			$existing_args['meta_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bwf_in_funnel',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				array(
					'key'     => '_bwf_ab_variation_of',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				)
			);


			return $existing_args;
		}

		public function maybe_show_wizard() {

			if ( isset( $_GET['tab'] ) && strpos( wc_clean( $_GET['tab'] ), 'wizard' ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			if ( true === apply_filters( 'wfocu_override_wizard', false ) ) {
				return;
			}

			if ( WFOCU_Core()->support->is_license_present() === false ) {
				wp_redirect( admin_url( 'admin.php?page=woofunnels&tab=' . WFOCU_SLUG . '-wizard' ) );
				exit;
			}

		}

		/**
		 * this function use for display upsell actual charge price for this order
		 *
		 * @param $order WC_Order
		 */
		public function show_advanced_field_order( $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$primary_id = $order->get_meta( '_wfocu_primary_order' );

			if ( empty( $primary_id ) ) {
				return;
			}


			/**
			 * Try to get if any upstroke order is created for this order as parent
			 */
			$results = WFOCU_Core()->track->query_results( array(
				'data'         => array(),
				'where'        => array(
					array(
						'key'      => 'session.order_id',
						'value'    => WFOCU_WC_Compatibility::get_order_id( $primary_id ),
						'operator' => '=',
					),
					array(
						'key'      => 'events.action_type_id',
						'value'    => 4,
						'operator' => '=',
					),
				),
				'where_meta'   => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_new_order',
						'meta_value' => $order->get_id(),
						'operator'   => '=',
					),
					array(
						'type'       => 'meta',
						'meta_key'   => '_is_diff_charged',
						'meta_value' => 'yes',
						'operator'   => '=',
					),
				),
				'session_join' => true,
				'order_by'     => 'events.id DESC',
				'query_type'   => 'get_results',
			) );

			$primary_order = wc_get_order( $primary_id );

			if ( empty( $results ) ) {
				if ( ! empty( $primary_order ) && ! empty( $primary_order->get_edit_order_url() ) ) { ?>
                    <div style="clear: both;"></div>
                    <div style="margin-top:15px" class="wfocu_order_backend_field_container">
                        <p style="padding:0px; margin:0px 0px 5px 0px"><?php echo esc_html__( 'Parent order: ', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            <a href="<?php echo esc_url( $primary_order->get_edit_order_url() ); ?>">
								<?php
								echo sprintf( esc_html__( '#%1$s', 'woofunnels-upstroke-one-click-upsell' ), esc_html( $primary_id ) );
								?>
                            </a>
                        </p>
                    </div>
					<?php
				}
			} else {
				?>
                <div style="clear: both;"></div>
                <div style="margin-top:15px" class="wfocu_order_backend_field_container">
					<?php if ( ! empty( $primary_order ) && ! empty( $primary_order->get_edit_order_url() ) ) { ?>
                        <p style="padding:0px; margin:0px 0px 15px 0px"><?php echo esc_html__( 'Parent order: ', 'woofunnels-upstroke-one-click-upsell' ); ?>
                            <a href="<?php echo esc_url( $primary_order->get_edit_order_url() ); ?>">
								<?php
								echo sprintf( esc_html__( '#%1$s', 'woofunnels-upstroke-one-click-upsell' ), esc_html( $primary_id ) );
								?>
                            </a>
                        </p>
					<?php } ?>
                    <p><i><b><?php _e( 'Upsell Amount Charged', 'woofunnels-upstroke-one-click-upsell' ); ?>:<?php echo wc_price( $results[0]->value ); ?></b><br>
							<?php _e( 'This order has charged the difference, and the same amount will be considered while refunding this order. You need to refund the rest of the amount from the parent order.', 'woofunnels-upstroke-one-click-upsell' ); ?>
                        </i></p>
                </div>
				<?php
			}
		}


		/**
		 * in Fb v3.0.0 we have modified the behaviour of the offer data, so we need to check if the offer data has the old discount type and quantity, then we need to migrate it to the new one
		 *
		 * @param $offer_data
		 * @param $offer_id
		 *
		 * @return mixed
		 */
		public function maybe_check_offer_data_for_qty_change( $offer_data, $offer_id ) {
			foreach ( $offer_data->products as $key => $prod ) {
				if ( isset( $offer_data->fields->{$key} ) && in_array( $offer_data->fields->{$key}->discount_type, [
						'fixed_on_sale',
						'fixed_on_reg'
					], true ) && 1 < absint( $offer_data->fields->{$key}->quantity ) ) {

					$pro = wc_get_product( $prod );

					if ( $pro instanceof WC_Product && $pro->is_type( 'variable' ) ) {
						continue;
					}

					$price = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$key}, true, $offer_data );

					/**
					 * Coming here mean that we need to migrate the old discount setup to the correct one
					 */
					if ( absint( $price ) === 0 ) {
						$offer_data->fields->{$key}->discount_amount = $offer_data->fields->{$key}->discount_amount / $offer_data->fields->{$key}->quantity;

						WFOCU_Common::update_offer( $offer_id, $offer_data );

					}
				}
			}

			return $offer_data;
		}

		function clear_delete_duplicate_comments_schedule_function() {
			// Clear the scheduled action
			wp_clear_scheduled_hook( 'wfocu_fkwcs_delete_duplicate_comments' );


			WFOCU_Core()->log->log( 'Recurring action "delete_duplicate_comments" cleared due to an error or no more duplicate comments.' );

		}

		function delete_duplicate_comments_function() {
			global $wpdb;

			try {
				// Corrected delete query with a subquery to support LIMIT
				$delete_query = "
            DELETE FROM {$wpdb->comments}
            WHERE comment_ID IN (
                SELECT comment_ID
                FROM (
                    SELECT wc.comment_ID
                    FROM {$wpdb->comments} wc
                    JOIN (
                        SELECT comment_post_ID, MIN(comment_ID) AS retained_comment_ID
                        FROM {$wpdb->comments}
                        WHERE comment_content LIKE '%Order charge successful in Stripe%'
                        GROUP BY comment_post_ID
                        HAVING COUNT(*) > 1
                    ) AS subquery
                    ON wc.comment_post_ID = subquery.comment_post_ID
                    WHERE wc.comment_ID != subquery.retained_comment_ID
                    AND wc.comment_content LIKE '%Order charge successful in Stripe%'
                    LIMIT 100
                ) AS temp_table
            )
        ";

				// Execute the delete query
				$deleted_rows = $wpdb->query( $delete_query );  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				// Log the number of deleted rows
				WFOCU_Core()->log->log( "Deleted $deleted_rows duplicate comments from wp_comments table." );

				// Check if more rows exist to delete
				$select_query = "
            SELECT wc.comment_ID
            FROM {$wpdb->comments} wc
            JOIN (
                SELECT comment_post_ID, MIN(comment_ID) AS retained_comment_ID
                FROM {$wpdb->comments}
                WHERE comment_content LIKE '%Order charge successful in Stripe%'
                GROUP BY comment_post_ID
                HAVING COUNT(*) > 1
            ) AS subquery
            ON wc.comment_post_ID = subquery.comment_post_ID
            WHERE wc.comment_ID != subquery.retained_comment_ID
            AND wc.comment_content LIKE '%Order charge successful in Stripe%'
            LIMIT 1
        ";

				// Run the select query to check for remaining rows
				$remaining_rows = $wpdb->get_results( $select_query );

				// If no remaining rows, schedule a single action to remove the recurring action
				if ( empty( $remaining_rows ) ) {
					wp_schedule_single_event( time(), 'wfocu_fkwcs_clear_delete_duplicate_comments_schedule' );
				}
			} catch ( Exception|Error $e ) {
				// Log the exception message
				WFOCU_Core()->log->log( 'Exception occurred in : ' . __FUNCTION__ . $e->getMessage() );

				// Schedule a single action to delete the recurring action
				wp_schedule_single_event( time(), 'wfocu_fkwcs_clear_delete_duplicate_comments_schedule' );
			}
		}

		public function get_svg_image() {
			if ( ! class_exists( 'WFFN_Core' ) ) {
				return '';
			}

			return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.875 7.62798C10.875 7.2828 11.1548 7.00298 11.5 7.00298C11.8452 7.00298 12.125 7.2828 12.125 7.62798V8.87988C12.125 9.22506 11.8452 9.50488 11.5 9.50488C11.1548 9.50488 10.875 9.22506 10.875 8.87988V7.62798ZM15.0633 8.80415C14.8192 8.56007 14.4235 8.56007 14.1794 8.80415L13.2941 9.68938C13.0501 9.93346 13.0501 10.3292 13.2941 10.5733C13.5382 10.8173 13.934 10.8173 14.178 10.5733L15.0633 9.68804C15.3073 9.44396 15.3073 9.04823 15.0633 8.80415ZM8.81513 8.80415C8.57105 8.56007 8.17532 8.56007 7.93125 8.80415C7.68717 9.04823 7.68717 9.44396 7.93125 9.68804L8.81648 10.5733C9.06055 10.8173 9.45628 10.8173 9.70036 10.5733C9.94444 10.3292 9.94444 9.93346 9.70036 9.68938L8.81513 8.80415ZM11.5 2C15.6421 2 19 5.24616 19 9.25051C19 11.3467 18.0683 13.2705 16.2451 14.9928C16.1655 15.068 16.1039 15.1586 16.064 15.2581L16.0314 15.3604L14.8613 20.2564C14.631 21.2204 13.7795 21.9188 12.7727 21.9934L12.5935 22H10.4068C9.38319 22 8.48749 21.3549 8.18582 20.4231L8.13885 20.2558L6.97033 15.3607C6.93694 15.2208 6.8627 15.0931 6.75644 14.9928C5.01913 13.3524 4.09081 11.5294 4.00634 9.54878L4 9.25051L4.00401 9.01118C4.13463 5.11761 7.44071 2 11.5 2ZM8.94375 18.25L9.34232 19.918L9.37505 20.0381C9.49618 20.4123 9.84478 20.6896 10.2645 20.7413L10.4068 20.75L12.5473 20.7509L12.6803 20.7468C13.1081 20.7151 13.4657 20.4511 13.6068 20.0897L13.6456 19.9658L14.0563 18.25H8.94375ZM11.5 3.25C8.20978 3.25 5.51485 5.69126 5.26888 8.76697L5.25383 9.03211L5.24972 9.22395L5.2552 9.49552C5.32471 11.1253 6.09697 12.651 7.61459 14.0839C7.84994 14.3061 8.02739 14.5809 8.13215 14.8848L8.18617 15.0705L8.645 17H10.8753V11.3713C10.8753 11.0262 11.1551 10.7463 11.5003 10.7463C11.8455 10.7463 12.1253 11.0262 12.1253 11.3713V17H14.3562L14.8403 14.9814L14.9038 14.7928C15.0113 14.5247 15.1762 14.2831 15.3867 14.0842C16.9781 12.5808 17.75 10.9757 17.75 9.25051C17.75 5.94616 14.9611 3.25 11.5 3.25Z" fill="#82838E"></path></svg>';
		}

		/**
		 * Add normalize order call in wc order list table
		 * @return void
		 */
		public function add_script_for_fire_normalize_order() {
			if ( 'shop_order' !== get_current_screen()->id && 'woocommerce_page_wc-orders' !== get_current_screen()->id ) {
				return;
			}
			?>
            <script type="text/javascript">
                var sendAjaxUrl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
                try {
                    jQuery(document).ready(function ($) {
                        // Set cookie for 1 hour
                        $.post(sendAjaxUrl, {action: 'wfocu_normalize_order_from_wc_list'});
                    });
                } catch (e) {

                }
            </script>
            <style>
                .wfocu_note_ul {
                    padding: 8px 0 0 16px !important;
                    list-style: disc !important;
                }

                .wfocu_note_ul li {
                    padding: 0 !important;
                }
            </style>
			<?php
		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'admin', 'WFOCU_Admin' );
	}
}