<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'wfob_Rule_Day' ) ) {
	class wfob_Rule_Day extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'day' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'==' => __( 'is', 'woofunnels-order-bump' ),
				'!=' => __( 'is not', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$options = array(
				'0' => __( 'Sunday', 'woofunnels-order-bump' ),
				'1' => __( 'Monday', 'woofunnels-order-bump' ),
				'2' => __( 'Tuesday', 'woofunnels-order-bump' ),
				'3' => __( 'Wednesday', 'woofunnels-order-bump' ),
				'4' => __( 'Thursday', 'woofunnels-order-bump' ),
				'5' => __( 'Friday', 'woofunnels-order-bump' ),
				'6' => __( 'Saturday', 'woofunnels-order-bump' ),

			);

			return $options;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			global $post;
			$result    = false;
			$timestamp = current_time( 'timestamp' );

			$dateTime = new DateTime();
			$dateTime->setTimestamp( $timestamp );

			$day_today = $dateTime->format( 'w' );

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {

				if ( $rule_data['operator'] == '==' ) {
					$result = in_array( $day_today, $rule_data['condition'] ) ? true : false;
				}

				if ( $rule_data['operator'] == '!=' ) {
					$result = in_array( $day_today, $rule_data['condition'] ) ? false : true;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Date' ) ) {
	class wfob_Rule_Date extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'date' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Date';
		}

		public function is_match( $rule_data ) {
			global $post;

			$result = false;

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {

				$dateTime = new DateTime();
				$dateTime->setTimestamp( current_time( 'timestamp' ) );

				switch ( $rule_data['operator'] ) {
					case '==':
						$result = ( $rule_data['condition'] ) == $dateTime->format( 'Y-m-d' );

						break;
					case '!=':
						$result = ( $rule_data['condition'] ) != $dateTime->format( 'Y-m-d' );

						break;

					case '>':
						$result = $dateTime->getTimestamp() > strtotime( $rule_data['condition'] );

						break;

					case '<':
						$result = $dateTime->getTimestamp() < strtotime( $rule_data['condition'] );

						break;

					case '<=':
						$result = $dateTime->getTimestamp() <= strtotime( $rule_data['condition'] );
						break;
					case '>=':
						$result = $dateTime->getTimestamp() >= strtotime( $rule_data['condition'] );

						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Time' ) ) {

	class wfob_Rule_Time extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'time' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( 'is equal to', 'woofunnels-order-bump' ),
				'!=' => __( 'is not equal to', 'woofunnels-order-bump' ),
				'>'  => __( 'is greater than', 'woofunnels-order-bump' ),
				'<'  => __( 'is less than', 'woofunnels-order-bump' ),
				'>=' => __( 'is greater or equal to', 'woofunnels-order-bump' ),
				'<=' => __( 'is less or equal to', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Time';
		}

		public function is_match( $rule_data ) {

			$result = false;

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) && $rule_data['condition'] ) {

				$parsetime = explode( ":", $rule_data['condition'] );
				if ( is_array( $parsetime ) && count( $parsetime ) !== 2 ) {
					return $this->return_is_match( $result, $rule_data );
				}

				$dateTime = new DateTime();
				$dateTime->setTimestamp( current_time( 'timestamp' ) );
				$timestamp_current = $dateTime->getTimestamp();

				$dateTime->setTime( trim( $parsetime[0] ), trim( $parsetime[1] ) );
				$timestamp = $dateTime->getTimestamp();

				switch ( $rule_data['operator'] ) {
					case '==':
						$result = $timestamp_current == $timestamp;

						break;
					case '!=':
						$result = $timestamp_current != $timestamp;

						break;

					case '>':
						$result = $timestamp_current > $timestamp;

						break;

					case '<':
						$result = $timestamp_current < $timestamp;

						break;

					case '<=':
						$result = $timestamp_current <= $timestamp;

						break;
					case '>=':
						$result = $timestamp_current >= $timestamp;

						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );

		}

	}
}