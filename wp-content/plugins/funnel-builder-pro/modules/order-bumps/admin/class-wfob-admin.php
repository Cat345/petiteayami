<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WFOB_Admin' ) ) {
	/**
	 * Class WFOB_Admin
	 * @todo this class has many upstroke functions
	 */
	class WFOB_Admin {

		private static $ins = null;
		public $admin_path;
		public $admin_url;
		public $section_page = '';
		public $wfob_id = 0;
		protected $localize_data = [];
		public $should_show_shortcodes = null;

		public function __construct() {
			$this->admin_path = WFOB_PLUGIN_DIR . '/admin';
			$this->admin_url  = WFOB_PLUGIN_URL . '/admin';
			if ( ! is_null( filter_input( INPUT_GET, 'section' ) ) ) {
				$this->section_page = strtolower( filter_input( INPUT_GET, 'section' ) );
			}
			$this->wfob_id = WFOB_Common::get_id();
			add_action( 'admin_menu', array( $this, 'redirect_to_our_url' ), 88 );
			add_action( 'admin_menu', array( $this, 'delete_wfob_post' ), 89 );
			if ( isset( $_GET['wfob_duplicate'] ) && isset( $_GET['wfob_id'] ) && $_GET['wfob_id'] > 0 ) {

				add_action( 'admin_menu', array( $this, 'duplicate_checkout_pages' ), 89 );
			}
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 90 );
			/**
			 * Admin enqueue scripts
			 */
			if ( WFOB_Common::is_load_admin_assets( 'all' ) ) {

				add_action( 'admin_menu', array( $this, 'admin_vue_js' ), - 5 );

			}
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 99 );
			/**
			 * Admin footer text
			 */
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 9999, 1 );
			add_filter( 'update_footer', array( $this, 'update_footer' ), 9999, 1 );
			if ( isset( $_GET['page'] ) && 'wfob' == $_GET['page'] ) {
				add_action( 'in_admin_header', array( $this, 'maybe_remove_all_notices_on_page' ) );
			}
			if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'wfob' ) {
				add_action( 'in_admin_header', [ $this, 'restrict_notices_display' ] );
			}
			add_action( 'save_post', array( $this, 'maybe_reset_transients' ), 10, 2 );
			add_action( 'delete_post', array( $this, 'clear_transients_on_delete' ), 10 );

			add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'show_bump_total_in_order_listings' ), 9999, 2 );
			if ( WFOB_Common::is_builder() ) {
				add_action( 'admin_print_styles', [ $this, 'remove_theme_css_and_scripts' ], 100 );
			}
			add_filter( 'woofunnels_global_settings', function ( $menu ) {
				array_push( $menu, array(
					'title'    => __( 'Order Bumps', 'woofunnels-order-bump' ),
					'slug'     => 'wfob',
					'link'     => admin_url( 'admin.php?page=wfob&tab=settings' ),
					'priority' => 40,
					'pro_tab'  => true,
				) );

				return $menu;
			} );
			if ( WFOB_Common::is_load_admin_assets( 'builder' ) ) {

				add_action( 'admin_enqueue_scripts', array( $this, 'maybe_register_breadcrumbs' ), 10 );
			}
			add_action( 'wfob_add_control_meta_query', [ $this, 'exlude_from_query' ] );
			add_filter( 'bwf_general_settings_link', [ $this, 'bwf_general_settings_link' ], 9 );
		}

		public static function get_instance() {
			if ( null == self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Get web url of admin directory
		 * @return string
		 */
		public function get_admin_url() {
			return plugin_dir_url( WFOB_PLUGIN_FILE ) . 'admin';
		}


		/**
		 * Duplicate Checkout page
		 */
		public function duplicate_checkout_pages() {
				$wfob_id = absint( $_GET['wfob_id'] );
				WFOB_Common::make_duplicate( $wfob_id );
				wp_redirect( admin_url( 'admin.php?page=wfob' ) );
				exit;

		}


		public function register_admin_menu() {
			$user = WFOB_Core()->role->user_access( 'menu', 'read' );
			if ( false !== $user ) {
				add_submenu_page( 'woofunnels', __( 'OrderBump', 'woofunnels-order-bump' ), __( 'OrderBump', 'woofunnels-order-bump' ), $user, 'wfob', array(
					$this,
					'wfob_page',
				) );
			}
		}

		public function admin_vue_js() {
				global $concatenate_scripts;
				$concatenate_scripts = false;
				wp_enqueue_script( 'wfob-vuejs', $this->admin_url . '/includes/vuejs/vue.min.js', array(), '2.5.13' );
				wp_enqueue_script( 'wfob-vue-vfg', $this->admin_url . '/includes/vuejs/vfg.min.js', array(), '2.1.0' );

		}

		public function admin_enqueue_assets() {

			wp_enqueue_style( 'wfob-admin-font', $this->admin_url . '/assets/css/wfob-admin-font.min.css', [], WFOB_VERSION_DEV );
			/**
			 * Load Funnel Builder page assets
			 */
			if ( WFOB_Common::is_load_admin_assets( 'builder' ) ) {
				wp_enqueue_style( 'woofunnels-opensans-font', '//fonts.googleapis.com/css?family=Open+Sans', array(), WFOB_VERSION_DEV );
			}

			if ( WFOB_Common::is_load_admin_assets( 'settings' ) ) {
				// Use new WooCommerce handle for WC >= 10.3.0, fallback to legacy handle for older versions
				$tiptip_handle = ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '10.3.0', '>=' ) ) ? 'wc-jquery-tiptip' : 'jquery-tiptip';
				wp_enqueue_script( $tiptip_handle );
			}

			/**
			 * Including One Click Upsell assets on all OCU pages.
			 */
			if ( WFOB_Common::is_load_admin_assets( 'all' ) ) {
				$suffix = '.min';

				if ( defined( 'WFOB_IS_DEV' ) && true === WFOB_IS_DEV ) {
					$suffix = '';
				}

				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_style( 'wfob-izimodal', $this->admin_url . '/includes/iziModal/iziModal.css', array(), WFOB_VERSION_DEV );
				wp_enqueue_script( 'wfob-izimodal', $this->admin_url . '/includes/iziModal/iziModal.js', array(), WFOB_VERSION_DEV );
				wp_enqueue_script( 'wc-backbone-modal' );
				wp_enqueue_style( 'wfob-vue-multiselect', $this->admin_url . '/includes/vuejs/vue-multiselect.min.css', array(), WFOB_VERSION_DEV );
				wp_enqueue_style( 'wfob-vfg', $this->admin_url . '/includes/vuejs/vfg.min.css', array(), WFOB_VERSION_DEV );
				wp_enqueue_style( 'wfob-sweetalert2', $this->admin_url . '/assets/css/sweetalert2' . $suffix . '.css', array(), WFOB_VERSION_DEV );
				wp_enqueue_style( 'wfob-admin', $this->admin_url . '/assets/css/wfob-admin' . $suffix . '.css', array(), time() );

				wp_enqueue_script( 'wfob-vue-multiselected', $this->admin_url . '/includes/vuejs/vue-multiselect.min.js', array(), '2.1.0' );
				wp_enqueue_script( 'wfob-sweetalert2', $this->admin_url . '/assets/js/wfob-sweetalert.min.js', array(), WFOB_VERSION_DEV );
				wp_enqueue_script( 'wfob-gl', $this->admin_url . '/assets/js/global.min.js', array(), WFOB_VERSION_DEV );
				wp_enqueue_script( 'wfob', $this->admin_url . '/assets/js/wfob' . $suffix . '.js', array(
					'wfob-gl',
					'jquery',
					'underscore',
					'backbone'
				), WFOB_VERSION_DEV );
				if ( 'design' == $this->section_page && WFOB_Common::get_id() > 0 ) {
					wp_enqueue_style( 'wfob-bump_c', $this->admin_url . '/assets/css/bump' . $suffix . '.css', array(), WFOB_VERSION_DEV );
				}
				if ( 0 == WFOB_Common::get_id() ) {
					wp_enqueue_style( 'woocommerce_admin_styles' );
					wp_enqueue_script( 'wc-backbone-modal' );
				}
				if ( 'rules' == $this->section_page && WFOB_Common::get_id() > 0 ) {
					wp_register_script( 'wfob-chosen', $this->admin_url . '/assets/js/chosen/chosen.jquery.min.js', array( 'jquery' ), WFOB_VERSION_DEV );
					wp_register_script( 'wfob-ajax-chosen', $this->admin_url . '/assets/js/chosen/ajax-chosen.jquery.min.js', array(
						'jquery',
						'wfob-chosen',
					), WFOB_VERSION_DEV );
					wp_enqueue_script( 'wfob-ajax-chosen' );
					wp_enqueue_style( 'wfob-chosen-app', $this->admin_url . '/assets/css/chosen' . $suffix . '.css', array(), WFOB_VERSION_DEV );
					wp_enqueue_style( 'wfob-admin-app', $this->admin_url . '/assets/css/wfob-admin-app' . $suffix . '.css', array(), WFOB_VERSION_DEV );
					wp_register_script( 'jquery-masked-input', $this->admin_url . '/assets/js/jquery.maskedinput.min.js', array( 'jquery' ), WFOB_VERSION_DEV );
					wp_enqueue_script( 'jquery-masked-input' );
					wp_enqueue_script( 'wfob-admin-app', $this->admin_url . '/assets/js/wfob-rules-app.min.js', array(
						'jquery',
						'jquery-ui-datepicker',
						'underscore',
						'backbone',
					), WFOB_VERSION_DEV );
				}
				if ( 'design' == $this->section_page && WFOB_Common::get_id() > 0 ) {
					wp_enqueue_editor();
				}
				$this->localize_data();
			}
		}

		private function localize_data() {
			wp_localize_script( 'wfob', 'wfob_data', $this->get_localize_data() );
			wp_localize_script( 'wfob', 'wfob_localization', WFOB_Common::get_builder_localization() );
			wp_localize_script( 'wfob-gl', 'wfob_secure', [
				'nonce'                  => wp_create_nonce( 'wfob_admin_secure_key' ),
				'search_products_nonce'  => wp_create_nonce( 'search-products' ),
				'ajax_chosen'            => wp_create_nonce( 'json-search' ),
				'text_or'                => __( 'or', 'woofunnels-order-bump' ),
				'search_customers_nonce' => wp_create_nonce( 'search-customers' ),
				'text_apply_when'        => __( 'Open this page when these conditions are matched', 'woofunnels-order-bump' ),
				'remove_text'            => __( 'Remove', 'woofunnels-order-bump' ),
			] );
		}


		/**
		 * Get all localize data for Order bump page builder
		 * @return array
		 */
		public function get_localize_data() {

			if ( is_array( $this->localize_data ) && count( $this->localize_data ) > 0 ) {
				return $this->localize_data;
			}

			$post          = get_post( $this->wfob_id );
			$our_post_type = false;
			if ( ! is_null( $post ) && WFOB_Common::get_bump_post_type_slug() == $post->post_type ) {
				$this->wfob_id = $post->ID;
				$our_post_type = true;
			}

			$this->localize_data['id']               = $this->wfob_id;
			$this->localize_data['name']             = ( $our_post_type ) ? get_the_title( $this->wfob_id ) : '';
			$this->localize_data['post_name']        = ( $our_post_type ) ? $post->post_name : '';
			$this->localize_data['currency']         = get_woocommerce_currency_symbol();
			$this->localize_data['products']         = WFOB_Common::get_prepared_products( $this->wfob_id );
			$this->localize_data['product_settings'] = WFOB_Common::get_product_settings( $this->wfob_id );
			$this->localize_data['design']           = WFOB_Common::get_design_data( $this->wfob_id );
			$this->localize_data['settings']         = WFOB_Common::get_setting_data( $this->wfob_id );
			$this->localize_data['global_settings']  = WFOB_Common::get_global_setting();

			return apply_filters( 'wfob_admin_localize_data', $this->localize_data );
		}

		public function wfob_page() {
			if ( isset( $_GET['page'] ) && 'wfob' === $_GET['page'] ) {

				if ( isset( $_GET['section'] ) && $_GET['section'] == 'export' ) {
					include_once( $this->admin_path . '/view/flex-export.php' );

					return;
				} else if ( isset( $_GET['section'] ) && $_GET['section'] == 'import' ) {
					include_once( $this->admin_path . '/view/flex-import.php' );

					return;
				}

				if ( isset( $_GET['section'] ) ) {
					include_once( $this->admin_path . '/view/bump-builder-view.php' );
				} elseif ( isset( $_GET['tab'] ) && $_GET['tab'] == 'settings' ) {
					include_once( $this->admin_path . '/view/global-settings.php' );
				} else {
					require_once( WFOB_PLUGIN_DIR . '/admin/includes/class-wfob-post-table.php' );
					include_once( $this->admin_path . '/view/bump-admin.php' );
				}
			}
		}


		public function is_wfob_page( $section = '' ) {
			if ( isset( $_GET['page'] ) && $_GET['page'] == 'wfob' && '' == $section ) {
				return true;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] == 'wfob' && isset( $_GET['section'] ) && $_GET['section'] == $section ) {
				return true;
			}

			return false;
		}

		public function admin_footer_text( $footer_text ) {
			if ( WFOB_Common::is_load_admin_assets( 'builder' ) || ( isset( $_GET['page'] ) && 'wfob' == $_GET['page'] ) ) {
				return $this->wfob_admin_footer();
			}

			return $footer_text;
		}

		public function update_footer( $footer_text ) {
			if ( WFOB_Common::is_load_admin_assets( 'builder' ) || ( isset( $_GET['page'] ) && 'wfob' == $_GET['page'] ) ) {
				return '';
			}

			return $footer_text;
		}


		public function get_bump_id() {
			if ( isset( $_GET['wfob_id'] ) && ! empty( $_GET['wfob_id'] ) && isset( $_GET['page'] ) && 'wfob' == $_GET['page'] ) {
				return $_GET['wfob_id'];
			}

			return false;
		}

		public function get_bump_section() {
			if ( isset( $_GET['section'] ) && ! empty( $_GET['section'] ) && isset( $_GET['page'] ) && 'wfob' == $_GET['page'] ) {
				return $_GET['section'];
			}

			return '';
		}


		public function tooltip( $text ) {
			?>
            <span class="wfob-help"><i class="icon"></i><div class="helpText"><?php echo $text; ?></div></span>
			<?php
		}

		/**
		 * Remove all the notices in our dashboard pages as they might break the design.
		 */
		public function maybe_remove_all_notices_on_page() {

				remove_all_actions( 'admin_notices' );

		}

		public function restrict_notices_display() {

			/** Inside Order bump page */
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );

		}


		public function get_bump_layout() {
			echo '<div class="wfob_bump_layout_container">';
			include WFOB_PLUGIN_DIR . '/admin/view/design/layout.php';
			echo '</div>';
		}

		public function redirect_to_our_url() {
			if ( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == WFOB_Common::get_bump_post_type_slug() && isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {
				$wfob_id = absint( $_REQUEST['post'] );
				if ( $wfob_id > 0 ) {
					$redirect_url = add_query_arg( [
						'section' => 'rules',
						'wfob_id' => $wfob_id,
					], admin_url( 'admin.php?page=wfob' ) );
					wp_safe_redirect( $redirect_url );
					exit;
				}
			}
		}

		/**
		 * Delete Checkout Page
		 */
		public function delete_wfob_post() {
			if ( isset( $_GET['action'] ) && 'wfob_delete' == $_GET['action'] && isset( $_GET['post'] ) && $_GET['post'] > 0 ) {
				//delete transient when page is deleted
				WFOB_Common::delete_transient( $_GET['post'] );

				wp_delete_post( $_GET['post'] );
			}
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

			if ( class_exists( 'WooFunnels_Transient' ) && ( is_object( $post ) && $post->post_type === WFOB_Common::get_bump_post_type_slug() ) ) {
				$WooFunnels_Transient_obj = WooFunnels_Transient::get_instance();
				$WooFunnels_Transient_obj->delete_all_transients( WFOB_SLUG );
			}

		}

		/**
		 * @hooked over `delete_post`
		 *
		 * @param $post_id
		 */
		public function clear_transients_on_delete( $post_id ) {

			$get_post_type = get_post_type( $post_id );

			if ( WFOB_Common::get_bump_post_type_slug() === $get_post_type ) {
				if ( class_exists( 'WooFunnels_Transient' ) ) {
					$WooFunnels_Transient_obj = WooFunnels_Transient::get_instance();
					$WooFunnels_Transient_obj->delete_all_transients( WFOB_SLUG );
				}
			}

		}

		private function find_js_css_handle( $url ) {
			$paths   = [ '/themes/', '/cache/' ];
			$plugins = [
				'revslider',
				'elementor/',
				'agile-store-locator',
			];
			$paths   = array_merge( $paths, $plugins );


			$paths = apply_filters( 'wfacp_admin_css_js_removal_paths', $paths, $this );
			if ( empty( $paths ) ) {
				return false;
			}
			foreach ( $paths as $path ) {
				if ( false !== strpos( $url, $path ) ) {
					return true;
					break;
				}
			}

			return false;

		}

		public function remove_theme_css_and_scripts() {

			global $wp_scripts, $wp_styles;
			$registered_script = $wp_scripts->registered;
			if ( ! empty( $registered_script ) ) {
				foreach ( $registered_script as $handle => $data ) {
					if ( $this->find_js_css_handle( $data->src ) ) {
						unset( $wp_scripts->registered[ $handle ] );
						wp_dequeue_script( $handle );
					}
				}
			}

			$registered_style = $wp_styles->registered;
			if ( ! empty( $registered_style ) ) {
				foreach ( $registered_style as $handle => $data ) {
					if ( $this->find_js_css_handle( $data->src ) ) {
						unset( $wp_styles->registered[ $handle ] );
						wp_dequeue_script( $handle );
					}
				}
			}

		}

		public function show_bump_total_in_order_listings( $total, $order ) {
			global $current_screen;
			if ( ! $current_screen instanceof WP_Screen || ! $order instanceof WC_Order || $current_screen->base !== 'edit' || $current_screen->id !== 'edit-shop_order' || $current_screen->post_type !== 'shop_order' ) {
				return $total;
			}

			$line_total = 0;
			$have_bumps = 0;
			if ( $order instanceof WC_Order ) {
				$line_items = $order->get_items();
				/**
				 * @var $item WC_Order_Item_Product
				 */
				foreach ( $line_items as $item ) {
					$_bump_purchase = $item->get_meta( '_bump_purchase' );
					if ( '' !== $_bump_purchase ) {
						$have_bumps ++;
						$line_total += $item->get_total();
					}
				}
			}

			if ( $have_bumps > 0 ) {

				$html = ( $order->get_meta( '_wfocu_upsell_amount' ) > 0 ) ? '' : '<br>';

				$html  .= '
<p style="font-size: 12px;"><em> ' . sprintf( esc_html__( 'Bump: %s' ), wc_price( $line_total, array( 'currency' => $order->get_currency() ) ) ) . '</em></p>';
				$total = $total . $html;
			}

			return $total;

		}

		/**
		 * Check if its our builder page and registered required nodes to prepare a breadcrumb
		 */
		public function maybe_register_breadcrumbs() {
				/**
				 * Only register primary node if not added yet
				 */
				if ( empty( BWF_Admin_Breadcrumbs::$nodes ) ) {
					BWF_Admin_Breadcrumbs::register_node( array(
						'text' => __( 'OrderBumps' ),
						'link' => admin_url( 'admin.php?page=wfob' )
					) );
				}
				$funnel_id = $this->wfob_id;
				$title     = ! empty( get_the_title( $funnel_id ) ) ? get_the_title( $funnel_id ) : __( '(no title)', 'woofunnels-order-bump' );
				BWF_Admin_Breadcrumbs::register_node( array( 'text' => $title, 'link' => '' ) );

		}

		public function wfob_admin_footer() {
			return sprintf( "%s <a href='%s' target='_blank'>%s</a>", __( "Thanks for creating with FunnelKit. Need Help?", 'woofunnels-order-bump' ), 'https://funnelkit.com/support', __( 'Contact Support.', 'woofunnels-order-bump' ) );
		}

		/**
		 * @param $existing_args
		 * Exclude bump create by funnel builder or AB testing
		 *
		 * @return mixed
		 */
		public function exlude_from_query( $existing_args ) {
			if ( isset( $existing_args['get_existing'] ) && true === $existing_args['get_existing'] ) {
				unset( $existing_args['get_existing'] );

				return $existing_args;
			}
			if ( isset( $existing_args['meta_query'] ) && is_array( $existing_args['meta_query'] ) && count( $existing_args['meta_query'] ) > 0 ) {
				array_push( $existing_args['meta_query'], array(
					'key'     => '_bwf_in_funnel',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				) );
				array_push( $existing_args['meta_query'], array(
					'key'     => '_bwf_ab_variation_of',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				) );
			} else {
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
			}

			return $existing_args;
		}

		public function bwf_general_settings_link() {
			return admin_url( 'admin.php?page=wfob&tab=settings' );
		}

	}

	if ( class_exists( 'WFOB_Core' ) ) {
		WFOB_Core::register( 'admin', 'WFOB_Admin' );
	}
}
