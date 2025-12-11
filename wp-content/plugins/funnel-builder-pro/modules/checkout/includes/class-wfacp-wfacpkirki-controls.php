<?php
defined( 'ABSPATH' ) || exit;

/**
 * Type radio-image-full
 * Class WFACP_Radio_Image_Full
 */
if ( ! class_exists( 'WFACP_Radio_Image_Full' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Radio_Image_Full extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'radio-image-full';

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			$this->json['default'] = $this->setting->default;
			if ( isset( $this->default ) ) {
				$this->json['default'] = $this->default;
			}
			$this->json['value'] = $this->value();

			foreach ( $this->choices as $key => $value ) {
				$this->json['choices'][ $key ]        = esc_url( $value['path'] );
				$this->json['choices_titles'][ $key ] = $value['label'];
			}

			$this->json['link'] = $this->get_link();
			$this->json['id']   = $this->id;

			$this->json['inputAttrs'] = '';
			$this->json['labelStyle'] = '';
			foreach ( $this->input_attrs as $attr => $value ) {
				if ( 'style' !== $attr ) {
					$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
				} else {
					$this->json['labelStyle'] = 'style="' . esc_attr( $value ) . '" ';
				}
			}

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
            <label class="customizer-text">
                <# if ( data.label ) { #><span class="customize-control-title er">{{{ data.label }}}</span><# } #>
                <# if ( data.description ) { #><span class="description customize-control-description">{{{ data.description }}}</span><# } #>
            </label>

            <div id="input_{{ data.id }}" class="image  WFACP_image_full">
                <# for ( key in data.choices ) { #>
                <input {{{ data.inputAttrs }}} class="image-select" type="radio" value="{{ key }}" name="_customize-radio-{{ data.id }}" id="{{ data.id }}{{ key }}" {{{ data.link }}}<# if ( data.value
                ===
                key ) { #> checked="checked"<# } #>>
                <label for="{{ data.id }}{{ key }}" {{{ data.labelStyle }}}>
                    <img class="" src="{{ data.choices[ key ] }}">
                    <span class="image-clickable" title="{{ data.choices_titles[ key ] }}"></span>
                </label>
                </input>
                <# } #>
            </div>
			<?php
		}

	}
}
if ( ! class_exists( 'WFACP_Radio_Icon' ) ) {
	/**
	 * Type radio-icon
	 * Class WFACP_Radio_Icon
	 */
	#[AllowDynamicProperties]
	class WFACP_Radio_Icon extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'radio-icon';

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			foreach ( $this->input_attrs as $attr => $value ) {
				if ( 'style' !== $attr ) {
					$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
					continue;
				}
				$this->json['labelStyle'] = 'style="' . esc_attr( $value ) . '" ';
			}

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
            <# if ( data.label ) { #><span class="customize-control-title">{{{ data.label }}}</span><# } #>
            <# if ( data.description ) { #><span class="description customize-control-description">{{{ data.description }}}</span><# } #>
            <div id="input_{{ data.id }}" class="WFACP_icons_wrapper buttonset">
                <# for ( key in data.choices ) { #>
                <input {{{ data.inputAttrs }}} class="switch-input screen-reader-text" type="radio" value="{{ key }}" name="_customize-icons-radio-{{{ data.id }}}" id="{{ data.id }}{{ key }}" {{{ data.link }}}<#
                if ( key === data.value ) { #> checked="checked" <# } #>>
                <label class="switch-label switch-label-<# if ( key === data.value ) { #>on <# } else { #>off<# } #>" for="{{ data.id }}{{ key }}">{{{ data.choices[ key ] }}}</label>
                </input>
                <# } #>
            </div>
			<?php
		}
	}
}
if ( ! class_exists( 'WFACP_Radio_Image_Text' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Radio_Image_Text extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'radio-image-text';


		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			$this->json['default'] = $this->setting->default;
			if ( isset( $this->default ) ) {
				$this->json['default'] = $this->default;
			}
			$this->json['value'] = $this->value();

			foreach ( $this->choices as $key => $value ) {
				$this->json['choices'][ $key ]        = esc_url( $value['path'] );
				$this->json['choices_titles'][ $key ] = isset( $value['label'] ) ? $value['label'] : '';
			}

			$this->json['link'] = $this->get_link();
			$this->json['id']   = $this->id;

			$this->json['inputAttrs'] = '';
			$this->json['labelStyle'] = '';
			foreach ( $this->input_attrs as $attr => $value ) {
				if ( 'style' !== $attr ) {
					$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
				} else {
					$this->json['labelStyle'] = 'style="' . esc_attr( $value ) . '" ';
				}
			}

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
            <label class="customizer-text">
                <# if ( data.label ) { #>
                <span class="customize-control-title">{{{ data.label }}}</span>
                <# } #>
                <# if ( data.description ) { #>
                <span class="description customize-control-description">{{{ data.description }}}</span>
                <# } #>
            </label>
            <div id="input_{{ data.id }}" class="image WFACP_image_text">
                <# for ( key in data.choices ) { #>
                <input {{{ data.inputAttrs }}} class="image-select" type="radio" value="{{ key }}" name="_customize-radio-{{ data.id }}" id="{{ data.id }}{{ key }}" {{{ data.link }}}<# if ( data.value
                ===
                key ) { #> checked="checked"<# } #>>
                <label for="{{ data.id }}{{ key }}" {{{ data.labelStyle }}}>
                    <img class="" src="{{ data.choices[ key ] }}">
                    <span class="image-clickable" title="{{ data.choices_titles[ key ] }}"></span>
                </label>
                </input>
                <# } #>
            </div>
			<?php
		}
	}
}
if ( ! class_exists( 'WFACP_Responsive_Font_Text' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Responsive_Font_Text extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacp-responsive-font';

		/**
		 * The control type.
		 *
		 * @access public
		 * @var array
		 */
		public $units = array();


		/**
		 * Enqueue control related scripts/styles.
		 *
		 * @access public
		 */
		public function enqueue() {

			$uri = WFACP_PLUGIN_URL . '/admin/includes/responsive/';

			wp_enqueue_script( 'wfacp-control-responsive-js', $uri . 'responsive.js', null, WFACP_VERSION, true );
			wp_enqueue_style( 'wfacp-control-responsive-css', $uri . 'responsive.css', null, WFACP_VERSION );

		}

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			$this->json['default'] = $this->setting->default;
			if ( isset( $this->default ) ) {
				$this->json['default'] = $this->default;
			}

			$val = maybe_unserialize( $this->value() );

			if ( ! is_array( $val ) || is_numeric( $val ) ) {

				$val = array(
					'desktop'      => $val,
					'tablet'       => '',
					'mobile'       => '',
					'desktop-unit' => '',
					'tablet-unit'  => '',
					'mobile-unit'  => '',
				);
			}

			$this->json['value']   = $val;
			$this->json['choices'] = $this->choices;
			$this->json['link']    = $this->get_link();
			$this->json['id']      = $this->id;
			$this->json['label']   = esc_html( $this->label );
			$this->json['units']   = $this->units;

			$this->json['inputAttrs'] = '';
			foreach ( $this->input_attrs as $attr => $value ) {
				$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
			}

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
            <label class="customizer-text" for="">
                <# if ( data.label ) { #>
                <span class="customize-control-title">{{{ data.label }}}</span>
                <ul class="wfacp-responsive-btns">
                    <li class="desktop active">
                        <button type="button" class="preview-desktop active" data-device="desktop">
                            <i class="dashicons dashicons-desktop"></i>
                        </button>
                    </li>
                    <li class="tablet">
                        <button type="button" class="preview-tablet" data-device="tablet">
                            <i class="dashicons dashicons-tablet"></i>
                        </button>
                    </li>
                    <li class="mobile">
                        <button type="button" class="preview-mobile" data-device="mobile">
                            <i class="dashicons dashicons-smartphone"></i>
                        </button>
                    </li>
                </ul>
                <# } #>
                <# if ( data.description ) { #>
                <span class="description customize-control-description">{{{ data.description }}}</span>
                <# }

                value_desktop = '';
                value_tablet = '';
                value_mobile = '';

                if ( data.value['desktop'] ) {
                value_desktop = data.value['desktop'];
                }

                if ( data.value['tablet'] ) {
                value_tablet = data.value['tablet'];
                }

                if ( data.value['mobile'] ) {
                value_mobile = data.value['mobile'];
                } #>

                <div class="input-wrapper wfacp-responsive-wrapper">

                    <input {{{ data.inputAttrs }}} data-id='desktop' class="wfacp-responsive-input desktop active" type="number" value="{{ value_desktop }}"/>
                    <select class="wfacp-responsive-select desktop active" data-id='desktop-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
                    <# _.each( data.units, function( value, key ) { #>
                    <option value="{{{ key }}}"
                    <# if ( data.value['desktop-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ] }}}</option>
                    <# }); #>
                    </select>

                    <input {{{ data.inputAttrs }}} data-id='tablet' class="wfacp-responsive-input tablet" type="number" value="{{ value_tablet }}"/>
                    <select class="wfacp-responsive-select tablet" data-id='tablet-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
                    <# _.each( data.units, function( value, key ) { #>
                    <option value="{{{ key }}}"
                    <# if ( data.value['tablet-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ] }}}</option>
                    <# }); #>
                    </select>

                    <input {{{ data.inputAttrs }}} data-id='mobile' class="wfacp-responsive-input mobile" type="number" value="{{ value_mobile }}"/>
                    <select class="wfacp-responsive-select mobile" data-id='mobile-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
                    <# _.each( data.units, function( value, key ) { #>
                    <option value="{{{ key }}}"
                    <# if ( data.value['mobile-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ] }}}</option>
                    <# }); #>
                    </select>

                </div>
            </label>
			<?php
		}

	}
}





