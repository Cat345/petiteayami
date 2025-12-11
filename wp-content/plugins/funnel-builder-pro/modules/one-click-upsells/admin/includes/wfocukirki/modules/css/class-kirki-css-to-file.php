<?php
/**
 * Writes compiled CSS to a file.
 *
 * @package     WFOCUKirki
 * @subpackage  CSS Module
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.0
 */
if ( ! class_exists( 'WFOCUKirki_CSS_To_File' ) ) {
	/**
	 * Handles writing CSS to a file.
	 */
	class WFOCUKirki_CSS_To_File {

		/**
		 * Fallback to inline CSS?
		 *
		 * @access protected
		 * @since 3.0.0
		 * @var bool
		 */
		protected $fallback = false;

		/**
		 * Constructor.
		 *
		 * @access public
		 * @since 3.0.0
		 */
		public function __construct() {

			// If the file doesn't exist, create it.
			if ( ! file_exists( $this->get_path( 'file' ) ) ) {
				// If the file-write fails, fallback to inline
				// and cache the failure so we don't try again immediately.
				$this->write_file();
			}
			add_action( 'customize_save_after', array( $this, 'write_file' ) );
		}

		/**
		 * Gets the path of the CSS file and folder in the filesystem.
		 *
		 * @access protected
		 *
		 * @param string $context Can be "file" or "folder". If empty, returns both as array.
		 *
		 * @return string|array
		 * @since 3.0.0
		 */
		protected function get_path( $context = '' ) {

			$upload_dir = wp_upload_dir();

			$paths = array(
				'file'   => wp_normalize_path( $upload_dir['basedir'] . '/kirki-css/styles.css' ),
				'folder' => wp_normalize_path( $upload_dir['basedir'] . '/kirki-css' ),
			);

			if ( 'file' === $context ) {
				return $paths['file'];
			}
			if ( 'folder' === $context ) {
				return $paths['folder'];
			}

			return $paths;

		}

		/**
		 * Gets the URL of the CSS file in the filesystem.
		 *
		 * @access public
		 * @return string
		 * @since 3.0.0
		 */
		public function get_url() {

			$upload_dir = wp_upload_dir();

			return esc_url_raw( $upload_dir['baseurl'] . '/kirki-css/styles.css' );

		}

		/**
		 * Gets the timestamp of the file.
		 * This will be used as "version" for cache-busting purposes.
		 *
		 * @access public
		 * @return integer|false
		 * @since 3.0.0
		 */
		public function get_timestamp() {

			if ( file_exists( $this->get_path( 'file' ) ) ) {
				return filemtime( $this->get_path( 'file' ) );
			}

			return false;
		}

		/**
		 * Writes the file to disk.
		 *
		 * @access public
		 * @return bool
		 * @since 3.0.0
		 */
		public function write_file() {

			$css     = array();
			$configs = WFOCUKirki::$config;
			foreach ( $configs as $config_id => $args ) {
				// Get the CSS we want to write.
				$css[ $config_id ] = apply_filters( "wfocukirki_{$config_id}_dynamic_css", WFOCUKirki_Modules_CSS::loop_controls( $config_id ) );
			}
			$css = implode( '', $css );

			/*
			WIP: Minimize the CSS a bit. Commented-out because of https://github.com/aristath/kirki/issues/1787
			$css = str_replace( array( "\n", "\t", "\r\n" ), '', $css );
			$css = str_replace( array( '{ ', '{  ', '{   ' ), '{', $css );
			$css = str_replace( ': ', ':', $css );
			$css = str_replace( array( '; ', ';  ', ';   ' ), ';', $css );
			$css = explode( '}', $css );
			$css = array_unique( $css );
			$css = implode( $css, '}' );
			*/

			// If the folder doesn't exist, create it.
			if ( ! file_exists( $this->get_path( 'folder' ) ) ) {
				wp_mkdir_p( $this->get_path( 'folder' ) );
			}

			$filesystem = $this->get_filesystem();
			$write_file = (bool) $filesystem->put_contents( $this->get_path( 'file' ), $css );
			if ( ! $write_file ) {
				$this->fallback = true;
				set_transient( 'wfocukirki_css_write_to_file_failed', true, HOUR_IN_SECONDS );
			}

			return $write_file;

		}

		/**
		 * Gets the WP_Filesystem object.
		 *
		 * @access protected
		 * @return object
		 * @since 3.0.0
		 */
		protected function get_filesystem() {

			// The WordPress filesystem.
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
				WP_Filesystem();
			}

			return $wp_filesystem;
		}
	}
}