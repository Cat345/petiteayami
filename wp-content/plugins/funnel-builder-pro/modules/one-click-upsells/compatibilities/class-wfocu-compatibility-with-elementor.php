<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WFOCU_Compatibility_With_Elementor' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Elementor
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Compatibility_With_Elementor {

		public function __construct() {
			add_post_type_support( WFOCU_Common::get_offer_post_type_slug(), 'elementor' );
			add_action( 'plugins_loaded', array( $this, 'wfocu_widget_init' ) );

			// Clear Elementor's cached copy of the offer on every front-end offer load.
			add_action( 'wfocu_offer_setup_completed', array( $this, 'clear_elementor_cache_when_offer_loads' ), 5 );
		}

		public function is_enable() {
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				return true;
			}

			return false;
		}

		// initialing widget settings
		public function wfocu_widget_init() {

			/**
			 * Include UpStroke template group for the elementor
			 */
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/wfocu-template-group-elementor.php';

			/**  Register widget category */
			add_action( 'elementor/elements/categories_registered', array( $this, 'add_wfocu_elementor_category' ) );

			/** Register widgets and Tags */
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
				add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
				add_action( 'elementor/dynamic_tags/register_tags', array( $this, 'register_tags' ) );
			}

			add_action( 'elementor/editor/init', array( $this, 'maybe_setup_customizer_data_on_offers' ), 100 );
			add_action( 'elementor/editor/init', array( $this, 'maybe_register_widget_message' ), 500 );
			add_action( 'elementor/editor/init', array( $this, 'maybe_setup_upstroke_fonts' ), 500 );

			add_action( 'wp_footer', array( $this, 'setup_footer_script' ), 9999 );
			add_action( 'elementor/ajax/register_actions', array( $this, 'maybe_setup_customizer_data_on_offers' ) );

			// Editor Preview Style
			add_action( 'elementor/preview/enqueue_styles', array( $this, 'maybe_add_slider_css_in_preview' ) );

			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'maybe_print_shortcodes_helpbox' ) );

			add_action( 'elementor/theme/register_conditions', array( $this, 'register_conditions' ) );

			add_filter( 'elementor/document/config', array( $this, 'hide_widgets_outside_offer' ), 11, 2 );

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'maybe_flush_elementor_element_cache' ) );
			}
		}

		/**
		 * Names of the Elementor widgets this module registers.
		 *
		 * @return array
		 */
		public static function get_widget_names() {
			return apply_filters(
				'wfocu_elementor_widget_names',
				array(
					'wfocu-accept-offer-button',
					'wfocu-offer-reject-button',
					'wfocu-accept-offer-link',
					'wfocu-offer-reject-link',
					'wfocu-variation-selector',
					'wfocu-qty-selector',
					'wfocu-offer-price',
					'wfocu-product-images',
					'wfocu-short-description',
					'wfocu-offer-product-title',
				)
			);
		}

		/**
		 * Hide our widgets from the Elementor panel (and its search) on every document that is not
		 * an UpStroke offer page.
		 *
		 * The panel category is already gated in add_wfocu_elementor_category(), which keeps the
		 * widgets out of the panel's category list. That is not sufficient on its own: the editor's
		 * shouldAddWidget() only tests `show_in_panel`, so a widget whose category is absent still
		 * lands in the elements collection and stays reachable through the panel search box.
		 *
		 * @param array $config
		 * @param int   $post_id
		 *
		 * @return array
		 */
		public function hide_widgets_outside_offer( $config, $post_id ) {
			if ( WFOCU_Common::get_offer_post_type_slug() === get_post_type( absint( $post_id ) ) ) {
				return $config;
			}

			if ( ! isset( $config['panel'] ) || ! is_array( $config['panel'] ) ) {
				$config['panel'] = array();
			}
			if ( ! isset( $config['panel']['widgets_settings'] ) || ! is_array( $config['panel']['widgets_settings'] ) ) {
				$config['panel']['widgets_settings'] = array();
			}

			foreach ( self::get_widget_names() as $widget_name ) {
				$config['panel']['widgets_settings'][ $widget_name ] = array(
					'show_in_panel'  => false,
					'hide_on_search' => true,
				);
			}

			return $config;
		}

		/**
		 * Clear Elementor's element cache for our offer pages once per plugin version.
		 *
		 * Runs in admin only and is gated on the plugin version, so it fires once per release
		 * rather than literally once. That is deliberate: widget markup can change between
		 * releases, and a stale cached copy would outlive the change. Existing installs may be carrying a cached
		 * copy of the document that was built while our widgets were not registered. Changing the
		 * registration only affects future cache builds - a row already in postmeta keeps being
		 * served until its TTL expires, which can be up to a year.
		 *
		 * @return void
		 */
		public function maybe_flush_elementor_element_cache() {
			if ( ! class_exists( '\Elementor\Core\Base\Document' ) || ! defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) ) {
				return;
			}

			$option_key = 'wfocu_elementor_element_cache_flushed';

			if ( WFOCU_VERSION === get_option( $option_key ) ) {
				return;
			}

			global $wpdb;

			$cache_meta_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
			// Left over from the previous front-end cache-busting approach, which has been removed.
			$legacy_meta_key = '_wfocu_elementor_cache_marker';
			$post_type       = WFOCU_Common::get_offer_post_type_slug();

			/*
			 * Collect the affected IDs before deleting. The DELETE below bypasses
			 * delete_post_meta(), so nothing invalidates the 'post_meta' object cache for those
			 * posts. On a site running a persistent object cache the row would be gone while
			 * get_post_meta() kept serving the stale value.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time upgrade routine
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ( %s, %s ) AND p.post_type = %s",
					$cache_meta_key,
					$legacy_meta_key,
					$post_type
				)
			);

			if ( ! empty( $post_ids ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time upgrade routine
				$wpdb->query(
					$wpdb->prepare(
						"DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ( %s, %s ) AND p.post_type = %s",
						$cache_meta_key,
						$legacy_meta_key,
						$post_type
					)
				);

				foreach ( $post_ids as $post_id ) {
					wp_cache_delete( (int) $post_id, 'post_meta' );
				}
			}

			update_option( $option_key, WFOCU_VERSION, false );
		}


		public function register_tags() {

			if ( false === $this->is_elementor_offer_page() ) {
				return;
			}

			\Elementor\Plugin::$instance->dynamic_tags->register_group(
				'upstroke',
				array(
					'title' => 'WooFunnels Upsells',
				)
			);

			require_once __DIR__ . '/page-builders/elementor/tags/offer-price.php';
			require_once __DIR__ . '/page-builders/elementor/tags/product-title.php';
			require_once __DIR__ . '/page-builders/elementor/tags/countdown-timer.php';

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Price() );
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Title() );
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Countdown() );
			} else {
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Price' );
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Title' );
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Countdown' );
			}
		}

		/**
		 * Adding a new widget category 'WooFunnels'
		 */
		public function add_wfocu_elementor_category() {
			if ( $this->is_elementor_offer_page() ) {
				\Elementor\Plugin::instance()->elements_manager->add_category(
					'upstroke',
					array(
						'title' => __( 'FunnelKit', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'fa fa-plug',
					)
				);
			}
		}

		public function maybe_setup_upstroke_fonts() {
			add_action( 'wp_head', array( $this, 'enqueue_font_css' ) );
		}

		public function enqueue_font_css() {
			wp_enqueue_style( 'wfocu-icons', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-font.css', null, WFOCU_VERSION );
		}

		/**
		 * @throws Exception
		 */
		/**
		 * Register our widgets on every request, not only on a resolved offer page.
		 *
		 * Elementor's element cache (`_elementor_element_cache`) is written by ANY non-admin
		 * render of the document - including REST and WP-Cron requests, where the offer context
		 * this method used to test is unreliable or absent. If the widget type is not registered
		 * at that moment, `Elements_Manager::create_element_instance()` returns null, the widget
		 * is dropped from the element tree entirely, and the widget-less HTML is what gets cached
		 * and served to real visitors until the cache expires.
		 *
		 * Rendering stays context-guarded inside each widget, and `is_dynamic_content()` keeps our
		 * markup out of the cache. Panel visibility is scoped in hide_widgets_outside_offer();
		 * the panel category is still gated in add_wfocu_elementor_category().
		 *
		 * @throws Exception
		 */
		public function register_widgets() {
			$this->includes();
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Accept_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Reject_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Accept_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Reject_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Variation_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Qty_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Price_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \WFOCU_Product_Images_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \WFOCU_Product_Short_Description_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Product_Title_Widget() );
			} else {
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Accept_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Reject_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Accept_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Reject_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Variation_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Qty_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Price_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \WFOCU_Product_Images_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \WFOCU_Product_Short_Description_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Product_Title_Widget() );
			}
		}

		/**
		 * Include widget Files
		 */
		public function includes() {

			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-accept-button-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-reject-button-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-accept-link-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-reject-link-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-variation-selector-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-qty-selector-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-price-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-images-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-short-description-widget.php';
			require_once __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-title-widget.php';
		}


		public function print_inline_script() {
			?>
			<script>

				(function ($) {
					"use strict";

					var wfocuSupportedMergeTagsWidgets =<?php echo wp_json_encode( $this->get_merge_tags_supported_widgets() ); ?>;

					elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
						if (wfocuSupportedMergeTagsWidgets.indexOf(model.get('widgetType')) === -1) {
							return;
						}
						// var html = '<div class="elementor-control elementor-control-wc_style_warning elementor-control-type-raw_html elementor-label-inline elementor-control-separator-default">\n' +
						//     '\t\t\t<div class="elementor-control-content">\n' +
						//     '\t\t\t\t\t\t\n' +
						//     '\t\t<div class="elementor-control-raw-html elementor-panel-alert elementor-panel-alert-info">You can also add personalization tags to this element using shortcodes.<a onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" href="javascript:void(0)">Click here to show the available shortcodes</a> </div>\n' +
						//     '\t\t\t\t\t</div>\n' +
						//     '\t\t</div>';
						var html = '\t\t\t<div class="wfocu-el-customize-note">\n' +
							'\t\t\t\t\t\t\n' +
							'\t\t<div class="elementor-panel-alert elementor-panel-alert-info">You can also add personalization tags to this element using shortcodes.<a style="text-decoration: underline;" onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" href="javascript:void(0)">Click here to show the available shortcodes</a> </div>\n' +
							'\t\t\t\t\t</div>\n' +
							'\t\t';
						$(".elementor-panel-navigation").eq(0).after(html);


					});


				})(jQuery);
			</script>
			<?php
		}

		public function setup_footer_script() {
			$this->print_inline_script_frontend();
		}

		public function print_inline_script_frontend() {
			global $post;
			if ( is_object( $post ) && $post instanceof WP_Post && WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				?>
				<script>

					(function ($) {
						"use strict";

						$(window).on('elementor/frontend/init', function () {
							elementorFrontend.hooks.addAction('frontend/element_ready/wfocu-product-images.default', function ($scope) {

								if (false === elementorFrontend.config.environmentMode.edit) {
									return;
								}

								if (jQuery('.wfocu-product-carousel').length > 0) {
									jQuery('.wfocu-product-carousel').each(function () {
										var flickity_attr = jQuery(this).attr('data-flickity');
										if (undefined !== flickity_attr) {
											jQuery(this).flickity(JSON.parse(flickity_attr));
										}
									});
								}

								if (jQuery('.wfocu-product-carousel-nav').length > 0) {
									jQuery('.wfocu-product-carousel-nav').each(function () {
										var flickity_attr = jQuery(this).attr('data-flickity');
										if (undefined !== flickity_attr) {
											jQuery(this).flickity(JSON.parse(flickity_attr));
										}
									});
								}
							});

							elementorFrontend.hooks.addAction('frontend/element_ready/countdown.default', function ($scope) {
								jQuery(document.body).on('countdown_expire', function (e) {
									var actions = $scope.find('.elementor-widget-container > div').attr('data-expire-actions');
									console.log('countdown_expire hits and actions are: ' + actions);

									var action_on_zero = '';

									if (typeof wfocu_vars !== "undefined" && false === wfocu_vars.is_preview) {
										console.log('Ajax action wfocu_front_offer_expired fired on countdown_expire.');
										$.post(
											wfocu_vars.ajax_url, {
												action: 'wfocu_front_offer_expired',
												'wfocu-si': wfocu_vars.session_id, nonce: wfocu_vars.nonces.wfocu_offer_expired, "next_action": action_on_zero
											}, function (response) {
												console.log('wfocu_front_offer_expired repsonse on countdown_expire');
												console.log(response);
											});
									}
								});
							});
						});
					})(jQuery);
				</script>
				<?php
			}
		}


		public function get_merge_tags_supported_widgets() {
			return array( 'heading', 'text-editor', 'shortcode' );
		}


		public function maybe_print_shortcodes_helpbox() {
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/help-shortcodes.php';
		}

		public function maybe_register_widget_message() {
			$id       = \Elementor\Plugin::$instance->editor->get_post_id();
			$get_post = get_post( $id );
			if ( ! is_null( $get_post ) && WFOCU_Common::get_offer_post_type_slug() === $get_post->post_type ) {
				add_action( 'wp_footer', array( $this, 'print_inline_script' ), 9999 );
			}
		}

		public function maybe_setup_customizer_data_on_offers() {

			global $post;

			if ( false === is_object( $post ) ) {
				return;
			}

			if ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				$maybe_offer_id                          = $post->ID;
				WFOCU_Core()->template_loader->is_single = true;
			}

			if ( empty( $maybe_offer_id ) ) {
				return;
			}

			WFOCU_Core()->template_loader->setup_complete_offer_setup_manual( $maybe_offer_id );
		}


		public function register_conditions( $conditions_manager ) {
			require_once __DIR__ . '/page-builders/elementor/conditions/offers.php';

			$new_condition = new ElementorPro\Modules\ThemeBuilder\Conditions\WooFunnels_Offers(
				array(
					'post_type' => WFOCU_Common::get_offer_post_type_slug(),
				)
			);
			$conditions_manager->get_condition( 'singular' )->register_sub_condition( $new_condition );
		}

		public function maybe_add_slider_css_in_preview() {
			wp_enqueue_style( 'flickity' );
			wp_enqueue_style( 'flickity-common' );
		}

		/**
		 * @return bool
		 */
		public function is_elementor_offer_page() {
			$offer_id = $this->get_elementor_offer_page_id();
			if ( $offer_id > 0 && WFOCU_Common::get_offer_post_type_slug() === get_post_type( $offer_id ) ) {
				if ( class_exists( '\Elementor\Plugin' ) ) {
					return true;
				}
			}

			if ( empty( $offer_id ) ) {
				if ( class_exists( '\Elementor\Plugin' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @return false|int
		 */
		public function get_elementor_offer_page_id() {
			$offer_id = 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Elementor builder detection, capability checked by Elementor
			if ( isset( $_REQUEST['action'] ) && 'elementor' === $_REQUEST['action'] && isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Elementor builder detection, capability checked by Elementor
				$offer_id = absint( wp_unslash( $_REQUEST['post'] ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Elementor AJAX builder detection, capability checked by Elementor
			if ( $offer_id < 1 && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] && isset( $_REQUEST['editor_post_id'] ) && $_REQUEST['editor_post_id'] > 0 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Elementor AJAX builder detection, capability checked by Elementor
				$offer_id = absint( wp_unslash( $_REQUEST['editor_post_id'] ) );
			}
			if ( $offer_id < 1 && function_exists( 'get_the_ID' ) ) {
				$offer_id = get_the_ID();
			}

			return $offer_id;
		}


		/**
		 * Drop Elementor's cached copy of this offer on every front-end offer load.
		 *
		 * Offer pages are per-order and per-customer: they carry merge tags such as
		 * [wfocu_order_data key="customer_email"], [wfocu_order_data key="order_no"] and the
		 * product/price tags, all of which resolve against the order being upsold. Elementor
		 * stores ONE cached copy per offer post, shared by every visitor.
		 *
		 * Most widgets survive that safely - the cached copy keeps the literal shortcode text
		 * and Elementor re-runs do_shortcode() on every cache hit, and dynamic widgets are
		 * stored as placeholders and re-rendered per request. Widgets that are static AND
		 * resolve shortcodes themselves at render time (Accordion, Toggle, Image Gallery in
		 * core; Form and Video Playlist in Pro - all via Widget_Base::parse_text_editor(),
		 * which calls do_shortcode()) have the resolved value baked in instead, so the first
		 * customer to populate the cache would have their details served to everyone after
		 * them.
		 *
		 * Given how personalised offer pages are, we do not rely on that distinction: the
		 * cached copy is dropped on every offer load so each customer is rendered fresh.
		 * Mirrors the thank you page behaviour in WFFN_Thank_You_WC_Pages.
		 *
		 * @return void
		 */
		public function clear_elementor_cache_when_offer_loads() {
			$group = WFOCU_Core()->template_loader->current_template_group;
			if ( ! $group || 'elementor' !== $group->get_slug() ) {
				return;
			}
			if ( ! class_exists( '\Elementor\Core\Base\Document' ) || ! defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) ) {
				return;
			}

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();
			if ( empty( $offer_id ) ) {
				$offer_id = WFOCU_Core()->data->get( 'current_offer' );
			}
			if ( empty( $offer_id ) && function_exists( 'get_the_ID' ) ) {
				$offer_id = get_the_ID();
			}

			$offer_id = absint( $offer_id );
			if ( $offer_id <= 0 ) {
				return;
			}

			delete_post_meta( $offer_id, \Elementor\Core\Base\Document::CACHE_META_KEY );
		}
	}


	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Elementor(), 'elementor' );
}