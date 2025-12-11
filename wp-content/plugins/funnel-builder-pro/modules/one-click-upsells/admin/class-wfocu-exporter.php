<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFOCU_Exporter' ) ) {
	/**
	 * Class WFOCU_Exporter
	 * Handles All the methods about page builder activities
	 */
	class WFOCU_Exporter {

		private static $ins = null;
		private $funnel = null;
		private $installed_plugins = null;

		public function __construct() {
			add_action( 'admin_init', [ $this, 'maybe_export' ] );
			add_action( 'admin_init', [ $this, 'maybe_export_single' ] );
		}

		/**
		 * @return WFOCU_Exporter|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_export( $posted_data = null ) {

			$skip_nonce = false;

			if ( null == $posted_data ) {
				$posted_data = bwf_clean( $_POST );
			} else {
				$skip_nonce = true;
			}

			if ( empty( $posted_data['wfocu-action'] ) || 'export' != $posted_data['wfocu-action'] ) {
				return;
			}

			if ( ! wp_verify_nonce( $posted_data['wfocu-action-nonce'], 'wfocu-action-nonce' ) && false === $skip_nonce ) {
				return;
			}

			$user = WFOCU_Core()->role->user_access( 'funnel', 'write' );
			if ( false === $user ) {
				return;
			}

			$funnels           = WFOCU_Common::get_post_table_data();
			$funnels_to_export = [];
			foreach ( $funnels['items'] as $key => $funnel ) {
				$funnels_to_export[ $key ] = $this->export_a_funnel( $funnel['id'] );
			}
			$funnels_to_export = apply_filters( 'wfocu_export_data', $funnels_to_export );
			nocache_headers();

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wfocu-funnels-export-' . date( 'm-d-Y' ) . '.json' );
			header( 'Expires: 0' );

			echo wp_json_encode( $funnels_to_export );
			exit;
		}

		public function maybe_export_single( $posted_data = null ) {

			$skip_nonce = false;

			if ( null == $posted_data ) {
				$posted_data = bwf_clean( $_GET );
			} else {
				$skip_nonce = true;
			}

			if ( empty( $posted_data['action'] ) || 'wfocu-export' != $posted_data['action'] ) {
				return;
			}

			if ( ! wp_verify_nonce( $posted_data['_wpnonce'], 'wfocu-export' ) && false === $skip_nonce ) {
				return;
			}

			$user = WFOCU_Core()->role->user_access( 'funnel', 'write' );
			if ( false === $user ) {
				return;
			}

			$funnels_to_export    = [];
			$funnels_to_export[0] = $this->export_a_funnel( $posted_data['id'] );

			$funnels_to_export = apply_filters( 'wfocu_export_data', $funnels_to_export );
			nocache_headers();

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wfocu-funnels-export-' . gmdate( 'm-d-Y' ) . '.json' );
			header( 'Expires: 0' );

			echo wp_json_encode( $funnels_to_export );
			exit;
		}

		public function export_a_funnel( $funnel_id ) {
			$data_funnels = WFOCU_Core()->funnels->get_funnel_offers_admin( $funnel_id, false );
			$funnel_post  = get_post( $funnel_id );
			$funnel_data  = [ 'steps' => array(), 'title' => $funnel_post->post_title, 'description' => $funnel_post->post_content ];

			/**
			 * Loop over every offer
			 */
			if ( ! empty( $data_funnels['steps'] ) ) {
				foreach ( $data_funnels['steps'] as $step ) {
					$new_all_meta         = array();
					$valid_step_meta_keys = array(
						'_thumbnail_id',
						'classic-editor-remember',
						'_elementor_page_assets',
					);
					$all_meta             = get_post_meta( $step['id'] );
					$post                 = get_post( $step['id'] );
					if ( is_array( $all_meta ) ) {
						foreach ( $all_meta as $meta_key => $value ) {
							if ( substr( $meta_key, 0, strlen( '_wfocu' ) ) === '_wfocu' ) {
								$new_all_meta[ $meta_key ] = maybe_unserialize( $value[0] );
							} elseif ( in_array( $meta_key, $valid_step_meta_keys, true ) ) {
								$new_all_meta[ $meta_key ] = maybe_unserialize( $value[0] );
							} elseif ( '_wp_page_template' === $meta_key ) {
								$new_all_meta['_wp_page_template'] = $value[0];
							} elseif ( '_elementor_data' === $meta_key ) {
								$new_all_meta['_elementor_data'] = maybe_unserialize( $value[0] );
							} else {
								$new_all_meta[ $meta_key ] = $value[0];
							}

						}
					}

					$customize_key = WFOCU_SLUG . '_c_' . $step['id'];
					$template_data = get_option( $customize_key, [] );

					if ( is_array( $template_data ) && count( $template_data ) > 0 ) {
						$template_data_keys = array_keys( $template_data );
						foreach ( $template_data_keys as $value ) {
							if ( ! is_null( $value ) && false !== strpos( $value, 'wfocu_product' ) ) {
								unset( $template_data[ $value ] );
							}
							if ( 'wfocu_guarantee_guarantee_icon_text' === $value ) {
								foreach ( $template_data[ $value ] as $key => $v ) {
									if ( ! empty( $v['image'] ) ) {
										$template_data[ $value ][ $key ]['image'] = $this->get_image_url( $v['image'] );
									}
								}
							}

						}
					}

					if ( isset( $new_all_meta['_wfocu_setting'] ) && isset( $new_all_meta['_wfocu_setting']->settings ) && isset( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_accepted ) && 0 < absint( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_accepted ) ) {
						$new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_accepted_index = WFOCU_Core()->offers->get_offer_index( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_accepted, $funnel_id );
					}
					if ( isset( $new_all_meta['_wfocu_setting'] ) && isset( $new_all_meta['_wfocu_setting']->settings ) && isset( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_rejected ) && 0 < absint( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_rejected ) ) {
						$new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_rejected_index = WFOCU_Core()->offers->get_offer_index( $new_all_meta['_wfocu_setting']->settings->jump_to_offer_on_rejected, $funnel_id );
					}
					$new_all_meta['customizer_data'] = $template_data;
					if ( isset( $new_all_meta['_wfocu_setting_override'] ) ) {
						unset( $new_all_meta['_wfocu_setting_override'] );
					}
					$funnel_data['steps'][] = array(
						'title'        => $post->post_title,
						'slug'         => $post->post_name,
						'state'        => $step['state'],
						'type'         => $all_meta['_offer_type'][0],
						'meta'         => $new_all_meta,
						'post_content' => $post->post_content,
					);
				}
			}
			/**
			 * Treat Primary Meta of the funnel
			 */
			$funnel_meta          = array();
			$valid_step_meta_keys = array(
				'_wp_page_template',
				'_thumbnail_id',
				'classic-editor-remember',
				'_elementor_page_assets',
			);
			$all_meta             = get_post_meta( $funnel_id );
			if ( is_array( $all_meta ) ) {
				foreach ( $all_meta as $meta_key => $value ) {
					if ( substr( $meta_key, 0, strlen( '_wfocu' ) ) === '_wfocu' ) {
						$funnel_meta[ $meta_key ] = maybe_unserialize( $value[0] );
					} elseif ( in_array( $meta_key, $valid_step_meta_keys, true ) ) {
						$funnel_meta[ $meta_key ] = maybe_unserialize( $value[0] );
					}
				}
			}

			return array_merge( $funnel_meta, $funnel_data );
		}

		protected function get_image_url( $attachment_id ) {
			return wp_get_attachment_image_src( $attachment_id )[0];
		}
	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'export', 'WFOCU_Exporter' );
	}
}