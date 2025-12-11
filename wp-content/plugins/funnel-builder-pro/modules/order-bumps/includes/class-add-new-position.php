<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFOB_Add_New_Position' ) ) {
	final class WFOB_Add_New_Position {

		private $data = [
			'position_id'   => '',
			'hook'          => '',
			'position_name' => '',
			'hook_priority' => ''
		];
		private $position_id = '';
		private $hook = '';
		private $position_name = '';
		private $hook_priority = 21;

		public function __construct( $data ) {
			if ( ! empty( $data ) ) {
				$this->data = array_merge( $this->data, $data );
				if ( false === $this->validate() ) {
					return;
				}
				add_filter( 'wfob_bump_positions', [ $this, 'add_new_place' ] );
			}

		}


		private function validate() {
			if ( empty( $this->data['position_id'] ) ) {
				return false;
			}
			if ( empty( $this->data['hook'] ) ) {
				return false;
			}
			if ( empty( $this->data['position_name'] ) ) {
				return false;
			}
			if ( ! is_numeric( $this->data['hook_priority'] ) ) {
				return false;
			}
			foreach ( $this->data as $key => $val ) {
				$this->{$key} = trim( $val );
			}
		}

		public function add_new_place( $positions ) {

			$positions[ $this->position_id ] = [
				'name'     => $this->position_name,
				'hook'     => $this->hook,
				'priority' => $this->hook_priority,
				'id'       => $this->position_id
			];

			return $positions;
		}

	}

}