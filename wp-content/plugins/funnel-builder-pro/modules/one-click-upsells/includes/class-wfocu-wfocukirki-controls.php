<?php
if ( ! class_exists( 'WFOCU_Radio_Image_Full' ) ) {
	/**
	 * Type radio-image-full
	 * Class WFOCU_Radio_Image_Full
	 */
	class WFOCU_Radio_Image_Full extends WFOCUKirki_Control_Base {

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
			include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/kirki/content-template-radio-image-full.php';
		}

	}
}
if ( ! class_exists( 'WFOCU_Radio_Icon' ) ) {
	/**
	 * Type radio-icon
	 * Class WFOCU_Radio_Icon
	 */
	class WFOCU_Radio_Icon extends WFOCUKirki_Control_Base {

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
			include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/kirki/content-template-radio-icon.php';
		}
	}
}
if ( ! class_exists( 'WFOCU_Radio_Image_Text' ) ) {
	class WFOCU_Radio_Image_Text extends WFOCUKirki_Control_Base {

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
			include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/kirki/content-template-radio-image-text.php';
		}
	}
}
if ( ! class_exists( 'WFOCU_Responsive_Font_Text' ) ) {
	class WFOCU_Responsive_Font_Text extends WFOCUKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfocu-responsive-font';

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

			$uri = WFOCU_PLUGIN_URL . '/admin/includes/responsive/';

			wp_enqueue_script( 'wfocu-control-responsive-js', $uri . 'responsive.js', null, WFOCU_VERSION, true );
			wp_enqueue_style( 'wfocu-control-responsive-css', $uri . 'responsive.css', null, WFOCU_VERSION );

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
			include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/kirki/content-template-responsive-font-text.php';
		}

	}
}