<?php

defined( 'ABSPATH' ) || exit;

/**
 * The Automations tools as the Abilities API registers them: definitions and
 * metadata only. The handlers live in FKMCP_Pack_Lite, loaded the first time
 * one of these tools runs, so registering abilities (every MCP request,
 * tools/list) never parses them.
 */
class FKMCP_Pack_Lite_Abilities extends FKMCP_Pack {
	/** Editor type codes a Send Email step stores in bwfan_email_data.mode. */
	const EMAIL_EDITOR_MODES = array( 1 => 'rich', 2 => 'wc', 3 => 'html', 4 => 'editor', 5 => 'block' );

	/** bwfan_engagement_tracking.mode: how a message went (BWFAN_Email_Conversations::$MODE_*). */
	const ENGAGEMENT_CHANNELS = array( 1 => 'email', 2 => 'sms', 3 => 'whatsapp', 4 => 'notification' );

	/**
	 * bwfan_engagement_tracking.type: what sent it (BWFAN_Email_Conversations::$TYPE_*). A one-off
	 * message from the Contact screen is TYPE_EMAIL (4) for email, WhatsApp and notification alike,
	 * and TYPE_SMS (5) for SMS, so both read as direct; the channel comes from mode.
	 */
	const ENGAGEMENT_SOURCES = array( 1 => 'automation', 2 => 'broadcast', 3 => 'note', 4 => 'direct', 5 => 'direct', 6 => 'incentive', 9 => 'transactional' );

	/** Automations' capability vocabulary: 'read' (CRM access) or 'write' (per-user write grant). */
	public function default_capability() {
		return 'read';
	}

	public function can( $capability ) {
		return 'write' === $capability ? BWFAN_MCP_Permissions::can_write() : BWFAN_MCP_Permissions::can_read();
	}

	public function is_available() {
		return class_exists( 'BWFAN_Common' ) && defined( 'BWFAN_PLUGIN_DIR' );
	}

	/**
	 * Pack identity + slug-needle → UI group map for the settings screen.
	 * Needle order is precedence: first substring match wins across all packs.
	 */
	/** Admin URLs for the entities these tools return - see BWFAN_MCP_Links. */
	public function link_templates() {
		$templates = array(
			'contact'            => array(
				'ids'  => array( 'contact_id', 'cid', 'id' ),
				'urls' => array(
					'admin' => BWFAN_MCP_Links::app( 'autonami', '/contact/{contact_id}' ),
				),
			),
			'automation'         => array(
				'ids'  => array( 'automation_id', 'aid', 'id' ),
				'urls' => array(
					'admin'     => BWFAN_MCP_Links::app( 'autonami', '/automation/{automation_id}' ),
					'analytics' => BWFAN_MCP_Links::app( 'autonami', '/automation/{automation_id}/analytics/' ),
					'contacts'  => BWFAN_MCP_Links::app( 'autonami', '/automation/{automation_id}/contacts/' ),
				),
			),
			'cart'               => array(
				'ids'  => array( 'cart_id', 'id' ),
				'urls' => array(
					'recoverable' => BWFAN_MCP_Links::app( 'autonami', '/carts/recoverable/' ),
					'recovered'   => BWFAN_MCP_Links::app( 'autonami', '/carts/recovered/' ),
					'lost'        => BWFAN_MCP_Links::app( 'autonami', '/carts/lost/' ),
				),
				'note' => 'Abandoned carts have no per-cart page: link the list for the cart\'s status and, when contact_id is set, the contact.',
			),
			'tag'                => array(
				'ids'  => array( 'tag_id', 'ID' ),
				'urls' => array( 'admin' => BWFAN_MCP_Links::app( 'autonami', '/manage/tags' ) ),
			),
			'list'               => array(
				'ids'  => array( 'list_id', 'ID' ),
				'urls' => array( 'admin' => BWFAN_MCP_Links::app( 'autonami', '/manage/lists' ) ),
			),
			'custom_field'       => array(
				'ids'  => array( 'ID', 'slug' ),
				'urls' => array( 'admin' => BWFAN_MCP_Links::app( 'autonami', '/manage/fields' ) ),
			),
			'connector'          => array(
				'urls' => array( 'admin' => BWFAN_MCP_Links::app( 'autonami', '/connectors' ) ),
			),
			'dashboard'          => array(
				'urls' => array(
					'automations' => BWFAN_MCP_Links::app( 'autonami', '/automations' ),
					'contacts'    => BWFAN_MCP_Links::app( 'autonami', '/contacts' ),
					'crm'         => BWFAN_MCP_Links::app( 'autonami', '/analytics' ),
					'carts'       => BWFAN_MCP_Links::app( 'autonami', '/analytics/carts' ),
					'settings'    => BWFAN_MCP_Links::app( 'autonami', '/settings' ),
				),
			),
		);

		// Cart abandonment is a WooCommerce feature; its abilities are gated
		// the same way, so the links must not advertise screens that are off.
		if ( ! function_exists( 'bwfan_is_woocommerce_active' ) || ! bwfan_is_woocommerce_active() ) {
			unset( $templates['cart'], $templates['dashboard']['urls']['carts'] );
		}

		return $templates;
	}

	public function plugin_meta() {
		return array(
			'id'     => 'automations',
			'name'   => __( 'Automations', 'wp-marketing-automations' ),
			'colour' => '#0073AA',
			'groups' => array(
				'cart'         => __( 'Carts', 'wp-marketing-automations' ),
				'merge-tag'    => __( 'Emails', 'wp-marketing-automations' ),
				'email-settings' => __( 'Emails', 'wp-marketing-automations' ),
				'connector'    => __( 'Connectors', 'wp-marketing-automations' ),
				'contact'      => __( 'Contacts', 'wp-marketing-automations' ),
				'tag'          => __( 'Segments', 'wp-marketing-automations' ),
				'contact-lists' => __( 'Segments', 'wp-marketing-automations' ),
				'custom-field' => __( 'Segments', 'wp-marketing-automations' ),
				'automation'   => __( 'Automations', 'wp-marketing-automations' ),
			),
		);
	}

	public function definitions() {
		$can_read = array( 'BWFAN_MCP_Permissions', 'can_read' );
		$readonly = array( 'readonly' => true );

		// One list on every store; list_contacts() refuses the order columns without WooCommerce.
		$sort_columns = array( 'last_modified', 'creation_date', 'email', 'f_name', 'l_name', 'id', 'country', 'state', 'total_order_count', 'total_order_value', 'l_order_date' );

		$definitions = array(
			'list-contacts' => array(
				'label'       => __( 'List Contacts', 'wp-marketing-automations' ),
				'description' => 'List and search FunnelKit Contacts: id, email, name and status each. `search` matches a name or email fragment; sort with `sort_by`/`order` (newest change first). One Contact in full: get-contact. Conditions on tags, fields or activity: filter-contacts (Pro). Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'search'  => BWFAN_MCP_Schema::in_search( 'Matches email, first name or last name.' ),
							'sort_by' => BWFAN_MCP_Schema::in_enum(
								$sort_columns,
								'Column to sort by - the same choices as the Contacts screen. creation_date orders by when the Contact was added, last_modified by latest activity; total_order_count (orders), total_order_value (total spend) and l_order_date (last order date) need WooCommerce.',
								array( 'default' => 'last_modified' ),
							),
							'order'   => BWFAN_MCP_Schema::in_enum(
								array( 'asc', 'desc' ),
								'Sort direction for sort_by: asc (A→Z, oldest first) or desc (Z→A, newest first).',
								array( 'default' => 'desc' ),
							),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'id'            => BWFAN_MCP_Schema::id( 'Contact id.' ),
							'email'         => BWFAN_MCP_Schema::str( 'Email address.' ),
							'f_name'        => BWFAN_MCP_Schema::str( 'First name (visitor-entered).' ),
							'l_name'        => BWFAN_MCP_Schema::str( 'Last name (visitor-entered).' ),
							'status'        => BWFAN_MCP_Schema::int(
								'Subscription status code: 0 = unverified, 1 = subscribed, 2 = bounced, 3 = unsubscribed, 4 = soft bounced, 5 = complaint. An opted-out Contact reads 3 whatever status was stored before.'
							),
							'status_label'  => BWFAN_MCP_Schema::str( 'Human label for status.', true ),
							'contact_no'    => BWFAN_MCP_Schema::str( 'Phone number.', true ),
							'creation_date' => BWFAN_MCP_Schema::datetime( 'Contact created, Y-m-d H:i:s site time.', true ),
							'last_modified' => BWFAN_MCP_Schema::datetime( 'Last activity, Y-m-d H:i:s site time.', true ),
						),
						'A FunnelKit Contact.'
					),
					'Contacts matching the query.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_contacts' ),
				'annotations'         => $readonly,
			),

			'get-contact' => array(
				'label'       => __( 'Get Contact', 'wp-marketing-automations' ),
				'description' => 'One Contact\'s full profile: name, email, phone, subscription status (code and label), location (country, state, timezone), source, Tags, Lists, custom field values keyed by slug, WooCommerce and offer summaries, and when it was created, last active and last engaged. Pass exactly one of contact_id or email. Orders: get-contact-orders; Automations: get-contact-automations; messages: get-contact-engagements. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'contact_id' => BWFAN_MCP_Schema::in_id( 'The Contact. Give contact_id or email.' ),
						'email'      => BWFAN_MCP_Schema::in_str( 'The Contact\'s email. Give contact_id or email.', array( 'format' => 'email', 'maxLength' => 200 ) ),
					),
					// No anyOf for "one of them": tools/list drops root composition, so the
					// agent never saw it, and core's error for it named neither field. The
					// handler's refusal does.
				),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'id'            => BWFAN_MCP_Schema::id( 'Contact id.' ),
						'email'         => BWFAN_MCP_Schema::str( 'Email address.' ),
						'f_name'        => BWFAN_MCP_Schema::str( 'First name (visitor-entered).' ),
						'l_name'        => BWFAN_MCP_Schema::str( 'Last name (visitor-entered).' ),
						'status'        => BWFAN_MCP_Schema::int(
							'Subscription status code: 0 = unverified, 1 = subscribed, 2 = bounced, 3 = unsubscribed, 4 = soft bounced, 5 = complaint. An opted-out Contact reads 3 whatever status was stored before.'
						),
						'status_label'  => BWFAN_MCP_Schema::str( 'Human label for status.', true ),
						'contact_no'    => BWFAN_MCP_Schema::str( 'Phone number.', true ),
						'creation_date' => BWFAN_MCP_Schema::datetime( 'Contact created, Y-m-d H:i:s site time.', true ),
						'last_modified' => BWFAN_MCP_Schema::datetime( 'Last activity, Y-m-d H:i:s site time.', true ),
						'country'       => BWFAN_MCP_Schema::str( 'ISO country code.', true ),
						'country_formatted' => BWFAN_MCP_Schema::str( 'Country name.', true ),
						'state'         => BWFAN_MCP_Schema::str( 'State/region code.', true ),
						'timezone'      => BWFAN_MCP_Schema::str( 'IANA timezone.', true ),
						'source'        => BWFAN_MCP_Schema::str( 'Where the Contact came from, e.g. wc_order.', true ),
						'type'          => BWFAN_MCP_Schema::str( 'lead or customer.', true ),
						'unsubscribed'  => BWFAN_MCP_Schema::bool( '', true ),
						'tags'          => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::obj( array( 'id' => BWFAN_MCP_Schema::int(), 'name' => BWFAN_MCP_Schema::str() ) ), 'Tags on the Contact.' ),
						'lists'         => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::obj( array( 'id' => BWFAN_MCP_Schema::int(), 'name' => BWFAN_MCP_Schema::str() ) ), 'Lists the Contact is on.' ),
						'fields'        => BWFAN_MCP_Schema::map( BWFAN_MCP_Schema::any( 'Field value.' ), 'Custom field values keyed by field slug (id when a field has no slug); empty values omitted. Definitions: list-custom-fields.' ),
						'wc'            => BWFAN_MCP_Schema::any_obj( 'WooCommerce summary: order counts, value, AOV, first/last order dates.' ),
						'offer'         => BWFAN_MCP_Schema::any_obj( 'Funnel offer summary: upsell/bump acceptance and revenue.' ),
						'abandoned'     => BWFAN_MCP_Schema::any_obj( 'Abandoned cart summary.' ),
						'last_sent'     => BWFAN_MCP_Schema::str( 'Last email sent to the Contact, Y-m-d.', true ),
						'last_open'     => BWFAN_MCP_Schema::str( 'Last open, Y-m-d.', true ),
						'last_click'    => BWFAN_MCP_Schema::str( 'Last click, Y-m-d.', true ),
					),
					'A FunnelKit Contact.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_contact' ),
				'annotations'         => $readonly,
			),

			'get-contact-automations' => array(
				'label'       => __( 'Get Contact Automations', 'wp-marketing-automations' ),
				'description' => 'Automations a Contact has entered, with the trigger Event, current status and when it started and ended. Needs contact_id (list-contacts or get-contact). Step-by-step detail of one run: get-automation-run (Pro). Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'contact_id' => BWFAN_MCP_Schema::in_id(),
					),
					array( 'contact_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'aid'              => BWFAN_MCP_Schema::id( 'Automation id.' ),
							'automation_title' => BWFAN_MCP_Schema::str( 'Automation name.', true ),
							'status'           => BWFAN_MCP_Schema::id(
								'Automation Contact status: 1 = active, 2 = failed, 3 = paused, 4 = waiting, 5 = terminated, 6 = retry.',
								true
							),
							'c_date'           => BWFAN_MCP_Schema::datetime( 'Enrolled at.', true ),
							'e_time'           => BWFAN_MCP_Schema::datetime( 'Next/last execution time.', true ),
							'event'            => BWFAN_MCP_Schema::str( 'Trigger event slug.', true ),
						)
					),
					'Automations the Contact is or was enrolled in.',
					array(
						'total' => BWFAN_MCP_Schema::int( 'Total matching rows, ignoring pagination; null when the source does not report one.', true ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_contact_automations' ),
				'annotations'         => $readonly,
			),

			'get-contact-orders' => array(
				'label'       => __( 'Get Contact Orders', 'wp-marketing-automations' ),
				'description' => 'WooCommerce Orders placed by one Contact: order id, status, currency, order total, product count and product names. Paged with limit/offset. Line-level Funnel attribution of an order: get-funnel-order. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'contact_id' => BWFAN_MCP_Schema::in_id(),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
					array( 'contact_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'order_id'      => BWFAN_MCP_Schema::id( 'WooCommerce order id.', true ),
							'status'        => BWFAN_MCP_Schema::str( 'Order status label.', true ),
							'currency'      => BWFAN_MCP_Schema::str( 'Currency code.', true ),
							'order_total'   => BWFAN_MCP_Schema::num( 'Order total in the order currency, after discounts, shipping and tax.', true ),
							'product_count' => BWFAN_MCP_Schema::int( 'Line items.' ),
							'product_names' => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::str( 'Product name.' ) ),
						)
					),
					'Orders FunnelKit records for the Contact.',
					array(
						'total' => BWFAN_MCP_Schema::int( 'Total matching rows, ignoring pagination; null when the source does not report one.', true ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_contact_orders' ),
				'annotations'         => $readonly,
				'available'           => 'bwfan_is_woocommerce_active',
			),

			'get-contact-engagements' => array(
				'label'       => __( 'Get Contact Engagements', 'wp-marketing-automations' ),
				'description' => 'Emails and SMS sent to one Contact: subject, channel (email, sms, whatsapp), where it came from (source: automation, broadcast, direct - a one-off from the Contact screen -, note, incentive, transactional, with source_id - the automation or broadcast id), send time and open/click counts - the answer to "what did we send this person". The message body and the address are not returned. Paged with limit/offset. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'contact_id' => BWFAN_MCP_Schema::in_id(),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
					array( 'contact_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'ID'         => BWFAN_MCP_Schema::id( 'Engagement row id.' ),
							'subject'    => BWFAN_MCP_Schema::str( 'Message subject.', true ),
							'channel'    => BWFAN_MCP_Schema::enum( array_values( self::ENGAGEMENT_CHANNELS ), 'How it was sent; null for a code this list does not know (see mode).', true ),
							'mode'       => BWFAN_MCP_Schema::id( 'Channel code: 1 email, 2 SMS, 3 WhatsApp, 4 notification.', true ),
							'source'     => BWFAN_MCP_Schema::enum( array_values( array_unique( self::ENGAGEMENT_SOURCES ) ), 'What sent it; null for a code this list does not know (see type).', true ),
							'type'       => BWFAN_MCP_Schema::id( 'Source code: 1 automation, 2 broadcast, 3 note, 4 one-off (email, WhatsApp or notification), 5 one-off SMS, 6 incentive, 9 transactional.', true ),
							'source_id'  => BWFAN_MCP_Schema::id( 'The automation or broadcast id it came from; null when there is none.', true ),
							'created_at' => BWFAN_MCP_Schema::datetime( 'Sent at, Y-m-d H:i:s site time.', true ),
							'open'       => BWFAN_MCP_Schema::num( 'Open count.', true ),
							'click'      => BWFAN_MCP_Schema::num( 'Click count.', true ),
						)
					),
					'Messages sent to the Contact.',
					array(
						'total' => BWFAN_MCP_Schema::int( 'Total matching rows, ignoring pagination; null when the source does not report one.', true ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_contact_engagements' ),
				'annotations'         => $readonly,
			),

			'list-automations' => array(
				'label'       => __( 'List Automations', 'wp-marketing-automations' ),
				'description' => 'FunnelKit Automations with status, trigger Event, source, conversions and revenue. Filter by status or `search` on the name. Contact-state counts: get-automation-stats; one Automation\'s Steps: get-automation; who is inside one: list-automation-contacts. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'status' => BWFAN_MCP_Schema::in_int(
								'Automation status: 1 = active, 2 = inactive. This install has no draft status - confirmed against every row in the Automations table.',
								array( 'enum' => array( 1, 2 ) ),
							),
							'search' => BWFAN_MCP_Schema::in_search(),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'id'          => BWFAN_MCP_Schema::id( 'Automation id.' ),
							'name'        => BWFAN_MCP_Schema::str( 'Automation name.' ),
							'status'      => BWFAN_MCP_Schema::id( '1 = active, 2 = inactive.' ),
							'event'       => BWFAN_MCP_Schema::str( 'Trigger event label.', true ),
							'source'      => BWFAN_MCP_Schema::str( 'Event source (plugin).', true ),
							'priority'    => BWFAN_MCP_Schema::id( 'Run priority.', true ),
							'conversions' => BWFAN_MCP_Schema::num( 'Attributed orders.', true ),
							'revenue'     => BWFAN_MCP_Schema::num( 'Attributed revenue, store currency.', true ),
							'last_update' => BWFAN_MCP_Schema::str( 'Last modified, may be empty.', true ),
							'v'           => BWFAN_MCP_Schema::id( 'Engine version: 1 = legacy, 2 = current.', true ),
						)
					),
					'Automations.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_automations' ),
				'annotations'         => $readonly,
			),

			'get-automation' => array(
				'label'       => __( 'Get Automation', 'wp-marketing-automations' ),
				'description' => 'One Automation in full: title, status, trigger Event and every Step in order (actions, delays, conditions, splits) with its settings. Send Email steps also carry `email`: subject, preheader and which editor made it (not the body). Find the id with list-automations. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'automation_id' => BWFAN_MCP_Schema::in_id(),
					),
					array( 'automation_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'ID'       => BWFAN_MCP_Schema::id( 'Automation id.' ),
						'title'    => BWFAN_MCP_Schema::str( 'Automation name.' ),
						'status'   => BWFAN_MCP_Schema::id( '1 = active, 2 = inactive.' ),
						'event'    => BWFAN_MCP_Schema::str( 'Trigger event slug.', true ),
						'source'   => BWFAN_MCP_Schema::str( 'Event source slug.', true ),
						'priority' => BWFAN_MCP_Schema::id( 'Run priority.', true ),
						'category' => BWFAN_MCP_Schema::str( 'Category.', true ),
						'v'        => BWFAN_MCP_Schema::id( 'Engine version.', true ),
						'revision' => BWFAN_MCP_Schema::str( 'Next Gen only: pass it to update-automation. It changes whenever the Automation is edited.', true ),
						'steps'    => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'id'          => BWFAN_MCP_Schema::str( 'Step id ("start" for the trigger).' ),
									'type'        => BWFAN_MCP_Schema::str( 'Step type: start, action, delay, conditional, goal, ...' ),
									'step_status' => BWFAN_MCP_Schema::any( 'Step status if any.' ),
									'action'      => BWFAN_MCP_Schema::str( 'Action slug for action steps.', true ),
									'description' => BWFAN_MCP_Schema::str( 'Step note.', true ),
									'path'        => BWFAN_MCP_Schema::str( 'Where the step sits in an update-automation definition, e.g. steps[2].yes[0]; absent when it cannot be placed.', true ),
									'definition_type' => BWFAN_MCP_Schema::str( 'The definition step type: email, action, wait, condition, branches, split, goal, jump or exit.', true ),
									'template_ref' => array_merge(
										BWFAN_MCP_Schema::obj(
											array(
												'id'       => BWFAN_MCP_Schema::int( 'The saved email template\'s id: its row in the email templates table (bwfan_templates), as list-email-templates and get-email-template use it.' ),
												'revision' => BWFAN_MCP_Schema::str( 'The template\'s revision this email was copied from.' ),
											),
											'The saved email template (a bwfan_templates row) this email was copied from, when it has one: any email copied from a saved template carries it, whatever its format.'
										),
										array( 'type' => array( 'object', 'null' ) )
									),
									'email'       => BWFAN_MCP_Schema::obj(
										array(
											'subject'    => BWFAN_MCP_Schema::str( 'Email subject; may contain {{merge_tags}}.' ),
											'preheader'  => BWFAN_MCP_Schema::str( 'Preview text shown after the subject in the inbox.' ),
											'mode'       => BWFAN_MCP_Schema::int( 'Editor code: 1 rich text, 2 WooCommerce template, 3 raw HTML, 4 legacy visual builder, 5 block editor.', true ),
											'mode_label' => BWFAN_MCP_Schema::enum( array_values( self::EMAIL_EDITOR_MODES ), 'Editor as a word; null for an unknown code.', true ),
											'has_design' => BWFAN_MCP_Schema::bool( 'true: the step stores an editable design (block editor or legacy builder) beside its HTML.' ),
											'has_html'   => BWFAN_MCP_Schema::bool( 'false: the step has no rendered email body, so nothing would be sent.' ),
										),
										'Send Email steps only (action wp_sendemail): what the email is, without its body.'
									),
									'settings'    => BWFAN_MCP_Schema::any( 'What the step does, as saved: condition rules [[{rule, operator, value}]] (outer list OR, inner AND), branches [{name, rules}] and has_else, split {paths, title, desc}, the wait, the goal {goal, settings} or the action\'s fields. Secret-looking fields are left out; Send Email steps use `email` instead. null when cut (see truncated).' ),
									'label'       => BWFAN_MCP_Schema::str( 'Branch, split path or yes/no row: its name (B1, Else, Path 2, Yes, No).', true ),
									'truncated'   => BWFAN_MCP_Schema::bool( 'true: settings were longer than 4 KB and left out.' ),
								)
							),
							'Steps in flow order.'
						),
						'definition' => BWFAN_MCP_Schema::any( 'With Pro: this Automation as the definition update-automation takes ({trigger, steps}, lanes nested; emails as {type: email, keep: true}). Edit it and send it back with revision. null when a step can\'t be expressed: see definition_unavailable.' ),
						'definition_unavailable' => BWFAN_MCP_Schema::any( 'With Pro, when definition is null: [{path, reason}] for each step that could not be read back.' ),
					),
					'One Automation with its steps.',
					array( 'ID', 'title' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_automation' ),
				'annotations'         => $readonly,
			),

			'get-automation-stats' => array(
				'label'       => __( 'Get Automation Stats', 'wp-marketing-automations' ),
				'description' => 'Contact-state counts (active, complete, failed, paused, inactive) for up to 100 Automations - a switched-off Automation reports every Contact as inactive, exactly as list-automation-contacts counts them; Automations on the older engine report zeros. truncated=true means total_automations exceeds what was covered. Pass automation_ids (from list-automations) for specific ones; ids that are not Automations come back in unknown_ids rather than as zeros. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'automation_ids' => BWFAN_MCP_Schema::in_arr(
							BWFAN_MCP_Schema::in_id(),
							'Specific Automation ids to get stats for, instead of the default first-100 aggregate.',
							array( 'maxItems' => 100 ),
						),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'items'               => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'automation_id'     => BWFAN_MCP_Schema::int( 'Automation id.' ),
									'automation_active' => BWFAN_MCP_Schema::bool( 'false: the Automation is switched off; its Contacts all count as inactive.' ),
									'active'            => BWFAN_MCP_Schema::int( 'Contacts currently in the flow (running, waiting or retrying).' ),
									'complete'          => BWFAN_MCP_Schema::int( 'Contacts that finished.' ),
									'failed'            => BWFAN_MCP_Schema::int( 'Contacts whose run failed.' ),
									'paused'            => BWFAN_MCP_Schema::int( 'Contacts paused.' ),
									'inactive'          => BWFAN_MCP_Schema::int( 'Contacts held in a switched-off Automation, whatever step they were at; 0 while it is on.' ),
								)
							)
						),
						'total_automations'   => BWFAN_MCP_Schema::int( 'Total FunnelKit Automations.' ),
						'covered_automations' => BWFAN_MCP_Schema::int( 'Automations included in items.' ),
						'truncated'           => BWFAN_MCP_Schema::bool( 'true: items cover only part of total_automations.' ),
						'unknown_ids'         => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::int( 'Requested id that is not an Automation.' ) ),
					),
					'Contact-state counts per Automation.',
					array( 'items', 'truncated' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_automation_stats' ),
				'annotations'         => $readonly,
			),

			'list-tags' => array(
				'label'       => __( 'List Tags', 'wp-marketing-automations' ),

				'description' => 'Every FunnelKit Tag with its id, name and creation date; `search` matches the name. Use it to turn a Tag name into the id that filter-contacts, create-contact and manage-contact-tags-lists take. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'search' => BWFAN_MCP_Schema::in_search(),
						),
						BWFAN_MCP_Schema::in_paging( 100, 50 ),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'ID'         => BWFAN_MCP_Schema::id( 'Tag id.' ),
							'name'       => BWFAN_MCP_Schema::str( 'Tag name.' ),
							'type'       => BWFAN_MCP_Schema::id( 'Term type code.', true ),
							'created_at' => BWFAN_MCP_Schema::datetime( 'Created.', true ),
							'updated_at' => BWFAN_MCP_Schema::datetime( 'Updated.', true ),
						)
					),
					'Tags.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_tags' ),
				'annotations'         => $readonly,
			),

			'list-contact-lists' => array(
				'label'       => __( 'List Contact Lists', 'wp-marketing-automations' ),
				'description' => 'Every FunnelKit contact List with its id, name, description and creation date; `search` matches the name. Use it to turn a List name into the id that filter-contacts, create-contact and manage-contact-tags-lists take. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'search' => BWFAN_MCP_Schema::in_search(),
						),
						BWFAN_MCP_Schema::in_paging( 100, 50 ),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'ID'          => BWFAN_MCP_Schema::id( 'List id.' ),
							'name'        => BWFAN_MCP_Schema::str( 'List name.' ),
							'type'        => BWFAN_MCP_Schema::id( 'Term type code.', true ),
							'created_at'  => BWFAN_MCP_Schema::datetime( 'Created.', true ),
							'updated_at'  => BWFAN_MCP_Schema::datetime( 'Updated.', true ),
							'description' => BWFAN_MCP_Schema::str( 'List description.', true ),
						)
					),
					'Lists.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_contact_lists' ),
				'annotations'         => $readonly,
			),

			'list-custom-fields' => array(
				'label'       => __( 'List Custom Fields', 'wp-marketing-automations' ),
				'description' => 'Every FunnelKit custom Contact field: slug, name, type, group and, for choice fields, the allowed options. Consult it before reading or writing field values (create-contact / update-contact take fields by slug). Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input( array() ),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'items' => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'ID'          => BWFAN_MCP_Schema::id( 'Field id or slug (built-in fields use the slug).' ),
									'name'        => BWFAN_MCP_Schema::str( 'Field label.' ),
									'slug'        => BWFAN_MCP_Schema::str( 'Field slug used as the key in write tools.', true ),
									'type'        => BWFAN_MCP_Schema::int(
										'Field type: 1 = text, 2 = number, 3 = date, 4 = select, 5 = radio, 6 = checkbox, 7 = textarea, 8 = email, 9 = phone.'
									),
									'group_id'    => BWFAN_MCP_Schema::int( 'Field group id.', true ),
									'options'     => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::any( 'Choice for select/radio/checkbox fields.' ) ),
									'placeholder' => BWFAN_MCP_Schema::str( 'Placeholder text.', true ),
								)
							)
						),
					),
					'Custom contact fields.',
					array( 'items' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_custom_fields' ),
				'annotations'         => $readonly,
			),

			'list-abandoned-carts' => array(
				'label'       => __( 'List Abandoned Carts', 'wp-marketing-automations' ),
				'description' => 'Carts shoppers left at checkout, with cart total, the Contact and how long ago, in the bucket you ask for. status = recoverable (default; FunnelKit can still email these), recovered (became an Order) or lost (past the recovery window). `search` matches an email fragment; after/before (Y-m-d) filter by capture date; country (2-letter) and has_phone need a date window. after/before, country and has_phone do not apply to recovered (those carts became Orders): for recovered carts over a date range use get-cart-analytics. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'status'    => BWFAN_MCP_Schema::in_enum( array( 'recoverable', 'recovered', 'lost' ), '', array( 'default' => 'recoverable' ) ),
							'search'    => BWFAN_MCP_Schema::in_search( 'Matches the Cart email.' ),
							'after'     => BWFAN_MCP_Schema::in_date( 'Carts created on or after this day. ' . BWFAN_MCP_Schema::DAY_FORMAT ),
							'before'    => BWFAN_MCP_Schema::in_date( 'Carts created on or before this day. ' . BWFAN_MCP_Schema::DAY_FORMAT ),
							'country'   => BWFAN_MCP_Schema::in_str( 'Billing country captured at checkout, e.g. IN, US.', array( 'pattern' => '^[A-Za-z]{2}$' ) ),
							'has_phone' => BWFAN_MCP_Schema::in_bool(),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'id'            => BWFAN_MCP_Schema::id( 'Cart id.' ),
							'email'         => BWFAN_MCP_Schema::str( 'Checkout email.' ),
							'phone'         => BWFAN_MCP_Schema::str( 'Billing phone.', true ),
							'f_name'        => BWFAN_MCP_Schema::str( 'Billing first name.', true ),
							'l_name'        => BWFAN_MCP_Schema::str( 'Billing last name.', true ),
							'status'        => BWFAN_MCP_Schema::id( 'Cart status code: 0/1/3/4/5 recoverable, 2 lost.' ),
							'date'          => BWFAN_MCP_Schema::datetime( 'Captured at.', true ),
							'created_on'    => BWFAN_MCP_Schema::datetime( 'Captured at.', true ),
							'diffstring'    => BWFAN_MCP_Schema::str( 'Human age, e.g. "23 hours ago".', true ),
							'total'         => BWFAN_MCP_Schema::num( 'Cart total, store currency.' ),
							'buyer_name'    => BWFAN_MCP_Schema::str( 'Name when known.', true ),
							'order_id'      => BWFAN_MCP_Schema::id( 'Order id once recovered, else empty.', true ),
							'user_id'       => BWFAN_MCP_Schema::id( 'WordPress user id, 0 for guests.', true ),
							'currency'      => BWFAN_MCP_Schema::str( 'Currency code.' ),
							'product_names' => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::str( 'Product name.' ) ),
						)
					),
					'Abandoned carts in the selected bucket.',
					array(
						'status' => BWFAN_MCP_Schema::enum( array( 'recoverable', 'recovered', 'lost' ), 'Bucket returned.' ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_abandoned_carts' ),
				'annotations'         => $readonly,
				'available'           => 'bwfan_is_woocommerce_active',
			),

			'get-abandoned-cart' => array(
				'label'       => __( 'Get Abandoned Cart', 'wp-marketing-automations' ),
				'description' => 'One Abandoned Cart by id: line items with quantities and totals, coupons, status, linked Order, and the billing country/phone captured at checkout. Find ids with list-abandoned-carts. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'cart_id' => BWFAN_MCP_Schema::in_id(),
					),
					array( 'cart_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'ID'            => BWFAN_MCP_Schema::id( 'Cart id.' ),
						'email'         => BWFAN_MCP_Schema::str( 'Checkout email.' ),
						'status'        => BWFAN_MCP_Schema::id( 'Cart status code.' ),
						'status_label'  => BWFAN_MCP_Schema::str( 'recoverable / lost / recovered.', true ),
						'created_time'  => BWFAN_MCP_Schema::datetime( 'Captured at.', true ),
						'last_modified' => BWFAN_MCP_Schema::datetime( 'Last update.', true ),
						'total'         => BWFAN_MCP_Schema::num( 'Cart total, store currency.' ),
						'total_base'    => BWFAN_MCP_Schema::num( 'Total in base currency.', true ),
						'currency'      => BWFAN_MCP_Schema::str( 'Currency code.' ),
						'order_id'      => BWFAN_MCP_Schema::id( 'Linked order id, 0 when none.', true ),
						'user_id'       => BWFAN_MCP_Schema::id( 'WordPress user id, 0 for guests.', true ),
						'items'         => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'product_id'   => BWFAN_MCP_Schema::int(  ),
									'product_name' => BWFAN_MCP_Schema::str(  ),
									'variation_id' => BWFAN_MCP_Schema::int( '0 when not a variation.' ),
									'quantity'     => BWFAN_MCP_Schema::int(  ),
									'line_total'   => BWFAN_MCP_Schema::num( 'Line total, store currency; 0 for a free gift.' ),
									'line_tax'     => BWFAN_MCP_Schema::num( 'Line tax; items plus tax reconcile to the cart total.', true ),
									'free_gift'    => BWFAN_MCP_Schema::bool( 'Present when the item is a Cart free gift, so its 0 total is real.' ),
								)
							)
						),
						'coupons'       => BWFAN_MCP_Schema::any( 'Coupon codes applied.' ),
						'billing'       => BWFAN_MCP_Schema::map(
							BWFAN_MCP_Schema::str( 'Billing field value as typed at checkout.', true ),
							'billing_* fields captured at checkout.'
						),
					),
					'One abandoned cart.',
					array( 'ID' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_abandoned_cart' ),
				'annotations'         => $readonly,
				'available'           => 'bwfan_is_woocommerce_active',
			),

			'get-cart-analytics' => array(
				'label'       => __( 'Get Cart Analytics', 'wp-marketing-automations' ),
				'description' => 'Abandoned Cart recovery performance: recoverable/recovered/lost counts, potential and recovered revenue, and the recovery rate, plus a per-interval breakdown. Defaults to the last week. Dates are Y-m-d or Y-m-d H:i:s in the site timezone. At most 92 buckets: a window too long for the asked interval comes back by the next coarser one, named in interval_used and interval_note. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array(
						'after'    => BWFAN_MCP_Schema::in_str(
							'Start of the window, Y-m-d or Y-m-d H:i:s, site timezone, e.g. 2026-10-01.',
							array(
								'maxLength' => 19,
								'pattern'   => '^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}:\\d{2})?$',
							),
						),
						'before'   => BWFAN_MCP_Schema::in_str(
							'End of the window, Y-m-d or Y-m-d H:i:s, site timezone, e.g. 2026-10-31.',
							array(
								'maxLength' => 19,
								'pattern'   => '^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}:\\d{2})?$',
							),
						),
						'interval' => BWFAN_MCP_Schema::in_enum( array( 'day', 'week', 'month', 'quarter', 'year' ), '', array( 'default' => 'week' ) ),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'totals'    => BWFAN_MCP_Schema::obj(
							array(
								'recoverable_carts' => BWFAN_MCP_Schema::num( 'Carts FunnelKit can still recover.' ),
								'potential_revenue' => BWFAN_MCP_Schema::num( 'Value of recoverable carts, store currency.' ),
								'recovered_cart'    => BWFAN_MCP_Schema::num( 'Carts converted to orders.' ),
								'recovered_revenue' => BWFAN_MCP_Schema::num( 'Revenue from recovered carts.' ),
								'recovery_rate'     => BWFAN_MCP_Schema::num( 'Recovered / (recovered + lost), percent.' ),
								'lost_cart'         => BWFAN_MCP_Schema::num( 'Carts past the recovery window.' ),
							)
						),
						'intervals' => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'interval'   => BWFAN_MCP_Schema::str( 'Bucket key, e.g. 2026-34 (ISO week) or 2026-08.' ),
									'start_date' => BWFAN_MCP_Schema::datetime( 'Bucket start.', true ),
									'end_date'   => BWFAN_MCP_Schema::datetime( 'Bucket end.', true ),
									'subtotals'  => BWFAN_MCP_Schema::obj(
										array(
											'recoverable_carts' => BWFAN_MCP_Schema::num( 'Carts FunnelKit can still recover.' ),
											'potential_revenue' => BWFAN_MCP_Schema::num( 'Value of recoverable carts, store currency.' ),
											'recovered_cart'    => BWFAN_MCP_Schema::num( 'Carts converted to orders.' ),
											'recovered_revenue' => BWFAN_MCP_Schema::num( 'Revenue from recovered carts.' ),
											'recovery_rate'     => BWFAN_MCP_Schema::num( 'Recovered / (recovered + lost), percent.' ),
											'lost_cart'         => BWFAN_MCP_Schema::num( 'Carts past the recovery window.' ),
										)
									),
								)
							)
						),
						'interval_used' => BWFAN_MCP_Schema::str( 'The bucket the intervals are by.' ),
						'interval_note' => BWFAN_MCP_Schema::str( 'Set when a coarser bucket than asked was used, because the window is too long; null otherwise.', true ),
					),
					'Cart recovery performance.',
					array( 'totals' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_cart_analytics' ),
				'annotations'         => $readonly,
				'available'           => 'bwfan_is_woocommerce_active',
			),

			'list-automation-contacts' => array(
				'label'       => __( 'List Automation Contacts', 'wp-marketing-automations' ),
				'description' => 'The Contacts inside one Automation, filtered by their state: active (default), paused, failed, completed, delayed, or inactive (held in a switched-off Automation). Also returns per-state counts for the whole Automation. Use this to answer "who is stuck in this flow". Find an automation_id with list-automations. Each row carries run_id - fetch that Contact\'s step-by-step journey with get-automation-run (Pro). Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'automation_id' => BWFAN_MCP_Schema::in_id(),
							'type'          => BWFAN_MCP_Schema::in_enum(
								array( 'active', 'paused', 'failed', 'completed', 'delayed', 'inactive' ),
								'',
								array( 'default' => 'active' ),
							),
							'search'        => BWFAN_MCP_Schema::in_search(),
						),
						BWFAN_MCP_Schema::in_paging(),
					),
					array( 'automation_id' ),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'ID'            => BWFAN_MCP_Schema::id( 'Automation Contact row id.' ),
							'cid'           => BWFAN_MCP_Schema::id( 'Contact id.' ),
							'aid'           => BWFAN_MCP_Schema::id( 'Automation id.' ),
							'run_id'        => BWFAN_MCP_Schema::str( 'Run id for get-automation-run.', true ),
							'email'         => BWFAN_MCP_Schema::str(  ),
							'f_name'        => BWFAN_MCP_Schema::str( '', true ),
							'l_name'        => BWFAN_MCP_Schema::str( '', true ),
							'contact_no'    => BWFAN_MCP_Schema::str( '', true ),
							'c_date'        => BWFAN_MCP_Schema::datetime( 'Entered the Automation.', true ),
							'e_time'        => BWFAN_MCP_Schema::datetime( 'Next scheduled execution.', true ),
							'last_action'   => BWFAN_MCP_Schema::str( 'Last step/action executed.', true ),
							'last_run_time' => BWFAN_MCP_Schema::id( 'Last run timestamp.', true ),
							'error_msg'     => BWFAN_MCP_Schema::str( 'Failure message when failed.', true ),
						)
					),
					'Contacts in one Automation state.',
					array(
						'total'  => BWFAN_MCP_Schema::int( 'Total matching rows, ignoring pagination; null when the source does not report one.', true ),
						'counts' => BWFAN_MCP_Schema::map(
							BWFAN_MCP_Schema::num( 'Contacts in that state.' ),
							'Per-state counts for the whole Automation, as get-automation-stats counts them; inactive = every Contact of a switched-off Automation.'
						),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_automation_contacts' ),
				'annotations'         => $readonly,
			),

			'list-merge-tags' => array(
				'label'       => __( 'List Merge Tags', 'wp-marketing-automations' ),
				'description' => 'The Merge Tags available in FunnelKit email and SMS templates, grouped (Contact, Order, Abandoned Cart, ...). Use this before writing template content so only real Tags are referenced. Use `search` for a Tag or group fragment. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input(
					array_merge(
						array(
							'search' => BWFAN_MCP_Schema::in_search(),
						),
						BWFAN_MCP_Schema::in_paging( 200, 50 ),
					),
				),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'slug'        => BWFAN_MCP_Schema::str( 'Internal id of the tag. Not what goes in an email: use name.' ),
							'name'        => BWFAN_MCP_Schema::str( 'The tag as written in an email: {{name}}, e.g. {{contact_first_name}}.' ),
							'group'       => BWFAN_MCP_Schema::str( 'Group label.' ),
							'description' => BWFAN_MCP_Schema::str( 'What it renders.', true ),
						)
					),
					'Merge tags.'
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_merge_tags' ),
				'annotations'         => $readonly,
			),

			'list-connectors' => array(
				'label'       => __( 'List Connectors', 'wp-marketing-automations' ),
				'description' => 'Third-party services FunnelKit can talk to (email providers, CRMs, SMS, forms ...) with a description and whether each is connected; connectors_plugin_active says if the FunnelKit Connectors plugin is installed. Credentials are never returned. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input( array() ),
				'output_schema' => BWFAN_MCP_Schema::paged(
					BWFAN_MCP_Schema::obj(
						array(
							'name'         => BWFAN_MCP_Schema::str( 'Connector name.' ),
							'description'  => BWFAN_MCP_Schema::str( '', true ),
							'is_connected' => BWFAN_MCP_Schema::bool( 'Credentials configured.' ),
							'slug'         => BWFAN_MCP_Schema::str( 'Connector slug.' ),
						)
					),
					'Third-party connectors.',
					array(
						'connectors_plugin_active' => BWFAN_MCP_Schema::bool( 'FunnelKit Connectors plugin active.' ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'list_connectors' ),
				'annotations'         => $readonly,
			),

			'get-email-settings' => array(
				'label'       => __( 'Get Email Settings', 'wp-marketing-automations' ),
				'description' => 'The sender details and footer every email from this store uses - the six fields the dashboard\'s Complete Email Settings step checks: business_name, business_address, from_name, from_email, reply_to, footer. value is what emails use now (From falls back to the WooCommerce or admin email when none is saved); saved says whether the store set it; missing lists the empty ones. A From address off site_domain or on a free mailbox tends to land in spam; the footer needs {{unsubscribe_link}} and the business address (anti-spam law). Bounce handling, sending limits and the email provider are not included. To change them: update-email-settings (Pro), or settings_url. Read-only.',
				'input_schema' => BWFAN_MCP_Schema::input( array() ),
				'output_schema' => BWFAN_MCP_Schema::obj(
					array(
						'fields'       => BWFAN_MCP_Schema::arr(
							BWFAN_MCP_Schema::obj(
								array(
									'field' => BWFAN_MCP_Schema::str( 'Field name, as update-email-settings takes it.' ),
									'label' => BWFAN_MCP_Schema::str( 'As the Settings screen names it.' ),
									'value' => BWFAN_MCP_Schema::str( 'What emails use now; footer is HTML.' ),
									'saved' => BWFAN_MCP_Schema::bool( 'false: not set by the store (empty, or a default).' ),
								)
							),
							'The six fields, in Settings order.'
						),
						'missing'      => BWFAN_MCP_Schema::arr( BWFAN_MCP_Schema::str(), 'Fields with no value: the dashboard step stays open until these are set.' ),
						'site_domain'  => BWFAN_MCP_Schema::str( 'The store\'s domain, to compare with from_email.' ),
						'settings_url' => BWFAN_MCP_Schema::str( 'The Settings screen.' ),
					)
				),
				'permission_callback' => $can_read,
				'execute_callback'    => $this->handler( 'get_email_settings' ),
				'annotations'         => $readonly,
			),
		);

		return $definitions;
	}

	public static function contact_status_map() {
		$map = array();

		if ( class_exists( 'BWFAN_Common' ) && method_exists( 'BWFAN_Common', 'get_contact_status_array_list' ) ) {
			foreach ( (array) BWFAN_Common::get_contact_status_array_list() as $status ) {
				if ( is_array( $status ) && isset( $status['id'], $status['name'] ) && is_scalar( $status['name'] ) ) {
					$map[ (int) $status['id'] ] = (string) $status['name'];
				}
			}
		}

		if ( empty( $map ) ) {
			$map = array( 0 => 'Unverified', 1 => 'Subscribed', 2 => 'Bounced', 3 => 'Unsubscribed', 4 => 'Soft Bounced', 5 => 'Complaint' );
		}

		return $map;
	}

	/** @var FKMCP_Pack_Lite|null The handler pack, built on the first tool call. */
	private $handlers = null;

	/**
	 * A tool's execute_callback. The handler pack itself (and any subclass a
	 * test builds) keeps direct callbacks, so overrides still apply; the light
	 * pack defers to the handler pack, loaded on first use.
	 *
	 * @param string $method Handler method on FKMCP_Pack_Lite.
	 *
	 * @return callable
	 */
	protected function handler( $method ) {
		if ( method_exists( $this, $method ) ) {
			return array( $this, $method );
		}

		return function () use ( $method ) {
			return call_user_func_array( array( $this->handlers(), $method ), func_get_args() );
		};
	}

	/** @return FKMCP_Pack_Lite The handler pack: one per registered pack, so one per request. */
	public function handlers() {
		if ( $this instanceof FKMCP_Pack_Lite ) { // already the handler pack: no second instance
			return $this;
		}

		if ( null === $this->handlers ) {
			require_once __DIR__ . '/class-fkmcp-pack-lite.php';
			$this->handlers = new FKMCP_Pack_Lite();
		}

		return $this->handlers;
	}
}

// Anything else that names the handler pack - an older Pro's class_exists( 'FKMCP_Pack_Lite' ),
// FKMCP_Pack_Lite::CONTACT_KEYS - loads it instead of failing. Exact name only.
spl_autoload_register(
	static function ( $class ) {
		if ( 'FKMCP_Pack_Lite' === $class ) {
			require_once __DIR__ . '/class-fkmcp-pack-lite.php';
		}
	}
);
