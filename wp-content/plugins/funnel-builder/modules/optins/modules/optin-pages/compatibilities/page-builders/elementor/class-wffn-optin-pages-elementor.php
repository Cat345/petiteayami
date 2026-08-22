<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

use Elementor\Plugin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Optin_Pages_Elementor
 */
if ( ! class_exists( 'WFFN_Optin_Pages_Elementor' ) ) {
	#[AllowDynamicProperties]

	class WFFN_Optin_Pages_Elementor {

		private static $ins             = null;
		protected $template_type        = array();
		protected $design_template_data = array();
		protected $templates            = array();
		private $edit_id                = 0;
		private $url                    = '';

		/**
		 * WFFN_Optin_Pages_Elementor constructor.
		 */
		public function __construct() {
			$this->url = plugin_dir_url( WFOPP_PLUGIN_FILE );
			$this->process_url();
			add_filter( 'bwf_page_template', array( $this, 'get_page_template' ) );

			/**  Register widget category */
			add_action( 'elementor/elements/categories_registered', array( $this, 'add_wffn_elementor_category' ) );

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
			add_action( 'init', array( $this, 'setup' ) );

			add_filter( 'elementor/document/config', array( $this, 'hide_widgets_outside_optin' ), 11, 2 );

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'maybe_flush_elementor_element_cache' ) );
			}
		}

		private function process_url() {

			if ( isset( $_REQUEST['action'] ) && 'elementor' === $_REQUEST['action'] && isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				$this->edit_id = absint( $_REQUEST['post'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
			}
			if ( isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] && isset( $_REQUEST['editor_post_id'] ) && $_REQUEST['editor_post_id'] > 0 ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				$this->edit_id = absint( $_REQUEST['editor_post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
			}
		}

		public function add_default_templates() {
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['optin'] ) ? $templates['optin'] : array();
			$template  = array(
				'slug'        => 'elementor',
				'title'       => __( 'Elementor', 'funnel-builder' ),
				'button_text' => __( 'Edit', 'funnel-builder' ),
				'edit_url'    => add_query_arg(
					array(
						'post'   => $this->edit_id,
						'action' => 'elementor',
					),
					admin_url( 'post.php' )
				),
			);
			WFOPP_Core()->optin_pages->register_template_type( $template );

			if ( isset( $designs['elementor'] ) && is_array( $designs['elementor'] ) ) {
				foreach ( $designs['elementor'] as $d_key => $templates ) {

					if ( isset( $templates['pro'] ) && 'yes' === $templates['pro'] ) {
						$templates['license_exist'] = WFFN_Core()->admin->get_license_status();
					}
					WFOPP_Core()->optin_pages->register_template( $d_key, $templates, 'elementor' );

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
				WFOPP_Core()->optin_pages->register_template( 'elementor_1', $empty_template, 'elementor' );
			}

			return array();
		}

		/**
		 * @return WFFN_Optin_Pages_Elementor|null
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

		public function get_module_path() {
			return plugin_dir_path( WFFN_PLUGIN_FILE ) . 'modules/optin-pages/templates/template-default-boxed.php';
		}

		/**
		 * Adding a new widget category 'Flex Funnels'
		 */
		public function add_wffn_elementor_category() {
			$design = WFOPP_Core()->optin_pages->get_page_design( WFOPP_Core()->optin_pages->edit_id );
			if ( 'elementor' === $design['selected_type'] && class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::instance()->elements_manager->add_category(
					'wffn-flex',
					array(
						'title' => __( 'FunnelKit', 'funnel-builder' ),
						'icon'  => 'fa fa-plug',
					)
				);
			}
		}

		/**
		 * Register our widget on every request, not only when the current post resolves to an
		 * optin page.
		 *
		 * Elementor's element cache (`_elementor_element_cache`) is written by ANY non-admin
		 * render of the document - including REST and WP-Cron requests, where the post context
		 * this method used to test is unreliable or absent. If the widget type is not registered
		 * at that moment, `Elements_Manager::create_element_instance()` returns null, the widget
		 * is dropped from the element tree entirely, and the widget-less HTML is what gets cached
		 * and served to real visitors until the cache expires.
		 *
		 * Rendering stays context-guarded inside the widget, and `is_dynamic_content()` keeps our
		 * markup out of the cache. Panel visibility is scoped in hide_widgets_outside_optin().
		 */
		public function register_widgets() {

			require_once __DIR__ . '/widgets/class-elementor-wffn-optin-form-widget.php';

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFFN_Optin_Form_Widget() );
			} else {
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFFN_Optin_Form_Widget() );
			}

			do_action( 'wffn_optin_elementor_lite_loaded' );
		}

		/**
		 * Names of the Elementor widgets this module registers.
		 *
		 * @return array
		 */
		public static function get_widget_names() {
			return apply_filters( 'wffn_optin_elementor_widget_names', array( 'wffn-optin-form' ) );
		}

		/**
		 * Hide our widget from the Elementor panel (and its search) on every document that is not
		 * a FunnelKit Elementor optin page.
		 *
		 * The widget category is already scoped in add_wffn_elementor_category(), which keeps the
		 * widget out of the panel's category list. That is not sufficient on its own: the editor's
		 * shouldAddWidget() only tests `show_in_panel`, so a widget whose category is absent still
		 * lands in the elements collection and stays reachable through the panel search box.
		 *
		 * @param array $config
		 * @param int   $post_id
		 *
		 * @return array
		 */
		public function hide_widgets_outside_optin( $config, $post_id ) {
			if ( $this->is_wffn_optin_elementor_document( $post_id ) ) {
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
		 * Is the given post a FunnelKit optin page designed with Elementor?
		 *
		 * @param int $post_id
		 *
		 * @return bool
		 */
		private function is_wffn_optin_elementor_document( $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id <= 0 || ! function_exists( 'WFOPP_Core' ) ) {
				return false;
			}

			if ( WFOPP_Core()->optin_pages->get_post_type_slug() !== get_post_type( $post_id ) ) {
				return false;
			}

			$design = WFOPP_Core()->optin_pages->get_page_design( $post_id );

			return ( is_array( $design ) && isset( $design['selected_type'] ) && 'elementor' === $design['selected_type'] );
		}

		/**
		 * Clear Elementor's element cache for our optin pages once per plugin version.
		 *
		 * Runs in admin only and is gated on the plugin version, so it fires once per release
		 * rather than literally once. That is deliberate: widget markup can change between
		 * releases, and a stale cached copy would outlive the change. Existing installs may be carrying a cached
		 * copy of the document that was built while our widget was not registered. Changing the
		 * registration only affects future cache builds - a row already in postmeta keeps being
		 * served until its TTL expires, which can be up to a year.
		 *
		 * @return void
		 */
		public function maybe_flush_elementor_element_cache() {
			if ( ! class_exists( '\Elementor\Core\Base\Document' ) || ! defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) || ! function_exists( 'WFOPP_Core' ) ) {
				return;
			}

			$option_key = 'wffn_optin_elementor_element_cache_flushed';

			if ( WFFN_VERSION === get_option( $option_key ) ) {
				return;
			}

			global $wpdb;

			$cache_meta_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
			// Left over from the previous front-end cache-busting approach, which has been removed.
			$legacy_meta_key = '_wffn_optin_elementor_cache_marker';
			$post_type       = WFOPP_Core()->optin_pages->get_post_type_slug();

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

			update_option( $option_key, WFFN_VERSION, false );
		}

		/**
		 * register optin script in elementor editor mode
		 */
		public function register_scripts() {
			if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview instanceof \Elementor\Preview && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				$post_id = get_the_ID();
				if ( apply_filters( 'wfop_show_flag_in_phone_field', true ) && WFOPP_Core()->optin_pages->get_post_type_slug() === get_post_type( $post_id ) && WFFN_Common::wffn_is_funnel_pro_active() ) {
					WFFN_Optin_Pages::enqueue_phone_flag_editor_assets();
				}
			}
		}

		public function setup() {
			if ( did_action( 'elementor/loaded' ) ) {
				add_action( 'elementor/theme/register_conditions', array( $this, 'register_conditions' ) );
			}
		}

		public function register_conditions( $conditions_manager ) {
			require plugin_dir_path( WFFN_PLUGIN_FILE ) . 'modules/optins/modules/optin-pages/compatibilities/page-builders/elementor/conditions/class-wffn-op-pages.php';
			$new_condition = new ElementorPro\Modules\ThemeBuilder\Conditions\WFFN_OP_Pages(
				array(
					'post_type' => WFOPP_Core()->optin_pages->get_post_type_slug(),
				)
			);
			$conditions_manager->get_condition( 'singular' )->register_sub_condition( $new_condition );
		}
	}

	WFFN_Optin_Pages_Elementor::get_instance();
}
