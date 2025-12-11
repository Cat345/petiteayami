<?php
if ( ! class_exists( 'WFOCU_Rule_Day' ) ) {
	class WFOCU_Rule_Day extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'day' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'==' => __( "is", 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( "is not", 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$options = array(
				'0' => __( 'Sunday', 'woofunnels-upstroke-one-click-upsell' ),
				'1' => __( 'Monday', 'woofunnels-upstroke-one-click-upsell' ),
				'2' => __( 'Tuesday', 'woofunnels-upstroke-one-click-upsell' ),
				'3' => __( 'Wednesday', 'woofunnels-upstroke-one-click-upsell' ),
				'4' => __( 'Thursday', 'woofunnels-upstroke-one-click-upsell' ),
				'5' => __( 'Friday', 'woofunnels-upstroke-one-click-upsell' ),
				'6' => __( 'Saturday', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $options;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$result    = false;
			$timestamp = current_time( 'timestamp' );

			$dateTime = new DateTime();
			$dateTime->setTimestamp( $timestamp );

			$day_today = $dateTime->format( 'w' );

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {

				if ( $rule_data['operator'] === '==' ) {
					$result = in_array( $day_today, $rule_data['condition'], true ) ? true : false;
				}

				if ( $rule_data['operator'] === '!=' ) {
					$result = in_array( $day_today, $rule_data['condition'], true ) ? false : true;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Current Day %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_day_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Date' ) ) {
	class WFOCU_Rule_Date extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'date' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( "is equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( "is not equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'>'  => __( "is greater than", 'woofunnels-upstroke-one-click-upsell' ),
				'<'  => __( "is less than", 'woofunnels-upstroke-one-click-upsell' ),
				'>=' => __( "is greater or equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'=<' => __( "is less or equal to", 'woofunnels-upstroke-one-click-upsell' )
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Date';
		}

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$result = false;


			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {


				$dateTime = new DateTime();
				$dateTime->setTimestamp( current_time( 'timestamp' ) );


				switch ( $rule_data['operator'] ) {
					case '==' :

						$result = ( $rule_data['condition'] ) === $dateTime->format( 'Y-m-d' );

						break;
					case '!=' :

						$result = ( $rule_data['condition'] ) !== $dateTime->format( 'Y-m-d' );

						break;

					case '>' :

						$result = $dateTime->getTimestamp() > strtotime( $rule_data['condition'] );

						break;

					case '<' :

						$result = $dateTime->getTimestamp() < strtotime( $rule_data['condition'] );

						break;

					case '=<' :

						$result = $dateTime->getTimestamp() <= strtotime( $rule_data['condition'] );
						break;
					case '>=' :

						$result = $dateTime->getTimestamp() >= strtotime( $rule_data['condition'] );

						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Current Date %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), date_i18n( get_option( 'date_format' ), strtotime( $rule['condition'] ) ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Time' ) ) {
	class WFOCU_Rule_Time extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'time' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'==' => __( "is equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'!=' => __( "is not equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'>'  => __( "is greater than", 'woofunnels-upstroke-one-click-upsell' ),
				'<'  => __( "is less than", 'woofunnels-upstroke-one-click-upsell' ),
				'>=' => __( "is greater or equal to", 'woofunnels-upstroke-one-click-upsell' ),
				'=<' => __( "is less or equal to", 'woofunnels-upstroke-one-click-upsell' )
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Time';
		}

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

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
					case '==' :

						$result = $timestamp_current === $timestamp;

						break;
					case '!=' :

						$result = $timestamp_current !== $timestamp;

						break;

					case '>' :

						$result = $timestamp_current > $timestamp;

						break;

					case '<' :

						$result = $timestamp_current < $timestamp;

						break;

					case '=<' :

						$result = $timestamp_current <= $timestamp;

						break;
					case '>=' :

						$result = $timestamp_current >= $timestamp;

						break;

					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );

		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Current Time %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), date_i18n( get_option( 'time_format' ), strtotime( $rule['condition'] ) ) );
		}

	}
}