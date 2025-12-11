<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Contact' ) ) {
	class WFFN_Export_Contact extends WFFN_Abstract_Exporter {
		protected static $slug = 'contacts';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_contact_export';

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		public function __construct() {
			parent::__construct();
		}

		public function get_columns() {
			return array(
				'email'  => 'Email',
				'f_name' => 'First Name',
				'l_name' => 'Last Name',
				'phone'  => 'Phone',
				'date'   => 'Date',
				'status' => 'Status',
			);
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public static function get_slug() {
			return self::$slug;
		}

		public function total_rows( $args ) {
			$args['total_count'] = true;
			$get_contacts        = WFFN_Core()->wffn_contacts->get_funnel_export_contacts( $args, true );

			if ( isset( $get_contacts['status'] ) && false === $get_contacts['status'] ) {
				return false;
			}
			if ( isset( $get_contacts['db_error'] ) && true === $get_contacts['db_error'] ) {
				return false;
			}
			if ( isset( $get_contacts['total'] ) ) {
				$count = $get_contacts['total'];
			}

			return absint( $count );

		}


		/**
		 * Export data to CSV function
		 */
		public function export_data() {
			$funnel_id = isset( $this->db_export_row['fid'] ) ? absint( $this->db_export_row['fid'] ) : 0;
			$args      = array(
				'funnel_id' => $funnel_id,
				'offset'    => $this->current_pos,
				'limit'     => get_option( 'posts_per_page' ),
				'filters'   => $this->export_meta['filters'],
			);

			$get_contacts = WFFN_Core()->wffn_contacts->get_funnel_export_contacts( $args );
			if ( isset( $get_contacts['db_error'] ) && true === $get_contacts['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_contacts, true ), 'wffn', true );
			} else if ( isset( $get_contacts['records'] ) && is_array( $get_contacts['records'] ) && count( $get_contacts['records'] ) > 0 ) {

				$this->data_populated_in_csv( $funnel_id, $get_contacts['records'] );
			}

		}

		/* prepared and import data in csv
		*
		* @param $funnel_id
		* @param $data
		*
		* @return void
		*/
		public function data_populated_in_csv( $funnel_id, $data ) {
			$file  = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			$count = 0;
			foreach ( $data as $subdata ) {
				$subdata = array_map( "strval", $subdata );
				fputcsv( $file, $subdata );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Contact::get_instance() );
	}
}