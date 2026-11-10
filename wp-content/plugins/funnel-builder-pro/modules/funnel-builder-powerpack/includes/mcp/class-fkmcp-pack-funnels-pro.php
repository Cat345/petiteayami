<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder Pro's tools: funnel/step analytics, the dashboard reads,
 * A/B Tests, Order Bumps, Upsell Offers and display rules - reads and
 * writes. They live in the Pro plugin so the free plugin neither ships nor
 * describes them: with Pro absent they do not exist; with Pro present but
 * not validly activated they stay registered and answer with an upgrade
 * notice instead of data (WFFN_Pro_MCP::gate).
 *
 * Language: ability labels are translated because the settings screen lists
 * them; descriptions, schema text and the errors an ability answers with stay
 * in English, because they are the tool spec a model reasons over. The paywall
 * message is translated - a client repeats it to the person who has to renew.
 * See the module README.
 */
class FKMCP_Pack_Funnels_Pro extends FKMCP_Pack {

	/** Funnel Builder's capability vocabulary: array( 'funnel'|'analytics'|'menu', 'read'|'write' ). */
	public function default_capability() {
		return array( 'funnel', 'read' );
	}

	/**
	 * @param mixed $capability
	 *
	 * @return mixed
	 */
	public function can( $capability ) {
		list( $cap, $access ) = array_pad( (array) $capability, 2, 'read' );

		return WFFN_MCP_Permissions::can_access( $cap, $access );
	}

	/**
	 * @return mixed
	 */
	public function is_available() {
		return defined( 'WFFN_PRO_VERSION' ) && class_exists( 'FKMCP_Pack_Funnels' ) && class_exists( 'WFFN_Funnel' );
	}

	/**
	 * @return mixed
	 */
	public function plugin_meta() {
		return array(
			'id'     => 'funnel-builder-pro',
			'pro'    => true,
			'name'   => __( 'Funnel Builder Pro', 'funnel-builder-powerpack' ),
			'colour' => '#F5841F',
			'groups' => array(
				'ab-test'   => __( 'A/B Tests', 'funnel-builder-powerpack' ),
				'dashboard' => __( 'Analytics', 'funnel-builder-powerpack' ),
				'revenue'   => __( 'Analytics', 'funnel-builder-powerpack' ),
				'payment'   => __( 'Checkout', 'funnel-builder-powerpack' ),
				'bump'      => __( 'Steps', 'funnel-builder-powerpack' ),
				'thankyou'  => __( 'Steps', 'funnel-builder-powerpack' ),
				'upsell'    => __( 'Upsells', 'funnel-builder-powerpack' ),
				'offer'     => __( 'Upsells', 'funnel-builder-powerpack' ),
			),
		);
	}

	/** Admin URLs for the Pro entities - see WFFN_MCP_Links. */
	public function link_templates() {
		return array(
			'ab_test' => array(
				'ids'  => array( 'experiment_id' ),
				'urls' => array(
					'admin' => WFFN_MCP_Links::app( 'bwf', '/funnels/{funnel_id}/experiments/{control_step_id}' ),
				),
				'note' => 'control_step_id is the variant flagged control in get-ab-test; funnel_id is that step\'s funnel.',
			),
		);
	}

	/**
	 * @return mixed
	 */
	public function definitions() {
		if ( null !== $this->definitions ) {
			return $this->definitions;
		}

		$this->definitions = array(
			'get-funnel-analytics'            => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Funnel Analytics', 'funnel-builder-powerpack' ),
				'description'         => 'Per-step analytics for one Funnel: views, conversions, conversion rate and revenue. Without a window: all-time. With after/before: exact conversions and revenue for the window per step (views are all-time-only and come back null). Without an active Funnel Builder Pro licence this returns available:false with the reason instead of data. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						array(
							'funnel_id' => WFFN_MCP_Schema::in_id(),
						),
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', 'true reports all-time through the windowed engine.' ),
					),
					array( 'funnel_id' )
				),
				'output_schema'       => self::schema_get_funnel_analytics(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_funnel_analytics' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-dashboard-overview'          => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Dashboard Overview', 'funnel-builder-powerpack' ),
				'description'         => 'Funnel Builder performance across all Funnels for a date window as single totals: revenue split by checkout/upsell/bump, orders, contacts, average order value. Defaults to the last 7 days; overall=true for all-time; a bare before date counts that whole day. The orders are Funnel-attributed ones: list-funnel-orders with the same after/before lists them with ids. For the same numbers as a time series use get-funnel-graph. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					WFFN_MCP_Schema::in_window( 'Window start, Y-m-d or Y-m-d H:i:s, site timezone. Default: 7 days ago.', 'Window end. Default: now.', 'true ignores the window and reports all-time.' )
				),
				'output_schema'       => self::schema_get_dashboard_overview(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_dashboard_overview' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-revenue-attribution'         => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Revenue Attribution', 'funnel-builder-powerpack' ),
				'description'         => 'Where sales and leads came from: orders, revenue and optins per traffic source (referrer / UTM) with each source\'s share, for a date window. Defaults to the last 7 days; overall=true for all-time. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					WFFN_MCP_Schema::in_window( 'Window start, Y-m-d or Y-m-d H:i:s, site timezone. Default: 7 days ago.', 'Window end. Default: now.', 'true ignores the window and reports all-time.' )
				),
				'output_schema'       => self::schema_get_revenue_attribution(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_revenue_attribution' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-step-stats'                  => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Step Stats', 'funnel-builder-powerpack' ),
				'description'         => 'Views, conversions and revenue for ONE Step of a Funnel - finer than get-funnel-analytics when only one Step matters. Add after/before for an exact date window (views then come back null - they are all-time-only). Find Step ids with get-funnel. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						array(
							'funnel_id' => WFFN_MCP_Schema::in_id(),
							'step_id'   => WFFN_MCP_Schema::in_id(),
						),
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', 'true reports all-time through the windowed engine.' ),
					),
					array( 'funnel_id', 'step_id' )
				),
				'output_schema'       => self::schema_get_step_stats(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_step_stats' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'list-ab-tests'                   => array(
				'label'               => __( 'List A/B Tests', 'funnel-builder-powerpack' ),
				'description'         => 'A/B Tests with type, status, dates and the funnel_id / control_step_id their admin link needs, newest first, 20 per call (limit up to 100, offset for the next page; total is the full count). Status alone answers winner questions: Started = running, no winner yet; Paused = stopped, no winner; Completed = winner declared; Draft = never started. Dates are d.m.Y site time (30.11.-0001 = not started/completed). Winning variant: get-ab-test; numbers: get-ab-test-results. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input( WFFN_MCP_Schema::in_paging( 100, 20, 'Tests per call, 1-100. Default 20.', 'Tests to skip; with limit this pages through total.' ) ),
				'output_schema'       => self::schema_ab_test_list(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_ab_tests' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-ab-test'                     => array(
				'label'               => __( 'Get A/B Test', 'funnel-builder-powerpack' ),
				'description'         => 'One A/B Test in full: title, status, step type and every variant with its traffic share, control and winner flags and live URL. Numbers per variant: get-ab-test-results. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_ab_test' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-ab-test-results'             => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get A/B Test Results', 'funnel-builder-powerpack' ),
				'description'         => 'How an A/B Test is performing: views, conversions, conversion rate and revenue in total, per interval and per variant, for a date window. Defaults to the last 7 days; overall=true for all-time. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						array(
							'experiment_id' => WFFN_MCP_Schema::in_id(),
						),
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d or Y-m-d H:i:s, site timezone. Default: 7 days ago.', 'Window end. Default: now.', 'true ignores the window and reports all-time.' ),
						array(
							'interval' => WFFN_MCP_Schema::in_enum( array( 'day', 'week', 'month', 'year' ) ),
						),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get_results(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_ab_test_results' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'create-ab-test'                  => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Create A/B Test', 'funnel-builder-powerpack' ),
				'description'         => 'Start a new A/B Test on one Step: the Step becomes the control (variant A) at 100% traffic and the test is created as Draft. The test type follows the Step type (checkout, landing, optin, thank you, order bump, upsell offer); an Upsell Step itself cannot be tested. A Step already under a Draft/Started/Paused test is refused. Next: create-ab-test-variant, then update-step-template / update-*-settings on the new variant id, update-ab-test for the traffic split, start-ab-test. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id'     => WFFN_MCP_Schema::in_id( 'Step that becomes the control; step ids from get-funnel.' ),
						'title'       => WFFN_MCP_Schema::in_str( 'Test name. Omit to auto-name after the Step.', array( 'maxLength' => self::AB_TITLE_MAX ) ),
						'description' => WFFN_MCP_Schema::in_str( 'Optional note.', array( 'maxLength' => self::AB_DESC_MAX ) ),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'create_ab_test' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),

			'create-ab-test-variant'          => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Create A/B Test Variant', 'funnel-builder-powerpack' ),
				'description'         => 'Add a variant to an A/B Test by copying a Step: the control by default, or any other Step of the same type (source_step_id). The copy is a real Step - its id (in variants) is a step_id for update-step-template, update-checkout-settings and the other step tools, so change its design after this call. It starts at 0% traffic: set the split with update-ab-test. Each call adds another variant; refused on a Completed test. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id'  => WFFN_MCP_Schema::in_id(),
						'source_step_id' => WFFN_MCP_Schema::in_id( 'Step to copy: a variant id from get-ab-test or another Step of the same type. Default: the control.' ),
						'title'          => WFFN_MCP_Schema::in_str( 'Variant name. Omit to auto-name.', array( 'maxLength' => self::AB_TITLE_MAX ) ),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'create_ab_test_variant' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),

			'update-ab-test'                  => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update A/B Test', 'funnel-builder-powerpack' ),
				'description'         => 'Rename an A/B Test, change its note, or set the traffic split. traffics replaces the whole split: one entry per active variant (ids from get-ab-test), shares in percent summing to 100. A description cannot be cleared, only replaced. Send at least one of title, description, traffics. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
						'title'         => WFFN_MCP_Schema::in_str( '', array( 'maxLength' => self::AB_TITLE_MAX ) ),
						'description'   => WFFN_MCP_Schema::in_str( '', array( 'maxLength' => self::AB_DESC_MAX ) ),
						'traffics'      => self::schema_ab_traffics_input(),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_ab_test' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'start-ab-test'                   => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Start A/B Test', 'funnel-builder-powerpack' ),
				'description'         => 'Start (or resume) an A/B Test so visitors are split between its variants. Needs at least two active variants with a design and (checkout/offer/bump) a product each, and traffic summing to 100 - the error names what is missing. Already Started: no change. Refused on a Completed test. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'start_ab_test' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'pause-ab-test'                   => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Pause A/B Test', 'funnel-builder-powerpack' ),
				'description'         => 'Pause a Started A/B Test: every visitor sees the control again and the results stay. start-ab-test resumes it. Already Paused: no change. Refused on a Draft or Completed test. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'experiment_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'pause_ab_test' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'declare-ab-test-winner'          => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Declare A/B Test Winner', 'funnel-builder-powerpack' ),
				'description'         => 'End an A/B Test by declaring one variant the winner. Irreversible: the winner\'s design and settings are copied over the control Step (the live URL), the other variants are taken out of traffic and their results are folded into the control, and the test becomes Completed, so a repeated call is refused. Read get-ab-test-results and confirm with the user first. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
						'variant_id'    => WFFN_MCP_Schema::in_id( 'Winning variant id from get-ab-test; the control id keeps the control as it is.' ),
					),
					array( 'experiment_id', 'variant_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'declare_ab_test_winner' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),

			'archive-ab-test-variant'         => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Archive A/B Test Variant', 'funnel-builder-powerpack' ),
				'description'         => 'Take one variant out of an A/B Test: it is unpublished, gets 0% traffic and stops receiving visitors; its Step and results are kept and restore-ab-test-variant brings it back. The control cannot be archived, and a Started test must keep two active variants (pause it instead). Give the remaining variants their new split with traffics in the same call (always on a Started test, so the live split never dips below 100) or with update-ab-test after. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
						'variant_id'    => WFFN_MCP_Schema::in_id( 'Variant id from get-ab-test.' ),
						'traffics'      => self::schema_ab_traffics_input(),
					),
					array( 'experiment_id', 'variant_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'archive_ab_test_variant' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'restore-ab-test-variant'         => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Restore A/B Test Variant', 'funnel-builder-powerpack' ),
				'description'         => 'Bring an archived variant back into an A/B Test: it is published again at 0% traffic - give it a share with traffics in the same call (the full new split, this variant included) or with update-ab-test after. Refused on a Completed test. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'experiment_id' => WFFN_MCP_Schema::in_id(),
						'variant_id'    => WFFN_MCP_Schema::in_id( 'Variant id from get-ab-test.' ),
						'traffics'      => self::schema_ab_traffics_input(),
					),
					array( 'experiment_id', 'variant_id' )
				),
				'output_schema'       => self::schema_ab_test_get(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'restore_ab_test_variant' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'list-funnel-contacts'            => array(
				'label'               => __( 'List Funnel Contacts', 'funnel-builder-powerpack' ),
				'description'         => 'Contacts captured by Funnels: name, email, phone, lead/customer type and capture date. Filter to one Funnel with funnel_id; `search` matches a name or email fragment. For the CRM view of a person (tags, lists, automations) prefer list-contacts / get-contact when FunnelKit Automations is active. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'funnel_id' => WFFN_MCP_Schema::in_id( 'Limit to one Funnel; omit for all Funnels.' ),
						'search'    => WFFN_MCP_Schema::in_search(),
						'limit'     => WFFN_MCP_Schema::in_int(
							'',
							array(
								'minimum' => 1,
								'maximum' => self::LIST_MAX,
								'default' => self::LIST_DEFAULT,
							)
						),
						'page'      => WFFN_MCP_Schema::in_int(
							'',
							array(
								'minimum' => 1,
								'default' => 1,
							)
						),
					)
				),
				'output_schema'       => self::schema_list_funnel_contacts(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_funnel_contacts' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-funnel-contact'              => array(
				'label'               => __( 'Get Funnel Contact', 'funnel-builder-powerpack' ),
				'description'         => 'One Contact as Funnel Builder sees it: profile, Funnel orders and optin activity, spend; scope to one Funnel with funnel_id. For tags, lists and automation history prefer get-contact when FunnelKit Automations is active. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'contact_id' => WFFN_MCP_Schema::in_id(),
						'funnel_id'  => WFFN_MCP_Schema::in_id(),
					),
					array( 'contact_id' )
				),
				'output_schema'       => self::schema_get_funnel_contact(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_funnel_contact' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'list-funnel-orders'              => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'List Funnel Orders', 'funnel-builder-powerpack' ),
				'description'         => 'WooCommerce Orders attributed to Funnels, newest first: order id and link, buyer, totals, UTM attribution and device. The same orders get-dashboard-overview, get-funnel-performance and get-top-funnels count - pass their after/before (and funnel_id) to list the orders behind a count; total then matches it. No after/before: every Funnel order. `search` matches buyer name, email or order number. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						array(
							'funnel_id' => WFFN_MCP_Schema::in_id( 'Limit to one Funnel; omit for all Funnels.' ),
							'search'    => WFFN_MCP_Schema::in_search(),
						),
						WFFN_MCP_Schema::in_window( 'Orders placed from, Y-m-d (whole day) or Y-m-d H:i:s, site timezone. Omit with before for all orders.', 'Orders placed up to, Y-m-d (whole day) or Y-m-d H:i:s. Default with after: now.' ),
						array(
							'limit' => WFFN_MCP_Schema::in_int(
								'',
								array(
									'minimum' => 1,
									'maximum' => self::LIST_MAX,
									'default' => self::LIST_DEFAULT,
								)
							),
							'page'  => WFFN_MCP_Schema::in_int(
								'',
								array(
									'minimum' => 1,
									'default' => 1,
								)
							),
						)
					)
				),
				'output_schema'       => self::schema_list_funnel_orders(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_funnel_orders' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'list-funnel-leads'               => array(
				'label'               => __( 'List Funnel Leads', 'funnel-builder-powerpack' ),
				'description'         => 'Optin submissions captured by Funnels: email, submitted fields and capture time. Filter with funnel_id; `search` matches the email or submitted data. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'funnel_id' => WFFN_MCP_Schema::in_id( 'Limit to one Funnel; omit for all Funnels.' ),
						'search'    => WFFN_MCP_Schema::in_search(),
						'limit'     => WFFN_MCP_Schema::in_int(
							'',
							array(
								'minimum' => 1,
								'maximum' => self::LIST_MAX,
								'default' => self::LIST_DEFAULT,
							)
						),
						'page'      => WFFN_MCP_Schema::in_int(
							'',
							array(
								'minimum' => 1,
								'default' => 1,
							)
						),
					)
				),
				'output_schema'       => self::schema_list_funnel_leads(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_funnel_leads' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'list-payment-methods'            => array(
				'label'               => __( 'List Payment Methods', 'funnel-builder-powerpack' ),
				'description'         => 'WooCommerce payment gateways (Stripe, PayPal, COD ...) with their title and whether each is enabled - what a Funnel Builder Checkout Step can offer buyers. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(),
				'output_schema'       => self::schema_checkout_list_payment_methods(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_payment_methods' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-top-funnels'                 => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Top Funnels', 'funnel-builder-powerpack' ),
				'description'         => 'Best performing Funnels for a date window: sales Funnels ranked by revenue (with views, orders and conversion rate) and lead Funnels by optins. Defaults to the last 7 days; overall=true for all-time. At most 20 of each, whatever limit asks for. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', '' ),
						array(
							'limit' => WFFN_MCP_Schema::in_int(
								'',
								array(
									'minimum' => 1,
									'maximum' => self::TOP_FUNNELS_LIMIT_MAX,
									'default' => self::TOP_FUNNELS_DEFAULT,
								)
							),
						),
					)
				),
				'output_schema'       => self::schema_get_top_funnels(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_top_funnels' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-upsell-offers'               => array(
				'label'               => __( 'Get Upsell Offers', 'funnel-builder-powerpack' ),
				'description'         => 'Upsell and downsell Offers on one Upsell Step: each Offer\'s products with pricing, status, page builder, and where accepting or rejecting it leads next; builder_inactive means the Offer\'s page builder plugin is off - tell the user that first. They play as one accept/decline sequence to the audience the Step\'s display rules admit - not as alternatives picked by rule. Find the Step with get-funnel (type wc_upsells). Take rates: get-offer-performance. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::schema_get_upsell_offers(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_upsell_offers' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-order-bump'                  => array(
				'label'               => __( 'Get Order Bump', 'funnel-builder-powerpack' ),
				'description'         => 'One Order Bump\'s configuration: products, pricing, design, display position on the checkout, product-settings behaviour and the display rules that decide which carts see it. Bump ids appear as substeps in get-funnel. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'bump_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'bump_id' )
				),
				'output_schema'       => self::schema_get_order_bump(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_order_bump' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'list-bump-skins'                 => array(
				'label'               => __( 'List Order Bump Skins', 'funnel-builder-powerpack' ),
				'description'         => 'The skins an Order Bump can wear - each with a short label (e.g. Smart Toggle), what it looks like, and which is active. Omit bump_id (or pass 0) before the bump exists: the same skins, none active. Match the user\'s words against label and description; apply one with update-bump-skin. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'bump_id' => WFFN_MCP_Schema::in_int( 'Order Bump whose active skin to mark. Omit or 0 for none.', array( 'minimum' => 0 ) ),
					)
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'bump_id' => WFFN_MCP_Schema::int( 'The bump asked about; 0 when none was given.' ),
							'skins'   => WFFN_MCP_Schema::arr(
								WFFN_MCP_Schema::obj(
									array(
										'id'          => WFFN_MCP_Schema::str( 'Pass as skin to update-bump-skin.' ),
										'name'        => WFFN_MCP_Schema::str( 'The admin picker\'s number, e.g. Skin 8.' ),
										'label'       => WFFN_MCP_Schema::str( 'Short semantic name, e.g. Smart Toggle - match the user\'s words against this.' ),
										'description' => WFFN_MCP_Schema::str( 'What the skin looks like.' ),
										'active'      => WFFN_MCP_Schema::bool( 'Whether the bump wears this skin now.' ),
										'legacy'      => WFFN_MCP_Schema::bool( 'Present when the admin picker no longer offers this skin.', true ),
									),
									'',
									array( 'id', 'name', 'label', 'description', 'active' )
								)
							),
							'total'   => WFFN_MCP_Schema::int(),
						),
						'',
						array( 'bump_id', 'skins', 'total' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_bump_skins' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-upsell-settings'             => array(
				'label'               => __( 'Get Upsell Settings', 'funnel-builder-powerpack' ),
				'description'         => 'Behaviour of one Upsell Step: the display rules that decide which orders see it, the Step\'s settings tabs (order handling, prices) and each Offer\'s own settings - values only, no UI schema. Credentials come back masked. Offers with their products: get-upsell-offers. Find the Step with get-funnel (type wc_upsells). Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::schema_get_upsell_settings(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_upsell_settings' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-checkout-conditional-fields' => array(
				'label'               => __( 'Get Checkout Conditional Fields', 'funnel-builder-powerpack' ),
				'description'         => 'Conditional Fields of one Checkout Step: every form field with whether it has an enabled condition and the condition itself (when to show or hide it), plus conditions set on whole sections. Field basics: get-checkout-fields. Find Step ids with get-funnel (type wc_checkout). Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::schema_get_checkout_conditional_fields(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_checkout_conditional_fields' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'duplicate-funnel'                => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Duplicate Funnel', 'funnel-builder-powerpack' ),
				'description'         => 'Copy a Funnel with every Step, Offer and Order Bump, as the admin\'s Duplicate does; the copy is titled "[title] - Copy" unless title is given. This is the clone path when a user creating a funnel wants one like an existing Funnel (offer their Funnels from list-funnels); create-funnel is the fresh path. Each call makes another copy. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'funnel_id' => WFFN_MCP_Schema::in_id(),
						'title'     => WFFN_MCP_Schema::in_str(
							'',
							array(
								'minLength' => 1,
								'maxLength' => 200,
							)
						),
					),
					array( 'funnel_id' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro( FKMCP_Pack_Funnels::funnel_summary_schema( array( 'source_funnel_id' => WFFN_MCP_Schema::int( 'The Funnel that was copied.' ) ) ) ),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'duplicate_funnel' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),

			'create-order-bump'               => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Create Order Bump', 'funnel-builder-powerpack' ),
				'description'         => 'Add an Order Bump to a Checkout Step (type wc_order_bump - the only sub-step type). A skin is chosen at creation: without one the call is refused with the options listed (labels and descriptions: list-bump-skins). Then configure products, texts and rules with the bump tools. Find Checkout Step ids with get-funnel (type wc_checkout). Each call adds another Order Bump. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id(),
						'type'    => WFFN_MCP_Schema::in_enum( array( 'wc_order_bump' ) ),
						'title'   => WFFN_MCP_Schema::in_str(
							'',
							array(
								'minLength' => 1,
								'maxLength' => 200,
							)
						),
						'skin'    => WFFN_MCP_Schema::in_str(
							'Skin the new bump wears, e.g. layout_6 - ask the user, options come from list-bump-skins (omitting it returns the list to ask with).',
							array(
								'minLength' => 1,
								'maxLength' => 50,
							)
						),
					),
					array( 'step_id', 'type' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'substep_id' => WFFN_MCP_Schema::int( 'The new Order Bump id (bump_id for get-order-bump).' ),
							'type'       => WFFN_MCP_Schema::str(),
							'title'      => WFFN_MCP_Schema::str(),
							'step_id'    => WFFN_MCP_Schema::int( 'Parent Checkout Step.' ),
							'funnel_id'  => WFFN_MCP_Schema::int(),
							'skin'       => WFFN_MCP_Schema::obj(
								array(
									'id'    => WFFN_MCP_Schema::str(),
									'name'  => WFFN_MCP_Schema::str(),
									'label' => WFFN_MCP_Schema::str(),
								),
								'The skin the bump was created with.'
							),
							'links'      => WFFN_MCP_Schema::obj( array( 'edit' => WFFN_MCP_Schema::url( 'Bump editor.' ) ) ),
						),
						'',
						array( 'substep_id', 'step_id', 'funnel_id' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'create_order_bump' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),

			'update-bump-products'            => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Order Bump Products', 'funnel-builder-powerpack' ),
				'description'         => 'Change the products an Order Bump offers (PATCH): add products (find ids with search-products for=bump; a variable product lets the shopper choose its variation in a popup, or pass a variation id), update a product\'s quantity or discount, remove products. Read the current list with get-order-bump first. Discount types: ' . implode( ', ', FKMCP_Pack_Funnels::DISCOUNT_TYPES ) . '. Write.',
				'input_schema'        => FKMCP_Pack_Funnels::products_patch_schema(
					'bump_id',
					array(
						'settings' => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'integer', 'array' ) ), 'Product-tab settings (PATCH): bump_action_type (1 add to cart, 2 replace a cart item), bump_replace_type (all | specific), selected_replace_product (product ids replaced when specific).' ),
					)
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'bump_id'          => WFFN_MCP_Schema::int(),
							'products'         => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::any_obj( 'Product row as get-order-bump returns it.' ) ),
							'product_settings' => WFFN_MCP_Schema::any_obj( 'The Products tab behaviour after the write, as get-order-bump shows it.' ),
							'changed'          => FKMCP_Pack_Funnels::changed_schema(),
						),
						'',
						array( 'bump_id', 'products', 'changed' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_bump_products' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-offer-products'           => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Upsell Offer Products', 'funnel-builder-powerpack' ),
				'description'         => 'Change the products an Upsell Offer sells (PATCH): add products (find ids with search-products for=offer; a variable product is added without a default variation, or pass a variation id), update a product\'s quantity or discount, remove products. Read the current offers with get-upsell-offers first. Do not run it in parallel with update-offer-template on the same Offer: each saves the whole Offer, so wait for one to finish before the other. Discount types: ' . implode( ', ', self::OFFER_DISCOUNT_TYPES ) . '. Write.',
				'input_schema'        => FKMCP_Pack_Funnels::products_patch_schema(
					'offer_id',
					array(
						'settings' => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'boolean', 'integer', 'number' ) ), 'Product-tab settings (PATCH): jump_on_accepted / jump_on_rejected (true/false) with jump_to_offer_on_accepted / jump_to_offer_on_rejected (automatic, terminate = thank you page, or the id of a later Offer), skip_exist, skip_purchased, qty_selector (true/false) with qty_max, ship_dynamic.' ),
					),
					self::OFFER_DISCOUNT_TYPES
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'offer_id' => WFFN_MCP_Schema::int(),
							'step_id'  => WFFN_MCP_Schema::int( 'The Upsell Step the Offer belongs to.' ),
							'products' => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::any_obj( 'Product row as get-upsell-offers returns it.' ) ),
							'settings' => WFFN_MCP_Schema::any_obj( 'Product-tab settings written (only when settings was passed).' ),
							'changed'  => FKMCP_Pack_Funnels::changed_schema(),
						),
						'',
						array( 'offer_id', 'step_id', 'products', 'changed' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_offer_products' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'create-offer'                    => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Create Upsell Offer', 'funnel-builder-powerpack' ),
				'description'         => 'Add an Offer to an Upsell Step. First say what the funnel\'s Upsell Steps sell (get-upsell-offers) and ask: edit an Offer, add one to an existing Upsell Step, or add a new Upsell Step? Then ask for the template: list-funnel-templates type=upsell for the site\'s page builder (the user\'s look words as search), suggest about 3 (one line each; none marked pro unless pro_active), and let the user pick one or start blank - skip this when they already named a template or asked for a blank page. ARCHITECTURE: display rules gate the whole Upsell Step, and its Offers play as one accept/decline sequence to that same audience - Offers for different audiences (rule-based offers) need one Upsell Step each: create-step type=wc_upsells, then update-upsell-rules per Step. A NEW Upsell Step already contains one default Offer (get-upsell-offers): configure that one first and use create-offer only for a follow-up offer or downsell in the same sequence. type: upsell (shown after the checkout, default) or downsell (shown when the previous offer is declined). template (a slug from that list, plus its builder) imports the page from the FunnelKit cloud; without it the offer page is blank and needs update-offer-template or the admin designer. The new Offer has no products: add them with update-offer-products. Find Upsell Step ids with get-funnel (type wc_upsells). Each call adds another Offer. Write.',
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'step_id' ),
					'properties'           => array(
						'step_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'    => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 200,
						),
						'type'     => array(
							'type'    => 'string',
							'enum'    => array( 'upsell', 'downsell' ),
							'default' => 'upsell',
						),
						'template' => array(
							'type'      => 'string',
							'pattern'   => '^[a-z0-9_-]+$',
							'maxLength' => 100,
						),
						'builder'  => array(
							'type'      => 'string',
							'pattern'   => '^[a-z0-9_-]+$',
							'maxLength' => 40,
						),
					),
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'offer_id' => WFFN_MCP_Schema::int( 'The new Offer id (offer_id for update-offer-products).' ),
							'step_id'  => WFFN_MCP_Schema::int( 'The Upsell Step.' ),
							'title'    => WFFN_MCP_Schema::str(),
							'type'     => WFFN_MCP_Schema::enum( array( 'upsell', 'downsell' ) ),
							'template' => WFFN_MCP_Schema::str( 'Template imported, or empty.' ),
							'links'    => WFFN_MCP_Schema::obj(
								array(
									'edit' => WFFN_MCP_Schema::url( 'Upsell Step editor.' ),
									'view' => WFFN_MCP_Schema::url( 'The offer page.' ),
								)
							),
						),
						'',
						array( 'offer_id', 'step_id', 'type' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'create_offer' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),

			'update-offer-template'           => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Offer Template', 'funnel-builder-powerpack' ),
				'description'         => 'Change the design template of an existing Upsell Offer, as the admin\'s Change Template does: the template is imported and replaces the Offer\'s page content, so edits made to the old design are lost - confirm with the user first. offer_id: from get-upsell-offers. template: a slug from list-funnel-templates (type upsell); builder: the page builder that template is listed under (default: the site\'s default builder). Do not run it in parallel with update-offer-products on the same Offer: each saves the whole Offer, so wait for one to finish before the other. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'offer_id' => WFFN_MCP_Schema::in_id(),
						'template' => WFFN_MCP_Schema::in_str(
							'',
							array(
								'pattern'   => '^[a-z0-9_-]+$',
								'maxLength' => 100,
							)
						),
						'builder'  => WFFN_MCP_Schema::in_enum( FKMCP_Pack_Funnels::BUILDERS ),
					),
					array( 'offer_id', 'template' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'offer_id' => WFFN_MCP_Schema::int(),
							'step_id'  => WFFN_MCP_Schema::int( 'The Upsell Step the Offer belongs to.' ),
							'title'    => WFFN_MCP_Schema::str(),
							'template' => FKMCP_Pack_Funnels::design_schema( 'The design the Offer shows now.' ),
							'previous' => FKMCP_Pack_Funnels::design_schema( 'The design it showed before this call.' ),
							'links'    => WFFN_MCP_Schema::obj(
								array(
									'edit' => WFFN_MCP_Schema::url( 'Upsell Step editor.' ),
									'view' => WFFN_MCP_Schema::url( 'Live offer page.' ),
								)
							),
						),
						'',
						array( 'offer_id', 'step_id', 'template' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_offer_template' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
			'update-upsell-settings'          => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Upsell Settings', 'funnel-builder-powerpack' ),
				'description'         => 'Change one Upsell Step\'s settings - keys and options are what get-upsell-settings shows under settings: order_behavior (batching = each accepted Offer is merged into the main order, the default; create_order = each accepted Offer is a new order), is_cancel_order (yes/no, only with create_order), is_tax_included (yes/no), the accepted/failed/wait messages, failed-payment recovery texts, funnel_success_script (stored verbatim). To REPLACE what the customer bought with the Offer: order_behavior=create_order and is_cancel_order=yes. Then accepting the Step\'s first Offer (not a downsell) cancels the whole main order, every item in it. Payment: a one-product Offer priced at or above the main order total is charged only the difference; otherwise the Offer is charged in full and the main order is refunded through its gateway, or, if that gateway cannot refund, only marked cancelled and the refund is manual. Tell the user this. PATCH: only the keys passed change. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id'  => WFFN_MCP_Schema::in_id(),
						'settings' => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'boolean', 'integer', 'number', 'array' ) ), 'Setting key => new value.', array( 'minProperties' => 1 ) ),
					),
					array( 'step_id', 'settings' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'step_id'  => WFFN_MCP_Schema::int(),
							'changed'  => WFFN_MCP_Schema::any_obj( 'Setting key => the value written.' ),
							'settings' => WFFN_MCP_Schema::any_obj( 'The settings after the write, per tab, as get-upsell-settings shows them.' ),
						),
						'',
						array( 'step_id', 'changed' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_upsell_settings' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-offer-settings'           => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Offer Settings', 'funnel-builder-powerpack' ),
				'description'         => 'Change one Upsell Offer\'s settings (ask for confirmation before accepting; tracking code on view and on purchase) - keys are what get-upsell-settings shows per offer under settings. Switching an Offer on/off is update-step-status (draft = off). offer_id: from get-upsell-offers. PATCH: only the keys passed change. On/off keys (ask_confirmation, check_add_offer_script, check_add_offer_purchase) as true/false; the code keys are stored verbatim. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'offer_id' => WFFN_MCP_Schema::in_id(),
						'settings' => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'boolean', 'integer', 'number', 'array' ) ), 'Setting key => new value.', array( 'minProperties' => 1 ) ),
					),
					array( 'offer_id', 'settings' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'offer_id' => WFFN_MCP_Schema::int(),
							'step_id'  => WFFN_MCP_Schema::int( 'The Upsell Step the Offer belongs to.' ),
							'changed'  => WFFN_MCP_Schema::any_obj( 'Setting key => the value written.' ),
							'settings' => WFFN_MCP_Schema::any_obj( 'The offer settings after the write, per tab.' ),
						),
						'',
						array( 'offer_id', 'changed' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_offer_settings' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-bump-settings'            => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Order Bump Settings', 'funnel-builder-powerpack' ),
				'description'         => 'Change where and how one Order Bump attaches to its checkout: order_bump_position_hooks (desktop) and order_bump_position_hooks_mobile both take any position id from get-order-bump\'s display_position options - offer every one, Above/Below The Order Summary included, and relay its caveat if it has one. order_bump_auto_added (pre-checked) and order_bump_auto_hide (hide once added). bump_id: from get-funnel (sub-steps of the checkout) or get-order-bump. PATCH: only the keys passed change. Booleans as true/false. Texts, image and colours: update-bump-design. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'bump_id'  => WFFN_MCP_Schema::in_id(),
						'settings' => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'boolean' ) ), 'Setting key => new value.', array( 'minProperties' => 1 ) ),
					),
					array( 'bump_id', 'settings' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'bump_id'  => WFFN_MCP_Schema::int(),
							'changed'  => WFFN_MCP_Schema::any_obj( 'Setting key => the value written.' ),
							'settings' => WFFN_MCP_Schema::any_obj( 'Placement settings after the write.' ),
							'warning'  => WFFN_MCP_Schema::str( 'Present when a chosen position will not render on this checkout; relay it to the user.' ),
						),
						'',
						array( 'bump_id', 'changed', 'settings' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_bump_settings' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-bump-design'              => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Order Bump Design', 'funnel-builder-powerpack' ),
				'description'         => 'Change one Order Bump\'s design values (PATCH): the texts shoppers read - checkbox line, description, badge, button labels, per product as product_[product key]_[field] - plus toggles and style tokens. Keys, types and current values come from get-order-bump\'s design.groups. Product image: product_[key]_featured_image shows/hides it; product_[key]_featured_image_options is an object patch {type: product|custom, custom_url: http(s) image URL, width: px, position: left|right|top}. Texts may use the merge tags get-order-bump lists; an unknown or malformed tag is refused because it would print literally at checkout. The skin itself changes with update-bump-skin. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'bump_id' => WFFN_MCP_Schema::in_id(),
						'design'  => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'boolean', 'integer', 'number', 'object' ) ), 'Design key => new value. Booleans for true/false toggles; an object only for *_featured_image_options; the rest is stored as text.', array( 'minProperties' => 1 ) ),
					),
					array( 'bump_id', 'design' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'bump_id' => WFFN_MCP_Schema::int(),
							'changed' => WFFN_MCP_Schema::any_obj( 'Design key => the value written (after sanitisation).' ),
						),
						'',
						array( 'bump_id', 'changed' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_bump_design' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-bump-skin'                => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Order Bump Skin', 'funnel-builder-powerpack' ),
				'description'         => 'Give one Order Bump a different skin from list-bump-skins. DESTRUCTIVE: importing a skin rebuilds the whole design - custom texts, colours and toggles reset to the skin\'s defaults, exactly as the admin\'s Change Skin does. Read get-order-bump first and re-apply the texts with update-bump-design afterwards. Applying the active skin again changes nothing. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'bump_id' => WFFN_MCP_Schema::in_id(),
						'skin'    => WFFN_MCP_Schema::in_str(
							'Skin id from list-bump-skins, e.g. layout_1.',
							array(
								'minLength' => 1,
								'maxLength' => 50,
							)
						),
					),
					array( 'bump_id', 'skin' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'bump_id'       => WFFN_MCP_Schema::int(),
							'skin'          => WFFN_MCP_Schema::obj(
								array(
									'id'    => WFFN_MCP_Schema::str(),
									'name'  => WFFN_MCP_Schema::str(),
									'label' => WFFN_MCP_Schema::str(),
								),
								'The skin the bump wears now.'
							),
							'previous_skin' => WFFN_MCP_Schema::obj(
								array(
									'id'    => WFFN_MCP_Schema::str(),
									'name'  => WFFN_MCP_Schema::str(),
									'label' => WFFN_MCP_Schema::str(),
								),
								'The skin it wore before.'
							),
							'changed'       => WFFN_MCP_Schema::any_obj( 'Empty when the skin was already active.' ),
							'design'        => WFFN_MCP_Schema::any_obj( 'Design values after the change - the texts now carry the skin defaults.' ),
							'links'         => WFFN_MCP_Schema::any_obj(),
						),
						'',
						array( 'bump_id', 'skin', 'previous_skin', 'changed', 'design' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_bump_skin' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'list-step-rule-types'            => array(
				'label'               => __( 'List Step Rule Types', 'funnel-builder-powerpack' ),
				'description'         => 'The display rule types one Order Bump, Upsell Step or Thank You Step can use, with each type\'s operators and what its condition takes (a number, a date, a choice from a fixed list, product ids, ...). Call before update-bump-rules / update-upsell-rules / update-thankyou-rules: the write validates against this list. for: bump (id = bump id), upsell or thankyou (id = step id). Bump rules check the cart at checkout; upsell and thank you rules check the placed order. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'for' => WFFN_MCP_Schema::in_enum( array( 'bump', 'upsell', 'thankyou' ) ),
						'id'  => WFFN_MCP_Schema::in_id( 'Bump id for bump; step id for upsell / thankyou.' ),
					),
					array( 'for', 'id' )
				),
				'output_schema'       => WFFN_MCP_Schema::pro(
					WFFN_MCP_Schema::obj(
						array(
							'for'        => WFFN_MCP_Schema::str(),
							'id'         => WFFN_MCP_Schema::int(),
							'rule_types' => WFFN_MCP_Schema::arr(
								WFFN_MCP_Schema::obj(
									array(
										'rule_type' => WFFN_MCP_Schema::str( 'Slug to pass as rule_type.' ),
										'label'     => WFFN_MCP_Schema::str(),
										'group'     => WFFN_MCP_Schema::str( 'Cart, Order, Customer, Geography, Date/Time, ...' ),
										'operators' => WFFN_MCP_Schema::arr(
											WFFN_MCP_Schema::obj(
												array(
													'value' => WFFN_MCP_Schema::str( 'Pass as operator.' ),
													'label' => WFFN_MCP_Schema::str( 'What it means, as the admin screen shows it; one symbol differs by type (> is "contains at least" qty on item rules).' ),
												)
											)
										),
										'condition' => WFFN_MCP_Schema::obj(
											array(
												'kind'    => WFFN_MCP_Schema::str( 'none | number | text | date | time | choice | list | products | product_qty | meta' ),
												'options' => WFFN_MCP_Schema::arr(
													WFFN_MCP_Schema::obj(
														array(
															'value' => WFFN_MCP_Schema::str(),
															'label' => WFFN_MCP_Schema::str(),
														)
													),
													'Allowed values for choice / list kinds (products come from search-products instead).'
												),
												'hint'    => WFFN_MCP_Schema::str( 'What to pass as condition.' ),
											)
										),
										'note'      => WFFN_MCP_Schema::str( 'Semantics to respect when writing this rule type; follow it over intuition.' ),
									)
								)
							),
							'total'      => WFFN_MCP_Schema::int(),
						),
						'',
						array( 'for', 'id', 'rule_types', 'total' )
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'list_step_rule_types' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'update-bump-rules'               => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Order Bump Rules', 'funnel-builder-powerpack' ),
				'description'         => 'Replace the display rules of one Order Bump (when it shows on the checkout). rules: groups (ORed) of rules (ANDed), each {rule_type, operator, condition} - types, operators and condition shapes from list-step-rule-types (for=bump); the current set from get-order-bump. "Product NOT in cart" is cart_item with operator != and qty 1 (a qty 0 condition never matches). The whole set is replaced (read it first, send it back edited); [] removes every rule = always shown. Write.',
				'input_schema'        => self::schema_rules_input( 'bump_id' ),
				'output_schema'       => WFFN_MCP_Schema::pro( self::schema_rules_result( 'bump_id' ) ),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_bump_rules' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-upsell-rules'             => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Upsell Rules', 'funnel-builder-powerpack' ),
				'description'         => 'Replace the display rules of one Upsell Step (which orders see it) - the rules gate the Step and every Offer on it together; for per-audience offers make one Upsell Step per rule set. rules: groups (ORed) of rules (ANDed), each {rule_type, operator, condition} - types, operators and condition shapes from list-step-rule-types (for=upsell); the current set from get-upsell-settings. "Product NOT in the order" is order_item with operator != and qty 1 (a qty 0 condition never matches). The whole set is replaced; [] removes every rule = always shown. Write.',
				'input_schema'        => self::schema_rules_input( 'step_id' ),
				'output_schema'       => WFFN_MCP_Schema::pro( self::schema_rules_result( 'step_id' ) ),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_upsell_rules' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'update-thankyou-rules'           => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Update Thank You Rules', 'funnel-builder-powerpack' ),
				'description'         => 'Replace the display rules of one Thank You Step (which orders land on it). rules: groups (ORed) of rules (ANDed), each {rule_type, operator, condition} - types, operators and condition shapes from list-step-rule-types (for=thankyou); the current set from get-thankyou-rules. "Product NOT in the order" is order_item with operator != and qty 1 (a qty 0 condition never matches). The whole set is replaced; [] removes every rule = always shown. Write.',
				'input_schema'        => self::schema_rules_input( 'step_id' ),
				'output_schema'       => WFFN_MCP_Schema::pro( self::schema_rules_result( 'step_id' ) ),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'update_thankyou_rules' ) ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),

			'get-thankyou-rules'              => array(
				'label'               => __( 'Get Thank You Rules', 'funnel-builder-powerpack' ),
				'description'         => 'Rules that decide which orders land on one Thank You Step (by product, category, total, payment method...). Empty means every order. The Step\'s template and settings: get-step-settings. Find Step ids with get-funnel (type wc_thankyou). Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::schema_get_thankyou_rules(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_thankyou_rules' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-offer-performance'           => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Offer Performance', 'funnel-builder-powerpack' ),
				'description'         => 'Take rates for Upsell Offers and Order Bumps in a date window: views, conversions and revenue per offer, best earners first (a new offer with no sales ranks last). Defaults to the last 7 days; overall=true for all-time; funnel_id narrows to one Funnel\'s offers. Counted from the offer and bump logs (plus Cart upsells), not the order tracking get-dashboard-overview reads, so its revenue need not equal that tool\'s upsell/bump split. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', '' ),
						array(
							'funnel_id' => WFFN_MCP_Schema::in_id( 'Only Offers and Bumps of this Funnel.' ),
							'limit'     => WFFN_MCP_Schema::in_int(
								'',
								array(
									'minimum' => 1,
									'maximum' => self::OFFER_PERF_MAX,
									'default' => self::OFFER_PERF_DEFAULT,
								)
							),
							'page'      => WFFN_MCP_Schema::in_int(
								'',
								array(
									'minimum' => 1,
									'default' => 1,
								)
							),
						),
					)
				),
				'output_schema'       => self::schema_get_offer_performance(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_offer_performance' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-funnel-graph'                => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Funnel Graph', 'funnel-builder-powerpack' ),
				'description'         => 'Revenue and order time series across all Funnels for charting: totals plus per-interval points split by checkout/upsell/bump. Defaults to the last 7 days; overall=true spans everything. For totals only (plus contacts and AOV) use get-dashboard-overview. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', '' ),
						array(
							'interval' => WFFN_MCP_Schema::in_enum( array( 'day', 'week', 'month' ) ),
						),
					)
				),
				'output_schema'       => self::schema_get_funnel_graph(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_funnel_graph' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-funnel-order'                => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Funnel Order', 'funnel-builder-powerpack' ),
				'description'         => 'One WooCommerce Order as the Funnel sold it: line items grouped by what sold them (checkout, order bump or upsell) with subtotals, and the order total. Find order ids with list-funnel-orders. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'order_id' => WFFN_MCP_Schema::in_id(),
					),
					array( 'order_id' )
				),
				'output_schema'       => self::schema_get_funnel_order(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_funnel_order' ) ),
				'annotations'         => array( 'readonly' => true ),
			),

			'get-funnel-performance'          => array(
				'capability'          => array( 'analytics', 'read' ),
				'label'               => __( 'Get Funnel Performance', 'funnel-builder-powerpack' ),
				'description'         => 'Orders and revenue for ONE Funnel in a date window, split by checkout/upsell/bump - the windowed view get-funnel-analytics (all-time) cannot give. Defaults to the last 7 days; overall=true for all-time. list-funnel-orders with the same funnel_id and after/before lists those orders. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array_merge(
						array(
							'funnel_id' => WFFN_MCP_Schema::in_id(),
						),
						WFFN_MCP_Schema::in_window( 'Window start, Y-m-d, site timezone. Default: 7 days ago.', 'Window end. Default: now.', '' ),
					),
					array( 'funnel_id' )
				),
				'output_schema'       => self::schema_get_funnel_performance(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => WFFN_Pro_MCP::gate( array( $this, 'get_funnel_performance' ) ),
				'annotations'         => array( 'readonly' => true ),
			),
		);

		return $this->definitions;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_funnel_analytics( $input ) {
		$input  = (array) $input;
		$funnel = new WFFN_Funnel( absint( $input['funnel_id'] ) );

		if ( ! $funnel->get_id() ) {
			return new WP_Error( 'fkmcp_funnel_not_found', 'No funnel with that id.' );
		}

		if ( self::wants_window( $input ) ) {
			return $this->windowed_funnel_analytics( $funnel, $input );
		}

		if ( ! class_exists( 'WFFN_REST_Funnel_Canvas' ) ) {
			return new WP_Error( 'fkmcp_analytics_unavailable', 'The analytics provider is not loaded on this request.' );
		}

		$canvas       = WFFN_REST_Funnel_Canvas::get_instance();
		$steps        = array();
		$has_checkout = in_array( 'wc_checkout', wp_list_pluck( (array) $funnel->get_steps(), 'type' ), true );
		$totals       = array(
			'views'       => 0,
			'conversions' => 0,
			'revenue'     => 0,
		);

		foreach ( (array) $funnel->get_steps() as $step ) {
			$step  = (array) $step;
			$type  = isset( $step['type'] ) ? (string) $step['type'] : '';
			$id    = isset( $step['id'] ) ? (int) $step['id'] : 0;
			$stats = $canvas->get_node_analytics_data( $type, $id );
			$stats = is_array( $stats ) ? $stats : array();

			$totals['views']       += isset( $stats['views'] ) ? (int) $stats['views'] : 0;
			$totals['conversions'] += isset( $stats['conversions'] ) ? (int) $stats['conversions'] : 0;

			// The checkout node's revenue is the order total, upsells included;
			// adding the upsell node's own revenue on top would count it twice.
			if ( 'wc_upsells' !== $type || ! $has_checkout ) {
				$totals['revenue'] += isset( $stats['revenue'] ) ? (float) $stats['revenue'] : 0;
			}

			$steps[] = array(
				'id'    => $id,
				'type'  => $type,
				'stats' => $stats,
			);
		}

		return array(
			'available' => true,
			'funnel_id' => (int) $funnel->get_id(),
			'title'     => $funnel->get_title(),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'totals'    => $totals,
			'steps'     => $steps,
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_dashboard_overview( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$window = self::window( $input );

		if ( is_wp_error( $window ) ) {
			return $window;
		}
		$data = FKMCP_Pack_Funnels::response_data( $dashboard->get_overview_data( $window, true ) );

		return array(
			'window' => empty( $window ) ? array( 'default' => 'last 7 days' ) : $window,
			'data'   => isset( $data['data'] ) ? $data['data'] : array(),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_revenue_attribution( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$window = self::window( $input );

		if ( is_wp_error( $window ) ) {
			return $window;
		}
		$data = FKMCP_Pack_Funnels::response_data( $dashboard->get_all_source_data( $window ) );

		return array(
			'window' => empty( $window ) ? array( 'default' => 'last 7 days' ) : $window,
			'data'   => isset( $data['data'] ) ? $data['data'] : array(),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_step_stats( $input ) {
		$input  = (array) $input;
		$funnel = new WFFN_Funnel( absint( $input['funnel_id'] ) );

		if ( ! $funnel->get_id() ) {
			return new WP_Error( 'fkmcp_funnel_not_found', 'No funnel with that id.' );
		}

		$canvas = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-canvas.php', 'WFFN_REST_Funnel_Canvas' );

		if ( ! $canvas && ! self::wants_window( $input ) ) {
			return new WP_Error( 'fkmcp_analytics_unavailable', 'The analytics provider is not loaded on this request.' );
		}

		$step_id = absint( $input['step_id'] );

		foreach ( (array) $funnel->get_steps() as $step ) {
			$step = (array) $step;

			if ( absint( isset( $step['id'] ) ? $step['id'] : 0 ) !== $step_id ) {
				continue;
			}

			$type = isset( $step['type'] ) ? (string) $step['type'] : '';

			if ( self::wants_window( $input ) ) {
				$range = self::window_range( $input );

				if ( is_wp_error( $range ) ) {
					return $range;
				}

				list( $start, $end ) = $range;

				return array(
					'funnel_id' => (int) $funnel->get_id(),
					'step_id'   => $step_id,
					'type'      => $type,
					'window'    => self::window( $input ),
					'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
					'stats'     => $this->windowed_step_stats( $type, $step_id, $start, $end ),
				);
			}

			$stats = $canvas->get_node_analytics_data( $type, $step_id );

			return array(
				'funnel_id' => (int) $funnel->get_id(),
				'step_id'   => $step_id,
				'type'      => $type,
				'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'stats'     => is_array( $stats ) ? $stats : array(),
			);
		}

		return new WP_Error( 'fkmcp_step_not_found', 'No step with that id in this funnel.' );
	}

	/**
	 * @param mixed $funnel
	 * @param mixed $input
	 *
	 * @return mixed
	 */
	private function windowed_funnel_analytics( $funnel, $input ) {
		$range = self::window_range( $input );

		if ( is_wp_error( $range ) ) {
			return $range;
		}

		list( $start, $end ) = $range;

		$steps        = array();
		$has_checkout = in_array( 'wc_checkout', wp_list_pluck( (array) $funnel->get_steps(), 'type' ), true );
		$totals       = array(
			'conversions' => 0,
			'revenue'     => 0,
		);

		foreach ( (array) $funnel->get_steps() as $step ) {
			$step  = (array) $step;
			$type  = isset( $step['type'] ) ? (string) $step['type'] : '';
			$id    = isset( $step['id'] ) ? (int) $step['id'] : 0;
			$stats = $this->windowed_step_stats( $type, $id, $start, $end );

			$totals['conversions'] += (int) $stats['conversions'];

			// Same rule as the all-time read: checkout revenue already holds
			// the upsell revenue.
			if ( 'wc_upsells' !== $type || ! $has_checkout ) {
				$totals['revenue'] += (float) $stats['revenue'];
			}

			$steps[] = array(
				'id'    => $id,
				'type'  => $type,
				'stats' => $stats,
			);
		}

		return array(
			'available' => true,
			'funnel_id' => (int) $funnel->get_id(),
			'title'     => $funnel->get_title(),
			'window'    => self::window( $input ),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'totals'    => $totals,
			'steps'     => $steps,
		);
	}

	/**
	 * Windowed per-step numbers. Conversions and revenue are exact - every
	 * source table is dated; per-step views are not (the views feed is an
	 * empty-by-default filter), so they answer null rather than a fake 0.
	 * The two direct SELECTs exist because nothing upstream queries these
	 * tables per step AND per window - canonical-by-absence.
	 */
	private function windowed_step_stats( $type, $step_id, $start, $end ) {
		global $wpdb;

		$stats = array(
			'views'           => null,
			'conversions'     => 0,
			'conversion_rate' => null,
			'revenue'         => 0,
		);

		$ids = self::step_and_variant_ids( $type, $step_id );

		if ( 'wc_checkout' === $type ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql          = "SELECT COUNT(ID) AS cn, SUM(total_revenue) AS rev FROM {$wpdb->prefix}wfacp_stats WHERE wfacp_id IN ({$placeholders})";
			$params       = $ids;

			if ( '' !== $start ) {
				$sql     .= ' AND `date` BETWEEN %s AND %s';
				$params[] = $start;
				$params[] = $end;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN() list is %d placeholders counted off the int array above.
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

			$stats['conversions'] = isset( $row['cn'] ) ? (int) $row['cn'] : 0;
			$stats['revenue']     = isset( $row['rev'] ) ? (float) $row['rev'] : 0;

			return $stats;
		}

		if ( 'optin' === $type ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql          = "SELECT COUNT(id) AS cn FROM {$wpdb->prefix}bwf_optin_entries WHERE step_id IN ({$placeholders})";
			$params       = $ids;

			if ( '' !== $start ) {
				$sql     .= ' AND `date` BETWEEN %s AND %s';
				$params[] = $start;
				$params[] = $end;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN() list is %d placeholders counted off the int array above.
			$stats['conversions'] = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

			return $stats;
		}

		if ( 'wc_upsells' === $type ) {
			// Offers hang off the step; their windowed numbers already exist
			// in the dashboard's offer analytics - sum the step's offers.
			$offer_ids = self::offer_posts( $step_id, 'ids' );

			$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

			if ( $dashboard && $offer_ids ) {
				$request = array(
					'limit'   => 100,
					'page_no' => 1,
				);

				if ( '' !== $start ) {
					$request['after']  = $start;
					$request['before'] = $end;
				}

				$rows  = FKMCP_Pack_Funnels::response_data( $dashboard->get_all_upsells( $request, 'analytics' ) );
				$views = 0;

				foreach ( (array) ( isset( $rows['result']['items'] ) ? $rows['result']['items'] : array() ) as $row ) {
					$row = (array) $row;

					if ( in_array( (int) ( isset( $row['id'] ) ? $row['id'] : 0 ), array_map( 'intval', $offer_ids ), true ) ) {
						$stats['conversions'] += (int) $row['conversion'];
						$stats['revenue']     += (float) $row['revenue'];
						$views                += (int) $row['views'];
					}
				}

				$stats['views']           = $views;
				$stats['conversion_rate'] = $views > 0 ? round( $stats['conversions'] / $views * 100, 2 ) : 0;
			}

			return $stats;
		}

		// thankyou / landing / optin_ty and bump substeps have no dated
		// per-step source; bumps report through get-offer-performance.
		$stats['conversions'] = null;
		$stats['revenue']     = null;

		return $stats;
	}

	/** The same A/B-variant expansion the canvas applies before counting. */
	private static function step_and_variant_ids( $type, $step_id ) {
		$ids = array();

		if ( class_exists( 'WFFN_Pro_Core' ) && is_object( WFFN_Pro_Core()->steps ) ) {
			$integration = WFFN_Pro_Core()->steps->get_integration_object( $type );

			if ( is_object( $integration ) && method_exists( $integration, 'maybe_get_ab_variants' ) ) {
				$ids = (array) $integration->maybe_get_ab_variants( $step_id );
			}
		}

		$ids[] = $step_id;

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return mixed
	 */
	private static function wants_window( $input ) {
		$input = (array) $input;

		return ! empty( $input['overall'] ) || ! empty( $input['after'] ) || ! empty( $input['before'] );
	}

	/** [start, end] datetime strings; ['',''] means all-time. */
	private static function window_range( $input ) {
		$window = self::window( $input );

		if ( is_wp_error( $window ) ) {
			return $window;
		}

		if ( ! empty( $window['overall'] ) ) {
			return array( '', '' );
		}

		return array( $window['after'], $window['before'] );
	}

	/**
	 * @return array|WP_Error
	 */
	public function list_ab_tests( $input = array() ) {
		$ready = self::ab_unavailable();

		if ( $ready ) {
			return $ready;
		}

		$page     = WFFN_MCP_Format::paginate( (array) $input );
		$result   = BWFABT_Core()->admin->get_experiments(
			array(
				'experiments' => 'all',
				'order'       => 'DESC',
				'funnels'     => 'DESC',
			)
		);
		$statuses = BWFABT_Core()->admin->get_experiment_statuses();
		$all      = (array) ( isset( $result['items'] ) ? $result['items'] : array() );
		$rows     = array_slice( $all, $page['offset'], $page['limit'] );
		$controls = self::control_steps( $rows );
		$items    = array();

		// One meta query for the page instead of one per test.
		if ( ! empty( $controls ) ) {
			update_meta_cache( 'post', array_values( array_filter( $controls ) ) );
		}

		foreach ( $rows as $row ) {
			$row     = (array) $row;
			$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$control = isset( $controls[ $id ] ) ? $controls[ $id ] : 0;

			$items[] = array(
				'id'              => $id,
				'title'           => isset( $row['title'] ) ? $row['title'] : '',
				'desc'            => isset( $row['desc'] ) ? $row['desc'] : '',
				'type'            => isset( $row['type'] ) ? $row['type'] : '',
				'status'          => isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : ( isset( $row['status'] ) ? $row['status'] : '' ),
				'funnel_id'       => self::ab_funnel_id( $control ),
				'control_step_id' => $control,
				'date_added'      => isset( $row['date_added'] ) ? $row['date_added'] : '',
				'date_started'    => isset( $row['date_started'] ) ? $row['date_started'] : '',
				'date_completed'  => isset( $row['date_completed'] ) ? $row['date_completed'] : '',
			);
		}

		return array(
			'items'  => $items,
			'total'  => isset( $result['found_posts'] ) ? (int) $result['found_posts'] : count( $all ),
			'limit'  => $page['limit'],
			'offset' => $page['offset'],
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_ab_test( $input ) {
		$ready = self::ab_unavailable();

		if ( $ready ) {
			return $ready;
		}

		$rest = self::ab_controller( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( ! $rest ) {
			return new WP_Error( 'fkmcp_ab_tests_unavailable', 'The A/B Tests API is not loaded on this request.' );
		}

		$input = (array) $input;

		// Checked here because the module's own loader wp_die()s on an unknown
		// id - an agent must get an error result, never a dead request.
		if ( ! self::experiment_exists( absint( $input['experiment_id'] ) ) ) {
			return new WP_Error( 'fkmcp_experiment_not_found', 'No A/B test with that id.' );
		}

		$experiment = $rest->get_experiment( absint( $input['experiment_id'] ) );

		if ( ! is_array( $experiment ) || empty( $experiment['id'] ) ) {
			return new WP_Error( 'fkmcp_experiment_not_found', 'No A/B test with that id.' );
		}

		$variants = array();

		foreach ( (array) ( isset( $experiment['variants'] ) ? $experiment['variants'] : array() ) as $variant ) {
			$variant = (array) $variant;
			$edit    = isset( $variant['edit'] ) && is_string( $variant['edit'] ) ? $variant['edit'] : '';
			unset( $variant['edit'], $variant['desc'] );

			// The editor link the admin UI opens for a variant, breadcrumb back to the test.
			$variant['edit_link'] = '' !== $edit ? add_query_arg( 'bwf_exp_ref', (int) $experiment['id'], $edit ) : '';
			$variants[]           = $variant;
		}

		$controls = self::control_steps( array( array( 'id' => (int) $experiment['id'] ) ) );
		$control  = isset( $controls[ (int) $experiment['id'] ] ) ? $controls[ (int) $experiment['id'] ] : 0;

		return array(
			'id'              => (int) $experiment['id'],
			'title'           => isset( $experiment['title'] ) ? $experiment['title'] : '',
			'desc'            => isset( $experiment['desc'] ) ? $experiment['desc'] : '',
			'type'            => isset( $experiment['type'] ) ? $experiment['type'] : '',
			'status'          => isset( $experiment['status'] ) ? $experiment['status'] : '',
			'funnel_id'       => self::ab_funnel_id( $control ),
			'control_step_id' => $control,
			'variants'        => $variants,
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_ab_test_results( $input ) {
		$ready = self::ab_unavailable();

		if ( $ready ) {
			return $ready;
		}

		$analytics = self::ab_controller( 'class-bwfabt-rest-analytics.php', 'BWFABT_REST_Analytics' );

		if ( ! $analytics ) {
			return new WP_Error( 'fkmcp_ab_tests_unavailable', 'The A/B Tests API is not loaded on this request.' );
		}

		$input = (array) $input;

		// Same wp_die() hazard as get-ab-test: refuse unknown ids up front.
		if ( ! self::experiment_exists( absint( $input['experiment_id'] ) ) ) {
			return new WP_Error( 'fkmcp_experiment_not_found', 'No A/B test with that id.' );
		}

		$request = self::window( $input );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$request['experiment_id'] = absint( $input['experiment_id'] );

		if ( isset( $input['interval'] ) && in_array( $input['interval'], array( 'day', 'week', 'month', 'year' ), true ) ) {
			$request['interval'] = $input['interval'];
		}

		$stats    = FKMCP_Pack_Funnels::response_data( $analytics->get_stats( $request ) );
		$variants = FKMCP_Pack_Funnels::response_data( $analytics->get_all_variants( $request ) );

		return array(
			'experiment_id' => $request['experiment_id'],
			'window'        => array_diff_key( $request, array( 'experiment_id' => 1 ) ),
			'totals'        => isset( $stats['totals'] ) ? $stats['totals'] : array(),
			'intervals'     => isset( $stats['intervals'] ) ? $stats['intervals'] : array(),
			'variants'      => $variants,
		);
	}

	/*
	------------------------------------------------------------------ */
	/*
	A/B Test writes                                                     */
	/* ------------------------------------------------------------------ */

	/** Rows a list ability answers with, and the most it will answer with. */
	const LIST_DEFAULT = 20;
	const LIST_MAX     = 100;

	/** Top funnels: a ranking, so a short list by default. */
	const TOP_FUNNELS_DEFAULT = 5;
	const TOP_FUNNELS_MAX     = 20;

	/** The largest limit get-top-funnels accepts; anything above TOP_FUNNELS_MAX answers with that many. */
	const TOP_FUNNELS_LIMIT_MAX = 100;

	/** Offer performance rows per call. */
	const OFFER_PERF_DEFAULT = 10;
	const OFFER_PERF_MAX     = 50;

	/** Design keys one patch may carry. */
	const DESIGN_KEYS_MAX = 60;

	const AB_TITLE_MAX    = 200;
	const AB_DESC_MAX     = 500;
	const AB_VARIANTS_MAX = 20;

	/** The traffic split as update-ab-test, archive- and restore-ab-test-variant all take it. */
	private static function schema_ab_traffics_input() {
		return WFFN_MCP_Schema::in_arr(
			WFFN_MCP_Schema::in_obj(
				array(
					'variant_id' => WFFN_MCP_Schema::in_id( 'Variant id from get-ab-test.' ),
					'traffic'    => WFFN_MCP_Schema::in_num(
						'Share in percent, 0-100, up to 2 decimals.',
						array(
							'minimum' => 0,
							'maximum' => 100,
						)
					),
				),
				'',
				array( 'variant_id', 'traffic' )
			),
			'Complete traffic split: every active variant once, shares summing to 100; an archived variant may only be 0 or left out.',
			array(
				'minItems' => 1,
				'maxItems' => self::AB_VARIANTS_MAX,
			)
		);
	}

	/**
	 * Step post type => A/B test type, as BWFABT_REST_Experiment::get_control_type()
	 * maps them. wfocu_funnel (an Upsell) is deliberately absent: the module
	 * refuses to test one.
	 */
	const AB_STEP_TYPES = array(
		'wfacp_checkout' => 'aero',
		'wfob_bump'      => 'order_bump',
		'wffn_landing'   => 'landing',
		'wffn_ty'        => 'thank_you',
		'wffn_optin'     => 'optin',
		'wffn_oty'       => 'optin_ty',
		'wfocu_offer'    => 'offer',
	);

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function create_ab_test( $input ) {
		return FKMCP_Pack_Funnels::write( 'create-ab-test', $input, array( $this, 'do_create_ab_test' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_create_ab_test( $input ) {
		$input = (array) $input;
		$rest  = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$post = self::ab_step( $input, 'step_id' );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$type = self::AB_STEP_TYPES[ $post->post_type ];

		if ( BWFABT_Core()->admin->maybe_existing_control( $post->ID, $type ) ) {
			return new WP_Error( 'fkmcp_invalid_state', sprintf( 'Step %d is already under an A/B test (see list-ab-tests); finish that one with declare-ab-test-winner first.', $post->ID ) );
		}

		$title    = self::ab_text( $input, 'title', self::AB_TITLE_MAX );
		$response = self::ab_call(
			$rest,
			'add_new_experiment',
			array(
				'control' => (int) $post->ID,
				'type'    => $type,
				'title'   => $title,
				'desc'    => self::ab_text( $input, 'description', self::AB_DESC_MAX ),
				'default' => '' === $title, // The module names the test after the Step.
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_create_failed', $response->get_error_message() );
		}

		$id = isset( $response['ex_id'] ) ? absint( $response['ex_id'] ) : 0;

		if ( ! $id ) {
			return new WP_Error( 'fkmcp_create_failed', 'The A/B test was not created.' );
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function create_ab_test_variant( $input ) {
		return FKMCP_Pack_Funnels::write( 'create-ab-test-variant', $input, array( $this, 'do_create_ab_test_variant' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_create_ab_test_variant( $input ) {
		$input      = (array) $input;
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		if ( BWFABT_Experiment::STATUS_COMPLETE === self::ab_status( $experiment ) ) {
			return new WP_Error( 'fkmcp_invalid_state', 'This A/B test is Completed; start a new one with create-ab-test.' );
		}

		$rest = self::ab_rest( 'class-bwfabt-rest-variant.php', 'BWFABT_REST_Variant' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$source = array( 'step_id' => isset( $input['source_step_id'] ) ? absint( $input['source_step_id'] ) : (int) $experiment->get_control() );
		$post   = self::ab_step( $source, 'step_id', (string) $experiment->get_type() );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$response = self::ab_call(
			$rest,
			'add_variant',
			array(
				'experiment_id' => (int) $experiment->get_id(),
				'duplicate_id'  => (int) $post->ID,
				'variant_title' => self::ab_text( $input, 'title', self::AB_TITLE_MAX ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_create_failed', $response->get_error_message() );
		}

		// The duplicate route names the copy "<control> - Copy" and only echoes
		// variant_title back; a variant's real name is its Step's post title.
		$title      = self::ab_text( $input, 'title', self::AB_TITLE_MAX );
		$variant_id = isset( $response['variant_id'] ) ? (int) $response['variant_id'] : 0;

		if ( '' !== $title && $variant_id > 0 ) {
			wp_update_post(
				array(
					'ID'         => $variant_id,
					'post_title' => $title,
				)
			);
		}

		return $this->get_ab_test( array( 'experiment_id' => (int) $experiment->get_id() ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_ab_test( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-ab-test', $input, array( $this, 'do_update_ab_test' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_ab_test( $input ) {
		$input      = (array) $input;
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		$title    = self::ab_text( $input, 'title', self::AB_TITLE_MAX );
		$desc     = self::ab_text( $input, 'description', self::AB_DESC_MAX );
		$traffics = array_key_exists( 'traffics', $input ) ? $input['traffics'] : null;

		if ( '' === $title && '' === $desc && null === $traffics ) {
			return new WP_Error( 'fkmcp_invalid_input', 'Send at least one of title, description, traffics.' );
		}

		$split = null;

		if ( null !== $traffics ) {
			$split = self::ab_traffics( $experiment, $traffics );

			if ( is_wp_error( $split ) ) {
				return $split;
			}
		}

		$rest = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$id = (int) $experiment->get_id();

		// The module's update handles either the text or the split per call, never both.
		if ( '' !== $title || '' !== $desc ) {
			$response = self::ab_call(
				$rest,
				'update_experiment',
				array(
					'experiment_id' => $id,
					'title'         => '' !== $title ? $title : (string) $experiment->get_title(),
					'desc'          => $desc,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
			}
		}

		if ( null !== $split ) {
			$response = self::ab_call(
				$rest,
				'update_experiment',
				array(
					'experiment_id' => $id,
					'traffics'      => $split,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
			}
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function start_ab_test( $input ) {
		return FKMCP_Pack_Funnels::write( 'start-ab-test', $input, array( $this, 'do_start_ab_test' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_start_ab_test( $input ) {
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		$status = self::ab_status( $experiment );
		$id     = (int) $experiment->get_id();

		if ( BWFABT_Experiment::STATUS_COMPLETE === $status ) {
			return new WP_Error( 'fkmcp_invalid_state', 'This A/B test is Completed and cannot be restarted; start a new one with create-ab-test.' );
		}

		if ( BWFABT_Experiment::STATUS_START === $status ) {
			return $this->get_ab_test( array( 'experiment_id' => $id ) );
		}

		$rest = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$response = self::ab_call( $rest, 'start_experiment', array( 'experiment_id' => $id ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_not_ready', $response->get_error_message() );
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function pause_ab_test( $input ) {
		return FKMCP_Pack_Funnels::write( 'pause-ab-test', $input, array( $this, 'do_pause_ab_test' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_pause_ab_test( $input ) {
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		$status = self::ab_status( $experiment );
		$id     = (int) $experiment->get_id();

		if ( BWFABT_Experiment::STATUS_PAUSE === $status ) {
			return $this->get_ab_test( array( 'experiment_id' => $id ) );
		}

		if ( BWFABT_Experiment::STATUS_START !== $status ) {
			return new WP_Error( 'fkmcp_invalid_state', 'Only a Started A/B test can be paused.' );
		}

		$rest = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$response = self::ab_call( $rest, 'stop_experiment', array( 'experiment_id' => $id ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function declare_ab_test_winner( $input ) {
		return FKMCP_Pack_Funnels::write( 'declare-ab-test-winner', $input, array( $this, 'do_declare_ab_test_winner' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_declare_ab_test_winner( $input ) {
		$input      = (array) $input;
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		if ( BWFABT_Experiment::STATUS_COMPLETE === self::ab_status( $experiment ) ) {
			return new WP_Error( 'fkmcp_invalid_state', 'This A/B test is Completed: its winner is already declared.' );
		}

		$variant_id = self::ab_variant( $experiment, $input );

		if ( is_wp_error( $variant_id ) ) {
			return $variant_id;
		}

		$rest = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

		if ( is_wp_error( $rest ) ) {
			return $rest;
		}

		$id       = (int) $experiment->get_id();
		$response = self::ab_call(
			$rest,
			'choose_winner',
			array(
				'experiment_id' => $id,
				'winner_id'     => $variant_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function archive_ab_test_variant( $input ) {
		return FKMCP_Pack_Funnels::write( 'archive-ab-test-variant', $input, array( $this, 'do_archive_ab_test_variant' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_archive_ab_test_variant( $input ) {
		return $this->ab_variant_status( $input, false );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function restore_ab_test_variant( $input ) {
		return FKMCP_Pack_Funnels::write( 'restore-ab-test-variant', $input, array( $this, 'do_restore_ab_test_variant' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_restore_ab_test_variant( $input ) {
		return $this->ab_variant_status( $input, true );
	}

	/** archive (draft) and restore (publish) share everything but the module method and the no-op direction. */
	private function ab_variant_status( $input, $publish ) {
		$input      = (array) $input;
		$experiment = self::ab_experiment( $input );

		if ( is_wp_error( $experiment ) ) {
			return $experiment;
		}

		if ( BWFABT_Experiment::STATUS_COMPLETE === self::ab_status( $experiment ) ) {
			return new WP_Error( 'fkmcp_invalid_state', 'This A/B test is Completed; its variants can no longer change.' );
		}

		$variant_id = self::ab_variant( $experiment, $input );

		if ( is_wp_error( $variant_id ) ) {
			return $variant_id;
		}

		$id = (int) $experiment->get_id();

		if ( ! $publish && $variant_id === (int) $experiment->get_control() ) {
			return new WP_Error( 'fkmcp_invalid_input', 'The control cannot be archived; pause the test with pause-ab-test instead.' );
		}

		// Checked against the state this call leaves behind, before anything is written.
		$split = array_key_exists( 'traffics', $input ) ? self::ab_traffics( $experiment, $input['traffics'], array( $variant_id => $publish ) ) : null;

		if ( is_wp_error( $split ) ) {
			return $split;
		}

		if ( $publish !== self::ab_variant_active( $experiment, $variant_id ) ) {
			$rest = self::ab_rest( 'class-bwfabt-rest-variant.php', 'BWFABT_REST_Variant' );

			if ( is_wp_error( $rest ) ) {
				return $rest;
			}

			$response = self::ab_call(
				$rest,
				$publish ? 'publish_variant' : 'draft_variant',
				array(
					'experiment_id' => $id,
					'variant_id'    => $variant_id,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
			}
		}

		if ( null !== $split ) {
			$rest = self::ab_rest( 'class-bwfabt-rest-experiment.php', 'BWFABT_REST_Experiment' );

			if ( is_wp_error( $rest ) ) {
				return $rest;
			}

			$response = self::ab_call(
				$rest,
				'update_experiment',
				array(
					'experiment_id' => $id,
					'traffics'      => $split,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'fkmcp_update_failed', sprintf( 'The variant was %s but the new split was not saved: %s', $publish ? 'restored' : 'archived', $response->get_error_message() ) );
			}
		}

		return $this->get_ab_test( array( 'experiment_id' => $id ) );
	}

	/* A/B write helpers ------------------------------------------------ */

	/** The module's REST singleton for a write, or the WP_Error every A/B tool answers when the module is missing. */
	private static function ab_rest( $file, $class ) {
		$ready = self::ab_unavailable();

		if ( $ready ) {
			return $ready;
		}

		$rest = self::ab_controller( $file, $class );

		return $rest ? $rest : new WP_Error( 'fkmcp_ab_tests_unavailable', 'The A/B Tests API is not loaded on this request.' );
	}

	/** experiment_id => BWFABT_Experiment, refusing unknown ids before the module can wp_die() on them. */
	private static function ab_experiment( $input ) {
		$ready = self::ab_unavailable();

		if ( $ready ) {
			return $ready;
		}

		$input = (array) $input;
		$id    = absint( isset( $input['experiment_id'] ) ? $input['experiment_id'] : 0 );

		if ( ! self::experiment_exists( $id ) ) {
			return new WP_Error( 'fkmcp_experiment_not_found', 'No A/B test with that id.' );
		}

		return BWFABT_Core()->admin->get_experiment( $id );
	}

	/**
	 * @param mixed $experiment
	 *
	 * @return mixed
	 */
	private static function ab_status( $experiment ) {
		return (int) $experiment->get_status();
	}

	/** A Step post that the A/B module can test; with $type, one of that test's type. */
	private static function ab_step( $input, $key, $type = '' ) {
		$input = (array) $input;
		$id    = absint( isset( $input[ $key ] ) ? $input[ $key ] : 0 );
		$post  = $id ? get_post( $id ) : null;

		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			return new WP_Error( 'fkmcp_step_not_found', 'No step with that id.' );
		}

		if ( 'wfocu_funnel' === $post->post_type ) {
			return new WP_Error( 'fkmcp_wrong_type', sprintf( 'Step %d is an Upsell, which cannot be A/B tested as a whole; test one of its Offers (get-upsell-offers) instead.', $id ) );
		}

		if ( ! isset( self::AB_STEP_TYPES[ $post->post_type ] ) ) {
			return new WP_Error( 'fkmcp_wrong_type', sprintf( 'Step %d (%s) cannot be A/B tested.', $id, $post->post_type ) );
		}

		if ( '' !== $type && self::AB_STEP_TYPES[ $post->post_type ] !== $type ) {
			return new WP_Error( 'fkmcp_wrong_type', sprintf( 'Step %d is a %s Step; this test is on %s Steps.', $id, self::AB_STEP_TYPES[ $post->post_type ], $type ) );
		}

		return $post;
	}

	/** Agent-written text that admins will read back: one line, no markup, capped. */
	private static function ab_text( $input, $key, $max ) {
		$input = (array) $input;
		$text  = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';

		return mb_substr( $text, 0, $max );
	}

	/** Variant ids of a test, as ints. */
	private static function ab_variant_ids( $experiment ) {
		return array_map( 'intval', array_keys( (array) $experiment->get_variants() ) );
	}

	/** variant_id from the input, verified to belong to this test. */
	private static function ab_variant( $experiment, $input ) {
		$input = (array) $input;
		$id    = absint( isset( $input['variant_id'] ) ? $input['variant_id'] : 0 );

		if ( ! in_array( $id, self::ab_variant_ids( $experiment ), true ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'variant_id %d is not a variant of this test; ids are in get-ab-test.', $id ) );
		}

		return $id;
	}

	/**
	 * @param mixed $experiment
	 * @param mixed $variant_id
	 *
	 * @return mixed
	 */
	private static function ab_variant_active( $experiment, $variant_id ) {
		$controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );

		return is_object( $controller ) && method_exists( $controller, 'is_variant_active' ) ? (bool) $controller->is_variant_active( $variant_id ) : false;
	}

	/**
	 * The traffic split the module stores: every id a variant of this test,
	 * every active variant present, archived ones at 0, shares summing to 100.
	 * $will_be_active overrides a variant's active state for a call that is
	 * about to change it.
	 */
	private static function ab_traffics( $experiment, $traffics, array $will_be_active = array() ) {
		if ( ! is_array( $traffics ) || empty( $traffics ) || count( $traffics ) > self::AB_VARIANTS_MAX ) {
			return new WP_Error( 'fkmcp_invalid_input', 'traffics must be a list of {variant_id, traffic}, one per active variant.' );
		}

		$known = self::ab_variant_ids( $experiment );
		$split = array();
		$sum   = 0.0;

		foreach ( $traffics as $row ) {
			$row   = (array) $row;
			$id    = absint( isset( $row['variant_id'] ) ? $row['variant_id'] : 0 );
			$share = isset( $row['traffic'] ) ? $row['traffic'] : null;

			if ( ! in_array( $id, $known, true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'variant_id %d is not a variant of this test; ids are in get-ab-test.', $id ) );
			}

			if ( isset( $split[ $id ] ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'variant_id %d is listed twice.', $id ) );
			}

			if ( ! is_numeric( $share ) || $share < 0 || $share > 100 ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'traffic for variant %d must be a number from 0 to 100.', $id ) );
			}

			$split[ $id ] = round( (float) $share, 2 );
			$sum         += $split[ $id ];
		}

		$missing = array();

		foreach ( $known as $id ) {
			$active = array_key_exists( $id, $will_be_active ) ? (bool) $will_be_active[ $id ] : self::ab_variant_active( $experiment, $id );

			if ( $active && ! isset( $split[ $id ] ) ) {
				$missing[] = $id;
			}

			// The module only counts active variants towards 100, so a share here would break start-ab-test.
			if ( ! $active && isset( $split[ $id ] ) && $split[ $id ] > 0 ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'variant %d is archived; give it 0 or leave it out.', $id ) );
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'traffics must cover every active variant; missing: ' . implode( ', ', $missing ) . '.' );
		}

		if ( abs( $sum - 100 ) > 0.01 ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Traffic shares must sum to 100, not %s.', rtrim( rtrim( number_format( $sum, 2, '.', '' ), '0' ), '.' ) ) );
		}

		$out = array();

		foreach ( $split as $id => $share ) {
			$out[] = array(
				'variant_id' => $id,
				'traffic'    => number_format( $share, 2, '.', '' ),
			);
		}

		return $out;
	}

	/**
	 * One module REST write under controller_call(). The module answers
	 * status + message rather than errors: a false status becomes a WP_Error
	 * carrying its message, tags stripped, so the agent learns why.
	 */
	private static function ab_call( $rest, $method, array $params ) {
		$request  = FKMCP_Pack_Funnels::rest_request( $params, 'POST' );
		$response = FKMCP_Pack_Funnels::controller_call(
			static function () use ( $rest, $method, $request ) {
				return $rest->{$method}( $request );
			}
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! empty( $response['status'] ) ) {
			return $response;
		}

		$message = '';

		// draft_variant answers error + description; the rest answer message.
		foreach ( array( 'description', 'message', 'error' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_string( $response[ $key ] ) && '' !== trim( $response[ $key ] ) ) {
				$message = trim( wp_strip_all_tags( $response[ $key ] ) );
				break;
			}
		}

		return new WP_Error( 'fkmcp_ab_refused', '' !== $message ? $message : 'The A/B Tests module refused the change.' );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function list_funnel_contacts( $input ) {
		$contacts = FKMCP_Pack_Funnels::controller( 'class-wffn-funnel-contacts.php', 'WFFN_Funnel_Contacts' );

		if ( ! $contacts ) {
			return new WP_Error( 'fkmcp_contacts_unavailable', 'The contacts provider is not loaded on this request.' );
		}

		$data = FKMCP_Pack_Funnels::response_data( $contacts->get_contacts( self::list_args( $input ) ) );
		$rows = array();

		foreach ( (array) ( isset( $data['records'] ) && is_array( $data['records'] ) ? $data['records'] : array() ) as $row ) {
			$rows[] = self::untrust( (array) $row );
		}

		return array(
			'items' => $rows,
			'total' => self::list_total( $data, $rows ),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_funnel_contact( $input ) {
		$contacts = FKMCP_Pack_Funnels::controller( 'class-wffn-funnel-contacts.php', 'WFFN_Funnel_Contacts' );

		if ( ! $contacts ) {
			return new WP_Error( 'fkmcp_contacts_unavailable', 'The contacts provider is not loaded on this request.' );
		}

		$input = (array) $input;
		$cid   = absint( isset( $input['contact_id'] ) ? $input['contact_id'] : 0 );
		$known = class_exists( 'BWF_Contacts' ) ? BWF_Contacts::get_instance()->get_contact_by( 'id', $cid ) : null;

		if ( ! is_object( $known ) || ! method_exists( $known, 'get_id' ) || (int) $known->get_id() <= 0 ) {
			return new WP_Error( 'fkmcp_contact_not_found', 'No contact with that id.' );
		}

		$data = FKMCP_Pack_Funnels::response_data(
			$contacts->get_contact_single(
				array(
					'id'  => ! empty( $input['funnel_id'] ) ? absint( $input['funnel_id'] ) : 0,
					'cid' => $cid,
				)
			)
		);

		return self::untrust( $data );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function list_funnel_orders( $input ) {
		$orders = FKMCP_Pack_Funnels::controller( 'class-wffn-funnel-orders.php', 'WFFN_Funnel_Orders' );

		if ( ! $orders ) {
			return new WP_Error( 'fkmcp_orders_unavailable', 'The orders provider is not loaded on this request.' );
		}

		$input  = (array) $input;
		$args   = self::list_args( $input );
		$window = array();

		// The controller is a singleton that keeps the last call's filters
		// unless handed new ones, so every call names all of its own.
		$args['filters'] = array(
			array(
				'filter' => 's',
				'data'   => $args['s'],
			),
		);

		if ( ! empty( $input['after'] ) || ! empty( $input['before'] ) ) {
			$window = self::window( $input );

			if ( is_wp_error( $window ) ) {
				return $window;
			}

			$args['filters'][] = array(
				'filter' => 'period',
				'data'   => array(
					'after'  => $window['after'],
					'before' => $window['before'],
				),
			);
		}

		$data = FKMCP_Pack_Funnels::response_data( $orders->get_orders( $args, true ) );
		$rows = array();

		foreach ( (array) ( isset( $data['records'] ) && is_array( $data['records'] ) ? $data['records'] : array() ) as $row ) {
			$rows[] = self::untrust( (array) $row );
		}

		$out = array(
			'items' => $rows,
			'total' => self::list_total( $data, $rows ),
		);

		if ( $window ) {
			$out['window'] = $window;
		}

		return $out;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function list_funnel_leads( $input ) {
		$orders = FKMCP_Pack_Funnels::controller( 'class-wffn-funnel-orders.php', 'WFFN_Funnel_Orders' );

		if ( ! $orders ) {
			return new WP_Error( 'fkmcp_orders_unavailable', 'The orders provider is not loaded on this request.' );
		}

		$data = FKMCP_Pack_Funnels::response_data( $orders->get_leads( self::list_args( $input ), true ) );
		$rows = array();

		foreach ( (array) ( isset( $data['records'] ) && is_array( $data['records'] ) ? $data['records'] : array() ) as $row ) {
			$rows[] = self::untrust( (array) $row );
		}

		return array(
			'items' => $rows,
			'total' => self::list_total( $data, $rows ),
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public function list_payment_methods() {
		if ( ! function_exists( 'WC' ) || ! is_object( WC()->payment_gateways() ) ) {
			return new WP_Error( 'fkmcp_wc_unavailable', 'WooCommerce is not active.' );
		}

		$items = array();

		foreach ( (array) WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}

			$items[] = array(
				'id'      => (string) $gateway->id,
				'title'   => (string) $gateway->get_title(),
				'method'  => (string) $gateway->get_method_title(),
				'enabled' => 'yes' === $gateway->enabled,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_top_funnels( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$input   = (array) $input;
		$request = self::window( $input );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$request['limit'] = isset( $input['limit'] ) ? max( 1, min( self::TOP_FUNNELS_MAX, absint( $input['limit'] ) ) ) : self::TOP_FUNNELS_DEFAULT;

		$data = $dashboard->get_top_funnels( $request );
		$data = is_array( $data ) ? $data : array();

		return array(
			'window' => array_diff_key( $request, array( 'limit' => 1 ) ),
			'sales'  => self::top_funnel_rows( isset( $data['sales'] ) ? $data['sales'] : array() ),
			'leads'  => self::top_funnel_rows( isset( $data['lead'] ) ? $data['lead'] : array() ),
		);
	}

	/**
	 * The dashboard hands back its rows as the query left them, every column a
	 * string - so a Funnel with no traffic answers views "0" where the schema
	 * says integer, and a client validating the result rejects the whole call.
	 * Counts go out as counts and money as money.
	 */
	private static function top_funnel_rows( $rows ) {
		$out = array();

		foreach ( (array) $rows as $row ) {
			$row = (array) $row;

			foreach ( array( 'fid', 'views', 'conversion' ) as $key ) {
				if ( isset( $row[ $key ] ) ) {
					$row[ $key ] = (int) $row[ $key ];
				}
			}

			foreach ( array( 'total', 'conversion_rate' ) as $key ) {
				if ( isset( $row[ $key ] ) ) {
					$row[ $key ] = (float) $row[ $key ];
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	/** Legacy stored names normalized the way the module itself reads them. */
	private static function offer_discount( array $fields ) {
		$discount = WFFN_MCP_Format::pick( $fields, array( 'discount_amount', 'discount_type', 'quantity' ) );

		if ( isset( $discount['discount_type'] ) && class_exists( 'WFOCU_Common' ) && method_exists( 'WFOCU_Common', 'get_discount_setting' ) ) {
			$discount['discount_type'] = (string) WFOCU_Common::get_discount_setting( (string) $discount['discount_type'] );
		}

		return $discount;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_upsell_offers( $input ) {
		if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wfocu_funnel' ), 'upsell' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		if ( ! function_exists( 'WFOCU_Core' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$offers = self::offer_posts( $step->ID );

		$items = array();

		foreach ( $offers as $offer_post ) {
			// Deep cast: the offer object nests stdClass all the way down.
			$config = json_decode( wp_json_encode( WFOCU_Core()->offers->get_offer( $offer_post->ID ) ), true );
			$config = is_array( $config ) ? $config : array();

			$products = array();

			foreach ( (array) ( isset( $config['products'] ) ? $config['products'] : array() ) as $key => $product_id ) {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : false;
				$fields  = isset( $config['fields'][ $key ] ) ? (array) $config['fields'][ $key ] : array();

				$products[] = array(
					'product_id' => (int) $product_id,
					'name'       => $product ? $product->get_name() : '',
					'price'      => $product ? $product->get_price() : null,
					'discount'   => self::offer_discount( $fields ),
				);
			}

			$state = null;

			if ( function_exists( 'WFOCU_Core' ) && is_object( WFOCU_Core()->offers ) && method_exists( WFOCU_Core()->offers, 'get_offer_state' ) ) {
				$state = WFOCU_Core()->offers->get_offer_state( (array) get_post_meta( (int) get_post_meta( $offer_post->ID, '_funnel_id', true ), '_funnel_steps', true ), $offer_post->ID );
			}

			$items[] = array(
				'id'       => (int) $offer_post->ID,
				'title'    => $offer_post->post_title,
				'status'   => $offer_post->post_status,
				'enabled'  => null === $state ? null : in_array( $state, array( '1', 1, true ), true ),
				'type'     => (string) get_post_meta( $offer_post->ID, '_offer_type', true ),
				'products' => $products,
				'settings' => WFFN_MCP_Format::pick( (array) ( isset( $config['settings'] ) ? $config['settings'] : array() ), array( 'skip_exist', 'skip_purchased', 'qty_selector', 'qty_max', 'ship_dynamic' ) ),
				'flow'     => WFFN_MCP_Format::pick( (array) ( isset( $config['settings'] ) ? $config['settings'] : array() ), array( 'jump_on_accepted', 'jump_on_rejected', 'jump_to_offer_on_accepted', 'jump_to_offer_on_rejected' ) ),
			) + self::offer_builder( $offer_post->ID );
		}

		return array(
			'step_id' => (int) $step->ID,
			'items'   => $items,
			'total'   => count( $items ),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_order_bump( $input ) {
		if ( ! class_exists( 'WFFN_REST_BUMP_API_EndPoint' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

		if ( is_wp_error( $bump ) ) {
			return $bump;
		}

		$price_hooks = self::price_hooks_snapshot();

		$data = FKMCP_Pack_Funnels::response_data( WFFN_REST_BUMP_API_EndPoint::get_instance()->get_bump( FKMCP_Pack_Funnels::rest_request( array( 'bump_id' => $bump->ID ) ) ) );

		self::price_hooks_restore( $price_hooks );

		foreach ( array( 'woocommerce_is_purchasable', 'woocommerce_subscription_is_purchasable', 'woocommerce_subscription_variation_is_purchasable', 'woocommerce_variation_is_purchasable', 'woocommerce_subscriptions_product_limitation' ) as $hook ) {
			remove_filter( $hook, '__return_true', 9999 );
		}

		$data = isset( $data['data'] ) ? (array) $data['data'] : array();
		// structure + values are the visual editor's field tree and style tokens
		// (~30KB of chrome); what the bump sells lives in products.
		unset( $data['funnel_data'], $data['step_data'], $data['html'], $data['layouts'], $data['merge_tags'], $data['default_css'], $data['structure'], $data['values'] );

		// One tab: its values, not the editor's field list beside them. Every
		// key below is always present so an empty bump reads like a full one.
		unset( $data['bump_settings'] ); // its store is display_position, with labels and options
		$data['active_layout'] = ! empty( $data['active_layout'] ) ? $data['active_layout'] : self::bump_imported_layout( $bump->ID );

		// Display rules and the products tab are two more admin routes, both
		// keyed by the bump post (their "step" is the bump).
		$endpoint = WFFN_REST_BUMP_API_EndPoint::get_instance();
		$request  = FKMCP_Pack_Funnels::rest_request( array( 'step_id' => $bump->ID ) );

		$rules         = FKMCP_Pack_Funnels::response_data( $endpoint->get_wfob_rules( $request ) );
		$data['rules'] = isset( $rules['data']['rules'] ) ? (array) $rules['data']['rules'] : array();

		$placement = FKMCP_Pack_Funnels::response_data( $endpoint->wfob_get_products( $request ) );
		$placement = isset( $placement['data'] ) ? (array) $placement['data'] : array();

		// The products route knows stock, sale state and variation names; the
		// single-bump route only the stored selection.
		$data['products'] = ! empty( $placement['products'] ) && is_array( $placement['products'] ) ? array_values( array_map( array( __CLASS__, 'bump_product_row' ), $placement['products'] ) ) : array();

		$map    = self::bump_design_map( $bump->ID );
		$layout = isset( $map['layout'] ) ? (string) $map['layout'] : '';

		$data['display_position'] = self::bump_display_position_fields( $bump->ID, $layout );
		$data['product_settings'] = self::bump_product_settings_fields( $bump->ID, $placement );
		$models                   = class_exists( 'WFOB_Bump_Fc' ) ? (array) WFOB_Bump_Fc::get_default_models() : array();
		$data['skin']             = self::bump_skin_ref( $layout, $models );
		$data['design']           = self::bump_design_fields( $bump->ID, $map, isset( $models[ $layout ] ) ? (array) $models[ $layout ] : array(), $layout );
		$data['merge_tags']       = self::bump_merge_tags();

		return array_merge( array( 'bump_id' => (int) $bump->ID ), $data );
	}

	/**
	 * The stored discount_price is dead weight (always 0); replace it with
	 * the unit price the shopper actually pays, computed by the module's
	 * own price engine - it makes a discount verifiable from the response.
	 */
	private static function bump_product_row( $row ) {
		$row = (array) $row;

		if ( ! method_exists( 'WFOB_Common', 'calculate_discount' ) ) {
			return $row;
		}

		$regular = isset( $row['regular_price'] ) && is_numeric( $row['regular_price'] ) ? (float) $row['regular_price'] : 0.0;
		// The shopper pays from the active price: a sale price stored for later (a scheduled sale) is not it yet.
		$on_sale = ! isset( $row['is_on_sale'] ) || wc_string_to_bool( $row['is_on_sale'] );
		$sale    = $on_sale && isset( $row['sale_price'] ) && is_numeric( $row['sale_price'] ) && (float) $row['sale_price'] > 0 ? (float) $row['sale_price'] : $regular;
		$price   = WFOB_Common::calculate_discount(
			array(
				'wfob_discount_type'   => isset( $row['discount_type'] ) ? (string) $row['discount_type'] : '',
				'wfob_product_rp'      => $regular,
				'wfob_product_p'       => $sale,
				'wfob_discount_amount' => isset( $row['discount_amount'] ) && is_numeric( $row['discount_amount'] ) ? (float) $row['discount_amount'] : 0.0,
			)
		);

		if ( null !== $price ) {
			$row['discount_price'] = round( (float) $price, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
		}

		return $row;
	}

	/**
	 * The Settings tab as schema fields. Labels, tooltips and options come
	 * from the module's own bump-settings schema; a bump the module cannot
	 * schema (e.g. no products yet) still gets keys, values and the
	 * position options - just without labels.
	 */
	private static function bump_display_position_fields( $bump_id, $layout ) {
		$defs   = array();
		$values = method_exists( 'WFOB_Common', 'get_setting_data' ) ? (array) WFOB_Common::get_setting_data( $bump_id ) : array();
		$probe  = self::bump_probe( $bump_id, $layout );

		if ( $probe ) {
			try {
				$admin = (array) $probe->get_admin_schema();

				if ( isset( $admin['bump-settings']['fields'] ) && is_array( $admin['bump-settings']['fields'] ) ) {
					$defs = $admin['bump-settings']['fields'];
				}
				if ( isset( $admin['bump-settings']['values'] ) && is_array( $admin['bump-settings']['values'] ) ) {
					$values = array_merge( $values, $admin['bump-settings']['values'] );
				}
			} catch ( Throwable $e ) {
				self::log_exception( 'bump display schema', $e );
				$defs = array();
			}
		}

		if ( empty( $defs ) ) {
			$options = array();

			foreach ( self::bump_positions() as $id => $name ) {
				$options[] = array(
					'label' => $name,
					'value' => $id,
				);
			}

			foreach ( self::BUMP_SETTING_KEYS as $key ) {
				$defs[] = array(
					'key'     => $key,
					'type'    => false === strpos( $key, 'position' ) ? 'bwf-toggle' : 'select',
					'options' => false === strpos( $key, 'position' ) ? array() : $options,
				);
			}
		}

		$fields      = array();
		$checkout_id = self::bump_checkout_id( $bump_id );

		foreach ( $defs as $def ) {
			$def = (array) $def;
			$key = isset( $def['key'] ) ? (string) $def['key'] : '';

			if ( ! in_array( $key, self::BUMP_SETTING_KEYS, true ) ) {
				continue;
			}

			$type  = isset( $def['type'] ) && 'select' === $def['type'] ? 'select' : 'toggle';
			$field = array(
				'key'   => $key,
				'type'  => $type,
				'value' => 'toggle' === $type ? ! empty( $values[ $key ] ) : ( isset( $values[ $key ] ) ? (string) $values[ $key ] : '' ),
			);

			if ( ! empty( $def['label'] ) && is_string( $def['label'] ) ) {
				$field['label'] = wp_strip_all_tags( $def['label'] );
			}
			if ( ! empty( $def['tooltip'] ) && is_string( $def['tooltip'] ) ) {
				$field['hint'] = wp_strip_all_tags( $def['tooltip'] );
			}
			if ( ! empty( $def['options'] ) && is_array( $def['options'] ) ) {
				$options = array();

				foreach ( self::bump_field_options( $def ) as $row ) {
					// Still an admin option: say why it will not show here rather than flag it off.
					if ( ! self::bump_position_renders( $checkout_id, $row['value'] ) ) {
						$row['caveat'] = 'Shows only once this checkout\'s form has an Order Summary field.';
					}

					$options[] = $row;
				}

				if ( ! empty( $options ) ) {
					$field['options'] = $options;
				}
			}

			$fields[] = $field;
		}

		return array( 'fields' => $fields );
	}

	/**
	 * The Products tab's behaviour settings as schema fields, from the
	 * products route's own field list (labels and option labels included);
	 * update-bump-products patches this store.
	 */
	private static function bump_product_settings_fields( $bump_id, array $data ) {
		$defs   = isset( $data['settings']['fields'] ) && is_array( $data['settings']['fields'] ) ? $data['settings']['fields'] : array();
		$values = array();

		foreach ( array( 'InitialValue', 'initialValue' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$values = (array) $data[ $key ];
				break;
			}
		}

		if ( empty( $values ) && method_exists( 'WFOB_Common', 'get_product_settings' ) ) {
			$values = (array) WFOB_Common::get_product_settings( $bump_id );
		}

		$fields = array();

		foreach ( $defs as $def ) {
			$def = (array) $def;
			$key = isset( $def['key'] ) ? (string) $def['key'] : '';

			if ( '' === $key ) {
				continue;
			}

			$field = array(
				'key'   => $key,
				'type'  => in_array( isset( $def['type'] ) ? $def['type'] : '', array( 'radios', 'select' ), true ) ? 'select' : 'products',
				'value' => isset( $values[ $key ] ) ? $values[ $key ] : '',
			);

			if ( ! empty( $def['label'] ) && is_string( $def['label'] ) ) {
				$field['label'] = wp_strip_all_tags( $def['label'] );
			}

			$hint = isset( $def['hint'] ) && '' !== $def['hint'] ? $def['hint'] : ( isset( $def['hintLabel'] ) ? $def['hintLabel'] : '' );

			if ( is_string( $hint ) && '' !== $hint ) {
				$field['hint'] = wp_strip_all_tags( $hint );
			}

			$toggler = self::bump_field_toggler( $def );

			if ( null !== $toggler ) {
				$field['toggler'] = $toggler;
			}

			if ( ! empty( $def['values'] ) && is_array( $def['values'] ) ) {
				$options = self::bump_field_options( $def, 'values' );

				if ( ! empty( $options ) ) {
					$field['options'] = $options;
				}
			}

			$fields[] = $field;
		}

		// The module changed shape: fall back to bare values.
		if ( empty( $fields ) ) {
			foreach ( array( 'bump_action_type', 'bump_replace_type', 'selected_replace_product' ) as $key ) {
				if ( array_key_exists( $key, $values ) ) {
					$fields[] = array(
						'key'   => $key,
						'type'  => 'selected_replace_product' === $key ? 'products' : 'select',
						'value' => $values[ $key ],
					);
				}
			}
		}

		return array( 'fields' => $fields );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_upsell_settings( $input ) {
		if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wfocu_funnel' ), 'upsell' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$rules = FKMCP_Pack_Funnels::response_data( WFFN_REST_UPSELL_API_EndPoint::get_instance()->get_upsell_rules( FKMCP_Pack_Funnels::rest_request( array( 'step_id' => $step->ID ) ) ) );

		$settings = FKMCP_Pack_Funnels::step_tabs( $step->ID, 'wc_upsells' );

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		// Offers are not funnel steps: the generic settings route's step guard
		// would wp_send_json()-die on their ids, so their tabs are built by the
		// controller's public tab builder instead.
		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );
		$offers  = array();

		foreach ( self::offer_posts( $step->ID ) as $offer ) {
			$tabs = array();

			if ( $modules && class_exists( 'WFOCU_Common' ) && method_exists( $modules, 'offer_get_customsettings' ) ) {
				$tabs = FKMCP_Pack_Funnels::values_of( (array) $modules->offer_get_customsettings( WFOCU_Common::get_offer( $offer->ID ) ) );
			}

			$offers[] = array(
				'offer_id' => (int) $offer->ID,
				'title'    => (string) $offer->post_title,
				'settings' => $tabs,
			);
		}

		return FKMCP_Pack_Funnels::mask_secrets(
			array(
				'step_id'  => (int) $step->ID,
				'rules'    => isset( $rules['data']['rules'] ) ? (array) $rules['data']['rules'] : array(),
				'settings' => $settings,
				'offers'   => $offers,
			)
		);
	}

	/** Price hooks the bump editor's reader strips with remove_all_wc_price_action(). */
	const PRICE_HOOKS = array(
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_price',
		'woocommerce_product_variation_get_price',
		'woocommerce_product_variation_get_sale_price',
		'woocommerce_product_variation_get_regular_price',
		'raw_woocommerce_price',
		'woocommerce_variation_prices',
		'woocommerce_get_price_html',
		'woocommerce_price_html',
	);

	/**
	 * Everything hooked to the price filters right now.
	 *
	 * @return array<string, \WP_Hook|null>
	 */
	private static function price_hooks_snapshot() {
		global $wp_filter;

		$snapshot = array();

		foreach ( self::PRICE_HOOKS as $hook ) {
			$snapshot[ $hook ] = isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof \WP_Hook ? clone $wp_filter[ $hook ] : null;
		}

		return $snapshot;
	}

	/**
	 * Puts back what price_hooks_snapshot() recorded.
	 *
	 * @param array<string, \WP_Hook|null> $snapshot Recorded hooks.
	 *
	 * @return void
	 */
	private static function price_hooks_restore( array $snapshot ) {
		global $wp_filter;

		foreach ( $snapshot as $hook => $stored ) {
			if ( null === $stored ) {
				unset( $wp_filter[ $hook ] );

				continue;
			}

			$wp_filter[ $hook ] = $stored;
		}
	}

	/**
	 * The toggler a field declares, as the screen's {key, value} pair, or null
	 * when it is always shown. Read the same way by every bump reader.
	 *
	 * @param array $def Field definition from the module's schema.
	 *
	 * @return array|null
	 */
	private static function bump_field_toggler( array $def ) {
		$toggler = isset( $def['toggler'] ) && is_array( (array) $def['toggler'] ) ? (array) $def['toggler'] : array();

		if ( isset( $toggler['key'] ) && '' !== (string) $toggler['key'] ) {
			return array(
				'key'   => (string) $toggler['key'],
				'value' => isset( $toggler['value'] ) ? $toggler['value'] : true,
			);
		}

		if ( ! empty( $def['ref_key'] ) && is_string( $def['ref_key'] ) ) {
			return array(
				'key'   => $def['ref_key'],
				'value' => 'true',
			);
		}

		return null;
	}

	/**
	 * A field's choices as {value, label} rows.
	 *
	 * @param array  $def    Field definition.
	 * @param string $source Key the choices sit under ('options' or 'values').
	 *
	 * @return array
	 */
	private static function bump_field_options( array $def, $source = 'options' ) {
		$options = array();
		$rows    = isset( $def[ $source ] ) && is_array( $def[ $source ] ) ? $def[ $source ] : array();

		foreach ( $rows as $option ) {
			$option = (array) $option;

			if ( isset( $option['value'] ) ) {
				$options[] = array(
					'value' => (string) $option['value'],
					'label' => isset( $option['label'] ) ? wp_strip_all_tags( (string) $option['label'] ) : (string) $option['value'],
				);
			}
		}

		return $options;
	}

	/**
	 * Offer posts of an upsell Step, in display order. Offer posts carry
	 * _funnel_id = the Step's id; the admin REST lookup is not used because it
	 * only works inside an admin request and answers empty elsewhere.
	 *
	 * @param int    $step_id Upsell Step id.
	 * @param string $fields  'ids' for a list of ids, '' for post objects.
	 *
	 * @return array
	 */
	private static function offer_posts( $step_id, $fields = '' ) {
		return get_posts(
			array(
				'post_type'      => 'wfocu_offer',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'fields'         => $fields,
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
				'meta_key'       => '_funnel_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $step_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function duplicate_funnel( $input ) {
		return FKMCP_Pack_Funnels::write( 'duplicate-funnel', $input, array( $this, 'do_duplicate_funnel' ) );
	}

	/**
	 * The controller's duplicate_funnel() builds the copy from
	 * WFFN_Admin::get_funnel() - the request-cached funnel - and add_funnel()
	 * updates in place once an object has an id: with any funnel loaded earlier
	 * in the request it would rename that one and copy the steps into it. Its
	 * loop is repeated here on a fresh object instead.
	 */
	public function do_duplicate_funnel( $input ) {
		$input  = (array) $input;
		$source = FKMCP_Pack_Funnels::funnel_from( $input );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		if ( ! function_exists( 'WFFN_Core' ) || ! isset( WFFN_Core()->steps ) || ! method_exists( WFFN_Core(), 'get_dB' ) ) {
			return new WP_Error( 'fkmcp_funnels_unavailable', 'Funnel Builder is not loaded on this request.' );
		}

		$source_id = (int) $source->get_id();
		$title     = FKMCP_Pack_Funnels::clean_title( $input );

		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$title = (string) $title;

		if ( '' === $title ) {
			/* translators: %s: title of the funnel being duplicated. */
			$title = sprintf( __( '%s - Copy', 'funnel-builder-powerpack' ), $source->get_title() );
		}

		$copy    = new WFFN_Funnel( 0 );
		$copy_id = (int) $copy->add_funnel(
			array(
				'title'  => $title,
				'desc'   => $source->get_desc(),
				'status' => 1,
			)
		);

		if ( $copy_id <= 0 ) {
			return new WP_Error( 'fkmcp_duplicate_failed', 'Funnel creation failed.' );
		}

		$copy->set_id( $copy_id );

		do_action( 'wffn_duplicate_funnel', $copy, $source );

		foreach ( (array) $source->get_steps() as $step ) {
			$step    = (array) $step;
			$type    = isset( $step['type'] ) ? (string) $step['type'] : '';
			$step_id = isset( $step['id'] ) ? (int) $step['id'] : 0;

			if ( '' === $type || ! $step_id ) {
				continue;
			}

			$integration = WFFN_Core()->steps->get_integration_object( $type );

			if ( ! $integration instanceof WFFN_Step ) {
				continue;
			}

			if ( class_exists( 'BWF_Admin_Breadcrumbs' ) ) {
				BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $copy_id );
			}

			$integration->duplicate_step(
				$copy_id,
				$step_id,
				array(
					'duplicate_funnel_id' => $source_id,
					'original_id'         => $step_id,
					'step_id'             => $step_id,
					'_data'               => array(
						'title'  => $integration->get_entity_title( $step_id ),
						'desc'   => $integration->get_entity_description( $step_id ),
						'status' => $integration->get_entity_status( $step_id ),
						'edit'   => $integration->get_entity_edit_link( $step_id ),
						'view'   => $integration->get_entity_view_link( $step_id ),
					),
				)
			);
		}

		foreach ( (array) WFFN_Core()->get_dB()->get_meta( $source_id ) as $key => $meta ) {
			if ( in_array( $key, array( '_is_global', '_last_updated_on' ), true ) || ! is_array( $meta ) || ! array_key_exists( 0, $meta ) ) {
				continue;
			}

			WFFN_Core()->get_dB()->update_meta( $copy_id, $key, maybe_unserialize( $meta[0] ) );
		}

		return FKMCP_Pack_Funnels::funnel_summary( $copy_id, array( 'source_funnel_id' => $source_id ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function create_order_bump( $input ) {
		return FKMCP_Pack_Funnels::write( 'create-order-bump', $input, array( $this, 'do_create_substep' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_create_substep( $input ) {
		$input = (array) $input;
		$type  = isset( $input['type'] ) ? (string) $input['type'] : '';

		if ( 'wc_order_bump' !== $type ) {
			return new WP_Error( 'fkmcp_invalid_input', 'type must be wc_order_bump.' );
		}

		if ( ! function_exists( 'WFFN_Core' ) || ! isset( WFFN_Core()->substeps ) || ! is_object( WFFN_Core()->substeps->get_integration_object( $type ) ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$step = FKMCP_Pack_Funnels::typed_step( $input, 'wfacp_checkout', 'checkout' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$funnel_id = (int) get_post_meta( $step->ID, '_bwf_in_funnel', true );

		if ( ! $funnel_id ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Checkout step #%d does not belong to a funnel.', $step->ID ) );
		}

		$title = FKMCP_Pack_Funnels::clean_title( $input );

		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$title = (string) $title;

		// The admin's Add Bump silently defaults to Skin 1; over MCP the look is
		// chosen up front, create-funnel-style: refuse until the agent has asked
		// the user which skin they want.
		$skin   = isset( $input['skin'] ) ? sanitize_key( (string) $input['skin'] ) : '';
		$models = class_exists( 'WFOB_Bump_Fc' ) ? (array) WFOB_Bump_Fc::get_default_models() : array();

		if ( ! empty( $models ) ) {
			$rows  = array();
			$looks = array();
			$texts = self::bump_skin_texts();

			foreach ( (array) apply_filters( 'wfob_default_preview_templates', self::BUMP_PICKER_SKINS ) as $id ) {
				if ( isset( $models[ $id ] ) ) {
					$ref     = self::bump_skin_ref( $id, $models );
					$rows[]  = $id . ' (' . ( '' !== $ref['label'] ? $ref['label'] : $ref['name'] ) . ')';
					$looks[] = $id . ': ' . rtrim( empty( $texts[ $id ]['description'] ) ? ( '' !== $ref['label'] ? $ref['label'] : $ref['name'] ) : $texts[ $id ]['description'], '.' );
				}
			}

			// The looks travel with the refusal: list-bump-skins needs a bump that exists, and this is the first one.
			if ( '' === $skin ) {
				return new WP_Error( 'fkmcp_needs_skin', sprintf( 'Ask the user which skin the Order Bump should wear, then call again with skin. Options: %1$s. What each looks like - %2$s. list-bump-skins without bump_id gives the same list.', implode( ', ', $rows ), implode( '; ', $looks ) ) );
			}

			if ( ! isset( $models[ $skin ] ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'skin must be one of: %s.', implode( ', ', $rows ) ) );
			}
		} else {
			$skin = '';
		}

		$substeps = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-substeps.php', 'WFFN_REST_Substeps' );

		if ( ! $substeps ) {
			return new WP_Error( 'fkmcp_funnels_unavailable', 'The sub-steps provider is not loaded on this request.' );
		}

		$args = array(
			'funnel_id' => $funnel_id,
			'step_id'   => (int) $step->ID,
			'type'      => $type,
		);

		if ( '' !== $title ) {
			$args['title'] = $title;
		}

		$response = FKMCP_Pack_Funnels::response_data( $substeps->create_substep( FKMCP_Pack_Funnels::rest_request( $args ) ) );
		// The populated sub-step row nests stdClass objects - flatten them to arrays.
		$data = isset( $response['data'] ) ? (array) json_decode( (string) wp_json_encode( $response['data'] ), true ) : array();
		$id   = isset( $data['id'] ) ? (int) $data['id'] : 0;

		if ( empty( $response['status'] ) || ! $id ) {
			return new WP_Error( 'fkmcp_create_failed', 'The Order Bump could not be created.' );
		}

		$integration = WFFN_Core()->substeps->get_integration_object( $type );

		// Dress the fresh bump in the chosen skin so its design meta exists
		// from the start (a bare bump only wears layout_1 by fallback).
		if ( '' !== $skin && class_exists( 'WFFN_REST_BUMP_API_EndPoint' ) ) {
			WFFN_REST_BUMP_API_EndPoint::get_instance()->import_skin(
				FKMCP_Pack_Funnels::rest_request(
					array(
						'bump_id' => $id,
						'layout'  => $skin,
					)
				)
			);
			self::bump_complete_import( $id );
		}

		$result = array(
			'substep_id' => $id,
			'type'       => $type,
			'title'      => isset( $data['title'] ) ? (string) $data['title'] : ( isset( $data['_data']['title'] ) ? (string) $data['_data']['title'] : (string) get_the_title( $id ) ),
			'step_id'    => (int) $step->ID,
			'funnel_id'  => $funnel_id,
			'links'      => array( 'edit' => is_object( $integration ) && method_exists( $integration, 'get_entity_edit_link' ) ? (string) $integration->get_entity_edit_link( $id ) : '' ),
		);

		if ( '' !== $skin ) {
			$result['skin'] = self::bump_skin_ref( $skin, $models );
		}

		return $result;
	}

	/** Product types an Order Bump may offer - the admin search's default list (wfob_offer_product_types). */
	const BUMP_PRODUCT_TYPES = array( 'simple', 'variable', 'course', 'variation', 'subscription', 'variable-subscription', 'subscription_variation', 'bundle', 'yith_bundle', 'woosb' );

	/** Product types an Upsell Offer may sell (wfocu_offer_product_types). */
	const OFFER_PRODUCT_TYPES = array( 'simple', 'variable', 'variation' );

	/** Discount types an Offer's product row accepts: the admin's short names and the stored keys. */
	/** The engine's switch knows only these; the module maps its legacy 'percentage'/'fixed' to the *_on_reg pair on read. */
	const OFFER_DISCOUNT_TYPES = array( 'percentage_on_reg', 'fixed_on_reg', 'percentage_on_sale', 'fixed_on_sale' );

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_bump_products( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-bump-products', $input, array( $this, 'do_update_bump_products' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_bump_products( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFFN_REST_BUMP_API_EndPoint' ) || ! class_exists( 'WFOB_Common' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

		if ( is_wp_error( $bump ) ) {
			return $bump;
		}

		$endpoint = WFFN_REST_BUMP_API_EndPoint::get_instance();
		$read     = static function () use ( $endpoint, $bump ) {
			$data = FKMCP_Pack_Funnels::response_data( $endpoint->wfob_get_products( FKMCP_Pack_Funnels::rest_request( array( 'step_id' => $bump->ID ) ) ) );

			return isset( $data['data'] ) ? (array) json_decode( (string) wp_json_encode( $data['data'] ), true ) : array();
		};

		$before      = $read();
		$rows        = isset( $before['products'] ) ? (array) $before['products'] : array();
		$settings_in = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$patch       = FKMCP_Pack_Funnels::products_patch( $input, $rows, FKMCP_Pack_Funnels::DISCOUNT_TYPES, (array) apply_filters( 'wfob_offer_product_types', self::BUMP_PRODUCT_TYPES ), ! empty( $settings_in ) );

		if ( is_wp_error( $patch ) ) {
			return $patch;
		}

		$final = count( $rows ) + count( $patch['add'] ) - count( $patch['remove'] );

		if ( $final < 1 ) {
			return new WP_Error( 'fkmcp_invalid_input', 'An Order Bump needs at least one product: add the replacement first, or disable/delete the bump in the admin instead of emptying it.' );
		}

		if ( ! empty( $patch['add'] ) ) {
			$added = FKMCP_Pack_Funnels::controller_call(
				static function () use ( $endpoint, $bump, $patch ) {
					return $endpoint->wfob_add_product(
						FKMCP_Pack_Funnels::body_request(
							array( 'step_id' => $bump->ID ),
							array(
								'products'           => array_keys( $patch['add'] ),
								'products_meta_data' => array(),
							)
						)
					);
				}
			);

			if ( is_wp_error( $added ) || empty( $added['success'] ) ) {
				return new WP_Error( 'fkmcp_update_failed', is_wp_error( $added ) ? $added->get_error_message() : ( isset( $added['msg'] ) ? (string) $added['msg'] : 'The products could not be added.' ) );
			}

			$after = $read();
			$rows  = isset( $after['products'] ) ? (array) $after['products'] : array();
		}

		$rows = FKMCP_Pack_Funnels::patch_rows( $rows, $patch );

		// The save route replaces products AND settings with what it is given; re-send the stored settings.
		$settings = isset( $before['InitialValue'] ) && is_array( $before['InitialValue'] ) ? $before['InitialValue'] : array();

		if ( ! empty( $settings['selected_replace_product'] ) && is_array( $settings['selected_replace_product'] ) ) {
			// The route reads each entry's label (the picker's shape); stored rows carry product.
			foreach ( $settings['selected_replace_product'] as $i => $entry ) {
				if ( is_array( $entry ) && ! isset( $entry['label'] ) && isset( $entry['product'] ) ) {
					$settings['selected_replace_product'][ $i ]['label'] = $entry['product'];
				}
			}
		}

		$settings_patch = array();

		if ( ! empty( $settings_in ) ) {
			// The screen's own field list validates the patch; the position hook rides here too but belongs to update-bump-settings.
			$tab            = isset( $before['settings'] ) && is_array( $before['settings'] ) ? $before['settings'] : array();
			$tabs           = isset( $tab['fields'] ) ? array( 'products' => $tab ) : $tab;
			$settings_patch = FKMCP_Pack_Funnels::step_patch( $tabs, $settings_in, self::BUMP_PRODUCT_SETTING_KEYS, array( 'order_bump_position_hooks' ) );

			if ( is_wp_error( $settings_patch ) ) {
				return $settings_patch;
			}

			if ( array_key_exists( 'selected_replace_product', $settings_patch ) ) {
				$settings_patch['selected_replace_product'] = self::replace_product_rows( $settings_patch['selected_replace_product'] );

				if ( is_wp_error( $settings_patch['selected_replace_product'] ) ) {
					return $settings_patch['selected_replace_product'];
				}
			}

			$settings = array_merge( $settings, $settings_patch );
		}

		$saved = FKMCP_Pack_Funnels::controller_call(
			static function () use ( $endpoint, $bump, $rows, $settings ) {
				return $endpoint->wfob_save_products(
					FKMCP_Pack_Funnels::body_request(
						array( 'step_id' => $bump->ID ),
						array(
							'products' => array_values( $rows ),
							'settings' => $settings,
						)
					)
				);
			}
		);

		if ( is_wp_error( $saved ) || empty( $saved['success'] ) ) {
			return new WP_Error( 'fkmcp_update_failed', is_wp_error( $saved ) ? $saved->get_error_message() : ( isset( $saved['msg'] ) ? (string) $saved['msg'] : 'The products could not be saved.' ) );
		}

		$result = $this->get_order_bump( array( 'bump_id' => $bump->ID ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$out = array(
			'bump_id'          => (int) $bump->ID,
			'products'         => isset( $result['products'] ) ? (array) $result['products'] : array(),
			'product_settings' => isset( $result['product_settings'] ) ? (array) $result['product_settings'] : array(),
			'changed'          => FKMCP_Pack_Funnels::changed( $patch ),
		);

		if ( ! empty( $settings_patch ) ) {
			$out['settings'] = $settings_patch;
		}

		return $out;
	}

	/** Product ids (or the rows a read shows) => the {id,label,product} rows the bump's save route reads for a replaced cart item. */
	private static function replace_product_rows( $value ) {
		$rows = array();

		foreach ( (array) $value as $entry ) {
			$entry = is_object( $entry ) ? (array) $entry : $entry;
			$id    = is_array( $entry ) ? ( isset( $entry['id'] ) ? $entry['id'] : ( isset( $entry['product_id'] ) ? $entry['product_id'] : 0 ) ) : $entry;
			$id    = absint( $id );

			$product = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;

			if ( ! $product ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'selected_replace_product: %s is not a WooCommerce product id.', is_scalar( $id ) ? $id : '?' ) );
			}

			$name   = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : (string) $id;
			$rows[] = array(
				'id'      => $id,
				'label'   => $name,
				'product' => $name,
			);
		}

		return $rows;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_offer_products( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-offer-products', $input, array( $this, 'do_update_offer_products' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_offer_products( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) || ! class_exists( 'WFOCU_Common' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$offer = self::typed_post( $input, 'offer_id', array( 'wfocu_offer' ), 'upsell offer' );

		if ( is_wp_error( $offer ) ) {
			return $offer;
		}

		$step_id = (int) get_post_meta( $offer->ID, '_funnel_id', true );

		if ( ! $step_id ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Offer #%d does not belong to an upsell step.', $offer->ID ) );
		}

		$endpoint = WFFN_REST_UPSELL_API_EndPoint::get_instance();
		$details  = FKMCP_Pack_Funnels::response_data( $endpoint->get_wfocu_offer_details( FKMCP_Pack_Funnels::rest_request( array( 'step_id' => $offer->ID ) ) ) );
		$details  = isset( $details['data'] ) ? (array) json_decode( (string) wp_json_encode( $details['data'] ), true ) : array();
		$fields   = isset( $details['fields'] ) ? (array) $details['fields'] : array();
		$rows     = array();

		// The offer's rows carry their pricing in a sibling map keyed by the product hash; the save route wants it on the row.
		foreach ( ( isset( $details['products'] ) ? (array) $details['products'] : array() ) as $row ) {
			$row    = (array) $row;
			$hash   = isset( $row['id'] ) ? (string) $row['id'] : '';
			$rows[] = array_merge(
				array(
					'discount_amount'    => 0,
					'discount_type'      => 'percentage_on_reg',
					'quantity'           => 1,
					'shipping_cost_flat' => 0,
				),
				isset( $fields[ $hash ] ) ? (array) $fields[ $hash ] : array(),
				$row
			);
		}

		$settings_in = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$patch       = FKMCP_Pack_Funnels::products_patch( $input, $rows, self::OFFER_DISCOUNT_TYPES, (array) apply_filters( 'wfocu_offer_product_types', self::OFFER_PRODUCT_TYPES ), ! empty( $settings_in ) );

		if ( is_wp_error( $patch ) ) {
			return $patch;
		}

		$final = count( $rows ) + count( $patch['add'] ) - count( $patch['remove'] );

		if ( $final > 1 && method_exists( 'WFOCU_Common', 'is_add_on_exist' ) && ! WFOCU_Common::is_add_on_exist( 'MultiProduct' ) ) {
			return new WP_Error( 'fkmcp_pro_required', 'More than one product per Offer needs the Multi Product add-on, which is not active.' );
		}

		if ( $final < 1 ) {
			return new WP_Error( 'fkmcp_invalid_input', 'An Offer needs at least one product: add the replacement first, or disable/delete the Offer in the admin instead of emptying it.' );
		}

		foreach ( $patch['add'] as $product_id => $options ) {
			// New rows the way the admin's add builds them: keyed by the product hash, defaults, no variation picked.
			$rows[] = array_merge(
				array(
					'id'                 => (string) WFOCU_Common::get_product_id_hash( $step_id, $offer->ID, $product_id ),
					'key'                => (string) $product_id,
					'discount_amount'    => 0,
					'discount_type'      => 'percentage_on_reg',
					'quantity'           => 1,
					'shipping_cost_flat' => 0,
					'variations'         => array(),
					'checkVarient'       => array(),
					'radioVarient'       => '',
				),
				$options
			);
		}

		$rows = FKMCP_Pack_Funnels::patch_rows( $rows, $patch );
		// The save rewrites the whole record: the stored settings ride along (empty until the screen saved once), the patch on top.
		$settings       = isset( $details['InitialValue'] ) && is_array( $details['InitialValue'] ) ? $details['InitialValue'] : array();
		$settings_patch = array();

		if ( ! empty( $settings_in ) ) {
			$tab      = isset( $details['settings'] ) && is_array( $details['settings'] ) ? $details['settings'] : array();
			$shown    = isset( $tab['fields_to_show'] ) && is_array( $tab['fields_to_show'] ) ? array_map( 'strval', $tab['fields_to_show'] ) : self::OFFER_PRODUCT_SETTING_KEYS;
			$writable = array_values( array_intersect( self::OFFER_PRODUCT_SETTING_KEYS, $shown ) );
			$blocked  = array();

			foreach ( isset( $tab['fields'] ) ? (array) $tab['fields'] : array() as $field ) {
				$field = (array) $field;

				if ( ! empty( $field['key'] ) && ! in_array( (string) $field['key'], $writable, true ) ) {
					$blocked[] = (string) $field['key'];
				}
			}

			$settings_patch = FKMCP_Pack_Funnels::step_patch( array( 'products' => $tab ), $settings_in, $writable, array_merge( $blocked, array_diff( array_keys( $settings ), $writable ) ) );

			if ( is_wp_error( $settings_patch ) ) {
				return $settings_patch;
			}

			$settings = array_merge( $settings, $settings_patch );
		}

		$saved = FKMCP_Pack_Funnels::controller_call(
			static function () use ( $endpoint, $offer, $rows, $settings ) {
				return $endpoint->wfocu_save_offer_products(
					FKMCP_Pack_Funnels::body_request(
						array( 'step_id' => $offer->ID ),
						array(
							'products' => array_values( $rows ),
							'settings' => $settings,
						)
					)
				);
			}
		);

		if ( is_wp_error( $saved ) || empty( $saved['success'] ) ) {
			return new WP_Error( 'fkmcp_update_failed', is_wp_error( $saved ) ? $saved->get_error_message() : ( isset( $saved['msg'] ) && '' !== $saved['msg'] ? (string) $saved['msg'] : 'The products could not be saved.' ) );
		}

		$offers   = $this->get_upsell_offers( array( 'step_id' => $step_id ) );
		$products = array();

		if ( ! is_wp_error( $offers ) ) {
			foreach ( ( isset( $offers['items'] ) ? (array) $offers['items'] : ( isset( $offers['offers'] ) ? (array) $offers['offers'] : array() ) ) as $entry ) {
				$entry = (array) $entry;

				$entry_id = isset( $entry['id'] ) ? (int) $entry['id'] : ( isset( $entry['offer_id'] ) ? (int) $entry['offer_id'] : 0 );

				if ( $entry_id === (int) $offer->ID ) {
					$products = isset( $entry['products'] ) ? (array) $entry['products'] : array();
				}
			}
		}

		$out = array(
			'offer_id' => (int) $offer->ID,
			'step_id'  => $step_id,
			'products' => $products,
			'changed'  => FKMCP_Pack_Funnels::changed( $patch ),
		);

		if ( ! empty( $settings_patch ) ) {
			$out['settings'] = $settings_patch;
		}

		return $out;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function create_offer( $input ) {
		return FKMCP_Pack_Funnels::write( 'create-offer', $input, array( $this, 'do_create_offer' ) );
	}

	/** The admin's Add Offer: POST funnel-upsell/{step}/offers -> WFFN_REST_UPSELL_API_EndPoint::wfocu_add_offer(). */
	public function do_create_offer( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) || ! class_exists( 'WFOCU_Common' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wfocu_funnel' ), 'upsell step' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$type = isset( $input['type'] ) && '' !== $input['type'] ? (string) $input['type'] : 'upsell';

		if ( ! in_array( $type, array( 'upsell', 'downsell' ), true ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'type must be upsell or downsell.' );
		}

		$title = FKMCP_Pack_Funnels::clean_title( $input );

		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$title = (string) $title;

		if ( '' === $title ) {
			$title = _x( 'Offer', 'default title of a newly created upsell offer', 'funnel-builder-powerpack' );
		}

		$template = isset( $input['template'] ) ? sanitize_text_field( (string) $input['template'] ) : '';
		$builder  = '';

		if ( '' !== $template ) {
			$picked = FKMCP_Pack_Funnels::pick_template( 'upsell', $input, ', or omit it only if the user wants a blank offer page' );

			if ( is_wp_error( $picked ) ) {
				return $picked;
			}

			$template = $picked['template'];
			$builder  = $picked['builder'];
		} elseif ( isset( $input['builder'] ) && '' !== $input['builder'] ) {
			return new WP_Error( 'fkmcp_invalid_input', 'builder only applies together with template.' );
		}

		$endpoint = WFFN_REST_UPSELL_API_EndPoint::get_instance();
		$params   = array( 'step_id' => (int) $step->ID );

		if ( '' !== $template ) {
			$params['template'] = $template;
			$params['builder']  = $builder;
		}

		$response = FKMCP_Pack_Funnels::controller_call(
			static function () use ( $endpoint, $params, $title, $type ) {
				return $endpoint->wfocu_add_offer(
					FKMCP_Pack_Funnels::body_request(
						$params,
						array(
							'title'     => $title,
							'step_type' => $type,
						)
					)
				);
			}
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_create_failed', $response->get_error_message() );
		}

		$data     = isset( $response['data'] ) ? (array) json_decode( (string) wp_json_encode( $response['data'] ), true ) : array();
		$offer_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

		if ( empty( $response['status'] ) || ! $offer_id ) {
			$message = isset( $response['msg'] ) && '' !== $response['msg'] ? (string) $response['msg'] : ( isset( $response['builders_options']['message'] ) ? (string) $response['builders_options']['message'] : 'The offer could not be created.' );

			return new WP_Error( 'fkmcp_create_failed', wp_strip_all_tags( $message ) );
		}

		$entity = isset( $data['_data'] ) ? (array) $data['_data'] : array();

		return array(
			'offer_id' => $offer_id,
			'step_id'  => (int) $step->ID,
			'title'    => isset( $entity['title'] ) && '' !== $entity['title'] ? (string) $entity['title'] : (string) get_the_title( $offer_id ),
			'type'     => (string) ( get_post_meta( $offer_id, '_offer_type', true ) ? get_post_meta( $offer_id, '_offer_type', true ) : $type ),
			'template' => $template,
			'links'    => array(
				'edit' => isset( $entity['edit'] ) ? (string) $entity['edit'] : '',
				'view' => isset( $entity['view'] ) ? (string) $entity['view'] : (string) get_permalink( $offer_id ),
			),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_offer_template( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-offer-template', $input, array( $this, 'do_update_offer_template' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_offer_template( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFOCU_Common' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$offer = self::typed_post( $input, 'offer_id', array( 'wfocu_offer' ), 'offer' );

		if ( is_wp_error( $offer ) ) {
			return $offer;
		}

		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );

		if ( ! $modules ) {
			return new WP_Error( 'fkmcp_modules_unavailable', 'The design provider is not loaded on this request.' );
		}

		$picked = FKMCP_Pack_Funnels::pick_template( 'upsell', $input );

		if ( is_wp_error( $picked ) ) {
			return $picked;
		}

		$offer_id = (int) $offer->ID;
		$before   = self::offer_design( $offer_id );
		$request  = FKMCP_Pack_Funnels::rest_request(
			array(
				'step_id' => $offer_id,
				'type'    => 'offer',
				'design'  => wp_json_encode(
					array(
						'builder'  => $picked['builder'],
						'template' => $picked['template'],
					)
				),
			),
			'POST'
		);
		$response = FKMCP_Pack_Funnels::controller_call(
			static function () use ( $modules, $request ) {
				return $modules->save_design( $request );
			}
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fkmcp_update_failed', $response->get_error_message() );
		}

		$response = FKMCP_Pack_Funnels::response_data( $response );

		if ( empty( $response['success'] ) ) {
			return FKMCP_Pack_Funnels::design_error( $response, $picked['builder'] );
		}

		$step_id     = (int) get_post_meta( $offer_id, '_funnel_id', true );
		$integration = WFFN_Core()->steps->get_integration_object( 'wc_upsells' );

		return array(
			'offer_id' => $offer_id,
			'step_id'  => $step_id,
			'title'    => (string) get_the_title( $offer_id ),
			'template' => self::offer_design( $offer_id ),
			'previous' => $before,
			'links'    => array(
				'edit' => is_object( $integration ) && method_exists( $integration, 'get_entity_edit_link' ) && $step_id ? (string) $integration->get_entity_edit_link( $step_id ) : '',
				'view' => (string) get_permalink( $offer_id ),
			),
		);
	}

	/**
	 * The page builder an Offer's design was made with, plus builder_inactive
	 * when that builder's plugin is off - by Funnel Builder's own check.
	 *
	 * @param int $offer_id Offer id.
	 *
	 * @return array builder (+ builder_inactive), or empty before a design is applied.
	 */
	public static function offer_builder( $offer_id ) {
		$design = self::offer_design( $offer_id );

		// builder_row() is Funnel Builder's: an older one without it gives no row.
		return '' === $design['builder'] || ! method_exists( 'FKMCP_Pack_Funnels', 'builder_row' ) ? array() : FKMCP_Pack_Funnels::builder_row( $design['builder'], $design['slug'], 'Offer' );
	}

	/** The design an Offer shows now, from its settings object. */
	public static function offer_design( $offer_id ) {
		$meta = (array) get_post_meta( $offer_id, '_wfocu_setting', true );

		return array(
			'slug'    => isset( $meta['template'] ) ? (string) $meta['template'] : '',
			'builder' => isset( $meta['template_group'] ) ? (string) $meta['template_group'] : '',
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_upsell_settings( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-upsell-settings', $input, array( $this, 'do_update_upsell_settings' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_upsell_settings( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFOCU_Common' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wfocu_funnel' ), 'upsell' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );

		if ( ! $modules ) {
			return new WP_Error( 'fkmcp_modules_unavailable', 'The step settings provider is not loaded on this request.' );
		}

		$tabs = FKMCP_Pack_Funnels::step_tab_schema( $modules, $step->ID, 'wc_upsells' );

		if ( is_wp_error( $tabs ) ) {
			return $tabs;
		}

		$patch = FKMCP_Pack_Funnels::step_patch( $tabs, isset( $input['settings'] ) ? $input['settings'] : null );

		if ( is_wp_error( $patch ) ) {
			return $patch;
		}

		// The route replaces the stored map and fills every missing key with its default: stored + patch goes, so nothing resets.
		$stored = get_post_meta( $step->ID, '_wfocu_settings', true );
		$stored = is_array( $stored ) ? $stored : ( is_object( $stored ) ? (array) $stored : array() );
		$map    = array_merge( $stored, $patch );

		// The runtime cancels the main order only for a new-order upsell; the admin hides the switch otherwise.
		if ( isset( $patch['is_cancel_order'] ) && 'yes' === $patch['is_cancel_order'] && ( ! isset( $map['order_behavior'] ) || 'create_order' !== $map['order_behavior'] ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'is_cancel_order=yes works only with order_behavior=create_order: pass both.' );
		}

		$saved = FKMCP_Pack_Funnels::save_step_settings( $modules, $step->ID, 'wc_upsells', $map );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return FKMCP_Pack_Funnels::mask_secrets(
			array(
				'step_id'  => (int) $step->ID,
				'changed'  => $patch,
				'settings' => FKMCP_Pack_Funnels::patched_values( $tabs, $patch ),
			)
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_offer_settings( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-offer-settings', $input, array( $this, 'do_update_offer_settings' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_offer_settings( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFOCU_Common' ) ) {
			return new WP_Error( 'fkmcp_upsells_unavailable', 'The Upsells module is not active.' );
		}

		$offer = self::typed_post( $input, 'offer_id', array( 'wfocu_offer' ), 'offer' );

		if ( is_wp_error( $offer ) ) {
			return $offer;
		}

		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );

		if ( ! $modules || ! method_exists( $modules, 'offer_get_customsettings' ) ) {
			return new WP_Error( 'fkmcp_modules_unavailable', 'The offer settings provider is not loaded on this request.' );
		}

		// Offers are not funnel steps: the generic read would die on their ids, the public tab builder is the read.
		$tabs  = (array) $modules->offer_get_customsettings( WFOCU_Common::get_offer( $offer->ID ) );
		$patch = FKMCP_Pack_Funnels::step_patch( $tabs, isset( $input['settings'] ) ? $input['settings'] : null );

		if ( is_wp_error( $patch ) ) {
			return $patch;
		}

		// The route merges into the offer's stored settings object: only the patch goes.
		$saved = FKMCP_Pack_Funnels::save_step_settings( $modules, $offer->ID, 'offer', $patch );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return FKMCP_Pack_Funnels::mask_secrets(
			array(
				'offer_id' => (int) $offer->ID,
				'step_id'  => (int) get_post_meta( $offer->ID, '_funnel_id', true ),
				'changed'  => $patch,
				'settings' => FKMCP_Pack_Funnels::patched_values( $tabs, $patch ),
			)
		);
	}

	/**
	 * The admin's offer toggle writes two places: the runtime reads state
	 * from the upsell funnel's _funnel_steps entry, and the admin list reads
	 * the offer's post status - flip both, as its save does.
	 *
	 * @param int  $offer_id
	 * @param bool $enabled
	 *
	 * @return true|WP_Error
	 */
	/**
	 * An Offer hangs off its Upsell Step; its funnel is the Step's. Links
	 * stay with the step link templates - the funnel id is what they need.
	 *
	 * @param array   $context array( funnel_id, links )
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public static function step_context( $context, $post ) {
		list( $funnel_id, $links ) = array_pad( (array) $context, 2, array() );

		if ( $post instanceof WP_Post && 'wfocu_offer' === $post->post_type && ! $funnel_id ) {
			$upsell    = (int) get_post_meta( $post->ID, '_funnel_id', true );
			$funnel_id = $upsell > 0 ? (int) get_post_meta( $upsell, '_bwf_in_funnel', true ) : 0;
		}

		return array( $funnel_id, $links );
	}

	/**
	 * fkmcp_create_step_guidance: create-step's description, on what a new
	 * Upsell Step holds. Agents put the product's name in title and took
	 * the Step for a finished upsell. Its templates are listed as type
	 * upsell, not the step type, and design that first Offer.
	 *
	 * @param string $guidance Guidance so far.
	 *
	 * @return string
	 */
	public static function create_step_guidance( $guidance ) {
		return (string) $guidance . ' For an Upsell Step the Funnel has already, name what its Offers sell (get-upsell-offers) in that question. An Upsell Step\'s templates are list-funnel-templates type=upsell; the one picked designs its first Offer. title names the Step only: a new Upsell Step comes with one Offer that sells nothing until update-offer-products adds the product.';
	}

	/**
	 * fkmcp_create_step_next: create-step's next for a new Upsell Step whose
	 * Offer sells nothing - which Offer, and the calls that add and confirm
	 * its product, and the settings that make the Offer replace the purchase
	 * (agents told users it could not). Kept within WFFN_MCP_Log::MAX_TEXT
	 * (500): longer result strings reach the agent cut short.
	 *
	 * @param string $next     next so far.
	 * @param string $type     The new Step's type.
	 * @param array  $substeps Its sub-step rows by type.
	 * @param string $template Template imported for it, or empty.
	 *
	 * @return string
	 */
	public static function create_step_next( $next, $type, $substeps, $template ) {
		if ( 'wc_upsells' !== $type ) {
			return $next;
		}

		$offers = isset( $substeps['offer'] ) ? (array) $substeps['offer'] : array();
		$empty  = 0;

		foreach ( $offers as $offer ) {
			if ( ! empty( $offer['id'] ) && empty( $offer['product_count'] ) ) {
				$empty = (int) $offer['id'];
				break;
			}
		}

		if ( ! $empty ) {
			return $next;
		}

		$lines = array(
			sprintf( 'Not done: Offer %d sells nothing (title only names the Step). Add the product: search-products for=offer, then update-offer-products offer_id=%d with any discount asked; check get-upsell-offers before calling it done.', $empty, $empty ),
		);

		if ( '' === (string) $template ) {
			$lines[] = 'No design: ask the user to pick from list-funnel-templates type=upsell for update-offer-template.';
		}

		$lines[] = 'To replace the purchase: update-upsell-settings order_behavior=create_order is_cancel_order=yes (accepting the first Offer cancels the whole main order; refund per gateway).';

		return implode( ' ', $lines );
	}

	/**
	 * fkmcp_funnel_ready_offers: a Store Checkout's template can bring an
	 * Order Bump and an Upsell that sell nothing. Funnel Builder asks no
	 * product question there (the checkout sells the cart), so agents told
	 * users to "add an order bump" beside an empty one. Name what came in
	 * empty and ask which products to put in it. Other Funnels
	 * are left alone: their product question names these parts already.
	 *
	 * @param string[] $offers    Questions so far.
	 * @param int      $funnel_id Funnel id.
	 * @param array    $steps     funnel_steps() rows.
	 *
	 * @return string[]
	 */
	public static function store_checkout_offers( $offers, $funnel_id, $steps ) {
		if ( ! class_exists( 'WFFN_Common' ) || (int) WFFN_Common::get_store_checkout_id() !== (int) $funnel_id ) {
			return $offers;
		}

		// Sub-step type => its name for one, and for several.
		$kinds = array(
			'wc_order_bump' => array( 'an Order Bump on the checkout', '%d Order Bumps on the checkout' ),
			'offer'         => array( 'an Upsell offer after payment', '%d Upsell offers after payment' ),
		);
		$empty = array();

		foreach ( (array) $steps as $step ) {
			foreach ( array_keys( $kinds ) as $kind ) {
				foreach ( isset( $step['substeps'][ $kind ] ) ? (array) $step['substeps'][ $kind ] : array() as $row ) {
					if ( isset( $row['product_count'] ) && 0 === (int) $row['product_count'] ) {
						$empty[ $kind ] = isset( $empty[ $kind ] ) ? $empty[ $kind ] + 1 : 1;
					}
				}
			}
		}

		if ( ! $empty ) {
			return $offers;
		}

		$names = array();

		foreach ( $empty as $kind => $count ) {
			$names[] = sprintf( $kinds[ $kind ][ 1 === $count ? 0 : 1 ], $count );
		}

		$total = array_sum( $empty );
		$one   = 1 === $total;

		$offers   = (array) $offers;
		$offers[] = sprintf(
			'The template also added %1$s; %2$s a product yet. Tell me which %3$s to put in %4$s.',
			implode( ' and ', $names ),
			$one ? 'it does not sell' : ( 2 === $total ? 'neither sells' : 'none of them sells' ),
			$one ? 'product' : 'products',
			$one ? 'it' : 'them'
		);

		return $offers;
	}

	/**
	 * fkmcp_import_status_guidance: get-import-status's description, on
	 * acting on the answer to store_checkout_offers()'s question.
	 *
	 * @param string $guidance Guidance so far.
	 *
	 * @return string
	 */
	public static function import_status_guidance( $guidance ) {
		return (string) $guidance . ' An Order Bump or Upsell offer ask_user says sells nothing: on the user\'s answer, add its products with update-bump-products or update-offer-products (search-products for=bump or offer).';
	}

	/**
	 * @param array $handlers
	 *
	 * @return array
	 */
	public static function substep_reorder_handlers( $handlers ) {
		// Offers: the canvas drag's own save - _funnel_steps alone leaves the
		// accept/decline map (_funnel_upsell_downsell) on the old order.
		$handlers['wfocu_funnel'] = static function ( $parent, array $ids ) {
			if ( ! class_exists( 'WFFN_REST_UPSELL_API_EndPoint' ) ) {
				return new WP_Error( 'fkmcp_upsells_unavailable', 'The One Click Upsells module is not active.' );
			}

			$steps = get_post_meta( $parent->ID, '_funnel_steps', true );

			if ( ! is_array( $steps ) ) {
				return new WP_Error( 'fkmcp_invalid_state', 'This Upsell has no offer sequence to reorder.' );
			}

			$by_id = array();

			foreach ( $steps as $entry ) {
				if ( is_array( $entry ) && isset( $entry['id'] ) ) {
					$by_id[ absint( $entry['id'] ) ] = $entry;
				}
			}

			if ( count( $ids ) !== count( $by_id ) || array_diff( $ids, array_keys( $by_id ) ) || array_diff( array_keys( $by_id ), $ids ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'step_ids must list every Offer of this Upsell exactly once: %s.', implode( ', ', array_keys( $by_id ) ) ) );
			}

			WFFN_REST_UPSELL_API_EndPoint::get_instance()->save_upsell_order( $parent->ID, $ids );

			return true;
		};

		// Bumps: a funnel checkout shows its bumps in the funnel's
		// substeps order, which the canvas drag saves.
		$handlers['wfacp_checkout'] = static function ( $parent, array $ids ) {
			$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );

			if ( ! class_exists( 'WFOB_Common' ) || ! $modules || ! method_exists( $modules, 'save_bump_order' ) ) {
				return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
			}

			$bumps = array();

			foreach ( (array) WFFN_Core()->substeps->get_substeps( (int) get_post_meta( $parent->ID, '_bwf_in_funnel', true ), $parent->ID ) as $key => $list ) {
				if ( 'wc_order_bump' === (string) $key ) {
					foreach ( (array) $list as $bump ) {
						$bumps[] = absint( is_array( $bump ) || is_object( $bump ) ? ( (array) $bump )['id'] : $bump );
					}
				}
			}

			$bumps = array_values( array_filter( $bumps ) );

			if ( empty( $bumps ) ) {
				return new WP_Error( 'fkmcp_invalid_state', 'This Checkout has no Order Bumps to reorder.' );
			}

			if ( count( $ids ) !== count( $bumps ) || array_diff( $ids, $bumps ) || array_diff( $bumps, $ids ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'step_ids must list every Order Bump of this Checkout exactly once: %s.', implode( ', ', $bumps ) ) );
			}

			if ( false === $modules->save_bump_order( $parent->ID, $ids ) ) {
				return new WP_Error( 'fkmcp_save_failed', 'The Order Bump order could not be saved.' );
			}

			return true;
		};

		return $handlers;
	}

	public static function step_status_handlers( $handlers ) {
		$handlers['wfocu_offer'] = static function ( $post, $enabled ) {
			return self::set_offer_enabled( (int) $post->ID, (bool) $enabled );
		};

		return $handlers;
	}

	private static function set_offer_enabled( $offer_id, $enabled ) {
		$upsell = (int) get_post_meta( $offer_id, '_funnel_id', true );

		if ( $upsell <= 0 ) {
			return new WP_Error( 'fkmcp_invalid_state', 'This Offer belongs to no Upsell funnel.' );
		}

		$steps = get_post_meta( $upsell, '_funnel_steps', true );

		if ( ! is_array( $steps ) ) {
			return new WP_Error( 'fkmcp_invalid_state', 'The Upsell funnel has no steps store to switch the Offer in.' );
		}

		$found = false;

		foreach ( $steps as &$entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) && absint( $entry['id'] ) === $offer_id ) {
				$entry['state'] = $enabled ? '1' : '0';
				$found          = true;
			}
		}

		unset( $entry );

		if ( ! $found ) {
			return new WP_Error( 'fkmcp_invalid_state', 'The Offer is not listed in its Upsell funnel.' );
		}

		update_post_meta( $upsell, '_funnel_steps', $steps );
		wp_update_post(
			array(
				'ID'          => $offer_id,
				'post_status' => $enabled ? 'publish' : 'draft',
			)
		);

		return true;
	}

	/** The product-tab settings of a bump (saved beside its products); the position store is update-bump-settings'. */
	const BUMP_PRODUCT_SETTING_KEYS = array( 'bump_action_type', 'bump_replace_type', 'selected_replace_product' );

	/** The product-tab settings of an offer this tool writes; subscription, free-trial and Sublium keys stay admin-only. */
	const OFFER_PRODUCT_SETTING_KEYS = array( 'jump_on_accepted', 'jump_on_rejected', 'jump_to_offer_on_accepted', 'jump_to_offer_on_rejected', 'skip_exist', 'skip_purchased', 'qty_selector', 'qty_max', 'ship_dynamic' );

	/** The position and auto-switch keys of a bump's settings store; the rest of that store (priority, design) is not this tool's. */
	const BUMP_SETTING_KEYS = array( 'order_bump_position_hooks', 'order_bump_position_hooks_mobile', 'order_bump_auto_added', 'order_bump_auto_hide' );

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_bump_settings( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-bump-settings', $input, array( $this, 'do_update_bump_settings' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_bump_settings( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFOB_Common' ) || ! method_exists( 'WFOB_Common', 'get_setting_data' ) || ! method_exists( 'WFOB_Common', 'update_setting_data' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

		if ( is_wp_error( $bump ) ) {
			return $bump;
		}

		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( empty( $settings ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'settings must map at least one setting key to a value.' );
		}

		$unknown = array_diff( array_keys( $settings ), self::BUMP_SETTING_KEYS );

		if ( ! empty( $unknown ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Unknown setting key(s): %s. Known: %s.', implode( ', ', array_map( 'strval', $unknown ) ), implode( ', ', self::BUMP_SETTING_KEYS ) ) );
		}

		$positions = self::bump_positions();
		$patch     = array();

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, array( 'order_bump_auto_added', 'order_bump_auto_hide' ), true ) ) {
				if ( in_array( $value, array( true, 1, '1', 'true', 'yes' ), true ) ) {
					$patch[ $key ] = true;
				} elseif ( in_array( $value, array( false, 0, '0', 'false', 'no', '' ), true ) ) {
					$patch[ $key ] = false;
				} else {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is a switch: pass true or false.', $key ) );
				}

				continue;
			}

			if ( ! is_string( $value ) || ( ! empty( $positions ) && ! isset( $positions[ $value ] ) ) ) {
				$list = array();

				foreach ( $positions as $id => $name ) {
					$list[] = $id . ' (' . $name . ')';
				}

				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be one of: %s.', $key, implode( ', ', $list ) ) );
			}

			$patch[ $key ] = sanitize_key( $value );
		}

		// The admin route merges these keys into the bump's settings store; the rest of the store stays as it is.
		$stored = (array) WFOB_Common::get_setting_data( $bump->ID );
		$merged = array_merge( $stored, $patch );

		WFOB_Common::update_setting_data( $bump->ID, $merged );

		if ( method_exists( 'WFOB_Common', 'delete_transient' ) ) {
			WFOB_Common::delete_transient( $bump->ID );
		}

		$result = array(
			'bump_id'  => (int) $bump->ID,
			'changed'  => $patch,
			'settings' => WFFN_MCP_Format::pick( array_merge( array_fill_keys( self::BUMP_SETTING_KEYS, null ), $merged ), self::BUMP_SETTING_KEYS ),
		);

		$checkout_id = self::bump_checkout_id( $bump->ID );

		foreach ( array( 'order_bump_position_hooks', 'order_bump_position_hooks_mobile' ) as $key ) {
			if ( isset( $patch[ $key ] ) && is_string( $patch[ $key ] ) && ! self::bump_position_renders( $checkout_id, $patch[ $key ] ) ) {
				$result['warning'] = sprintf( 'Saved, but this checkout\'s form has no Order Summary field, so the bump will not render at %s. Add the field in the checkout designer or pick a payment-gateway position (they always render).', $patch[ $key ] );
			}
		}

		return $result;
	}

	/** The wc_checkout Step of the bump's funnel; 0 when it cannot be resolved. */
	private static function bump_checkout_id( $bump_id ) {
		$funnel_id = (int) get_post_meta( $bump_id, '_bwf_in_funnel', true );

		if ( $funnel_id <= 0 || ! class_exists( 'WFFN_Funnel' ) ) {
			return 0;
		}

		foreach ( (array) ( new WFFN_Funnel( $funnel_id ) )->get_steps() as $step ) {
			$step = (array) $step;

			if ( 'wc_checkout' === ( isset( $step['type'] ) ? $step['type'] : '' ) && ! empty( $step['id'] ) ) {
				return (int) $step['id'];
			}
		}

		return 0;
	}

	/**
	 * The order-summary positions only fire from the checkout form's Order
	 * Summary field; without it the bump saves fine and never renders.
	 * true when the position renders, or when it cannot be checked.
	 */
	private static function bump_position_renders( $checkout_id, $position ) {
		if ( false === strpos( (string) $position, 'order_summary' ) ) {
			return true;
		}

		if ( $checkout_id <= 0 || ! class_exists( 'WFACP_Common' ) || ! method_exists( 'WFACP_Common', 'get_page_layout' ) ) {
			return true;
		}

		$layout = (array) WFACP_Common::get_page_layout( $checkout_id );

		foreach ( (array) ( isset( $layout['fieldsets'] ) ? $layout['fieldsets'] : array() ) as $sections ) {
			foreach ( (array) $sections as $section ) {
				$section = (array) $section;

				foreach ( (array) ( isset( $section['fields'] ) ? $section['fields'] : array() ) as $field ) {
					$field = (array) $field;

					if ( 'order_summary' === ( isset( $field['id'] ) ? $field['id'] : '' ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/** id => name of the checkout positions a bump can attach to, whichever shape the module lists them in. */
	private static function bump_positions() {
		if ( ! method_exists( 'WFOB_Common', 'get_bump_position' ) ) {
			return array();
		}

		$positions = array();

		foreach ( (array) WFOB_Common::get_bump_position() as $key => $row ) {
			$row = (array) $row;
			$id  = isset( $row['id'] ) ? (string) $row['id'] : (string) $key;

			if ( '' !== $id ) {
				$positions[ $id ] = isset( $row['name'] ) ? wp_strip_all_tags( (string) $row['name'] ) : $id;
			}
		}

		return $positions;
	}

	/** Admin Change Skin picker: the wfob_default_preview_templates default list, in its order. */
	const BUMP_PICKER_SKINS = array( 'layout_1', 'layout_8', 'layout_5', 'layout_7', 'layout_11', 'layout_10', 'layout_9', 'layout_6', 'layout_12' );

	/** The label + look description each skin class declares, keyed by slug. */
	private static function bump_skin_texts() {
		$texts = array();

		if ( class_exists( 'WFOB_Bump_Fc' ) && method_exists( 'WFOB_Bump_Fc', 'get_layouts_info' ) ) {
			foreach ( (array) WFOB_Bump_Fc::get_layouts_info() as $slug => $row ) {
				$texts[ $slug ] = array(
					'label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
					'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
				);
			}
		}

		return $texts;
	}

	/**
	 * Finishes a skin import the way the admin's skin picker does: that import also
	 * stores the bump's product list (empty until products are added), and the admin
	 * opens the Design tab only on a bump that has one - without it, it offers the
	 * skin picker again. import_skin() stores the design alone.
	 *
	 * @param int $bump_id Order Bump id.
	 */
	private static function bump_complete_import( $bump_id ) {
		if ( ! metadata_exists( 'post', $bump_id, '_wfob_selected_products' ) ) {
			WFOB_Common::update_page_product( $bump_id, array() );
		}
	}

	/**
	 * The skin a bump's admin Design tab opens with: the stored design's layout once
	 * the import is complete (bump_complete_import()), else null - the admin still
	 * offers the skin picker. get_bump() answers before its active_layout while the
	 * bump has no products.
	 *
	 * @param int $bump_id Order Bump id.
	 *
	 * @return string|null
	 */
	private static function bump_imported_layout( $bump_id ) {
		$layout = self::bump_stored_layout( $bump_id );

		return '' !== $layout && metadata_exists( 'post', $bump_id, '_wfob_selected_products' ) ? $layout : null;
	}

	/**
	 * The layout of the bump's stored design; '' when it has none (unlike bump_design_map(), no fallback).
	 *
	 * @param int $bump_id Order Bump id.
	 *
	 * @return string
	 */
	private static function bump_stored_layout( $bump_id ) {
		$stored = method_exists( 'WFOB_Common', 'get_design_data_meta' ) ? WFOB_Common::get_design_data_meta( $bump_id ) : get_post_meta( $bump_id, '_wfob_design_data', true );

		return is_array( $stored ) && ! empty( $stored['layout'] ) ? (string) $stored['layout'] : '';
	}

	/** Skin identity keys - update-bump-skin changes them, update-bump-design must not. */
	const BUMP_DESIGN_LOCKED = array( 'layout', 'layout_name', 'class_name' );

	/** Per-product design fields: stored as product_<product key>_<suffix>. */
	const BUMP_PRODUCT_KEY_SUFFIXES = array( 'title', 'sub_title', 'description', 'small_description', 'add_btn_text', 'added_btn_text', 'remove_btn_text', 'featured_image', 'featured_image_options', 'read_more', 'exclusive_content_enable', 'exclusive_content', 'social_proof_enable', 'social_proof_heading', 'social_proof_content' );

	/** The stored design map, or the default skin's model when the bump was never designed (get_design_data()'s own fallback). */
	private static function bump_design_map( $bump_id ) {
		$stored = method_exists( 'WFOB_Common', 'get_design_data_meta' ) ? WFOB_Common::get_design_data_meta( $bump_id ) : get_post_meta( $bump_id, '_wfob_design_data', true );

		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}

		$models = class_exists( 'WFOB_Bump_Fc' ) ? (array) WFOB_Bump_Fc::get_default_models() : array();
		$layout = property_exists( 'WFOB_Common', 'design_default_layout' ) ? (string) WFOB_Common::$design_default_layout : 'layout_1';
		$map    = isset( $models[ $layout ] ) ? (array) $models[ $layout ] : array();

		$map['layout'] = $layout;

		return $map;
	}

	/** The design map as read tools return it: scalar values minus empties and the skin identity keys. */
	private static function bump_design_values( array $map ) {
		$values = array();

		foreach ( $map as $key => $value ) {
			if ( in_array( $key, self::BUMP_DESIGN_LOCKED, true ) ) {
				continue;
			}

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$values[ $key ] = $value;
			}
		}

		return $values;
	}

	/** Field type inferred from the skin's own default - the fallback when the module schema has no entry. */
	private static function bump_field_type( $key, $value ) {
		$v = is_scalar( $value ) ? (string) $value : '';

		if ( 'true' === $v || 'false' === $v || '_enable' === substr( (string) $key, -7 ) ) {
			return 'toggle';
		}
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $v ) || preg_match( '/(_color|_background)$/', (string) $key ) ) {
			return 'color';
		}
		if ( '' !== $v && is_numeric( $v ) ) {
			return 'number';
		}

		return 'text';
	}

	/** The module's field type vocabulary reduced to what an agent needs to pick a value shape. */
	private static function bump_schema_type( $type ) {
		$map = array(
			'checkbox'          => 'toggle',
			'toggle'            => 'toggle',
			'color'             => 'color',
			'number'            => 'number',
			'width'             => 'number',
			'select'            => 'select',
			'dimension-control' => 'dimension',
			'image'             => 'image',
			'richeditor'        => 'text',
			'text'              => 'text',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'text';
	}

	/**
	 * A swallowed skin exception, kept as one log line so a silent fallback
	 * can still be traced.
	 *
	 * @param string    $context What was being read.
	 * @param Throwable $e       The exception.
	 *
	 * @return void
	 */
	private static function log_exception( $context, $e ) {
		if ( ! class_exists( 'WFFN_MCP_Log' ) ) {
			return;
		}

		WFFN_MCP_Log::event( sprintf( '%s failed: %s %s', $context, get_class( $e ), $e->getMessage() ) );
	}

	/** A skin-class instance for schema harvesting. */
	private static function bump_probe( $bump_id, $layout ) {
		if ( ! class_exists( 'WFOB_Bump_Fc' ) || '' === (string) $layout ) {
			return null;
		}

		try {
			$layouts = (array) WFOB_Bump_Fc::get_layouts();

			return isset( $layouts[ $layout ] ) && class_exists( $layouts[ $layout ] ) ? new $layouts[ $layout ]( $bump_id ) : null;
		} catch ( Throwable $e ) {
			self::log_exception( 'bump skin probe', $e );

			return null;
		}
	}

	/**
	 * The skins' own admin schema: field definitions (label, type, hint,
	 * options) plus the designer's accordion grouping. Any failure returns
	 * what was collected; callers fall back to inference.
	 */
	private static function bump_field_schema( $bump_id, $layout ) {
		$out = array(
			'defs'   => array(),
			'groups' => array(),
			'assign' => array(),
		);

		if ( ! class_exists( 'WFOB_Bump_Fc' ) || '' === (string) $layout ) {
			return $out;
		}

		$probe = self::bump_probe( $bump_id, $layout );

		if ( null === $probe ) {
			return $out;
		}

		try {
			$probe->set_bump_design_selectors( $bump_id, $layout, $layout );

			foreach ( (array) $probe->get_bump_design_selectors() as $key => $def ) {
				$def = (array) $def;

				if ( isset( $def['type'] ) && 'bwf-label' !== $def['type'] ) {
					$out['defs'][ (string) $key ] = $def;
				}
			}

			// The designer's accordions, straight from the module.
			$stored = self::bump_design_map( $bump_id );
			$probe->set_design_data( $stored );

			foreach ( (array) $probe->admin_design_fields( $stored ) as $group ) {
				$group = (array) $group;
				$gkey  = isset( $group['key'] ) ? (string) $group['key'] : '';

				if ( '' === $gkey ) {
					continue;
				}

				$out['groups'][ $gkey ] = isset( $group['label'] ) ? wp_strip_all_tags( (string) $group['label'] ) : $gkey;

				foreach ( (array) ( isset( $group['fields'] ) ? $group['fields'] : array() ) as $field ) {
					$field = (array) $field;

					if ( ! empty( $field['key'] ) ) {
						$out['assign'][ (string) $field['key'] ] = $gkey;
					}
				}
			}

			if ( method_exists( 'WFOB_Common', 'get_bump_products' ) ) {
				// The designer's per-product fields (texts, image toggle, image options), keyed by product.
				$admin = (array) $probe->get_admin_schema();

				foreach ( (array) WFOB_Common::get_bump_products( $bump_id ) as $product_key => $product ) {
					$product = (array) $product;
					$gkey    = 'product_' . $product_key;

					$out['groups'][ $gkey ] = 'Product: ' . ( isset( $product['title'] ) && '' !== (string) $product['title'] ? wp_strip_all_tags( (string) $product['title'] ) : (string) $product_key );

					foreach ( (array) ( isset( $admin['contents']['content'][ $product_key ] ) ? $admin['contents']['content'][ $product_key ] : array() ) as $def ) {
						$def = (array) $def;

						if ( ! empty( $def['key'] ) && isset( $def['type'] ) && 'bwf-label' !== $def['type'] ) {
							$out['defs'][ (string) $def['key'] ]   = $def;
							$out['assign'][ (string) $def['key'] ] = $gkey;
						}
					}
				}
			}
		} catch ( Throwable $e ) {
			self::log_exception( 'bump product field schema', $e );

			return $out;
		}

		return $out;
	}

	/** The design as the admin's accordions: groups of fields with key, label, type, value, hint and options. */
	private static function bump_design_fields( $bump_id, array $stored, array $defaults, $layout ) {
		$schema  = self::bump_field_schema( $bump_id, $layout );
		$defs    = $schema['defs'];
		$grouped = array_fill_keys( array_keys( $schema['groups'] ), array() );
		$other   = array();

		foreach ( array_diff( array_unique( array_merge( array_keys( $defs ), array_keys( $defaults ), array_keys( $stored ) ) ), self::BUMP_DESIGN_LOCKED ) as $key ) {
			$def    = isset( $defs[ $key ] ) ? (array) $defs[ $key ] : array();
			$value  = array_key_exists( $key, $stored ) && is_scalar( $stored[ $key ] ) ? $stored[ $key ] : ( isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
			$sample = isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) && '' !== (string) $defaults[ $key ] ? $defaults[ $key ] : $value;
			$field  = array(
				'key'   => (string) $key,
				'type'  => isset( $def['type'] ) ? self::bump_schema_type( (string) $def['type'] ) : self::bump_field_type( $key, $sample ),
				'value' => $value,
			);

			if ( self::bump_is_image_key( $key ) ) {
				$field['type']  = 'image';
				$field['value'] = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
			}

			if ( isset( $def['label'] ) && is_string( $def['label'] ) && '' !== $def['label'] ) {
				$field['label'] = wp_strip_all_tags( $def['label'] );
			}
			if ( isset( $def['hint'] ) && is_string( $def['hint'] ) && '' !== $def['hint'] ) {
				$field['hint'] = wp_strip_all_tags( $def['hint'] );
			}

			$toggler = self::bump_field_toggler( $def );

			if ( null !== $toggler ) {
				$field['toggler'] = $toggler;
			}

			if ( 'select' === $field['type'] && ! empty( $def['options'] ) && is_array( $def['options'] ) ) {
				$options = self::bump_field_options( $def, 'options' );

				if ( ! empty( $options ) ) {
					$field['options'] = $options;
				}
			}

			$gkey = isset( $schema['assign'][ $key ] ) ? $schema['assign'][ $key ] : null;

			// Hover variants sit inside their base field's accordion.
			if ( null === $gkey && false !== strpos( $key, 'hover' ) ) {
				$base = preg_replace( '/_+/', '_', trim( str_replace( array( '_hover_', '_hover', 'hover_' ), '_', $key ), '_' ) );
				$gkey = isset( $schema['assign'][ $base ] ) ? $schema['assign'][ $base ] : null;
			}

			if ( null !== $gkey && isset( $grouped[ $gkey ] ) ) {
				$grouped[ $gkey ][] = $field;
			} else {
				$other[] = $field;
			}
		}

		$groups = array();

		foreach ( $schema['groups'] as $gkey => $glabel ) {
			if ( ! empty( $grouped[ $gkey ] ) ) {
				$groups[] = array(
					'group_key' => (string) $gkey,
					'group'     => (string) $glabel,
					'fields'    => $grouped[ $gkey ],
				);
			}
		}

		if ( ! empty( $other ) ) {
			$groups[] = array(
				'group_key' => 'other',
				'group'     => 'Other stored values',
				'fields'    => $other,
			);
		}

		$products = method_exists( 'WFOB_Common', 'get_prepared_products' ) ? array_map( 'strval', array_keys( (array) WFOB_Common::get_prepared_products( $bump_id ) ) ) : array();

		return array(
			'groups'         => $groups,
			'product_fields' => array(
				'products' => $products,
				'suffixes' => self::BUMP_PRODUCT_KEY_SUFFIXES,
				'pattern'  => 'product_<product key>_<suffix>',
			),
		);
	}

	/** Skin name computed from the layout key - the module misnames some (layout_8 calls itself "Skin 1"). */
	private static function bump_skin_name( $id, array $model ) {
		if ( preg_match( '/^layout_(\d+)$/', (string) $id, $m ) ) {
			return 'Skin ' . $m[1];
		}

		return isset( $model['layout_name'] ) ? wp_strip_all_tags( (string) $model['layout_name'] ) : (string) $id;
	}

	/** One skin as results reference it: the admin's number, plus the pack's semantic label when it has one. */
	private static function bump_skin_ref( $id, array $models ) {
		$id    = (string) $id;
		$texts = self::bump_skin_texts();

		return array(
			'id'    => $id,
			'name'  => '' !== $id ? self::bump_skin_name( $id, isset( $models[ $id ] ) ? (array) $models[ $id ] : array() ) : '',
			'label' => isset( $texts[ $id ]['label'] ) ? $texts[ $id ]['label'] : '',
		);
	}

	/** tag + description rows from the module's own (filterable) merge tag list. */
	private static function bump_merge_tags() {
		if ( ! class_exists( 'WFOB_Product_Switcher_Merge_Tags' ) ) {
			return array();
		}

		$rows = array();

		foreach ( (array) WFOB_Product_Switcher_Merge_Tags::get_tags_list() as $row ) {
			$row = (array) $row;

			if ( ! empty( $row['tag'] ) ) {
				$rows[] = array(
					'tag'         => (string) $row['tag'],
					'description' => isset( $row['description'] ) ? wp_strip_all_tags( (string) $row['description'] ) : '',
				);
			}
		}

		return $rows;
	}

	/** Bare names allowed inside {{...}} - get_all_tags() is the render-time whitelist, a superset of the documented list. */
	private static function bump_merge_tag_names() {
		if ( ! class_exists( 'WFOB_Product_Switcher_Merge_Tags' ) ) {
			return array();
		}

		$names = array();

		foreach ( (array) WFOB_Product_Switcher_Merge_Tags::get_all_tags() as $row ) {
			$row = (array) $row;

			if ( ! empty( $row['tag'] ) ) {
				$names[] = (string) $row['tag'];
			}
		}

		return $names;
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function list_bump_skins( $input ) {
		if ( ! class_exists( 'WFOB_Bump_Fc' ) || ! class_exists( 'WFOB_Common' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$input   = (array) $input;
		$bump_id = 0;
		$active  = '';

		if ( ! empty( $input['bump_id'] ) ) {
			$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

			if ( is_wp_error( $bump ) ) {
				return $bump;
			}

			$bump_id = (int) $bump->ID;
			$map     = self::bump_design_map( $bump_id );
			$active  = isset( $map['layout'] ) ? (string) $map['layout'] : '';
		}

		$models = (array) WFOB_Bump_Fc::get_default_models();
		$picker = (array) apply_filters( 'wfob_default_preview_templates', self::BUMP_PICKER_SKINS );
		$rows   = array();

		foreach ( $picker as $id ) {
			if ( isset( $models[ $id ] ) ) {
				$rows[] = self::bump_skin_row( $id, (array) $models[ $id ], $active );
			}
		}

		// A bump may still wear a skin the picker no longer offers.
		if ( '' !== $active && ! in_array( $active, $picker, true ) && isset( $models[ $active ] ) ) {
			$row           = self::bump_skin_row( $active, (array) $models[ $active ], $active );
			$row['legacy'] = true;
			$rows[]        = $row;
		}

		return array(
			'bump_id' => $bump_id,
			'skins'   => $rows,
			'total'   => count( $rows ),
		);
	}

	/**
	 * @param mixed $id
	 * @param array $model
	 * @param mixed $active
	 *
	 * @return mixed
	 */
	private static function bump_skin_row( $id, array $model, $active ) {
		$texts = self::bump_skin_texts();

		return array(
			'id'          => (string) $id,
			'name'        => self::bump_skin_name( $id, $model ),
			'label'       => isset( $texts[ $id ]['label'] ) ? $texts[ $id ]['label'] : '',
			'description' => isset( $texts[ $id ]['description'] ) ? $texts[ $id ]['description'] : '',
			'active'      => (string) $id === $active,
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_bump_design( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-bump-design', $input, array( $this, 'do_update_bump_design' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_bump_design( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFOB_Common' ) || ! method_exists( 'WFOB_Common', 'get_design_data_meta' ) || ! method_exists( 'WFOB_Common', 'update_design_data' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

		if ( is_wp_error( $bump ) ) {
			return $bump;
		}

		$design = isset( $input['design'] ) && is_array( $input['design'] ) ? $input['design'] : array();

		if ( empty( $design ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'design must map at least one design key to a value.' );
		}

		if ( count( $design ) > self::DESIGN_KEYS_MAX ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'design patches at most %d keys per call.', self::DESIGN_KEYS_MAX ) );
		}

		$stored   = self::bump_design_map( $bump->ID );
		$allowed  = self::bump_design_allowed_keys( $bump->ID, $stored );
		$models   = class_exists( 'WFOB_Bump_Fc' ) ? (array) WFOB_Bump_Fc::get_default_models() : array();
		$layout   = isset( $stored['layout'] ) ? (string) $stored['layout'] : '';
		$defaults = isset( $models[ $layout ] ) ? (array) $models[ $layout ] : array();
		$schema   = self::bump_field_schema( $bump->ID, $layout );
		$defs_map = $schema['defs'];
		$tags     = self::bump_merge_tag_names();
		$patch    = array();

		foreach ( $design as $key => $value ) {
			$key = (string) $key;

			if ( in_array( $key, self::BUMP_DESIGN_LOCKED, true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is the skin identity - change skins with update-bump-skin.', $key ) );
			}

			if ( in_array( $key, array( 'product_featured_image', 'product_image_url', 'product_image_position', 'product_image_position_class', 'product_image_position_width' ), true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is the skin\'s default for products added later and does not change this bump\'s image: use product_<product key>_featured_image and product_<product key>_featured_image_options.', $key ) );
			}

			if ( ! preg_match( '/^[a-z0-9_]{1,100}$/', $key ) || ! in_array( $key, $allowed, true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Unknown design key %s. Valid keys are the ones get-order-bump lists under design.groups, plus product_<product key>_<field> for the bump\'s products (fields: %s).', $key, implode( ', ', self::BUMP_PRODUCT_KEY_SUFFIXES ) ) );
			}

			if ( self::bump_is_image_key( $key ) ) {
				// Patch over the stored options, or over the module's own seed when they are not an array yet.
				$base = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();

				if ( empty( $base ) ) {
					$seed = (array) WFOB_Common::add_product_details_default_layout( WFOB_Common::get_prepared_products( $bump->ID ), $defaults );
					$base = isset( $seed[ $key ] ) ? (array) $seed[ $key ] : array();
				}

				$image = self::bump_image_patch( $key, $value, $base, isset( $defs_map[ $key ] ) ? (array) $defs_map[ $key ] : array() );

				if ( is_wp_error( $image ) ) {
					return $image;
				}

				$patch[ $key ] = $image;

				continue;
			}

			$sample = isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) && '' !== (string) $defaults[ $key ] ? $defaults[ $key ] : ( isset( $stored[ $key ] ) ? $stored[ $key ] : '' );
			$def    = isset( $defs_map[ $key ] ) ? (array) $defs_map[ $key ] : array();
			$type   = isset( $def['type'] ) && 'bwf-label' !== $def['type'] ? self::bump_schema_type( (string) $def['type'] ) : self::bump_field_type( $key, $sample );

			if ( 'toggle' === $type && ! is_bool( $value ) && ! in_array( $value, array( 'true', 'false' ), true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is a toggle: pass true or false.', $key ) );
			}

			if ( 'number' === $type && ! is_bool( $value ) && '' !== $value && ! is_numeric( $value ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is a number, as get-order-bump\'s design.groups shows.', $key ) );
			}

			// Colours are printed into the bump's stylesheet unescaped.
			if ( 'color' === $type && '' !== $value && ! preg_match( '/^(#[0-9a-f]{3,8}|(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)|transparent|var\(--[\w-]+\))$/i', trim( (string) $value ) ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is a colour: pass #1a2b3c, rgb(a), hsl(a), transparent or var(--name).', $key ) );
			}

			if ( 'select' === $type && ! empty( $def['options'] ) && is_array( $def['options'] ) ) {
				$choices = array_map(
					'strval',
					array_filter(
						array_column(
							array_map(
								function ( $o ) {
									return (array) $o;
								},
								$def['options']
							),
							'value'
						),
						'strlen'
					)
				);

				if ( ! empty( $choices ) && ! in_array( (string) $value, $choices, true ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be one of: %s.', $key, implode( ', ', $choices ) ) );
				}
			}

			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$value = (string) $value;
			}

			if ( ! is_string( $value ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s must be a string, number or boolean - the design store is a flat map.', $key ) );
			}

			if ( strlen( $value ) > 5000 ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is longer than 5000 bytes.', $key ) );
			}

			// Same guard the admin save gives every design value.
			$value = (string) wp_kses_post( $value );

			$tag_error = self::bump_check_merge_tags( $key, $value, $tags );

			if ( is_wp_error( $tag_error ) ) {
				return $tag_error;
			}

			$patch[ $key ] = $value;
		}

		WFOB_Common::update_design_data( $bump->ID, array_merge( $stored, $patch ) );

		if ( method_exists( 'WFOB_Common', 'delete_transient' ) ) {
			WFOB_Common::delete_transient( $bump->ID );
		}

		return array(
			'bump_id' => (int) $bump->ID,
			'changed' => $patch,
		);
	}

	/**
	 * Render replaces merge tags by exact string match, so {{ product_name }} or a
	 * misspelled tag prints literally to shoppers - refuse both before they persist.
	 */
	private static function bump_check_merge_tags( $key, $value, array $tags ) {
		if ( ! preg_match_all( '/\{\{[^{}]*\}\}/', $value, $found ) ) {
			return null;
		}

		foreach ( array_unique( $found[0] ) as $token ) {
			if ( ! preg_match( '/^\{\{([a-z0-9_]+)\}\}$/', $token, $m ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s holds a malformed merge tag %s - tags are lowercase with no spaces, e.g. {{product_name}}.', $key, $token ) );
			}

			if ( ! empty( $tags ) && ! in_array( $m[1], $tags, true ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s holds an unknown merge tag {{%s}} - it would print literally on the live checkout. Known tags: {{%s}}.', $key, $m[1], implode( '}}, {{', $tags ) ) );
			}
		}

		return null;
	}

	/** product_<key>_featured_image_options: an array {type, custom_url, image_url, width, position}, not text. */
	private static function bump_is_image_key( $key ) {
		return '_featured_image_options' === substr( (string) $key, -23 );
	}

	/**
	 * An image options patch (object or its JSON) merged over the stored options.
	 *
	 * @param string $key   Design key.
	 * @param mixed  $value The patch.
	 * @param array  $base  The stored options.
	 * @param array  $def   The skin's image field: its alignmentClassList keys are the positions.
	 *
	 * @return array|WP_Error
	 */
	private static function bump_image_patch( $key, $value, array $base, array $def ) {
		$value     = is_string( $value ) ? json_decode( $value, true ) : $value;
		$positions = isset( $def['alignmentClassList'] ) ? array_keys( (array) $def['alignmentClassList'] ) : array();

		if ( ! is_array( $value ) || empty( $value ) || array_diff( array_keys( $value ), array( 'type', 'custom_url', 'width', 'position' ) )
			|| ( isset( $value['type'] ) && ! in_array( $value['type'], array( 'product', 'custom' ), true ) )
			|| ( isset( $value['width'] ) && ! is_numeric( $value['width'] ) )
			|| ( isset( $value['position'] ) && ! in_array( $value['position'], $positions, true ) ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s is an object with any of: type (product|custom), custom_url (http(s) image URL), width (px), position (%s).', $key, implode( '|', $positions ) ) );
		}

		// The module prints custom_url into an unescaped src attribute: only a plain http(s) URL may pass.
		if ( isset( $value['custom_url'] ) && '' !== $value['custom_url'] && ( ! is_string( $value['custom_url'] ) || ! preg_match( '#^https?://[^\s\'"<>]+$#i', $value['custom_url'] ) || sanitize_url( $value['custom_url'] ) !== $value['custom_url'] ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s custom_url must be a plain http(s) image URL.', $key ) );
		}

		if ( isset( $value['width'] ) ) {
			$value['width'] = (string) absint( $value['width'] );
		}

		// The skin shows custom_url only with type custom.
		if ( ! empty( $value['custom_url'] ) && ! isset( $value['type'] ) ) {
			$value['type'] = 'custom';
		}

		return array_merge( $base, $value );
	}

	/** Keys update-bump-design may touch: the stored map, the active skin's own model, and per-product fields for the bump's products. */
	private static function bump_design_allowed_keys( $bump_id, array $stored ) {
		$allowed = array_keys( $stored );
		$models  = class_exists( 'WFOB_Bump_Fc' ) ? (array) WFOB_Bump_Fc::get_default_models() : array();
		$layout  = isset( $stored['layout'] ) ? (string) $stored['layout'] : '';

		if ( isset( $models[ $layout ] ) ) {
			$allowed = array_merge( $allowed, array_keys( (array) $models[ $layout ] ) );
		}

		if ( method_exists( 'WFOB_Common', 'get_prepared_products' ) ) {
			foreach ( array_keys( (array) WFOB_Common::get_prepared_products( $bump_id ) ) as $product_key ) {
				foreach ( self::BUMP_PRODUCT_KEY_SUFFIXES as $suffix ) {
					$allowed[] = 'product_' . $product_key . '_' . $suffix;
				}
			}
		}

		return array_values( array_unique( array_diff( $allowed, self::BUMP_DESIGN_LOCKED ) ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_bump_skin( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-bump-skin', $input, array( $this, 'do_update_bump_skin' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_bump_skin( $input ) {
		$input = (array) $input;

		if ( ! class_exists( 'WFFN_REST_BUMP_API_EndPoint' ) || ! class_exists( 'WFOB_Bump_Fc' ) || ! class_exists( 'WFOB_Common' ) ) {
			return new WP_Error( 'fkmcp_bumps_unavailable', 'The Order Bumps module is not active.' );
		}

		$bump = self::typed_post( $input, 'bump_id', array( 'wfob_bump' ), 'order bump' );

		if ( is_wp_error( $bump ) ) {
			return $bump;
		}

		$skin   = isset( $input['skin'] ) ? sanitize_key( (string) $input['skin'] ) : '';
		$models = (array) WFOB_Bump_Fc::get_default_models();

		if ( '' === $skin || ! isset( $models[ $skin ] ) ) {
			$known = array();

			foreach ( (array) apply_filters( 'wfob_default_preview_templates', self::BUMP_PICKER_SKINS ) as $id ) {
				if ( isset( $models[ $id ] ) ) {
					$ref     = self::bump_skin_ref( $id, $models );
					$known[] = $id . ' (' . ( '' !== $ref['label'] ? $ref['label'] : $ref['name'] ) . ')';
				}
			}

			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'skin must be one of: %s. See list-bump-skins.', implode( ', ', $known ) ) );
		}

		$previous    = self::bump_design_map( $bump->ID );
		$previous_id = isset( $previous['layout'] ) ? (string) $previous['layout'] : '';
		$named       = self::bump_skin_ref( $skin, $models );
		$was         = self::bump_skin_ref( $previous_id, $models );

		// A bump an earlier create-order-bump left half-imported wears its skin in meta
		// while the admin still offers the picker: applying that skin again completes
		// the import and keeps the design it already has.
		if ( $previous_id === $skin && $skin === self::bump_stored_layout( $bump->ID ) ) {
			$completed = null === self::bump_imported_layout( $bump->ID );

			self::bump_complete_import( $bump->ID );

			return array(
				'bump_id'       => (int) $bump->ID,
				'skin'          => $named,
				'previous_skin' => $was,
				'changed'       => $completed ? array( 'skin' => $skin ) : new stdClass(),
				'design'        => self::bump_design_values( $previous ),
			);
		}

		$response = FKMCP_Pack_Funnels::response_data(
			WFFN_REST_BUMP_API_EndPoint::get_instance()->import_skin(
				FKMCP_Pack_Funnels::rest_request(
					array(
						'bump_id' => $bump->ID,
						'layout'  => $skin,
					)
				)
			)
		);

		if ( empty( $response['status'] ) ) {
			return new WP_Error( 'fkmcp_write_failed', isset( $response['msg'] ) ? (string) $response['msg'] : 'The skin could not be imported.' );
		}

		self::bump_complete_import( $bump->ID );

		$integration = is_object( WFFN_Core()->substeps ) ? WFFN_Core()->substeps->get_integration_object( 'wc_order_bump' ) : null;

		return array(
			'bump_id'       => (int) $bump->ID,
			'skin'          => $named,
			'previous_skin' => $was,
			'changed'       => array( 'skin' => $skin ),
			'design'        => self::bump_design_values( self::bump_design_map( $bump->ID ) ),
			'links'         => array( 'edit' => is_object( $integration ) && method_exists( $integration, 'get_entity_edit_link' ) ? (string) $integration->get_entity_edit_link( $bump->ID ) : '' ),
		);
	}

	/** The three rule engines: same shape, different controllers, different targets. */
	const RULE_MODULES = array(
		'bump'     => array(
			'endpoint'  => 'WFFN_REST_BUMP_API_EndPoint',
			'engine'    => 'WFOB_Rules',
			'get'       => 'get_wfob_rules',
			'update'    => 'update_wfob_rules',
			'post_type' => 'wfob_bump',
			'id'        => 'bump_id',
			'label'     => 'order bump',
			'slug'      => 'update-bump-rules',
		),
		'upsell'   => array(
			'endpoint'  => 'WFFN_REST_UPSELL_API_EndPoint',
			'engine'    => 'WFOCU_Rules',
			'get'       => 'get_upsell_rules',
			'update'    => 'update_upsell_rules',
			'post_type' => 'wfocu_funnel',
			'id'        => 'step_id',
			'label'     => 'upsell',
			'slug'      => 'update-upsell-rules',
		),
		'thankyou' => array(
			'endpoint'  => 'WFFN_REST_THANKYOU_API_EndPoint',
			'engine'    => 'WFTY_Rules',
			'get'       => 'get_wcty_rules',
			'update'    => 'update_wcty_rules',
			'post_type' => 'wffn_ty',
			'id'        => 'step_id',
			'label'     => 'thank you',
			'slug'      => 'update-thankyou-rules',
		),
	);

	/** Operators that compare a number. */
	const NUMERIC_OPERATORS = array( '==', '!=', '>', '<', '>=', '<=', '=<' );

	/**
	 * @param mixed $id_key
	 *
	 * @return array
	 */
	private static function schema_rules_input( $id_key ) {
		return WFFN_MCP_Schema::input(
			array(
				$id_key => WFFN_MCP_Schema::in_id(),
				'rules' => WFFN_MCP_Schema::in_arr(
					WFFN_MCP_Schema::in_arr(
						array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'required'             => array( 'rule_type' ),
							'properties'           => array(
								'rule_type' => WFFN_MCP_Schema::in_str(
									'From list-step-rule-types.',
									array(
										'pattern'   => '^[a-z0-9_]+$',
										'maxLength' => 60,
									)
								),
								'operator'  => WFFN_MCP_Schema::in_str( 'One of the type\'s operators; omit for types without.', array( 'maxLength' => 20 ) ),
								'condition' => array( 'description' => 'Shape per the type\'s condition kind: a number/text/date/time, a list of option values, product ids, {qty, products}, or {meta_key, meta_value}.' ),
							),
						),
						'One group: rules ANDed.',
						array( 'minItems' => 1 )
					),
					'Groups ORed. [] = no rules, always shown.'
				),
			),
			array( $id_key, 'rules' )
		);
	}

	/**
	 * @param mixed $id_key
	 *
	 * @return array
	 */
	private static function schema_rules_result( $id_key ) {
		return WFFN_MCP_Schema::obj(
			array(
				$id_key      => WFFN_MCP_Schema::int(),
				'rules'      => self::schema_rules(),
				'groups'     => WFFN_MCP_Schema::int( 'Groups saved.' ),
				'rule_count' => WFFN_MCP_Schema::int( 'Rules saved across groups.' ),
			),
			'',
			array( $id_key, 'rules', 'groups', 'rule_count' )
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	/**
	 * Semantics an agent cannot see from the operator list alone. The item
	 * quantity rules only count products present, so an absent product fails
	 * every comparison except the != one.
	 */
	const RULE_TYPE_NOTES = array(
		'cart_item'  => 'Counts the product\'s quantity in the cart; condition.products is a LIST of product ids. When every listed product is absent each operator is false EXCEPT != : to match "product NOT in cart" use operator != with qty 1; a qty 0 condition with == or < never matches anything.',
		'order_item' => 'Counts the product\'s quantity in the placed order; condition.products is ONE product id, not a list. When the product is absent every operator is false EXCEPT != : to match "product NOT in the order" use operator != with qty 1; a qty 0 condition with == or < never matches anything.',
	);

	public function list_step_rule_types( $input ) {
		$input  = (array) $input;
		$for    = isset( $input['for'] ) ? (string) $input['for'] : '';
		$module = isset( self::RULE_MODULES[ $for ] ) ? self::RULE_MODULES[ $for ] : null;

		if ( ! $module ) {
			return new WP_Error( 'fkmcp_invalid_input', 'for must be bump, upsell or thankyou.' );
		}

		$post = self::typed_post( array( $module['id'] => isset( $input['id'] ) ? $input['id'] : 0 ), $module['id'], array( $module['post_type'] ), $module['label'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$read = self::rules_read( $module, $post->ID );

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		$types = array();
		$group = '';

		foreach ( $read['catalog'] as $entry ) {
			// The list carries a group label on its first entry only.
			if ( ! empty( $entry['nameKey'] ) ) {
				$group = (string) $entry['nameKey'];
			}

			$kind = self::rule_kind( $entry );
			$row  = array(
				'rule_type' => (string) $entry['key'],
				'label'     => isset( $entry['label'] ) ? (string) $entry['label'] : (string) $entry['key'],
				'group'     => $group,
				'operators' => self::kind_options( array( 'options' => isset( $entry['operators'] ) ? (array) $entry['operators'] : array() ) ),
				'condition' => $kind,
			);

			if ( isset( self::RULE_TYPE_NOTES[ $entry['key'] ] ) ) {
				$row['note'] = self::RULE_TYPE_NOTES[ $entry['key'] ];
			}

			$types[] = $row;
		}

		return array(
			'for'        => $for,
			'id'         => (int) $post->ID,
			'rule_types' => $types,
			'total'      => count( $types ),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_bump_rules( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-bump-rules', $input, array( $this, 'do_update_bump_rules' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_bump_rules( $input ) {
		return self::do_update_rules( 'bump', $input );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_upsell_rules( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-upsell-rules', $input, array( $this, 'do_update_upsell_rules' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_upsell_rules( $input ) {
		return self::do_update_rules( 'upsell', $input );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function update_thankyou_rules( $input ) {
		return FKMCP_Pack_Funnels::write( 'update-thankyou-rules', $input, array( $this, 'do_update_thankyou_rules' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function do_update_thankyou_rules( $input ) {
		return self::do_update_rules( 'thankyou', $input );
	}

	/** The rules write shared by the three engines: validate against the module's own catalogue, rebuild the admin payload, replace, re-read. */
	private static function do_update_rules( $for, $input ) {
		$input  = (array) $input;
		$module = self::RULE_MODULES[ $for ];
		$post   = self::typed_post( $input, $module['id'], array( $module['post_type'] ), $module['label'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! isset( $input['rules'] ) || ! is_array( $input['rules'] ) ) {
			return new WP_Error( 'fkmcp_invalid_input', 'rules must be a list of groups, each a list of {rule_type, operator, condition}; [] clears every rule.' );
		}

		$read = self::rules_read( $module, $post->ID );

		if ( is_wp_error( $read ) ) {
			return $read;
		}

		$dead = self::first_dead_rule( $input['rules'] );

		if ( is_wp_error( $dead ) ) {
			return $dead;
		}

		$payload = self::rules_payload( $input['rules'], $read['catalog'], $for );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$endpoint = call_user_func( array( $module['endpoint'], 'get_instance' ) );
		$response = FKMCP_Pack_Funnels::response_data( $endpoint->{$module['update']}( FKMCP_Pack_Funnels::body_request( array( 'step_id' => (int) $post->ID ), array( 'basic' => $payload ) ) ) );

		if ( empty( $response['success'] ) ) {
			return new WP_Error( 'fkmcp_update_failed', isset( $response['msg'] ) && '' !== $response['msg'] ? wp_strip_all_tags( (string) $response['msg'] ) : 'The rules could not be saved.' );
		}

		$after = self::rules_read( $module, $post->ID );
		$rules = is_wp_error( $after ) ? array() : $after['rules'];
		$count = 0;

		foreach ( $rules as $group ) {
			$count += count( (array) $group );
		}

		return array(
			$module['id'] => (int) $post->ID,
			'rules'       => $rules,
			'groups'      => count( $rules ),
			'rule_count'  => $count,
		);
	}

	/**
	 * Refuses a rule that can mechanically never match. The item quantity
	 * engines skip absent products, so a comparison only satisfiable at a
	 * count of zero is dead however it is written.
	 */
	private static function first_dead_rule( array $groups ) {
		foreach ( $groups as $gi => $group ) {
			foreach ( (array) $group as $ri => $rule ) {
				$rule = (array) $rule;

				if ( ! in_array( isset( $rule['rule_type'] ) ? (string) $rule['rule_type'] : '', array( 'cart_item', 'order_item' ), true ) ) {
					continue;
				}

				$operator  = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
				$condition = isset( $rule['condition'] ) ? (array) $rule['condition'] : array();
				$qty       = absint( isset( $condition['qty'] ) ? $condition['qty'] : 0 );

				if ( 0 === $qty && in_array( $operator, array( '==', '<' ), true ) ) {
					return new WP_Error(
						'fkmcp_rule_never_matches',
						sprintf(
							'Rule %1$d of group %2$d (%3$s %4$s qty 0) can never match: the engine only counts products present, so a zero count is unreachable with %4$s. "Product NOT in the cart/order" is operator != with qty 1. Nothing was saved.',
							$ri + 1,
							$gi + 1,
							$rule['rule_type'],
							$operator
						)
					);
				}
			}
		}

		return null;
	}

	/** Current rules + the module's rule-type catalogue, through its admin GET (deep-cast: the controllers nest stdClass). */
	private static function rules_read( array $module, $post_id ) {
		if ( ! class_exists( $module['endpoint'] ) || ! class_exists( $module['engine'] ) ) {
			return new WP_Error( 'fkmcp_rules_unavailable', sprintf( 'The %s rules engine is not active.', $module['label'] ) );
		}

		$endpoint = call_user_func( array( $module['endpoint'], 'get_instance' ) );
		$data     = FKMCP_Pack_Funnels::response_data( $endpoint->{$module['get']}( FKMCP_Pack_Funnels::rest_request( array( 'step_id' => (int) $post_id ) ) ) );
		$data     = json_decode( (string) wp_json_encode( isset( $data['data'] ) ? $data['data'] : array() ), true );
		$data     = is_array( $data ) ? $data : array();
		$catalog  = array();

		foreach ( isset( $data['rules_list'] ) ? (array) $data['rules_list'] : array() as $entry ) {
			$entry = (array) $entry;

			if ( ! empty( $entry['key'] ) ) {
				$catalog[ (string) $entry['key'] ] = $entry;
			}
		}

		return array(
			'rules'   => isset( $data['rules'] ) && is_array( $data['rules'] ) ? array_values( $data['rules'] ) : array(),
			'catalog' => $catalog,
		);
	}

	/**
	 * What a rule type's condition takes, from the widget the admin screen draws
	 * for it: kind + fixed options where the screen has them.
	 */
	private static function rule_kind( array $entry ) {
		$key    = isset( $entry['key'] ) ? (string) $entry['key'] : '';
		$fields = isset( $entry['fields'] ) ? array_values( (array) $entry['fields'] ) : array();
		$first  = ! empty( $fields ) ? (array) $fields[0] : array();
		$type   = isset( $first['type'] ) ? (string) $first['type'] : '';
		$widget = isset( $first['key'] ) && preg_match( '/\[([a-z_]+)\]$/', (string) $first['key'], $m ) ? $m[1] : '';
		$kind   = array(
			'kind'    => 'text',
			'options' => array(),
			'hint'    => 'A text value.',
		);

		if ( 'custom-html' === $type ) {
			return array(
				'kind'    => 'unavailable',
				'options' => array(),
				'hint'    => 'Not usable on this site yet (order indexing pending).',
			);
		}

		if ( empty( $fields ) ) {
			return false !== strpos( $key, 'custom_meta' ) ? array(
				'kind'    => 'meta',
				'options' => array(),
				'hint'    => '{meta_key, meta_value} of the order.',
			) : array(
				'kind'    => 'none',
				'options' => array(),
				'hint'    => 'No operator, no condition.',
			);
		}

		if ( count( $fields ) > 1 && 'qty' === $widget ) {
			return array(
				'kind'    => 'product_qty',
				'options' => array(),
				'hint'    => '{qty, products}: quantity and product id(s) (ids from search-products).',
			);
		}

		switch ( $widget ) {
			case 'products':
				$kind = array(
					'kind'    => 'products',
					'options' => array(),
					'hint'    => 'Product ids (from search-products).',
				);
				break;
			case 'chosen_select':
			case 'category_select':
			case 'user_select':
			case 'coupon_select':
				$kind = array(
					'kind'    => 'list',
					'options' => self::kind_options( $first ),
					'hint'    => 'One or more option values.',
				);
				break;
			case 'coupon_exist':
				$kind = array(
					'kind'    => 'choice',
					'options' => self::kind_options( $first ),
					'hint'    => 'One option value.',
				);
				break;
			case 'date':
				$kind = array(
					'kind'    => 'date',
					'options' => array(),
					'hint'    => 'YYYY-MM-DD.',
				);
				break;
			case 'time':
				$kind = array(
					'kind'    => 'time',
					'options' => array(),
					'hint'    => 'HH:MM (24h).',
				);
				break;
			case 'text':
				$kind = array(
					'kind'    => 'number',
					'options' => array(),
					'hint'    => 'A number.',
				);
				break;
			default:
				break;
		}

		return $kind;
	}

	/** {value,label} rows out of a field's option list, whichever of the two shapes the screen used. */
	private static function kind_options( array $field ) {
		$rows = array();
		$list = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : ( isset( $field['values'] ) && is_array( $field['values'] ) ? $field['values'] : array() );

		foreach ( $list as $option ) {
			$option = (array) $option;
			$value  = isset( $option['id'] ) ? $option['id'] : ( isset( $option['value'] ) ? $option['value'] : null );

			if ( null === $value ) {
				continue;
			}

			$rows[] = array(
				'value' => (string) $value,
				'label' => isset( $option['name'] ) ? (string) $option['name'] : ( isset( $option['label'] ) ? (string) $option['label'] : (string) $value ),
			);
		}

		return $rows;
	}

	/** Groups of rules => the {groupN: {ruleN: {rule_type, operator, condition}}} map the admin app posts, every rule checked against the catalogue. */
	private static function rules_payload( array $groups, array $catalog, $for ) {
		$payload = array();
		$g       = 0;

		foreach ( array_values( $groups ) as $gi => $group ) {
			if ( ! is_array( $group ) || empty( $group ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Group %d is empty: each group needs at least one rule.', $gi + 1 ) );
			}

			$rules = array();
			$r     = 0;

			foreach ( array_values( $group ) as $ri => $rule ) {
				$rule = is_object( $rule ) ? (array) $rule : $rule;

				if ( ! is_array( $rule ) || empty( $rule['rule_type'] ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( 'Group %d rule %d: rule_type is required.', $gi + 1, $ri + 1 ) );
				}

				$built = self::rule_row( $rule, $catalog, $for, sprintf( 'Group %d rule %d', $gi + 1, $ri + 1 ) );

				if ( is_wp_error( $built ) ) {
					return $built;
				}

				$rules[ 'rule' . $r ] = $built;
				++$r;
			}

			$payload[ 'group' . $g ] = $rules;
			++$g;
		}

		return $payload;
	}

	/** One rule the way the admin app posts it, or a WP_Error naming what is wrong. */
	private static function rule_row( array $rule, array $catalog, $for, $where ) {
		$type = sanitize_key( (string) $rule['rule_type'] );

		if ( ! isset( $catalog[ $type ] ) ) {
			$label = self::RULE_MODULES[ $for ]['label'];

			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: "%s" is not a rule type %s %s can use; list-step-rule-types (for=%s) has the valid ones.', $where, $type, preg_match( '/^[aeiou]/', $label ) ? 'an' : 'a', $label, $for ) );
		}

		$entry     = $catalog[ $type ];
		$kind      = self::rule_kind( $entry );
		$operators = array_values( array_map( 'strval', array_column( isset( $entry['operators'] ) ? (array) $entry['operators'] : array(), 'value' ) ) );
		$operator  = isset( $rule['operator'] ) ? trim( (string) $rule['operator'] ) : '';

		if ( 'unavailable' === $kind['kind'] ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s is not usable on this site yet.', $where, $type ) );
		}

		if ( ! empty( $operators ) && ! in_array( $operator, $operators, true ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: operator for %s must be one of: %s.', $where, $type, implode( ', ', $operators ) ) );
		}

		if ( empty( $operators ) && '' !== $operator ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s takes no operator.', $where, $type ) );
		}

		$condition = array_key_exists( 'condition', $rule ) ? $rule['condition'] : null;
		$condition = is_object( $condition ) ? (array) $condition : $condition;

		switch ( $kind['kind'] ) {
			case 'none':
				$condition = '';
				break;

			case 'number':
				if ( ! is_scalar( $condition ) || '' === trim( (string) $condition ) || ( in_array( $operator, self::NUMERIC_OPERATORS, true ) && ! is_numeric( $condition ) ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs a number as condition.', $where, $type ) );
				}

				$condition = (string) $condition;
				break;

			case 'text':
				if ( ! is_scalar( $condition ) || '' === trim( (string) $condition ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs a text condition.', $where, $type ) );
				}

				$condition = sanitize_text_field( (string) $condition );
				break;

			case 'date':
				if ( ! is_string( $condition ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $condition, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs a date as YYYY-MM-DD.', $where, $type ) );
				}
				break;

			case 'time':
				if ( ! is_string( $condition ) || ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', str_replace( ' ', '', $condition ) ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs a time as HH:MM (24h).', $where, $type ) );
				}

				$condition = str_replace( ' ', '', $condition );
				break;

			case 'choice':
				$allowed = array_column( $kind['options'], 'value' );

				if ( ! is_scalar( $condition ) || ! in_array( (string) $condition, $allowed, true ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s must be one of: %s.', $where, $type, implode( ', ', $allowed ) ) );
				}

				$condition = (string) $condition;
				break;

			case 'list':
				$values = is_array( $condition ) && isset( $condition['categories'] ) ? $condition['categories'] : $condition;
				$values = is_array( $values ) ? array_values( array_map( 'strval', $values ) ) : ( is_scalar( $values ) && '' !== $values ? array( (string) $values ) : array() );

				if ( empty( $values ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs one or more option values as condition.', $where, $type ) );
				}

				$allowed = array_column( $kind['options'], 'value' );

				if ( ! empty( $allowed ) ) {
					$bad = array_diff( $values, $allowed );

					if ( ! empty( $bad ) ) {
						return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s does not accept %s; options: %s%s.', $where, $type, implode( ', ', $bad ), implode( ', ', array_slice( $allowed, 0, 25 ) ), count( $allowed ) > 25 ? ', ...' : '' ) );
					}
				}

				// The screen posts option lists under categories; the controller unwraps the ones its store keeps flat.
				$condition = array( 'categories' => $values );
				break;

			case 'products':
				$ids = is_array( $condition ) && isset( $condition['products'] ) ? $condition['products'] : $condition;
				$ids = self::product_ids( $ids, $where, $type );

				if ( is_wp_error( $ids ) ) {
					return $ids;
				}

				$condition = array( 'products' => $ids );
				break;

			case 'product_qty':
				$qty = is_array( $condition ) && isset( $condition['qty'] ) ? $condition['qty'] : 1;
				$ids = is_array( $condition ) && isset( $condition['products'] ) ? $condition['products'] : ( is_array( $condition ) && ! isset( $condition['qty'] ) ? $condition : ( is_scalar( $condition ) ? $condition : array() ) );
				$ids = self::product_ids( $ids, $where, $type );

				if ( is_wp_error( $ids ) ) {
					return $ids;
				}

				// The screen's QTY box takes 1 or more; > is "contains at least", so "has the product" is > with qty 1.
				if ( ! is_numeric( $qty ) || (int) $qty < 1 ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s qty must be a whole number of 1 or more; operator > means "contains at least" qty, so "has the product" is > with qty 1.', $where, $type ) );
				}

				// The order-side engines keep one product per rule; the cart-side one a list.
				if ( 'order_item' === $type && count( $ids ) > 1 ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s takes one product per rule; add a rule per product.', $where, $type ) );
				}

				$condition = array(
					'qty'      => (string) (int) $qty,
					'products' => 'order_item' === $type ? $ids[0] : $ids,
				);
				break;

			case 'meta':
				if ( ! is_array( $condition ) || empty( $condition['meta_key'] ) || ! is_scalar( $condition['meta_key'] ) ) {
					return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs {meta_key, meta_value}.', $where, $type ) );
				}

				$condition = array(
					'meta_key'   => sanitize_text_field( (string) $condition['meta_key'] ),
					'meta_value' => isset( $condition['meta_value'] ) && is_scalar( $condition['meta_value'] ) ? sanitize_text_field( (string) $condition['meta_value'] ) : '',
				);
				break;

			default:
				break;
		}

		return array(
			'rule_type' => $type,
			'operator'  => $operator,
			'condition' => $condition,
		);
	}

	/** Product ids as the screen posts them (strings), each checked to exist. */
	private static function product_ids( $ids, $where, $type ) {
		$ids = is_array( $ids ) ? $ids : ( is_scalar( $ids ) && '' !== $ids ? array( $ids ) : array() );
		$out = array();

		foreach ( $ids as $id ) {
			$id = is_array( $id ) || is_object( $id ) ? ( isset( ( (array) $id )['id'] ) ? ( (array) $id )['id'] : 0 ) : $id;
			$id = absint( $id );

			if ( ! $id || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $id ) ) {
				return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s - %s is not a WooCommerce product id (search-products finds ids).', $where, $type, is_scalar( $id ) ? $id : '?' ) );
			}

			$out[] = (string) $id;
		}

		if ( empty( $out ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%s: %s needs at least one product id.', $where, $type ) );
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_thankyou_rules( $input ) {
		if ( ! class_exists( 'WFFN_REST_THANKYOU_API_EndPoint' ) ) {
			return new WP_Error( 'fkmcp_thankyou_unavailable', 'The Thank You Pro module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wffn_ty' ), 'thank you step' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$data = FKMCP_Pack_Funnels::response_data( WFFN_REST_THANKYOU_API_EndPoint::get_instance()->get_wcty_rules( FKMCP_Pack_Funnels::rest_request( array( 'step_id' => $step->ID ) ) ) );

		return array(
			'step_id' => (int) $step->ID,
			'rules'   => isset( $data['data']['rules'] ) ? (array) $data['data']['rules'] : array(),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_checkout_conditional_fields( $input ) {
		$api = '\\FunnelKit\\Checkout\\Modules\\Conditional_Fields\\Api\\Rest_Controller';

		if ( ! class_exists( $api ) || ! method_exists( $api, 'get_instance' ) ) {
			return new WP_Error( 'fkmcp_conditional_fields_unavailable', 'The Conditional Fields module is not active.' );
		}

		$step = self::typed_post( $input, 'step_id', array( 'wfacp_checkout' ), 'checkout' );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$api    = $api::get_instance();
		$editor = FKMCP_Pack_Funnels::response_data( $api->get_editor_data( FKMCP_Pack_Funnels::rest_request( array( 'checkout_id' => $step->ID ) ) ) );
		$editor = isset( $editor['data'] ) ? (array) $editor['data'] : array();
		$fields = array();

		foreach ( ( isset( $editor['fields'] ) ? (array) $editor['fields'] : array() ) as $field ) {
			$field = (array) $field;
			$row   = WFFN_MCP_Format::pick( $field, array( 'id', 'label', 'section', 'type', 'enabled' ) );
			$rule  = null;

			if ( ! empty( $field['has_rules'] ) && isset( $field['id'] ) ) {
				$detail = FKMCP_Pack_Funnels::response_data(
					$api->get_field_rules(
						FKMCP_Pack_Funnels::rest_request(
							array(
								'checkout_id' => $step->ID,
								'field_id'    => $field['id'],
							)
						)
					)
				);
				$rule   = isset( $detail['data']['rules'] ) ? $detail['data']['rules'] : null;
			}

			$row['condition'] = $rule;
			$fields[]         = $row;
		}

		return array(
			'step_id'            => (int) $step->ID,
			'fields'             => $fields,
			'section_conditions' => isset( $editor['section_rules'] ) ? (array) $editor['section_rules'] : array(),
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_offer_performance( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$input   = (array) $input;
		$request = self::window( $input );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$limit  = isset( $input['limit'] ) ? max( 1, min( self::OFFER_PERF_MAX, absint( $input['limit'] ) ) ) : self::OFFER_PERF_DEFAULT;
		$page   = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
		$funnel = isset( $input['funnel_id'] ) ? absint( $input['funnel_id'] ) : 0;
		$window = $request;

		// The route knows no funnel filter: with funnel_id, walk its pages and
		// filter on each row's fid (capped; cart upsells carry fid 0).
		$request['limit']   = $funnel > 0 ? 50 : $limit;
		$request['page_no'] = $funnel > 0 ? 1 : $page;

		$items = array();
		$total = 0;

		for ( $route_page = 1; $route_page <= 20; $route_page++ ) {
			$request['page_no'] = $funnel > 0 ? $route_page : $page;

			$rows  = FKMCP_Pack_Funnels::response_data( $dashboard->get_all_upsells( $request, 'analytics' ) );
			$batch = (array) ( isset( $rows['result']['items'] ) ? $rows['result']['items'] : array() );
			$total = isset( $rows['result']['total_count'] ) ? (int) $rows['result']['total_count'] : $total;

			foreach ( $batch as $row ) {
				$row = (array) $row;

				if ( $funnel > 0 && $funnel !== absint( isset( $row['fid'] ) ? $row['fid'] : 0 ) ) {
					continue;
				}

				$items[] = $row;
			}

			if ( $funnel <= 0 || empty( $batch ) || $route_page * 50 >= $total ) {
				break;
			}
		}

		if ( $funnel > 0 ) {
			$total = count( $items );
			$items = array_slice( $items, ( $page - 1 ) * $limit, $limit );
		}

		return array(
			'window' => $window,
			'items'  => $items,
			'total'  => $total,
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_funnel_graph( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$input   = (array) $input;
		$request = self::window( $input );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		if ( isset( $input['interval'] ) && in_array( $input['interval'], array( 'day', 'week', 'month' ), true ) ) {
			// The route picks overall mode's granularity from the span itself.
			if ( ! empty( $request['overall'] ) ) {
				return new WP_Error( 'fkmcp_invalid_input', 'overall=true picks its granularity from the data span; for a chosen interval pass an explicit after/before window instead.' );
			}

			$request['interval'] = $input['interval'];
		}

		$data = FKMCP_Pack_Funnels::response_data( $dashboard->get_graph_data( $request ) );
		$data = isset( $data['data'] ) ? $data['data'] : array();

		// The route's bucket generator overshoots the window: drop buckets
		// that start after it ends and cap the last bucket's end to it.
		$end = isset( $request['before'] ) ? (string) $request['before'] : current_time( 'Y-m-d H:i:s' );

		if ( is_array( $data ) && isset( $data['intervals'] ) && is_array( $data['intervals'] ) ) {
			$kept = array();

			foreach ( $data['intervals'] as $bucket ) {
				$bucket = (array) $bucket;

				foreach ( array( 'start_date', 'end_date' ) as $key ) {
					if ( isset( $bucket[ $key ] ) && is_string( $bucket[ $key ] ) ) {
						$bucket[ $key ] = trim( $bucket[ $key ] );
					}
				}

				if ( isset( $bucket['start_date'] ) && strtotime( (string) $bucket['start_date'] ) > strtotime( $end ) ) {
					continue;
				}

				if ( isset( $bucket['end_date'] ) && strtotime( (string) $bucket['end_date'] ) > strtotime( $end ) ) {
					$bucket['end_date'] = $end;
				}

				$kept[] = $bucket;
			}

			$data['intervals'] = $kept;
		}

		return array(
			'window' => $request,
			'data'   => $data,
		);
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_funnel_order( $input ) {
		$orders = FKMCP_Pack_Funnels::controller( 'class-wffn-funnel-orders.php', 'WFFN_Funnel_Orders' );

		if ( ! $orders ) {
			return new WP_Error( 'fkmcp_orders_unavailable', 'The orders provider is not loaded on this request.' );
		}

		$input    = (array) $input;
		$order_id = absint( isset( $input['order_id'] ) ? $input['order_id'] : 0 );

		if ( ! function_exists( 'wc_get_order' ) || false === wc_get_order( $order_id ) ) {
			return new WP_Error( 'fkmcp_order_not_found', 'No order with that id.' );
		}

		$data = FKMCP_Pack_Funnels::response_data( $orders->get_single_order_info( array( 'id' => $order_id ) ) );
		$data = WFFN_MCP_Format::pick( $data, array( 'items', 'order_total' ) );

		// Items come grouped by which Funnel part sold them: checkout / bump /
		// upsell. Keep the grouping, drop the duplicated HTML price per line.
		if ( isset( $data['items'] ) ) {
			$groups = array();

			foreach ( (array) $data['items'] as $group => $rows ) {
				$groups[ $group ] = array();

				foreach ( (array) $rows as $row ) {
					$row = (array) $row;
					unset( $row['subtotal_html'] );

					$groups[ $group ][] = $row;
				}
			}

			$data['items'] = $groups;
		}

		return self::untrust( array_merge( array( 'order_id' => $order_id ), $data ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array|WP_Error
	 */
	public function get_funnel_performance( $input ) {
		$dashboard = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-api-dashboard-endpoint.php', 'WFFN_REST_API_Dashboard_EndPoint' );

		if ( ! $dashboard ) {
			return new WP_Error( 'fkmcp_dashboard_unavailable', 'The dashboard provider is not loaded on this request.' );
		}

		$input  = (array) $input;
		$funnel = new WFFN_Funnel( absint( $input['funnel_id'] ) );

		if ( ! $funnel->get_id() ) {
			return new WP_Error( 'fkmcp_funnel_not_found', 'No funnel with that id.' );
		}

		$range = self::window_range( $input );

		if ( is_wp_error( $range ) ) {
			return $range;
		}

		list( $start, $end ) = $range;

		$orders  = $dashboard->get_total_orders( (int) $funnel->get_id(), $start, $end );
		$revenue = $dashboard->get_total_revenue( (int) $funnel->get_id(), $start, $end );
		$revenue = is_array( $revenue ) ? $revenue : array();

		$total    = isset( $revenue['aero'][0]['total'] ) ? (float) $revenue['aero'][0]['total'] : 0;
		$checkout = isset( $revenue['aero'][0]['sum_aero'] ) ? (float) $revenue['aero'][0]['sum_aero'] : $total;
		$bump     = isset( $revenue['bump'][0]['sum_bump'] ) ? (float) $revenue['bump'][0]['sum_bump'] : 0;
		$upsell   = isset( $revenue['upsell'][0]['sum_upsells'] ) ? (float) $revenue['upsell'][0]['sum_upsells'] : 0;

		return array(
			'funnel_id' => (int) $funnel->get_id(),
			'title'     => $funnel->get_title(),
			'window'    => self::window( $input ),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'orders'    => is_numeric( $orders ) ? (int) $orders : 0,
			'revenue'   => array(
				'total'    => $total,
				'checkout' => $checkout,
				'upsell'   => $upsell,
				'bump'     => $bump,
			),
		);
	}

	/** Existence + post-type check - these endpoints wp_send_json() on bad ids. */
	private static function typed_post( $input, $key, $types, $label ) {
		return FKMCP_Pack_Funnels::typed_step( $input, $types, $label, $key, $label );
	}

	/** Shared shape for the contacts/orders/leads list handlers. */
	private static function list_args( $input ) {
		$input = (array) $input;

		return array(
			'funnel_id'   => ! empty( $input['funnel_id'] ) ? absint( $input['funnel_id'] ) : 0,
			's'           => isset( $input['search'] ) ? (string) $input['search'] : '',
			'limit'       => isset( $input['limit'] ) ? max( 1, min( self::LIST_MAX, absint( $input['limit'] ) ) ) : self::LIST_DEFAULT,
			'page_no'     => isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1,
			'total_count' => 'yes',
		);
	}

	/** Row count as the list controllers report it: total_count (orders, leads) or total_records (contacts). */
	private static function list_total( array $data, array $rows ) {
		foreach ( array( 'total_count', 'total_records' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
				return (int) $data[ $key ];
			}
		}

		return count( $rows );
	}

	/**
	 * Contact rows, order buyers and optin submissions are text a site
	 * visitor typed into a form. Markup is stripped, breaks with it, strings
	 * are capped and deep structures dropped - the model reports these
	 * values, it must never read them as instructions.
	 */
	private static function untrust( $value, $depth = 0 ) {
		if ( is_string( $value ) ) {
			return mb_substr( wp_strip_all_tags( $value, true ), 0, 500 );
		}

		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( is_array( $value ) ) {
			if ( $depth >= 6 ) {
				return array();
			}

			$clean = array();

			foreach ( $value as $key => $item ) {
				$clean[ $key ] = self::untrust( $item, $depth + 1 );
			}

			return $clean;
		}

		return is_scalar( $value ) || null === $value ? $value : null;
	}

	/**
	 * Validated date window for the analytics providers. The pattern repeats
	 * the input schema on purpose: these values reach SQL, so the handler
	 * enforces the format itself rather than trusting transport validation.
	 */
	private static function window( $input ) {
		$input   = (array) $input;
		$request = array();

		if ( ! empty( $input['overall'] ) ) {
			$request['overall'] = true;

			return $request;
		}

		foreach ( array( 'after', 'before' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && preg_match( '/^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}:\\d{2})?$/', $input[ $key ] ) ) {
				$request[ $key ] = $input[ $key ];
			}
		}

		// A bare date is the whole day, as the admin's date picker sends it:
		// the providers compare `< before`, so before=Y-m-d alone would drop
		// that last day while get-funnel-performance counted it.
		if ( isset( $request['after'] ) && 10 === strlen( $request['after'] ) ) {
			$request['after'] .= ' 00:00:00';
		}

		if ( isset( $request['before'] ) && 10 === strlen( $request['before'] ) ) {
			$request['before'] .= ' 23:59:59';
		}

		if ( isset( $request['after'], $request['before'] ) && strtotime( $request['after'] ) > strtotime( $request['before'] ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( 'after (%s) must not be later than before (%s).', $request['after'], $request['before'] ) );
		}

		// The providers treat a missing bound as unfiltered, which silently
		// turns a half or empty window into all-time; resolve to the
		// documented default instead and say so in the echo.
		if ( ! isset( $request['before'] ) ) {
			$request['before'] = current_time( 'Y-m-d H:i:s' );
		}

		if ( ! isset( $request['after'] ) ) {
			$request['after'] = gmdate( 'Y-m-d', strtotime( $request['before'] ) - 7 * DAY_IN_SECONDS ) . ' 00:00:00';

			if ( ! isset( $input['before'] ) || ! is_string( $input['before'] ) || '' === $input['before'] ) {
				$request['default'] = 'last 7 days';
			}
		}

		return $request;
	}

	/**
	 * @return mixed
	 */
	private static function ab_ready() {
		return function_exists( 'BWFABT_Core' ) && is_object( BWFABT_Core()->admin );
	}

	/**
	 * The refusal every A/B tool gives when the module is missing, or null
	 * when it is there: one message instead of five copies.
	 *
	 * @return WP_Error|null
	 */
	private static function ab_unavailable() {
		return self::ab_ready() ? null : new WP_Error( 'fkmcp_ab_tests_unavailable', 'The A/B Tests module is not active.' );
	}

	/** Row lookup by primary key - the one read that cannot wp_die(). */
	private static function experiment_exists( $experiment_id ) {
		$row = $experiment_id > 0 ? BWFABT_Core()->get_dataStore()->get( $experiment_id ) : array();

		return ! empty( $row ) && is_array( $row );
	}

	/**
	 * The Funnel an A/B test's control Step is in. An Upsell Offer is not a Step of
	 * the Funnel itself: it hangs under an Upsell Step, which carries the Funnel.
	 *
	 * @param int $control Control Step id.
	 *
	 * @return int 0 when the Step no longer exists.
	 */
	private static function ab_funnel_id( $control ) {
		if ( $control && 'wfocu_offer' === get_post_type( $control ) ) {
			$control = (int) get_post_meta( $control, '_funnel_id', true );
		}

		return $control ? (int) get_post_meta( $control, '_bwf_in_funnel', true ) : 0;
	}

	/** experiment id => control Step id, one query for the whole list. */
	private static function control_steps( array $rows ) {
		global $wpdb;

		$ids = array();

		foreach ( $rows as $row ) {
			$row   = (array) $row;
			$ids[] = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		}

		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN() list is %d placeholders counted off the int array above.
		$pairs = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, control FROM {$wpdb->prefix}bwf_ab_experiments WHERE id IN ({$placeholders})", $ids ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$map = array();

		foreach ( (array) $pairs as $pair ) {
			$map[ (int) $pair['id'] ] = (int) $pair['control'];
		}

		return $map;
	}

	/** A/B REST classes are singletons; include on demand like the Lite controllers. */
	private static function ab_controller( $file, $class ) {
		if ( ! class_exists( $class ) && defined( 'BWFABT_PLUGIN_DIR' ) ) {
			$base = BWFABT_PLUGIN_DIR . '/includes/class-bwfabt-rest-controller.php';

			if ( ! class_exists( 'BWFABT_REST_Controller' ) && is_readable( $base ) ) {
				include_once $base;
			}

			$path = BWFABT_PLUGIN_DIR . '/includes/' . $file;

			if ( is_readable( $path ) ) {
				include_once $path;
			}
		}

		return class_exists( $class ) && method_exists( $class, 'get_instance' ) ? $class::get_instance() : null;
	}

	/*
	------------------------------------------------------------------ */
	/*
	Output schemas                                                      */
	/* ------------------------------------------------------------------ */

	private static function schema_stats( $windowed_note ) {
		$s = 'WFFN_MCP_Schema';

		return $s::obj(
			array(
				'views'           => $s::int( 'Page views. ' . $windowed_note, true ),
				'conversions'     => $s::int( 'Conversions (orders or optins).', true ),
				'conversion_rate' => $s::num( 'Percent, 0-100.', true ),
				'revenue'         => $s::num( 'Revenue in store currency.', true ),
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_funnel_analytics() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'available' => $s::bool( 'true: data follows.' ),
					'funnel_id' => $s::int(),
					'title'     => $s::str( 'Funnel name.' ),
					'window'    => $s::window(),
					'currency'  => $s::str( 'ISO currency code for revenue values.' ),
					'totals'    => $s::obj(
						array(
							'views'       => $s::int( 'All-time views; absent when windowed.', true ),
							'conversions' => $s::int(),
							'revenue'     => $s::num( 'Store currency. Order revenue including upsells and bumps; the upsell step\'s own revenue is part of it, not on top.' ),
						),
						'Funnel-wide totals.'
					),
					'steps'     => $s::arr(
						$s::obj(
							array(
								'id'    => $s::int( 'Step id (use with get-step / links).' ),
								'type'  => $s::str( 'Step type slug, e.g. wc_checkout, wc_upsells, landing, optin.' ),
								'stats' => self::schema_stats( 'null when a window is set (views are all-time-only).' ),
							)
						),
						'Per-step numbers in funnel order.'
					),
				),
				'Per-step analytics for one Funnel.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_dashboard_overview() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'window' => $s::window(),
					'data'   => $s::obj(
						array(
							'revenue'             => $s::num( 'Total Funnel revenue, store currency.' ),
							'total_orders'        => $s::int(),
							'checkout_revenue'    => $s::num( 'Revenue from checkout Steps.' ),
							'upsell_revenue'      => $s::num( 'Revenue from one-click upsells.' ),
							'bump_revenue'        => $s::num( 'Revenue from order bumps.' ),
							'total_contacts'      => $s::num( 'Contacts captured in the window.' ),
							'average_order_value' => $s::num( 'Store currency.' ),
						)
					),
				),
				'Store-wide Funnel performance.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_revenue_attribution() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'window' => $s::window(),
					'data'   => $s::obj(
						array(
							'sales' => $s::arr(
								$s::obj(
									array(
										'referrers'     => $s::str( 'Traffic source label, e.g. Direct, Google, Instagram.' ),
										'orders'        => $s::num( 'Orders from this source.' ),
										'total_revenue' => $s::num( 'Store currency.' ),
										'percentage'    => $s::num( 'Share of revenue, percent 0-100.' ),
										'ref_key'       => $s::str( 'Machine key of the source.' ),
									)
								),
								'Sales by traffic source.'
							),
							'lead'  => $s::arr(
								$s::obj(
									array(
										'referrers'  => $s::str( 'Traffic source label.' ),
										'conversion' => $s::num( 'Optins from this source.' ),
										'percentage' => $s::num( 'Share of optins, percent 0-100.' ),
										'ref_key'    => $s::str(),
									)
								),
								'Leads by traffic source.'
							),
						)
					),
				),
				'Revenue and lead attribution by source.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_step_stats() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'funnel_id' => $s::int(),
					'step_id'   => $s::int(),
					'type'      => $s::str( 'Step type slug.' ),
					'window'    => $s::window(),
					'currency'  => $s::str( 'ISO currency code.' ),
					'stats'     => self::schema_stats( 'null when a window is set (views are all-time-only).' ),
				),
				'Numbers for one Step.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_ab_test_row() {
		$s = 'WFFN_MCP_Schema';

		return array(
			'id'     => $s::int( 'Experiment id (experiment_id in other tools).' ),
			'title'  => $s::str(),
			'desc'   => $s::str( 'Description; may be empty.' ),
			'type'   => $s::str( 'Step type under test, e.g. aero (checkout), upsell, landing.' ),
			'status' => $s::str( 'Draft, Started (running, no winner yet), Paused (stopped, no winner) or Completed (winner declared).' ),
		);
	}

	/**
	 * @return array
	 */
	private static function schema_ab_test_list() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::paged(
				$s::obj(
					array_merge(
						self::schema_ab_test_row(),
						array(
							'funnel_id'       => $s::int( 'Funnel of the control Step; 0 when that Step no longer exists. With control_step_id this fills the ab_test admin link.' ),
							'control_step_id' => $s::int( 'Step id of the control variant.' ),
							'date_added'      => $s::str( 'd.m.Y, site time.' ),
							'date_started'    => $s::str( 'd.m.Y, site time; a nonsense date (30.11.-0001) means never started.' ),
							'date_completed'  => $s::str( 'd.m.Y, site time; a nonsense date (30.11.-0001) means not completed - for a Started test, days running = today minus date_started.' ),
						)
					)
				),
				'A/B Tests, newest first.',
				array(
					'limit'  => $s::int( 'Tests per call used for this page.' ),
					'offset' => $s::int( 'Tests skipped for this page.' ),
				)
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_ab_test_get() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array_merge(
					self::schema_ab_test_row(),
					array(
						'funnel_id'       => $s::int( 'Funnel of the control Step; 0 when that Step no longer exists.' ),
						'control_step_id' => $s::int( 'Step id of the control variant.' ),
						'variants'        => $s::arr(
							$s::obj(
								array(
									'id'        => $s::int( 'Variant Step id (control_step_id for links when control is true).' ),
									'title'     => $s::str(),
									'traffic'   => $s::num( 'Traffic share, percent 0-100.' ),
									'control'   => $s::bool( 'true for the control variant.' ),
									'winner'    => $s::bool(),
									'active'    => $s::bool( 'false when excluded from traffic.' ),
									'view_link' => $s::url( 'Public URL of this variant.' ),
									'edit_link' => $s::url( 'Admin URL that opens this variant in its editor.' ),
								)
							)
						),
					)
				),
				'One A/B Test with variants.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_ab_totals() {
		$s = 'WFFN_MCP_Schema';

		return $s::obj(
			array(
				'total_views'           => $s::num(),
				'total_conversion'      => $s::num(),
				'conversion_rate'       => $s::num( 'Percent, 0-100.' ),
				'total_revenue'         => $s::num( 'Store currency.' ),
				'avg_revenue_per_visit' => $s::num( 'Store currency.' ),
			)
		);
	}

	/**
	 * @param array $subtotals
	 *
	 * @return array
	 */
	private static function schema_interval( array $subtotals ) {
		$s = 'WFFN_MCP_Schema';

		return $s::obj(
			array(
				'interval'       => $s::str( 'Bucket label: Y-m-d (day), Y-W (week), Y-m (month) or Y (year).' ),
				'start_date'     => $s::datetime( 'Bucket start, site time.' ),
				'date_start_gmt' => $s::datetime( 'Bucket start, UTC.' ),
				'end_date'       => $s::datetime( 'Bucket end, site time.' ),
				'date_end_gmt'   => $s::datetime( 'Bucket end, UTC.' ),
				'subtotals'      => $subtotals,
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_ab_test_get_results() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'experiment_id' => $s::int(),
					'window'        => $s::window(),
					'totals'        => self::schema_ab_totals(),
					'intervals'     => $s::arr( self::schema_interval( self::schema_ab_totals() ), 'Time series for the window.' ),
					'variants'      => $s::map(
						$s::any( 'Per-variant stats { object_id, object_name, revenue, revenue_per_visit, conversions, views, conversion_rate (percent 0-100) }.' ),
						'Keyed by variant Step id. A test without variants returns { status: false, message } instead.'
					),
				),
				'A/B Test results.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_list_funnel_contacts() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::paged(
				$s::obj(
					array(
						'cid'          => $s::int( 'Contact id (contact_id for get-funnel-contact and links).' ),
						'f_name'       => $s::str( 'First name.' ),
						'l_name'       => $s::str( 'Last name.' ),
						'email'        => $s::str(),
						'phone'        => $s::str(),
						'contact_type' => $s::str( 'lead or customer.' ),
						'status_text'  => $s::str( 'Display label of the type.' ),
						'status_type'  => $s::str( 'lead or customer.' ),
						'date'         => $s::datetime( 'Captured at.' ),
					)
				),
				'Contacts captured by Funnels.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_funnel_contact() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'user_info'   => $s::obj(
						array(
							'user_type'  => $s::str( 'Customer or Lead.' ),
							'first_name' => $s::str(),
							'last_name'  => $s::str(),
							'email'      => $s::str(),
							'contact_no' => $s::str( 'Phone.' ),
							'created_on' => $s::datetime(),
							'additional' => $s::arr(
								$s::obj(
									array(
										'name'  => $s::str(),
										'value' => $s::str(),
									)
								),
								'Extra profile fields as label/value pairs.'
							),
						)
					),
					'records'     => $s::arr( $s::any(), 'Activity records; entries may be null.' ),
					'orders'      => $s::arr(
						$s::obj(
							array(
								'order_id'         => $s::id( 'WooCommerce order id.' ),
								'order_number'     => $s::id(),
								'order_url'        => $s::url( 'Admin order page.' ),
								'order_total_html' => $s::str( 'Formatted total with currency symbol (HTML entities).' ),
								'campaign'         => $s::obj(
									array(
										'source'  => $s::str( 'utm_source.' ),
										'medium'  => $s::str( 'utm_medium.' ),
										'name'    => $s::str( 'utm_campaign.' ),
										'term'    => $s::str( 'utm_term.' ),
										'content' => $s::str( 'utm_content.' ),
									)
								),
								'conversion'       => $s::obj(
									array(
										'funnel_id'     => $s::id(),
										'funnel_title'  => $s::str(),
										'funnel_link'   => $s::url( 'Admin funnel page.' ),
										'click_id'      => $s::str(),
										'referrer'      => $s::str( 'Referring URL.' ),
										'source_id'     => $s::id( 'Order id.' ),
										'device'        => $s::str( 'Desktop, Mobile or Tablet.' ),
										'browser'       => $s::str(),
										'country'       => $s::str( 'ISO-2.' ),
										'first_click'   => $s::datetime( 'Visit start.' ),
										'aov_in_second' => $s::str( 'Human-readable time from first click to order.' ),
									)
								),
								'products'         => $s::arr( $s::any(), 'Line items; entries may be null.' ),
								'customer_info'    => $s::obj(
									array(
										'email'            => $s::str(),
										'phone'            => $s::str(),
										'billing_address'  => $s::str( 'Flattened address.' ),
										'shipping_address' => $s::str( 'Flattened address.' ),
										'purchased_on'     => $s::datetime(),
										'payment_method'   => $s::str( 'Gateway title.' ),
									)
								),
							)
						),
						'Orders attributed to Funnels.'
					),
					'optins'      => $s::arr( $s::any(), 'Optin submissions.' ),
					'is_autonami' => $s::bool( 'true when FunnelKit Automations manages this contact.' ),
					'view_link'   => $s::url( 'Admin contact page.' ),
					'overview'    => $s::obj(
						array(
							'total'        => $s::num( 'Lifetime Funnel revenue, store currency.' ),
							'checkout'     => $s::num(),
							'bump'         => $s::num(),
							'upsell'       => $s::num(),
							'total_orders' => $s::int(),
							'aov'          => $s::num( 'Average order value.' ),
						)
					),
					'funnel_data' => $s::obj(
						array(
							'id'          => $s::id(),
							'title'       => $s::str(),
							'description' => $s::str(),
							'count_data'  => $s::obj( array( 'steps' => $s::int() ) ),
						),
						'The Funnel in scope; empty when funnel_id was not given.'
					),
				),
				'One Contact in full.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_list_funnel_orders() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::paged(
				$s::obj(
					array(
						'order_id'       => $s::id( 'WooCommerce order id (order_id for links).' ),
						'order_number'   => $s::id(),
						'order_url'      => $s::url( 'Admin order page.' ),
						'email'          => $s::str(),
						'name'           => $s::str( 'Buyer first name.' ),
						'phone'          => $s::str(),
						'date'           => $s::datetime( 'Order placed.' ),
						'referrers'      => $s::str( 'Traffic source label.' ),
						'device'         => $s::str( 'Desktop, Mobile or Tablet.' ),
						'utm_campaign'   => $s::str(),
						'utm_source'     => $s::str(),
						'utm_medium'     => $s::str(),
						'utm_term'       => $s::str(),
						'utm_content'    => $s::str(),
						'total_spent'    => $s::num( 'Order total, store currency.' ),
						'customer_id'    => $s::id( 'Contact id.' ),
						'funnel_title'   => $s::str(),
						'fid'            => $s::id( 'Funnel id.' ),
						'convert_time'   => $s::str( 'Human-readable time from first click to order.' ),
						'step_id'        => $s::id( 'Checkout Step that converted.' ),
						'checkout_total' => $s::num( 'Checkout part of the total.' ),
						'bump_total'     => $s::num( 'Order bump part of the total.' ),
						'bump_accepted'  => $s::str( 'JSON-encoded list of accepted bump ids; empty string when none.' ),
						'bump_rejected'  => $s::str( 'JSON-encoded list of rejected bump ids.' ),
						'offer_total'    => $s::num( 'Upsell part of the total.' ),
						'offer_accepted' => $s::str( 'JSON-encoded list of accepted offer ids.' ),
						'offer_rejected' => $s::str( 'JSON-encoded list of rejected offer ids.' ),
					)
				),
				'Orders attributed to Funnels.',
				array(
					'window' => $s::any_obj( 'The after/before applied, whole days resolved; absent when listing every order.' ),
				)
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_list_funnel_leads() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::paged(
				$s::obj(
					array(
						'entry_id'     => $s::id( 'Optin entry id.' ),
						'email'        => $s::str(),
						'name'         => $s::str(),
						'phone'        => $s::str(),
						'others'       => $s::str( 'Other submitted fields as key:value text.' ),
						'date'         => $s::datetime( 'Submitted at.' ),
						'device'       => $s::str( 'Desktop, Mobile or Tablet.' ),
						'referrers'    => $s::str( 'Traffic source label.' ),
						'utm_campaign' => $s::str(),
						'utm_source'   => $s::str(),
						'utm_medium'   => $s::str(),
						'utm_term'     => $s::str(),
						'customer_id'  => $s::id( 'Contact id.' ),
						'funnel_title' => $s::str(),
						'fid'          => $s::id( 'Funnel id.' ),
						'convert_time' => $s::str( 'Human-readable time from first click to optin.' ),
					)
				),
				'Optin submissions.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_checkout_list_payment_methods() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::paged(
				$s::obj(
					array(
						'id'      => $s::str( 'Gateway id, e.g. fkwcs_stripe, cod.' ),
						'title'   => $s::str( 'Customer-facing title.' ),
						'method'  => $s::str( 'Gateway name.' ),
						'enabled' => $s::bool(),
					)
				),
				'WooCommerce payment gateways.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_top_funnels() {
		$s   = 'WFFN_MCP_Schema';
		$row = $s::obj(
			array(
				'fid'             => $s::id( 'Funnel id (funnel_id for links).' ),
				'title'           => $s::str(),
				'total'           => $s::num( 'Revenue, store currency (0 for lead funnels).' ),
				'views'           => $s::int(),
				'conversion'      => $s::num( 'Orders (sales) or optins (leads).' ),
				'conversion_rate' => $s::num( 'Percent, 0-100.' ),
			)
		);

		return $s::pro(
			$s::obj(
				array(
					'window' => $s::window(),
					'sales'  => $s::arr( $row, 'Top sales Funnels.' ),
					'leads'  => $s::arr( $row, 'Top lead Funnels.' ),
				),
				'Best performing Funnels.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_upsell_offers() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'step_id' => $s::int( 'Upsell Step id.' ),
					'items'   => $s::arr(
						$s::obj(
							array(
								'id'       => $s::int( 'Offer id.' ),
								'title'    => $s::str(),
								'status'   => $s::str( 'publish or draft.' ),
								'type'     => $s::str( 'upsell or downsell.' ),
								'products' => $s::arr(
									$s::obj(
										array(
											'product_id' => $s::int(),
											'name'       => $s::str(),
											'price'      => $s::num( 'Regular price, store currency.', true ),
											'discount'   => $s::obj(
												array(
													'discount_amount' => $s::num(),
													'discount_type'   => $s::str( 'e.g. percentage_on_reg, fixed_on_reg.' ),
													'quantity'        => $s::int(),
												)
											),
										)
									)
								),
								'settings' => $s::any_obj( 'Product-tab settings: skip_exist, skip_purchased, qty_selector, qty_max, ship_dynamic.' ),
								'flow'     => $s::obj(
									array(
										'jump_on_accepted' => $s::any( 'Next offer/step when accepted; true/false or a target.' ),
										'jump_on_rejected' => $s::any(),
										'jump_to_offer_on_accepted' => $s::any( 'automatic or an offer id.' ),
										'jump_to_offer_on_rejected' => $s::any(),
									),
									'Where the customer goes after this offer.'
								),
								'builder'          => $s::str( 'Page builder the Offer page was designed with.' ),
								'builder_inactive' => $s::str( 'Present when that builder\'s plugin is off: the Offer page cannot be edited or shown as designed. Tell the user first.' ),
							)
						)
					),
					'total'   => $s::int(),
				),
				'Offers on one Upsell Step.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_rules() {
		$s = 'WFFN_MCP_Schema';

		return $s::arr(
			$s::arr(
				$s::obj(
					array(
						'rule_type' => $s::str( 'What the rule checks, e.g. wfacp_page, cart_total, product.' ),
						'operator'  => $s::str(),
						'condition' => $s::any( 'Operand; shape depends on rule_type.' ),
						'gkey'      => $s::str( 'Group key.' ),
						'rkey'      => $s::str( 'Rule key.' ),
					)
				),
				'Rules in one group are ANDed.'
			),
			'Display rule groups; groups are ORed. Empty = always shown.'
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_order_bump() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'bump_id'          => $s::int(),
					'products'         => $s::arr( $s::any_obj( 'Product row: title, id, discount_type, discount_amount, quantity, regular_price, sale_price, discount_price (the unit price the shopper pays after the discount, computed live), stock, product_status.' ), 'Products the bump sells.' ),
					'active_layout'    => $s::str( 'Skin/layout key; null until one is chosen.', true ),
					'skin'             => $s::obj(
						array(
							'id'    => $s::str(),
							'name'  => $s::str(),
							'label' => $s::str( 'Short semantic name, e.g. Smart Toggle.' ),
						),
						'The skin the bump wears; change with update-bump-skin, list with list-bump-skins.'
					),
					'design'           => $s::obj(
						array(
							'groups'         => $s::arr(
								$s::obj(
									array(
										'group_key' => $s::str(),
										'group'     => $s::str( 'Accordion label as the admin designer shows it.' ),
										'fields'    => $s::arr(
											$s::obj(
												array(
													'key'  => $s::str( 'Design key update-bump-design takes.' ),
													'label' => $s::str( 'Admin designer label; absent for keys the designer does not surface.' ),
													'type' => $s::enum( array( 'text', 'toggle', 'color', 'number', 'select', 'dimension', 'image' ), 'dimension: space-separated CSS box values, e.g. "10 12 10 12".' ),
													'value' => $s::any( 'Current value; toggles are the strings true/false.' ),
													'hint' => $s::str(),
													'options' => $s::arr(
														$s::obj(
															array(
																'value'   => $s::str(),
																'label'   => $s::str(),
																'renders' => $s::bool( 'false: this checkout\'s layout never fires the position, the bump would not show.' ),
															)
														),
														'select fields: the allowed values.'
													),
													'toggler' => $s::obj(
														array(
															'key' => $s::str(),
															'value' => $s::any(),
														),
														'Present when the field only applies while another field holds this value.'
													),
												)
											)
										),
									),
									'',
									array( 'group_key', 'group', 'fields' )
								)
							),
							'product_fields' => $s::obj(
								array(
									'products' => $s::arr( $s::str( 'Product key.' ) ),
									'suffixes' => $s::arr( $s::str() ),
									'pattern'  => $s::str(),
								),
								'Per-product text keys: product_[product key]_[suffix].'
							),
						),
						'The design field by field; patch values with update-bump-design.'
					),
					'merge_tags'       => $s::arr(
						$s::obj(
							array(
								'tag'         => $s::str(),
								'description' => $s::str(),
							)
						),
						'Merge tags the design texts may use.'
					),
					'rules'            => self::schema_rules(),
					'display_position' => $s::obj(
						array(
							'fields' => $s::arr(
								$s::obj(
									array(
										'key'     => $s::str(),
										'label'   => $s::str(),
										'type'    => $s::enum( array( 'select', 'toggle', 'products' ) ),
										'value'   => $s::any(),
										'hint'    => $s::str(),
										'options' => $s::arr(
											$s::obj(
												array(
													'value'   => $s::str(),
													'label'   => $s::str(),
													'caveat'  => $s::str( 'Why this position will not show the bump on this checkout yet. It is still a valid choice: list it and relay this.' ),
												)
											),
											'select fields: the allowed values.'
										),
										'toggler' => $s::obj(
											array(
												'key'   => $s::str(),
												'value' => $s::any(),
											),
											'Present when the field only applies while another field holds this value.'
										),
									)
								)
							),
						),
						'The Settings tab: where the bump renders and its auto switches. update-bump-settings patches these keys.'
					),
					'product_settings' => $s::obj(
						array(
							'fields' => $s::arr(
								$s::obj(
									array(
										'key'     => $s::str(),
										'label'   => $s::str(),
										'type'    => $s::enum( array( 'select', 'toggle', 'products' ) ),
										'value'   => $s::any(),
										'hint'    => $s::str(),
										'options' => $s::arr(
											$s::obj(
												array(
													'value'   => $s::str(),
													'label'   => $s::str(),
													'renders' => $s::bool( 'false: this checkout\'s layout never fires the position, the bump would not show.' ),
												)
											),
											'select fields: the allowed values.'
										),
										'toggler' => $s::obj(
											array(
												'key'   => $s::str(),
												'value' => $s::any(),
											),
											'Present when the field only applies while another field holds this value.'
										),
									)
								)
							),
						),
						'The Products tab behaviour: what ticking the bump does to the cart. update-bump-products settings patches these keys.'
					),
				),
				'One Order Bump configuration. Every key is present; an empty bump has products [].',
				array( 'bump_id', 'products', 'skin', 'design', 'merge_tags', 'rules', 'display_position', 'product_settings' )
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_upsell_settings() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'step_id'  => $s::int( 'Upsell Step id.' ),
					'rules'    => self::schema_rules(),
					'settings' => $s::map( $s::any_obj( 'Tab values.' ), 'Step settings keyed by tab slug.' ),
					'offers'   => $s::arr(
						$s::obj(
							array(
								'offer_id' => $s::int(),
								'title'    => $s::str(),
								'settings' => $s::map( $s::any_obj(), 'Offer settings keyed by tab slug.' ),
							)
						)
					),
				),
				'Upsell Step rules and settings.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_checkout_conditional_fields() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'step_id'            => $s::int( 'Checkout Step id.' ),
					'fields'             => $s::arr(
						$s::obj(
							array(
								'id'        => $s::str( 'Field key, e.g. billing_email.' ),
								'label'     => $s::str(),
								'section'   => $s::str( 'Fieldset the field sits in.' ),
								'type'      => $s::str( 'Input type.' ),
								'enabled'   => $s::bool(),
								'condition' => $s::any( 'When to show or hide the field; null when none.' ),
							)
						)
					),
					'section_conditions' => $s::map( $s::any( 'Condition or null.' ), 'Keyed by fieldset id.' ),
				),
				'Conditional Fields of a Checkout Step.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_thankyou_rules() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'step_id' => $s::int( 'Thank You Step id.' ),
					'rules'   => self::schema_rules(),
				),
				'Thank You Step display rules.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_offer_performance() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'window' => $s::window(),
					'items'  => $s::arr(
						$s::obj(
							array(
								'id'              => $s::id( 'Offer or bump id.' ),
								'title'           => $s::str(),
								'fid'             => $s::id( 'Funnel id.' ),
								'funnel_name'     => $s::str( 'Null when the Funnel has been deleted.', true ),
								'type'            => $s::str( 'wfob_bump (order bump) or wfocu_offer (upsell offer).' ),
								'conversion'      => $s::num( 'Accepted count.' ),
								'revenue'         => $s::num( 'Store currency.' ),
								'views'           => $s::num( 'Times shown.' ),
								'conversion_rate' => $s::num( 'Percent, 0-100.' ),
							)
						)
					),
					'total'  => $s::int( 'Total offers/bumps, ignoring pagination.' ),
				),
				'Bump and upsell offer performance.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_revenue_totals() {
		$s = 'WFFN_MCP_Schema';

		return $s::obj(
			array(
				'orders'           => $s::num(),
				'revenue'          => $s::num( 'Store currency.' ),
				'checkout_revenue' => $s::num(),
				'upsell_revenue'   => $s::num(),
				'bump_revenue'     => $s::num(),
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_funnel_graph() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'window' => $s::window(),
					'data'   => $s::obj(
						array(
							'totals'        => self::schema_revenue_totals(),
							'intervals'     => $s::arr( self::schema_interval( self::schema_revenue_totals() ), 'Time series.' ),
							'interval_type' => $s::str( 'Bucket size used: day, week, month or year.', true ),
						)
					),
				),
				'Revenue time series.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_funnel_order() {
		$s    = 'WFFN_MCP_Schema';
		$line = $s::obj(
			array(
				'title'          => $s::str( 'Product name.' ),
				'subtotal_total' => $s::num( 'Line subtotal, store currency.' ),
			)
		);

		return $s::pro(
			$s::obj(
				array(
					'order_id'    => $s::int(),
					'items'       => $s::obj(
						array(
							'checkout' => $s::arr( $line, 'Bought on the checkout.' ),
							'bump'     => $s::arr( $line, 'Added via order bumps.' ),
							'upsell'   => $s::arr( $line, 'Added via one-click upsells.' ),
						),
						'Line items grouped by the Funnel part that sold them.'
					),
					'order_total' => $s::num( 'Store currency.' ),
				),
				'One order as the Funnel saw it.'
			)
		);
	}

	/**
	 * @return array
	 */
	private static function schema_get_funnel_performance() {
		$s = 'WFFN_MCP_Schema';

		return $s::pro(
			$s::obj(
				array(
					'funnel_id' => $s::int(),
					'title'     => $s::str(),
					'window'    => $s::window(),
					'currency'  => $s::str( 'ISO currency code.' ),
					'orders'    => $s::int(),
					'revenue'   => $s::obj(
						array(
							'total'    => $s::num( 'Store currency.' ),
							'checkout' => $s::num(),
							'upsell'   => $s::num(),
							'bump'     => $s::num(),
						)
					),
				),
				'Orders and revenue split for one Funnel.'
			)
		);
	}
}
