<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FK_Checkout_Redirect' ) ) {

	#[\AllowDynamicProperties]
	class FK_Checkout_Redirect {

		const LOG_SOURCE = 'fk-checkout-redirect';

		private static $ins = null;

		/**
		 * In-memory cache for the resolved step ID (current request only).
		 * null  = not yet computed
		 * false = computed, no override (use default)
		 * int   = computed, redirect to this step ID
		 *
		 * @var null|false|int
		 */
		private $resolved_step_cache = null;

		/**
		 * WC session key prefix. Final key = prefix + cart_hash.
		 * A changed cart_hash means a different key → automatic cache miss.
		 */
		const SESSION_KEY_PREFIX = 'fk_checkout_redirect_';

		protected function __construct() {
			// Hooks are registered externally by WFFN_Step_WC_Checkout so this class is
			// only loaded when one of the relevant hooks actually fires.
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Write a debug message to the WooCommerce log (source: fk-checkout-redirect).
		 * Only active when WFACP_IS_DEV is true.
		 *
		 * @param string $message
		 */
		private function log( $message ) {
			if ( defined( 'WFACP_IS_DEV' ) && WFACP_IS_DEV && function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->debug( $message, array( 'source' => self::LOG_SOURCE ) );
			}
		}

		/**
		 * Resolve checkout page ID based on cart contents.
		 *
		 * Priority 10 runs AFTER the store checkout override at priority 8.
		 * Loops all cart items and collects resolved checkout step IDs.
		 * Items with no product or category configuration are skipped (not blocking).
		 * Falls back to default only when no items resolve, or resolved IDs conflict.
		 *
		 * @param int $checkout_page_id Current checkout page ID.
		 *
		 * @return int Resolved checkout page ID.
		 */
		public function resolve_checkout_from_cart( $checkout_page_id ) {

			// Layer 1 — in-memory: free for all repeat calls within the same request.
			if ( null !== $this->resolved_step_cache ) {
				$this->log( '[CACHE:memory] step_id: ' . ( $this->resolved_step_cache ?: 'default' ) );

				return $this->resolved_step_cache ?: $checkout_page_id;
			}

			$this->log( '---- resolve_checkout_from_cart triggered. Default page ID: ' . $checkout_page_id );

			if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
				$this->log( 'Cart is empty or WC unavailable. Returning default.' );
				$this->resolved_step_cache = false;

				return $checkout_page_id;
			}

			// Layer 2 — WC session keyed by cart_hash: persists across requests until cart changes.
			$cart_hash   = WC()->cart->get_cart_hash();
			$session_key = self::SESSION_KEY_PREFIX . $cart_hash;

			if ( $cart_hash && WC()->session ) {
				$cached = WC()->session->get( $session_key );
				if ( null !== $cached ) {
					$this->resolved_step_cache = $cached > 0 ? $cached : false;
					$this->log( '[CACHE:session] key: ' . $session_key . ' | step_id: ' . ( $cached ?: 'default' ) );

					return $cached > 0 ? $cached : $checkout_page_id;
				}
			}

			// Layer 3 — compute: run the full cart resolution logic.
			$this->log( '[COMPUTE] cart_hash: ' . $cart_hash );

			$resolved_ids = array();

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_id   = $cart_item['product_id'];
				$product_name = get_the_title( $product_id );

				$this->log( sprintf( 'Cart item [%s] => Product ID: %d (%s)', $cart_item_key, $product_id, $product_name ) );

				// Priority 1: Product-level meta
				$step_id = absint( get_post_meta( $product_id, '_fk_checkout_step_id', true ) );
				if ( $step_id > 0 ) {
					$this->log( sprintf( '  [PRODUCT META] step_id: %d', $step_id ) );
					$resolved_ids[] = $step_id;
					continue;
				}

				$this->log( '  No product meta. Checking categories...' );

				// Priority 2: Category-level meta (exactly 1 unique checkout ID must match)
				$step_id = $this->resolve_category_checkout( $product_id );
				if ( $step_id > 0 ) {
					$this->log( sprintf( '  [CATEGORY META] step_id: %d', $step_id ) );
					$resolved_ids[] = $step_id;
					continue;
				}

				$this->log( '  No category meta. Item skipped.' );

				// Item has no routing configured — skip, does not block other items
			}

			$this->log( 'Collected resolved_ids: ' . wp_json_encode( $resolved_ids ) );

			// No item had any routing configured — use default checkout
			if ( empty( $resolved_ids ) ) {
				$this->log( 'All items have no routing. Returning default: ' . $checkout_page_id );

				return $this->store_cache( $session_key, 0, $checkout_page_id );
			}

			$unique_ids = array_unique( $resolved_ids );

			$this->log( 'Unique IDs: ' . wp_json_encode( array_values( $unique_ids ) ) );

			// Conflict: configured items point to different checkout IDs
			if ( count( $unique_ids ) > 1 ) {
				$this->log( 'Conflict — multiple checkout IDs. Returning default: ' . $checkout_page_id );

				return $this->store_cache( $session_key, 0, $checkout_page_id );
			}

			$target_step_id = reset( $unique_ids );

			// Validate the step exists, is published, and is a checkout post type
			$post = get_post( $target_step_id );
			if ( ! $post || 'publish' !== $post->post_status || 'wfacp_checkout' !== $post->post_type ) {
				$reason = ! $post ? 'post not found' : ( 'publish' !== $post->post_status ? 'not published (status: ' . $post->post_status . ')' : 'wrong post_type: ' . $post->post_type );
				$this->log( sprintf( 'Validation failed for step_id %d — %s. Returning default.', $target_step_id, $reason ) );

				return $this->store_cache( $session_key, 0, $checkout_page_id );
			}

			$this->log( sprintf( 'Resolved to step_id: %d (%s)', $target_step_id, get_the_title( $target_step_id ) ) );

			return $this->store_cache( $session_key, $target_step_id, $checkout_page_id );
		}

		/**
		 * Persist result to both WC session and in-memory cache, then return the correct page ID.
		 *
		 * Stores step_id as int: 0 = use default, positive = redirect to step.
		 * Keeps session storage and in-memory cache in sync at a single point.
		 *
		 * @param string $session_key      The cart-hash-keyed session key.
		 * @param int    $step_id          Resolved step ID, or 0 for default.
		 * @param int    $checkout_page_id Fallback default page ID.
		 *
		 * @return int
		 */
		private function store_cache( $session_key, $step_id, $checkout_page_id ) {
			$this->resolved_step_cache = $step_id > 0 ? $step_id : false;

			if ( $session_key && WC()->session ) {
				WC()->session->set( $session_key, $step_id );
			}

			return $step_id > 0 ? $step_id : $checkout_page_id;
		}

		/**
		 * Resolve checkout step ID from product category term meta.
		 *
		 * Returns a step ID only when all configured categories agree on the same
		 * checkout ID (exactly 1 unique ID). Conflicts between categories return 0.
		 *
		 * @param int $product_id Product ID.
		 *
		 * @return int Step ID if exactly one unique checkout ID found, 0 otherwise.
		 */
		private function resolve_category_checkout( $product_id ) {
			$terms = get_the_terms( $product_id, 'product_cat' );

			if ( ! is_array( $terms ) || empty( $terms ) ) {
				$this->log( '  Product has no categories.' );

				return 0;
			}

			$matching_step_ids = array();

			foreach ( $terms as $term ) {
				$cat_step_id = absint( get_term_meta( $term->term_id, '_fk_checkout_step_id', true ) );
				$this->log( sprintf( '    Category "%s" (ID:%d) => step_id: %s', $term->name, $term->term_id, $cat_step_id ?: 'none' ) );
				if ( $cat_step_id > 0 ) {
					$matching_step_ids[] = $cat_step_id;
				}
			}

			// Exactly 1 unique checkout ID required across all matching categories
			$unique_step_ids = array_unique( $matching_step_ids );

			$this->log( '    Unique category step_ids: ' . wp_json_encode( array_values( $unique_step_ids ) ) );

			if ( count( $unique_step_ids ) === 1 ) {
				return $unique_step_ids[0];
			}

			if ( count( $unique_step_ids ) > 1 ) {
				$this->log( '    Category conflict — multiple different IDs found. Treating as unconfigured.' );
			}

			return 0;
		}

		/**
		 * Override "Add to Cart" button text.
		 *
		 * On archive/shop pages: only checks product-level meta (performance).
		 * On single product pages: checks product meta, then falls back to category meta.
		 *
		 * @param string     $text    Default button text.
		 * @param WC_Product $product Product object.
		 *
		 * @return string Custom text or original.
		 */
		public function override_add_to_cart_text( $text, $product ) {
			if ( ! $product instanceof WC_Product ) {
				return $text;
			}

			// For variable products, meta is stored on the parent product.
			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

			// Product-level meta (cached by WP object cache)
			$custom_text = get_post_meta( $product_id, '_fk_add_to_cart_text', true );
			if ( ! empty( $custom_text ) ) {
				return $custom_text;
			}

			// Category fallback only on single product pages (performance safeguard)
			if ( is_singular( 'product' ) ) {
				$terms = get_the_terms( $product_id, 'product_cat' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$cat_text = get_term_meta( $term->term_id, '_fk_add_to_cart_text', true );
						if ( ! empty( $cat_text ) ) {
							return $cat_text;
						}
					}
				}
			}

			return $text;
		}
	}
}
