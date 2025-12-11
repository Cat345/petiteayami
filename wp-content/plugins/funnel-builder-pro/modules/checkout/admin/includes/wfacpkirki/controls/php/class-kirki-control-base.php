<?php
/**
 * Customizer Controls Base.
 *
 * Extend this in other controls.
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.12
 */
if ( ! class_exists( 'WFACPKirki_Control_Base' ) ) {
	/**
	 * A base for controls.
	 */
	class WFACPKirki_Control_Base extends WP_Customize_Control {

		/**
		 * Used to automatically generate all CSS output.
		 *
		 * @access public
		 * @var array
		 */
		public $output = array();

		/**
		 * Data type
		 *
		 * @access public
		 * @var string
		 */
		public $option_type = 'theme_mod';

		/**
		 * Option name (if using options).
		 *
		 * @access public
		 * @var string
		 */
		public $option_name = false;

		/**
		 * The wfacpkirki_config we're using for this control
		 *
		 * @access public
		 * @var string
		 */
		public $wfacpkirki_config = 'global';

		/**
		 * Whitelisting the "required" argument.
		 *
		 * @since 3.0.17
		 * @access public
		 * @var array
		 */
		public $required = array();

		/**
		 * Whitelisting the "preset" argument.
		 *
		 * @since 3.0.26
		 * @access public
		 * @var array
		 */
		public $preset = array();

		/**
		 * Whitelisting the "css_vars" argument.
		 *
		 * @since 3.0.28
		 * @access public
		 * @var string
		 */
		public $css_vars = '';

		/**
		 * Extra script dependencies.
		 *
		 * @return array
		 * @since 3.1.0
		 */
		public function wfacpkirki_script_dependencies() {
			return array();
		}

		/**
		 * Enqueue control related scripts/styles.
		 *
		 * @access public
		 */
		public function enqueue() {

			// Build the suffix for the script.
			$suffix = '';
			$suffix .= ( ! defined( 'SCRIPT_DEBUG' ) || true !== SCRIPT_DEBUG ) ? '.min' : '';

			// The WFACPKirki plugin URL.
			$wfacpkirki_url = trailingslashit( WFACPKirki::$url );

			// Enqueue ColorPicker.
			wp_enqueue_script( 'wp-color-picker-alpha', trailingslashit( WFACPKirki::$url ) . 'assets/vendor/wp-color-picker-alpha/wp-color-picker-alpha.js', array( 'wp-color-picker' ), WFACP_VERSION, true );
			wp_enqueue_style( 'wp-color-picker' );

			// Enqueue selectWoo.
			wp_enqueue_script( 'selectWoo', trailingslashit( WFACPKirki::$url ) . 'assets/vendor/selectWoo/js/selectWoo.full.js', array( 'jquery' ), WFACP_VERSION, true );
			wp_enqueue_style( 'selectWoo', trailingslashit( WFACPKirki::$url ) . 'assets/vendor/selectWoo/css/selectWoo.css', array(), WFACP_VERSION );
			wp_enqueue_style( 'wfacpkirki-selectWoo', trailingslashit( WFACPKirki::$url ) . 'assets/vendor/selectWoo/kirki.css', [], WFACP_VERSION );

			// Enqueue the script.
			wp_enqueue_script( 'wfacpkirki-script', "{$wfacpkirki_url}controls/js/script{$suffix}.js", array(
				'jquery',
				'customize-base',
				'wp-color-picker-alpha',
				'selectWoo',
				'jquery-ui-button',
				'jquery-ui-datepicker',
			), WFACP_VERSION );

			wp_localize_script( 'wfacpkirki-script', 'wfacpkirkiL10n', array(
				'isScriptDebug'        => ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ),
				'noFileSelected'       => esc_attr__( 'No File Selected', 'wfacpkirki' ),
				'remove'               => esc_attr__( 'Remove', 'wfacpkirki' ),
				'default'              => esc_attr__( 'Default', 'wfacpkirki' ),
				'selectFile'           => esc_attr__( 'Select File', 'wfacpkirki' ),
				'standardFonts'        => esc_attr__( 'Standard Fonts', 'wfacpkirki' ),
				'googleFonts'          => esc_attr__( 'Google Fonts', 'wfacpkirki' ),
				'defaultCSSValues'     => esc_attr__( 'CSS Defaults', 'wfacpkirki' ),
				'defaultBrowserFamily' => esc_attr__( 'Default Browser Font-Family', 'wfacpkirki' ),
			) );

			$suffix = str_replace( '.min', '', $suffix );
			// Enqueue the style.
			wp_enqueue_style( 'wfacpkirki-styles', "{$wfacpkirki_url}controls/css/styles{$suffix}.css", array(), WFACP_VERSION );
		}

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			// Get the basics from the parent class.
			parent::to_json();
			// Default.
			$this->json['default'] = $this->setting->default;
			if ( isset( $this->default ) ) {
				$this->json['default'] = $this->default;
			}
			// Required.
			$this->json['required'] = $this->required;
			// Output.
			$this->json['output'] = $this->output;
			// Value.
			$this->json['value'] = $this->value();
			// Choices.
			$this->json['choices'] = $this->choices;
			// The link.
			$this->json['link'] = $this->get_link();
			// The ID.
			$this->json['id'] = $this->id;
			// Translation strings.
			$this->json['l10n'] = $this->l10n();
			// The ajaxurl in case we need it.
			$this->json['ajaxurl'] = admin_url( 'admin-ajax.php' );
			// Input attributes.
			$this->json['inputAttrs'] = '';
			foreach ( $this->input_attrs as $attr => $value ) {
				$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
			}
			// The wfacpkirki-config.
			$this->json['wfacpkirkiConfig'] = $this->wfacpkirki_config;
			// The option-type.
			$this->json['wfacpkirkiOptionType'] = $this->option_type;
			// The option-name.
			$this->json['wfacpkirkiOptionName'] = $this->option_name;
			// The preset.
			$this->json['preset'] = $this->preset;
			// The CSS-Variables.
			$this->json['css-var'] = $this->css_vars;
		}

		/**
		 * Render the control's content.
		 *
		 * Allows the content to be overridden without having to rewrite the wrapper in `$this::render()`.
		 *
		 * Supports basic input types `text`, `checkbox`, `textarea`, `radio`, `select` and `dropdown-pages`.
		 * Additional input types such as `email`, `url`, `number`, `hidden` and `date` are supported implicitly.
		 *
		 * Control content can alternately be rendered in JS. See WP_Customize_Control::print_template().
		 *
		 * @since 3.4.0
		 */
		protected function render_content() {
		}

		/**
		 * An Underscore (JS) template for this control's content (but not its container).
		 *
		 * Class variables for this control class are available in the `data` JS object;
		 * export custom variables by overriding {@see WP_Customize_Control::to_json()}.
		 *
		 * @see WP_Customize_Control::print_template()
		 *
		 * @access protected
		 */
		protected function content_template() {
		}

		/**
		 * Returns an array of translation strings.
		 *
		 * @access protected
		 * @return array
		 * @since 3.0.0
		 */
		protected function l10n() {
			return array();
		}
	}
}