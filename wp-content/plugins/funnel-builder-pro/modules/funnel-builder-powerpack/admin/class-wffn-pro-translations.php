<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WFFN_Translations' ) && ! class_exists( 'WFFN_Pro_Translations' ) ) {
	/**
	 * Pro variant of the free plugin's translation chunk combiner. Merges
	 * the free plugin's chunk JSONs (shared strings), chunks referencing the
	 * pro bundle, and the JSONs shipped in this plugin's languages/ dir into
	 * a separate combined file (…-wffn-contact-admin-pro.json) so it never
	 * clashes with the free combiner's output.
	 *
	 * Instantiated from WFFN_Pro_React_App::load_react_app(), i.e. only when
	 * the pro bundle actually serves the app.
	 */
	class WFFN_Pro_Translations extends WFFN_Translations {

		/**
		 * Redeclared: the parent uses late static binding, and without an own
		 * property both classes would share one singleton slot.
		 *
		 * @var WFFN_Pro_Translations|null
		 */
		protected static $instance = null;

		/**
		 * Free bundle (shared strings) + pro bundle references both count.
		 * Both plugins now ship the app at admin/app/dist/.
		 *
		 * @var array
		 */
		protected $dist_folders = array( 'admin/app/dist/' );

		/**
		 * Instantiated mid admin_enqueue_scripts, so the filter is registered
		 * directly. Rebuild triggers are covered by the lazy staleness check
		 * in the parent. The free combiner's filter is unhooked: its combined
		 * file is a subset of this one, so letting it run would glob and
		 * rebuild on every app load only to be overridden.
		 */
		public function __construct() {
			remove_filter( 'load_script_translation_file', array( WFFN_Translations::get_instance(), 'load_script_translation_file' ), 10 );
			add_filter( 'load_script_translation_file', array( $this, 'load_script_translation_file' ), 10, 3 );
		}

		protected function get_combined_translation_filename( $locale ) {
			return $this->domain . '-' . $locale . '-' . $this->app_handle . '-pro.json';
		}

		/**
		 * Adds the JSONs shipped inside this module's languages dir (compiled
		 * from languages/funnel-builder.pot) and the pro plugin root's
		 * languages dir; both trusted without a reference check.
		 *
		 * The inherited filter (registered at priority 20, after the free
		 * combiner) serves this class's own combined file, which supersets
		 * the free one — so on pro sites the pro combined always wins.
		 *
		 * @return array path => trust_unreferenced
		 */
		protected function get_extra_chunk_dirs() {
			$dirs = parent::get_extra_chunk_dirs();

			$dirs[ WFFN_Pro_React_App::get_instance()->get_languages_dir() ] = true;
			$dirs[ dirname( WFFN_PRO_PLUGIN_DIR, 2 ) . '/languages' ]        = true;

			return $dirs;
		}
	}
}
