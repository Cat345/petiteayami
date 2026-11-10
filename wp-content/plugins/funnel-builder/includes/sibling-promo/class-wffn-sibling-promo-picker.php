<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Sibling_Promo_Picker' ) ) {
	/**
	 * Chooses which sibling products the admin submenu promotes.
	 *
	 * Every method is pure: it reads only its arguments, calls no WordPress
	 * function and returns the new state rather than saving it, so the rules
	 * can be tested with a fixed clock.
	 *
	 * State shape: array( 'seeded' => 1, 'products' => array( key => array(
	 *     'first_shown_at' => int, 'activated_at' => int, 'set_up_at' => int,
	 *     'snoozed_until' => int, 'rejoined_at' => int, 'dismissed' => 1 for good ) ) )
	 *
	 * Config shape: array(
	 *     'products'     => array( key => array( 'eligible' => bool, 'detector' => bool, 'present' => bool ) ), in priority order,
	 *     'snooze_after' => seconds shown with no install before snoozing,
	 *     'snooze_for'   => seconds a snooze or a dismiss lasts )
	 */
	class WFFN_Sibling_Promo_Picker {

		const DAY = 86400;

		/**
		 * @return array
		 */
		public static function default_config() {
			return array(
				'products'     => array(),
				'snooze_after' => 14 * self::DAY,
				'snooze_for'   => 60 * self::DAY,
			);
		}

		/**
		 * @param array $state Stored state.
		 * @param int   $now Unix time.
		 * @param array $config See the class docblock.
		 *
		 * @return array { show: string[] at most one product key, state: array, changed: bool }
		 */
		public static function pick( $state, $now, $config ) {
			$config  = array_merge( self::default_config(), $config );
			$state   = self::normalize_state( $state );
			$now     = (int) $now;
			$changed = false;

			$groups   = array( array(), array(), array() );
			$position = 0;

			foreach ( $config['products'] as $key => $product ) {
				++$position;
				$item      = isset( $state['products'][ $key ] ) ? $state['products'][ $key ] : array();
				$installed = ! empty( $product['present'] ) && ! empty( $item['activated_at'] );
				$set_up    = ! empty( $product['detector'] ) && ! empty( $item['set_up_at'] );

				if ( $set_up || ! empty( $item['dismissed'] ) || empty( $product['eligible'] ) ) {
					continue;
				}

				if ( ! empty( $item['snoozed_until'] ) ) {
					if ( $now < (int) $item['snoozed_until'] ) {
						continue;
					}
					$item['rejoined_at'] = (int) $item['snoozed_until'];
					unset( $item['snoozed_until'], $item['first_shown_at'] );
					$changed = true;
				}

				if ( ! $installed && ! empty( $item['first_shown_at'] ) && $now - (int) $item['first_shown_at'] >= (int) $config['snooze_after'] ) {
					$item['snoozed_until'] = $now + (int) $config['snooze_for'];
					unset( $item['first_shown_at'] );
					$state['products'][ $key ] = $item;
					$changed                   = true;
					continue;
				}

				if ( ! empty( $item ) ) {
					$state['products'][ $key ] = $item;
				}

				if ( $installed ) {
					$group = 0;
				} elseif ( ! empty( $item['first_shown_at'] ) ) {
					$group = 1;
				} else {
					$group = 2;
				}
				$groups[ $group ][] = array(
					'key'         => $key,
					'rejoined_at' => isset( $item['rejoined_at'] ) ? (int) $item['rejoined_at'] : 0,
					'position'    => $position,
				);
			}

			$show = array();
			foreach ( $groups as $group ) {
				if ( ! empty( $group ) ) {
					usort( $group, array( __CLASS__, 'compare_candidates' ) );
					$show[] = $group[0]['key'];
					break;
				}
			}

			foreach ( $show as $key ) {
				if ( empty( $state['products'][ $key ]['first_shown_at'] ) ) {
					$state['products'][ $key ]['first_shown_at'] = $now;
					$changed                                     = true;
				}
			}

			return array(
				'show'    => $show,
				'state'   => $state,
				'changed' => $changed,
			);
		}

		/**
		 * Dismissed means snoozed: the product steps aside for snooze_for and then rejoins at the back.
		 *
		 * @param array  $state Stored state.
		 * @param string $key Product key.
		 * @param int    $now Unix time.
		 * @param array  $config See the class docblock.
		 *
		 * @return array
		 */
		public static function dismiss( $state, $key, $now, $config ) {
			$config = array_merge( self::default_config(), $config );
			$state  = self::normalize_state( $state );
			$item   = isset( $state['products'][ $key ] ) ? $state['products'][ $key ] : array();

			$item['snoozed_until'] = (int) $now + (int) $config['snooze_for'];
			unset( $item['first_shown_at'] );
			$state['products'][ $key ] = $item;

			return $state;
		}

		/**
		 * Sets a timestamp field only if it is empty, so a second activation of the same product, Lite or Pro, keeps the first time.
		 *
		 * @param array  $state Stored state.
		 * @param string $key Product key.
		 * @param string $field activated_at or set_up_at.
		 * @param int    $now Unix time.
		 *
		 * @return array { state: array, changed: bool }
		 */
		public static function stamp( $state, $key, $field, $now ) {
			$state = self::normalize_state( $state );
			if ( ! empty( $state['products'][ $key ][ $field ] ) ) {
				return array(
					'state'   => $state,
					'changed' => false,
				);
			}
			$state['products'][ $key ][ $field ] = (int) $now;

			return array(
				'state'   => $state,
				'changed' => true,
			);
		}

		/**
		 * @param mixed $state Stored state.
		 *
		 * @return array
		 */
		public static function normalize_state( $state ) {
			if ( ! is_array( $state ) ) {
				$state = array();
			}
			if ( ! isset( $state['products'] ) || ! is_array( $state['products'] ) ) {
				$state['products'] = array();
			}
			foreach ( $state['products'] as $key => $item ) {
				if ( ! is_array( $item ) ) {
					$state['products'][ $key ] = array();
				}
			}

			return $state;
		}

		/**
		 * Products back from a snooze go behind those never snoozed, then priority order.
		 *
		 * @param array $a Candidate.
		 * @param array $b Candidate.
		 *
		 * @return int
		 */
		public static function compare_candidates( $a, $b ) {
			if ( $a['rejoined_at'] !== $b['rejoined_at'] ) {
				return $a['rejoined_at'] < $b['rejoined_at'] ? -1 : 1;
			}

			return $a['position'] - $b['position'];
		}
	}
}
