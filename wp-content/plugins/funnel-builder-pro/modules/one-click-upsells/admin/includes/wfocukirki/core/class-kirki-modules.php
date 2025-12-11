<?php
/**
 * Handles modules loading.
 *
 * @package     WFOCUKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.0
 */
if ( ! class_exists( 'WFOCUKirki_Modules' ) ) {
	/**
	 * The WFOCUKirki_Modules class.
	 */
	class WFOCUKirki_Modules {

		/**
		 * An array of available modules.
		 *
		 * @static
		 * @access private
		 * @since 3.0.0
		 * @var array
		 */
		private static $modules = array();

		/**
		 * An array of active modules (objects).
		 *
		 * @static
		 * @access private
		 * @since 3.0.0
		 * @var array
		 */
		private static $active_modules = array();

		/**
		 * Constructor.
		 *
		 * @access public
		 * @since 3.0.0
		 */
		public function __construct() {

			$this->default_modules();
			$this->init();

		}

		/**
		 * Set the default modules and apply the 'wfocukirki_modules' filter.
		 *
		 * @access private
		 * @since 3.0.0
		 */
		private function default_modules() {

			self::$modules = apply_filters( 'wfocukirki_modules', array(
					'css'                => 'WFOCUKirki_Modules_CSS',
					'css-vars'           => 'WFOCUKirki_Modules_CSS_Vars',
					'customizer-styling' => 'WFOCUKirki_Modules_Customizer_Styling',
					'icons'              => 'WFOCUKirki_Modules_Icons',
					'loading'            => 'WFOCUKirki_Modules_Loading',
					'tooltips'           => 'WFOCUKirki_Modules_Tooltips',
					'branding'           => 'WFOCUKirki_Modules_Customizer_Branding',
					'postMessage'        => 'WFOCUKirki_Modules_PostMessage',
					'selective-refresh'  => 'WFOCUKirki_Modules_Selective_Refresh',
					'field-dependencies' => 'WFOCUKirki_Modules_Field_Dependencies',
					'custom-sections'    => 'WFOCUKirki_Modules_Custom_Sections',
					'webfonts'           => 'WFOCUKirki_Modules_Webfonts',
					'webfont-loader'     => 'WFOCUKirki_Modules_Webfont_Loader',
					'preset'             => 'WFOCUKirki_Modules_Preset',
				) );

		}

		/**
		 * Instantiates the modules.
		 *
		 * @access private
		 * @since 3.0.0
		 */
		private function init() {

			foreach ( self::$modules as $key => $module_class ) {
				if ( class_exists( $module_class ) ) {
					// Use this syntax instead of $module_class::get_instance()
					// for PHP 5.2 compatibility.
					self::$active_modules[ $key ] = call_user_func( array( $module_class, 'get_instance' ) );
				}
			}
		}

		/**
		 * Add a module.
		 *
		 * @static
		 * @access public
		 *
		 * @param string $module The classname of the module to add.
		 *
		 * @since 3.0.0
		 */
		public static function add_module( $module ) {

			if ( ! in_array( $module, self::$modules, true ) ) {
				self::$modules[] = $module;
			}

		}

		/**
		 * Remove a module.
		 *
		 * @static
		 * @access public
		 *
		 * @param string $module The classname of the module to add.
		 *
		 * @since 3.0.0
		 */
		public static function remove_module( $module ) {

			$key = array_search( $module, self::$modules, true );
			if ( false !== $key ) {
				unset( self::$modules[ $key ] );
			}
		}

		/**
		 * Get the modules array.
		 *
		 * @static
		 * @access public
		 * @return array
		 * @since 3.0.0
		 */
		public static function get_modules() {

			return self::$modules;

		}

		/**
		 * Get the array of active modules (objects).
		 *
		 * @static
		 * @access public
		 * @return array
		 * @since 3.0.0
		 */
		public static function get_active_modules() {

			return self::$active_modules;

		}
	}
}