<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFACP_Elementor' ) ) {
	#[\AllowDynamicProperties]
	class WFACP_Elementor {
		private static $ins = null;

		private $is_elementor         = false;
		private static $front_locals  = array();
		private $template_file        = '';
		private $widget_dir           = '';
		private $wfacp_id             = 0;
		private $widget_initialize    = false;
		private static $widget_names  = array();
		private $incomplete_css_posts = array();

		private function __construct() {

			$this->widget_dir    = WFACP_Core()->dir( 'builder/elementor/widgets' );
			$this->template_file = WFACP_Core()->dir( 'builder/elementor/template/template.php' );
			$this->register();
			add_action( 'wfacp_template_removed', array( $this, 'delete_elementor_data' ) );
			add_action( 'wfacp_duplicate_pages', array( $this, 'duplicate_template' ), 10, 3 );
			add_action( 'wfacp_update_page_design', array( $this, 'update_page_design' ), 10, 2 );
			add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_categories' ) );
			add_action( 'woocommerce_checkout_terms_and_conditions', array( $this, 'remove_the_content_filter' ) );
		}


		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}


		public static function set_locals( $name, $id ) {
			self::$front_locals[ $name ] = $id;
		}

		public static function get_locals() {
			return self::$front_locals;
		}

		private function widgets() {
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'initialize_widgets' ) );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'initialize_widgets' ) );
			}
		}

		public function initialize_widgets( $widgets_manager = null ) {
			if ( $this->widget_initialize ) {
				return;
			}

			$registered_before = ( $widgets_manager instanceof \Elementor\Widgets_Manager ) ? array_keys( $widgets_manager->get_widget_types() ) : array();

			include_once __DIR__ . '/class-abstract-wfacp-fields.php';
			include_once __DIR__ . '/class-wfacp-html-block-elementor.php';
			foreach ( glob( $this->widget_dir . '/class-elementor-*.php' ) as $_field_filename ) {
				require_once $_field_filename;
			}
			$this->widget_initialize = true;

			if ( $widgets_manager instanceof \Elementor\Widgets_Manager ) {
				$registered_after   = array_keys( $widgets_manager->get_widget_types() );
				self::$widget_names = array_values( array_diff( $registered_after, $registered_before ) );
			}
		}

		/**
		 * Names of the Elementor widgets this module registers.
		 * Populated during registration; falls back to the known set.
		 *
		 * @return array
		 */
		public static function get_widget_names() {
			$names = ! empty( self::$widget_names ) ? self::$widget_names : array( 'wfacp_form' );

			return apply_filters( 'wfacp_elementor_widget_names', $names );
		}

		/**
		 * Hide our widgets from the Elementor panel (and its search) on every document that is
		 * not a FunnelKit Elementor checkout.
		 *
		 * The widget category is already scoped in add_widget_categories(), which keeps the
		 * widget out of the panel's category list. That is not sufficient on its own: the
		 * editor's shouldAddWidget() only tests `show_in_panel`, so a widget whose category is
		 * absent still lands in the elements collection and stays reachable through the panel
		 * search box. `hide_on_search` exists precisely for that case.
		 *
		 * @param array $config
		 * @param int   $post_id
		 *
		 * @return array
		 */
		public function hide_widgets_outside_checkout( $config, $post_id ) {
			if ( $this->is_wfacp_elementor_document( $post_id ) ) {
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
		 * Is the given post a FunnelKit checkout page designed with Elementor?
		 *
		 * @param int $post_id
		 *
		 * @return bool
		 */
		private function is_wfacp_elementor_document( $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id <= 0 || WFACP_Common::get_post_type_slug() !== get_post_type( $post_id ) ) {
				return false;
			}

			$design = WFACP_Common::get_page_design( $post_id );

			return ( isset( $design['selected_type'] ) && 'elementor' === $design['selected_type'] );
		}

		/**
		 * Clear Elementor's element cache for our checkout pages once per plugin version.
		 *
		 * Runs in admin only and is gated on the plugin version, so it fires once per release
		 * rather than literally once. That is deliberate: widget markup can change between
		 * releases, and a stale cached copy would outlive the change. Existing installs may be carrying a
		 * cached copy of the document that was built while our widgets were not registered
		 * (see the comment in register()). Changing the registration only affects future cache
		 * builds - a row already in postmeta keeps being served until its TTL expires, which
		 * can be up to a year. This clears them once on upgrade.
		 *
		 * @return void
		 */
		public function maybe_flush_elementor_element_cache() {
			if ( ! class_exists( '\Elementor\Core\Base\Document' ) || ! defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) ) {
				return;
			}

			$option_key = 'wfacp_elementor_element_cache_flushed';

			if ( WFACP_VERSION === get_option( $option_key ) ) {
				return;
			}

			global $wpdb;

			$cache_meta_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
			// Left over from the previous front-end cache-busting approach, which has been removed.
			$legacy_meta_key = '_wfacp_elementor_cache_marker';
			$post_type       = WFACP_Common::get_post_type_slug();

			/*
			 * Collect the affected IDs before deleting. The DELETE below bypasses
			 * delete_post_meta(), so nothing invalidates the 'post_meta' object cache for those
			 * posts. On a site running a persistent object cache the row would be gone while
			 * get_post_meta() kept serving the stale (poisoned) value.
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

			update_option( $option_key, WFACP_VERSION, false );
		}

		/**
		 * Elementor builds a checkout page's post-{id}.css by reading each widget's controls.
		 * WFACP_EL_Fields::register_controls() bails when wfacp_template() is null, so on a
		 * request without checkout context - REST, WP-Cron, or any other background render -
		 * our widget registers no controls and the generated file contains none of the
		 * `#wfacp-e-form` / `#place_order` rules. Persisting that file leaves real customers
		 * with an unstyled checkout until something regenerates it.
		 *
		 * Elementor writes the file straight after parsing (Base::update_file() does
		 * `content = parse_content(); if ( content ) { write(); }`), so deleting it from inside
		 * the parse hook is undone by the write that follows. Record the post here and drop the
		 * file on shutdown instead; the next real checkout render rebuilds it in full.
		 *
		 * An already-complete file is untouched: Elementor only re-parses when the file is
		 * missing or stale, so this hook does not even fire for it.
		 *
		 * @param \Elementor\Core\Files\CSS\Post $css_file
		 *
		 * @return void
		 */
		public function flag_incomplete_post_css( $css_file ) {
			if ( ! is_object( $css_file ) || ! method_exists( $css_file, 'get_post_id' ) ) {
				return;
			}

			$post_id = absint( $css_file->get_post_id() );

			if ( $post_id <= 0 || WFACP_Common::get_post_type_slug() !== get_post_type( $post_id ) ) {
				return;
			}

			// A real checkout render has the template loaded, so the controls - and the CSS - are complete.
			if ( function_exists( 'wfacp_template' ) && null !== wfacp_template() ) {
				return;
			}

			$this->incomplete_css_posts[ $post_id ] = true;
		}

		/**
		 * @return void
		 */
		public function drop_incomplete_post_css() {
			if ( empty( $this->incomplete_css_posts ) || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				return;
			}

			foreach ( array_keys( $this->incomplete_css_posts ) as $post_id ) {
				$css_file = new \Elementor\Core\Files\CSS\Post( absint( $post_id ) );
				$css_file->delete();
			}

			$this->incomplete_css_posts = array();
		}

		public function add_widget_categories( $elements_manager ) {
			$design = WFACP_Common::get_page_design( WFACP_Common::get_id() );
			if ( 'elementor' === $design['selected_type'] && class_exists( '\Elementor\Plugin' ) ) {
				$elements_manager->add_category(
					'woofunnels-aero-checkout',
					array(
						'title' => __( 'FunnelKit', 'funnel-builder' ),
						'icon'  => 'fa fa-plug',
					)
				);
			}
		}

		private function register() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Checking for Elementor editor/preview mode parameters
			if ( isset( $_REQUEST['elementor-preview'] ) || ( isset( $_REQUEST['action'] ) && ( 'elementor' == $_REQUEST['action'] || 'elementor_ajax' == $_REQUEST['action'] ) ) || ( isset( $_REQUEST['preview_id'] ) && isset( $_REQUEST['preview_nonce'] ) ) ) {
				$this->is_elementor = true;
				add_filter( 'wfacp_is_theme_builder', '__return_true' );
			}

			add_filter( 'wfacp_post', array( $this, 'check_current_page_is_aero_page' ) );

			/**
			 * Register our widgets on every request, not only when a checkout page is detected.
			 *
			 * Elementor's element cache (`_elementor_element_cache`) is written by ANY non-admin
			 * render of the document - including REST and WP-Cron requests, which never reach
			 * `wfacp_checkout_page_found`. If the widget type is not registered at that moment,
			 * `Elements_Manager::create_element_instance()` returns null, the widget is dropped
			 * from the element tree entirely, and the form-less HTML is what gets cached.
			 *
			 * Rendering stays context-guarded inside the widget itself, and `is_dynamic_content()`
			 * guarantees Elementor stores a re-rendered placeholder rather than baking our markup
			 * into the cache. Panel visibility is scoped in hide_widgets_outside_checkout().
			 */
			$this->widgets();
			add_filter( 'elementor/document/config', array( $this, 'hide_widgets_outside_checkout' ), 11, 2 );

			add_action( 'elementor/css-file/post/parse', array( $this, 'flag_incomplete_post_css' ), 99 );
			add_action( 'shutdown', array( $this, 'drop_incomplete_post_css' ), 1 );

			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'maybe_flush_elementor_element_cache' ) );
			}

			add_action( 'wfacp_checkout_page_found', array( $this, 'initialize_elementor_widgets' ) );
			add_action( 'wfacp_register_template_types', array( $this, 'register_template_type' ), 11 );
			add_filter( 'wfacp_register_templates', array( $this, 'register_templates' ) );
			add_action( 'wfacp_template_load', array( $this, 'load_elementor_abs_class' ), 10, 2 );
			add_filter( 'wfacp_template_edit_link', array( $this, 'add_template_edit_link' ), 10, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 101 );
		}


		public function check_current_page_is_aero_page( $post ) {
			if ( WFACP_Common::is_theme_builder() && true == $this->is_elementor ) {
				if ( isset( $_REQUEST['post'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Nonce verification not required for Elementor editor page detection
					$temp_id = absint( $_REQUEST['post'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Nonce verification not required for Elementor editor page detection
				} elseif ( isset( $_REQUEST['editor_post_id'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Nonce verification not required for Elementor editor page detection
					$temp_id = absint( $_REQUEST['editor_post_id'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Nonce verification not required for Elementor editor page detection
				} else {
					$temp_id = 0;
				}
				$post = get_post( $temp_id );
			}

				return $post;
		}

		public function initialize_elementor_widgets( $post_id ) {
			$design = WFACP_Common::get_page_design( $post_id );
			if ( 'elementor' == $design['selected_type'] && class_exists( '\Elementor\Plugin' ) ) {
				$this->wfacp_id = $post_id;
				global $post;
				$post = get_post( $this->wfacp_id );
				add_filter( 'the_content', array( $this, 'change_global_post_var_to_our_page_post' ), 5 );
				add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'custom_admin_style' ) );
				add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_custom_font' ) );
			}
		}

		public function change_global_post_var_to_our_page_post( $content ) {
			global $post;
			$post = get_post( $this->wfacp_id );

			return $content;
		}


		public function enqueue_scripts() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Checking for Elementor preview parameter
			if ( isset( $_REQUEST['elementor-preview'] ) ) {
				$preview_id = absint( $_REQUEST['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				if ( 'wfacp_checkout' === get_post_type( $preview_id ) ) {
					wp_enqueue_script( 'wfacp_elementor_edit', WFACP_Core()->url( '/builder/elementor/js/elementor-preview-iframe.js' ), array( 'wfacp_checkout_js' ), WFACP_VERSION_DEV, true );
				}
			}
		}


		/**
		 * @param $loader WFACP_Template_loader
		 */
		public function register_template_type( $loader ) {
			$template = array(
				'slug'    => 'elementor',
				'title'   => __( 'Elementor', 'funnel-builder' ),
				'filters' => WFACP_Common::get_template_filter(),
			);

			$loader->register_template_type( $template );
		}

		public function register_templates( $designs ) {
			$templates = WooFunnels_Dashboard::get_all_templates();

			$designs['elementor'] = ( isset( $templates['wc_checkout'] ) && isset( $templates['wc_checkout']['elementor'] ) ) ? $templates['wc_checkout']['elementor'] : array();

			if ( is_array( $designs['elementor'] ) && count( $designs['elementor'] ) > 0 ) {
				foreach ( $designs['elementor'] as $key => $val ) {
					$val['path']                  = WFACP_BUILDER_DIR . '/elementor/template/template.php';
					$designs['elementor'][ $key ] = $val;
				}
			}

			return $designs;
		}

		public function load_elementor_abs_class( $wfacp_id, $template = array() ) {
			if ( empty( $template ) ) {
				return;
			}
			if ( 'elementor' === $template['selected_type'] ) {
				$this->check_css_file_issue( $wfacp_id );
				include_once WFACP_Core()->dir( 'builder/elementor/class-wfacp-elementor-template.php' );
			}
		}

		public function add_template_edit_link( $links, $admin ) {
			$url                = add_query_arg(
				array(
					'post'   => $admin->wfacp_id,
					'action' => 'elementor',
				),
				admin_url( 'post.php' )
			);
			$links['elementor'] = array(
				'url'         => $url,
				'button_text' => __( 'Edit', 'elementor' ),
			);

			return $links;
		}

		public function custom_admin_style() {
			echo '<style>';
			include __DIR__ . '/css/custom_admin_style.css';
			echo '</style>';
		}

		public function register_custom_font() {
			wp_enqueue_style( 'wfacp-icons', WFACP_PLUGIN_URL . '/admin/assets/css/wfacp-font.css', null, WFACP_VERSION );
		}

		/**
		 * Delete Elementor saved data from postmeta of aerocheckout ID
		 *
		 * @param $post_id
		 */
		public function delete_elementor_data( $post_id ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			delete_post_meta( $post_id, '_elementor_version' );
			delete_post_meta( $post_id, '_elementor_template_type' );
			delete_post_meta( $post_id, '_elementor_edit_mode' );
			delete_post_meta( $post_id, '_elementor_data' );
			delete_post_meta( $post_id, '_elementor_controls_usage' );
			delete_post_meta( $post_id, '_elementor_css' );
		}

		public function update_page_design( $page_id, $data ) {
			if ( ! isset( $page_id ) ) {
				return;
			}

			if ( ! is_array( $data ) || count( $data ) == 0 ) {
				return;
			}
			if ( ! isset( $data['selected_type'] ) || $data['selected_type'] !== 'elementor' ) {
				return;
			}
		}

		public function duplicate_template( $new_post_id, $post_id, $data ) {
			if ( 'elementor' === $data['_wfacp_selected_design']['selected_type'] ) {
				if ( class_exists( 'SitePress' ) ) {
					WFACP_Common::copy_meta( $post_id, $new_post_id );

					return;
				}
				$contents = get_post_meta( $post_id, '_elementor_data', true );
				$data     = array(
					'_elementor_version'       => get_post_meta( $post_id, '_elementor_version', true ),
					'_elementor_template_type' => get_post_meta( $post_id, '_elementor_template_type', true ),
					'_elementor_edit_mode'     => get_post_meta( $post_id, '_elementor_edit_mode', true ),
				);

				foreach ( $data as $meta_key => $meta_value ) {
					update_post_meta( $new_post_id, $meta_key, $meta_value );
				}

				/**
				 * @var $instance WFACP_Elementor_Importer
				 */
				$instance = new WFACP_Elementor_Importer();
				if ( ! is_null( $instance ) ) {
					if ( is_array( $contents ) ) {
						$contents = json_encode( $contents );

					}
					$instance->delete_page_meta = false;
					$instance->import_aero_template( $new_post_id, $contents );
				}
				update_post_meta( $new_post_id, '_wp_page_template', get_post_meta( $post_id, '_wp_page_template', true ) );
			}
		}


		public function check_css_file_issue( $wfacp_id ) {
			if ( 'internal' == get_option( 'elementor_css_print_method' ) ) {
				return;
			}
			$elementor_css_present = get_post_meta( $wfacp_id, '_elementor_css', true );
			if ( ! empty( $elementor_css_present ) ) {
				delete_post_meta( $wfacp_id, '_elementor_css' );
			}
		}
		public function remove_the_content_filter() {
			if ( defined( 'BRICKS_VERSION' ) ) {
				// If Bricks is active, we don`t need to remove the filter that changes the global post variable.
				return;
			}
			remove_filter( 'the_content', array( $this, 'change_global_post_var_to_our_page_post' ), 5 );
		}
	}

	WFACP_Elementor::get_instance();
}
