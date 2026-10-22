<?php
/**
 * Shared customer-journey reader/resolver.
 *
 * Single source of truth for turning the stored `journey` JSON into a clean,
 * ordered list of visited pages that the order meta box, the Analytics
 * Conversions column/modal, the "Pages Visited" filter and the CSV export all
 * agree on. It is a thin normaliser: every piece of real work delegates to the
 * existing static helpers on WFFN_Visitor_Tracking so there is exactly one copy
 * of the title/URL resolution logic.
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.16.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_Journey' ) ) {
	/**
	 * Reads and resolves the stored customer journey.
	 *
	 * The stored value is dirty by design — browser entries key by epoch seconds
	 * with a string `i`, server entries key by milliseconds with an int `i`, and
	 * rows written by the legacy FunnelKit Automations pipeline still carry `t`
	 * titles. This class absorbs all of that and never forks the underlying
	 * helpers on WFFN_Visitor_Tracking.
	 *
	 * @since 3.16.1
	 */
	class WFFN_Journey {

		/**
		 * Decode a stored journey string into the normalised { j, s } shape.
		 *
		 * @since 3.16.1
		 *
		 * @param string $raw Stored journey JSON.
		 *
		 * @return array { 'j' => array, 's' => array } — empty maps on any failure.
		 */
		public static function decode( $raw ) {
			$empty = array(
				'j' => array(),
				's' => array(),
			);

			if ( ! is_string( $raw ) || '' === $raw || ! class_exists( 'WFFN_Visitor_Tracking' ) ) {
				return $empty;
			}

			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return $empty;
			}

			return WFFN_Visitor_Tracking::journey_normalize( $decoded );
		}

		/**
		 * Resolve a stored journey into an ordered list of normalised entries.
		 *
		 * @since 3.16.1
		 *
		 * @param string $raw Stored journey JSON.
		 *
		 * @return array List of entries: { ts, at, page_id, path, url, title, site, is_local }. Empty when there is no journey.
		 */
		public static function resolve( $raw, $all_titles_by_id = [] ) {
			if ( ! class_exists( 'WFFN_Visitor_Tracking' ) ) {
				return array();
			}

			$store   = self::decode( $raw );
			$entries = ( isset( $store['j'] ) && is_array( $store['j'] ) ) ? $store['j'] : array();
			$sites   = ( isset( $store['s'] ) && is_array( $store['s'] ) ) ? $store['s'] : array();

			if ( empty( $entries ) ) {
				return array();
			}

			// Server entries always append last, so a numeric sort on the raw key
			// yields true visit order regardless of the seconds-vs-milliseconds mix.
			ksort( $entries, SORT_NUMERIC );

			$home_url = home_url( '/' );
			$origin   = WFFN_Visitor_Tracking::site_origin();
			$list     = array();

			foreach ( $entries as $key => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['u'] ) ) {
					continue;
				}

				// Normalise millisecond keys down to seconds so "time of visit"
				// and gap display are consistent across writers. Compare as float
				// first: a raw millisecond key (e.g. 1788354215231) overflows a
				// 32-bit int, so casting before the magnitude check would misfire
				// on 32-bit PHP.
				$ts_raw = (float) $key;
				$ts     = ( $ts_raw > 100000000000 ) ? (int) floor( $ts_raw / 1000 ) : (int) $ts_raw;

				$site_idx  = isset( $entry['s'] ) ? $entry['s'] : null;
				$full_path = stripslashes( rawurldecode( $entry['u'] ) );

				// Keep the full path (may carry UTM query) for a faithful link, but
				// strip the query for the display path and page-id matching.
				$q_pos = strpos( $full_path, '?' );
				$path  = ( false === $q_pos ) ? $full_path : substr( $full_path, 0, $q_pos );

				$is_local = ( null === $site_idx || ! isset( $sites[ $site_idx ] ) || untrailingslashit( $sites[ $site_idx ] ) === $origin );

				// `ts` is a UTC epoch, kept for arithmetic: gaps between steps and
				// time to convert. `at` is the same moment as a site-local wall
				// clock, which is what every other date this API sends looks like,
				// so a screen renders it without knowing a timezone at all.

				$list[] = array(
					'ts'       => $ts,
					'at'       => ( $ts > 0 && function_exists( 'wp_date' ) ) ? wp_date( 'Y-m-d H:i:s', $ts ) : '',
					'page_id'  => ( isset( $entry['i'] ) && is_numeric( $entry['i'] ) ) ? absint( $entry['i'] ) : 0,
					'path'     => $path,
					'url'      => esc_url_raw( WFFN_Visitor_Tracking::journey_resolve_url( $full_path, $site_idx, $sites, $home_url ) ),
					'title'    => WFFN_Visitor_Tracking::journey_entry_title( $entry, $path, $all_titles_by_id ),
					'site'     => $is_local ? $origin : untrailingslashit( $sites[ $site_idx ] ),
					'is_local' => $is_local,
				);
			}

			// Cap after ordering, keeping the most recent path depth (mirrors the cookie writer cap).
			$max = absint( apply_filters( 'wffn_journey_max_entries', 20 ) );
			if ( $max > 0 && count( $list ) > $max ) {
				$list = array_slice( $list, - $max );
			}

			return $list;
		}

		/**
		 * Build the human-readable export string for a resolved journey.
		 *
		 * Produces `Title (/path) > … > Conversion completed`. Titles are stripped
		 * of the separator token so the field stays parseable, and non-local steps
		 * are prefixed with their host.
		 *
		 * @since 3.16.1
		 *
		 * @param array  $steps Resolved journey list from resolve().
		 * @param string $sep   Separator between steps.
		 *
		 * @return string Export string, or '' when there is no journey.
		 */
		public static function to_text( $steps, $sep = ' > ' ) {
			if ( ! is_array( $steps ) || empty( $steps ) ) {
				return '';
			}

			$parts = array();
			foreach ( $steps as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}

				// Strip '>' (the default separator token) so the field stays parseable.
				$title = isset( $step['title'] ) ? trim( str_replace( '>', '', (string) $step['title'] ) ) : '';
				$path  = isset( $step['path'] ) ? (string) $step['path'] : '';

				// Non-local steps prefix the path with their host so it is unambiguous.
				if ( empty( $step['is_local'] ) && ! empty( $step['site'] ) ) {
					$host = wp_parse_url( $step['site'], PHP_URL_HOST );
					if ( is_string( $host ) && '' !== $host ) {
						$path = $host . $path;
					}
				}

				$parts[] = ( '' !== $path ) ? $title . ' (' . $path . ')' : $title;
			}

			if ( empty( $parts ) ) {
				return '';
			}

			$parts[] = __( 'Conversion completed', 'funnel-builder-pro' );

			return implode( $sep, $parts );
		}

		/**
		 * Build the MySQL REGEXP pattern that matches any of the given page ids.
		 *
		 * The pattern tolerates both `"i":"9483"` (JS string) and `"i":9483` (PHP
		 * int); the trailing `[,}]` stops `9483` matching `94831`.
		 *
		 * @since 3.16.1
		 *
		 * @param int[] $ids Page ids.
		 *
		 * @return string REGEXP pattern, or '' when no valid id is supplied.
		 */
		public static function page_id_pattern( $ids ) {
			$ids = array_filter( array_map( 'absint', (array) $ids ) );
			if ( empty( $ids ) ) {
				return '';
			}

			return '"i":"?(' . implode( '|', $ids ) . ')"?[,}]';
		}

		/**
		 * Whether journey collection is currently enabled.
		 *
		 * @since 3.16.1
		 *
		 * @return bool
		 */
		public static function is_enabled() {
			return class_exists( 'WFFN_Visitor_Tracking' ) && WFFN_Visitor_Tracking::is_journey_enabled();
		}

		/**
		 * Post types the journey typeahead searches: public viewable types plus
		 * the funnel step types.
		 *
		 * @since 3.16.1
		 *
		 * @return string[]
		 */
		public static function page_post_types() {
			$funnel_types = array( 'wffn_landing', 'wfacp_checkout', 'wfocu_offer', 'wffn_ty', 'wffn_optin', 'wffn_optin_ty', 'wffn_wc_thankyou' );
			$public_types = function_exists( 'get_post_types' ) ? array_values( get_post_types( array( 'public' => true ), 'names' ) ) : array( 'page', 'post', 'product' );
			$types        = array_values( array_unique( array_merge( $public_types, $funnel_types ) ) );

			/**
			 * Filters the post types the "Pages Visited" typeahead searches.
			 *
			 * @since 3.16.1
			 *
			 * @param string[] $types Post type slugs.
			 */
			return apply_filters( 'wffn_journey_page_post_types', $types );
		}

		/**
		 * Default typeahead options for the "Pages Visited" filter.
		 *
		 * Lists the funnel's own steps first when the list is funnel-scoped, then
		 * fills up to five with the most recently published journey pages site-wide.
		 *
		 * @since 3.16.1
		 *
		 * @param int $funnel_id Funnel the Conversions list is scoped to, or 0.
		 *
		 * @return array List of { id, label, name, value } option objects.
		 */
		public static function default_page_options( $funnel_id = 0 ) {
			$funnel_id = absint( $funnel_id );
			$options   = array();
			$seen      = array();

			if ( $funnel_id > 0 && class_exists( 'WFFN_Funnel' ) ) {
				$funnel = new WFFN_Funnel( $funnel_id );
				if ( $funnel instanceof WFFN_Funnel && $funnel->get_id() > 0 ) {
					foreach ( (array) $funnel->get_steps() as $step ) {
						$post_id = ( is_array( $step ) && isset( $step['id'] ) ) ? absint( $step['id'] ) : 0;
						if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
							continue;
						}
						$option = self::page_option( $post_id );
						if ( ! empty( $option ) ) {
							$seen[ $post_id ] = true;
							$options[]        = $option;
						}
					}
				}
			}

			if ( count( $options ) < 5 ) {
				$recent = get_posts(
					array(
						'post_type'        => self::page_post_types(),
						'post_status'      => 'publish',
						'numberposts'      => 5,
						'orderby'          => 'date',
						'order'            => 'DESC',
						'suppress_filters' => false,
					)
				);
				foreach ( $recent as $post ) {
					if ( isset( $seen[ $post->ID ] ) ) {
						continue;
					}
					$option = self::page_option( $post->ID );
					if ( ! empty( $option ) ) {
						$seen[ $post->ID ] = true;
						$options[]         = $option;
					}
					if ( count( $options ) >= 5 ) {
						break;
					}
				}
			}

			return array_values( $options );
		}

		/**
		 * Build a single typeahead option for a post.
		 *
		 * @since 3.16.1
		 *
		 * @param int $post_id Post id.
		 *
		 * @return array { id, label, name, value } — empty when the post is missing.
		 */
		public static function page_option( $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 || ! ( get_post( $post_id ) instanceof WP_Post ) ) {
				return array();
			}

			$title = get_the_title( $post_id );
			$title = ( '' !== $title ) ? $title : '#' . $post_id;
			$path  = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );

			return array(
				'id'    => $post_id,
				'label' => $title,
				'name'  => $title,
				'value' => is_string( $path ) ? $path : '',
			);
		}
	}
}
