<?php

defined( 'ABSPATH' ) || exit;

/**
 * Step design with Elementor: makes a Funnel Step something Elementor's own
 * abilities can design, and runs those abilities from the FunnelKit server so
 * one connection builds a funnel's structure and its pages.
 *
 * Elementor's abilities edit atomic (V4) elements of any post that supports
 * Elementor - every Step post type does - but they only reach the live page when
 * the post is marked as built with Elementor, and they cannot place classic
 * widgets such as the checkout or optin form. prepare-step-design closes both
 * gaps. Elementor's abilities are not tools of this server: read-design and
 * edit-design list the ones they run, hand over one's instructions and schema
 * (describe), and run them - on Funnel Steps only, behind
 * FunnelKit's write switch - so a session carries two short tools instead of
 * Elementor's long specs, and follows whatever the installed Elementor ships.
 *
 * A Funnel Builder Pro feature: every tool answers through WFFN_Pro_MCP::gate,
 * so a lapsed or inactive licence gets the Pro notice instead of a design,
 * and Funnel Builder alone lists them as Pro tools rather than carrying them.
 *
 * Language: labels are translated, descriptions and errors stay in English -
 * they are the tool spec a model reasons over (see the module README).
 */
class FKMCP_Pack_Elementor_Design extends FKMCP_Pack {

	/**
	 * Elementor abilities this server runs: whether each one writes, and a line
	 * for the list (Elementor's own descriptions run to pages).
	 */
	const ELEMENTOR_TOOLS = array(
		'elementor/get-page-structure'  => array(
			'writes'  => false,
			'summary' => 'A Step\'s element tree (ids, types, version 4 = editable), optionally one subtree with its settings and styles.',
		),
		'elementor/list-widget-schemas' => array(
			'writes'  => false,
			'summary' => 'Every widget type the design tools can configure.',
		),
		'elementor/get-widget-schema'   => array(
			'writes'  => false,
			'summary' => 'One widget type\'s settings schema, with defaults.',
		),
		'elementor/list-resources'      => array(
			'writes'  => false,
			'summary' => 'Elementor\'s reference resources: global classes and variables, style guides, interactions.',
		),
		'elementor/read-resource'       => array(
			'writes'  => false,
			'summary' => 'Reads one resource by URI, e.g. elementor://global-variables.',
		),
		'elementor/list-assets'         => array(
			'writes'  => false,
			'summary' => 'Images, SVGs and videos in the Media Library, to place by id.',
		),
		'elementor/get-default-styles'  => array(
			'writes'  => false,
			'summary' => 'The site\'s default styles for one HTML tag.',
		),
		'elementor/build-composition'   => array(
			'writes'  => true,
			'summary' => 'Builds sections of atomic elements on a Step from XML, with their styles.',
		),
		'elementor/manage-elements'     => array(
			'writes'  => true,
			'summary' => 'Updates, moves, duplicates or deletes atomic elements on a Step.',
		),
		'elementor/publish-document'    => array(
			'writes'  => true,
			'summary' => 'Publishes a Step\'s pending edits to the live page.',
		),
	);

	/** @var FKMCP_Pack_Elementor_Design|null */
	private static $instance = null;

	/** @var array|null FunnelKit's own design tools, built once per request. */
	private static $funnelkit_tools = null;

	/** @var array|null Step design types, built once per request. */
	private static $types = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hook_server();
		}

		return self::$instance;
	}

	/** Server-side hooks, added once when the pack is collected. */
	private function hook_server() {
		add_filter( 'fkmcp_funnel_ready_offers', array( __CLASS__, 'offer_customising' ), 10, 3 );
		add_filter( 'fkmcp_import_status_guidance', array( __CLASS__, 'import_status_guidance' ) );
		add_filter( 'fkmcp_create_funnel_brief', array( __CLASS__, 'create_funnel_brief' ) );
		add_filter( 'fkmcp_scrub_result', array( __CLASS__, 'keep_raw' ), 10, 2 );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_elementor_abilities' ), 20 );
	}

	/** Funnel Builder's capability vocabulary, as the Funnels pack uses it. */
	public function default_capability() {
		return array( 'funnel', 'read' );
	}

	public function can( $capability ) {
		list( $cap, $access ) = array_pad( (array) $capability, 2, 'read' );

		return WFFN_MCP_Permissions::can_access( $cap, $access );
	}

	public function is_available() {
		return function_exists( 'WFFN_Core' ) && defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' );
	}

	/** Funnel Builder's own tools: same plugin id, so the settings screen lists them with the Funnels pack. */
	/** Funnel Builder Pro's tools: the settings screen lists them with the Pro pack, under Step Design. */
	public function plugin_meta() {
		return array(
			'id'     => 'funnel-builder-pro',
			'pro'    => true,
			'name'   => __( 'Funnel Builder Pro', 'funnel-builder-powerpack' ),
			'colour' => '#F5841F',
			'groups' => array(
				'step-design' => __( 'Step Design', 'funnel-builder-powerpack' ),
				'read-design' => __( 'Step Design', 'funnel-builder-powerpack' ),
				'edit-design' => __( 'Step Design', 'funnel-builder-powerpack' ),
			),
		);
	}

	/**
	 * How to design a Step with the design tools, step by step. Returned as
	 * `workflow` by get-step-design and prepare-step-design rather than carried
	 * in a tool description: the FunnelKit AI gateway hides any tool whose
	 * description passes 2,000 characters, and a result has no such bound.
	 */
	public static function workflow() {
		return 'Elementor\'s design tools (elementor/build-composition, elementor/manage-elements, elementor/publish-document, elementor/get-page-structure ...) and FunnelKit\'s funnel widget tools (funnelkit/get-funnel-widgets, funnelkit/update-funnel-widget, funnelkit/move-funnel-widget) are run through this server: read-design runs the ones that read and edit-design the ones that write (tool = its name, input = its input), and each lists its tools; before you first use a tool, call its runner with describe: true for its instructions and input schema. Design to the user\'s brief when they gave one - their inspiration, colours and fonts. Designing a Step: 1) prepare-step-design (once per Step) - with the Atomic Editor on, FunnelKit templates arrive as atomic elements the Elementor tools can edit, so to use a template the user names import that template itself (create-funnel mode template, update-step-template) and restyle or edit it; never rebuild it from scratch, and never stand another Funnel in for it 2) read elementor/get-widget-schema / elementor/read-resource for the widget props, global classes and variables 3) elementor/build-composition with post_id = the Step id (post_id from prepare-step-design) 4) elementor/manage-elements to change, move or delete what you built; put the funnel widgets (checkout form, optin form, order details, offer controls - classic widgets) where your layout needs them with funnelkit/move-funnel-widget (into a flexbox you built, e.g. the right column of a two-column checkout) 5) restyle every funnel widget to match your design - this is part of designing the Step, not optional: they keep FunnelKit\'s own look (its fonts, colours and button) until you change them, so a page styled only around them looks unfinished. funnelkit/get-funnel-widgets with search (e.g. button, color, typography, heading, border) lists the settings; set the fonts, heading and text colours, field borders and the button\'s colour, text and radius with funnelkit/update-funnel-widget, or brand the colours with prepare-step-design brand_color 6) work in rounds until the Step is right: read-design funnelkit/check-step-design scores the page (edit-design returns the same as quality after every change) - fix its findings, biggest cost first, and check again; the Step is done only when ready is true (90+ with no defect) 7) elementor/publish-document - edits to a published Step stay in a draft until then 8) open the Step\'s view link and look at it at desktop and phone width as a visitor would, if you can open pages - every Step, not only the first; if anything looks off (cramped, cluttered, hard to read, clashing with the funnel widgets), go back to 6. Tell the user a Step is finished only when it is ready and you have looked at it. The Elementor tools on this server work on Funnel Steps only. Site-wide styles: when the site has global classes or variables (elementor/read-resource elementor://global-classes and elementor://global-variables), use them on your elements instead of typing the same colours, fonts and sizes - attach a class with elementor/manage-elements (classes, by label) and, since an element\'s own style wins over a class for the same property, rewrite that element\'s own style without those properties (style_apply_mode replace); use them as they are - never create, edit or delete a global class or variable, since they restyle the whole site (if the user wants one, they set it up in Elementor first). Content rules: never type prices, product names or discounts into your own elements - the funnel widgets show them live from the products and offers, and typed values go stale; do not show the order bump or the upsell offer on another Step (the upsell is a one-time offer made after payment). Structure rules - build each thing as what it is: a button or link that moves the visitor on links to next_step_link (?wffn-next-link=yes), never a typed address of a Step or the checkout, which skips the funnel\'s bumps and upsells and breaks when the funnel changes; a link is a link - header and footer links (Privacy Policy, Terms, Contact) carry their link (the element\'s link setting, or <a href> in the text), never plain text that looks like one; a list of points is a list - a paragraph with <ul><li>, one item per line - never ticks, arrows or bullets typed as characters, nor a stack of icon-and-text rows; a star rating is e-svg star icons (fa-star) in a row, never ★ characters; a header or footer is an e-flexbox with tag header or footer (nav for its links); a look repeated on several elements uses the site\'s global class when it has one.';
	}

	public function definitions() {
		if ( null !== $this->definitions ) {
			return $this->definitions;
		}

		$this->definitions = array(
			'get-step-design'     => array(
				'label'               => __( 'Get Step Design', 'funnel-builder-powerpack' ),
				'description'         => 'How a Step is designed and whether Elementor\'s design tools can edit it: the FunnelKit design (template slug + builder), whether Elementor and its Atomic Editor are available, whether the Step is built with Elementor, the funnel widgets the Step needs (checkout form, optin form, order details) with their element ids, the top-level elements with their Elementor version (only version 4 elements can be edited by the Elementor tools), unpublished changes, the link a button uses to go to the next Step, and next when the Step shows classic content the tools cannot edit. step_id: a Step id, or an Upsell Offer id when Funnel Builder Pro is active. The result\'s workflow says how to design a Step with the design tools (prepare-step-design, read-design, edit-design): read it before you design and follow it. Read-only.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::design_output_schema(),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => array( $this, 'get_step_design' ),
				'annotations'         => array( 'readonly' => true ),
			),
			'prepare-step-design' => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Prepare Step Design', 'funnel-builder-powerpack' ),
				'description'         => 'Make a Step ready for Elementor\'s design tools; call it once per Step before edit-design. mode keep (default): the Step must already show an Elementor design (an Elementor template or build-from-scratch) and its content is kept. mode blank: the Step\'s design is replaced by a blank Elementor layout - its sections and text are lost, so confirm with the user first - but the funnel widgets it already has (checkout form and order summary, optin form, order and customer details, the upsell offer\'s price, product and accept / decline controls) are kept with their settings, moved into one container; use it to design a Step from scratch or to move a Step off another builder. A Step that is still empty (no template and no content, as each Step of a Funnel created from scratch starts) is switched to a blank Elementor layout in either mode, with nothing lost and nothing to confirm; the result says mode blank. Either way the Step is marked as built with Elementor (without it Elementor\'s edits never reach the live page), given FunnelKit Canvas when it uses the theme\'s page template (a template chosen for it is kept), and the funnel widgets it needs and lacks are added in their own container: the checkout form on a Checkout, the optin form on an Optin, the order and customer details on a Thank You page, the accept and decline controls on an Upsell Offer. Those are classic widgets the Elementor tools cannot place, move or edit: change their settings with funnelkit/update-funnel-widget and move them with funnelkit/move-funnel-widget (run through read-design / edit-design), never delete and rebuild them. brand_color brands their colours; never use custom CSS for that. Returns post_id for the Elementor tools, next_step_link (set it as a button\'s link to send the visitor to the next Step) and workflow, the steps to design the Step: follow it. Write.',
				'input_schema'        => WFFN_MCP_Schema::input(
					array(
						'step_id'     => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
						'mode'        => WFFN_MCP_Schema::in_enum( array( 'keep', 'blank' ), 'keep (default) or blank.' ),
						'brand_color' => WFFN_MCP_Schema::in_str(
							'Label of an Elementor colour variable synced to Global Colors (see elementor/read-resource elementor://global-variables), e.g. brand-accent. The Step\'s funnel widgets - checkout form buttons and checks, optin button, upsell accept button - take this colour and follow the variable when it changes. The variable is used as the site has it, never changed.',
							array(
								'pattern'   => '^[A-Za-z0-9_-]+$',
								'maxLength' => 64,
							)
						),
					),
					array( 'step_id' )
				),
				'output_schema'       => self::design_output_schema(
					array(
						'mode'     => WFFN_MCP_Schema::str( 'The mode applied.' ),
						'switched' => WFFN_MCP_Schema::bool( 'Whether the Step\'s FunnelKit design was changed to a blank Elementor layout.' ),
						'canvas'   => WFFN_MCP_Schema::bool( 'Whether the Step\'s page template was changed from the theme\'s to FunnelKit Canvas.' ),
						'added'    => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'Funnel widgets added by this call.' ),
						'kept'     => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'Funnel widgets a blank reset kept, with their settings.' ),
						'brand'    => WFFN_MCP_Schema::obj(
							array(
								'variable' => WFFN_MCP_Schema::str( 'The colour variable applied.' ),
								'global'   => WFFN_MCP_Schema::str( 'The classic global colour it is published as.' ),
								'applied'  => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'widget type:colour control pairs now following it.' ),
							),
							'Present when brand_color was given.'
						),
					)
				),
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => array( $this, 'prepare_step_design' ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		);

		$this->definitions += $this->design_tool_definitions();

		// Pro tools: a licence that is not active answers with the Pro notice, which the result schema allows.
		foreach ( $this->definitions as $slug => $definition ) {
			$this->definitions[ $slug ]['execute_callback'] = WFFN_Pro_MCP::gate( $definition['execute_callback'] );
			$this->definitions[ $slug ]['output_schema']    = WFFN_MCP_Schema::pro( $definition['output_schema'] );
		}

		return $this->definitions;
	}

	/**
	 * FunnelKit's own design tools, run like Elementor's through read-design
	 * and edit-design rather than listed on the server: the classic widgets on
	 * a Step - the funnel widgets above all - which Elementor's tools cannot
	 * edit or move. Each: label, description, input_schema, result_schema,
	 * writes, summary, and run (the FKMCP_Funnel_Widgets method).
	 *
	 * @return array tool name => definition
	 */
	private static function funnelkit_tools() {
		if ( null !== self::$funnelkit_tools ) {
			return self::$funnelkit_tools;
		}

		$finding = WFFN_MCP_Schema::obj(
			array(
				'code'       => WFFN_MCP_Schema::str(),
				'cost'       => WFFN_MCP_Schema::int( 'Points it takes off the score; 0 is a note.' ),
				'message'    => WFFN_MCP_Schema::str( 'What is wrong and how to fix it.' ),
				'element_id' => WFFN_MCP_Schema::str( 'The element it is about, when it is about one.' ),
			)
		);

		self::$funnelkit_tools = array(
			'funnelkit/check-step-design'    => array(
				'label'         => __( 'Check Step Design', 'funnel-builder-powerpack' ),
				'description'   => 'How well a Step\'s page is made, as a designer checks a page before it goes live - on the design you are working on (the draft of a published Step): the funnel widgets present and styled to match (not FunnelKit\'s default look), one main headline, readable contrast, few typefaces and text colours, a layout that holds on a phone (no wide fixed widths, columns that stack, headings that fit), no placeholder copy or typed prices, buttons that go somewhere (a Sales Page\'s main button to next_step_link), the template\'s sample images replaced. A score out of 100 with a finding per shortfall saying how to fix it; ready is true at 90+ with no defect (missing or unstyled funnel widget, placeholder text, dead button, unreadable text). Work in rounds: fix the findings, check again, and finish only when ready. edit-design returns this as quality after every change. Read-only.',
				'input_schema'  => WFFN_MCP_Schema::input(
					array(
						'step_id' => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
					),
					array( 'step_id' )
				),
				'result_schema' => WFFN_MCP_Schema::obj(
					array(
						'step_id'     => WFFN_MCP_Schema::int(),
						'score'       => WFFN_MCP_Schema::int( 'Out of 100.' ),
						'grade'       => WFFN_MCP_Schema::enum( array( 'good', 'fair', 'needs_work' ) ),
						'ready'       => WFFN_MCP_Schema::bool( '90+ and no defect: the page can go live.' ),
						'findings'    => WFFN_MCP_Schema::arr( $finding, 'Biggest cost first.' ),
						'unpublished' => WFFN_MCP_Schema::bool( 'The checked design waits in the draft.' ),
						'next'        => WFFN_MCP_Schema::str(),
					)
				),
				'writes'        => false,
				'summary'       => 'Scores the Step\'s page out of 100 - funnel widgets styled, headline, contrast, fonts, phone layout, copy, links - with a fix for each finding; finish when ready.',
				'run'           => array( 'FKMCP_Step_Design_Quality', 'check' ),
			),
			'funnelkit/get-funnel-widgets'   => array(
				'label'         => __( 'Get Funnel Widgets', 'funnel-builder-powerpack' ),
				'description'   => 'The classic widgets on a Step - FunnelKit\'s funnel widgets (checkout form and order summary, optin form, order and customer details, the upsell offer\'s price, product and accept / decline controls) and any classic Elementor widget the page kept - which the Elementor tools cannot edit. For each: element_id, widget_type, its settings as set now (values left at their default are not listed), and how many settings it has. search (a word such as button, color, typography, heading) lists the matching settings with their type, label, default and allowed options, so you know what funnelkit/update-funnel-widget accepts; element_id narrows to one widget. Read-only.',
				'input_schema'  => WFFN_MCP_Schema::input(
					array(
						'step_id'    => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
						'element_id' => WFFN_MCP_Schema::in_str( 'One widget\'s element id.', array( 'pattern' => '^[A-Za-z0-9]{1,16}$' ) ),
						'search'     => WFFN_MCP_Schema::in_search( 'Words to match in setting names and labels, e.g. button color.', 64 ),
					),
					array( 'step_id' )
				),
				'result_schema' => WFFN_MCP_Schema::obj(
					array(
						'step_id' => WFFN_MCP_Schema::int(),
						'widgets' => WFFN_MCP_Schema::arr(
							WFFN_MCP_Schema::obj(
								array(
									'element_id'     => WFFN_MCP_Schema::str(),
									'widget_type'    => WFFN_MCP_Schema::str(),
									'title'          => WFFN_MCP_Schema::str( 'The widget\'s name in Elementor.' ),
									'settings'       => WFFN_MCP_Schema::any_obj( 'Settings as set now; unset ones take their default.' ),
									'settings_count' => WFFN_MCP_Schema::int( 'How many settings the widget has.' ),
									'controls'       => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::any_obj(), 'With search: the matching settings (name, type, label, default, options).' ),
									'more'           => WFFN_MCP_Schema::int( 'Matching settings left out: narrow the search.' ),
								)
							)
						),
					)
				),
				'writes'        => false,
				'summary'       => 'A Step\'s classic widgets (checkout form, optin form, order details, offer controls) and their settings; search lists the settings you can change.',
				'run'           => 'get',
			),
			'funnelkit/update-funnel-widget' => array(
				'label'         => __( 'Update Funnel Widget', 'funnel-builder-powerpack' ),
				'description'   => 'Change the settings of one classic widget on a Step (see funnelkit/get-funnel-widgets): FunnelKit\'s funnel widgets and any classic Elementor widget, which the Elementor tools cannot edit. The widget stays where it is, with every setting you do not name - never delete and rebuild a funnel widget. settings: a map of setting name to value, in the shape funnelkit/get-funnel-widgets shows (a colour as #hex, a size as {size, unit}, a dimension as {top, right, bottom, left, unit, isLinked}, a choice as one of its options); null removes a setting so it takes its default. Responsive settings take the _tablet / _mobile suffix. globals: colour or typography settings to follow one of the site\'s global colours or fonts (setting name => its id, e.g. button_color => primary), only ones the site has. Like the Elementor tools, a published Step\'s change waits in its draft until elementor/publish-document: publish, then open the Step and look at it. Write.',
				'input_schema'  => WFFN_MCP_Schema::input(
					array(
						'step_id'    => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
						'element_id' => WFFN_MCP_Schema::in_str( 'The widget\'s element id, from funnelkit/get-funnel-widgets.', array( 'pattern' => '^[A-Za-z0-9]{1,16}$' ) ),
						'settings'   => WFFN_MCP_Schema::in_map( array( 'type' => array( 'string', 'number', 'integer', 'boolean', 'object', 'array', 'null' ) ), 'Setting name => value; null removes it.', array( 'maxProperties' => 60 ) ),
						'globals'    => WFFN_MCP_Schema::in_map(
							array(
								'type'      => 'string',
								'maxLength' => 64,
							),
							'Setting name => a global colour or font id the site has; an empty string unlinks it.',
							array( 'maxProperties' => 20 )
						),
					),
					array( 'step_id', 'element_id' )
				),
				'result_schema' => WFFN_MCP_Schema::obj(
					array(
						'step_id'     => WFFN_MCP_Schema::int(),
						'element_id'  => WFFN_MCP_Schema::str(),
						'widget_type' => WFFN_MCP_Schema::str(),
						'changed'     => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'Settings set.' ),
						'removed'     => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'Settings back to their default.' ),
						'unpublished' => WFFN_MCP_Schema::bool( 'The change waits in the Step\'s draft for elementor/publish-document.' ),
						'next'        => WFFN_MCP_Schema::str(),
					)
				),
				'writes'        => true,
				'summary'       => 'Changes the settings of one classic widget - colours, fonts, texts, layout - keeping it where it is.',
				'run'           => 'update',
			),
			'funnelkit/move-funnel-widget'   => array(
				'label'         => __( 'Move Funnel Widget', 'funnel-builder-powerpack' ),
				'description'   => 'Move one classic widget on a Step (see funnelkit/get-funnel-widgets) - a funnel widget such as the checkout form, or any classic Elementor widget - into another container, settings and all: into an atomic flexbox or div block you built with elementor/build-composition (to place the checkout form inside your two-column layout, say), or into a classic container or column. The Elementor tools cannot move classic widgets. index: its position among the container\'s children (default last). Classic containers the move leaves empty are removed; an empty column in a row that still holds widgets stays. A published Step\'s change waits in its draft until elementor/publish-document: publish, then open the Step and look at it. Write.',
				'input_schema'  => WFFN_MCP_Schema::input(
					array(
						'step_id'       => WFFN_MCP_Schema::in_id( 'Step id (or Upsell Offer id).' ),
						'element_id'    => WFFN_MCP_Schema::in_str( 'The classic widget\'s element id, from funnelkit/get-funnel-widgets.', array( 'pattern' => '^[A-Za-z0-9]{1,16}$' ) ),
						'new_parent_id' => WFFN_MCP_Schema::in_str( 'Element id of the container to move it into.', array( 'pattern' => '^[A-Za-z0-9]{1,16}$' ) ),
						'index'         => WFFN_MCP_Schema::in_int( 'Position among the container\'s children, from 0; default last.', array( 'minimum' => 0 ) ),
					),
					array( 'step_id', 'element_id', 'new_parent_id' )
				),
				'result_schema' => WFFN_MCP_Schema::obj(
					array(
						'step_id'       => WFFN_MCP_Schema::int(),
						'element_id'    => WFFN_MCP_Schema::str(),
						'widget_type'   => WFFN_MCP_Schema::str(),
						'new_parent_id' => WFFN_MCP_Schema::str(),
						'index'         => WFFN_MCP_Schema::int(),
						'removed_empty' => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'Classic containers the move left empty, removed.' ),
						'unpublished'   => WFFN_MCP_Schema::bool( 'The change waits in the Step\'s draft for elementor/publish-document.' ),
						'next'          => WFFN_MCP_Schema::str(),
					)
				),
				'writes'        => true,
				'summary'       => 'Moves one classic widget, settings and all, into another container - e.g. the checkout form into your two-column layout.',
				'run'           => 'move',
			),
		);

		return self::$funnelkit_tools;
	}

	/**
	 * read-design and edit-design: the design tools - FunnelKit's for the
	 * funnel widgets and Elementor's for the atomic elements - run through
	 * this server rather than listed on it. Each lists its tools with a line
	 * on what they do; describe hands over one tool's instructions and schemas.
	 *
	 * @return array
	 */
	private function design_tool_definitions() {
		$catalogue  = static function ( $writes ) {
			$lines = array();

			foreach ( self::design_tools() as $name => $tool ) {
				if ( $tool['writes'] === $writes ) {
					$lines[] = $name . ( '' !== $tool['summary'] ? ': ' . $tool['summary'] : '' );
				}
			}

			return implode( ' | ', $lines );
		};
		$tool_input = static function ( array $names ) {
			return WFFN_MCP_Schema::input(
				array(
					'tool'     => WFFN_MCP_Schema::in_enum( $names, 'The design tool.' ),
					'describe' => WFFN_MCP_Schema::in_bool( 'true: do not run the tool - return its instructions, input schema and result schema. Do this before you first use a tool: its input is checked against that schema.' ),
					'input'    => WFFN_MCP_Schema::in_obj( array(), 'The tool\'s input, as its input schema says (describe). The tools work on Funnel Steps only: post_id / step_id = the Step id.', array(), array( 'additionalProperties' => true ) ),
				),
				array( 'tool' )
			);
		};
		$run_output = WFFN_MCP_Schema::obj(
			array(
				'tool'          => WFFN_MCP_Schema::str( 'The tool run or described.' ),
				'step_id'       => WFFN_MCP_Schema::int( 'The Step it worked on, when it takes a post.' ),
				'result'        => WFFN_MCP_Schema::any_obj( 'The tool\'s answer, as its result schema says.' ),
				'next'          => WFFN_MCP_Schema::str( 'What to do next on this Step - follow it.' ),
				'quality'       => WFFN_MCP_Schema::obj(
					array(
						'score'    => WFFN_MCP_Schema::int(),
						'grade'    => WFFN_MCP_Schema::str(),
						'ready'    => WFFN_MCP_Schema::bool(),
						'findings' => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::any_obj(), 'The biggest first: code, cost, message, element_id.' ),
					),
					'After a change: the Step\'s quality, as funnelkit/check-step-design gives it. Keep going until ready.'
				),
				'label'         => WFFN_MCP_Schema::str( 'describe: the tool\'s name for people.' ),
				'description'   => WFFN_MCP_Schema::str( 'describe: the tool\'s instructions - Elementor\'s own for an Elementor tool.' ),
				'input_schema'  => WFFN_MCP_Schema::any_obj( 'describe: JSON Schema of its input.' ),
				'result_schema' => WFFN_MCP_Schema::any_obj( 'describe: JSON Schema of its answer.' ),
			)
		);
		$how        = ' tool: its name; describe: true for its instructions and schemas instead of running it (do this before you first use a tool); input: its input, as its input schema says - post_id / step_id is the Funnel Step id. Returns the tool\'s answer as result.';

		return array(
			'read-design' => array(
				'label'               => __( 'Read Design', 'funnel-builder-powerpack' ),
				'description'         => 'Run one of the design tools that read a Step\'s page - FunnelKit\'s for the funnel widgets (checkout form, optin form, order details, offer controls - classic widgets) and Elementor\'s for the atomic elements. The tools: ' . $catalogue( false ) . '. The tools that change the page run with edit-design.' . $how . ' Read-only.',
				'input_schema'        => $tool_input( self::tool_names( false ) ),
				'output_schema'       => $run_output,
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_read' ),
				'execute_callback'    => array( $this, 'read_design' ),
				'annotations'         => array( 'readonly' => true ),
			),
			'edit-design' => array(
				'capability'          => array( 'funnel', 'write' ),
				'label'               => __( 'Edit Design', 'funnel-builder-powerpack' ),
				'description'         => 'Run one of the design tools that change a Funnel Step\'s page (call prepare-step-design first). The tools: ' . $catalogue( true ) . '. A change that would delete a funnel widget is refused; the Step\'s design before the first change, and each publish, go into its History.' . $how . ' Write.',
				'input_schema'        => $tool_input( self::tool_names( true ) ),
				'output_schema'       => $run_output,
				'permission_callback' => array( 'WFFN_MCP_Permissions', 'can_write' ),
				'execute_callback'    => array( $this, 'edit_design' ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		);
	}

	private static function design_output_schema( array $extra = array() ) {
		return WFFN_MCP_Schema::obj(
			array_merge(
				array(
					'step_id'          => WFFN_MCP_Schema::int( 'Step (or Offer) id.' ),
					'post_id'          => WFFN_MCP_Schema::int( 'The post id the Elementor tools take - the Step id itself.' ),
					'type'             => WFFN_MCP_Schema::str( 'Step type slug.' ),
					'funnel_id'        => WFFN_MCP_Schema::int(),
					'title'            => WFFN_MCP_Schema::str(),
					'status'           => WFFN_MCP_Schema::str( 'publish or draft.' ),
					'design'           => WFFN_MCP_Schema::obj(
						array(
							'slug'    => WFFN_MCP_Schema::str( 'Template slug.' ),
							'builder' => WFFN_MCP_Schema::str( 'Page builder key.' ),
						),
						'The FunnelKit design the Step shows.'
					),
					'page_template'    => WFFN_MCP_Schema::str( 'The page template it renders with: a FunnelKit Canvas file (wflp-canvas.php, wfacp-canvas.php ...) shows the design alone; empty or default is the theme\'s, with its header, footer and sidebar around the design - prepare-step-design sets Canvas.' ),
					'elementor'        => WFFN_MCP_Schema::obj(
						array(
							'active'               => WFFN_MCP_Schema::bool(),
							'version'              => WFFN_MCP_Schema::str(),
							'design_tools'         => WFFN_MCP_Schema::bool( 'Elementor\'s design abilities are registered.' ),
							'atomic_editor'        => WFFN_MCP_Schema::bool( 'Elementor\'s Atomic Editor is on - the design tools need it.' ),
							'outdated'             => WFFN_MCP_Schema::bool( 'Elementor is too old for designing Steps here (no design abilities or Atomic Editor): tell the user, do not offer customising.' ),
							'built_with_elementor' => WFFN_MCP_Schema::bool(),
							'ready'                => WFFN_MCP_Schema::bool( 'The Elementor tools can design this Step now.' ),
							'reason'               => WFFN_MCP_Schema::str( 'Why not, when not ready.' ),
						)
					),
					'required_widgets' => WFFN_MCP_Schema::arr(
						WFFN_MCP_Schema::obj(
							array(
								'widget_type' => WFFN_MCP_Schema::str(),
								'label'       => WFFN_MCP_Schema::str(),
								'present'     => WFFN_MCP_Schema::bool(),
								'element_id'  => WFFN_MCP_Schema::str( 'Elementor element id, when present.' ),
							)
						),
						'Funnel widgets this Step needs to work.'
					),
					'elements'         => WFFN_MCP_Schema::arr(
						WFFN_MCP_Schema::obj(
							array(
								'id'      => WFFN_MCP_Schema::str(),
								'type'    => WFFN_MCP_Schema::str( 'Element or widget type.' ),
								'version' => WFFN_MCP_Schema::int( '4 = atomic (editable by the Elementor tools), 3 = classic.' ),
							)
						),
						'Top-level elements, in page order.'
					),
					'unpublished'      => WFFN_MCP_Schema::bool( 'Your Elementor edits are waiting for elementor/publish-document.' ),
					'next'             => WFFN_MCP_Schema::str( 'What to do first, when the Step shows classic Elementor content.' ),
					'workflow'         => WFFN_MCP_Schema::str( 'How to design this Step with the design tools, step by step: read it before you design and follow it.' ),
					'ask_user'         => WFFN_MCP_Schema::arr( WFFN_MCP_Schema::str(), 'What to tell the user, word for word or close to it (an Elementor too old to customise the design here).' ),
					'restore_points'   => WFFN_MCP_Schema::arr(
						WFFN_MCP_Schema::obj(
							array(
								'revision_id' => WFFN_MCP_Schema::int(),
								'kind'        => WFFN_MCP_Schema::enum( array( self::BEFORE_AI, self::AI_RESULT ) ),
								'label'       => WFFN_MCP_Schema::str( 'How to name it to the user.' ),
								'saved'       => WFFN_MCP_Schema::str( 'When, ISO 8601.' ),
								'by'          => WFFN_MCP_Schema::str( 'Whose session saved it.' ),
							)
						),
						'Newest first: the revisions saved before and after AI changes. The user restores one in the Elementor editor, History > Revisions.'
					),
					'locked_by'        => WFFN_MCP_Schema::str( 'Who has the Step open in the Elementor editor; changes are refused until they close it.' ),
					'next_step_link'   => WFFN_MCP_Schema::url( 'Link destination that sends the visitor to the next Step.', true ),
					'links'            => WFFN_MCP_Schema::obj(
						array(
							'edit'      => WFFN_MCP_Schema::url( 'Step settings in FunnelKit.' ),
							'elementor' => WFFN_MCP_Schema::url( 'The Step in the Elementor editor.' ),
							'view'      => WFFN_MCP_Schema::url( 'Live page.' ),
						)
					),
				),
				$extra
			)
		);
	}

	/**
	 * fkmcp_funnel_ready_offers: with Elementor's design tools on this server,
	 * ask the user whether to make the imported template their own (the agent
	 * then edits it in place - see get-import-status).
	 *
	 * @param array $offers    Offers so far.
	 * @param int   $funnel_id Funnel id.
	 * @param array $steps     The Funnel's Steps.
	 *
	 * @return array
	 */
	/**
	 * fkmcp_import_status_guidance: how to customise an imported template, for
	 * get-import-status - only when Elementor's design tools are on this server.
	 *
	 * @param string $guidance Guidance so far.
	 *
	 * @return string
	 */
	public static function import_status_guidance( $guidance ) {
		if ( ! self::design_tools_on() ) {
			return $guidance;
		}

		return $guidance . ' When they want it customised, edit the imported template in place with the Elementor tools (get-step-design, elementor/get-page-structure, elementor/manage-elements with the site\'s variables, elementor/publish-document), restyle and place the funnel widgets with funnelkit/get-funnel-widgets, funnelkit/update-funnel-widget and funnelkit/move-funnel-widget, and brand them with prepare-step-design brand_color; never rebuild it.';
	}

	/**
	 * What create-funnel's brief asks when the Steps can be designed here:
	 * the look as well as the template.
	 *
	 * @param string $brief The brief's questions so far.
	 *
	 * @return string
	 */
	public static function create_funnel_brief( $brief ) {
		if ( ! self::design_tools_on() ) {
			return $brief;
		}

		return $brief . ' The brief can also give colours - the site\'s own brand, the inspiration\'s, or new ones - and fonts and tone, and the Steps are designed to them.';
	}

	public static function offer_customising( $offers, $funnel_id, $steps ) {
		$atomic  = array();
		$classic = array();

		foreach ( (array) $steps as $step ) {
			$post = isset( $step['step_id'] ) ? get_post( (int) $step['step_id'] ) : null;

			if ( ! $post instanceof WP_Post || ! isset( self::types()[ $post->post_type ] ) || 'builder' !== get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
				continue;
			}

			if ( self::classic_count( self::elements( $post->ID ) ) > 0 ) {
				$classic[] = (int) $post->ID;
			} else {
				$atomic[] = (int) $post->ID;
			}
		}

		$offers = (array) $offers;
		$first  = $atomic ? $atomic[0] : ( $classic ? $classic[0] : 0 );

		if ( $first && self::elementor_status( get_post( $first ) )['outdated'] ) {
			$offers[] = self::outdated_notice();
		} elseif ( $atomic && self::design_tools_on() ) {
			$offers[] = 'Would you like me to customise the template to your brand - colours, fonts, headlines, copy and images? Tell me what to change, or share screenshots of a site whose look you like and I will match it - a link alone shows me its text, not its look.';
		} elseif ( $classic ) {
			$offers[] = 'Would you like the template customised to your brand? Its pages came in as classic Elementor, which I cannot edit: turn on Elementor\'s Atomic Editor and I can re-import it and customise it, or you can edit it yourself in Elementor.';
		}

		return $offers;
	}

	/**
	 * Step post types the design tools work on. Each entry: type (step type
	 * slug), design_type (what the design controller calls it), catalog (its
	 * template catalogue key), label, widgets (widget type => label, the funnel
	 * widgets a working Step needs), keep (widget types a blank reset keeps,
	 * with their settings - the funnel widgets whose content is live data),
	 * brand (widget type => colour controls a brand colour is applied to), and
	 * optionally design (callable, step id => array( slug, builder )) and
	 * funnel (callable, step id => funnel id) where those are not read the
	 * Step's own way. Pro adds the Upsell Offer.
	 *
	 * @return array
	 */
	public static function types() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$types = array(
			'wffn_landing'   => array(
				'type'    => 'landing',
				'label'   => 'Sales Page',
				'widgets' => array(),
			),
			'wffn_optin'     => array(
				'type'    => 'optin',
				'label'   => 'Optin Page',
				'widgets' => array( 'wffn-optin-form' => 'Optin Form' ),
				'keep'    => array( 'wffn-optin-form', 'wffn-optin-popup' ),
				'brand'   => array( 'wffn-optin-form' => array( 'button_bg_color' ) ),
			),
			'wffn_oty'       => array(
				'type'    => 'optin_ty',
				'label'   => 'Optin Confirmation',
				'widgets' => array(),
			),
			'wfacp_checkout' => array(
				'type'    => 'wc_checkout',
				'label'   => 'Checkout',
				'widgets' => array( 'wfacp_form' => 'Checkout Form' ),
				// The order summary is its own widget in some templates: without it the
				// buyer sees no product, price or total.
				'keep'    => array( 'wfacp_form', 'wfacp_form_summary' ),
				'brand'   => array( 'wfacp_form' => array( 'default_primary_color' ) ),
			),
			'wffn_ty'        => array(
				'type'    => 'wc_thankyou',
				'label'   => 'Thank You Page',
				'widgets' => array(
					'wfty-order-detail'    => 'Order Details',
					'wfty-customer-detail' => 'Customer Details',
				),
			),
		);

		$types = (array) apply_filters( 'fkmcp_step_design_types', $types );

		foreach ( $types as $post_type => $entry ) {
			$entry = wp_parse_args(
				(array) $entry,
				array(
					'type'    => '',
					'label'   => '',
					'widgets' => array(),
					'brand'   => array(),
				)
			);

			$entry['keep']        = array_values( array_unique( array_merge( array_keys( (array) $entry['widgets'] ), (array) ( $entry['keep'] ?? array() ) ) ) );
			$entry['design_type'] = isset( $entry['design_type'] ) ? (string) $entry['design_type'] : $entry['type'];
			$entry['catalog']     = isset( $entry['catalog'] ) ? (string) $entry['catalog'] : $entry['type'];
			$types[ $post_type ]  = $entry;
		}

		self::$types = $types;

		return self::$types;
	}

	/**
	 * get-step-design handler.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function get_step_design( $input ) {
		$step = self::resolve_step( $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		return self::describe( $step );
	}

	/**
	 * prepare-step-design handler.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function prepare_step_design( $input ) {
		return FKMCP_Pack_Funnels::write( 'prepare-step-design', $input, array( $this, 'do_prepare_step_design' ) );
	}

	/**
	 * The write behind funnelkit/prepare-step-design, once its input is valid.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function do_prepare_step_design( $input ) {
		$input = (array) $input;
		$step  = self::resolve_step( $input );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$mode      = isset( $input['mode'] ) && 'blank' === $input['mode'] ? 'blank' : 'keep';
		$readiness = self::elementor_status( $step['post'] );

		if ( ! $readiness['design_tools'] || ! $readiness['atomic_editor'] ) {
			return new WP_Error( 'fkmcp_elementor_unavailable', $readiness['reason'] . ' Nothing was changed.' );
		}

		$brand = null;

		if ( isset( $input['brand_color'] ) && '' !== (string) $input['brand_color'] ) {
			self::funnel_widgets();
			$brand = FKMCP_Funnel_Widgets::brand_global( (string) $input['brand_color'] );

			if ( is_wp_error( $brand ) ) {
				return $brand;
			}
		}

		// Whether the user may edit the Step with Elementor, before anything changes.
		$document = \Elementor\Plugin::$instance->documents->get( $step['id'], false );

		if ( ! $document || ! $document->is_editable_by_current_user() ) {
			return new WP_Error( 'fkmcp_step_not_editable', sprintf( 'You cannot edit Step %d with Elementor. Nothing was changed.', $step['id'] ) );
		}

		$locked = self::lock_error( $step['id'] );

		if ( $locked ) {
			return $locked;
		}

		$design   = self::step_design( $step );
		$switched = false;
		// A blank reset keeps the funnel widgets the Step shows now, settings and all -
		// from its Elementor design only: data an earlier Elementor design left behind is stale.
		$kept_nodes = 'blank' === $mode && 'elementor' === $design['builder'] ? self::collect_widgets( self::elements( self::working_post_id( $step['id'] ) ), $step['entry']['keep'] ) : array();

		// A Step that is still empty has nothing to keep: it is switched as mode blank would.
		if ( 'elementor' !== $design['builder'] && 'blank' !== $mode && self::is_empty_step( $step, $design ) ) {
			$mode = 'blank';
		}

		if ( 'elementor' !== $design['builder'] ) {
			if ( 'blank' !== $mode ) {
				return new WP_Error( 'fkmcp_design_not_elementor', sprintf( 'Step %1$d shows a %2$s design, not an Elementor one. Call again with mode blank to replace it with a blank Elementor layout (its current content is lost - confirm with the user), or apply an Elementor template first (update-step-template; update-offer-template for an Upsell Offer). Nothing was changed.', $step['id'], '' !== $design['builder'] ? $design['builder'] : 'default' ) );
			}

			$result = self::switch_to_elementor( $step );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$switched = true;
		}

		$document->set_is_built_with_elementor( true );

		$canvas = self::ensure_canvas( $step );
		$target = self::working_post_id( $step['id'] );

		if ( 'blank' === $mode ) {
			self::discard_autosave( $step['id'] );
			$target   = $step['id'];
			$elements = empty( $kept_nodes ) ? array() : array( self::container( $step['entry'], $kept_nodes ) );
		} else {
			$elements = self::elements( $target );
		}

		$found   = self::find_widgets( $elements, array_keys( $step['entry']['widgets'] ) );
		$missing = array_values( array_diff( array_keys( $step['entry']['widgets'] ), array_keys( $found ) ) );

		if ( ! empty( $missing ) ) {
			$elements[] = self::widget_container( $step['entry'], $missing );
		}

		$applied = $brand ? FKMCP_Funnel_Widgets::apply_brand( $elements, (array) $step['entry']['brand'], $brand['global'] ) : array();

		if ( 'blank' === $mode || ! empty( $missing ) || ! empty( $applied ) ) {
			self::save_revision( $step['id'], self::BEFORE_AI );

			// Like the Elementor tools, a published Step's change waits in the user's draft;
			// a blank reset replaces the Step itself, as switching its design already did.
			$target = self::save_design( $step['id'], $elements, 'blank' === $mode );

			if ( is_wp_error( $target ) ) {
				return $target;
			}

			if ( $target === $step['id'] ) {
				self::save_revision( $step['id'], self::AI_RESULT );
			}
		}

		$result = array_merge(
			self::describe( $step ),
			array(
				'mode'     => $mode,
				'switched' => $switched,
				'canvas'   => $canvas,
				'added'    => $missing,
				'kept'     => array_values( wp_list_pluck( $kept_nodes, 'widgetType' ) ),
			)
		);

		if ( $brand ) {
			$result['brand'] = array(
				'variable' => $brand['variable'],
				'global'   => $brand['global'],
				'applied'  => $applied,
			);
		}

		return $result;
	}

	/**
	 * The Step (or Offer) an input names, with its design type entry.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error array( id, post, entry )
	 */
	public static function resolve_step( $input ) {
		$input   = (array) $input;
		$step_id = absint( isset( $input['step_id'] ) ? $input['step_id'] : 0 );
		$post    = $step_id ? get_post( $step_id ) : null;

		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			return new WP_Error( 'fkmcp_step_not_found', 'No step with that id.' );
		}

		$types = self::types();

		if ( 'wfocu_funnel' === $post->post_type ) {
			return new WP_Error( 'fkmcp_wrong_step_type', sprintf( 'An Upsell is designed on its Offers: list them with get-upsell-offers (step %d) and pass an offer id%s.', $step_id, isset( $types['wfocu_offer'] ) ? '' : ' - designing Offers needs Funnel Builder Pro' ) );
		}

		if ( ! isset( $types[ $post->post_type ] ) ) {
			return new WP_Error( 'fkmcp_wrong_step_type', sprintf( 'Post %d is not a Funnel Step that can be designed with Elementor: the design tools on this server work on Funnel Steps only - find Step ids with get-funnel.', $step_id ) );
		}

		return array(
			'id'    => (int) $post->ID,
			'post'  => $post,
			'entry' => $types[ $post->post_type ],
		);
	}

	/**
	 * Everything get-step-design reports about one Step.
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return array
	 */
	public static function describe( $step ) {
		$status   = self::elementor_status( $step['post'] );
		$design   = self::step_design( $step );
		$built    = $status['built_with_elementor'];
		$elements = $built ? self::elements( self::working_post_id( $step['id'] ) ) : array();
		$found    = self::find_widgets( $elements, array_keys( $step['entry']['widgets'] ) );
		$required = array();

		foreach ( $step['entry']['widgets'] as $widget_type => $label ) {
			$required[] = array(
				'widget_type' => (string) $widget_type,
				'label'       => (string) $label,
				'present'     => isset( $found[ $widget_type ] ),
				'element_id'  => isset( $found[ $widget_type ] ) ? $found[ $widget_type ] : '',
			);
		}

		if ( 'elementor' !== $design['builder'] ) {
			$status['ready']  = false;
			$status['reason'] = 'The Step shows a ' . ( '' !== $design['builder'] ? $design['builder'] : 'default' ) . ' design: call prepare-step-design - a Step that is still empty is switched to a blank Elementor layout; one with content needs mode blank (replaces its content) or an Elementor template first.';
		} elseif ( $status['ready'] && ! $built ) {
			$status['ready']  = false;
			$status['reason'] = 'The Step is not marked as built with Elementor yet: call prepare-step-design.';
		}

		$top = array();

		foreach ( $elements as $element ) {
			$top[] = array(
				'id'      => isset( $element['id'] ) ? (string) $element['id'] : '',
				'type'    => isset( $element['widgetType'] ) ? (string) $element['widgetType'] : ( isset( $element['elType'] ) ? (string) $element['elType'] : '' ),
				'version' => self::is_atomic( $element ) ? 4 : 3,
			);
		}

		// The step integrations answer escaped URLs, for HTML; an agent needs them raw.
		$links = array_map( 'wp_specialchars_decode', FKMCP_Pack_Funnels::step_links( $step['entry']['type'], $step['id'] ) );

		$links['elementor'] = add_query_arg(
			array(
				'post'   => $step['id'],
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);

		if ( empty( $links['view'] ) ) {
			$links['view'] = (string) get_permalink( $step['id'] );
		}

		$funnel_id = isset( $step['entry']['funnel'] ) && is_callable( $step['entry']['funnel'] ) ? (int) call_user_func( $step['entry']['funnel'], $step['id'] ) : (int) get_post_meta( $step['id'], '_bwf_in_funnel', true );
		$classic   = self::classic_count( $elements );

		$result = array(
			'step_id'          => $step['id'],
			'post_id'          => $step['id'],
			'type'             => (string) $step['entry']['type'],
			'funnel_id'        => $funnel_id,
			'title'            => (string) get_the_title( $step['id'] ),
			'status'           => (string) get_post_status( $step['id'] ),
			'design'           => $design,
			'page_template'    => self::page_template( $step['id'] ),
			'elementor'        => $status,
			'required_widgets' => $required,
			'elements'         => $top,
			'unpublished'      => self::working_post_id( $step['id'] ) !== $step['id'],
			'restore_points'   => self::restore_points( $step['id'] ),
			'next_step_link'   => add_query_arg( 'wffn-next-link', 'yes', home_url( '/' ) ),
			'links'            => $links,
			'workflow'         => self::workflow(),
		);

		$editor = self::locked_by( $step['id'] );

		if ( $editor ) {
			$result['locked_by'] = $editor->display_name;
		}

		if ( $status['outdated'] ) {
			$result['ask_user'] = array( self::outdated_notice() );
		} elseif ( $status['atomic_editor'] && $classic > 0 ) {
			$result['next'] = 'This Step shows classic Elementor content, which the Elementor tools cannot edit. Re-import its template (update-step-template; update-offer-template for an Upsell Offer) to get the atomic version the template library serves while the Atomic Editor is on - confirm with the user first, as it replaces the Step\'s content. Do not rebuild the template from scratch.';
		}

		return $result;
	}

	/**
	 * Whether Elementor's design abilities can work on a post.
	 *
	 * @param WP_Post $post Step post.
	 *
	 * @return array
	 */
	public static function elementor_status( $post ) {
		$active = defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance );
		$tools  = self::has_ability( 'elementor/build-composition' );
		$atomic = false;
		$exists = false;
		$built  = false;

		if ( $active ) {
			$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
			// One answer for the template importer and the design tools alike.
			$atomic = class_exists( 'WFFN_Common' ) && method_exists( 'WFFN_Common', 'is_elementor_atomic_active' )
				? WFFN_Common::is_elementor_atomic_active()
				: is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && $experiments->is_feature_active( 'e_atomic_elements' );
			$exists = $atomic || ( is_object( $experiments ) && method_exists( $experiments, 'get_features' ) && null !== $experiments->get_features( 'e_atomic_elements' ) );
			$built  = 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true );
		}

		// An Elementor without the design abilities or the Atomic Editor is too old
		// for designing Steps here: the user is told, rather than offered it.
		$outdated = $active && ( ! $tools || ! $exists );
		$reason   = '';

		if ( ! $active ) {
			$reason = 'Elementor is not active on this site.';
		} elseif ( $outdated ) {
			$reason = self::outdated_notice() . ' Tell the user this; do not offer to customise the design.';
		} elseif ( ! $atomic ) {
			$reason = 'Elementor\'s Atomic Editor feature is off, and the design tools only work with it: ask the user to turn it on in Elementor > Settings > Features.';
		} elseif ( ! post_type_supports( $post->post_type, 'elementor' ) ) {
			$reason = 'Elementor is not enabled for this step type.';
		}

		return array(
			'active'               => $active,
			'version'              => $active ? (string) ELEMENTOR_VERSION : '',
			'design_tools'         => $tools,
			'atomic_editor'        => $atomic,
			'outdated'             => $outdated,
			'built_with_elementor' => $built,
			'ready'                => '' === $reason,
			'reason'               => $reason,
		);
	}

	/**
	 * What the user is told when their Elementor is too old for designing Steps here.
	 *
	 * @return string
	 */
	public static function outdated_notice() {
		return sprintf( 'This site\'s Elementor (%s) is too old for customising the funnel\'s design here: that needs a recent Elementor 4, with its design tools and Atomic Editor. Update Elementor to customise the template this way; until then the pages can be edited in the Elementor editor.', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
	}

	/**
	 * The FunnelKit design a Step shows (slug + builder key).
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return array
	 */
	public static function step_design( $step ) {
		if ( isset( $step['entry']['design'] ) && is_callable( $step['entry']['design'] ) ) {
			$design = (array) call_user_func( $step['entry']['design'], $step['id'] );
		} else {
			$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );
			$design  = $modules ? FKMCP_Pack_Funnels::step_design( $modules, $step['id'], $step['entry']['design_type'] ) : array();
		}

		return array(
			'slug'    => isset( $design['slug'] ) ? (string) $design['slug'] : '',
			'builder' => isset( $design['builder'] ) ? (string) $design['builder'] : '',
		);
	}

	/** Meta where page builders keep a page's layout: Bricks, Oxygen (classic and 4), Breakdance and Oxygen 6, Beaver Builder. */
	const BUILDER_LAYOUT_META = array( '_bricks_page_content_2', 'ct_builder_shortcodes', 'ct_builder_json', '_breakdance_data', '_fl_builder_data' );

	/**
	 * Whether a Step is still empty, so switching it to Elementor loses nothing:
	 * no FunnelKit template applied yet (the admin still offers its template
	 * picker), no page content, no Elementor data and no layout another builder
	 * keeps in meta - as each Step of a Funnel made from scratch starts.
	 *
	 * @param array $step   resolve_step() result.
	 * @param array $design step_design() result.
	 *
	 * @return bool
	 */
	private static function is_empty_step( $step, $design ) {
		if ( '' !== trim( (string) $step['post']->post_content ) || ! empty( self::elements( $step['id'] ) ) ) {
			return false;
		}

		// Builders that keep their layout in meta, not in the content.
		foreach ( self::BUILDER_LAYOUT_META as $key ) {
			if ( ! empty( get_post_meta( $step['id'], $key, true ) ) ) {
				return false;
			}
		}

		// A design read its own way (an Upsell Offer's) has no template until one is chosen.
		if ( isset( $step['entry']['design'] ) && is_callable( $step['entry']['design'] ) ) {
			return '' === $design['slug'];
		}

		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );

		if ( ! $modules ) {
			return false;
		}

		$data = FKMCP_Pack_Funnels::response_data(
			$modules->get_step(
				FKMCP_Pack_Funnels::rest_request(
					array(
						'step_id' => $step['id'],
						'type'    => $step['entry']['design_type'],
					)
				)
			)
		);

		return isset( $data['data']['step']['design']['template_active'] ) && 'no' === $data['data']['step']['design']['template_active'];
	}

	/**
	 * Apply the Step's blank Elementor layout, as the admin's Build from Scratch does.
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return true|WP_Error
	 */
	private static function switch_to_elementor( $step ) {
		$modules = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnel-modules.php', 'WFFN_REST_Funnel_Modules' );
		$funnels = FKMCP_Pack_Funnels::controller( 'class-wffn-rest-funnels.php', 'WFFN_REST_Funnels' );

		if ( ! $modules || ! $funnels ) {
			return new WP_Error( 'fkmcp_modules_unavailable', 'The step design provider is not loaded on this request.' );
		}

		$catalog   = FKMCP_Pack_Funnels::response_data( $funnels->get_templates() );
		$templates = isset( $catalog['templates'][ $step['entry']['catalog'] ]['elementor'] ) ? (array) $catalog['templates'][ $step['entry']['catalog'] ]['elementor'] : array();
		$slug      = '';

		foreach ( $templates as $key => $template ) {
			if ( ! empty( $template['build_from_scratch'] ) && wffn_string_to_bool( $template['build_from_scratch'] ) ) {
				$slug = isset( $template['slug'] ) && '' !== $template['slug'] ? (string) $template['slug'] : (string) $key;
				break;
			}
		}

		if ( '' === $slug ) {
			return new WP_Error( 'fkmcp_template_unavailable', sprintf( 'No blank Elementor layout is listed for %s Steps; the FunnelKit template catalogue may not have loaded - try again later. Nothing was changed.', $step['entry']['label'] ) );
		}

		$request  = FKMCP_Pack_Funnels::rest_request(
			array(
				'step_id' => $step['id'],
				'type'    => $step['entry']['design_type'],
				'design'  => wp_json_encode(
					array(
						'builder'  => 'elementor',
						'template' => $slug,
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
			return FKMCP_Pack_Funnels::design_error( $response, 'elementor' );
		}

		return true;
	}

	/** The funnel widget tools' work lives in FKMCP_Funnel_Widgets, loaded when one runs. */
	private static function funnel_widgets() {
		require_once __DIR__ . '/class-fkmcp-funnel-widgets.php';
	}

	/**
	 * The post the Elementor tools read and write for the current user: their
	 * pending autosave of a published Step, else the Step itself.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return int
	 */
	public static function working_post_id( $step_id ) {
		$user_id  = get_current_user_id();
		$autosave = $user_id ? wp_get_post_autosave( $step_id, $user_id ) : false;

		return $autosave instanceof WP_Post ? (int) $autosave->ID : (int) $step_id;
	}

	/**
	 * The page template a Step renders with. '' and default are the theme's:
	 * its header, footer and sidebar around the design.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return string
	 */
	public static function page_template( $step_id ) {
		return (string) get_post_meta( $step_id, '_wp_page_template', true );
	}

	/**
	 * Puts a Step's page template back after a design save, on the Step and on
	 * the user's draft of it. Elementor saves a change to a published Step into
	 * an autosave without the page settings, so the autosave's template becomes
	 * default - and WordPress writes a revision's meta to its parent: the live
	 * Step would render inside the theme until the draft is published.
	 *
	 * @param int    $step_id  Step id.
	 * @param string $template The template it had before the save.
	 *
	 * @return void
	 */
	private static function keep_page_template( $step_id, $template ) {
		if ( '' === $template ) {
			return;
		}

		if ( self::page_template( $step_id ) !== $template ) {
			update_post_meta( $step_id, '_wp_page_template', $template );
		}

		$draft = self::working_post_id( $step_id );

		// update_metadata(): update_post_meta() would write the revision's meta to the Step.
		if ( $draft !== (int) $step_id && (string) get_metadata( 'post', $draft, '_wp_page_template', true ) !== $template ) {
			update_metadata( 'post', $draft, '_wp_page_template', $template );
		}
	}

	/**
	 * The FunnelKit Canvas template a Step's type registers (wflp-canvas.php,
	 * wfacp-canvas.php, wfocu-canvas.php ...): the page builder's design alone,
	 * without the theme's header, footer and sidebar.
	 *
	 * @param WP_Post $post The Step.
	 *
	 * @return string '' when its type registers none.
	 */
	private static function canvas_template( $post ) {
		foreach ( array_keys( (array) wp_get_theme()->get_page_templates( $post ) ) as $file ) {
			if ( preg_match( '/^wf[a-z]+-canvas\.php$/', (string) $file ) ) {
				return (string) $file;
			}
		}

		return '';
	}

	/**
	 * The FunnelKit Canvas template a Step on the theme's page template should
	 * get; '' when it has a template chosen, or its type registers no Canvas.
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return string
	 */
	public static function missing_canvas( $step ) {
		if ( ! in_array( self::page_template( $step['id'] ), array( '', 'default' ), true ) ) {
			return '';
		}

		return self::canvas_template( isset( $step['post'] ) ? $step['post'] : get_post( $step['id'] ) );
	}

	/**
	 * Gives a Step on the theme's page template FunnelKit Canvas, as importing
	 * a template does; a template chosen for it is kept.
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return bool Whether the template changed.
	 */
	private static function ensure_canvas( $step ) {
		$canvas = self::missing_canvas( $step );

		if ( '' === $canvas ) {
			return false;
		}

		self::keep_page_template( $step['id'], $canvas );

		return true;
	}

	private static function discard_autosave( $step_id ) {
		$user_id  = get_current_user_id();
		$autosave = $user_id ? wp_get_post_autosave( $step_id, $user_id ) : false;

		if ( $autosave instanceof WP_Post ) {
			wp_delete_post_revision( $autosave->ID );
		}
	}

	/**
	 * Elementor element tree stored on a post.
	 *
	 * @param int $post_id Step or autosave id.
	 *
	 * @return array
	 */
	public static function elements( $post_id ) {
		$data = get_post_meta( $post_id, '_elementor_data', true );

		if ( is_string( $data ) && '' !== $data ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) ? $data : array();
	}

	/** Revision meta naming a restore point the design tools saved. */
	const RESTORE_META = '_fkmcp_restore_point';

	/** Restore point: the design before an agent's changes. */
	const BEFORE_AI = 'before_ai';

	/** Restore point: the design an agent's changes left, once live. */
	const AI_RESULT = 'ai_result';

	/** How long one round of an agent's changes lasts: within it, only the first change tags "Before AI changes". */
	const BEFORE_AI_TTL = 2 * HOUR_IN_SECONDS;

	/**
	 * Saves a Step's element tree through Elementor's own document save, as its
	 * design tools do (Document_Mutator::save_as_draft): a published Step's change
	 * waits in the user's autosave, a draft is saved itself, and Elementor's
	 * sanitising, caches, hooks and revisions apply. $live saves the Step itself
	 * whatever its status - a blank reset replaces the design outright.
	 *
	 * Elementor rebuilds the tree through its element classes and drops an element
	 * whose class is not registered on the request. A funnel widget must never be
	 * lost that way: when one is missing after the save, the tree is written as
	 * given, straight to the meta.
	 *
	 * @param int   $step_id  Step id.
	 * @param array $elements Element tree.
	 * @param bool  $live     Save the Step itself, not its draft.
	 *
	 * @return int|WP_Error The post written: the Step, or the user's autosave of it.
	 */
	public static function save_design( $step_id, array $elements, $live = false ) {
		$elements = array_values( $elements );
		$template = self::page_template( $step_id );
		$document = \Elementor\Plugin::$instance->documents->get( $step_id, false );

		if ( ! $document ) {
			return new WP_Error( 'fkmcp_step_not_editable', sprintf( 'Step %d has no Elementor document. Nothing was changed.', $step_id ) );
		}

		if ( ! $document->is_built_with_elementor() ) {
			$document->set_is_built_with_elementor( true );
		}

		// Elementor renders the page while it saves, for its plain text. The checkout
		// form renders only for a shopper - it needs their session and cart - so it
		// sits that out (its own switch); and a widget that prints, or closes a buffer
		// it did not open, must not put page HTML into the tool's answer.
		$level = ob_get_level();
		add_filter( 'wfacp_print_elementor_widget', '__return_false', 99 );
		ob_start();

		try {
			$saved = self::save_document( $step_id, $document, $elements, $live );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_filter( 'wfacp_print_elementor_widget', '__return_false', 99 );
			// Even when the save fails part-way: Elementor may already have reset it.
			self::keep_page_template( $step_id, $template );
		}

		return $saved;
	}

	/**
	 * The save behind save_design(), its page template kept by the caller.
	 *
	 * @param int    $step_id  Step id.
	 * @param object $document Its Elementor document.
	 * @param array  $elements Element tree.
	 * @param bool   $live     Save the Step itself, not its draft.
	 *
	 * @return int|WP_Error
	 */
	private static function save_document( $step_id, $document, array $elements, $live ) {
		$mutator = '\Elementor\Core\Utils\Document\Document_Mutator';

		if ( ! class_exists( $mutator ) ) {
			$target = $live ? (int) $step_id : self::draft_post_id( $step_id, $document );
			self::save_elements( $target, $elements );

			return $target;
		}

		if ( $live ) {
			$saved = $document->save( array( 'elements' => $elements ) ) ? $document : new WP_Error( 'fkmcp_save_failed', sprintf( 'Elementor could not save Step %d. Nothing was changed.', $step_id ) );
		} else {
			$saved = $mutator::instance()->save_as_draft( $document, $elements, true );
		}

		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'fkmcp_save_failed', sprintf( 'Elementor could not save Step %1$d: %2$s Nothing was changed.', $step_id, $saved->get_error_message() ) );
		}

		$target = (int) $saved->get_post()->ID;
		$given  = self::classic_ids( $elements );
		$kept   = self::classic_ids( self::elements( $target ) );

		$lost = array_diff( $given, $kept );

		if ( $lost ) {
			self::save_elements( $target, $elements );

			if ( class_exists( 'WFFN_MCP_Log' ) ) {
				WFFN_MCP_Log::event( sprintf( 'DESIGN_SAVE_KEPT_WIDGETS post=%d lost=%s', $target, implode( ',', $lost ) ) );
			}
		}

		return $target;
	}

	/**
	 * Where a change to a published Step waits without Elementor's document
	 * save: the user's autosave (created when there is none), else the Step.
	 *
	 * @param int    $step_id  Step id.
	 * @param object $document Its Elementor document.
	 *
	 * @return int
	 */
	private static function draft_post_id( $step_id, $document ) {
		$target = self::working_post_id( $step_id );

		if ( $target === (int) $step_id && 'publish' === get_post_status( $step_id ) ) {
			$autosave = $document->get_autosave( get_current_user_id(), true );
			$target   = $autosave ? (int) $autosave->get_post()->ID : $target;
		}

		return $target;
	}

	/**
	 * Ids of the classic widgets in a tree - what a save must keep.
	 *
	 * @param array $elements Element tree.
	 *
	 * @return string[]
	 */
	private static function classic_ids( array $elements ) {
		$ids  = array();
		$walk = static function ( $nodes ) use ( &$walk, &$ids ) {
			foreach ( (array) $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( 'widget' === ( $node['elType'] ?? '' ) && 0 !== strpos( (string) ( $node['widgetType'] ?? '' ), 'e-' ) ) {
					$ids[] = (string) ( $node['id'] ?? '' );
				}
				if ( ! empty( $node['elements'] ) ) {
					$walk( $node['elements'] );
				}
			}
		};
		$walk( $elements );

		return $ids;
	}

	/**
	 * The Step's newest restore points: revisions tagged before or after an
	 * agent's changes, as the History panel lists them, newest first. The
	 * newest "Before AI changes" point is always among them, however many
	 * revisions were saved since.
	 *
	 * @param int $step_id Step id.
	 * @param int $limit   How many.
	 *
	 * @return array[]
	 */
	public static function restore_points( $step_id, $limit = 4 ) {
		$limit     = max( 1, (int) $limit );
		$revisions = self::tagged_revisions( $step_id, array( self::BEFORE_AI, self::AI_RESULT ), $limit );
		$before    = self::tagged_revisions( $step_id, array( self::BEFORE_AI ), 1 );

		if ( $before && ! in_array( (int) $before[0]->ID, array_map( 'intval', wp_list_pluck( $revisions, 'ID' ) ), true ) ) {
			// Older than every point listed: it takes the last place.
			$revisions   = array_slice( $revisions, 0, $limit - 1 );
			$revisions[] = $before[0];
		}

		$points = array();

		foreach ( $revisions as $revision ) {
			$kind     = (string) get_metadata( 'post', $revision->ID, self::RESTORE_META, true );
			$author   = get_userdata( (int) $revision->post_author );
			$points[] = array(
				'revision_id' => (int) $revision->ID,
				'kind'        => $kind,
				'label'       => self::BEFORE_AI === $kind ? 'Before AI changes' : 'AI result',
				'saved'       => (string) get_post_time( 'c', true, $revision ),
				'by'          => $author ? (string) $author->display_name : '',
			);
		}

		return $points;
	}

	/**
	 * The Step's newest revisions tagged as one of the restore points given,
	 * newest first, found by their tag rather than among the latest revisions.
	 *
	 * @param int      $step_id Step id.
	 * @param string[] $kinds   Restore point kinds.
	 * @param int      $limit   How many.
	 *
	 * @return WP_Post[]
	 */
	private static function tagged_revisions( $step_id, array $kinds, $limit ) {
		$revisions = get_posts(
			array(
				'post_type'      => 'revision',
				'post_status'    => 'inherit',
				'post_parent'    => (int) $step_id,
				'posts_per_page' => (int) $limit,
				'orderby'        => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a Step's few revisions, by their parent.
				'meta_query'     => array(
					array(
						'key'     => self::RESTORE_META,
						'value'   => $kinds,
						'compare' => 'IN',
					),
				),
				'no_found_rows'  => true,
			)
		);

		return array_values(
			array_filter(
				(array) $revisions,
				static function ( $revision ) {
					return $revision instanceof WP_Post && ! wp_is_post_autosave( $revision );
				}
			)
		);
	}

	/**
	 * Who else has the Step open in the Elementor editor, as Elementor reads its
	 * edit lock.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return WP_User|null
	 */
	public static function locked_by( $step_id ) {
		$editor = isset( \Elementor\Plugin::$instance->editor ) ? \Elementor\Plugin::$instance->editor : null;

		if ( $editor && method_exists( $editor, 'get_locked_user' ) ) {
			$user = $editor->get_locked_user( $step_id );
		} else {
			if ( ! function_exists( 'wp_check_post_lock' ) ) {
				require_once ABSPATH . 'wp-admin/includes/post.php';
			}

			$user_id = wp_check_post_lock( $step_id );
			$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
		}

		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * The refusal for a change to a Step someone else has open in the Elementor
	 * editor: the write would overwrite their work, or theirs would overwrite it.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return WP_Error|null
	 */
	public static function lock_error( $step_id ) {
		$user = self::locked_by( $step_id );

		if ( ! $user ) {
			return null;
		}

		return new WP_Error( 'fkmcp_step_locked', sprintf( '%1$s has Step %2$d open in the Elementor editor, so changing it now would overwrite their work or be overwritten by it. Tell the user; ask them to save and close the editor (its lock clears within a few minutes), then try again. Nothing was changed.', $user->display_name, $step_id ) );
	}

	/**
	 * Writes an element tree the way FunnelKit's Elementor importer does, and
	 * drops the post's generated CSS and render caches so Elementor rebuilds them.
	 * The metadata functions, not the post_meta ones: those move a write aimed
	 * at an autosave onto its parent Step.
	 *
	 * @param int   $post_id  Step or autosave id.
	 * @param array $elements Element tree.
	 */
	public static function save_elements( $post_id, $elements ) {
		update_metadata( 'post', $post_id, '_elementor_edit_mode', 'builder' );
		update_metadata( 'post', $post_id, '_elementor_version', ELEMENTOR_VERSION );
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( array_values( $elements ) ) ) );

		foreach ( array( '_elementor_css', '_elementor_element_cache', '_elementor_page_assets' ) as $key ) {
			delete_metadata( 'post', $post_id, $key );
		}
	}

	/**
	 * Saves the Step's current design as a revision, so it shows in Elementor's
	 * History and can be restored. Elementor's MCP tools write a published Step
	 * through an autosave that publish-document copies back without saving the
	 * Step, and the writes above go straight to its meta: neither makes one.
	 * Nothing is saved when the latest revision already holds this design; that
	 * revision is then the one tagged.
	 *
	 * "Before AI changes" is tagged once per round of changes: a draft Step's
	 * own design changes with every write, and tagging each one would push the
	 * real original out of the restore points. "AI result" is likewise one
	 * point per round: each later write moves the tag to its own revision, so
	 * it always holds the round's latest design. The round ends when the design
	 * is published (publish-document) or after self::BEFORE_AI_TTL. A round
	 * whose "Before AI changes" revision is gone (WordPress pruned it) tags a
	 * new one.
	 *
	 * @param int    $step_id Step id.
	 * @param string $kind    Restore point it is: self::BEFORE_AI or self::AI_RESULT ('' for none).
	 *
	 * @return int The revision holding the design; 0 when revisions are off, or
	 *             when this round's "Before AI changes" is already tagged.
	 */
	public static function save_revision( $step_id, $kind = '' ) {
		$post = get_post( $step_id );

		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || ! wp_revisions_enabled( $post ) ) {
			return 0;
		}

		if ( self::BEFORE_AI === $kind ) {
			if ( self::round_point( self::before_ai_key( $post->ID ), self::BEFORE_AI ) ) {
				return 0;
			}

			delete_transient( self::before_ai_key( $post->ID ) );
		}

		$data        = (string) get_metadata( 'post', $post->ID, '_elementor_data', true );
		$revision_id = 0;

		foreach ( wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 5 ) ) as $revision ) {
			if ( wp_is_post_autosave( $revision ) ) {
				continue;
			}

			$tag = (string) get_metadata( 'post', $revision->ID, self::RESTORE_META, true );

			// "Before AI changes" gets a revision of its own, never the last round's "AI result".
			if ( (string) get_metadata( 'post', $revision->ID, '_elementor_data', true ) === $data && ( self::BEFORE_AI !== $kind || '' === $tag || self::BEFORE_AI === $tag ) ) {
				$revision_id = (int) $revision->ID;
			}

			break;
		}

		if ( ! $revision_id ) {
			// The post fields have not changed, only the design: save it anyway.
			$always = '__return_false';
			add_filter( 'wp_save_post_revision_check_for_changes', $always, 99 );
			$revision_id = (int) wp_save_post_revision( $post->ID );
			remove_filter( 'wp_save_post_revision_check_for_changes', $always, 99 );
		}

		$tagged = false;

		// The revision holding this design is the restore point, unless it is one already.
		if ( $revision_id && '' !== $kind && '' === (string) get_metadata( 'post', $revision_id, self::RESTORE_META, true ) ) {
			update_metadata( 'post', $revision_id, self::RESTORE_META, $kind );
			$tagged = true;
		}

		if ( $revision_id && self::BEFORE_AI === $kind ) {
			set_transient( self::before_ai_key( $post->ID ), $revision_id, self::BEFORE_AI_TTL );
			// A new round: its first "AI result" is a point of its own.
			delete_transient( self::ai_result_key( $post->ID ) );
		}

		if ( $tagged && self::AI_RESULT === $kind ) {
			// This round's earlier "AI result" gives its tag to the newer design.
			$previous = self::round_point( self::ai_result_key( $post->ID ), self::AI_RESULT );

			if ( $previous && $previous !== $revision_id ) {
				delete_metadata( 'post', $previous, self::RESTORE_META );
			}

			set_transient( self::ai_result_key( $post->ID ), $revision_id, self::BEFORE_AI_TTL );
		}

		return $revision_id;
	}

	/**
	 * The revision this round tagged as a restore point, while it still exists
	 * and still carries that tag.
	 *
	 * @param string $key  Transient naming the round's revision.
	 * @param string $kind Restore point kind.
	 *
	 * @return int Revision id; 0 for none.
	 */
	private static function round_point( $key, $kind ) {
		$revision_id = (int) get_transient( $key );

		if ( $revision_id && wp_is_post_revision( $revision_id ) && (string) get_metadata( 'post', $revision_id, self::RESTORE_META, true ) === $kind ) {
			return $revision_id;
		}

		return 0;
	}

	/**
	 * The marker naming the revision this round of the current user's changes
	 * to a Step tagged "AI result".
	 *
	 * @param int $step_id Step id.
	 *
	 * @return string Transient name.
	 */
	private static function ai_result_key( $step_id ) {
		return 'fkmcp_ai_result_' . (int) $step_id . '_' . get_current_user_id();
	}

	/**
	 * The marker that this round of the current user's changes to a Step has
	 * its "Before AI changes" point.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return string Transient name.
	 */
	private static function before_ai_key( $step_id ) {
		return 'fkmcp_before_ai_' . (int) $step_id . '_' . get_current_user_id();
	}

	/**
	 * The first funnel widget at or under an element: what deleting the element
	 * (or replacing its children) would remove with it.
	 *
	 * @param array  $tree         Element tree.
	 * @param string $element_id   Element id.
	 * @param array  $widget_types Funnel widget types.
	 * @param bool   $self_too     Whether the element itself counts (a delete) or only its children.
	 *
	 * @return array|null The widget node.
	 */
	private static function funnel_widgets_under( $tree, $element_id, $widget_types, $self_too ) {
		$start = null;
		$find  = static function ( $nodes ) use ( &$find, &$start, $element_id ) {
			foreach ( (array) $nodes as $node ) {
				if ( null !== $start ) {
					return;
				}
				if ( is_array( $node ) && (string) ( $node['id'] ?? '' ) === $element_id ) {
					$start = $node;
					return;
				}
				$find( is_array( $node ) ? ( $node['elements'] ?? array() ) : array() );
			}
		};
		$find( $tree );

		if ( null === $start ) {
			return null;
		}

		$hit  = null;
		$walk = static function ( $nodes ) use ( &$walk, &$hit, $widget_types ) {
			foreach ( (array) $nodes as $node ) {
				if ( null !== $hit || ! is_array( $node ) ) {
					continue;
				}
				if ( in_array( (string) ( $node['widgetType'] ?? '' ), (array) $widget_types, true ) ) {
					$hit = $node;
					return;
				}
				$walk( $node['elements'] ?? array() );
			}
		};
		$walk( $self_too ? array( $start ) : ( $start['elements'] ?? array() ) );

		return $hit;
	}

	/**
	 * Element ids of the first instance of each widget type, anywhere in the tree.
	 *
	 * @param array $elements     Element tree.
	 * @param array $widget_types Widget types to look for.
	 *
	 * @return array widget type => element id
	 */
	private static function find_widgets( $elements, $widget_types ) {
		$found = array();

		if ( empty( $widget_types ) ) {
			return $found;
		}

		$walk = static function ( $nodes ) use ( &$walk, &$found, $widget_types ) {
			foreach ( (array) $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}

				$type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';

				if ( '' !== $type && in_array( $type, $widget_types, true ) && ! isset( $found[ $type ] ) ) {
					$found[ $type ] = isset( $node['id'] ) ? (string) $node['id'] : '';
				}

				if ( ! empty( $node['elements'] ) ) {
					$walk( $node['elements'] );
				}
			}
		};

		$walk( $elements );

		return $found;
	}

	/**
	 * Classic top-level elements: a template imported before the Atomic Editor
	 * was on, or one the template library has no atomic version of. The classic
	 * container this pack put funnel widgets in before it used an atomic one
	 * (titled "<Step type> - funnel widgets") is not the template's content.
	 *
	 * @param array $elements Element tree.
	 *
	 * @return int
	 */
	private static function classic_count( $elements ) {
		$classic = 0;

		foreach ( (array) $elements as $element ) {
			$title = isset( $element['settings']['_title'] ) ? (string) $element['settings']['_title'] : '';

			if ( is_array( $element ) && ! self::is_atomic( $element ) && ' - funnel widgets' !== substr( $title, -17 ) ) {
				++$classic;
			}
		}

		return $classic;
	}

	/** Whether an element is an atomic (V4) one - the only kind Elementor's tools edit. */
	private static function is_atomic( $element ) {
		$type = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : ( isset( $element['elType'] ) ? (string) $element['elType'] : '' );

		return 0 === strpos( $type, 'e-' );
	}

	/**
	 * A classic container holding the funnel widgets a Step lacks, with their defaults.
	 *
	 * @param array $entry   Step design type.
	 * @param array $widgets Widget types to add.
	 *
	 * @return array
	 */
	private static function widget_container( $entry, $widgets ) {
		$children = array();

		foreach ( $widgets as $widget_type ) {
			$children[] = array(
				'id'         => self::element_id(),
				'elType'     => 'widget',
				'widgetType' => $widget_type,
				'settings'   => array(),
				'elements'   => array(),
			);
		}

		return self::container( $entry, $children );
	}

	/**
	 * An atomic flexbox around funnel widget nodes: a column, full width, 20px
	 * between widgets. Atomic, so Elementor's tools can move it - and the funnel
	 * widgets with it - into the layout they build, and restyle it.
	 *
	 * @param array $entry    Step design type.
	 * @param array $children Widget nodes.
	 *
	 * @return array
	 */
	private static function container( $entry, $children ) {
		$id    = self::element_id();
		$style = 'e-' . $id . '-' . substr( md5( $id ), 0, 4 );
		$size  = static function ( $value, $unit ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => $value,
					'unit' => $unit,
				),
			);
		};

		return array(
			'id'              => $id,
			'elType'          => 'e-flexbox',
			'isInner'         => false,
			'settings'        => array(
				'classes' => array(
					'$$type' => 'classes',
					'value'  => array( $style ),
				),
			),
			'styles'          => array(
				$style => array(
					'id'       => $style,
					'label'    => 'local',
					'type'     => 'class',
					'variants' => array(
						array(
							'meta'       => array(
								'breakpoint' => 'desktop',
								'state'      => null,
							),
							'props'      => array(
								'flex-direction' => array(
									'$$type' => 'string',
									'value'  => 'column',
								),
								'width'          => $size( 100, '%' ),
								'gap'            => $size( 20, 'px' ),
							),
							'custom_css' => null,
						),
					),
				),
			),
			'editor_settings' => array( 'title' => $entry['label'] . ' - funnel widgets' ),
			'version'         => '0.0',
			'elements'        => array_values( $children ),
		);
	}

	/**
	 * Every widget node of the given types, in page order, wherever it sits.
	 *
	 * @param array $elements     Element tree.
	 * @param array $widget_types Widget types.
	 *
	 * @return array Widget nodes, settings included.
	 */
	private static function collect_widgets( $elements, $widget_types ) {
		$nodes = array();

		$walk = static function ( $tree ) use ( &$walk, &$nodes, $widget_types ) {
			foreach ( (array) $tree as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}

				if ( isset( $node['widgetType'] ) && in_array( (string) $node['widgetType'], $widget_types, true ) ) {
					$nodes[] = $node;
					continue;
				}

				if ( ! empty( $node['elements'] ) ) {
					$walk( $node['elements'] );
				}
			}
		};

		$walk( $elements );

		return $nodes;
	}

	private static function element_id() {
		return class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::generate_random_string() : substr( md5( uniqid( '', true ) ), 0, 7 );
	}

	/* ------------------------------------------------- design tools */

	/**
	 * What describe answers: a tool's instructions and schemas - a FunnelKit
	 * tool's from its definition, an Elementor tool's from the installed
	 * Elementor, so they always match what the tool checks its input against.
	 *
	 * @param string $name   Tool name.
	 * @param bool   $writes Whether it writes.
	 *
	 * @return array
	 */
	private static function describe_tool( $name, $writes ) {
		$spec = self::tool_spec( $name );

		return array(
			'tool'          => $name,
			'label'         => $spec['label'],
			'description'   => $spec['description'],
			'input_schema'  => (object) $spec['input_schema'],
			'result_schema' => (object) $spec['result_schema'],
			'next'          => sprintf( 'Run it with %1$s: tool %2$s, input as input_schema says.', $writes ? 'edit-design' : 'read-design', $name ),
		);
	}

	/**
	 * A design tool's label, description and schemas: a FunnelKit tool's from
	 * its definition, an Elementor tool's from Elementor, as installed.
	 *
	 * @param string $name Tool name.
	 *
	 * @return array label, description, input_schema, result_schema
	 */
	private static function tool_spec( $name ) {
		$own = self::funnelkit_tools();

		if ( isset( $own[ $name ] ) ) {
			return $own[ $name ];
		}

		$ability = wp_get_ability( $name );

		return array(
			'label'         => $ability->get_label(),
			'description'   => $ability->get_description(),
			'input_schema'  => $ability->get_input_schema(),
			'result_schema' => $ability->get_output_schema(),
		);
	}

	/**
	 * The read-design handler: runs one of the design tools that only reads.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function read_design( $input ) {
		return self::run_design_tool( (array) $input, false );
	}

	/**
	 * The edit-design handler: runs one of the design tools that writes.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function edit_design( $input ) {
		// Describing a tool changes nothing: no write to audit.
		if ( ! empty( $input['describe'] ) ) {
			return self::run_design_tool( (array) $input, true );
		}

		return FKMCP_Pack_Funnels::write( 'edit-design', $input, array( $this, 'do_edit_design' ) );
	}

	/**
	 * The write behind funnelkit/edit-design.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function do_edit_design( $input ) {
		return self::run_design_tool( (array) $input, true );
	}

	/**
	 * Runs a design tool for read-design or edit-design. A FunnelKit tool
	 * checks its input against its schema and does its own Step checks. An
	 * Elementor tool works on Funnel Steps only; a write needs a Step on an
	 * Elementor design and must not take the funnel widgets with it, and the
	 * Step's History keeps what it had.
	 *
	 * @param array $input  Ability input: tool, input.
	 * @param bool  $writes Whether the caller runs the tools that write.
	 *
	 * @return array|WP_Error
	 */
	private static function run_design_tool( array $input, $writes ) {
		$available = self::available_design_tools();
		$name      = self::tool_name( isset( $input['tool'] ) ? $input['tool'] : '' );

		if ( ! isset( $available[ $name ] ) ) {
			return self::unknown_tool( isset( $input['tool'] ) ? $input['tool'] : '' );
		}

		if ( $available[ $name ]['writes'] !== $writes ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%1$s %2$s: run it with %3$s. Nothing was changed.', $name, $writes ? 'only reads' : 'writes', $writes ? 'read-design' : 'edit-design' ) );
		}

		if ( ! empty( $input['describe'] ) ) {
			return self::describe_tool( $name, $available[ $name ]['writes'] );
		}

		$args = isset( $input['input'] ) && is_array( $input['input'] ) ? $input['input'] : array();
		$own  = self::funnelkit_tools();

		if ( isset( $own[ $name ] ) ) {
			return self::run_funnelkit_tool( $name, $own[ $name ], $args );
		}

		$step_id = self::guard( $name, $args, $writes );

		if ( is_wp_error( $step_id ) ) {
			return $step_id;
		}

		$ability  = wp_get_ability( $name );
		$before   = $writes && $step_id ? self::funnel_widgets_snapshot( $step_id ) : null;
		$template = $writes && $step_id ? self::page_template( $step_id ) : '';

		try {
			// As MCP clients send it: {} for a tool that takes no input means none.
			$result = $ability->execute( ! $args && ! $ability->get_input_schema() ? null : $args );
		} finally {
			// Elementor's draft save resets the live Step's page template (see keep_page_template()).
			self::keep_page_template( $step_id, $template );
		}

		if ( is_wp_error( $result ) ) {
			// Elementor's own message tells the agent what to fix in its input.
			$result = new WP_Error( 'fkmcp_design_tool_failed', sprintf( '%1$s: %2$s', $name, $result->get_error_message() ) );
		} elseif ( $before ) {
			$lost = self::restore_lost_funnel_widgets( $step_id, $before );

			if ( is_wp_error( $lost ) ) {
				$result = $lost;
			}
		}

		if ( ! is_wp_error( $result ) && $step_id && 'elementor/publish-document' === $name ) {
			// A published edit goes into the Step's History, as the result of the agent's changes.
			self::save_revision( $step_id, self::AI_RESULT );
			// The next change starts a new session, with its own "Before AI changes" and "AI result" points.
			delete_transient( self::before_ai_key( $step_id ) );
			delete_transient( self::ai_result_key( $step_id ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$answer = array( 'tool' => $name );

		if ( $step_id ) {
			$answer['step_id'] = $step_id;
		}

		$answer['result'] = $result;

		return $writes && $step_id ? self::with_quality( $answer ) : $answer;
	}

	/**
	 * Runs one of FunnelKit's design tools: its input checked against its
	 * schema, as the Abilities API checks an ability's.
	 *
	 * @param string $name Tool name.
	 * @param array  $spec Its definition.
	 * @param array  $args Its input.
	 *
	 * @return array|WP_Error
	 */
	private static function run_funnelkit_tool( $name, array $spec, array $args ) {
		$valid = rest_validate_value_from_schema( $args, $spec['input_schema'], 'input' );

		if ( is_wp_error( $valid ) ) {
			return new WP_Error( 'fkmcp_invalid_input', sprintf( '%1$s: %2$s. Nothing was changed.', $name, rtrim( $valid->get_error_message(), '.' ) ) );
		}

		if ( is_array( $spec['run'] ) ) {
			self::quality();
			$result = call_user_func( $spec['run'], $args );
		} else {
			self::funnel_widgets();
			$result = call_user_func( array( 'FKMCP_Funnel_Widgets', $spec['run'] ), $args );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 'update' === $spec['run'] ) {
			self::mark_restyled( absint( $args['step_id'] ), (string) $args['element_id'] );
		}

		$answer = array(
			'tool'    => $name,
			'step_id' => absint( $args['step_id'] ),
			'result'  => $result,
		);

		return $spec['writes'] ? self::with_quality( $answer ) : $answer;
	}

	/**
	 * Adds the Step's quality to a design change's answer, so each round of
	 * edits shows where the page stands, and the next thing to do.
	 *
	 * @param array $answer A run's answer, with step_id.
	 *
	 * @return array
	 */
	private static function with_quality( array $answer ) {
		$step = empty( $answer['step_id'] ) ? null : self::resolve_step( array( 'step_id' => $answer['step_id'] ) );

		if ( ! is_array( $step ) ) {
			return $answer;
		}

		// One decode of the design the agent works on, for the review and the reminder alike.
		$elements = self::elements( self::working_post_id( $step['id'] ) );

		self::quality();
		$review = FKMCP_Step_Design_Quality::review( $step, $elements );

		$answer['quality'] = array(
			'score'    => $review['score'],
			'grade'    => $review['grade'],
			'ready'    => $review['ready'],
			'findings' => array_slice( $review['findings'], 0, 6 ),
		);

		$hint = self::restyle_hint( $step['id'], $step, $elements );

		if ( '' !== $hint ) {
			$answer['next'] = $hint;
		} elseif ( ! $review['ready'] ) {
			$answer['next'] = $review['next'];
		}

		return $answer;
	}

	/** The quality check loads when one runs. */
	private static function quality() {
		require_once __DIR__ . '/class-fkmcp-step-design-quality.php';
	}

	/** How long a funnel widget counts as restyled, for restyle_hint(). */
	const RESTYLED_TTL = DAY_IN_SECONDS;

	/**
	 * What an Elementor write's answer reminds the agent of: the Step's funnel
	 * widgets nobody has restyled yet. They keep FunnelKit's own look, so an
	 * agent that styles only its own sections leaves them clashing with them.
	 *
	 * @param int        $step_id  Step id.
	 * @param array|null $step     The resolved Step, when the caller has it.
	 * @param array|null $elements Its working design, when the caller has decoded it.
	 *
	 * @return string '' once each funnel widget on the Step has been restyled.
	 */
	private static function restyle_hint( $step_id, $step = null, $elements = null ) {
		$step = is_array( $step ) ? $step : self::resolve_step( array( 'step_id' => $step_id ) );

		if ( is_wp_error( $step ) || empty( $step['entry']['keep'] ) ) {
			return '';
		}

		$elements = is_array( $elements ) ? $elements : self::elements( self::working_post_id( $step['id'] ) );
		$found    = self::find_widgets( $elements, (array) $step['entry']['keep'] );
		$restyled = (array) get_transient( 'fkmcp_restyled_' . $step['id'] );
		$left     = array();

		foreach ( $found as $type => $element_id ) {
			if ( '' !== $element_id && ! in_array( $element_id, $restyled, true ) ) {
				$label  = isset( $step['entry']['widgets'][ $type ] ) ? $step['entry']['widgets'][ $type ] : $type;
				$left[] = sprintf( '%1$s (%2$s, element %3$s)', $label, $type, $element_id );
			}
		}

		if ( ! $left ) {
			return '';
		}

		return sprintf( 'Restyle the funnel widgets to match your design before you finish: %1$s %2$s FunnelKit\'s own fonts, colours and button. List their settings with read-design funnelkit/get-funnel-widgets (step_id %3$d, search e.g. button color typography) and set them with edit-design funnelkit/update-funnel-widget.', implode( ', ', $left ), 1 === count( $left ) ? 'still shows' : 'still show', $step['id'] );
	}

	/**
	 * Notes that the agent restyled a funnel widget, so restyle_hint() stops
	 * naming it.
	 *
	 * @param int    $step_id    Step id.
	 * @param string $element_id The widget's element id.
	 */
	private static function mark_restyled( $step_id, $element_id ) {
		$key      = 'fkmcp_restyled_' . $step_id;
		$restyled = (array) get_transient( $key );

		if ( ! in_array( $element_id, $restyled, true ) ) {
			$restyled[] = $element_id;
			set_transient( $key, array_slice( $restyled, -20 ), self::RESTYLED_TTL );
		}
	}

	/**
	 * What a design tool may do on this server: one that takes a post works on
	 * a Funnel Step; a write needs the Step on an Elementor design and must not
	 * remove its funnel widgets. Before a write the Step is re-marked as built
	 * with Elementor, since Elementor drops edits to a post without that mark
	 * when it publishes, and its design so far is saved to its History.
	 *
	 * @param string $name   Ability name.
	 * @param array  $args   The tool's input.
	 * @param bool   $writes Whether the tool writes.
	 *
	 * @return int|WP_Error The Step id (0 for a read that names no post).
	 */
	/**
	 * Why a design write must not run on a Step, when it does not show an
	 * Elementor design: a save there would mark it built with Elementor and
	 * bring back whatever Elementor design it showed before, stale.
	 *
	 * @param array $step resolve_step() result.
	 *
	 * @return WP_Error|null
	 */
	public static function not_elementor_error( $step ) {
		if ( 'elementor' === self::step_design( $step )['builder'] ) {
			return null;
		}

		return new WP_Error( 'fkmcp_design_not_elementor', sprintf( 'Step %d does not show an Elementor design yet: call prepare-step-design first. Nothing was changed.', $step['id'] ) );
	}

	private static function guard( $name, array $args, $writes ) {
		if ( ! array_key_exists( 'post_id', $args ) ) {
			// A write that names no post cannot be scoped to a Funnel Step: refuse it
			// rather than let it through, should an Elementor release rename the argument.
			return $writes ? new WP_Error( 'fkmcp_step_required', sprintf( '%s needs post_id, the id of a Funnel Step. Nothing was changed.', $name ) ) : 0;
		}

		$step = self::resolve_step( array( 'step_id' => $args['post_id'] ) );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		if ( ! $writes ) {
			return $step['id'];
		}

		$locked = self::lock_error( $step['id'] );

		if ( $locked ) {
			return $locked;
		}

		$not_elementor = self::not_elementor_error( $step );

		if ( $not_elementor ) {
			return $not_elementor;
		}

		$parent = isset( $args['parent_id'] ) ? (string) $args['parent_id'] : 'document';
		$mode   = isset( $args['mode'] ) ? $args['mode'] : '';

		if ( 'elementor/build-composition' === $name && 'replace_children' === $mode && in_array( $parent, array( '', 'document' ), true ) && ! empty( $step['entry']['widgets'] ) ) {
			return new WP_Error( 'fkmcp_would_remove_funnel_widgets', sprintf( 'Replacing every top-level element of Step %1$d would delete its %2$s. Replace the children of one of your own sections instead (parent_id = its id), or delete sections with elementor/manage-elements. To start the page over, call prepare-step-design with mode blank. Nothing was changed.', $step['id'], implode( ', ', array_values( $step['entry']['widgets'] ) ) ) );
		}

		// Nor may a delete, or replacing one element's children, take a funnel widget with it.
		$doomed = array();

		if ( 'elementor/build-composition' === $name && 'replace_children' === $mode && ! in_array( $parent, array( '', 'document' ), true ) ) {
			$doomed[] = array( $parent, false );
		}
		if ( 'elementor/manage-elements' === $name && ! empty( $args['operations'] ) && is_array( $args['operations'] ) ) {
			foreach ( $args['operations'] as $operation ) {
				if ( is_array( $operation ) && 'delete' === ( $operation['action'] ?? '' ) && ! empty( $operation['element_id'] ) ) {
					$doomed[] = array( (string) $operation['element_id'], true );
				}
			}
		}

		if ( $doomed && ! empty( $step['entry']['keep'] ) ) {
			$tree = self::elements( self::working_post_id( $step['id'] ) );

			foreach ( $doomed as list( $element_id, $self_too ) ) {
				$held = self::funnel_widgets_under( $tree, $element_id, $step['entry']['keep'], $self_too );

				if ( $held ) {
					return new WP_Error( 'fkmcp_would_remove_funnel_widgets', sprintf( 'Element %1$s holds the Step\'s %2$s (element %3$s): this would delete it. Move it out first with funnelkit/move-funnel-widget (or move the element that holds it with elementor/manage-elements), then delete. Nothing was changed.', $element_id, $held['widgetType'], $held['id'] ) );
				}
			}
		}

		if ( 'builder' !== get_post_meta( $step['id'], '_elementor_edit_mode', true ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $step['id'], false );

			if ( $document ) {
				$document->set_is_built_with_elementor( true );
			}
		}

		// The design before the first change, often an imported template with no revision yet.
		self::save_revision( $step['id'], self::BEFORE_AI );

		return $step['id'];
	}

	/**
	 * The Step's working design and the funnel widgets it must keep, taken
	 * before an Elementor write so the write can be checked against it.
	 *
	 * @param int $step_id Step id.
	 *
	 * @return array|null tree, found (widget type => element id); null when the Step keeps none.
	 */
	private static function funnel_widgets_snapshot( $step_id ) {
		$step = self::resolve_step( array( 'step_id' => $step_id ) );

		if ( is_wp_error( $step ) || empty( $step['entry']['keep'] ) ) {
			return null;
		}

		$tree  = self::elements( self::working_post_id( $step_id ) );
		$found = self::find_widgets( $tree, (array) $step['entry']['keep'] );

		return $found ? array(
			'tree'  => $tree,
			'found' => $found,
		) : null;
	}

	/**
	 * After an Elementor write: guard() checks each delete against the page
	 * as it was, but a batch applies its operations in order - a funnel widget
	 * moved into an element and that element deleted passes it. Every funnel
	 * widget the Step had must still be there; when one is gone, the design
	 * before the write is put back.
	 *
	 * @param int   $step_id Step id.
	 * @param array $before  funnel_widgets_snapshot() result.
	 *
	 * @return WP_Error|null The refusal, once the design is restored; null when nothing was lost.
	 */
	private static function restore_lost_funnel_widgets( $step_id, array $before ) {
		$after = self::find_widgets( self::elements( self::working_post_id( $step_id ) ), array_keys( $before['found'] ) );
		$lost  = array_diff_key( $before['found'], $after );

		if ( ! $lost ) {
			return null;
		}

		$restored = self::save_design( $step_id, $before['tree'] );

		if ( class_exists( 'WFFN_MCP_Log' ) ) {
			WFFN_MCP_Log::event( sprintf( 'DESIGN_WRITE_UNDONE step=%d lost=%s', $step_id, implode( ',', array_keys( $lost ) ) ) );
		}

		$type = (string) key( $lost );

		if ( is_wp_error( $restored ) ) {
			return new WP_Error( 'fkmcp_would_remove_funnel_widgets', sprintf( 'That change deleted the Step\'s %1$s (element %2$s), and the design before it could not be put back: %3$s Restore it from the Step\'s History ("Before AI changes").', $type, $lost[ $type ], $restored->get_error_message() ) );
		}

		return new WP_Error( 'fkmcp_would_remove_funnel_widgets', sprintf( 'That change deleted the Step\'s %1$s (element %2$s) - an element it was moved into was deleted - so it was undone and the design is as it was before. Keep the funnel widget out of what you delete: move it out first with funnelkit/move-funnel-widget, then delete.', $type, $lost[ $type ] ) );
	}

	/**
	 * A tool name as given, cleaned.
	 *
	 * @param string $tool Tool name.
	 *
	 * @return string
	 */
	private static function tool_name( $tool ) {
		return sanitize_text_field( (string) $tool );
	}

	/**
	 * The refusal for a tool this server does not run, naming the ones it does.
	 *
	 * @param string $tool Tool name as given.
	 *
	 * @return WP_Error
	 */
	private static function unknown_tool( $tool ) {
		return new WP_Error( 'fkmcp_invalid_input', sprintf( '%1$s is not one of the design tools on this server: %2$s. Nothing was changed.', sanitize_text_field( (string) $tool ), implode( ', ', array_keys( self::available_design_tools() ) ) ) );
	}

	/**
	 * Whether this Elementor ships the design tools. Descriptions are built
	 * while abilities still register, Elementor's possibly after FunnelKit's:
	 * ask whether the Elementor has the tool, not the registry.
	 *
	 * @return bool
	 */
	private static function ships_design_tools() {
		return class_exists( '\Elementor\Modules\Mcp\Abilities\Manage_Elements_Ability' );
	}

	/**
	 * wp_abilities_api_init, on the requests FunnelKit AI's abilities register on:
	 * registers Elementor's too when its "Enable MCP access" is off, as Elementor
	 * then builds them but leaves them unregistered. Elementor's own registration
	 * is used - its guards run every call, and the abilities stay off its public
	 * MCP server while that setting is off.
	 *
	 * @return void
	 */
	public static function register_elementor_abilities() {
		$module = 'Elementor\Modules\Mcp\Module';

		if ( ! FKMCP_Module::needs_abilities() || self::has_ability( 'elementor/build-composition' ) || ! class_exists( $module ) || ! $module::is_active() ) {
			return;
		}

		try {
			$mcp = \Elementor\Plugin::$instance->modules_manager->get_modules( 'mcp' );

			if ( $mcp instanceof $module ) {
				$mcp->register_abilities();
			}
		} catch ( Throwable $e ) {
			return;
		}
	}

	/** Whether this server runs Elementor's design tools: the site's Elementor has them. */
	public static function design_tools_on() {
		return self::ships_design_tools();
	}

	/**
	 * fkmcp_scrub_result: the design tools hand over Elementor's answers whole -
	 * their CSS strings, element trees and instructions are the payload, as
	 * they are on Elementor's own server.
	 *
	 * @param bool   $scrub        Whether to scrub.
	 * @param string $ability_name Ability name.
	 *
	 * @return bool
	 */
	public static function keep_raw( $scrub, $ability_name ) {
		foreach ( array( 'read-design', 'edit-design' ) as $slug ) {
			if ( FKMCP_Registry::PREFIX . $slug === $ability_name ) {
				return false;
			}
		}

		return $scrub;
	}

	/** Whether an ability is registered, without the notice wp_get_ability() raises for a missing one. */
	private static function has_ability( $name ) {
		if ( function_exists( 'wp_has_ability' ) ) {
			return wp_has_ability( (string) $name );
		}

		return function_exists( 'wp_get_ability' ) && null !== wp_get_ability( (string) $name );
	}

	/**
	 * Elementor design tools this server runs: ability name => array( writes, summary ).
	 * Only abilities scoped to one post belong here - the site-wide ones (global
	 * classes and variables, default styles, components, new pages) stay on
	 * Elementor's own server.
	 *
	 * @return array
	 */
	public static function elementor_tools() {
		return self::normalize_tools( (array) apply_filters( 'fkmcp_elementor_design_tools', self::ELEMENTOR_TOOLS ) );
	}

	/**
	 * Tool list in the shape the runners use: name => array( writes, summary ).
	 *
	 * @param array $list Tools as the constant or the filter gives them.
	 *
	 * @return array
	 */
	private static function normalize_tools( array $list ) {
		$tools = array();

		foreach ( $list as $name => $tool ) {
			$tool           = is_array( $tool ) ? $tool : array( 'writes' => (bool) $tool );
			$tools[ $name ] = array(
				'writes'  => ! empty( $tool['writes'] ),
				'summary' => isset( $tool['summary'] ) ? (string) $tool['summary'] : '',
			);
		}

		return $tools;
	}

	/**
	 * Every design tool this server can run: FunnelKit's, then Elementor's.
	 * Tool name => array( writes, summary ).
	 *
	 * @return array
	 */
	private static function design_tools() {
		$tools = array();

		foreach ( self::funnelkit_tools() as $name => $tool ) {
			$tools[ $name ] = array(
				'writes'  => $tool['writes'],
				'summary' => $tool['summary'],
			);
		}

		return $tools + self::elementor_tools();
	}

	/**
	 * The design tools a call can run now: FunnelKit's, and the Elementor ones
	 * the installed Elementor registers.
	 *
	 * @return array tool name => array( writes, summary )
	 */
	private static function available_design_tools() {
		$tools = self::design_tools();

		foreach ( array_keys( self::elementor_tools() ) as $name ) {
			if ( ! isset( $tools[ $name ] ) ) {
				continue;
			}

			if ( ! self::has_ability( $name ) ) {
				unset( $tools[ $name ] );
				continue;
			}

			// Elementor's own word decides too: a tool it does not mark read-only
			// writes, whatever the list says, so read-design can never run it.
			$annotations = (array) wp_get_ability( $name )->get_meta_item( 'annotations', array() );

			if ( true !== ( $annotations['readonly'] ?? null ) ) {
				$tools[ $name ]['writes'] = true;
			}
		}

		return $tools;
	}

	/**
	 * Names of the design tools that read, or that write, for the runners' input schemas -
	 * FunnelKit's and Elementor's stock list, whatever fkmcp_elementor_design_tools says.
	 *
	 * @param bool $writes Which.
	 *
	 * @return string[]
	 */
	private static function tool_names( $writes ) {
		// ponytail: the constant, not the filtered list - a tool schema is the same on every site; the filter acts when a tool runs (available_design_tools).
		$schema_tools = array();

		foreach ( self::funnelkit_tools() as $name => $tool ) {
			$schema_tools[ $name ] = array( 'writes' => $tool['writes'] );
		}

		return array_keys(
			array_filter(
				$schema_tools + self::normalize_tools( self::ELEMENTOR_TOOLS ),
				static function ( $tool ) use ( $writes ) {
					return $tool['writes'] === $writes;
				}
			)
		);
	}
}
