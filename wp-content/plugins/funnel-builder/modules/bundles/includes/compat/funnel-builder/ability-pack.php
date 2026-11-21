<?php
/** Ability_Pack — place bundles in Funnel Builder's checkouts and One-Click Upsell offers. */
namespace WFFN\Bundles\Includes\Compat\Funnel_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Mcp\Pack as Bundles_Pack;
use WFFN\Bundles\Includes\Mcp\Permissions;
use WFFN\Bundles\Includes\Mcp\Schema;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

/**
 * A bundle sits in a checkout's products (the product switcher) or an upsell
 * offer's as an ordinary product row whose meta names the bundle and the offer
 * it sells (Adapter::BUNDLE_KEY, OFFER_KEY, ANCHOR_KEY). These abilities build
 * that row the way the admin does: the options come from the product search the
 * admin uses, and the row is added and removed through Funnel Builder's own
 * product routes, so every hook either plugin hangs there runs.
 *
 * Funnel Builder's own update-checkout-products ability takes WooCommerce
 * products only, which is why a bundle row needs these.
 */
class Ability_Pack extends \FKMCP_Pack {

	/** Pack id: a Bundles pack, so the Bundles context tool lists these tools. */
	const ID = Bundles_Pack::ID . '-funnels';

	/** Places a bundle row can go. */
	const CHECKOUT = 'checkout';
	const OFFER    = 'offer';

	/** Funnel Builder's routes for a place's products, by place. */
	const ROUTES = array(
		self::CHECKOUT => '/funnelkit-app/funnel-checkout/%d/products',
		self::OFFER    => '/funnelkit-app/funnel-offer/%d/products',
	);

	/** What each offer a row can carry sells, for the user. Any other offer is a Quantity Breaks tier. */
	const KINDS = array(
		Adapter::OFFER_BUNDLE    => 'pack',
		Adapter::OFFER_SINGLE    => 'single',
		Bundle_Store::FBT_OFFER  => 'set',
	);

	/** Checkouts and offers listed at most, and products of a bundle searched for its options. */
	const PLACES_MAX   = 100;
	const PRODUCTS_MAX = 10;

	/** A chat client's choice picker shows four options at most. */
	const OPTIONS_MAX = 4;

	/** The code of the throw a Funnel Builder controller's die is turned into. */
	const CONTROLLER_EXIT = 4642;

	/**
	 * Capability an ability needs when its definition names none.
	 *
	 * @return string
	 */
	public function default_capability() {
		return 'read';
	}

	/**
	 * Whether the current user holds one of this pack's capabilities.
	 *
	 * @param mixed $capability `read` or `write`.
	 * @return bool
	 */
	public function can( $capability ) {
		return 'write' === $capability ? Permissions::can_write() : Permissions::can_read();
	}

	/**
	 * A checkout or an upsell offer is there, beside this plugin's storage.
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( Bundle_Store::class ) && function_exists( 'wc_get_product' ) && ( self::serves( self::CHECKOUT ) || self::serves( self::OFFER ) );
	}

	/**
	 * Pack identity and the slug-needle groups a settings screen lists tools under.
	 *
	 * @return array
	 */
	public function plugin_meta() {
		return array(
			'id'     => self::ID,
			'name'   => __( 'Bundles in funnels', 'funnel-builder' ),
			'colour' => '#0073AA',
			// Longer than the host's own "funnel" needle, so these list under Bundles, not Funnels.
			'groups' => array(
				'bundle-funnel'      => __( 'Bundles', 'funnel-builder' ),
				'bundle-to-funnel'   => __( 'Bundles', 'funnel-builder' ),
				'bundle-from-funnel' => __( 'Bundles', 'funnel-builder' ),
			),
		);
	}

	/**
	 * Every ability this pack contributes, keyed by slug.
	 *
	 * @return array
	 */
	public function definitions() {
		$readonly = array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
		$can_read = array( Permissions::class, 'can_read' );
		$where    = self::where_text();
		$place    = Schema::obj(
			array(
				'place'          => Schema::enum( array( self::CHECKOUT, self::OFFER ) ),
				'id'             => Schema::int( 'checkout_id or offer_id.' ),
				'title'          => Schema::str(),
				'funnel_id'      => Schema::int( '', true ),
				'funnel_title'   => Schema::str( '', true ),
				'upsell_step_id' => Schema::int( 'For an offer: its upsell step.', true ),
				'status'         => Schema::str(),
				'selection_mode' => Schema::int( 'For a checkout: 1 every product sold together, 2 the shopper picks one (the product switcher), 3 the shopper picks any.', true ),
				'edit_link'      => Schema::str( '', true ),
				'rows'           => Schema::arr( self::row_schema() ),
			)
		);

		return array(
			'list-bundle-funnel-placements'    => array(
				'label'               => __( 'List Bundle Funnel Placements', 'funnel-builder' ),
				'description'         => 'The ' . $where . ' on this store, by funnel, each with its product rows and which of them sell a bundle: "where is my Performance Stack used?", "which checkouts sell a bundle?". bundle_id lists only the places selling that bundle. A row\'s row_key is what remove-bundle-from-funnel takes. Read-only.',
				'input_schema'        => Schema::input(
					array(
						'bundle_id' => Schema::in_id( 'Only places with a row selling this bundle.' ),
						'search'    => Schema::in_search( 'Only places whose title or funnel title contains this.', 100 ),
					),
					array(),
					array( 'default' => (object) array() )
				),
				'output_schema'       => Schema::obj(
					array(
						'places'    => Schema::arr( $place ),
						'checkouts' => Schema::bool( 'Funnel Builder checkouts are active here.' ),
						'offers'    => Schema::bool( 'One-Click Upsell offers are active here.' ),
					),
					'',
					array( 'places' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => array( $this, 'list_places' ),
				'annotations'         => $readonly,
			),

			'get-bundle-funnel-options' => array(
				'label'               => __( 'Get Bundle Funnel Options', 'funnel-builder' ),
				'description'         => 'The ways a bundle can be sold as a row in a checkout or an upsell offer, as the admin\'s product search offers them: the whole pack, just the product (a Fixed Bundle\'s "Buy 1"), the bought-together set, or a Quantity Breaks tier. Pass bundle_id, or product_id for the bundle that product shows. Each option\'s option value is what add-bundle-to-funnel takes. Only a live bundle, and only the one its product shows, has options; a Mix & Match bundle sells on its product page, not as a row. Read-only.',
				'input_schema'        => Schema::input(
					array(
						'bundle_id'  => Schema::in_id( 'A bundle, from list-bundles.' ),
						'product_id' => Schema::in_id( 'A product: the options of the bundle it shows.' ),
					),
					array(),
					array( 'default' => (object) array() )
				),
				'output_schema'       => Schema::obj(
					array(
						'options' => Schema::arr( self::option_schema() ),
						'note'    => Schema::str( 'Why there are none, when there are none.', true ),
					),
					'',
					array( 'options' )
				),
				'permission_callback' => $can_read,
				'execute_callback'    => array( $this, 'get_options' ),
				'annotations'         => $readonly,
			),

			'add-bundle-to-funnel'      => array(
				'capability'          => 'write',
				'label'               => __( 'Add Bundle To Funnel', 'funnel-builder' ),
				'description'         => 'Sell a bundle in a Funnel Builder checkout (its product switcher) or a One-Click Upsell offer, the way the admin\'s product search adds it: "add the Performance Stack to my checkout", "offer the whey set as the upsell". Send checkout_id or offer_id (list-bundle-funnel-placements) and option (get-bundle-funnel-options), or bundle_id when it has one way to sell. FIRST ask the user what they have not said, each as a multiple-choice pick: called without the place or the option it adds nothing and answers added=false with those questions in choices; put them to the user as picks, never as plain text, then call again. One row per call. For a checkout, selection_mode sets how shoppers choose between its products (2 = pick one in the product switcher; 2 and 3 need Funnel Builder Pro); left out, it is unchanged, and when the checkout then sells several products together it asks. checkout_id with selection_mode alone changes only that. Write.',
				'input_schema'        => Schema::input(
					array(
						'checkout_id'    => Schema::in_id( 'A checkout step, from list-bundle-funnel-placements.' ),
						'offer_id'       => Schema::in_id( 'An upsell offer, from list-bundle-funnel-placements.' ),
						'option'         => Schema::in_str( 'From get-bundle-funnel-options.', array( 'maxLength' => 100 ) ),
						'bundle_id'      => Schema::in_id( 'Instead of option, when the bundle sells one way only.' ),
						'selection_mode' => Schema::in_int(
							'Checkout only: 1 every product sold together, 2 the shopper picks one, 3 the shopper picks any.',
							array(
								'minimum' => 1,
								'maximum' => 3,
							)
						),
					),
					array(),
					array( 'default' => (object) array() )
				),
				'output_schema'       => Schema::obj(
					array(
						'added'          => Schema::bool(),
						'place'          => Schema::enum( array( self::CHECKOUT, self::OFFER ), '', true ),
						'id'             => Schema::int( '', true ),
						'row'            => array_merge( self::row_schema(), array( 'type' => array( 'object', 'null' ) ) ),
						'selection_mode' => Schema::int( '', true ),
						'edit_link'      => Schema::str( '', true ),
						'ask_user'       => Schema::arr( Schema::str(), 'Questions to put to the user.' ),
						'choices'        => Bundles_Pack::choices_schema(),
						'next'           => Schema::str( 'What to do now.' ),
					),
					'',
					array( 'added' )
				),
				'permission_callback' => array( Permissions::class, 'can_write' ),
				'execute_callback'    => array( $this, 'add_row' ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),

			'remove-bundle-from-funnel' => array(
				'capability'          => 'write',
				'label'               => __( 'Remove Bundle From Funnel', 'funnel-builder' ),
				'description'         => 'Take one bundle row out of a checkout or an upsell offer: checkout_id or offer_id, and the row\'s row_key from list-bundle-funnel-placements. Only a row that sells a bundle is removed; the place\'s other products are left as they are. Write.',
				'input_schema'        => Schema::input(
					array(
						'checkout_id' => Schema::in_id( 'A checkout step.' ),
						'offer_id'    => Schema::in_id( 'An upsell offer.' ),
						'row_key'     => Schema::in_str( 'The row, from list-bundle-funnel-placements.', array( 'maxLength' => 64 ) ),
					),
					array( 'row_key' )
				),
				'output_schema'       => Schema::obj(
					array(
						'removed' => Schema::bool(),
						'place'   => Schema::enum( array( self::CHECKOUT, self::OFFER ) ),
						'id'      => Schema::int(),
						'rows'    => Schema::arr( self::row_schema(), 'The rows left.' ),
					),
					'',
					array( 'removed' )
				),
				'permission_callback' => array( Permissions::class, 'can_write' ),
				'execute_callback'    => array( $this, 'remove_row' ),
				'annotations'         => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * List Bundle Funnel Placements: the funnelkit/list-bundle-funnel-placements ability.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public function list_places( $input = array() ) {
		$input  = (array) $input;
		$bundle = (string) absint( $input['bundle_id'] ?? 0 );
		$search = strtolower( trim( (string) ( $input['search'] ?? '' ) ) );
		$places = array();

		foreach ( array( self::CHECKOUT, self::OFFER ) as $kind ) {
			foreach ( self::place_ids( $kind ) as $id ) {
				$place = self::describe_place( $kind, $id );
				if ( '0' !== $bundle && ! in_array( $bundle, array_map( 'strval', array_column( $place['rows'], 'bundle_id' ) ), true ) ) {
					continue;
				}
				if ( '' !== $search && false === strpos( strtolower( $place['title'] . ' ' . (string) $place['funnel_title'] ), $search ) ) {
					continue;
				}
				$places[] = $place;
			}
		}

		return array(
			'places'    => $places,
			'checkouts' => self::serves( self::CHECKOUT ),
			'offers'    => self::serves( self::OFFER ),
		);
	}

	/**
	 * Get Bundle Funnel Options: the funnelkit/get-bundle-funnel-options ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function get_options( $input = array() ) {
		$input   = (array) $input;
		$bundle  = absint( $input['bundle_id'] ?? 0 );
		$product = absint( $input['product_id'] ?? 0 );
		if ( ! $bundle && ! $product ) {
			return new \WP_Error( 'fkmcp_invalid_input', 'Pass bundle_id or product_id.' );
		}
		if ( $bundle ) {
			$refused = self::unsellable( $bundle );
			if ( null !== $refused ) {
				return $refused;
			}
		}

		$options = $bundle ? self::bundle_options( $bundle ) : self::product_options( $product );
		return array(
			'options' => $options,
			'note'    => $options ? null : self::no_options_note( $bundle, $product ),
		);
	}

	/**
	 * Add Bundle To Funnel: the funnelkit/add-bundle-to-funnel ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function add_row( $input = array() ) {
		return Bundles_Pack::write( 'add-bundle-to-funnel', $input, array( $this, 'do_add_row' ) );
	}

	/**
	 * The add itself: ask for what is missing, then one row through Funnel Builder's route.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function do_add_row( $input ) {
		$input = (array) $input;
		$place = self::place_from( $input );
		if ( is_wp_error( $place ) ) {
			return $place;
		}

		// A checkout and a mode alone: only how shoppers choose changes.
		if ( null !== $place && self::CHECKOUT === $place[0] && ! empty( $input['selection_mode'] ) && '' === (string) ( $input['option'] ?? '' ) && empty( $input['bundle_id'] ) ) {
			return self::change_mode( $place[1], absint( $input['selection_mode'] ) );
		}

		// The option, or the bundle's only one.
		$option = null;
		$asks   = array();
		if ( '' !== (string) ( $input['option'] ?? '' ) ) {
			$option = self::resolve_option( (string) $input['option'] );
			if ( null === $option ) {
				return new \WP_Error( 'fkmcp_invalid_input', 'That option is not one this store offers now. get-bundle-funnel-options lists them.' );
			}
		} elseif ( ! empty( $input['bundle_id'] ) ) {
			$refused = self::unsellable( absint( $input['bundle_id'] ) );
			if ( null !== $refused ) {
				return $refused;
			}
			$options = self::bundle_options( absint( $input['bundle_id'] ) );
			if ( ! $options ) {
				return new \WP_Error( 'fkmcp_invalid_input', (string) self::no_options_note( absint( $input['bundle_id'] ), 0 ) );
			}
			if ( 1 === count( $options ) ) {
				$option = $options[0];
			} else {
				$asks[] = self::option_question( $options );
			}
		} else {
			$asks[] = self::bundle_question();
		}
		if ( null === $place ) {
			array_unshift( $asks, self::place_question() );
		}
		if ( $asks ) {
			$asks = array_slice( $asks, 0, 3 );
			return array(
				'added'    => false,
				'ask_user' => self::question_lines( $asks ),
				'choices'  => $asks,
				'next'     => 'Nothing was added yet. Ask the user every question in choices as a clickable multiple-choice pick, never as plain text, following each note, then call add-bundle-to-funnel again with the answers.',
			);
		}

		list( $kind, $id ) = $place;
		$mode              = isset( $input['selection_mode'] ) ? absint( $input['selection_mode'] ) : 0;
		$refused           = self::mode_refusal( $kind, $mode );
		if ( null !== $refused ) {
			return $refused;
		}

		$before = wp_list_pluck( self::rows( $kind, $id ), 'row_key' );
		$sent   = self::post_row( $kind, $id, $option );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}
		$after = self::rows( $kind, $id );
		$row   = null;
		foreach ( $after as $candidate ) {
			if ( ! in_array( $candidate['row_key'], $before, true ) ) {
				$row = $candidate;
			}
		}
		if ( null === $row ) {
			return new \WP_Error( 'fkmcp_write_failed', 'Funnel Builder did not keep the row. On an offer without One-Click Upsells\' multi-product add-on, an offer holds one product: remove the one it has first.' );
		}
		if ( $mode ) {
			self::set_selection_mode( $id, $mode );
		}

		$place = self::describe_place( $kind, $id );
		$out   = array(
			'added'          => true,
			'place'          => $kind,
			'id'             => $id,
			'row'            => $row,
			'selection_mode' => $place['selection_mode'],
			'edit_link'      => $place['edit_link'],
			'next'           => 'Tell the user the bundle row was added, with edit_link.',
		);
		// Sold together, a bundle beside other products is bought with them: worth a question, not a guess.
		if ( self::CHECKOUT === $kind && 1 === $place['selection_mode'] && count( $place['rows'] ) > 1 ) {
			$out['choices']  = array(
				array(
					'question' => sprintf( 'This checkout now sells %d products together, so shoppers buy all of them. How should they choose?', count( $place['rows'] ) ),
					'options'  => array( 'Pick one (product switcher)', 'Pick any of them', 'Keep selling all together' ),
					'note'     => 'Apply it with add-bundle-to-funnel, checkout_id and selection_mode only: 2 = pick one, 3 = pick any (both need Funnel Builder Pro). Keep = nothing to do.',
				),
			);
			$out['ask_user'] = self::question_lines( $out['choices'] );
			$out['next']     = 'Tell the user the bundle row was added, with edit_link, then ask the ask_user question as a clickable pick and act on the answer as its note says.';
		}
		return $out;
	}

	/**
	 * Change only how shoppers choose between a checkout's products.
	 *
	 * @param int $id   Checkout id.
	 * @param int $mode 1, 2 or 3.
	 * @return array|\WP_Error
	 */
	private static function change_mode( $id, $mode ) {
		$refused = self::mode_refusal( self::CHECKOUT, $mode );
		if ( null !== $refused ) {
			return $refused;
		}
		self::set_selection_mode( $id, $mode );
		$place = self::describe_place( self::CHECKOUT, $id );
		return array(
			'added'          => false,
			'place'          => self::CHECKOUT,
			'id'             => $id,
			'selection_mode' => $place['selection_mode'],
			'edit_link'      => $place['edit_link'],
			'next'           => 'Nothing was added; only how shoppers choose between the checkout\'s products changed. Tell the user, with edit_link.',
		);
	}

	/**
	 * Why a selection mode cannot be set, or null when it can.
	 *
	 * @param string $kind self::CHECKOUT or self::OFFER.
	 * @param int    $mode 0 (none sent), 1, 2 or 3.
	 * @return \WP_Error|null
	 */
	private static function mode_refusal( $kind, $mode ) {
		if ( $mode && self::CHECKOUT !== $kind ) {
			return new \WP_Error( 'fkmcp_invalid_input', 'selection_mode is a checkout setting; an upsell offer has none.' );
		}
		if ( $mode && ! in_array( $mode, self::selection_modes(), true ) ) {
			return new \WP_Error( 'fkmcp_pro_required', 'Letting shoppers pick between a checkout\'s products (selection_mode 2 or 3) needs Funnel Builder Pro, which is not active here; nothing was changed.' );
		}
		return null;
	}

	/**
	 * The selection modes a checkout can be set to on this site.
	 *
	 * @return int[]
	 */
	private static function selection_modes() {
		/**
		 * Filters the selection modes a checkout can be set to: 1 sells every product
		 * together; Funnel Builder Pro adds 2 (the shopper picks one) and 3 (picks any).
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $modes Selection modes.
		 */
		return array_map( 'intval', (array) apply_filters( 'fkmcp_checkout_selection_modes', array( 1 ) ) );
	}

	/**
	 * Remove Bundle From Funnel: the funnelkit/remove-bundle-from-funnel ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function remove_row( $input = array() ) {
		return Bundles_Pack::write( 'remove-bundle-from-funnel', $input, array( $this, 'do_remove_row' ) );
	}

	/**
	 * The removal itself, through Funnel Builder's route.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function do_remove_row( $input ) {
		$input = (array) $input;
		$place = self::place_from( $input );
		if ( is_wp_error( $place ) ) {
			return $place;
		}
		if ( null === $place ) {
			return new \WP_Error( 'fkmcp_invalid_input', 'Send checkout_id or offer_id, from list-bundle-funnel-placements.' );
		}
		list( $kind, $id ) = $place;
		$key               = (string) ( $input['row_key'] ?? '' );

		$target = null;
		foreach ( self::rows( $kind, $id ) as $row ) {
			if ( $row['row_key'] === $key ) {
				$target = $row;
			}
		}
		if ( null === $target ) {
			return new \WP_Error( 'fkmcp_not_found', 'No row with that row_key there. list-bundle-funnel-placements lists the rows.' );
		}
		if ( null === $target['bundle_id'] ) {
			return new \WP_Error( 'fkmcp_invalid_input', 'That row sells a plain product, not a bundle; change it in Funnel Builder.' );
		}

		$response = self::dispatch( 'DELETE', $kind, $id, array( 'product_key' => array( $key ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$rows = self::rows( $kind, $id );
		return array(
			'removed' => ! in_array( $key, wp_list_pluck( $rows, 'row_key' ), true ),
			'place'   => $kind,
			'id'      => $id,
			'rows'    => $rows,
		);
	}

	/**
	 * Whether this store has the place: Aero Checkout, or One-Click Upsells.
	 *
	 * @param string $kind self::CHECKOUT or self::OFFER.
	 * @return bool
	 */
	private static function serves( $kind ) {
		return self::CHECKOUT === $kind
			? class_exists( 'WFACP_Common' )
			: class_exists( 'WFOCU_Core' ) && class_exists( 'WFOCU_Common' );
	}

	/**
	 * The places this store has, for descriptions.
	 *
	 * @return string
	 */
	private static function where_text() {
		$where = array();
		if ( self::serves( self::CHECKOUT ) ) {
			$where[] = 'Funnel Builder checkouts';
		}
		if ( self::serves( self::OFFER ) ) {
			$where[] = 'One-Click Upsell offers';
		}
		return $where ? implode( ' and ', $where ) : 'Funnel Builder checkouts and upsell offers';
	}

	/**
	 * The place an input names, as [ kind, id ]; null when it names none.
	 *
	 * @param array $input Ability input.
	 * @return array|null|\WP_Error
	 */
	private static function place_from( array $input ) {
		$checkout = absint( $input['checkout_id'] ?? 0 );
		$offer    = absint( $input['offer_id'] ?? 0 );
		if ( $checkout && $offer ) {
			return new \WP_Error( 'fkmcp_invalid_input', 'Send checkout_id or offer_id, not both.' );
		}
		if ( ! $checkout && ! $offer ) {
			return null;
		}
		$kind = $checkout ? self::CHECKOUT : self::OFFER;
		$id   = $checkout ? $checkout : $offer;
		if ( ! self::serves( $kind ) ) {
			return new \WP_Error( 'fkmcp_unavailable', self::CHECKOUT === $kind ? 'Funnel Builder\'s checkout is not active on this store.' : 'One-Click Upsells is not active on this store.' );
		}
		if ( get_post_type( $id ) !== ( self::CHECKOUT === $kind ? 'wfacp_checkout' : 'wfocu_offer' ) ) {
			return new \WP_Error( 'fkmcp_not_found', 'No ' . ( self::CHECKOUT === $kind ? 'checkout' : 'upsell offer' ) . ' with that id. list-bundle-funnel-placements lists them.' );
		}
		return array( $kind, $id );
	}

	/**
	 * The ids of a kind of place, newest first.
	 *
	 * @param string $kind self::CHECKOUT or self::OFFER.
	 * @return int[]
	 */
	private static function place_ids( $kind ) {
		if ( ! self::serves( $kind ) ) {
			return array();
		}
		return array_map(
			'intval',
			// The same arguments get_posts() would add: no filters, no sticky posts, no row count, newest first.
			( new \WP_Query(
				array(
					'post_type'           => self::CHECKOUT === $kind ? 'wfacp_checkout' : 'wfocu_offer',
					'post_status'         => array( 'publish', 'draft', 'private' ),
					'posts_per_page'      => self::PLACES_MAX,
					'fields'              => 'ids',
					'suppress_filters'    => true,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			) )->posts
		);
	}

	/**
	 * One place: its funnel, status, how shoppers choose, and its rows.
	 *
	 * @param string $kind self::CHECKOUT or self::OFFER.
	 * @param int    $id   Post id.
	 * @return array
	 */
	private static function describe_place( $kind, $id ) {
		$step   = self::OFFER === $kind ? absint( get_post_meta( $id, '_funnel_id', true ) ) : $id;
		$funnel = absint( get_post_meta( $step, '_bwf_in_funnel', true ) );
		$title  = null;
		if ( $funnel && isset( WFFN_Core()->admin ) && method_exists( WFFN_Core()->admin, 'get_funnel' ) ) {
			$object = WFFN_Core()->admin->get_funnel( $funnel );
			$title  = is_object( $object ) && method_exists( $object, 'get_title' ) ? html_entity_decode( (string) $object->get_title(), ENT_QUOTES, 'UTF-8' ) : null;
		}

		$mode = null;
		if ( self::CHECKOUT === $kind ) {
			$settings = get_post_meta( $id, '_wfacp_selected_products_settings', true );
			$mode     = max( 1, absint( is_array( $settings ) ? ( $settings['add_to_cart_setting'] ?? 1 ) : 1 ) );
		}

		$link = null;
		if ( $funnel && class_exists( 'WFFN_Common' ) && method_exists( 'WFFN_Common', 'get_step_edit_link' ) ) {
			$link = (string) \WFFN_Common::get_step_edit_link( $id, self::OFFER === $kind ? 'upsell' : 'aero', $funnel );
			$link = '#' === $link ? null : html_entity_decode( $link, ENT_QUOTES, 'UTF-8' );
		}

		return array(
			'place'          => $kind,
			'id'             => $id,
			'title'          => html_entity_decode( (string) get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			'funnel_id'      => $funnel ? $funnel : null,
			'funnel_title'   => $title,
			'upsell_step_id' => self::OFFER === $kind && $step ? $step : null,
			'status'         => (string) get_post_status( $id ),
			'selection_mode' => $mode,
			'edit_link'      => $link,
			'rows'           => self::rows( $kind, $id ),
		);
	}

	/**
	 * A place's product rows, each with the bundle it sells, if any.
	 *
	 * @param string $kind self::CHECKOUT or self::OFFER.
	 * @param int    $id   Post id.
	 * @return array[]
	 */
	private static function rows( $kind, $id ) {
		$rows = array();
		if ( self::CHECKOUT === $kind ) {
			// Raw meta, not WFACP_Common's per-request cache, which a write in this request leaves stale.
			foreach ( (array) get_post_meta( $id, '_wfacp_selected_products', true ) as $key => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$meta   = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
				$rows[] = self::row( (string) $key, absint( $row['id'] ?? 0 ), (string) ( $row['title'] ?? '' ), absint( $row['quantity'] ?? 1 ), $meta );
			}
			return $rows;
		}

		$setting = get_post_meta( $id, '_wfocu_setting', true );
		$fields  = is_object( $setting ) && isset( $setting->fields ) ? (array) $setting->fields : array();
		foreach ( is_object( $setting ) && isset( $setting->products ) ? (array) $setting->products : array() as $hash => $product ) {
			$field  = isset( $fields[ $hash ] ) ? (array) $fields[ $hash ] : array();
			// An offer keeps no title of its own; a pack's product is the shared container, so its bundle names it.
			$rows[] = self::row( (string) $hash, absint( $product ), '', absint( $field['quantity'] ?? 1 ), $field );
		}
		return $rows;
	}

	/**
	 * One row, in this pack's shape.
	 *
	 * @param string $key      Row key (a checkout's row id, an offer's product hash).
	 * @param int    $product  Product the row posts.
	 * @param string $title    Row title.
	 * @param int    $quantity Quantity.
	 * @param array  $meta     Row meta.
	 * @return array
	 */
	private static function row( $key, $product, $title, $quantity, array $meta ) {
		$bundle = absint( $meta[ Adapter::BUNDLE_KEY ] ?? 0 );
		$offer  = (string) ( $meta[ Adapter::OFFER_KEY ] ?? '' );
		$config = $bundle ? Bundle_Store::get_config( $bundle ) : null;
		if ( '' === $title ) {
			$title = is_array( $config ) ? (string) ( $config['name'] ?? '' ) : (string) get_the_title( $product );
		}
		return array(
			'row_key'     => $key,
			'product_id'  => $product,
			'title'       => html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ),
			'quantity'    => max( 1, $quantity ),
			'bundle_id'   => $bundle ? $bundle : null,
			'bundle_name' => is_array( $config ) ? (string) ( $config['name'] ?? '' ) : null,
			'sells'       => $bundle ? ( self::KINDS[ $offer ] ?? 'tier' ) : 'product',
		);
	}

	/**
	 * The options of the bundle a product shows, as the admin's product search offers them.
	 *
	 * @param int $product Product id.
	 * @return array[]
	 */
	private static function product_options( $product ) {
		$object = wc_get_product( $product );
		if ( ! $object instanceof \WC_Product || $object->get_parent_id() ) {
			return array();
		}
		$base = array(
			'id'                => $product,
			'product'           => $object->get_title(),
			'product_attribute' => '',
			'product_price'     => '',
			'product_type'      => $object->get_type(),
			'currency_symbol'   => get_woocommerce_currency_symbol(),
		);

		/** This filter is documented in Funnel Builder's admin/rest-api/class-wffn-rest-funnel-modules.php */
		$found   = (array) apply_filters( 'wffn_woocommerce_json_search_found_products', array( $base ) );
		$options = array();
		foreach ( $found as $result ) {
			$meta = is_array( $result['product_meta_data'] ?? null ) ? $result['product_meta_data'] : array();
			if ( empty( $meta[ Adapter::BUNDLE_KEY ] ) || empty( $meta[ Adapter::OFFER_KEY ] ) ) {
				continue;
			}
			$bundle    = absint( $meta[ Adapter::BUNDLE_KEY ] );
			$offer     = (string) $meta[ Adapter::OFFER_KEY ];
			$config    = Bundle_Store::get_config( $bundle );
			$options[] = array(
				'option'      => $product . ':' . $bundle . ':' . $offer,
				'label'       => html_entity_decode( (string) ( $result['product'] ?? '' ), ENT_QUOTES, 'UTF-8' ),
				'price'       => html_entity_decode( wp_strip_all_tags( (string) ( $result['product_price'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ),
				'sells'       => self::KINDS[ $offer ] ?? 'tier',
				'bundle_id'   => $bundle,
				'bundle_name' => is_array( $config ) ? (string) ( $config['name'] ?? '' ) : '',
				'product_id'  => $product,
				'row_product' => absint( $result['id'] ?? 0 ),
				'meta'        => array_map( 'strval', $meta ),
			);
		}
		return $options;
	}

	/**
	 * A bundle's options, from the products it is placed on.
	 *
	 * @param int $bundle Bundle id.
	 * @return array[]
	 */
	private static function bundle_options( $bundle ) {
		$config = Bundle_Store::get_config( $bundle );
		$sel    = is_array( $config['productSelection'] ?? null ) ? $config['productSelection'] : array();
		$ids    = array();
		foreach ( (array) ( $sel['products'] ?? array() ) as $entry ) {
			$ids[] = absint( is_array( $entry ) ? ( $entry['id'] ?? 0 ) : $entry );
		}

		// Each of its products is its own option: a set or a pack is built around the product it hangs off.
		$options = array();
		foreach ( array_slice( array_filter( array_unique( $ids ) ), 0, self::PRODUCTS_MAX ) as $product ) {
			foreach ( self::product_options( $product ) as $option ) {
				if ( $bundle === $option['bundle_id'] ) {
					$options[] = $option;
				}
			}
		}
		return $options;
	}

	/**
	 * The option an option value names, rebuilt from what the store offers now.
	 *
	 * @param string $value `product:bundle:offer`.
	 * @return array|null
	 */
	private static function resolve_option( $value ) {
		$parts = explode( ':', $value, 3 );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		foreach ( self::product_options( absint( $parts[0] ) ) as $option ) {
			if ( $option['option'] === $value ) {
				return $option;
			}
		}
		return null;
	}

	/**
	 * Refuse a bundle that cannot be a row: missing, a type nothing renders here, or Mix & Match.
	 *
	 * @param int $bundle Bundle id.
	 * @return \WP_Error|null
	 */
	private static function unsellable( $bundle ) {
		$config = Bundle_Store::get_config( $bundle );
		if ( null === $config ) {
			return new \WP_Error( 'fkmcp_not_found', 'No bundle with that id. list-bundles lists them.' );
		}
		$type = (int) ( $config['discountType'] ?? 0 );
		if ( ! in_array( $type, Bundle_Store::accepted_types(), true ) ) {
			// Named by the bundle: without the add-on, its type has only a generic label.
			return Bundles_Pack::pro_required( '"' . html_entity_decode( (string) ( $config['name'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '"', ' - nothing was added.' );
		}
		return null;
	}

	/**
	 * Why a bundle or product has no options.
	 *
	 * @param int $bundle  Bundle id, or 0.
	 * @param int $product Product id, or 0.
	 * @return string
	 */
	private static function no_options_note( $bundle, $product ) {
		$config = $bundle ? Bundle_Store::get_config( $bundle ) : null;
		if ( is_array( $config ) && 1 !== (int) ( $config['status'] ?? 0 ) ) {
			return 'The bundle is a draft: only a live bundle sells as a row. Make it live with update-bundle-status first.';
		}
		if ( is_array( $config ) && Bundle_Store::MODE_SPECIFIC_PRODUCTS !== (int) ( $config['productSelection']['mode'] ?? 0 ) ) {
			return 'The bundle is placed by category, so pass product_id: a product in one of its categories.';
		}
		return 'No bundle sells as a row there: a Mix & Match box sells on its product page only, and a product offers only the bundle it shows (get-product-bundles says which). A Quantity Breaks tier needs the Bundles Addon.';
	}

	/**
	 * The question naming where to add, with places as picks.
	 *
	 * @return array
	 */
	private static function place_question() {
		$labels = array();
		$notes  = array();
		foreach ( array( self::CHECKOUT, self::OFFER ) as $kind ) {
			foreach ( self::place_ids( $kind ) as $id ) {
				if ( count( $labels ) >= self::OPTIONS_MAX - 1 ) {
					break 2;
				}
				$place    = self::describe_place( $kind, $id );
				$labels[] = trim( ( $place['funnel_title'] ? $place['funnel_title'] . ': ' : '' ) . $place['title'] . ( self::OFFER === $kind ? ' (upsell offer)' : ' (checkout)' ) );
				$notes[]  = ( self::CHECKOUT === $kind ? 'checkout_id ' : 'offer_id ' ) . $id;
			}
		}
		if ( ! $labels ) {
			return array(
				'question' => 'There is no checkout or upsell offer to add it to yet. Create one in Funnel Builder first?',
				'options'  => array(),
			);
		}
		$labels[] = 'Another one';
		return array(
			'question' => 'Where should it be sold?',
			'options'  => $labels,
			'note'     => 'In order, these are ' . implode( ', ', $notes ) . '. For "Another one", list-bundle-funnel-placements has every place: offer the matches as picks the same way.',
		);
	}

	/**
	 * The question naming the bundle, with live bundles that sell as a row as picks, top priority first.
	 *
	 * @return array
	 */
	private static function bundle_question() {
		$labels = array();
		$ids    = array();
		foreach ( Bundle_Store::index() as $entry ) {
			$config = Bundle_Store::get_config( (int) $entry['id'] );
			if ( ! is_array( $config ) || 1 !== (int) ( $config['status'] ?? 0 ) || ! self::bundle_options( (int) $entry['id'] ) ) {
				continue;
			}
			$labels[] = html_entity_decode( (string) ( $config['name'] ?? '' ), ENT_QUOTES, 'UTF-8' );
			$ids[]    = (int) $entry['id'];
			if ( count( $labels ) >= self::OPTIONS_MAX - 1 ) {
				break;
			}
		}
		if ( ! $labels ) {
			return array(
				'question' => 'No live bundle sells as a row yet. Make one live first?',
				'options'  => array( 'Show my bundles', 'Not now' ),
				'note'     => 'A Fixed Bundle or a Frequently Bought Together set sells as a row once it is live: list-bundles, then update-bundle-status.',
			);
		}
		$labels[] = 'Another bundle';
		return array(
			'question' => 'Which bundle should it sell?',
			'options'  => $labels,
			'note'     => 'In order, these are bundle_id ' . implode( ', ', $ids ) . '. For "Another bundle", find it with list-bundles and offer matches as picks the same way. Then get-bundle-funnel-options for the bundle picked.',
		);
	}

	/**
	 * The question naming how a bundle sells, with its options as picks.
	 *
	 * @param array[] $options From bundle_options().
	 * @return array
	 */
	private static function option_question( array $options ) {
		$options = array_slice( $options, 0, self::OPTIONS_MAX );
		// One fixed bundle placed on several products offers the same pack once per product: only the row's name and image differ.
		$packs = array_unique( wp_list_pluck( $options, 'sells' ) ) === array( 'pack' ) && 1 === count( array_unique( wp_list_pluck( $options, 'price' ) ) );
		return array(
			'question' => $packs ? 'Which product should the row show the pack under? Its name and image head the row; the items and price are the same.' : 'Which way should it sell?',
			'options'  => array_map(
				static function ( $option ) {
					return trim( $option['label'] . ' (' . $option['price'] . ')' );
				},
				$options
			),
			'note'     => 'In order, the option values are ' . implode( ', ', wp_list_pluck( $options, 'option' ) ) . ': pass the picked one as option.',
		);
	}

	/**
	 * Questions as plain lines, the options spelled out.
	 *
	 * @param array[] $questions Questions.
	 * @return string[]
	 */
	private static function question_lines( array $questions ) {
		return array_map(
			static function ( $q ) {
				return $q['options'] ? $q['question'] . ' Options: ' . implode( ' | ', $q['options'] ) . '.' : $q['question'];
			},
			$questions
		);
	}

	/**
	 * Add one row through Funnel Builder's product route, as the admin's search does.
	 *
	 * @param string $kind   self::CHECKOUT or self::OFFER.
	 * @param int    $id     Post id.
	 * @param array  $option From product_options().
	 * @return true|\WP_Error
	 */
	private static function post_row( $kind, $id, array $option ) {
		$product  = (int) $option['row_product'];
		$response = self::dispatch(
			'POST',
			$kind,
			$id,
			array(
				'products'           => array( $product ),
				'products_meta_data' => array( array( $product, $option['meta'] ) ),
			)
		);
		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Call one of Funnel Builder's product routes with a JSON body, as the admin does.
	 *
	 * @param string $method HTTP method.
	 * @param string $kind   self::CHECKOUT or self::OFFER.
	 * @param int    $id     Post id.
	 * @param array  $body   Request body.
	 * @return array|\WP_Error Response data.
	 */
	private static function dispatch( $method, $kind, $id, array $body ) {
		$request = new \WP_REST_Request( $method, sprintf( self::ROUTES[ $kind ], $id ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		// As Funnel Builder's own abilities call its controllers: some routes end in
		// wp_send_json(), a bare die outside AJAX, so it is turned into a throw and
		// the JSON it echoed is read back.
		$thrower = static function () {
			return static function () {
				throw new \RuntimeException( '', (int) self::CONTROLLER_EXIT );
			};
		};
		$hooks   = array( 'wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler' );
		foreach ( $hooks as $hook ) {
			add_filter( $hook, $thrower, 9999 );
		}
		add_filter( 'wp_doing_ajax', '__return_true', 9999 );
		$level = ob_get_level();
		ob_start();
		try {
			$response = rest_do_request( $request );
			$echoed   = (string) ob_get_clean();
			$data     = $response->is_error() ? null : (array) $response->get_data();
			if ( '' !== trim( $echoed ) && is_array( json_decode( $echoed, true ) ) ) {
				$data = json_decode( $echoed, true );
			}
			if ( null === $data ) {
				return new \WP_Error( 'fkmcp_write_failed', 'Funnel Builder refused it: ' . $response->as_error()->get_error_message() );
			}
		} catch ( \RuntimeException $e ) {
			if ( self::CONTROLLER_EXIT !== $e->getCode() ) {
				throw $e;
			}
			$data = json_decode( (string) ob_get_clean(), true );
			if ( ! is_array( $data ) ) {
				return new \WP_Error( 'fkmcp_write_failed', 'Funnel Builder ended the request without an answer.' );
			}
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_doing_ajax', '__return_true', 9999 );
			foreach ( $hooks as $hook ) {
				remove_filter( $hook, $thrower, 9999 );
			}
		}

		if ( array_key_exists( 'success', $data ) && ! $data['success'] ) {
			return new \WP_Error( 'fkmcp_write_failed', 'Funnel Builder refused it: ' . wp_strip_all_tags( (string) ( $data['msg'] ?? 'no reason given' ) ) . '.' );
		}
		return $data;
	}

	/**
	 * Set how shoppers choose between a checkout's products, as Funnel Builder's product screen saves it.
	 *
	 * @param int $id   Checkout id.
	 * @param int $mode 1, 2 or 3.
	 * @return void
	 */
	private static function set_selection_mode( $id, $mode ) {
		$settings = \WFACP_Common::get_page_product_settings( $id );
		$settings = is_array( $settings ) ? $settings : array();
		if ( (string) ( $settings['add_to_cart_setting'] ?? '' ) === (string) $mode ) {
			return;
		}
		$settings['add_to_cart_setting'] = (string) $mode;
		\WFACP_Common::update_page_product_setting( $id, $settings );

		// The switcher's default picks belong to the old mode, as the product screen resets them.
		$switcher = get_post_meta( $id, '_wfacp_product_switcher_setting', true );
		if ( is_array( $switcher ) && ! empty( $switcher ) ) {
			$switcher['default_products'] = array();
			update_post_meta( $id, '_wfacp_product_switcher_setting', $switcher );
		}
	}

	/**
	 * A row's output shape.
	 *
	 * @return array
	 */
	private static function row_schema() {
		return Schema::obj(
			array(
				'row_key'     => Schema::str( 'What remove-bundle-from-funnel takes.' ),
				'product_id'  => Schema::int( 'The product the row posts; a pack posts the shared bundle container.' ),
				'title'       => Schema::str(),
				'quantity'    => Schema::int(),
				'bundle_id'   => Schema::int( 'The bundle it sells; null for a plain product.', true ),
				'bundle_name' => Schema::str( '', true ),
				'sells'       => Schema::enum( array( 'product', 'pack', 'single', 'set', 'tier' ), 'product = a plain product; pack = the whole fixed bundle; single = its product alone; set = a bought-together set; tier = a Quantity Breaks tier.' ),
			)
		);
	}

	/**
	 * An option's output shape.
	 *
	 * @return array
	 */
	private static function option_schema() {
		return Schema::obj(
			array(
				'option'      => Schema::str( 'What add-bundle-to-funnel takes.' ),
				'label'       => Schema::str( 'As the admin\'s search names it.' ),
				'price'       => Schema::str(),
				'sells'       => Schema::enum( array( 'pack', 'single', 'set', 'tier' ) ),
				'bundle_id'   => Schema::int(),
				'bundle_name' => Schema::str(),
				'product_id'  => Schema::int( 'The product whose bundle it is.' ),
				'row_product' => Schema::int( 'The product the row posts.' ),
				'meta'        => Schema::map( Schema::str(), 'The row meta the admin posts with it.' ),
			)
		);
	}
}
