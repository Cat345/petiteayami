<?php
if ( ! class_exists( 'WFOCU_OXY' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_OXY {
		private static $ins          = null;
		private static $front_locals = array();
		private $section_slug        = 'woofunnels';
		private $tab_slug            = 'woofunnels';
		public $modules_instance     = array();
		protected $module_path       = array();
		private $edit_id             = 0;
		private $post                = null;


		private function __construct() {
			$this->module_path = __DIR__ . '/modules/';
			$this->register();
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}


		private function register() {
			$this->register_oxygen_section();
			add_action( 'init', array( $this, 'init_extension' ), 21 );
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 1003 );
			add_action( 'oxygen_enqueue_frontend_scripts', array( $this, 'enable_self_page_css' ) );
			add_action( 'wfocu_template_removed', array( $this, 'delete_oxy_data' ) );
		}


		private function register_oxygen_section() {
			if ( isset( $_GET['ct_template'] ) && isset( $_GET['ct_builder'] ) ) {
				return;
			}
			/* show a section in +Add */
			add_action( 'oxygen_add_plus_sections', array( $this, 'add_plus_sections' ) );
			add_action( 'oxygen_add_plus_' . $this->section_slug . '_section_content', array( $this, 'add_plus_subsections_content' ) );
		}


		public function remove_front_end_css_hook() {
			remove_action( 'wp_enqueue_scripts', array( WFOCU_Core()->assets, 'wfocu_add_upsell_frontend_styles' ), 30 );
		}


		public function add_plus_sections() {
			if ( did_action( "oxygen_add_plus_{$this->section_slug}_section_content" ) > 0 ) {
				return;
			}
			/* show a section in +Add dropdown menu and name it "My Custom Elements" */
			CT_Toolbar::oxygen_add_plus_accordion_section( $this->section_slug, __( 'FunnelKit', 'woofunnels-aero-checkout' ) );
		}


		public function add_plus_subsections_content() {
			if ( did_action( 'oxygen_add_plus_woofunnels_woofunnels' ) > 0 ) {
				return;
			}
			do_action( 'oxygen_add_plus_woofunnels_woofunnels' );
		}

		public static function is_template_editor() {
			return isset( $_REQUEST['action'] ) && ( 'ct_save_components_tree' == $_REQUEST['action'] || 'ct_render_innercontent' == $_REQUEST['action'] );
		}


		public function init_extension() {
			if ( self::is_template_editor() ) {
				add_action( 'wp', array( $this, 'prepare_frontend_module' ), 10 );

				return;
			}
			if ( ! class_exists( 'CT_Component' ) ) {
				return;
			}
			$post_id = 0;
			if ( isset( $_REQUEST['post_id'] ) && $_REQUEST['post_id'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['post_id'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_REQUEST['oxy_wfocu_id'] ) ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['oxy_wfocu_id'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 && isset( $_REQUEST['action'] ) ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['post'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$post = get_post( $post_id );
			if ( ! is_null( $post ) && $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {

				$this->post = $post;
				$this->prepare_module();
				add_action(
					'admin_head',
					function () {
						add_filter( 'post_type_link', array( $this, 'change_edit_with_oxygen_link' ), 10, 2 );
					}
				);
				/* Remove Upstroke theme compatabilty css */
				add_action( 'wp', array( $this, 'remove_front_end_css_hook' ), 11 );

				return;
			}

			add_action( 'wp', array( $this, 'prepare_frontend_module' ), 10 );
		}

		public function prepare_frontend_module() {
			global $post;
			if ( is_null( $post ) ) {
				return;
			}
			$this->post = $post;

			if ( $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {

				add_filter( 'wfocu_valid_state_for_data_setup', '__return_true' );
				WFOCU_Core()->template_loader->offer_id = $post->ID;

				if ( WFOCU_Core()->template_loader->current_template_group instanceof WFOCU_Template_Group_Oxygen ) {
					/* Remove Upstroke theme compatabilty css */
					add_action( 'wp', array( $this, 'remove_front_end_css_hook' ), 11 );
				}
			}

			$this->prepare_module();
		}

		public function prepare_module() {
			if ( is_null( $this->post ) ) {
				return;
			}

			$id = $this->post->ID;

			$design = WFOCU_Common::get_offer( $id );

			if ( empty( $design ) || empty( $design->template_group ) ) {
				return;
			}

			if ( 'oxy' !== $design->template_group || ! class_exists( 'OxyEl' ) ) {
				return;
			}
			$modules = $this->get_modules();
			if ( ! empty( $modules ) ) {
				include __DIR__ . '/class-abstract-wfocu-fields.php';
				include __DIR__ . '/class-wfocu-html-block-oxy.php';
				foreach ( $modules as $key => $module ) {
					if ( ! file_exists( $module['path'] ) ) {
						continue;
					}

					$this->modules_instance[ $key ] = include $module['path'];
				}
			}
		}


		private function get_modules() {
			$modules = array(
				'accept_button'      => array(
					'name' => __( 'WF Accept Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-button.php',
				),
				'reject_button'      => array(
					'name' => __( 'WF Reject Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-button.php',
				),
				'accept_link'        => array(
					'name' => __( 'WF Accept Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-link.php',
				),
				'reject_link'        => array(
					'name' => __( 'WF Reject Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-link.php',
				),
				'product_title'      => array(
					'name' => __( 'WF Product Title', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-title.php',
				),
				'product_images'     => array(
					'name' => __( 'WF Product Images', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-images.php',
				),
				'product_short_desc' => array(
					'name' => __( 'WF Product Short Description', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-short-desc.php',
				),
				'variation_selector' => array(
					'name' => __( 'WF Variation Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'variation-selector.php',
				),
				'qty_selector'       => array(
					'name' => __( 'WF Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'qty-selector.php',
				),
				'offer_price'        => array(
					'name' => __( 'WF Offer Price', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'offer-price.php',
				),

			);

			return apply_filters( 'wfocu_oxy_modules', $modules, $this );
		}

		public function change_edit_with_oxygen_link( $link, $post ) {
			$link = add_query_arg( array( 'oxy_wfocu_id' => $post->ID ), $link );

			return $link;
		}

		public function add_admin_bar_link() {
			/**
			 * @var $wp_admin_bar WP_Admin_Bar;
			 */ global $wp_admin_bar;

			if ( ! is_null( $wp_admin_bar ) ) {

				$node = $wp_admin_bar->get_node( 'edit_post_template' );
				if ( ! is_null( $node ) ) {
					$node = (array) $node;
					global $post;
					if ( ! is_null( $post ) && $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {
						$wfacp_id     = $post->ID;
						$href         = $node['href'];
						$node['href'] = add_query_arg(
							array(
								'ct_builder'   => 'true',
								'oxy_wfocu_id' => $wfacp_id,
							),
							$href
						);
						$wp_admin_bar->add_node( $node );
					}
				}
			}
		}

		public function enable_self_page_css() {
			if ( apply_filters( 'bwf_enable_oxygen_universal_css', true, $this ) ) {
				return;
			}
			add_filter( 'pre_option_oxygen_vsb_universal_css_cache', array( $this, 'disable_universal_css' ) );
		}

		public function disable_universal_css( $status ) {
			global $post;
			if ( ! is_null( $post ) && $post->post_type == WFOCU_Common::get_offer_post_type_slug() ) {
				$status = 'false';
			}

			return $status;
		}

		public function delete_oxy_data( $post_id ) {
			delete_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_other_template' ) );
			delete_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_shortcodes' ) );
			delete_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_page_settings' ) );
			delete_post_meta( $post_id, WFOCU_Common::oxy_get_meta_prefix( 'ct_builder_json' ) );
		}
	}

	WFOCU_OXY::get_instance();
}
