<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFFN_Compatibility_With_Polylang_plugin
 */
if ( ! class_exists( 'WFFN_Compatibility_With_Polylang_plugin' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Compatibility_With_Polylang_plugin extends WFFN_REST_Controller {
		protected $namespace        = 'funnelkit-app';
		public $is_language_support = true;
		public function __construct() {

			add_filter( 'wffn_funnel_next_link', array( $this, 'polylang_funnel_next_link_function' ), 10, 1 );
			add_filter( 'wffn_filter_upsells', array( $this, 'filter_upsells_by_language' ), 10, 2 );
			add_filter( 'wffn_filter_thankyou_by_language', array( $this, 'filter_thankyou_by_language' ), 10, 2 );
			add_action( 'rest_api_init', array( $this, 'register_endpoint' ), 12 );
		}

		/**
		 * Registers REST API endpoints for multilingual functionality
		 */
		public function register_endpoint() {
			register_rest_route(
				$this->namespace,
				'/multilingual/funnel-step-languages/',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_funnel_step_languages' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'update_funnel_step_languages' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					),
				)
			);
		}

		public function get_write_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
		}

		public function get_read_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
		}

		/**
		 * Retrieves funnel step languages information for the Polylang
		 *
		 * @param WP_REST_Request $request Request object containing funnel ID
		 *
		 * @return WP_REST_Response Response with language information for each funnel step
		 */
		public static function get_funnel_step_languages( $request ) {
			try {
				$funnel_id = $request->get_param( 'fid' );
				$funnel    = self::getFunnelData( $funnel_id );

				if ( empty( $funnel['steps'] ) ) {
					wp_send_json_error( array( 'message' => 'No funnel steps found.' ) );
					exit;
				}
				$funnel_metadata  = array(
					'id'          => $funnel['id'] ?? null,
					'title'       => $funnel['title'] ?? null,
					'description' => $funnel['description'] ?? null,
					'count_data'  => $funnel['count_data'] ?? null,
				);
				$active_languages = pll_languages_list();
				$default_language = pll_default_language();

				$common_language_options = array_reduce(
					$active_languages,
					function ( $result, $lang ) {
						$result[ $lang ] = array(
							'code'         => $lang,
							'display_name' => Locale::getDisplayLanguage( $lang ),
						);

						return $result;
					},
					array()
				);

				$funnel_step_ids = self::getAllFunnelStepIds( $funnel['steps'] );
				$formatted_steps = array();
				foreach ( $funnel['steps'] as $step ) {
					$formatted_step    = self::formatFunnelStep( $step, $funnel_step_ids );
					$formatted_steps[] = $formatted_step;
				}
				$enable_value = WFFN_Core()->get_dB()->get_meta( $funnel_id, 'enable_translation' );
				$enable       = ( $enable_value === null ) ? false : ( $enable_value === 'yes' );

				return rest_ensure_response(
					array(
						'result'  => array(
							'items'            => $formatted_steps,
							'base_language'    => $default_language,
							'language_options' => $common_language_options,
							'funnel_data'      => $funnel_metadata,
							'enable'           => $enable,
						),
						'status'  => true,
						'message' => __( 'Steps Languages found.', 'funnel-builder' ),
					)
				);

			} catch ( Exception | Error $e ) {
				return rest_ensure_response(
					array(
						'result'  => array(),
						'status'  => false,
						'message' => __( $e->getMessage(), 'funnel-builder' ), //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					)
				);
			}
		}

		/**
		 * Retrieves funnel data by funnel ID.
		 * Fetches the funnel data and decodes it into an associative array.
		 *
		 * @param string $fid Funnel ID.
		 *
		 * @return array The decoded funnel data.
		 */
		private static function getFunnelData( $fid ) {
			$funnel = WFFN_REST_Funnels::get_instance();
			$funnel = $funnel->get_funnel_data( $fid, true );

			return json_decode( wp_json_encode( $funnel ), true );
		}

		/**
		 * Formats a single funnel step by including necessary language information.
		 * Retrieves language options, selected language, and translations for the step.
		 *
		 * @param array $step The funnel step data.
		 * @param array $funnel_step_ids Array of all step IDs in the funnel (including substeps).
		 *
		 * @return array The formatted funnel step with language and translation data.
		 */
		private static function formatFunnelStep( $step, $funnel_step_ids = array() ) {
			$post_id           = $step['id'];
			$selected_language = pll_get_post_language( $post_id );
			$translations      = self::getTranslations( $post_id, $funnel_step_ids );

			$formatted_step = array(
				'id'                => $step['id'],
				'type'              => $step['type'],
				'title'             => $step['_data']['title'] ?? '',
				'substeps'          => self::formatSubsteps( $step['substeps'] ),
				'selected_language' => $selected_language,
				'translations'      => $translations,
			);

			return $formatted_step;
		}

		/**
		 * Retrieves the translations for a given post (funnel step).
		 * This function fetches all translations of a post, excluding the post itself.
		 * Only returns translations that exist in the current funnel.
		 *
		 * @param int   $post_id The ID of the post (funnel step).
		 * @param array $funnel_step_ids Array of all step IDs in the funnel (including substeps).
		 *
		 * @return array Associative array of translations with language code as key and post ID as value.
		 */
		private static function getTranslations( $post_id, $funnel_step_ids = array() ) {
			$translations = array();

			if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_get_post_translations' ) ) {
				$current_language = pll_get_post_language( $post_id );

				$default_language = pll_default_language();

				if ( $current_language !== $default_language ) {
					$all_translations = pll_get_post_translations( $post_id );

					if ( isset( $all_translations[ $default_language ] ) && ! empty( $all_translations[ $default_language ] ) ) {
						return $translations;
					}
				}

				$all_translations = pll_get_post_translations( $post_id );

				foreach ( $all_translations as $lang => $translated_id ) {
					if ( $translated_id !== $post_id ) {
						// Only include translation if it exists in the current funnel
						if ( empty( $funnel_step_ids ) || in_array( $translated_id, $funnel_step_ids, true ) ) {
							$translations[ $lang ] = $translated_id;
						}
					}
				}
			}

			return $translations;
		}

		/**
		 * Formats the substeps for a given funnel step.
		 * Returns an array of substep IDs and titles, formatted for the response.
		 *
		 * @param array $substeps Array of substeps for a given funnel step.
		 *
		 * @return array Formatted substeps including ID and title.
		 */
		private static function formatSubsteps( $substeps ) {
			$formatted_substeps = array();
			if ( ! empty( $substeps ) && is_array( $substeps ) ) {
				foreach ( $substeps as $substep_key => $substep_group ) {
					$formatted_substeps[ $substep_key ] = array();
					foreach ( $substep_group as $substep ) {
						$formatted_substeps[ $substep_key ][] = array(
							'id'    => $substep['id'],
							'title' => $substep['_data']['title'] ?? '',
						);
					}
				}
			}

			return $formatted_substeps;
		}

		/**
		 * Collects all step IDs and substep IDs from the funnel steps.
		 * This includes main steps and all their substeps.
		 *
		 * @param array $steps Array of funnel steps.
		 *
		 * @return array Array of all step IDs (including substeps) in the funnel.
		 */
		private static function getAllFunnelStepIds( $steps ) {
			$step_ids = array();

			if ( ! empty( $steps ) && is_array( $steps ) ) {
				foreach ( $steps as $step ) {
					// Add main step ID
					if ( isset( $step['id'] ) ) {
						$step_ids[] = (int) $step['id'];
					}

					// Add substep IDs
					if ( isset( $step['substeps'] ) && is_array( $step['substeps'] ) ) {
						foreach ( $step['substeps'] as $substep_group ) {
							if ( is_array( $substep_group ) ) {
								foreach ( $substep_group as $substep ) {
									if ( isset( $substep['id'] ) ) {
										$step_ids[] = (int) $substep['id'];
									}
								}
							}
						}
					}
				}
			}

			return $step_ids;
		}


		/**
		 * Updates language associations for funnel steps
		 *
		 * @param WP_REST_Request $request Request object containing steps data with language information
		 *
		 * @return WP_REST_Response Response indicating success or failure
		 */
		public static function update_funnel_step_languages( $request ) {
			try {
				$params        = $request->get_json_params();
				$steps         = $params['steps'] ?? array();
				$funnel_id     = $request->get_param( 'fid' );
				$enable_status = $params['enable'] ?? 'yes';
				if ( $funnel_id ) {
					self::updateFunnelEnableStatus( $funnel_id, $enable_status );
				}
				if ( empty( $steps ) ) {
					return rest_ensure_response(
						array(
							'status'  => false,
							'message' => __( 'No steps provided.', 'funnel-builder' ),
						)
					);
				}

				$step_map = array();
				foreach ( $steps as $step ) {
					if ( isset( $step['id'] ) ) {
						$step_map[ $step['id'] ] = $step;
					}
				}

				$translation_groups = array();
				$group_counter      = 0;
				$step_to_group      = array();

				foreach ( $steps as $step ) {
					$step_id = $step['id'] ?? null;
					if ( ! $step_id ) {
						continue;
					}

					if ( isset( $step_to_group[ $step_id ] ) ) {
						continue;
					}

					$group_id                        = 'group_' . $group_counter++;
					$translation_groups[ $group_id ] = array(
						'type'      => $step['type'] ?? '',
						'steps'     => array( $step_id ),
						'languages' => array( $step['selected_language'] => $step_id ),
					);
					$step_to_group[ $step_id ]       = $group_id;

					if ( isset( $step['translations'] ) && is_array( $step['translations'] ) ) {
						foreach ( $step['translations'] as $lang => $trans_id ) {
							if ( ! empty( $trans_id ) && $trans_id !== $step_id ) {
								$translation_groups[ $group_id ]['steps'][]            = $trans_id;
								$translation_groups[ $group_id ]['languages'][ $lang ] = $trans_id;
								$step_to_group[ $trans_id ]                            = $group_id;
							}
						}
					}
				}

				$allowed_types_for_translation = array( 'landing', 'optin', 'wc_checkout', 'optin_ty' );

				foreach ( $translation_groups as $group_id => $group ) {
					$type = $group['type'];

					if ( ! in_array( $type, $allowed_types_for_translation, true ) ) {
						foreach ( $group['steps'] as $step_id ) {
							$step = $step_map[ $step_id ] ?? null;
							if ( $step && isset( $step['selected_language'] ) ) {
								pll_set_post_language( $step_id, $step['selected_language'] );
							}
						}
						continue;
					}

					$translations = array();
					foreach ( $group['languages'] as $lang => $post_id ) {
						$translations[ $lang ] = $post_id;
						pll_set_post_language( $post_id, $lang );
					}

					if ( count( $translations ) > 1 ) {
						pll_save_post_translations( $translations );
					}
				}

				foreach ( $steps as $step ) {
					$type              = $step['type'] ?? '';
					$substeps          = $step['substeps'] ?? array();
					$selected_language = $step['selected_language'] ?? null;

					if ( $type === 'wc_upsells' && ! empty( $substeps ) && ! empty( $selected_language ) ) {
						self::processSubsteps( $substeps, $selected_language );
					}
				}

				return rest_ensure_response(
					array(
						'status'  => true,
						'message' => __( 'Funnel step languages updated successfully.', 'funnel-builder' ),
					)
				);
			} catch ( Exception | Error $e ) {
				return rest_ensure_response(
					array(
						'status'  => false,
						'message' => __( $e->getMessage(), 'funnel-builder' ), //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					)
				);
			}
		}

		/**
		 * Updates the enable status for a funnel
		 *
		 * @param int  $funnel_id The ID of the funnel
		 * @param bool $enable_status Whether the funnel is enabled
		 *
		 * @return bool Success or failure
		 */
		private static function updateFunnelEnableStatus( $funnel_id, $enable_status ) {
			$status_value = is_bool( $enable_status ) ? ( $enable_status ? 'yes' : 'no' ) : $enable_status;
			$result       = WFFN_Core()->get_dB()->update_meta( $funnel_id, 'enable_translation', $status_value );

			return $result !== false;
		}

		/**
		 * Processes and updates substeps for a funnel step of type 'wc_upsells'.
		 * This function sets the language for each substep and handles its translations.
		 *
		 * @param array  $substeps The substeps for the funnel step.
		 * @param string $selected_language The selected language for the funnel step.
		 */
		private static function processSubsteps( $substeps, $selected_language ) {
			foreach ( $substeps as $substep_group ) {
				foreach ( $substep_group as $substep ) {
					$substep_id = $substep['id'] ?? null;
					if ( ! $substep_id ) {
						continue;
					}

					pll_set_post_language( $substep_id, $selected_language );

					if ( isset( $substep['translations'] ) && is_array( $substep['translations'] ) ) {
						self::setTranslations( $substep, $substep_id, $selected_language );
					}
				}
			}
		}

		/**
		 * Sets translations for a funnel step's substeps.
		 * Updates the language settings for each translation of a substep.
		 *
		 * @param array  $substep The substep data.
		 * @param int    $substep_id The ID of the substep.
		 * @param string $selected_language The selected language for the substep.
		 */
		private static function setTranslations( $substep, $substep_id, $selected_language ) {
			$substep_translations = array();

			$substep_translations[ $selected_language ] = $substep_id;

			foreach ( $substep['translations'] as $language_code => $translated_substep_id ) {
				if ( ! empty( $translated_substep_id ) && $translated_substep_id !== $substep_id && is_int( $translated_substep_id ) ) {
					$substep_translations[ $language_code ] = $translated_substep_id;
					pll_set_post_language( $translated_substep_id, $language_code );
				}
			}

			if ( count( $substep_translations ) > 1 ) {
				pll_save_post_translations( $substep_translations );
			}
		}

		/**
		 * Filters the next link URL to maintain language continuity in the funnel
		 *
		 * @param int $current_step_id Current step ID
		 *
		 * @return string|bool URL of the next step in the same language or false if not found
		 */
		public function polylang_funnel_next_link_function( $current_step_id ) {

			try {
				$funnel_data = WFFN_Core()->data->get_session_funnel();
				if ( ! $funnel_data ) {
					return false;
				}

				$steps              = $funnel_data->steps;
				$current_step_index = self::getCurrentStepIndex( $current_step_id, $steps );

				if ( $current_step_index === false ) {
					return false;
				}

				$current_language = pll_get_post_language( $current_step_id );
				if ( ! $current_language ) {
					return false;
				}

				$current_translation_ids = self::getTranslationIds( $current_step_id );

				$next_step_id = self::getNextStepId( $steps, $current_step_index, $current_translation_ids, $current_language );

				if ( $next_step_id ) {
					return get_permalink( $next_step_id );
				}
			} catch ( Exception | Error $e ) {
				return $current_step_id;
			}

			return false;
		}

		/**
		 * Retrieves the index of the current step in the funnel steps array.
		 * This function finds the position of the current step in the steps list.
		 *
		 * @param int   $current_step_id The ID of the current step.
		 * @param array $steps The array of all funnel steps.
		 *
		 * @return int|false The index of the current step, or false if not found.
		 */
		private static function getCurrentStepIndex( $current_step_id, $steps ) {
			return array_search( $current_step_id, array_column( $steps, 'id' ), true );
		}

		/**
		 * Retrieves translation IDs for the current step.
		 * This function gets all translation IDs associated with the current step.
		 *
		 * @param int $current_step_id The ID of the current step.
		 *
		 * @return array An array of translation IDs for the current step.
		 */
		private static function getTranslationIds( $current_step_id ) {
			$current_translation_ids = array();
			$translations            = pll_get_post_translations( $current_step_id );

			foreach ( $translations as $lang_code => $translation_id ) {//phpcs:ignore
				if ( ! empty( $translation_id ) ) {
					$current_translation_ids[] = (int) $translation_id;
				}
			}

			return $current_translation_ids;
		}

		/**
		 * Determines the next step ID in the funnel, considering language continuity and processed steps.
		 * This function finds the next valid step in the funnel, either in the same language or a fallback language.
		 *
		 * @param array  $steps The array of all funnel steps.
		 * @param int    $current_step_index The index of the current step.
		 * @param array  $current_translation_ids The list of translation IDs for the current step.
		 * @param string $current_language The current language of the funnel step.
		 *
		 * @return int|null The ID of the next step, or null if no valid step is found.
		 */
		private static function getNextStepId( $steps, $current_step_index, $current_translation_ids, $current_language ) {
			$current_step_type = null;
			if ( isset( $steps[ $current_step_index ] ) ) {
				$current_step_type = $steps[ $current_step_index ]['type'];
			}

			$next_step_type = null;
			for ( $i = $current_step_index + 1; $i < count( $steps ); $i++ ) {
				if ( $steps[ $i ]['type'] !== $current_step_type && ! in_array( $steps[ $i ]['id'], $current_translation_ids, true ) ) {
					$next_step_type = $steps[ $i ]['type'];
					break;
				}
			}

			if ( ! $next_step_type ) {
				return null;
			}

			$processed_step_ids      = array();
			$next_step_same_language = null;
			$next_step_fallback      = null;

			for ( $i = $current_step_index + 1; $i < count( $steps ); $i++ ) {
				$next_step   = $steps[ $i ];
				$get_next_id = $next_step['id'];

				if ( $next_step['type'] !== $next_step_type ) {
					continue;
				}

				if ( in_array( $get_next_id, $current_translation_ids, true ) ) {
					continue;
				}

				$translations_of_next = pll_get_post_translations( $get_next_id );

				$skip = false;
				foreach ( $translations_of_next as $trans_id ) {
					if ( in_array( $trans_id, $processed_step_ids, true ) ) {
						$skip = true;
						break;
					}
				}

				if ( $skip ) {
					continue;
				}

				$processed_step_ids[] = $get_next_id;

				if ( $next_step_fallback === null ) {
					$next_step_fallback = $get_next_id;
				}

				$next_language = pll_get_post_language( $get_next_id );
				if ( $next_language === $current_language ) {
					$next_step_same_language = $get_next_id;
					break;
				}

				foreach ( $translations_of_next as $translation_id ) {
					if ( pll_get_post_language( $translation_id ) === $current_language ) {
						$next_step_same_language = $translation_id;
						break 2;
					}
				}
			}

			return $next_step_same_language !== null ? $next_step_same_language : $next_step_fallback;
		}


		/**
		 * Filters thank you pages to match checkout page language
		 *
		 * @param array $thankyou_page_ids Array of thank you page IDs
		 * @param array $current_step Current step information
		 *
		 * @return array Filtered thank you page IDs that match the current language
		 */
		public function filter_thankyou_by_language( $thankyou_page_ids, $current_step ) {
			try {
				if ( empty( $thankyou_page_ids ) || ! function_exists( 'pll_get_post_language' ) ) {
					return $thankyou_page_ids;
				}

				// Get target language: from current step if available, otherwise use current/default language
				$target_language = null;
				if ( ! empty( $current_step ) && isset( $current_step['id'] ) ) {
					$checkout_id       = $current_step['id'];
					$checkout_language = pll_get_post_language( $checkout_id );
					if ( $checkout_language ) {
						$target_language = $checkout_language;
					}
				}

				// Fallback to current language, then default language if step language not available
				if ( empty( $target_language ) ) {
					$target_language = pll_current_language();
					if ( empty( $target_language ) ) {
						$target_language = pll_default_language();
					}
				}

				if ( empty( $target_language ) ) {
					return $thankyou_page_ids;
				}

				$checkout_language = $target_language;

				$matching_thankyou_ids = array();

				foreach ( $thankyou_page_ids as $ty_id ) {
					$ty_lang = pll_get_post_language( $ty_id );

					if ( $ty_lang === $checkout_language ) {
						$matching_thankyou_ids[] = $ty_id;
					}
				}
			} catch ( Exception | Error $e ) {
				return $thankyou_page_ids;
			}

			return ! empty( $matching_thankyou_ids ) ? $matching_thankyou_ids : $thankyou_page_ids;
		}

		/**
		 * Filters upsell offers to match current step language
		 *
		 * @param array $upsells Array of upsell step data
		 * @param array $current_step Current step information
		 *
		 * @return array Filtered upsells that match the current language
		 */
		public function filter_upsells_by_language( $upsells, $current_step ) {
			try {
				$step_id       = $current_step['id'];
				$step_language = pll_get_post_language( $step_id );

				if ( ! $step_language ) {
					return $upsells;
				}

				$language_matching_upsells = array();

				foreach ( $upsells as $upsell ) {
					$upsell_id = $upsell['id'];

					$upsell_lang = pll_get_post_language( $upsell_id );

					if ( $upsell_lang === $step_language ) {
						$language_matching_upsells[] = $upsell;
					}
				}
			} catch ( Exception | Error $e ) {
				return $upsells;
			}

			return ! empty( $language_matching_upsells ) ? $language_matching_upsells : $upsells;
		}

		public function get_plugin_nicename() {
			return 'Polylang';
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_With_Polylang_plugin(), 'polylang' );
}
