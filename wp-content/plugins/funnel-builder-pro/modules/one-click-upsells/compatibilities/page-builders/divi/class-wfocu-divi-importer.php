<?php

/**
 * Divi Importer
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'ET_Core_Portability' ) ) {
	include_once ET_BUILDER_PLUGIN_DIR . '/core/components/Portability.php';
}
if ( ! class_exists( 'WFOCU_Divi_Importer' ) ) {
	class WFOCU_Divi_Importer extends ET_Core_Portability {

		public function __construct() {
			//Dont Need To call Parent Constructor because of some time other divi addon created fatal error Like Monarch Plugin.
			add_action( 'wfocu_template_removed', [ $this, 'delete_divi_data' ] );
		}

		public function single_template_import( $post_id, $content, $offer_settings = array() ) {
			wp_update_post( [ 'ID' => $post_id, 'post_content' => '' ] );

			$this->prevent_failure();
			self::$_doing_import = true;
			$timestamp           = $this->get_timestamp();

			if ( ! is_array( $content ) && is_string( $content ) ) {
				try {
					$content = json_decode( $content, true );
				} catch ( Exception $error ) {
					return false;
				}
			}


			$data = $content['data'];
			// Pass the post content and let js save the post.

			$data    = reset( $data );
			$success = true;
			$result  = wp_update_post( [ 'ID' => $post_id, 'post_content' => $data ] );


			if ( $result instanceof WP_Error ) {
				$success = false;
			}

			return $success;
		}

		/**
		 * Serialize images in chunks.
		 *
		 * @param array $images
		 * @param string $method Method applied on images.
		 * @param string $id Unique ID to use for temporary files.
		 * @param integer $chunk
		 *
		 * @return array
		 * @since 4.0
		 *
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
			 *
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
			wp_update_post( [ 'ID' => $post_id, 'post_content' => '' ] );
			delete_post_meta( $post_id, 'et_enqueued_post_fonts' );
		}

	}

}