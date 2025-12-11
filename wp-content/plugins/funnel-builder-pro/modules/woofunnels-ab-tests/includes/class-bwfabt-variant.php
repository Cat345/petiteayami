<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Variant' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_Variant {

		/**
		 * @var $ins
		 */
		private static $ins = null;

		/**
		 * @var $id
		 */
		public $id = '';

		/**
		 * @var $experiment
		 */
		public $experiment = '';

		/**
		 * @var $variants
		 */
		public $variants = '';


		/**
		 * @var $traffic
		 */
		public $traffic = '';

		/**
		 * @var $control
		 */
		public $control = false;

		/**
		 * @var $winner
		 */
		public $winner = false;

		/**
		 * BWFABT_Variant constructor.
		 *
		 * @param $id
		 * @param BWFABT_Experiment $experiment
		 */
		public function __construct( $id, $experiment ) {
			$this->id         = $id;
			$this->experiment = $experiment;

			$this->variants = $this->experiment->get_variants();
			if ( array_key_exists( $this->id, $this->variants ) ) {
				$variant       = $this->variants[ $this->id ];
				$this->traffic = isset( $variant['traffic'] ) ? $variant['traffic'] : "0.00";
				$this->control = isset( $variant['control'] ) ? $variant['control'] : false;
				$this->winner  = isset( $variant['winner'] ) ? $variant['winner'] : false;
			}
		}

		/**
		 * @return BWFABT_Variant|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function get_id() {
			return $this->id;
		}


		public function get_traffic() {
			return $this->traffic;
		}

		public function get_control() {
			return $this->control;
		}

		public function get_winner() {
			return $this->winner;
		}

		public function set_id( $id ) {
			$this->id = ( '' === $id || $id < 0 ) ? $this->id : $id;
		}


		public function set_traffic( $traffic ) {
			$this->traffic = ( '' === $traffic || $traffic < 0 ) ? $this->traffic : $traffic;
		}

		public function set_control( $control ) {
			$this->control = empty( $control ) ? $this->control : $control;
		}

		public function set_winner( $winner ) {
			BWFABT_Core()->admin->log( "Set winner: $winner " );
			$this->winner = empty( $winner ) ? $this->winner : $winner;
		}

		/**
		 * Create/update experiment using given or set experiment data
		 *
		 * @param $data
		 *
		 * @return mixed
		 */
		public function save( $data = array() ) {
			if ( count( $data ) > 0 ) {
				foreach ( $data as $key => $value ) {
					$this->$key = $value;
				}
			}
			$variant_data            = array();
			$variant_data['traffic'] = $this->get_traffic();
			$variant_data['control'] = $this->get_control();
			$variant_data['winner']  = $this->get_winner();

			$this->variants[ $this->get_id() ] = $variant_data;
			$this->experiment->set_variants( $this->variants );

			$this->experiment->save( array() );

			return $this->get_id();
		}
	}
}