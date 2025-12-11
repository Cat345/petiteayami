<?php

if ( ! class_exists( 'WFOCU_Template_Common' ) ) {
	/**
	 * Abstract Class for all the Template Loading
	 * Class WFOCU_Template_Common
	 */
	#[AllowDynamicProperties]
	class WFOCU_Template_Common {

		public $img_public_path = '';
		public $variation_field = false;
		public $data = null;
		public $products_data = array();
		public $img_path = '';
		public $no_img_path = '';
		protected $section_fields = array();
		protected $offer_data = null;
		protected $offer_products_meta = null;
		protected $change_set = array();
		protected $sections = array( 'wfocu_section' );
		protected $template_dir = __DIR__;
		protected $template_slug = 'sp-classic';
		protected $offer_id = 0;
		public $selected_font_family = '';
		public $web_google_fonts = [
			'default'   => 'Default',
			'Open Sans' => 'Open Sans',
		];

		public function __construct() {
			$this->img_path        = WFOCU_PLUGIN_URL . '/admin/assets/img/';
			$this->img_public_path = WFOCU_PLUGIN_URL . '/assets/img/';
			$this->no_img_path     = WFOCU_PLUGIN_URL . '/admin/assets/img/';
			
		}

		public function get_offer_id() {
			return $this->offer_id;
		}

		public function set_offer_id( $offer_id = false ) {
			if ( false !== $offer_id ) {
				$this->offer_id = $offer_id;
			}
		}

		public function get_offer_data() {
			return $this->offer_data;
		}

		public function set_offer_data( $offer = false ) {
			$this->offer_data = $offer;
		}

		public function load_hooks() {

		}

		public function set_data( $data ) {
			$this->data = $data;
		}

		public function get_slug() {
			return $this->template_slug;
		}

		public function __call( $method, $args ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			/**
			 * This is only for backward compatibility of multi product templates
			 */
			if ( 'get_view' === $method ) {
				extract( array( 'data' => $this->data ) ); //@codingStandardsIgnoreLine

				do_action( 'wfocu_before_template_load' );
				include $this->template_dir . '/views/view.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
				do_action( 'wfocu_after_template_load' );
				exit;
			}
		}
	}
}