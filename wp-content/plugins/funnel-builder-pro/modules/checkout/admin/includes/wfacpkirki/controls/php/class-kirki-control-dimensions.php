<?php
/**
 * Customizer Control: dimensions.
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dimensions control.
 * multiple fields with CSS units validation.
 */
if ( ! class_exists( 'WFACPKirki_Control_Dimensions' ) ) {
	class WFACPKirki_Control_Dimensions extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacpkirki-dimensions';

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			if ( is_array( $this->choices ) ) {
				foreach ( $this->choices as $choice => $value ) {
					if ( 'labels' !== $choice && true === $value ) {
						$this->json['choices'][ $choice ] = true;
					}
				}
			}
			if ( is_array( $this->json['default'] ) ) {
				foreach ( $this->json['default'] as $key => $value ) {
					if ( isset( $this->json['choices'][ $key ] ) && ! isset( $this->json['value'][ $key ] ) ) {
						$this->json['value'][ $key ] = $value;
					}
				}
			}
		}

		/**
		 * Enqueue control related scripts/styles.
		 *
		 * @access public
		 */
		public function enqueue() {

			wp_enqueue_style( 'wfacpkirki-styles', trailingslashit( WFACPKirki::$url ) . 'controls/css/styles.css', array(), WFACP_KIRKI_VERSION );
			wp_localize_script( 'wfacpkirki-script', 'dimensionswfacpkirkiL10n', $this->l10n() );
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
			?>
            <label>
                <# if ( data.label ) { #><span class="customize-control-title">{{{ data.label }}}</span><# } #>
                <# if ( data.description ) { #><span class="description customize-control-description">{{{ data.description }}}</span><# } #>
                <div class="wrapper">
                    <div class="control">
                        <# for ( choiceKey in data.default ) { #>
                        <div class="{{ choiceKey }}">
                            <h5>
                                <# if ( ! _.isUndefined( data.choices.labels ) && ! _.isUndefined( data.choices.labels[ choiceKey ] ) ) { #>
                                {{ data.choices.labels[ choiceKey ] }}
                                <# } else if ( ! _.isUndefined( data.l10n[ choiceKey ] ) ) { #>
                                {{ data.l10n[ choiceKey ] }}
                                <# } else { #>
                                {{ choiceKey }}
                                <# } #>
                            </h5>
                            <div class="{{ choiceKey }} input-wrapper">
                                <# var val = ( ! _.isUndefined( data.value ) && ! _.isUndefined( data.value[ choiceKey ] ) ) ? data.value[ choiceKey ].toString().replace( '%%', '%' ) : ''; #>
                                <input {{{ data.inputAttrs }}} type="text" data-choice="{{ choiceKey }}" value="{{ val }}"/>
                            </div>
                        </div>
                        <# } #>
                    </div>
                </div>
            </label>
			<?php
		}

		/**
		 * Returns an array of translation strings.
		 *
		 * @access protected
		 * @return array
		 * @since 3.0.0
		 */
		protected function l10n() {
			return array(
				'left-top'       => esc_attr__( 'Left Top', 'wfacpkirki' ),
				'left-center'    => esc_attr__( 'Left Center', 'wfacpkirki' ),
				'left-bottom'    => esc_attr__( 'Left Bottom', 'wfacpkirki' ),
				'right-top'      => esc_attr__( 'Right Top', 'wfacpkirki' ),
				'right-center'   => esc_attr__( 'Right Center', 'wfacpkirki' ),
				'right-bottom'   => esc_attr__( 'Right Bottom', 'wfacpkirki' ),
				'center-top'     => esc_attr__( 'Center Top', 'wfacpkirki' ),
				'center-center'  => esc_attr__( 'Center Center', 'wfacpkirki' ),
				'center-bottom'  => esc_attr__( 'Center Bottom', 'wfacpkirki' ),
				'font-size'      => esc_attr__( 'Font Size', 'wfacpkirki' ),
				'font-weight'    => esc_attr__( 'Font Weight', 'wfacpkirki' ),
				'line-height'    => esc_attr__( 'Line Height', 'wfacpkirki' ),
				'font-style'     => esc_attr__( 'Font Style', 'wfacpkirki' ),
				'letter-spacing' => esc_attr__( 'Letter Spacing', 'wfacpkirki' ),
				'word-spacing'   => esc_attr__( 'Word Spacing', 'wfacpkirki' ),
				'top'            => esc_attr__( 'Top', 'wfacpkirki' ),
				'bottom'         => esc_attr__( 'Bottom', 'wfacpkirki' ),
				'left'           => esc_attr__( 'Left', 'wfacpkirki' ),
				'right'          => esc_attr__( 'Right', 'wfacpkirki' ),
				'center'         => esc_attr__( 'Center', 'wfacpkirki' ),
				'size'           => esc_attr__( 'Size', 'wfacpkirki' ),
				'height'         => esc_attr__( 'Height', 'wfacpkirki' ),
				'spacing'        => esc_attr__( 'Spacing', 'wfacpkirki' ),
				'width'          => esc_attr__( 'Width', 'wfacpkirki' ),
				'height'         => esc_attr__( 'Height', 'wfacpkirki' ),
				'invalid-value'  => esc_attr__( 'Invalid Value', 'wfacpkirki' ),
			);
		}
	}
}