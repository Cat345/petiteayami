<?php
/**
 * Combines per-chunk script translation JSON files into a single file with a
 * stable, handle-based name and serves it for the admin app. Based on
 * WooCommerce's Translations class.
 *
 * Webpack chunk filenames change every release, so the md5-keyed JSONs that
 * GlotPress/Loco generate stop matching after an update. Combining every
 * chunk JSON into one file named after the (stable) script handle survives
 * releases, and a single file under the main handle also covers strings that
 * live in lazy-loaded chunks WordPress never registers.
 *
 * @package FunnelKit
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Translations' ) ) {
	class WFFN_Translations {

		/**
		 * @var WFFN_Translations|null
		 */
		protected static $instance = null;

		protected $domain = 'funnel-builder';

		protected $app_handle = 'wffn-contact-admin';

		/**
		 * Substrings a chunk JSON's source reference must contain to count as
		 * part of the app bundle (other scripts share the text domain).
		 *
		 * @var array
		 */
		protected $dist_folders = array( 'admin/app/dist/' );

		/**
		 * @return static
		 */
		public static function get_instance() {
			if ( is_null( static::$instance ) ) {
				static::$instance = new static();
			}

			return static::$instance;
		}

		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'register_translation_file_filter' ), 15 );

			/** Rebuild eagerly when language packs update or the plugin is (re)activated */
			add_action( 'upgrader_process_complete', array( $this, 'combine_translation_chunk_files' ), 10, 2 );
			add_action( 'wffn_activated_plugin', array( $this, 'potentially_generate_translation_strings' ) );
		}

		public function register_translation_file_filter() {
			add_filter( 'load_script_translation_file', array( $this, 'load_script_translation_file' ), 10, 3 );
		}

		/**
		 * Points the app handle's translation lookup at the combined file.
		 *
		 * The combined file always wins when it can be served: it is a
		 * superset of every chunk JSON in every scanned location (language
		 * packs, Loco saves, plugin-shipped files), whereas any single md5
		 * file only covers one dist file. Loco Translate still layers its
		 * custom-dir overrides on top afterwards via its own
		 * load_script_translations filter.
		 *
		 * @param string|false $file   Resolved translation file path.
		 * @param string       $handle Script handle.
		 * @param string       $domain Text domain.
		 *
		 * @return string|false
		 */
		public function load_script_translation_file( $file, $handle, $domain ) {
			if ( $this->app_handle !== $handle || $this->domain !== $domain ) {
				return $file;
			}

			$combined = $this->maybe_build_combined_file( determine_locale() );

			return ( is_string( $combined ) && is_readable( $combined ) ) ? $combined : $file;
		}

		/**
		 * Returns the combined file path for a locale, rebuilding it first
		 * when it is missing or older than the newest chunk JSON (covers Loco
		 * saves and language-pack updates that slipped past the hooks).
		 *
		 * @param string $locale Locale being served.
		 *
		 * @return string Combined file path (existence not guaranteed).
		 */
		protected function maybe_build_combined_file( $locale ) {
			/**
			 * Keyed by output filename, not locale: method statics are shared
			 * with subclasses (WFFN_Pro_Translations), which build a
			 * different file for the same locale in the same request.
			 */
			static $checked = array();

			$lang_dir = WP_LANG_DIR . '/plugins/';
			$filename = $this->get_combined_translation_filename( $locale );
			$combined = $lang_dir . $filename;

			if ( isset( $checked[ $filename ] ) || 'en_US' === $locale ) {
				return $combined;
			}
			$checked[ $filename ] = true;

			$chunks = array_merge( $this->get_chunk_files( $locale ), array_keys( $this->get_extra_chunk_files( $locale ) ) );
			if ( empty( $chunks ) ) {
				return $combined;
			}

			$combined_mtime = file_exists( $combined ) ? (int) filemtime( $combined ) : 0;
			$newest_chunk   = max( array_map( 'filemtime', $chunks ) );

			/**
			 * <= because filemtime has 1-second resolution: a chunk saved in
			 * the same second as the last rebuild must still trigger one (the
			 * rebuild bumps the combined mtime, so ties never loop).
			 */
			if ( $combined_mtime <= $newest_chunk ) {
				$this->build_and_save_translations( $locale );
			}

			return $combined;
		}

		/**
		 * @param string $domain Text domain.
		 * @param string $locale Locale.
		 *
		 * @return string
		 */
		protected function get_combined_translation_filename( $locale ) {
			return $this->domain . '-' . $locale . '-' . $this->app_handle . '.json';
		}

		/**
		 * Combined output filenames that must never be treated as chunks —
		 * both the free and the pro variant, whichever instance runs, or the
		 * two combiners would see each other's output as a fresh chunk and
		 * rebuild in a loop.
		 *
		 * @param string $locale Locale.
		 *
		 * @return array
		 */
		protected function get_excluded_combined_basenames( $locale ) {
			$base = $this->domain . '-' . $locale . '-' . $this->app_handle;

			return array( $base . '.json', $base . '-pro.json' );
		}

		/**
		 * Chunk JSON candidates for a locale, excluding combined output
		 * files. Kept cheap (no content reads) so it can gate staleness.
		 *
		 * @param string $locale Locale.
		 *
		 * @return array
		 */
		protected function get_chunk_files( $locale ) {
			$files    = glob( WP_LANG_DIR . '/plugins/' . $this->domain . '-' . $locale . '-*.json' );
			$excluded = $this->get_excluded_combined_basenames( $locale );

			if ( false === $files ) {
				return array();
			}

			return array_values(
				array_filter(
					$files,
					function ( $file ) use ( $excluded ) {
						return ! in_array( basename( $file ), $excluded, true );
					}
				)
			);
		}

		/**
		 * Extra directories to scan for chunk JSONs, mapped to whether files
		 * WITHOUT a source reference may still be trusted as app
		 * translations. Referenced files are always verified against the
		 * dist folders regardless of directory; unreferenced ones are only
		 * accepted from plugin-shipped locations.
		 *
		 * Loco Translate saves to LOCO_LANG_DIR/plugins by default ("custom"
		 * location) and its chunk JSONs are never requested per-script once
		 * the chunks stop being registered — scanning the dir is the only way
		 * those translations reach the app.
		 *
		 * @return array path => trust_unreferenced
		 */
		protected function get_extra_chunk_dirs() {
			$loco_dir = defined( 'LOCO_LANG_DIR' ) ? LOCO_LANG_DIR : WP_LANG_DIR . '/loco';

			return array(
				$loco_dir . '/plugins'            => false,
				WFFN_PLUGIN_DIR . '/languages'    => true,
			);
		}

		/**
		 * Chunk JSON candidates from the extra directories, as
		 * array( path => trust_unreferenced ).
		 *
		 * @param string $locale Locale.
		 *
		 * @return array
		 */
		protected function get_extra_chunk_files( $locale ) {
			$excluded = $this->get_excluded_combined_basenames( $locale );
			$files    = array();

			foreach ( $this->get_extra_chunk_dirs() as $dir => $trust_unreferenced ) {
				$found = glob( trailingslashit( $dir ) . $this->domain . '-' . $locale . '-*.json' );
				if ( ! is_array( $found ) ) {
					continue;
				}
				foreach ( $found as $file ) {
					if ( ! in_array( basename( $file ), $excluded, true ) ) {
						$files[ $file ] = $trust_unreferenced;
					}
				}
			}

			return $files;
		}

		/**
		 * Merges every chunk JSON for the locale into the combined file.
		 *
		 * Handles both the official GlotPress format (reference under
		 * comment.reference, strings under locale_data.messages) and
		 * user-generated formats from wp-cli make-json / Loco Translate
		 * (reference under source, strings possibly keyed by domain).
		 *
		 * @param string $locale Locale.
		 */
		protected function build_and_save_translations( $locale ) {
			if ( ! function_exists( 'get_filesystem_method' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( 'direct' !== get_filesystem_method() ) {
				return;
			}
			WP_Filesystem();
			global $wp_filesystem;

			$messages = array();

			foreach ( $this->get_chunk_files( $locale ) as $json_file ) {
				$this->merge_chunk( $messages, $json_file, false );
			}
			foreach ( $this->get_extra_chunk_files( $locale ) as $json_file => $trust_unreferenced ) {
				$this->merge_chunk( $messages, $json_file, $trust_unreferenced );
			}

			if ( empty( $messages ) ) {
				return;
			}

			if ( ! isset( $messages[''] ) ) {
				$messages[''] = array(
					'domain' => 'messages',
					'lang'   => $locale,
				);
			}

			$combined = array(
				'domain'      => 'messages',
				'locale_data' => array( 'messages' => $messages ),
			);

			$wp_filesystem->put_contents(
				WP_LANG_DIR . '/plugins/' . $this->get_combined_translation_filename( $locale ),
				wp_json_encode( $combined )
			);
		}

		/**
		 * Merges one chunk JSON's entries into $messages.
		 *
		 * A chunk carrying a source reference (comment.reference in GlotPress
		 * files, source in wp-cli/Loco files) must reference an app dist
		 * folder; a chunk without any reference is only accepted when the
		 * caller marks it trusted (plugin-shipped locations).
		 *
		 * @param array  $messages           Accumulated locale_data entries (by ref).
		 * @param string $json_file          Chunk file path.
		 * @param bool   $trust_unreferenced Whether a chunk without a reference may be merged.
		 */
		protected function merge_chunk( &$messages, $json_file, $trust_unreferenced ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem->is_readable( $json_file ) ) {
				return;
			}

			$chunk = json_decode( $wp_filesystem->get_contents( $json_file ), true );
			if ( empty( $chunk['locale_data'] ) || ! is_array( $chunk['locale_data'] ) ) {
				return;
			}

			$reference = null;
			if ( isset( $chunk['comment']['reference'] ) ) {
				$reference = $chunk['comment']['reference'];
			} elseif ( isset( $chunk['source'] ) && '' !== $chunk['source'] ) {
				$reference = $chunk['source'];
			}

			if ( null === $reference ) {
				if ( ! $trust_unreferenced ) {
					return;
				}
			} else {
				$matches = false;
				foreach ( $this->dist_folders as $dist_folder ) {
					if ( is_string( $reference ) && false !== strpos( $reference, $dist_folder ) ) {
						$matches = true;
						break;
					}
				}
				if ( ! $matches ) {
					return;
				}
			}

			$locale_data = array();
			if ( isset( $chunk['locale_data']['messages'] ) ) {
				$locale_data = $chunk['locale_data']['messages'];
			} elseif ( isset( $chunk['locale_data'][ $this->domain ] ) ) {
				$locale_data = $chunk['locale_data'][ $this->domain ];
			}

			if ( ! is_array( $locale_data ) || array() === $locale_data ) {
				return;
			}

			$messages = array_merge( $messages, $locale_data );
		}

		/**
		 * Rebuilds combined files when language packs for this plugin update.
		 *
		 * @param WP_Upgrader $instance   Upgrader instance.
		 * @param array       $hook_extra Info about the upgraded language packs.
		 */
		public function combine_translation_chunk_files( $instance, $hook_extra ) {
			if ( ! is_a( $instance, 'Language_Pack_Upgrader' ) || ! isset( $hook_extra['translations'] ) || ! is_array( $hook_extra['translations'] ) ) {
				return;
			}

			foreach ( $hook_extra['translations'] as $translation ) {
				if ( 'plugin' === $translation['type'] && $this->domain === $translation['slug'] ) {
					$this->build_and_save_translations( $translation['language'] );
				}
			}
		}

		/**
		 * Rebuilds the current locale's combined file on plugin activation.
		 *
		 * @param string $filename Activated plugin filename.
		 */
		public function potentially_generate_translation_strings( $filename ) {
			$locale = determine_locale();
			if ( 'en_US' === $locale ) {
				return;
			}

			$this->build_and_save_translations( $locale );
		}
	}
}
