<?php
/**
 * Initializes WFOCUKirki
 *
 * @package     WFOCUKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */
if ( ! class_exists( 'WFOCUKirki_Init' ) ) {
	/**
	 * Initialize WFOCUKirki
	 */
	class WFOCUKirki_Init {

		/**
		 * Control types.
		 *
		 * @access private
		 * @since 3.0.0
		 * @var array
		 */
		private $control_types = array();

		/**
		 * The class constructor.
		 */
		public function __construct() {

			self::set_url();
			add_action( 'after_setup_theme', array( $this, 'set_url' ) );
			add_action( 'wp_loaded', array( $this, 'add_to_customizer' ), 1 );
			add_filter( 'wfocukirki_control_types', array( $this, 'default_control_types' ) );

			add_action( 'customize_register', array( $this, 'remove_panels' ), 99999 );
			add_action( 'customize_register', array( $this, 'remove_sections' ), 99999 );
			add_action( 'customize_register', array( $this, 'remove_controls' ), 99999 );

			new WFOCUKirki_Values();
			new WFOCUKirki_Sections();
		}

		/**
		 * Properly set the WFOCUKirki URL for assets.
		 *
		 * @static
		 * @access public
		 */
		public static function set_url() {

			if ( WFOCUKirki_Util::is_plugin() ) {
				return;
			}

			// Get correct URL and path to wp-content.
			$content_url = untrailingslashit( dirname( dirname( get_stylesheet_directory_uri() ) ) );
			$content_dir = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );

			WFOCUKirki::$url = str_replace( $content_dir, $content_url, wp_normalize_path( WFOCUKirki::$path ) );

			// Apply the wfocukirki_config filter.
			$config = apply_filters( 'wfocukirki_config', array() );
			if ( isset( $config['url_path'] ) ) {
				WFOCUKirki::$url = $config['url_path'];
			}

			// Make sure the right protocol is used.
			WFOCUKirki::$url = set_url_scheme( WFOCUKirki::$url );
		}

		/**
		 * Add the default WFOCUKirki control types.
		 *
		 * @access public
		 *
		 * @param array $control_types The control types array.
		 *
		 * @return array
		 * @since 3.0.0
		 */
		public function default_control_types( $control_types = array() ) {

			$this->control_types = array(
				'checkbox'                   => 'WFOCUKirki_Control_Checkbox',
				'wfocukirki-background'      => 'WFOCUKirki_Control_Background',
				'code_editor'                => 'WFOCUKirki_Control_Code',
				'wfocukirki-color'           => 'WFOCUKirki_Control_Color',
				'wfocukirki-color-palette'   => 'WFOCUKirki_Control_Color_Palette',
				'wfocukirki-custom'          => 'WFOCUKirki_Control_Custom',
				'wfocukirki-date'            => 'WFOCUKirki_Control_Date',
				'wfocukirki-dashicons'       => 'WFOCUKirki_Control_Dashicons',
				'wfocukirki-dimension'       => 'WFOCUKirki_Control_Dimension',
				'wfocukirki-dimensions'      => 'WFOCUKirki_Control_Dimensions',
				'wfocukirki-editor'          => 'WFOCUKirki_Control_Editor',
				'wfocukirki-fontawesome'     => 'WFOCUKirki_Control_FontAwesome',
				'wfocukirki-image'           => 'WFOCUKirki_Control_Image',
				'wfocukirki-multicolor'      => 'WFOCUKirki_Control_Multicolor',
				'wfocukirki-multicheck'      => 'WFOCUKirki_Control_MultiCheck',
				'wfocukirki-number'          => 'WFOCUKirki_Control_Number',
				'wfocukirki-palette'         => 'WFOCUKirki_Control_Palette',
				'wfocukirki-radio'           => 'WFOCUKirki_Control_Radio',
				'wfocukirki-radio-buttonset' => 'WFOCUKirki_Control_Radio_ButtonSet',
				'wfocukirki-radio-image'     => 'WFOCUKirki_Control_Radio_Image',
				'repeater'                   => 'WFOCUKirki_Control_Repeater',
				'wfocukirki-select'          => 'WFOCUKirki_Control_Select',
				'wfocukirki-slider'          => 'WFOCUKirki_Control_Slider',
				'wfocukirki-sortable'        => 'WFOCUKirki_Control_Sortable',
				'wfocukirki-spacing'         => 'WFOCUKirki_Control_Dimensions',
				'wfocukirki-switch'          => 'WFOCUKirki_Control_Switch',
				'wfocukirki-generic'         => 'WFOCUKirki_Control_Generic',
				'wfocukirki-toggle'          => 'WFOCUKirki_Control_Toggle',
				'wfocukirki-typography'      => 'WFOCUKirki_Control_Typography',
				'image'                      => 'WFOCUKirki_Control_Image',
				'cropped_image'              => 'WFOCUKirki_Control_Cropped_Image',
				'upload'                     => 'WFOCUKirki_Control_Upload',
			);

			return array_merge( $this->control_types, $control_types );

		}

		/**
		 * Helper function that adds the fields, sections and panels to the customizer.
		 */
		public function add_to_customizer() {
			$this->fields_from_filters();
			add_action( 'customize_register', array( $this, 'register_control_types' ) );
			add_action( 'customize_register', array( $this, 'add_panels' ), 97 );
			add_action( 'customize_register', array( $this, 'add_sections' ), 98 );
			add_action( 'customize_register', array( $this, 'add_fields' ), 99 );
		}

		/**
		 * Register control types
		 */
		public function register_control_types() {
			global $wp_customize;

			$section_types = apply_filters( 'wfocukirki_section_types', array() );
			foreach ( $section_types as $section_type ) {
				$wp_customize->register_section_type( $section_type );
			}

			$this->control_types = $this->default_control_types();
			if ( ! class_exists( 'WP_Customize_Code_Editor_Control' ) ) {
				unset( $this->control_types['code_editor'] );
			}
			foreach ( $this->control_types as $key => $classname ) {
				if ( ! class_exists( $classname ) ) {
					unset( $this->control_types[ $key ] );
				}
			}

			$skip_control_types = apply_filters( 'wfocukirki_control_types_exclude', array(
					'WFOCUKirki_Control_Repeater',
					'WP_Customize_Control',
				) );

			foreach ( $this->control_types as $control_type ) {
				if ( ! in_array( $control_type, $skip_control_types, true ) && class_exists( $control_type ) ) {
					$wp_customize->register_control_type( $control_type );
				}
			}
		}

		/**
		 * Register our panels to the WordPress Customizer.
		 *
		 * @access public
		 */
		public function add_panels() {
			if ( ! empty( WFOCUKirki::$panels ) ) {
				foreach ( WFOCUKirki::$panels as $panel_args ) {
					// Extra checks for nested panels.
					if ( isset( $panel_args['panel'] ) ) {
						if ( isset( WFOCUKirki::$panels[ $panel_args['panel'] ] ) ) {
							// Set the type to nested.
							$panel_args['type'] = 'wfocukirki-nested';
						}
					}

					new WFOCUKirki_Panel( $panel_args );
				}
			}
		}

		/**
		 * Register our sections to the WordPress Customizer.
		 *
		 * @var object The WordPress Customizer object
		 */
		public function add_sections() {
			if ( ! empty( WFOCUKirki::$sections ) ) {
				foreach ( WFOCUKirki::$sections as $section_args ) {
					// Extra checks for nested sections.
					if ( isset( $section_args['section'] ) ) {
						if ( isset( WFOCUKirki::$sections[ $section_args['section'] ] ) ) {
							// Set the type to nested.
							$section_args['type'] = 'wfocukirki-nested';
							// We need to check if the parent section is nested inside a panel.
							$parent_section = WFOCUKirki::$sections[ $section_args['section'] ];
							if ( isset( $parent_section['panel'] ) ) {
								$section_args['panel'] = $parent_section['panel'];
							}
						}
					}
					new WFOCUKirki_Section( $section_args );
				}
			}
		}

		/**
		 * Create the settings and controls from the $fields array and register them.
		 *
		 * @var object The WordPress Customizer object.
		 */
		public function add_fields() {

			global $wp_customize;
			foreach ( WFOCUKirki::$fields as $args ) {

				// Create the settings.
				new WFOCUKirki_Settings( $args );

				// Check if we're on the customizer.
				// If we are, then we will create the controls, add the scripts needed for the customizer
				// and any other tweaks that this field may require.
				if ( $wp_customize ) {

					// Create the control.
					new WFOCUKirki_Control( $args );

				}
			}
		}

		/**
		 * Process fields added using the 'wfocukirki_fields' and 'wfocukirki_controls' filter.
		 * These filters are no longer used, this is simply for backwards-compatibility.
		 *
		 * @access private
		 * @since 2.0.0
		 */
		private function fields_from_filters() {

			$fields = apply_filters( 'wfocukirki_controls', array() );
			$fields = apply_filters( 'wfocukirki_fields', $fields );

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					WFOCUKirki::add_field( 'global', $field );
				}
			}
		}

		/**
		 * Alias for the is_plugin static method in the WFOCUKirki_Util class.
		 * This is here for backwards-compatibility purposes.
		 *
		 * @static
		 * @access public
		 * @return bool
		 * @since 3.0.0
		 */
		public static function is_plugin() {
			// Return result using the WFOCUKirki_Util class.
			return WFOCUKirki_Util::is_plugin();
		}

		/**
		 * Alias for the get_variables static method in the WFOCUKirki_Util class.
		 * This is here for backwards-compatibility purposes.
		 *
		 * @static
		 * @access public
		 * @return array Formatted as array( 'variable-name' => value ).
		 * @since 2.0.0
		 */
		public static function get_variables() {
			// Log error for developers.
			_doing_it_wrong( __METHOD__, esc_attr__( 'We detected you\'re using WFOCUKirki_Init::get_variables(). Please use WFOCUKirki_Util::get_variables() instead.', 'wfocukirki' ), '3.0.10' );

			// Return result using the WFOCUKirki_Util class.
			return WFOCUKirki_Util::get_variables();
		}

		/**
		 * Remove panels.
		 *
		 * @param object $wp_customize The customizer object.
		 *
		 * @return void
		 * @since 3.0.17
		 */
		public function remove_panels( $wp_customize ) {
			foreach ( WFOCUKirki::$panels_to_remove as $panel ) {
				$wp_customize->remove_panel( $panel );
			}
		}

		/**
		 * Remove sections.
		 *
		 * @param object $wp_customize The customizer object.
		 *
		 * @return void
		 * @since 3.0.17
		 */
		public function remove_sections( $wp_customize ) {
			foreach ( WFOCUKirki::$sections_to_remove as $section ) {
				$wp_customize->remove_section( $section );
			}
		}

		/**
		 * Remove controls.
		 *
		 * @param object $wp_customize The customizer object.
		 *
		 * @return void
		 * @since 3.0.17
		 */
		public function remove_controls( $wp_customize ) {
			foreach ( WFOCUKirki::$controls_to_remove as $control ) {
				$wp_customize->remove_control( $control );
			}
		}
	}
}