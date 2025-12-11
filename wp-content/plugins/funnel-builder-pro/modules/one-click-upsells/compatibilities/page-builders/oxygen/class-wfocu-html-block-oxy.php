<?php
if ( ! class_exists( 'WFOCU_Oxy_HTML_BLOCK' ) ) {
	abstract class WFOCU_Oxy_HTML_BLOCK extends WFOCU_Oxy_Field {

		public function __construct() {
			parent::__construct();
			add_action( 'template_include', [ $this, 'style' ] );
			add_action( 'wp_footer', [ $this, 'scripts' ] );
			add_filter( 'pre_do_shortcode_tag', [ $this, 'pick_data' ], 10, 3 );
		}

		public function options() {
			return [ 'rebuild_on_dom_change' => true ];
		}

		final public function render( $setting, $defaults, $content ) {
			if ( apply_filters( 'wffn_optin_print_oxy_widget', true, $this->get_id(), $this ) ) {

				if ( WFOCU_OXY::is_template_editor() ) {
					$this->preview_shortcode();

					return;
				}

				$this->settings = $setting;
				$this->html( $setting, $defaults, $content );
				if ( isset( $_REQUEST['action'] ) && false !== strpos( $_REQUEST['action'], 'oxy_render' ) ) {//phpcs:ignore
					exit;
				}
			}
		}

		protected function preview_shortcode() {
			echo "[{$this->name}]";
		}

		protected function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return '';
		}

		public function scripts() {

		}

		public function style() {

		}

		public function pick_data( $status, $tag, $attr ) {

			if ( ( $tag === 'oxy-' . $this->slug() ) && ! empty( $attr ) && ! empty( $attr['ct_options'] ) ) {
				$ct_options = json_decode( $attr['ct_options'], true );
				if ( is_array( $ct_options ) && isset( $ct_options['media'] ) ) {
					$this->media_settings = $ct_options['media'];
				}
			}

			return $status;
		}


	}
}