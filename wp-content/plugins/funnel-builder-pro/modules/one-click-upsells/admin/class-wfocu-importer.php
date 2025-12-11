<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFOCU_Importer' ) ) {
	/**
	 * Class WFOCU_Importer
	 * Handles All the methods about page builder activities
	 */
	class WFOCU_Importer {

		private static $ins = null;
		private $funnel = null;
		private $installed_plugins = null;
		public $is_imported = false;

		public function __construct() {
			add_action( 'admin_init', [ $this, 'maybe_import' ] );
		}

		/**
		 * @return WFOCU_Importer|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Import our exported file
		 *
		 * @since 1.1.4
		 */
		function maybe_import( $posted_data = null ) {
			$skip_nonce = false;

			if ( null == $posted_data ) {
				$posted_data = bwf_clean( $_POST );
			} else {
				$skip_nonce = true;
			}

			if ( empty( $posted_data['wfocu-action'] ) || 'import' != $posted_data['wfocu-action'] ) {
				return;
			}
			if ( ! wp_verify_nonce( $posted_data['wfocu-action-nonce'], 'wfocu-action-nonce' ) && false === $skip_nonce ) {
				return;
			}

			$user = WFOCU_Core()->role->user_access( 'funnel', 'write' );
			if ( false === $user ) {
				return;
			}

			$filename  = $_FILES['file']['name'];
			$file_info = explode( '.', $filename );
			$extension = end( $file_info );

			if ( 'json' != $extension ) {
				wp_die( __( 'Please upload a valid .json file', 'wfocu' ) );
			}

			$file = $_FILES['file']['tmp_name'];

			if ( empty( $file ) ) {
				wp_die( __( 'Please upload a file to import', 'wfocu' ) );
			}

			// Retrieve the settings from the file and convert the JSON object to an array.
			$funnels = json_decode( file_get_contents( $file ), true );

			$this->import_from_json_data( $funnels );
			$this->is_imported = true;
		}

		public function import_from_json_data( $funnels ) {

			$imported_funnels = [];
			if ( $funnels ) {
				$offers = [];
				foreach ( $funnels as $funnel ) {
					$funnel_title = $funnel['title'];
					if ( post_exists( $funnel['title'] ) ) {
						$funnel_title = $funnel['title'];
					}
					foreach ( is_array( $funnel['steps'] ) ? $funnel['steps'] : array() as $offer_step ) {

						$offer_data = array(
							'id'           => '{{offer_id}}',
							'name'         => $offer_step['title'],
							'type'         => $offer_step['type'],
							'state'        => $offer_step['state'],
							'post_content' => isset( $offer_step['post_content'] ) ? $offer_step['post_content'] : '',
							'slug'         => isset( $offer_step['slug'] ) ? $offer_step['slug'] : '',
							'meta'         => array(
								'_offer_type'    => $offer_step['type'],
								'_wfocu_setting' => (object) array(
									'variations'            => (object) array(),
									'have_multiple_product' => 0,
								),
							),
						);
						if ( isset( $offer_step['meta'] ) && is_array( $offer_step['meta'] ) ) {
							$offer_data['meta'] = wp_parse_args( $offer_data['meta'], $offer_step['meta'] );
						}

						/**
						 * wrapping with isset to avoid any undefined index issue
						 */
						if ( isset( $offer_step['meta']['_wfocu_setting']['products'] ) ) {
							$offer_data['meta']['_wfocu_setting']->products = (object) $offer_step['meta']['_wfocu_setting']['products'];
						}
						if ( isset( $offer_step['meta']['_wfocu_setting']['fields'] ) ) {
							$offer_data['meta']['_wfocu_setting']->fields = (object) $this->maybe_deep_object( $offer_step['meta']['_wfocu_setting']['fields'] );
						}
						if ( isset( $offer_step['meta']['_wfocu_setting']['template'] ) ) {
							$offer_data['meta']['_wfocu_setting']->template = $offer_step['meta']['_wfocu_setting']['template'];
						}
						if ( isset( $offer_step['meta']['_wfocu_setting']['template_group'] ) ) {
							$offer_data['meta']['_wfocu_setting']->template_group = $offer_step['meta']['_wfocu_setting']['template_group'];
						}
						if ( isset( $offer_step['meta']['_wfocu_setting']['settings'] ) ) {
							$offer_data['meta']['_wfocu_setting']->settings = $offer_step['meta']['_wfocu_setting']['settings'];
						}
						if ( isset( $offer_step['meta']['customizer_data'] ) ) {
							$offer_data['meta']['_customizer_data'] = $offer_step['meta']['customizer_data'];
						}
						if ( isset( $offer_data['meta']['_wfocu_setting'] ) && ! empty( $offer_data['meta']['_wfocu_setting'] ) ) {
							$offer_data['meta']['_wfocu_setting'] = (object) $offer_data['meta']['_wfocu_setting'];
						}
						if ( isset( $offer_step['template'] ) && ! empty( $offer_step['template'] ) ) {
							$offer_data['meta']['_tobe_import_template']      = $offer_step['template'];
							$offer_data['meta']['_tobe_import_template_type'] = $offer_step['template_type'];
						}
						$offers[] = $offer_data;
					}

					$funnel_to_create = array(
						'title'       => $funnel_title,
						'description' => ( isset( $funnel['description'] ) && ! empty( $funnel['description'] ) ) ? $funnel['description'] : '',
						'status'      => ( isset( $funnel['status'] ) && ! empty( $funnel['status'] ) ) ? 'publish' : WFOCU_SLUG . '-disabled',
						'priority'    => WFOCU_Common::get_next_funnel_priority(),
						'offers'      => $offers,
						'meta'        => array(
							'_wfocu_is_rules_saved'   => "yes",
							'_wfocu_rules'            => isset( $funnel['_wfocu_rules'] ) ? $funnel['_wfocu_rules'] : [],
							'_funnel_steps'           => array(),
							'_funnel_upsell_downsell' => array(),
						),
					);
					if ( isset( $funnel['_wfocu_settings'] ) ) {
						$funnel_to_create['meta']['_wfocu_settings'] = $funnel['_wfocu_settings'];
					}


					$id = WFOCU_Core()->funnels->generate_preset_funnel_data( $funnel_to_create );
					array_push( $imported_funnels, $id );

				}
			}

			return $imported_funnels;
		}

		public function import_customizer_data( $offer_id, $customizer_content ) {

			foreach ( $customizer_content as $k => $val ) {

				if ( 'wfocu_guarantee_guarantee_icon_text' === $k ) {
					foreach ( $customizer_content[ $k ] as $key => $v ) {
						if ( ! empty( $v['image'] ) ) {
							$customizer_content[ $k ][ $key ]['image'] = $this->import_image( $v['image'] );
						}
					}
				}
			}

			$customize_key = WFOCU_SLUG . '_c_' . $offer_id;
			update_option( $customize_key, $customizer_content );
		}


		public function import_image( $url ) {
			$saved_image = $this->get_saved_image( $url );

			if ( $saved_image ) {
				return $saved_image;
			}

			// Extract the file name and extension from the url.
			$filename = basename( $url );

			$file_content = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

			if ( empty( $file_content ) ) {
				return false;
			}

			$upload = wp_upload_bits( $filename, null, $file_content );

			$post = [
				'post_title' => $filename,
				'guid'       => $upload['url'],
			];

			$info = wp_check_filetype( $upload['file'] );
			if ( $info ) {
				$post['post_mime_type'] = $info['type'];
			} else {
				// For now just return the origin attachment
				return $url;
				// return new \WP_Error( 'attachment_processing_error', __( 'Invalid file type.', 'elementor' ) );
			}

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$post_id = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );
			update_post_meta( $post_id, '_bwf_source_image_hash', $this->get_hash_image( $url ) );

			$new_attachment = $upload['url'];

			return $new_attachment;
		}


		/**
		 * Get saved image.
		 *
		 * Retrieve new image ID, if the image has a new ID after the import.
		 *
		 * @param array $attachment The attachment.
		 *
		 * @return false|array New image ID  or false.
		 * @since 2.0.0
		 * @access private
		 *
		 */
		private function get_saved_image( $url ) {

			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM `' . $wpdb->postmeta . '`
					WHERE `meta_key` = \'_bwf_source_image_hash\'
						AND `meta_value` = %s
				;', $this->get_hash_image( $url ) ) );

			if ( $post_id ) {
				$new_attachment = wp_get_attachment_url( $post_id );


				return $new_attachment;
			}

			return false;
		}

		/**
		 * Get image hash.
		 *
		 * Retrieve the sha1 hash of the image URL.
		 *
		 * @param string $attachment_url The attachment URL.
		 *
		 * @return string Image hash.
		 * @since 2.0.0
		 * @access private
		 *
		 */
		private function get_hash_image( $attachment_url ) {
			return sha1( $attachment_url );
		}

		public function maybe_deep_object( $array ) {
			foreach ( $array as $key => &$val ) {
				$val = (object) $val;
			}

			return $array;
		}
	}


	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'import', 'WFOCU_Importer' );
	}
}