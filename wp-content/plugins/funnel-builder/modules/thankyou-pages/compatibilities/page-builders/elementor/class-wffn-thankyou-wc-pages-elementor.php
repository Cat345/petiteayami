<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

use Elementor\Plugin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_ThankYou_WC_Pages_Elementor' ) ) {
	/**
	 * Funnel Public facing functionality
	 * Class WFFN_Public
	 */
	#[AllowDynamicProperties]
	class WFFN_ThankYou_WC_Pages_Elementor {

		private static $ins             = null;
		protected $template_type        = array();
		protected $design_template_data = array();
		protected $templates            = array();
		private $url                    = '';

		/**
		 * WFFN_Session constructor..
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			$this->url = plugin_dir_url( __FILE__ );
			add_filter( 'bwf_page_template', array( $this, 'get_page_template' ) );

			/**  Register widget category */
			add_action( 'elementor/elements/categories_registered', array( $this, 'wfty_elementor_category' ) );
			/** Register widgets */
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
			}

			add_action( 'elementor/editor/init', array( $this, 'maybe_setup_wfty_fonts' ), 500 );

			/** show short-code */
			add_action( 'elementor/editor/init', array( $this, 'maybe_register_widget_message' ), 500 );
			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'maybe_print_shortcodes_helpbox' ) );
			add_action( 'init', array( $this, 'setup' ) );

			add_filter( 'elementor/document/config', array( $this, 'hide_widgets_outside_thankyou' ), 11, 2 );

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'maybe_flush_elementor_element_cache' ) );
			}
		}

		/**
		 * Include fonts
		 */
		public function maybe_setup_wfty_fonts() {
			add_action( 'wp_head', array( $this, 'enqueue_styles' ) );
		}

		/**
		 * Adding a new widget category 'Custom'
		 */
		public function wfty_elementor_category() {
			$edit_id = WFFN_Core()->thank_you_pages->get_edit_id();
			if ( ! empty( $edit_id ) && class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::instance()->elements_manager->add_category(
					'wffn_woo_thankyou',
					array(
						'title' => __( 'FunnelKit', 'funnel-builder' ),
						'icon'  => 'fa fa-plug',
					)
				);
			}
		}

		/**
		 * @throws Exception
		 */
		/**
		 * Register our widgets on every request, not only when the current post resolves to a
		 * thank-you page.
		 *
		 * Elementor's element cache (`_elementor_element_cache`) is written by ANY non-admin
		 * render of the document - including REST and WP-Cron requests, where the post context
		 * this method used to test is unreliable or absent. If the widget type is not registered
		 * at that moment, `Elements_Manager::create_element_instance()` returns null, the widget
		 * is dropped from the element tree entirely, and the widget-less HTML is what gets cached
		 * and served to real visitors until the cache expires.
		 *
		 * Rendering stays context-guarded inside the widgets, and `is_dynamic_content()` keeps our
		 * markup out of the cache. Panel visibility is scoped in hide_widgets_outside_thankyou().
		 *
		 * @throws Exception
		 */
		public function register_widgets() {
			$this->includes();

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFTY_Order_Details_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFTY_Customer_Details_Widget() );
			} else {
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFTY_Order_Details_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFTY_Customer_Details_Widget() );
			}

			do_action( 'wffn_register_elementor_widgets' );
		}

		/**
		 * Names of the Elementor widgets this module registers.
		 *
		 * @return array
		 */
		public static function get_widget_names() {
			return apply_filters( 'wffn_thankyou_elementor_widget_names', array( 'wfty-order-detail', 'wfty-customer-detail' ) );
		}

		/**
		 * Hide our widgets from the Elementor panel (and its search) on every document that is not
		 * a FunnelKit thank-you page.
		 *
		 * The widget category is already scoped in wfty_elementor_category(), which keeps the
		 * widgets out of the panel's category list. That is not sufficient on its own: the editor's
		 * shouldAddWidget() only tests `show_in_panel`, so a widget whose category is absent still
		 * lands in the elements collection and stays reachable through the panel search box.
		 *
		 * @param array $config
		 * @param int   $post_id
		 *
		 * @return array
		 */
		public function hide_widgets_outside_thankyou( $config, $post_id ) {
			if ( $this->is_wffn_thankyou_document( $post_id ) ) {
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
		 * Is the given post a FunnelKit thank-you page?
		 *
		 * @param int $post_id
		 *
		 * @return bool
		 */
		private function is_wffn_thankyou_document( $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id <= 0 || ! function_exists( 'WFFN_Core' ) ) {
				return false;
			}

			return ( WFFN_Core()->thank_you_pages->get_post_type_slug() === get_post_type( $post_id ) );
		}

		/**
		 * Clear Elementor's element cache for our thank-you pages once per plugin version.
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
			if ( ! class_exists( '\Elementor\Core\Base\Document' ) || ! defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) || ! function_exists( 'WFFN_Core' ) ) {
				return;
			}

			$option_key = 'wffn_thankyou_elementor_element_cache_flushed';

			if ( WFFN_VERSION === get_option( $option_key ) ) {
				return;
			}

			global $wpdb;

			$cache_meta_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
			$post_type      = WFFN_Core()->thank_you_pages->get_post_type_slug();

			/*
			 * Collect the affected IDs before deleting. The DELETE below bypasses
			 * delete_post_meta(), so nothing invalidates the 'post_meta' object cache for those
			 * posts. On a site running a persistent object cache the row would be gone while
			 * get_post_meta() kept serving the stale value.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time upgrade routine
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s",
					$cache_meta_key,
					$post_type
				)
			);

			if ( ! empty( $post_ids ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time upgrade routine
				$wpdb->query(
					$wpdb->prepare(
						"DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s",
						$cache_meta_key,
						$post_type
					)
				);

				foreach ( $post_ids as $post_id ) {
					wp_cache_delete( (int) $post_id, 'post_meta' );
				}
			}

			update_option( $option_key, WFFN_VERSION, false );
		}

		/**
		 * Include widget Files
		 */
		public function includes() {
			require_once __DIR__ . '/widget/class-elementor-wfty-order-details-widget.php';
			require_once __DIR__ . '/widget/class-elementor-wfty-customer-details-widget.php';

			do_action( 'wffn_include_elementor_widget' );
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles() {
			wp_enqueue_style( 'wffn_woo_thankyou_page', plugin_dir_url( WFTY_PLUGIN_FILE ) . 'assets/css/wffn-woo-thankyou-el-widgets.css', array(), WFFN_VERSION . time(), 'all' );
		}


		public function add_default_templates() {

			$template = array(
				'slug'        => 'elementor',
				'title'       => __( 'Elementor', 'funnel-builder' ),
				'button_text' => __( 'Edit', 'funnel-builder' ),
				'edit_url'    => add_query_arg(
					array(
						'post'   => WFFN_Core()->thank_you_pages->get_edit_id(),
						'action' => 'elementor',
					),
					admin_url( 'post.php' )
				),
			);
			WFFN_Core()->thank_you_pages->register_template_type( $template );
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['wc_thankyou'] ) ? $templates['wc_thankyou'] : array();

			if ( isset( $designs['elementor'] ) && is_array( $designs['elementor'] ) ) {
				foreach ( $designs['elementor'] as $d_key => $templates ) {

					if ( isset( $templates['pro'] ) && 'yes' === $templates['pro'] ) {
						$templates['license_exist'] = WFFN_Core()->admin->get_license_status();
					}
					WFFN_Core()->thank_you_pages->register_template( $d_key, $templates, 'elementor' );

				}
			} else {

				$empty_template = array(
					'type'               => 'view',
					'import'             => 'no',
					'show_import_popup'  => 'no',
					'slug'               => 'elementor_1',
					'build_from_scratch' => true,
					'group'              => array(
						'inline',
						'popup',
					),
				);
				WFFN_Core()->thank_you_pages->register_template( 'elementor_1', $empty_template, 'elementor' );
			}

			return array();
		}

		/**
		 * @return WFFN_ThankYou_WC_Pages_Elementor|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}


		/**
		 * Get page template fiter callback for elementor preview mode
		 *
		 * @param string $template page template.
		 *
		 * @return string
		 */
		public function get_page_template( $template ) {

			$response = WFFN_Common::check_builder_status( 'elementor' );
			if ( is_singular() && ( true === $response['found'] ) ) {
				if ( version_compare( $response['version'], '3.2.0', '<=' ) ) {
					$el_build = Plugin::$instance->db->is_built_with_elementor( get_the_ID() );
				} else {
					$el_build = Plugin::$instance->documents->get( get_the_ID() )->is_built_with_elementor();
				}
				if ( true === $el_build ) {
					$document = Plugin::$instance->documents->get_doc_for_frontend( get_the_ID() );

					if ( $document ) {
						$template = $document->get_meta( '_wp_page_template' );
					}
				}
			}

			return $template;
		}

		public function maybe_register_widget_message() {
			$id       = \Elementor\Plugin::$instance->editor->get_post_id();
			$get_post = get_post( $id );

			if ( WFFN_Core()->thank_you_pages->get_post_type_slug() === $get_post->post_type ) {
				add_action( 'wp_footer', array( $this, 'print_inline_script' ), 9999 );
			}
		}

		public function print_inline_script() {

			?>
			<script>

				(function ($) {
					"use strict";

					var wftySupportedMergeTagsWidgets =<?php echo wp_json_encode( $this->get_merge_tags_supported_widgets() ); ?>;

					elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
						if (wftySupportedMergeTagsWidgets.indexOf(model.get('widgetType')) === -1) {
							return;
						}
						var html = '\t\t\t<div class="wfty-el-customize-note">\n' +
							'\t\t\t\t\t\t\n' +
							'\t\t<div class="elementor-panel-alert elementor-panel-alert-info"><?php esc_html_e( 'You can also add personalization tags to this element using shortcodes. ', 'funnel-builder' ); ?><a style="text-decoration: underline;" onclick="wfty_show_tb(\'FunnelKit Shortcodes\', \'wfty_shortcode_help_box\');" href="javascript:void(0)"><?php esc_html_e( 'Click here to show the available shortcodes', 'funnel-builder' ); ?></a> </div>\n'
						'\t\t\t\t\t</div>\n' +
						'\t\t';
						$(".elementor-panel-navigation").eq(0).after(html);
					});

				})(jQuery);
			</script>
			<?php
		}

		public function get_merge_tags_supported_widgets() {
			return apply_filters( 'merge_tags_supported_widgets', array( 'heading', 'text-editor', 'shortcode', 'wfty-customer-detail', 'wfty-order-detail', 'wfty-order-download' ) );
		}

		public function maybe_print_shortcodes_helpbox() {
			include_once WFFN_Core()->thank_you_pages->get_module_path() . '/includes/help-shortcodes.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomFunction
		}

		public function setup() {
			if ( did_action( 'elementor/loaded' ) ) {
				add_action( 'elementor/theme/register_conditions', array( $this, 'register_conditions' ) );
			}
		}

		public function register_conditions( $conditions_manager ) {
			require plugin_dir_path( WFFN_PLUGIN_FILE ) . 'modules/thankyou-pages/compatibilities/page-builders/elementor/conditions/class-wffn-ty-pages.php';
			$new_condition = new ElementorPro\Modules\ThemeBuilder\Conditions\WFFN_TY_Pages(
				array(
					'post_type' => WFFN_Core()->thank_you_pages->get_post_type_slug(),
				)
			);
			$conditions_manager->get_condition( 'singular' )->register_sub_condition( $new_condition );
		}
	}

	WFFN_ThankYou_WC_Pages_Elementor::get_instance();
}

