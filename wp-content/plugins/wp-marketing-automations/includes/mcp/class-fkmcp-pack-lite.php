<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-fkmcp-pack-lite-abilities.php';

/**
 * Automations' tool handlers: contacts, automations, registries, carts. The
 * definitions they answer are in FKMCP_Pack_Lite_Abilities.
 * Field whitelists were verified against live controller responses - the
 * rationale per key lives in the original plugin's git history.
 */
class FKMCP_Pack_Lite extends FKMCP_Pack_Lite_Abilities {

	/** A step's settings past this many bytes of JSON are left out. */
	const STEP_SETTINGS_MAX = 4096;

	/** A settings key naming a credential, as a whole _/- separated word: api/private/access key, secret, token, pass or password (api_key, access_token, smtp_pass) - not any *_key (bwfan_email_map_key is a field path). */
	const SECRET_KEY_PATTERN = '/(^|[_-])(api[_-]?key|private[_-]?key|access[_-]?key|secret|token|password|passwd|pass)([_-]|$)/i';

	/** A header or key-value row whose name is a credential (Authorization, X-Api-Key, Cookie, a signature). */
	const SECRET_ROW_NAME_PATTERN = '/(auth|token|secret|cookie|passw|api[_-]?key|signature|bearer)/i';

	/** Webhook URLs that are themselves the credential: anyone holding one can post to the workflow behind it. */
	const CAPABILITY_URL_PATTERN = '#^https?://(hooks\.zapier\.com|hook\.[a-z0-9]+\.make\.com|hook\.integromat\.com|connect\.pabbly\.com|webhooks\.integrately\.com)/#i';

	/** get-automation past this many bytes of JSON drops per-step settings, then the definition. */
	const RESULT_MAX = 102400;

	const CONTACT_KEYS = array( 'id', 'email', 'f_name', 'l_name', 'status', 'contact_no', 'creation_date', 'last_modified' );

	/** What get-contact returns on top of the list row: location, source, terms, custom fields and the summary blocks. */
	const CONTACT_PROFILE_KEYS = array( 'state', 'country', 'country_formatted', 'timezone', 'source', 'type', 'unsubscribed', 'tags', 'lists', 'fields', 'wc', 'offer', 'abandoned', 'last_sent', 'last_open', 'last_click' );

	const AUTOMATION_LIST_KEYS = array( 'id', 'name', 'status', 'event', 'source', 'priority', 'conversions', 'revenue', 'last_update', 'v' );

	const TAG_KEYS = array( 'ID', 'name', 'type', 'created_at', 'updated_at' );

	const LIST_KEYS = array( 'ID', 'name', 'type', 'created_at', 'updated_at' );

	const CUSTOM_FIELD_KEYS = array( 'ID', 'name', 'type', 'slug', 'group_id' );

	const ABANDONED_CART_KEYS = array( 'id', 'email', 'phone', 'f_name', 'l_name', 'contact_id', 'status', 'date', 'created_on', 'diffstring', 'total', 'buyer_name', 'order_id', 'user_id' );

	const CONNECTOR_KEYS = array( 'name', 'description', 'is_connected' );

	const AUTOMATION_CONTACT_KEYS = array( 'ID', 'cid', 'aid', 'trail', 'email', 'f_name', 'l_name', 'contact_no', 'c_date', 'e_time' );

	const CART_STATUS_SLUGS = array(
		'recoverable' => 'list-abandoned-carts',
		'recovered'   => 'list-recovered-carts',
		'lost'        => 'list-lost-carts',
	);

	/** Longest visitor-typed string an ability result carries (same budget as the Pro pack). */
	const UNTRUSTED_MAX_CHARS = 500;

	/** Deepest visitor-sourced structure an ability result walks. */
	const UNTRUSTED_MAX_DEPTH = 4;

	/**
	 * Visitor-typed text (names, phone, checkout fields) is data, never
	 * markup or instructions - the Pro pack's guard, mirrored here.
	 */
	private static function untrust( $value, $depth = 0 ) {
		if ( is_string( $value ) ) {
			// Breaks removed as well as tags: newlines are how planted text
			// fakes a boundary ("\n\nSYSTEM:") in a rendered tool result.
			return mb_substr( wp_strip_all_tags( $value, true ), 0, self::UNTRUSTED_MAX_CHARS );
		}

		if ( is_array( $value ) ) {
			// A payload nested deeper than this is a structure, not a message:
			// drop it rather than walk an arbitrary tree from the database.
			if ( $depth >= self::UNTRUSTED_MAX_DEPTH ) {
				return array();
			}

			$clean = array();

			foreach ( $value as $key => $item ) {
				$clean[ $key ] = self::untrust( $item, $depth + 1 );
			}

			return $clean;
		}

		// Objects have no place in an ability response; scalars pass as they are.
		return is_scalar( $value ) || null === $value ? $value : null;
	}

	public function list_contacts( $input = array() ) {
		$page = BWFAN_MCP_Format::paginate( (array) $input );

		$params = array(
			'limit'       => $page['limit'],
			'offset'      => $page['offset'],

			'grab_totals' => true,
		);

		if ( ! empty( $input['search'] ) ) {
			$params['search'] = sanitize_text_field( $input['search'] );
		}

		$sort_columns    = array( 'last_modified', 'creation_date', 'email', 'f_name', 'l_name', 'id', 'country', 'state' );
		$wc_sort_columns = array( 'total_order_count', 'total_order_value', 'l_order_date' );

		if ( isset( $input['sort_by'] ) || isset( $input['order'] ) ) {
			// The controller applies ordering only when both params arrive.
			$sort_by = isset( $input['sort_by'] ) ? (string) $input['sort_by'] : 'last_modified';

			if ( in_array( $sort_by, $wc_sort_columns, true ) ) {
				if ( ! class_exists( 'WooCommerce' ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( 'sort_by=%1$s needs WooCommerce, which is not active on this site. Sort by one of: %2$s.', $sort_by, implode( ', ', $sort_columns ) ) );
				}

				// WC columns live on a joined table the query only includes
				// when customer data is requested.
				$params['get_wc'] = true;
			} elseif ( ! in_array( $sort_by, $sort_columns, true ) ) {
				$sort_by = 'last_modified';
			}

			$params['order_by'] = $sort_by;
			$params['order']    = ( isset( $input['order'] ) && 'asc' === strtolower( (string) $input['order'] ) ) ? 'ASC' : 'DESC';
		}

		$response = BWFAN_MCP_Dispatch::call( 'list-contacts', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$collection = BWFAN_MCP_Format::collection( $response, self::CONTACT_KEYS, 'result' );

		$collection['items'] = array_map( array( __CLASS__, 'decorate_contact_row' ), self::overlay_unsubscribed( $collection['items'] ) );

		if ( null === $collection['total'] ) {
			$collection['total'] = 0;
		}

		return $collection;
	}

	/**
	 * An unsubscribe is recorded in the unsubscribe table, not on bwf_contact.status,
	 * so a list row still reads its old status (0 Unverified, 1 Subscribed) after the
	 * contact has opted out. get-contact and filter-contacts already overlay status 3
	 * on that check; do the same here so a segment built from the list is not wrong.
	 *
	 * @param array $items List rows carrying email/contact_no.
	 *
	 * @return array
	 */
	private static function overlay_unsubscribed( array $items ) {
		if ( empty( $items ) || ! class_exists( 'BWFAN_Model_Message_Unsubscribe' ) ) {
			return $items;
		}

		$recipients = array();

		foreach ( $items as $item ) {
			foreach ( array( 'email', 'contact_no' ) as $key ) {
				if ( ! empty( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
					$recipients[] = strtolower( trim( (string) $item[ $key ] ) );
				}
			}
		}

		$recipients = array_values( array_unique( array_filter( $recipients ) ) );

		if ( empty( $recipients ) ) {
			return $items;
		}

		// One query for the page: the row-at-a-time check the admin listing does
		// would be a query per contact here.
		$rows = BWFAN_Model_Message_Unsubscribe::get_message_unsubscribe_row( array( 'recipient' => $recipients ), false );

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $items;
		}

		$unsubscribed = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['recipient'] ) ) {
				$unsubscribed[ strtolower( trim( (string) $row['recipient'] ) ) ] = true;
			}
		}

		foreach ( $items as $index => $item ) {
			foreach ( array( 'email', 'contact_no' ) as $key ) {
				if ( ! empty( $item[ $key ] ) && is_scalar( $item[ $key ] ) && isset( $unsubscribed[ strtolower( trim( (string) $item[ $key ] ) ) ] ) ) {
					$items[ $index ]['status'] = 3;
					break;
				}
			}
		}

		return $items;
	}

	public function get_contact( $input = array() ) {
		$contact_id = isset( $input['contact_id'] ) ? (int) $input['contact_id'] : 0;

		if ( ! $contact_id && ! empty( $input['email'] ) ) {
			$found = BWFAN_MCP_Dispatch::call( 'list-contacts', array(
				'search' => sanitize_email( $input['email'] ),
				'limit'  => 20,
				'offset' => 0,
			) );

			if ( is_wp_error( $found ) ) {
				return $found;
			}

			$collection = BWFAN_MCP_Format::collection( $found, self::CONTACT_KEYS, 'result' );

			$wanted = strtolower( trim( sanitize_email( $input['email'] ) ) );
			$match  = null;

			foreach ( $collection['items'] as $item ) {
				if ( isset( $item['email'] ) && strtolower( trim( $item['email'] ) ) === $wanted ) {
					$match = $item;
					break;
				}
			}

			if ( null === $match || empty( $match['id'] ) ) {
				return new WP_Error( 'fkmcp_not_found', __( 'No contact with that email.', 'wp-marketing-automations' ) );
			}

			$contact_id = (int) $match['id'];
		}

		if ( ! $contact_id ) {
			return new WP_Error( 'fkmcp_invalid_input', __( 'Provide contact_id or email.', 'wp-marketing-automations' ) );
		}

		$response = BWFAN_MCP_Dispatch::call( 'get-contact', array(), array( 'contact_id' => $contact_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$row = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();

		return self::decorate_contact_row( self::contact_profile( $row ) );
	}

	/** The full single-contact view: the list row plus location, terms, fields keyed by slug, and the summary blocks. */
	private static function contact_profile( array $row ) {
		$out = BWFAN_MCP_Format::pick( $row, array_merge( self::CONTACT_KEYS, self::CONTACT_PROFILE_KEYS ) );

		foreach ( array( 'tags', 'lists' ) as $key ) {
			$terms = array();

			foreach ( ( isset( $out[ $key ] ) && is_array( $out[ $key ] ) ) ? $out[ $key ] : array() as $term ) {
				$term = (array) $term;

				if ( isset( $term['ID'] ) ) {
					$terms[] = array( 'id' => (int) $term['ID'], 'name' => isset( $term['name'] ) ? (string) $term['name'] : '' );
				}
			}

			$out[ $key ] = $terms;
		}

		// Field values arrive keyed by field id; slugs are what an agent can
		// read and write elsewhere, so key by slug where one is known and drop
		// the empties.
		if ( isset( $out['fields'] ) && is_array( $out['fields'] ) ) {
			$slugs = array();
			$defs  = BWFAN_MCP_Dispatch::call( 'list-custom-fields' );

			if ( ! is_wp_error( $defs ) && isset( $defs['result'] ) && is_array( $defs['result'] ) ) {
				foreach ( $defs['result'] as $def ) {
					if ( is_array( $def ) && isset( $def['ID'] ) ) {
						$slugs[ (int) $def['ID'] ] = isset( $def['slug'] ) && '' !== (string) $def['slug'] ? (string) $def['slug'] : (string) $def['ID'];
					}
				}
			}

			$fields = array();

			foreach ( $out['fields'] as $id => $value ) {
				if ( null === $value || '' === $value ) {
					continue;
				}

				$fields[ isset( $slugs[ (int) $id ] ) ? $slugs[ (int) $id ] : (string) $id ] = $value;
			}

			$out['fields'] = $fields;
		}

		return $out;
	}

	public function get_contact_automations( $input = array() ) {
		$response = BWFAN_MCP_Dispatch::call(
			'get-contact-automations',
			array(),
			array( 'contact_id' => (int) $input['contact_id'] )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();

		return BWFAN_MCP_Format::collection( $data, array( 'aid', 'automation_title', 'status', 'c_date', 'e_time', 'event' ), 'contacts' );
	}

	public function get_contact_orders( $input = array() ) {
		$page = BWFAN_MCP_Format::paginate( (array) $input );

		$response = BWFAN_MCP_Dispatch::call(
			'get-contact-orders',
			array( 'limit' => $page['limit'], 'offset' => $page['offset'] ),
			array( 'contact_id' => (int) $input['contact_id'] )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$rows = isset( $data['orders'] ) && is_array( $data['orders'] ) ? $data['orders'] : array();

		$items = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$names = array();

			if ( ! empty( $row['products'] ) && is_array( $row['products'] ) ) {
				foreach ( $row['products'] as $product ) {
					$name = is_object( $product ) ? ( $product->name ?? null ) : ( is_array( $product ) ? ( $product['name'] ?? null ) : null );

					if ( $name && is_scalar( $name ) ) {
						$names[] = $name;
					}
				}
			}

			$items[] = array(
				'order_id'      => isset( $row['order_id'] ) ? $row['order_id'] : null,
				'status'        => $this->flat_field( $row['status'] ?? null, 'label' ),
				'currency'      => $this->flat_field( $row['currency'] ?? null, 'code' ),
				'order_total'   => isset( $row['order_total'] ) && is_numeric( $row['order_total'] ) ? (float) $row['order_total'] : null,
				'product_count' => count( $names ),
				'product_names' => $names,
			);
		}

		return array(
			'items' => $items,
			'total' => empty( $rows ) ? 0 : ( isset( $response['total_count'] ) ? (int) $response['total_count'] : null ),
		);
	}

	private function flat_field( $value, $key ) {
		if ( is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) && isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
			return $value[ $key ];
		}

		return null;
	}

	public function get_contact_engagements( $input = array() ) {
		$page = BWFAN_MCP_Format::paginate( (array) $input );

		$response = BWFAN_MCP_Dispatch::call(
			'get-contact-engagements',
			array( 'limit' => $page['limit'], 'offset' => $page['offset'] ),
			array( 'contact_id' => (int) $input['contact_id'] )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$collection = BWFAN_MCP_Format::collection( $response, array( 'ID', 'subject', 'mode', 'type', 'oid', 'created_at', 'open', 'click' ), 'result' );

		// The tracking table keeps the channel in `mode` and what sent it in `type`, and stamps
		// created_at in UTC; every other date the pack hands back is site time.
		$collection['items'] = array_map(
			static function ( $row ) {
				$mode = isset( $row['mode'] ) ? (int) $row['mode'] : 0;
				$type = isset( $row['type'] ) ? (int) $row['type'] : 0;
				$oid  = isset( $row['oid'] ) ? (int) $row['oid'] : 0;

				$row['mode']      = $mode;
				$row['type']      = $type;
				$row['channel']   = isset( self::ENGAGEMENT_CHANNELS[ $mode ] ) ? self::ENGAGEMENT_CHANNELS[ $mode ] : null;
				$row['source']    = isset( self::ENGAGEMENT_SOURCES[ $type ] ) ? self::ENGAGEMENT_SOURCES[ $type ] : null;
				$row['source_id'] = $oid > 0 ? $oid : null;
				unset( $row['oid'] );

				if ( ! empty( $row['created_at'] ) && is_string( $row['created_at'] ) ) {
					$row['created_at'] = get_date_from_gmt( $row['created_at'] );
				}

				return $row;
			},
			$collection['items']
		);

		// The controller carries no total; count the same rows the listing
		// query reads (all modes).
		$collection['total'] = class_exists( 'BWFAN_Model_Engagement_Tracking' )
			? (int) BWFAN_Model_Engagement_Tracking::count( array( 'cid' => array( 'operator' => '%d', 'value' => (int) $input['contact_id'] ) ) )
			: null;

		return $collection;
	}

	public function list_automations( $input = array() ) {
		$page = BWFAN_MCP_Format::paginate( (array) $input );

		// version=2 like the app's own list screen: legacy v1 counting reads
		// tasks/logs tables that V2-only installs may not have.
		$params = array( 'limit' => $page['limit'], 'offset' => $page['offset'], 'version' => 2 );

		if ( ! empty( $input['status'] ) ) {
			$params['status'] = (int) $input['status'];
		}

		if ( ! empty( $input['search'] ) ) {
			$params['search'] = sanitize_text_field( $input['search'] );
		}

		$response = BWFAN_MCP_Dispatch::call( 'list-automations', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data       = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$collection = BWFAN_MCP_Format::collection( $data, self::AUTOMATION_LIST_KEYS, 'automations' );

		if ( null === $collection['total'] ) {
			$collection['total'] = isset( $response['total_count'] ) ? (int) $response['total_count'] : 0;
		}

		return $collection;
	}

	public function get_automation( $input = array() ) {
		$requested_id = (int) $input['automation_id'];

		// Several tools can share one PHP process (JSON-RPC batch, STDIO, WP-CLI): read the row as it is now.
		if ( class_exists( 'BWFAN_Automation_V2' ) ) {
			BWFAN_Automation_V2::unset_instance( $requested_id );
		}

		$response = BWFAN_MCP_Dispatch::call(
			'get-automation',
			array(),
			array( 'automation_id' => $requested_id )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$row    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		// Cheap assertion, not a workaround: BWFAN_Automation_V2::get_instance()
		// now caches per id, so a mismatch here means a real dispatch fault.
		if ( ! $this->row_is_the_requested_automation( $row, $requested_id ) ) {
			return new WP_Error(
				'fkmcp_automation_mismatch',
				__( 'FunnelKit returned a different automation than requested.', 'wp-marketing-automations' )
			);
		}

		$automation = BWFAN_MCP_Format::pick( $row, array( 'ID', 'title', 'status', 'event', 'source', 'priority', 'category', 'v' ) );

		if ( isset( $result['revision'] ) && is_string( $result['revision'] ) ) {
			$automation['revision'] = $result['revision']; // pass to update-automation.
		}

		$steps                = isset( $result['steps'] ) && is_array( $result['steps'] ) ? $result['steps'] : array();
		$automation['steps'] = $this->flatten_automation_steps( $steps );

		// Pro adds each step's definition path (steps[2].yes[0]) so an agent can target it (keep, template_id).
		$automation = apply_filters( 'fkmcp_get_automation_result', $automation, $requested_id, $result );

		return is_array( $automation ) ? self::fit_budget( $automation ) : $automation;
	}

	private function row_is_the_requested_automation( array $row, $requested_id ) {
		return isset( $row['ID'] ) && (int) $row['ID'] === (int) $requested_id;
	}

	private function flatten_automation_steps( array $steps ) {
		$out = array();

		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$data = isset( $step['data'] ) && is_array( $step['data'] ) ? $step['data'] : array();

			$row = array(
				'id'          => isset( $step['id'] ) ? $step['id'] : null,
				'type'        => isset( $step['type'] ) ? $step['type'] : null,
				'step_status' => isset( $step['step_status'] ) ? $step['step_status'] : null,
				'action'      => isset( $data['selected'] ) && is_scalar( $data['selected'] ) ? $data['selected'] : null,
				'description' => $this->flatten_step_description( isset( $data['desc_text'] ) ? $data['desc_text'] : null ),
			);

			$email = $this->email_step_summary( $data );

			if ( null !== $email ) {
				$row['email'] = $email;
			} else {
				$row += self::step_settings( (string) $row['type'], $data );
			}

			$label = self::step_label( (string) $row['type'], $data );

			if ( null !== $label ) {
				$row['label'] = $label;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * What a step does, from the settings the builder saved with it: rules in the
	 * shape create-automation and update-automation take, everything else as stored.
	 *
	 * @param string $type The node type.
	 * @param array  $data The node's data.
	 *
	 * @return array settings (and truncated when cut), or nothing for steps without settings.
	 */
	private static function step_settings( $type, array $data ) {
		if ( ! array_key_exists( 'sidebarValues', $data ) ) {
			return array();
		}

		// Decoded JSON arrives as objects as often as arrays.
		$saved = json_decode( (string) wp_json_encode( $data['sidebarValues'] ), true );

		switch ( $type ) {
			case 'conditional':
				$settings = array( 'rules' => self::agent_rules( $saved ) );
				break;
			case 'branches':
				$branches = array();
				foreach ( isset( $saved['branches'] ) && is_array( $saved['branches'] ) ? $saved['branches'] : array() as $branch ) {
					$branches[] = array(
						'name'  => isset( $branch['name'] ) ? (string) $branch['name'] : '',
						'rules' => self::agent_rules( isset( $branch['rules'] ) ? $branch['rules'] : array() ),
					);
				}
				$settings = array( 'branches' => $branches, 'has_else' => true ); // the builder always adds the else lane.
				break;
			case 'split':
				$settings = array(
					'paths' => isset( $saved['split_path'] ) ? (int) $saved['split_path'] : 0,
					'title' => isset( $saved['title'] ) ? (string) $saved['title'] : '',
					'desc'  => isset( $saved['desc'] ) ? (string) $saved['desc'] : '',
				);
				break;
			case 'benchmark':
				$settings = array(
					'goal'     => isset( $data['benchmark'] ) && is_scalar( $data['benchmark'] ) ? (string) $data['benchmark'] : null,
					'settings' => self::strip_secrets( $saved ),
				);
				break;
			default:
				$settings = self::strip_secrets( $saved );
		}

		if ( strlen( (string) wp_json_encode( $settings ) ) > self::STEP_SETTINGS_MAX ) {
			return array( 'settings' => null, 'truncated' => true );
		}

		return array( 'settings' => $settings );
	}

	/** [[{filter, rule, data}]] as saved → [[{rule, operator, value}]] as the definition takes it. */
	private static function agent_rules( $groups ) {
		$out = array();

		foreach ( is_array( $groups ) ? $groups : array() as $group ) {
			$rules = array();
			foreach ( is_array( $group ) ? $group : array() as $rule ) {
				if ( is_array( $rule ) ) {
					$rules[] = array(
						'rule'     => isset( $rule['filter'] ) ? $rule['filter'] : null,
						'operator' => isset( $rule['rule'] ) ? $rule['rule'] : null,
						'value'    => isset( $rule['data'] ) ? $rule['data'] : null,
					);
				}
			}
			$out[] = $rules;
		}

		return $out;
	}

	/**
	 * Drops every key that looks like a credential, at any depth; hides the value of
	 * a header row named like one and any webhook capability URL.
	 */
	private static function strip_secrets( $value ) {
		if ( is_string( $value ) ) {
			return preg_match( self::CAPABILITY_URL_PATTERN, $value ) ? '[hidden]' : $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( array( 'field', 'key', 'name', 'header' ) as $name_key ) {
			if ( isset( $value[ $name_key ] ) && is_string( $value[ $name_key ] ) && preg_match( self::SECRET_ROW_NAME_PATTERN, $value[ $name_key ] ) ) {
				foreach ( array( 'field_value', 'value' ) as $value_key ) {
					if ( array_key_exists( $value_key, $value ) ) {
						$value[ $value_key ] = '[hidden]';
					}
				}
			}
		}

		foreach ( $value as $key => $one ) {
			if ( is_string( $key ) && preg_match( self::SECRET_KEY_PATTERN, $key ) ) {
				unset( $value[ $key ] );
				continue;
			}
			$value[ $key ] = self::strip_secrets( $one );
		}

		return $value;
	}

	/**
	 * Keeps get-automation under RESULT_MAX: the per-step settings go first (with Pro,
	 * the definition carries the same), then the definition, each saying so.
	 *
	 * @param array $automation get-automation's result.
	 *
	 * @return array
	 */
	private static function fit_budget( array $automation ) {
		if ( strlen( (string) wp_json_encode( $automation ) ) <= self::RESULT_MAX ) {
			return $automation;
		}

		foreach ( isset( $automation['steps'] ) && is_array( $automation['steps'] ) ? array_keys( $automation['steps'] ) : array() as $i ) {
			if ( is_array( $automation['steps'][ $i ] ) && array_key_exists( 'settings', $automation['steps'][ $i ] ) ) {
				$automation['steps'][ $i ]['settings']  = null;
				$automation['steps'][ $i ]['truncated'] = true;
			}
		}

		if ( strlen( (string) wp_json_encode( $automation ) ) > self::RESULT_MAX && ! empty( $automation['definition'] ) ) {
			$automation['definition']             = null;
			$automation['definition_unavailable'] = array( array( 'path' => null, 'reason' => 'the response would be longer than 100 KB' ) );
		}

		return $automation;
	}

	/** The name a branch, split path or yes/no row shows in the builder. */
	private static function step_label( $type, array $data ) {
		switch ( $type ) {
			case 'branchpath':
				return ! empty( $data['isElse'] ) ? 'Else' : ( isset( $data['name'] ) ? (string) $data['name'] : '' );
			case 'splitpath':
				return 'Path ' . ( isset( $data['path'] ) ? (int) $data['path'] : 0 );
			case 'yesNoNode':
				return ( isset( $data['direction'] ) && 'no' === $data['direction'] ) ? 'No' : 'Yes';
		}

		return null;
	}

	/**
	 * What a Send Email step sends, without its body: subject, preheader and
	 * which editor made it. The body stays out - it is the rendered email, far
	 * past what a result string may carry, and nothing here can write it back.
	 *
	 * The step's settings arrive as decoded JSON objects as often as arrays,
	 * so every level is cast before it is read.
	 *
	 * @param array $data The step's data as the automation controller returns it.
	 *
	 * @return array|null Null for any step that is not a Send Email action.
	 */
	private function email_step_summary( array $data ) {
		if ( ! isset( $data['selected'] ) || 'wp_sendemail' !== $data['selected'] ) {
			return null;
		}

		$values = isset( $data['sidebarValues'] ) ? (array) $data['sidebarValues'] : array();
		$email  = isset( $values['bwfan_email_data'] ) ? (array) $values['bwfan_email_data'] : array();

		if ( empty( $email ) ) {
			return null;
		}

		$settings = isset( $email['data'] ) ? (array) $email['data'] : array();
		$mode     = isset( $email['mode'] ) && is_numeric( $email['mode'] ) ? (int) $email['mode'] : null;
		$template = isset( $email['template'] ) && is_string( $email['template'] ) ? $email['template'] : '';

		return array(
			'subject'    => self::plain_text( isset( $email['subject'] ) ? $email['subject'] : '' ),
			'preheader'  => self::plain_text( isset( $settings['preheader'] ) ? $settings['preheader'] : '' ),
			'mode'       => $mode,
			'mode_label' => null !== $mode && isset( self::EMAIL_EDITOR_MODES[ $mode ] ) ? self::EMAIL_EDITOR_MODES[ $mode ] : null,
			'has_design' => self::email_has_design( $mode, $settings ),
			'has_html'   => '' !== trim( $template ),
		);
	}

	/**
	 * Whether the step holds an editable design beside its HTML: the block
	 * markup for the block editor, the builder JSON for the legacy visual
	 * builder. Rich text and raw HTML have no design apart from the body.
	 *
	 * @param int|null $mode
	 * @param array    $settings bwfan_email_data.data
	 *
	 * @return bool
	 */
	private static function email_has_design( $mode, array $settings ) {
		if ( 5 === $mode ) {
			$block = isset( $settings['block'] ) ? (array) $settings['block'] : array();

			return isset( $block['body'] ) && is_string( $block['body'] ) && '' !== trim( $block['body'] );
		}

		if ( 4 === $mode ) {
			return ! empty( $settings['design'] );
		}

		return false;
	}

	/** A stored scalar as plain text; anything else as an empty string. */
	private static function plain_text( $value ) {
		return is_scalar( $value ) ? wp_strip_all_tags( (string) $value, true ) : '';
	}

	private function flatten_step_description( $value ) {
		if ( is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$scalars = array_filter( $value, 'is_scalar' );

			return $scalars ? implode( ', ', $scalars ) : null;
		}

		return null;
	}

	public function get_automation_stats( $input = array() ) {
		$unknown_ids = array();

		if ( ! empty( $input['automation_ids'] ) && is_array( $input['automation_ids'] ) ) {
			$total_lookup = BWFAN_MCP_Dispatch::call( 'list-automations', array( 'limit' => 1, 'offset' => 0, 'version' => 2 ) );

			if ( is_wp_error( $total_lookup ) ) {
				return $total_lookup;
			}

			$total_automations = isset( $total_lookup['total_count'] ) ? (int) $total_lookup['total_count'] : 0;
			$requested_ids     = array_values( array_unique( array_filter( array_map( 'absint', $input['automation_ids'] ) ) ) );
			$automation_ids = $this->real_automation_ids( $requested_ids );
			$unknown_ids    = array_values( array_diff( $requested_ids, $automation_ids ) );

			$truncated      = false;
		} else {
			$list = BWFAN_MCP_Dispatch::call( 'list-automations', array( 'limit' => 100, 'offset' => 0, 'version' => 2 ) );

			if ( is_wp_error( $list ) ) {
				return $list;
			}

			$total_automations = isset( $list['total_count'] ) ? (int) $list['total_count'] : 0;

			$rows = isset( $list['result']['automations'] ) && is_array( $list['result']['automations'] ) ? $list['result']['automations'] : array();

			$automation_ids = array();
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$automation_ids[] = (int) $row['id'];
				}
			}

			$truncated = count( $automation_ids ) < $total_automations;
		}

		if ( empty( $automation_ids ) ) {
			return array(
				'items'               => array(),
				'total_automations'   => $total_automations,
				'covered_automations' => 0,
				'truncated'           => $truncated,
				'unknown_ids'         => $unknown_ids,
			);
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $automation_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN() list is %d placeholders counted off the int array above.
		$state_rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT aid,
				SUM( CASE WHEN status IN (1, 4, 6) THEN 1 ELSE 0 END ) AS active,
				SUM( CASE WHEN status = 2 THEN 1 ELSE 0 END ) AS failed,
				SUM( CASE WHEN status = 3 THEN 1 ELSE 0 END ) AS paused,
				COUNT(*) AS total
			FROM {$wpdb->prefix}bwfan_automation_contact
			WHERE aid IN ({$placeholders})
			GROUP BY aid",
			$automation_ids
		), ARRAY_A );
		// A switched-off Automation holds its Contacts still: the admin and
		// list-automation-contacts count them as inactive, not by step state.
		$switched_on  = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, status FROM {$wpdb->prefix}bwfan_automations WHERE ID IN ({$placeholders})", $automation_ids ), ARRAY_A ) as $row ) {
			$switched_on[ (int) $row['ID'] ] = 1 === (int) $row['status'];
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$states       = array_column( (array) $state_rows, null, 'aid' );

		$completed = array();
		foreach ( (array) BWFAN_Model_Automation_Complete_Contact::get_automation_complete_contact_count( $automation_ids ) as $row ) {
			if ( is_array( $row ) && isset( $row['aid'] ) ) {
				$completed[ (int) $row['aid'] ] = (int) $row['count'];
			}
		}

		$items = array();

		foreach ( $automation_ids as $automation_id ) {
			$state   = isset( $states[ $automation_id ] ) ? $states[ $automation_id ] : array();
			$on      = ! empty( $switched_on[ $automation_id ] );
			$items[] = array(
				'automation_id'     => (int) $automation_id,
				'automation_active' => $on,
				'active'            => $on && isset( $state['active'] ) ? (int) $state['active'] : 0,
				'complete'          => isset( $completed[ $automation_id ] ) ? $completed[ $automation_id ] : 0,
				'failed'            => $on && isset( $state['failed'] ) ? (int) $state['failed'] : 0,
				'paused'            => $on && isset( $state['paused'] ) ? (int) $state['paused'] : 0,
				'inactive'          => ! $on && isset( $state['total'] ) ? (int) $state['total'] : 0,
			);
		}

		return array(
			'items'               => $items,
			'total_automations'   => $total_automations,

			'covered_automations' => count( $items ),
			'truncated'           => $truncated,
			'unknown_ids'         => $unknown_ids,
		);
	}

	private function real_automation_ids( array $ids ) {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'bwfan_automations';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table from $wpdb->prefix; ids bound via %d
		$found = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$table} WHERE ID IN ({$placeholders})", $ids ) );

		return array_map( 'intval', $found );
	}

	public function list_tags( $input = array() ) {
		$page   = BWFAN_MCP_Format::paginate( (array) $input, 50 );
		$params = array( 'limit' => $page['limit'], 'offset' => $page['offset'] );

		if ( ! empty( $input['search'] ) ) {
			$params['search'] = sanitize_text_field( $input['search'] );
		}

		$response = BWFAN_MCP_Dispatch::call( 'list-tags', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return BWFAN_MCP_Format::collection( $response, self::TAG_KEYS, 'result' );
	}

	public function list_contact_lists( $input = array() ) {
		$page   = BWFAN_MCP_Format::paginate( (array) $input, 50 );
		$params = array( 'limit' => $page['limit'], 'offset' => $page['offset'] );

		if ( ! empty( $input['search'] ) ) {
			$params['search'] = sanitize_text_field( $input['search'] );
		}

		$response = BWFAN_MCP_Dispatch::call( 'list-lists', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows  = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$items = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = BWFAN_MCP_Format::pick( $row, self::LIST_KEYS );

			$item['description'] = isset( $row['description'] ) && is_scalar( $row['description'] ) ? $row['description'] : null;

			$items[] = $item;
		}

		return array(
			'items' => $items,
			'total' => isset( $response['total_count'] ) ? (int) $response['total_count'] : null,
		);
	}

	public function list_custom_fields( $input = array() ) {
		$response = BWFAN_MCP_Dispatch::call( 'list-custom-fields' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows  = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$items = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = BWFAN_MCP_Format::pick( $row, self::CUSTOM_FIELD_KEYS );

			// The field table answers these as strings on the rows it stores
			// itself, where the schema calls them integers.
			$item['type']     = isset( $item['type'] ) ? (int) $item['type'] : null;
			$item['group_id'] = isset( $item['group_id'] ) ? (int) $item['group_id'] : null;

			$meta = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();

			$item['options']     = isset( $meta['options'] ) && is_array( $meta['options'] ) ? array_values( array_filter( $meta['options'], 'is_scalar' ) ) : array();
			$item['placeholder'] = isset( $meta['placeholder'] ) && is_scalar( $meta['placeholder'] ) ? $meta['placeholder'] : null;

			$items[] = $item;
		}

		return array( 'items' => $items );
	}

	public function list_abandoned_carts( $input = array() ) {
		$page = BWFAN_MCP_Format::paginate( (array) $input );

		$status = isset( $input['status'] ) && is_string( $input['status'] ) ? $input['status'] : 'recoverable';

		if ( ! isset( self::CART_STATUS_SLUGS[ $status ] ) ) {
			return new WP_Error(
				'fkmcp_invalid_input',
				__( 'Unknown cart status.', 'wp-marketing-automations' ),
				array( 'allowed' => array_keys( self::CART_STATUS_SLUGS ) )
			);
		}

		$wants_filtered = isset( $input['country'] ) || isset( $input['has_phone'] ) || ! empty( $input['after'] ) || ! empty( $input['before'] );

		if ( $wants_filtered ) {
			if ( 'recovered' === $status ) {
				return new WP_Error(
					'fkmcp_invalid_input',
					__( 'Date/country/phone filters only apply to recoverable and lost carts; recovered carts live on the WooCommerce order.', 'wp-marketing-automations' )
				);
			}

			return $this->filtered_cart_query( $input, $status, $page );
		}

		$params = array(
			'limit'  => $page['limit'],
			'offset' => $page['offset'],
		);

		if ( ! empty( $input['search'] ) ) {
			$params['search']    = sanitize_text_field( $input['search'] );
			$params['search_by'] = 'email';
		}

		$response = BWFAN_MCP_Dispatch::call( self::CART_STATUS_SLUGS[ $status ], $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows  = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$items = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = BWFAN_MCP_Format::pick( $row, self::ABANDONED_CART_KEYS );

			$item['currency'] = $this->flat_field( isset( $row['currency'] ) ? $row['currency'] : null, 'code' );

			$product_names = array();
			if ( ! empty( $row['items'] ) && is_array( $row['items'] ) ) {
				$product_names = array_values( array_filter( $row['items'], 'is_scalar' ) );
			}
			$item['product_names'] = $product_names;

			$items[] = $item;
		}

		return array(
			'items'  => $items,
			'status' => $status,

			'total'  => isset( $response['total_count'] ) ? (int) $response['total_count'] : 0,
		);
	}

	private function filtered_cart_query( $input, $status, $page ) {
		$after  = ! empty( $input['after'] ) && is_string( $input['after'] ) ? $input['after'] : '';
		$before = ! empty( $input['before'] ) && is_string( $input['before'] ) ? $input['before'] : '';

		foreach ( array( 'after' => $after, 'before' => $before ) as $name => $value ) {
			if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				return new WP_Error( 'fkmcp_invalid_input', __( 'Dates must be Y-m-d.', 'wp-marketing-automations' ), array( 'field' => $name ) );
			}
		}

		if ( '' !== $after && '' !== $before && strtotime( $after ) > strtotime( $before ) ) {
			return new WP_Error( 'fkmcp_invalid_input', __( 'after must not be later than before.', 'wp-marketing-automations' ) );
		}

		if ( ( isset( $input['country'] ) || isset( $input['has_phone'] ) ) && '' === $after ) {
			return new WP_Error( 'fkmcp_invalid_input', __( 'country/has_phone need a bounded window: pass after (and optionally before).', 'wp-marketing-automations' ) );
		}

		global $wpdb;

		// Same statuses and the same ordering the controllers use, so adding a
		// date filter re-sorts nothing: created_time bounds the window,
		// last_modified orders it - and last_modified is what this tool
		// reports as `date`.
		$where  = 'lost' === $status ? 'WHERE status = 2' : 'WHERE status IN (0,1,3,4,5)';
		$params = array();

		// created_time is stored in UTC (current_time( 'mysql', 1 )), while the
		// caller's window - and every date this tool reports - is site time.
		if ( '' !== $after ) {
			$where   .= ' AND created_time >= %s';
			$params[] = get_gmt_from_date( $after . ' 00:00:00' );
		}

		if ( '' !== $before ) {
			$where   .= ' AND created_time <= %s';
			$params[] = get_gmt_from_date( $before . ' 23:59:59' );
		}

		if ( ! empty( $input['search'] ) ) {
			$where   .= ' AND email LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
		}

		$table   = $wpdb->prefix . 'bwfan_abandonedcarts';
		$columns = 'ID, email, status, created_time, last_modified, total, currency, order_id, user_id, items, checkout_data';

		$country   = isset( $input['country'] ) && is_string( $input['country'] ) ? strtoupper( $input['country'] ) : '';
		$has_phone = isset( $input['has_phone'] ) ? (bool) $input['has_phone'] : null;

		// Without country/has_phone the whole window is expressible in SQL, so
		// the page and the count come straight from the database.
		if ( '' === $country && null === $has_phone ) {
			$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
			$rows_sql  = "SELECT {$columns} FROM {$table} {$where} ORDER BY last_modified DESC LIMIT %d OFFSET %d";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- query built with hardcoded fragments and prepare() params
			$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, ...$params ) : $count_sql );

			$row_params = array_merge( $params, array( $page['limit'], $page['offset'] ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- hardcoded columns; values bound via prepare()
			$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$row_params ), ARRAY_A );

			return array(
				'items'  => $this->cart_rows( is_array( $rows ) ? $rows : array() ),
				'status' => $status,
				'total'  => $total,
			);
		}

		// country/has_phone live inside the checkout_data JSON, so they can only
		// be applied in PHP. Walk the window in batches counting every match and
		// keeping only the requested page: `total` is then the real number, not
		// however many happened to fall inside an arbitrary first slice.
		$scan_limit = (int) apply_filters( 'fkmcp_cart_scan_limit', 20000 );
		// A row ceiling alone does not bound the work: 20k json_decodes behind
		// 40 deepening OFFSET queries can outlast max_execution_time, and a
		// request killed mid-scan reports nothing at all - where a scan stopped
		// on its own clock still returns a page and says it was truncated.
		$deadline   = microtime( true ) + max( 1, (int) apply_filters( 'fkmcp_cart_scan_seconds', 10 ) );
		$batch      = 500;
		$scanned    = 0;
		$matched    = 0;
		$truncated  = false;
		$page_ids   = array();
		$page_start = $page['offset'];
		$page_end   = $page['offset'] + $page['limit'];

		$scan_sql = "SELECT ID, checkout_data FROM {$table} {$where} ORDER BY last_modified DESC LIMIT %d OFFSET %d";

		do {
			$scan_params = array_merge( $params, array( $batch, $scanned ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- hardcoded fragments; values bound via prepare()
			$rows = $wpdb->get_results( $wpdb->prepare( $scan_sql, ...$scan_params ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();

			foreach ( $rows as $row ) {
				$fields = $this->cart_checkout_fields( $row );

				$row_phone   = isset( $fields['billing_phone'] ) && is_scalar( $fields['billing_phone'] ) ? trim( (string) $fields['billing_phone'] ) : '';
				$row_country = isset( $fields['billing_country'] ) && is_scalar( $fields['billing_country'] ) ? strtoupper( trim( (string) $fields['billing_country'] ) ) : '';

				if ( '' !== $country && $row_country !== $country ) {
					continue;
				}

				if ( true === $has_phone && '' === $row_phone ) {
					continue;
				}

				if ( false === $has_phone && '' !== $row_phone ) {
					continue;
				}

				if ( $matched >= $page_start && $matched < $page_end ) {
					$page_ids[] = (int) $row['ID'];
				}

				++ $matched;
			}

			$scanned += count( $rows );

			// A short batch means the window is exhausted and the count is
			// complete; only a full batch cut off by the ceiling is truncated.
			if ( count( $rows ) < $batch ) {
				break;
			}

			if ( $scanned >= $scan_limit || microtime( true ) >= $deadline ) {
				$truncated = true;
				break;
			}
		} while ( true );

		$page_rows = array();

		if ( $page_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $page_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- hardcoded columns; ids (int)-cast and bound via %d
			$page_rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE ID IN ({$placeholders}) ORDER BY last_modified DESC", ...$page_ids ), ARRAY_A );
			$page_rows = is_array( $page_rows ) ? $page_rows : array();
		}

		$out = array(
			'items'  => $this->cart_rows( $page_rows ),
			'status' => $status,
			'total'  => $matched,
		);

		// Say so rather than let a capped scan pass for a complete count.
		if ( $truncated ) {
			$out['scan_truncated'] = true;
			$out['scanned']        = $scanned;
		}

		return $out;
	}

	private function cart_checkout_fields( array $row ) {
		$data = json_decode( isset( $row['checkout_data'] ) ? (string) $row['checkout_data'] : '', true );

		return ( is_array( $data ) && isset( $data['fields'] ) && is_array( $data['fields'] ) ) ? $data['fields'] : array();
	}

	private function cart_product_names( array $row ) {
		if ( empty( $row['items'] ) || ! class_exists( 'WC_Product' ) ) {
			return array();
		}

		return array_values( BWFAN_Common::cart_item_names( $row['items'] ) );
	}

	/**
	 * Builds cart rows in the shape the unfiltered list-abandoned-carts branch
	 * returns - ABANDONED_CART_KEYS plus currency and product_names. Adding a
	 * filter must never change which fields an agent gets back.
	 */
	private function cart_rows( array $rows ) {
		$items = array();

		foreach ( $rows as $row ) {
			$fields        = $this->cart_checkout_fields( $row );
			$last_modified = isset( $row['last_modified'] ) ? (string) $row['last_modified'] : '';
			$created_time  = isset( $row['created_time'] ) ? (string) $row['created_time'] : '';

			$items[] = array(
				'id'            => isset( $row['ID'] ) ? (int) $row['ID'] : null,
				'email'         => isset( $row['email'] ) ? $row['email'] : '',
				'phone'         => isset( $fields['billing_phone'] ) && is_scalar( $fields['billing_phone'] ) ? trim( (string) $fields['billing_phone'] ) : '',
				'f_name'        => '',
				'l_name'        => '',
				'contact_id'    => null,
				'status'        => isset( $row['status'] ) ? (int) $row['status'] : null,
				// Stored in UTC; the whole tool reports site time.
				'date'          => '' !== $last_modified ? get_date_from_gmt( $last_modified ) : null,
				'created_on'    => '' !== $created_time ? get_date_from_gmt( $created_time ) : null,
				'diffstring'    => $this->cart_diffstring( $last_modified ),
				'total'         => isset( $row['total'] ) ? $row['total'] : null,
				// Only recovered carts carry an Order, and those never reach
				// this branch - the controller reports '' for the rest too.
				'buyer_name'    => '',
				'order_id'      => isset( $row['order_id'] ) ? (int) $row['order_id'] : 0,
				'user_id'       => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
				'currency'      => isset( $row['currency'] ) && is_scalar( $row['currency'] ) ? $row['currency'] : null,
				'product_names' => $this->cart_product_names( $row ),
				'checkout_data' => isset( $row['checkout_data'] ) ? $row['checkout_data'] : '',
			);
		}

		// Same name/contact backfill the list controllers run; it reads (and
		// drops) checkout_data, which is only carried this far to feed it.
		if ( $items && class_exists( 'BWFAN_Recoverable_Carts' ) ) {
			$items = BWFAN_Recoverable_Carts::populate_contact_info( $items );
		}

		foreach ( $items as $index => $item ) {
			unset( $items[ $index ]['checkout_data'] );
		}

		// Emails, phones and the backfilled names all came from the checkout form.
		return self::untrust( array_values( $items ) );
	}

	private function cart_diffstring( $last_modified ) {
		// BWFAN_Common is guaranteed by is_available().
		if ( '' === $last_modified ) {
			return null;
		}

		try {
			$now  = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$then = new DateTime( $last_modified, new DateTimeZone( 'UTC' ) );

			return BWFAN_Common::get_difference_string( date_diff( $now, $then, true ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function get_abandoned_cart( $input = array() ) {
		if ( ! class_exists( 'BWFAN_Model_Abandonedcarts' ) ) {
			return new WP_Error( 'fkmcp_plugin_missing', __( 'FunnelKit cart storage is not available.', 'wp-marketing-automations' ) );
		}

		$row = BWFAN_Model_Abandonedcarts::get( (int) $input['cart_id'] );

		if ( empty( $row ) || ! is_array( $row ) ) {
			return new WP_Error( 'fkmcp_not_found', __( 'No abandoned cart with that id.', 'wp-marketing-automations' ) );
		}

		$cart = BWFAN_MCP_Format::pick( $row, array( 'ID', 'email', 'status', 'created_time', 'last_modified', 'total', 'total_base', 'currency', 'order_id', 'user_id' ) );

		// Stored in UTC; list-abandoned-carts reports site time - align.
		foreach ( array( 'created_time', 'last_modified' ) as $time_key ) {
			if ( ! empty( $cart[ $time_key ] ) && is_string( $cart[ $time_key ] ) ) {
				$cart[ $time_key ] = get_date_from_gmt( $cart[ $time_key ], 'Y-m-d H:i:s' );
			}
		}

		$cart['status_label'] = $this->cart_status_label( isset( $row['status'] ) ? (int) $row['status'] : null );

		$items    = array();
		$raw_list = maybe_unserialize( isset( $row['items'] ) ? $row['items'] : '' );

		if ( is_array( $raw_list ) ) {
			foreach ( $raw_list as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$product_id = isset( $entry['product_id'] ) ? (int) $entry['product_id'] : 0;

				$item = array(
					'product_id'   => $product_id,
					'product_name' => $product_id ? ( get_the_title( $product_id ) ?: null ) : null,
					'variation_id' => isset( $entry['variation_id'] ) ? (int) $entry['variation_id'] : 0,
					'quantity'     => isset( $entry['quantity'] ) && is_numeric( $entry['quantity'] ) ? $entry['quantity'] + 0 : null,
					'line_total'   => isset( $entry['line_total'] ) && is_numeric( $entry['line_total'] ) ? round( (float) $entry['line_total'], 2 ) : null,
					'line_tax'     => isset( $entry['line_subtotal_tax'] ) && is_numeric( $entry['line_subtotal_tax'] ) ? round( (float) $entry['line_subtotal_tax'], 2 ) : null,
				);

				// A Cart free gift legitimately carries a 0 line total; say so
				// or the items look broken next to the cart total.
				if ( ! empty( $entry['_fkcart_free_gift'] ) ) {
					$item['free_gift'] = true;
				}

				$items[] = $item;
			}
		}
		$cart['items'] = $items;

		$coupons = maybe_unserialize( isset( $row['coupons'] ) ? $row['coupons'] : '' );
		if ( is_array( $coupons ) ) {
			$cart['coupons'] = array_values( array_filter( $coupons, 'is_scalar' ) );
		} else {
			$cart['coupons'] = ( is_scalar( $coupons ) && '' !== trim( (string) $coupons ) ) ? array( (string) $coupons ) : array();
		}

		$billing       = array();
		$checkout_data = json_decode( isset( $row['checkout_data'] ) ? (string) $row['checkout_data'] : '', true );
		$fields        = is_array( $checkout_data ) && isset( $checkout_data['fields'] ) && is_array( $checkout_data['fields'] ) ? $checkout_data['fields'] : array();

		foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_country', 'billing_state', 'billing_city', 'billing_postcode' ) as $field ) {
			$billing[ $field ] = isset( $fields[ $field ] ) && is_scalar( $fields[ $field ] ) ? $fields[ $field ] : null;
		}
		$cart['billing'] = $billing;

		// Email, billing fields and coupon codes are visitor-typed.
		return self::untrust( $cart );
	}

	/**
	 * A cart row is only ever recoverable or lost: recovery deletes the row and
	 * the WooCommerce order takes its place, so there is no recovered status to
	 * report here.
	 *
	 * @param int|null $status
	 *
	 * @return string|null
	 */
	private function cart_status_label( $status ) {
		if ( null === $status ) {
			return null;
		}

		return 2 === $status ? 'lost' : 'recoverable';
	}

	public static function decorate_contact_row( array $row ) {
		// f_name/l_name/contact_no arrive from checkout and opt-in forms.
		$row = self::untrust( $row );

		if ( isset( $row['status'] ) && is_numeric( $row['status'] ) ) {
			$map                 = self::contact_status_map();
			$row['status']       = (int) $row['status'];
			$row['status_label'] = isset( $map[ $row['status'] ] ) ? $map[ $row['status'] ] : null;
		}

		return $row;
	}

	public function get_cart_analytics( $input = array() ) {
		$params = array();

		foreach ( array( 'after', 'before' ) as $bound ) {
			if ( empty( $input[ $bound ] ) || ! is_string( $input[ $bound ] ) ) {
				continue;
			}

			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $input[ $bound ] ) ) {
				return new WP_Error(
					'fkmcp_invalid_input',
					__( 'Dates must be Y-m-d or Y-m-d H:i:s.', 'wp-marketing-automations' ),
					array( 'field' => $bound )
				);
			}

			$params[ $bound ] = $input[ $bound ];
		}

		if ( ! empty( $params['after'] ) && ! empty( $params['before'] ) && strtotime( $params['after'] ) > strtotime( $params['before'] ) ) {
			return new WP_Error( 'fkmcp_invalid_input', __( 'after must not be later than before.', 'wp-marketing-automations' ) );
		}

		if ( ! empty( $input['interval'] ) ) {
			$params['interval'] = sanitize_key( $input['interval'] );
		}

		$response = BWFAN_MCP_Dispatch::call( 'get-cart-analytics', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();

		$totals = isset( $result['totals'] ) && is_array( $result['totals'] ) ? $result['totals'] : array();
		$totals = BWFAN_MCP_Format::pick( $totals, array( 'recoverable_carts', 'potential_revenue', 'recovered_cart', 'recovered_revenue', 'recovery_rate', 'lost_cart' ) );

		$intervals = array();
		if ( isset( $result['intervals'] ) && is_array( $result['intervals'] ) ) {
			foreach ( $result['intervals'] as $interval_row ) {
				if ( ! is_array( $interval_row ) ) {
					continue;
				}

				$trimmed              = BWFAN_MCP_Format::pick( $interval_row, array( 'interval', 'start_date', 'end_date' ) );
				$subtotals            = isset( $interval_row['subtotals'] ) && is_array( $interval_row['subtotals'] ) ? $interval_row['subtotals'] : array();
				$trimmed['subtotals'] = BWFAN_MCP_Format::pick( $subtotals, array( 'recoverable_carts', 'potential_revenue', 'recovered_cart', 'recovered_revenue', 'recovery_rate', 'lost_cart' ) );

				$intervals[] = $trimmed;
			}
		}

		return array(
			'totals'    => $totals,
			'intervals' => $intervals,
		);
	}

	public function list_automation_contacts( $input = array() ) {
		$automation_id = (int) $input['automation_id'];

		if ( array() === $this->real_automation_ids( array( $automation_id ) ) ) {
			return new WP_Error( 'fkmcp_not_found', __( 'No automation with that id.', 'wp-marketing-automations' ) );
		}

		$page = BWFAN_MCP_Format::paginate( (array) $input );

		$params = array(
			'limit'  => $page['limit'],
			'offset' => $page['offset'],
			'type'   => ! empty( $input['type'] ) && is_string( $input['type'] ) ? sanitize_key( $input['type'] ) : 'active',
		);

		if ( ! empty( $input['search'] ) ) {
			$params['search'] = sanitize_text_field( $input['search'] );
		}

		$response = BWFAN_MCP_Dispatch::call(
			'list-automation-contacts',
			$params,
			array( 'automation_id' => $automation_id )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$rows   = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		$items = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = BWFAN_MCP_Format::pick( $row, self::AUTOMATION_CONTACT_KEYS );

			if ( array_key_exists( 'trail', $item ) ) {
				$item['run_id'] = $item['trail'];
				unset( $item['trail'] );
			}

			$data                  = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
			$item['last_action']   = isset( $data['action'] ) && is_scalar( $data['action'] ) ? $data['action'] : null;
			$item['last_run_time'] = isset( $data['run_time'] ) && is_scalar( $data['run_time'] ) ? $data['run_time'] : null;

			if ( isset( $row['error_msg'] ) && is_scalar( $row['error_msg'] ) ) {
				$item['error_msg'] = $row['error_msg'];
			}

			$items[] = $item;
		}

		$count_data = isset( $result['count_data'] ) && is_array( $result['count_data'] ) ? array_filter( $result['count_data'], 'is_scalar' ) : array();

		return array(
			// Contact names and emails in the rows are visitor-typed.
			'items'  => self::untrust( $items ),
			'total'  => isset( $result['total'] ) ? (int) $result['total'] : null,
			'counts' => $count_data,
		);
	}

	public function list_merge_tags( $input = array() ) {
		if ( ! class_exists( 'BWFAN_Merge_Tag_Loader' ) ) {
			return new WP_Error( 'fkmcp_plugin_missing', __( 'FunnelKit merge tags are not available.', 'wp-marketing-automations' ) );
		}

		$all = BWFAN_Merge_Tag_Loader::get_all_merge_tags();

		$loader = function_exists( 'BWFAN_Core' ) && is_object( BWFAN_Core()->merge_tags ) ? BWFAN_Core()->merge_tags : null;
		$labels = $loader && method_exists( $loader, 'get_merge_tag_groups' ) ? (array) $loader->get_merge_tag_groups() : array();

		$search = ! empty( $input['search'] ) && is_string( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

		$tags = array();

		foreach ( (array) $all as $group => $group_tags ) {
			if ( ! is_array( $group_tags ) ) {
				continue;
			}

			$group_label = isset( $labels[ $group ] ) && is_scalar( $labels[ $group ] ) ? (string) $labels[ $group ] : (string) $group;

			foreach ( $group_tags as $slug => $instance ) {
				if ( ! is_object( $instance ) ) {
					continue;
				}

				$name        = method_exists( $instance, 'get_name' ) && is_scalar( $instance->get_name() ) ? (string) $instance->get_name() : (string) $slug;
				$description = method_exists( $instance, 'get_description' ) && is_scalar( $instance->get_description() ) ? trim( (string) $instance->get_description() ) : '';

				if ( '' !== $search
					&& false === stripos( (string) $slug, $search )
					&& false === stripos( $name, $search )
					&& false === stripos( $group_label, $search ) ) {
					continue;
				}

				$tags[] = array(
					'slug'        => (string) $slug,
					'name'        => $name,
					'group'       => $group_label,
					'description' => $description,
				);
			}
		}

		$page = BWFAN_MCP_Format::paginate( (array) $input, 50, 200 );

		return array(
			'items' => array_slice( $tags, $page['offset'], $page['limit'] ),
			'total' => count( $tags ),
		);
	}

	public function list_connectors( $input = array() ) {
		/**
		 * MCP requests don't match the REST namespaces that normally prime connector
		 * state (WFCO_Load_Connectors::load_connectors_rest_call), so without this
		 * every connector reports is_connected=false. Prime here, not in dispatch,
		 * to keep connector files lazy for the other abilities.
		 */
		if ( class_exists( 'WFCO_Common' ) ) {
			WFCO_Common::get_connectors_data();
		}
		if ( class_exists( 'WFCO_Load_Connectors' ) ) {
			WFCO_Load_Connectors::load_connectors_direct();
		}

		$response = BWFAN_MCP_Dispatch::call( 'list-connectors' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows  = isset( $response['result'] ) && is_array( $response['result'] ) ? $response['result'] : array();
		$items = array();

		foreach ( $rows as $slug => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item         = BWFAN_MCP_Format::pick( $row, self::CONNECTOR_KEYS );
			$item['slug'] = (string) $slug;

			$items[] = $item;
		}

		return array(
			'items'                    => $items,
			'total'                    => count( $items ),
			'connectors_plugin_active' => defined( 'WFCO_AUTONAMI_CONNECTORS_VERSION' ),
		);
	}

	/**
	 * get-email-settings: the six fields the dashboard's Complete Email Settings step checks,
	 * as emails use them (saved over defaults), and which the store set itself.
	 *
	 * @return array
	 */
	public function get_email_settings( $input = array() ) {
		$fields = array(
			'business_name'    => array( 'bwfan_setting_business_name', __( 'Business Name', 'wp-marketing-automations' ) ),
			'business_address' => array( 'bwfan_setting_business_address', __( 'Business Address', 'wp-marketing-automations' ) ),
			'from_name'        => array( 'bwfan_email_from_name', __( 'From Name', 'wp-marketing-automations' ) ),
			'from_email'       => array( 'bwfan_email_from', __( 'From Email', 'wp-marketing-automations' ) ),
			'reply_to'         => array( 'bwfan_email_reply_to', __( 'Reply To Email', 'wp-marketing-automations' ) ),
			'footer'           => array( 'bwfan_email_footer_setting', __( 'Footer Text', 'wp-marketing-automations' ) ),
		);
		$in_use = BWFAN_Common::get_global_settings();
		$stored = get_option( 'bwfan_global_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();

		$items   = array();
		$missing = array();
		foreach ( $fields as $field => $info ) {
			list( $key, $label ) = $info;
			$value = isset( $in_use[ $key ] ) && is_scalar( $in_use[ $key ] ) ? trim( (string) $in_use[ $key ] ) : '';
			$items[] = array(
				'field' => $field,
				'label' => $label,
				'value' => $value,
				'saved' => isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) && '' !== trim( (string) $stored[ $key ] ),
			);
			if ( '' === $value ) {
				$missing[] = $field;
			}
		}

		return array(
			'fields'       => $items,
			'missing'      => $missing,
			'site_domain'  => preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'settings_url' => BWFAN_MCP_Links::app( 'autonami', '/settings' ),
		);
	}
}
