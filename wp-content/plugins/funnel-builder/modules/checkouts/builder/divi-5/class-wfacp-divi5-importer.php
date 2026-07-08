<?php

/**
 * Divi 5 Importer
 *
 * @since 1.0.0
 */

// CRITICAL: Check if Divi constants are available before requiring files
if ( ! defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
	// Try to get Divi plugin directory
	if ( defined( 'ET_BUILDER_5_DIR' ) ) {
		// Divi 5 - ET_BUILDER_5_DIR points to the builder-5 directory
		// Portability.php is in the parent directory
		$et_builder_dir = dirname( ET_BUILDER_5_DIR );
	} elseif ( defined( 'ET_BUILDER_DIR' ) ) {
		// Divi 4 - ET_BUILDER_DIR points to the builder directory
		$et_builder_dir = dirname( ET_BUILDER_DIR );
	} else {
		// Fallback: try to find Divi plugin directory
		$et_builder_dir = '';
		if ( file_exists( WP_PLUGIN_DIR . '/divi-builder/core/components/Portability.php' ) ) {
			$et_builder_dir = WP_PLUGIN_DIR . '/divi-builder';
		} elseif ( file_exists( WP_PLUGIN_DIR . '/Divi/core/components/Portability.php' ) ) {
			$et_builder_dir = WP_PLUGIN_DIR . '/Divi';
		} elseif ( file_exists( get_template_directory() . '/includes/builder/core/components/Portability.php' ) ) {
			// Divi theme
			$et_builder_dir = get_template_directory() . '/includes/builder';
		}
	}

	if ( ! empty( $et_builder_dir ) && file_exists( $et_builder_dir . '/core/components/Portability.php' ) ) {
		include_once $et_builder_dir . '/core/components/Portability.php';
	}
} elseif ( ! class_exists( 'ET_Core_Portability' ) ) {
		include_once ET_BUILDER_PLUGIN_DIR . '/core/components/Portability.php';
}

if ( ! class_exists( 'WFACP_Divi5_Importer' ) && class_exists( 'ET_Core_Portability' ) ) {
	#[\AllowDynamicProperties]
	class WFACP_Divi5_Importer extends ET_Core_Portability {

		public function __construct() {
			// Don't need to call Parent Constructor because sometimes other divi addon created fatal error Like Monarch Plugin.
			add_action( 'wfacp_template_removed', array( $this, 'delete_divi_data' ) );
		}

		public function single_template_import( $post_id, $content, $checkout_settings = array() ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);

			$this->prevent_failure();
			self::$_doing_import = true;

			if ( ! is_array( $content ) && is_string( $content ) ) {
				try {
					$content = json_decode( $content, true );
				} catch ( Throwable $error ) {
					return false;
				}
			}

			if ( ! is_array( $content ) || empty( $content['data'] ) ) {
				return false;
			}

			require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			$alien_count = WFFN_Content_Validator::sanitize_response_urls( $content );
			if ( $alien_count >= 5 ) {
				return false;
			}

			$data = $content['data'];

			$data = reset( $data );
			if ( WFFN_Content_Validator::contains_php_code( $data ) || WFFN_Content_Validator::contains_dangerous_tags( $data ) ) {
				return false;
			}

			$success = true;
			$result  = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $data,
				)
			);

			if ( $result instanceof WP_Error ) {
				$success = false;
			}

			return $success;
		}

		/**
		 * Serialize images in chunks.
		 *
		 * @param array   $images
		 * @param string  $method Method applied on images.
		 * @param string  $id Unique ID to use for temporary files.
		 * @param integer $chunk
		 *
		 * @return array
		 * @since 4.0
		 */
		protected function chunk_images( $images, $method, $id, $chunk = 0 ) {
			$images_per_chunk = 100;
			$chunks           = 1;

			/**
			 * Filters whether or not images in the file being imported should be paginated.
			 *
			 * @param bool $paginate_images Default `true`.
			 *
			 * @since 3.0.99
			 */
			$paginate_images = apply_filters( 'et_core_portability_paginate_images', true );

			if ( $paginate_images && count( $images ) > $images_per_chunk ) {
				$chunks       = ceil( count( $images ) / $images_per_chunk );
				$slice        = $images_per_chunk * $chunk;
				$images       = array_slice( $images, $slice, $images_per_chunk );
				$images       = $this->$method( $images );
				$filesystem   = $this->get_filesystem();
				$temp_file_id = sanitize_file_name( "images_{$id}" );
				$temp_file    = $this->temp_file( $temp_file_id, 'et_core_export' );
				$temp_images  = json_decode( $filesystem->get_contents( $temp_file ), true );

				if ( is_array( $temp_images ) ) {
					$images = array_merge( $temp_images, $images );
				}

				if ( $chunk + 1 < $chunks ) {
					$filesystem->put_contents( $temp_file, wp_json_encode( (array) $images ) );
				} else {
					$this->delete_temp_files( 'et_core_export', array( $temp_file_id => $temp_file ) );
				}
			} else {
				$images = $this->$method( $images );
			}

			return array(
				'ready'  => $chunk + 1 >= $chunks,
				'chunks' => $chunks,
				'images' => $images,
			);
		}

		public function delete_divi_data( $post_id ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			delete_post_meta( $post_id, 'et_enqueued_post_fonts' );
		}
	}
}
