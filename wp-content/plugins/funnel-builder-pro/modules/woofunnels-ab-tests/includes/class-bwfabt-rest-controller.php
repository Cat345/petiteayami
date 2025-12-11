<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class BWFABT_REST_Controller
 *
 * * @extends BWFABT_REST_Controller
 */
if ( ! class_exists( 'BWFABT_REST_Controller' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_REST_Controller extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * @var string
		 */
		public static $sql_datetime_format = 'Y-m-d H:i:s';

		/**
		 * Endpoint namespace.
		 *
		 * @var string
		 */
		protected $namespace = 'woofunnels-analytics';

		/**
		 * Route base.
		 *
		 * @var string
		 */
		protected $rest_base = '';


		public function date_format( $interval ) {
			switch ( $interval ) {
				case 'hour':
					$format = '%Y-%m-%d %H';
					break;
				case 'day':
					$format = '%Y-%m-%d';
					break;
				case 'month':
					$format = '%Y-%m';
					break;
				case 'quarter':
					$format = 'QUARTER';
					break;
				case 'year':
					$format = 'YEAR';
					break;
				default:
					$format = '%x-%v';
					break;
			}

			return apply_filters( 'BWFABT_api_date_format_' . $interval, $format, $interval );
		}

		public function get_interval_format_query( $interval, $table_col ) {

			$interval_type = $this->date_format( $interval );
			$avg           = ( $interval === 'day' ) ? 1 : 0;
			if ( 'YEAR' === $interval_type ) {
				$interval = ", YEAR(" . $table_col . ") ";
				$avg      = 365;
			} elseif ( 'QUARTER' === $interval_type ) {
				$interval = ", CONCAT(YEAR(" . $table_col . "), '-', QUARTER(" . $table_col . ")) ";
				$avg      = 90;
			} elseif ( '%x-%v' === $interval_type ) {
				$first_day_of_week = absint( get_option( 'start_of_week' ) );

				if ( 1 === $first_day_of_week ) {
					$interval = ", DATE_FORMAT(" . $table_col . ", '" . $interval_type . "')";
				} else {
					$interval = ", CONCAT(YEAR(" . $table_col . "), '-', LPAD( FLOOR( ( DAYOFYEAR(" . $table_col . ") + ( ( DATE_FORMAT(MAKEDATE(YEAR(" . $table_col . "),1), '%w') - $first_day_of_week + 7 ) % 7 ) - 1 ) / 7  ) + 1 , 2, '0'))";
				}
				$avg = 7;
			} else {
				$interval = ", DATE_FORMAT( " . $table_col . ", '" . $interval_type . "')";
			}

			$interval       .= " as time_interval ";
			$interval_group = " `time_interval` ";

			return array(
				'interval_query' => $interval,
				'interval_group' => $interval_group,
				'interval_avg'   => $avg,

			);

		}


		public function get_total_intervals( $start_date, $end_date, $interval, $table, $table_col ) {
			global $wpdb;

			$get_interval   = $this->get_interval_format_query( $interval, $table_col );
			$interval_query = $get_interval['interval_query'];
			$interval_group = $get_interval['interval_group'];

			$query = "SELECT MIN(" . $table_col . ") AS start_date, MAX(" . $table_col . ") as end_date, " . ltrim( $interval_query, ',' ) . "  FROM `" . $table . "` WHERE 1=1 AND $table_col >= '" . esc_sql( $start_date ) . "' AND `" . $table_col . "` < '" . esc_sql( $end_date ) . "' GROUP BY " . $interval_group . " ASC";

			$intervals = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			return ( is_array( $intervals ) && count( $intervals ) > 0 ) ? $intervals : array();
		}

		public function maybe_interval_exists( $all_data, $interval_key, $current_interval ) {
			if ( is_array( $all_data ) && count( $all_data ) > 0 ) {
				foreach ( $all_data as $data ) {
					if ( isset( $data[ $interval_key ] ) && $current_interval === $data[ $interval_key ] ) {
						return array( $data );
					}

				}
			}

			return false;
		}

		public static function string_to_date( $datetime_string ) {
			$datetime = new DateTime( $datetime_string, new \DateTimeZone( wp_timezone_string() ) );

			return $datetime;
		}

		public static function convert_local_datetime_to_gmt( $datetime_string ) {
			$datetime = new DateTime( $datetime_string, new \DateTimeZone( wp_timezone_string() ) );
			$datetime->setTimezone( new DateTimeZone( 'GMT' ) );

			return $datetime;
		}

		public static function default_date( $diff_time = 0 ) {
			$now      = time();
			$datetime = new DateTime();
			if ( $diff_time > 0 ) {
				$week_back = $now - $diff_time;
				$datetime->setTimestamp( $week_back );
			}
			$datetime->setTimezone( new DateTimeZone( wp_timezone_string() ) );

			return $datetime;
		}

		public function missing_intervals( $sql_intervals, $all_intervals ) {

			if ( count( $sql_intervals ) === count( $all_intervals ) ) {
				return $sql_intervals;
			}

			if ( count( $sql_intervals ) === 0 ) {
				return $all_intervals;
			}

			$array = array_merge( $sql_intervals, $all_intervals );

			$temp_array = [];
			$key        = 'time_interval';

			foreach ( $array as &$v ) {
				if ( ! isset( $temp_array[ $v[ $key ] ] ) ) {
					$temp_array[ $v[ $key ] ] =& $v;
				}
			}

			usort( $temp_array, function ( $a, $b ) {
				$datetime1 = strtotime( $a['time_interval'] );
				$datetime2 = strtotime( $b['time_interval'] );

				return $datetime1 - $datetime2;
			} );


			$array = array_values( $temp_array );

			return $array;


		}

		public function intervals_between( $start, $end, $interval ) {

			switch ( $interval ) {
				case 'hour':
					$interval_type = 'PT60M';
					$format        = 'Y-m-d H';
					break;
				case 'day':
					$interval_type = "P1D";
					$format        = 'Y-m-d';
					break;
				case 'month':
					$interval_type = "P1M";
					$format        = 'Y-m';
					break;
				case 'quarter':
					$interval_type = "P3M";
					$format        = 'Y-m';
					break;
				case 'year':
					$interval_type = "P1Y";
					$format        = 'Y';
					break;
				default:
					$interval_type = "P1W";
					$format        = 'W';
					break;
			}


			$result = array();

			// Variable that store the date interval
			// of period 1 day
			$period = new DateInterval( $interval_type );

			$realEnd = new DateTime( $end );

			$realEnd->add( $period );

			$period   = new DatePeriod( new DateTime( $start ), $period, $realEnd );
			$date_end = date_create( $end );
			$count    = iterator_count( $period );

			if ( 'week' !== $interval ) {
				$count = $count - 1;
			}

			foreach ( $period as $date ) {
				if ( $count >= 1 ) {
					$new_interval = array();

					if ( 'day' === $interval || 'hour' === $interval ) {
						$new_interval['start_date'] = $date->format( self::$sql_datetime_format );
						$new_interval['end_date']   = $date->format( 'Y-m-d 23:59:59' );
					} else {
						$new_interval['start_date'] = self::maybe_first_date( $date, $format );
						$new_interval['end_date']   = ( $count > 1 ) ? self::maybe_last_date( $date, $format ) : $date_end->format( self::$sql_datetime_format );
					}
					if ( 'week' === $interval ) {
						$year                          = $date->format( 'Y' );
						$new_interval['time_interval'] = $year . '-' . $date->format( $format );
					} else {
						$new_interval['time_interval'] = $date->format( $format );
					}

					$result[] = $new_interval;
				}
				$count --;

			}

			return $result;
		}

		public static function maybe_first_date( $newDate, $period ) {
			switch ( $period ) {
				case 'Y':
					$newDate->modify( 'first day of january ' . $newDate->format( 'Y' ) );
					break;
				case 'quarter':
					$month = $newDate->format( 'n' );
					if ( $month < 4 ) {
						$newDate->modify( 'first day of january ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 3 && $month < 7 ) {
						$newDate->modify( 'first day of april ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 6 && $month < 10 ) {
						$newDate->modify( 'first day of july ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 9 ) {
						$newDate->modify( 'first day of october ' . $newDate->format( 'Y' ) );
					}
					break;
				case 'Y-m':
					$newDate->modify( 'first day of this month' );
					break;
				case 'W':
					$newDate->modify( ( $newDate->format( 'w' ) === '0' ) ? self::first_day_of_week() . ' last week' : self::first_day_of_week() . ' this week' );
					break;
			}

			return $newDate->format( self::$sql_datetime_format );

		}

		public static function maybe_last_date( $newDate, $period ) {
			switch ( $period ) {
				case 'Y':
					$newDate->modify( 'last day of december ' . $newDate->format( 'Y' ) );
					break;
				case 'quarter':
					$month = $newDate->format( 'n' );

					if ( $month < 4 ) {
						$newDate->modify( 'last day of march ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 3 && $month < 7 ) {
						$newDate->modify( 'last day of june ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 6 && $month < 10 ) {
						$newDate->modify( 'last day of september ' . $newDate->format( 'Y' ) );
					} elseif ( $month > 9 ) {
						$newDate->modify( 'last day of december ' . $newDate->format( 'Y' ) );
					}
					break;
				case 'Y-m':
					$newDate->modify( 'last day of this month' );
					break;
				case 'W':
					$newDate->modify( ( $newDate->format( 'w' ) === '0' ) ? 'now' : self::last_day_of_week() . ' this week' );
					break;
			}

			return $newDate->format( 'Y-m-d 23:59:59 ' );

		}

		public static function first_day_of_week() {
			$days_of_week = array(
				1 => 'monday',
				2 => 'tuesday',
				3 => 'wednesday',
				4 => 'thursday',
				5 => 'friday',
				6 => 'saturday',
				7 => 'sunday',
			);

			$day_number = absint( get_option( 'start_of_week' ) );

			return $days_of_week[ $day_number ];
		}

		public static function last_day_of_week() {
			$days_of_week = array(
				1 => 'sunday',
				2 => 'saturday',
				3 => 'friday',
				4 => 'thursday',
				5 => 'wednesday',
				6 => 'tuesday',
				7 => 'monday',
			);

			$day_number = absint( get_option( 'start_of_week' ) );

			return $days_of_week[ $day_number ];
		}

	}
}