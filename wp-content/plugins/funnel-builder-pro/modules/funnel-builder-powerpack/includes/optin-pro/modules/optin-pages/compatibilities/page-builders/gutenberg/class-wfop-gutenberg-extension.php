<?php
if ( ! class_exists( 'WFOP_Gutenberg_PRO' ) ) {
	/**
	 * Class Gutenberg
	 */
	#[AllowDynamicProperties]
	class WFOP_Gutenberg_PRO {
		/**
		 * @var string $ins | Instance.
		 */
		private static $ins = null;

		/**
		 * @var array $modules_instance | Instance Array.
		 */
		public $modules_instance = array();

		/**
		 * @var object $post | Post Object.
		 */
		private $post = null;

		/**
		 * @var array $widgets_json | Widgets Json.
		 */
		protected $widgets_json = array();

		/**
		 * @var object $optin_object | Optin Object.
		 */
		public $optin_object = null;
		private $url         = '';

		/**
		 * Class constructor
		 */
		private function __construct() {

			$this->register();
		}


		/**
		 * Get Class Instance
		 */
		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Register
		 */
		private function register() {
			$this->url          = plugin_dir_url( __FILE__ );
			$this->optin_object = WFFN_Optin_Pages::get_instance();

			add_action( 'init', array( $this, 'init_extension' ), 21 );
		}

		/**
		 * Load assets for wp-admin when editor is active.
		 */
		public function admin_script_style() {

			global $pagenow, $post;

			if ( $this->optin_object->get_post_type_slug() === $post->post_type && 'post.php' === $pagenow && isset( $_GET['post'] ) && intval( $_GET['post'] ) > 0 ) { //phpcs:ignore

				defined( 'BWF_I18N' ) || define( 'BWF_I18N', 'funnel-builder-powerpack' );
				$app_name     = 'optin-popup-block';
				$frontend_dir = defined( 'BWFOP_POPUP_REACT_ENVIRONMENT' ) ? BWFOP_POPUP_REACT_ENVIRONMENT : $this->url . 'dist';
				// $frontend_dir = 'http://localhost:9016';

				$js_path    = "/$app_name.js";
				$style_path = "/$app_name.css";

				// FontAwesome runtime for the popup block's icon picker (IconControl/SvgIcon).
				$this->enqueue_script_with_integrity(
					'wfoptin-font-awesome-kit',
					'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js',
					'6.7.2',
					'sha384-DsXFqEUf3HnCU8om0zbXN58DxV7Bo8/z7AbHBGd2XxkeNpdLrygNiGFr/03W0Xmt'
				);

				wp_enqueue_script( 'wfoptin-pro-script', $frontend_dir . $js_path, array( 'wfoptin-script', 'wfoptin-font-awesome-kit' ), time(), true );
				wp_enqueue_style( 'wfoptin-pro-default', $frontend_dir . $style_path, array(), time() );

			}
		}

		/**
		 * Register and enqueue a remote script locked with Subresource Integrity, so a tampered
		 * CDN response cannot execute in the page.
		 *
		 * @param string $handle    Script handle.
		 * @param string $src       Remote script URL.
		 * @param string $version   Script version.
		 * @param string $integrity Integrity hash (e.g. "sha384-...").
		 */
		private function enqueue_script_with_integrity( $handle, $src, $version, $integrity ) {
			wp_register_script( $handle, $src, array(), $version, true );
			wp_enqueue_script( $handle );

			add_filter(
				'script_loader_tag',
				function ( $tag, $tag_handle ) use ( $handle, $integrity ) {
					if ( $tag_handle === $handle && false === strpos( $tag, 'integrity=' ) ) {
						$tag = str_replace( ' src=', ' integrity="' . esc_attr( $integrity ) . '" crossorigin="anonymous" src=', $tag );
					}

					return $tag;
				},
				10,
				2
			);
		}


		/**
		 * Init Extension
		 */
		public function init_extension() {

			$post_id = 0;
			if ( isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['post'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_REQUEST['edit'] ) && $_REQUEST['edit'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['edit'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$post = get_post( $post_id );
			if ( ! is_null( $post ) && $post->post_type === $this->optin_object->get_post_type_slug() ) {

				$this->post = $post;
				$this->prepare_module();

				return;
			}

			add_action( 'wp', array( $this, 'prepare_frontend_module' ), - 5 );
		}

		/**
		 * Prepare Frontend Module
		 */
		public function prepare_frontend_module() {
			global $post;
			if ( is_null( $post ) ) {
				return;
			}
			$this->post = $post;

			if ( $post->post_type === $this->optin_object->get_post_type_slug() ) {
				if ( current_action() == 'wp' && ! is_admin() ) {
					$this->register_scripts();
				}
			}

			$this->prepare_module();
		}

		/**
		 * Prepare Module
		 */
		public function prepare_module() {
			if ( is_null( $this->post ) ) {
				return;
			}

			$id   = $this->post->ID;
			$data = get_post_meta( $id, '_wfop_selected_design', true );

			$design = apply_filters( 'get_offer', $data, $id );

			if ( empty( $design ) || empty( $design['selected_type'] ) ) {
				return;
			}

			if ( 'wp_editor' === $design['selected_type'] || 'gutenberg' === $design['selected_type'] ) {
				add_action( 'enqueue_block_editor_assets', array( $this, 'admin_script_style' ) );
				add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_css_in_iframe' ) );
			}
		}

		/**
		 * Register Scripts
		 */
		private function register_scripts() {

			if ( is_null( $this->post ) ) {
				return;
			}

			$id   = $this->post->ID;
			$data = get_post_meta( $id, '_wfop_selected_design', true );

			$design = apply_filters( 'get_offer', $data, $id );

			if ( empty( $design ) || empty( $design['selected_type'] ) ) {
				return;
			}

			if ( 'wp_editor' === $design['selected_type'] || 'gutenberg' === $design['selected_type'] ) {

				defined( 'BWF_I18N' ) || define( 'BWF_I18N', 'funnel-builder-powerpack' );
				$app_name = 'optin-popup-public';

				$frontend_dir = defined( 'BWFOP_POPUP_REACT_ENVIRONMENT' ) ? BWFOP_POPUP_REACT_ENVIRONMENT : $this->url . 'dist';

				$js_path    = "/$app_name.js";
				$style_path = "/$app_name.css";

				$version = time();

				wp_enqueue_script( 'bwf-optin-pro-gutenberg-scripts', $frontend_dir . $js_path, array(), $version, true );
				wp_enqueue_style( 'bwf-optin-pro-gutenberg-defaults', $frontend_dir . $style_path, array( 'bwf-optin-block-style' ), $version );

			}
		}

		/**
		 * Enqueue CSS inside the block editor iframe via enqueue_block_assets.
		 */
		public function enqueue_block_editor_css_in_iframe() {
			if ( ! is_admin() ) {
				return;
			}

			global $pagenow, $post;

			if ( ! isset( $post->post_type ) || $this->optin_object->get_post_type_slug() !== $post->post_type || 'post.php' !== $pagenow ) {
				return;
			}

			$app_name     = 'optin-popup-block';
			$frontend_dir = defined( 'BWFOP_POPUP_REACT_ENVIRONMENT' ) ? BWFOP_POPUP_REACT_ENVIRONMENT : $this->url . 'dist';
			$style_path   = "/$app_name.css";

			wp_enqueue_style( 'wfoptin-pro-default', $frontend_dir . $style_path, array(), time() );

			// Layer 1: Enqueue saved default font for iframe editor
			$default_font = get_post_meta( $post->ID, 'bwfblock_default_font', true );
			if ( ! empty( $default_font ) ) {
				$font_url = 'https://fonts.googleapis.com/css?family=' . urlencode( $default_font ) . ':100,200,300,400,500,600,700,800,900';
				wp_enqueue_style( 'bwfblock-editor-default-font', $font_url, array(), null );
				wp_add_inline_style( 'bwfblock-editor-default-font', '.editor-styles-wrapper { font-family: ' . esc_attr( $default_font ) . '; }' );
			}
		}
	}

	WFOP_Gutenberg_PRO::get_instance();
}
