<?php
/**
 * Initializes WFACPKirki
 *
 * @package     WFACPKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */
if ( ! class_exists( 'WFACPKirki_Init' ) ) {
	/**
	 * Initialize WFACPKirki
	 */
	#[AllowDynamicProperties]
	class WFACPKirki_Init {

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
			add_filter( 'wfacpkirki_control_types', array( $this, 'default_control_types' ) );

			add_action( 'customize_register', array( $this, 'remove_panels' ), 99999 );
			add_action( 'customize_register', array( $this, 'remove_sections' ), 99999 );
			add_action( 'customize_register', array( $this, 'remove_controls' ), 99999 );

			new WFACPKirki_Values();
			new WFACPKirki_Sections();
		}

		/**
		 * Properly set the WFACPKirki URL for assets.
		 *
		 * @static
		 * @access public
		 */
		public static function set_url() {

			if ( WFACPKirki_Util::is_plugin() ) {
				return;
			}

			// Get correct URL and path to wp-content.
			$content_url = untrailingslashit( WFACP_PLUGIN_URL );
			$content_dir = wp_normalize_path( untrailingslashit( WFACP_PLUGIN_DIR ) );

			WFACPKirki::$url = str_replace( $content_dir, $content_url, wp_normalize_path( WFACPKirki::$path ) );

			// Apply the wfacpkirki_config filter.
			$config = apply_filters( 'wfacpkirki_config', array() );
			if ( isset( $config['url_path'] ) ) {
				WFACPKirki::$url = $config['url_path'];
			}

			// Make sure the right protocol is used.
			WFACPKirki::$url = set_url_scheme( WFACPKirki::$url );
		}

		/**
		 * Add the default WFACPKirki control types.
		 *
		 * @access public
		 *
		 * @param array $control_types The control types array.
		 *
		 * @return array
		 * @since 3.0.0
		 *
		 */
		public function default_control_types( $control_types = array() ) {

			$this->control_types = array(
				'checkbox'                   => 'WFACPKirki_Control_Checkbox',
				'wfacpkirki-background'      => 'WFACPKirki_Control_Background',
				'code_editor'                => 'WFACPKirki_Control_Code',
				'wfacpkirki-color'           => 'WFACPKirki_Control_Color',
				'wfacpkirki-color-palette'   => 'WFACPKirki_Control_Color_Palette',
				'wfacpkirki-custom'          => 'WFACPKirki_Control_Custom',
				'wfacpkirki-date'            => 'WFACPKirki_Control_Date',
				'wfacpkirki-dashicons'       => 'WFACPKirki_Control_Dashicons',
				'wfacpkirki-dimension'       => 'WFACPKirki_Control_Dimension',
				'wfacpkirki-dimensions'      => 'WFACPKirki_Control_Dimensions',
				'wfacpkirki-editor'          => 'WFACPKirki_Control_Editor',
				'wfacpkirki-fontawesome'     => 'WFACPKirki_Control_FontAwesome',
				'wfacpkirki-image'           => 'WFACPKirki_Control_Image',
				'wfacpkirki-multicolor'      => 'WFACPKirki_Control_Multicolor',
				'wfacpkirki-multicheck'      => 'WFACPKirki_Control_MultiCheck',
				'wfacpkirki-number'          => 'WFACPKirki_Control_Number',
				'wfacpkirki-palette'         => 'WFACPKirki_Control_Palette',
				'wfacpkirki-radio'           => 'WFACPKirki_Control_Radio',
				'wfacpkirki-radio-buttonset' => 'WFACPKirki_Control_Radio_ButtonSet',
				'wfacpkirki-radio-image'     => 'WFACPKirki_Control_Radio_Image',
				'repeater'                   => 'WFACPKirki_Control_Repeater',
				'wfacpkirki-select'          => 'WFACPKirki_Control_Select',
				'wfacpkirki-slider'          => 'WFACPKirki_Control_Slider',
				'wfacpkirki-sortable'        => 'WFACPKirki_Control_Sortable',
				'wfacpkirki-spacing'         => 'WFACPKirki_Control_Dimensions',
				'wfacpkirki-switch'          => 'WFACPKirki_Control_Switch',
				'wfacpkirki-generic'         => 'WFACPKirki_Control_Generic',
				'wfacpkirki-toggle'          => 'WFACPKirki_Control_Toggle',
				'wfacpkirki-typography'      => 'WFACPKirki_Control_Typography',
				'image'                      => 'WFACPKirki_Control_Image',
				'cropped_image'              => 'WFACPKirki_Control_Cropped_Image',
				'upload'                     => 'WFACPKirki_Control_Upload',
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

			$section_types = apply_filters( 'wfacpkirki_section_types', array() );
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

			$skip_control_types = apply_filters( 'wfacpkirki_control_types_exclude', array(
				'WFACPKirki_Control_Repeater',
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
			if ( ! empty( WFACPKirki::$panels ) ) {
				foreach ( WFACPKirki::$panels as $panel_args ) {
					// Extra checks for nested panels.
					if ( isset( $panel_args['panel'] ) ) {
						if ( isset( WFACPKirki::$panels[ $panel_args['panel'] ] ) ) {
							// Set the type to nested.
							$panel_args['type'] = 'wfacpkirki-nested';
						}
					}

					new WFACPKirki_Panel( $panel_args );
				}
			}
		}

		/**
		 * Register our sections to the WordPress Customizer.
		 *
		 * @var object The WordPress Customizer object
		 */
		public function add_sections() {
			if ( ! empty( WFACPKirki::$sections ) ) {
				foreach ( WFACPKirki::$sections as $section_args ) {
					// Extra checks for nested sections.
					if ( isset( $section_args['section'] ) ) {
						if ( isset( WFACPKirki::$sections[ $section_args['section'] ] ) ) {
							// Set the type to nested.
							$section_args['type'] = 'wfacpkirki-nested';
							// We need to check if the parent section is nested inside a panel.
							$parent_section = WFACPKirki::$sections[ $section_args['section'] ];
							if ( isset( $parent_section['panel'] ) ) {
								$section_args['panel'] = $parent_section['panel'];
							}
						}
					}
					new WFACPKirki_Section( $section_args );
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
			foreach ( WFACPKirki::$fields as $args ) {

				// Create the settings.
				new WFACPKirki_Settings( $args );

				// Check if we're on the customizer.
				// If we are, then we will create the controls, add the scripts needed for the customizer
				// and any other tweaks that this field may require.
				if ( $wp_customize ) {

					// Create the control.
					new WFACPKirki_Control( $args );

				}
			}
		}

		/**
		 * Process fields added using the 'wfacpkirki_fields' and 'wfacpkirki_controls' filter.
		 * These filters are no longer used, this is simply for backwards-compatibility.
		 *
		 * @access private
		 * @since 2.0.0
		 */
		private function fields_from_filters() {

			$fields = apply_filters( 'wfacpkirki_controls', array() );
			$fields = apply_filters( 'wfacpkirki_fields', $fields );

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					WFACPKirki::add_field( 'global', $field );
				}
			}
		}

		/**
		 * Alias for the is_plugin static method in the WFACPKirki_Util class.
		 * This is here for backwards-compatibility purposes.
		 *
		 * @static
		 * @access public
		 * @return bool
		 * @since 3.0.0
		 */
		public static function is_plugin() {
			// Return result using the WFACPKirki_Util class.
			return WFACPKirki_Util::is_plugin();
		}

		/**
		 * Alias for the get_variables static method in the WFACPKirki_Util class.
		 * This is here for backwards-compatibility purposes.
		 *
		 * @static
		 * @access public
		 * @return array Formatted as array( 'variable-name' => value ).
		 * @since 2.0.0
		 */
		public static function get_variables() {
			// Log error for developers.
			_doing_it_wrong( __METHOD__, esc_attr__( 'We detected you\'re using WFACPKirki_Init::get_variables(). Please use WFACPKirki_Util::get_variables() instead.', 'wfacpkirki' ), '3.0.10' );

			// Return result using the WFACPKirki_Util class.
			return WFACPKirki_Util::get_variables();
		}

		/**
		 * Remove panels.
		 *
		 * @param object $wp_customize The customizer object.
		 *
		 * @return void
		 * @since 3.0.17
		 *
		 */
		public function remove_panels( $wp_customize ) {
			foreach ( WFACPKirki::$panels_to_remove as $panel ) {
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
		 *
		 */
		public function remove_sections( $wp_customize ) {
			foreach ( WFACPKirki::$sections_to_remove as $section ) {
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
		 *
		 */
		public function remove_controls( $wp_customize ) {
			foreach ( WFACPKirki::$controls_to_remove as $control ) {
				$wp_customize->remove_control( $control );
			}
		}
	}
}