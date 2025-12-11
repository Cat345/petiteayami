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
		public $modules_instance = [];

		/**
		 * @var object $post | Post Object.
		 */
		private $post = null;

		/**
		 * @var array $widgets_json | Widgets Json.
		 */
		protected $widgets_json = [];

		/**
		 * @var object $optin_object | Optin Object.
		 */
		public $optin_object = null;
		private $url = '';

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


				wp_enqueue_script( 'wfoptin-pro-script', $frontend_dir . $js_path, array( 'wfoptin-script' ), time(), true );
				wp_enqueue_style( 'wfoptin-pro-default', $frontend_dir . $style_path, array(), time() );

			}

		}


		/**
		 * Init Extension
		 */
		public function init_extension() {

			$post_id = 0;
			if ( isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['post'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else if ( isset( $_REQUEST['edit'] ) && $_REQUEST['edit'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['edit'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$post = get_post( $post_id );
			if ( ! is_null( $post ) && $post->post_type === $this->optin_object->get_post_type_slug() ) {

				$this->post = $post;
				$this->prepare_module();

				return;
			}

			add_action( 'wp', [ $this, 'prepare_frontend_module' ], - 5 );


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
				add_action( 'enqueue_block_editor_assets', [ $this, 'admin_script_style' ] );
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


	}

	WFOP_Gutenberg_PRO::get_instance();
}