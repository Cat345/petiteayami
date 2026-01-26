<?php

class BWFAN_API_Importer_Listing extends BWFAN_API_Base {
	public static $ins;
	private $total_count = 0;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method             = WP_REST_Server::READABLE;
		$this->route              = '/contacts/importer/listing';
		$this->pagination->offset = 0;
		$this->pagination->limit  = 20;
	}

	public function process_api_call() {
		$offset = $this->pagination->offset;
		$limit  = $this->pagination->limit;

		$imports = BWFAN_Model_Export_Import::get_lists( $offset, $limit, 1, [ 0 ] );

		if ( empty( $imports ) ) {
			return $this->success_response( [], __( 'No Imported Found.', 'wp-marketing-automations' ) );
		}

		$importer_data = array();
		$get_fields    = BWFCRM_Fields::get_groups_with_fields( true, true, null, true );
		foreach ( $imports as $import ) {
			$meta            = ! empty( $import['meta'] ) && BWFAN_Common::is_json( $import['meta'] ) ? json_decode( $import['meta'], true ) : [];
			$importer_data[] = array(
				'id'              => $import['id'] ?? '',
				'created_on'      => $import['created_date'] ?? '',
				'type'            => $this->get_importer_type_label( $meta['import_type'] ?? '' ),
				'contact_count'   => $import['count'] ?? 0,
				'status'          => $import['status'] ?? 0,
				'title'           => $meta['title'] ?? '-',
				'fields_imported' => $this->get_fields( $meta, $get_fields ),
				'tags'            => $this->get_tags( $meta ),
				'lists'           => $this->get_lists( $meta ),
			);
		}

		$this->total_count = BWFAN_Model_Export_Import::total_count();

		return $this->success_response( $importer_data, __( 'All List Imported successfully', 'wp-marketing-automations' ) );
	}

	private function get_fields( $meta, $get_fields ) {
		if ( isset( $meta['mapped_fields'] ) && is_array( $meta['mapped_fields'] ) ) {
			$field_names = array_map( function ( $field_id ) use ( $get_fields ) {
				foreach ( $get_fields as $group ) {
					foreach ( $group['fields'] as $field ) {
						if ( $field['id'] == $field_id ) {
							return $field['name'];
						}
					}
				}

				return '';
			}, array_values( $meta['mapped_fields'] ) );

			return implode( ' | ', array_filter( $field_names ) );
		}

		return '';
	}

	private function get_tags( $meta ) {
		$all_tags = [];

		// Get standard tags
		if ( isset( $meta['tags'] ) && is_array( $meta['tags'] ) ) {
			$standard_tags = array_column( $meta['tags'], 'value' );
			$all_tags      = array_merge( $all_tags, $standard_tags );
		}

		// Get importer specific tags
		if ( isset( $meta['import_type'] ) && ! empty( $meta['import_type'] ) ) {
			$importer_prefix = $meta['import_type'] . '_tags';

			if ( isset( $meta[ $importer_prefix ] ) && is_array( $meta[ $importer_prefix ] ) ) {
				$importer_tags = array_map( function ( $tag ) {
					return $tag['title'] ?? '';
				}, array_values( $meta[ $importer_prefix ] ) );

				$all_tags = array_merge( $all_tags, array_filter( $importer_tags ) );
			}
		}

		return ! empty( $all_tags ) ? implode( ', ', array_unique( $all_tags ) ) : '';
	}

	private function get_lists( $meta ) {
		$all_lists = [];

		// Get standard lists
		if ( isset( $meta['lists'] ) && is_array( $meta['lists'] ) ) {
			$standard_lists = array_column( $meta['lists'], 'value' );
			$all_lists      = array_merge( $all_lists, $standard_lists );
		}

		// Get importer specific lists
		if ( isset( $meta['import_type'] ) && ! empty( $meta['import_type'] ) ) {
			$importer_prefix = $meta['import_type'] . '_lists';

			if ( isset( $meta[ $importer_prefix ] ) && is_array( $meta[ $importer_prefix ] ) ) {
				$importer_lists = array_map( function ( $list ) {
					return $list['title'] ?? '';
				}, array_values( $meta[ $importer_prefix ] ) );

				$all_lists = array_merge( $all_lists, array_filter( $importer_lists ) );
			}
		}

		return ! empty( $all_lists ) ? implode( ', ', array_unique( $all_lists ) ) : '';
	}

	/**
	 * Return Label for Importers
	 *
	 * @param $type
	 *
	 * @return mixed
	 */
	public static function get_importer_type_label( $type ) {
		$types = array(
			'ac'        => __( 'ActiveCampaign', 'wp-marketing-automations' ),
			'affwp'     => __( 'Affiliate WP', 'wp-marketing-automations' ),
			'csv'       => __( 'CSV', 'wp-marketing-automations' ),
			'mailchimp' => __( 'Mailchimp', 'wp-marketing-automations' ),
			'wc'        => __( 'WooCommerce', 'wp-marketing-automations' ),
			'wp'        => __( 'WordPress', 'wp-marketing-automations' ),
			'wlm'       => __( 'Wishlist Member', 'wp-marketing-automations' ),
			'wcs'       => __( 'WooCommerce Subscriptions', 'wp-marketing-automations' ),
		);

		return $types[ $type ] ?? $type;
	}


	public function get_result_total_count() {
		return $this->total_count;
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Importer_Listing' );