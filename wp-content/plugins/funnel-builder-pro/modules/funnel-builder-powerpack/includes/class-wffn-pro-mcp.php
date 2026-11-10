<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder Pro's side of the shared MCP module: registers its ability
 * pack and gates every Pro ability on the licence. Lives here, not in the
 * free plugin, so nothing Pro - tools or paywall - ships or is described there.
 */
if ( ! class_exists( 'WFFN_Pro_MCP' ) ) {

	class WFFN_Pro_MCP {

		public static function init() {
			add_filter( 'fkmcp_ability_packs', array( __CLASS__, 'register_pack' ) );
			// After Funnel Builder's own packs (priority 10): its runtime is loaded by then.
			add_filter( 'fkmcp_ability_packs', array( __CLASS__, 'register_design_pack' ), 20 );
			// On every request: any save of a Step - the editor's too - can prune its revisions.
			add_filter( 'wp_save_post_revision_revisions_before_deletion', array( __CLASS__, 'keep_before_ai_point' ), 10, 2 );
			add_filter(
				'fkmcp_context_pro',
				function () {
					return array(
						'active'  => true,
						'version' => defined( 'WFFN_PRO_BUILD_VERSION' ) ? WFFN_PRO_BUILD_VERSION : null,
					);
				}
			);
		}

		/**
		 * Keeps a Step's newest "Before AI changes" restore point (the design
		 * tools' _fkmcp_restore_point revision meta) out of WordPress's revision
		 * pruning. Pruned, it would be re-created from a design that already
		 * holds the AI's changes, and the original would be lost. One revision
		 * per Step at most: a newer round's point takes over, and the older one
		 * can be pruned again.
		 *
		 * @param WP_Post[] $revisions The Step's revisions, oldest first: the candidates for deletion.
		 * @param int       $post_id   The Step.
		 *
		 * @return WP_Post[]
		 */
		public static function keep_before_ai_point( $revisions, $post_id ) {
			if ( ! is_array( $revisions ) || count( $revisions ) < 2 || ! in_array( get_post_type( $post_id ), array( 'wffn_landing', 'wffn_optin', 'wffn_oty', 'wfacp_checkout', 'wffn_ty', 'wfocu_offer' ), true ) ) {
				return $revisions;
			}

			update_meta_cache( 'post', wp_list_pluck( $revisions, 'ID' ) );

			foreach ( array_reverse( $revisions, true ) as $index => $revision ) {
				if ( 'before_ai' === get_metadata( 'post', $revision->ID, '_fkmcp_restore_point', true ) ) {
					unset( $revisions[ $index ] );

					return array_values( $revisions );
				}
			}

			return $revisions;
		}

		public static function register_pack( $packs ) {
			if ( ! class_exists( 'FKMCP_Pack' ) || ! defined( 'WFFN_PLUGIN_DIR' ) ) {
				return $packs;
			}

			// The pack calls into free-plugin code that lands with the MCP
			// screen: a Funnel Builder older than that has the loader but not
			// the surface this pack builds on, so it contributes nothing.
			if ( ! defined( 'WFFN_VERSION' ) ) {
				return $packs;
			}

			// Pro packs use the lite plugin's MCP runtime (format, links, permissions).
			$loader = WFFN_PLUGIN_DIR . '/includes/mcp/class-wffn-mcp-loader.php';

			// A Funnel Builder older than the MCP runtime: no pack rather than a fatal.
			if ( ! is_readable( $loader ) ) {
				return $packs;
			}

			require_once $loader;
			WFFN_MCP_Loader::load();

			require_once __DIR__ . '/mcp/class-fkmcp-pack-funnels-pro.php';

			// Registered here, not at file load: every callback below is a
			// method of the class this line requires.
			add_filter( 'fkmcp_step_status_handlers', array( 'FKMCP_Pack_Funnels_Pro', 'step_status_handlers' ) );
			add_filter( 'fkmcp_step_context', array( 'FKMCP_Pack_Funnels_Pro', 'step_context' ), 10, 2 );
			add_filter( 'fkmcp_substep_reorder_handlers', array( 'FKMCP_Pack_Funnels_Pro', 'substep_reorder_handlers' ) );
			add_filter( 'fkmcp_create_step_guidance', array( 'FKMCP_Pack_Funnels_Pro', 'create_step_guidance' ) );
			add_filter( 'fkmcp_create_step_next', array( 'FKMCP_Pack_Funnels_Pro', 'create_step_next' ), 10, 4 );
			add_filter( 'fkmcp_funnel_ready_offers', array( 'FKMCP_Pack_Funnels_Pro', 'store_checkout_offers' ), 5, 3 );
			add_filter( 'fkmcp_import_status_guidance', array( 'FKMCP_Pack_Funnels_Pro', 'import_status_guidance' ) );
			// Pro is installed: no "install the Pro plugin you bought" nudge.
			add_filter( 'fkmcp_pro_plugin_nudge', '__return_empty_string' );

			// Upsell Offers join the step-design tools.
			require_once __DIR__ . '/mcp/class-wffn-pro-mcp-step-design.php';

			$packs[] = new FKMCP_Pack_Funnels_Pro();

			return $packs;
		}

		/**
		 * Step design with Elementor, a Pro feature: the step-design tools, only
		 * where Elementor runs and the Pro pack loaded. Not twice: a Funnel
		 * Builder build from before the tools moved to Pro still defines the
		 * pack, and has added it already.
		 *
		 * @param array $packs Packs so far.
		 *
		 * @return array
		 */
		public static function register_design_pack( $packs ) {
			if ( ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( 'FKMCP_Pack_Funnels_Pro', false ) || ! class_exists( 'FKMCP_Pack_Funnels', false ) || class_exists( 'FKMCP_Pack_Elementor_Design', false ) ) {
				return $packs;
			}

			require_once __DIR__ . '/mcp/class-fkmcp-pack-elementor-design.php';
			$packs[] = FKMCP_Pack_Elementor_Design::instance();

			return $packs;
		}

		/**
		 * Wraps a Pro ability's execute callback: a lapsed or inactive licence
		 * answers with a paywall instead of data. Paywalls are successful
		 * results, not errors, so AI clients relay the message and link rather
		 * than reporting a tool failure. Runs inside WFFN_MCP_Log::wrap, so the
		 * call is still audited and rate limited like any other.
		 */
		public static function gate( $callback ) {
			return static function ( $input = array() ) use ( $callback ) {
				$state = self::resolve();

				if ( 'ok' !== $state ) {
					return self::paywall( $state );
				}

				return call_user_func( $callback, $input );
			};
		}

		/**
		 * Maps the licence state machine onto the gate's vocabulary. Fail-open
		 * when the licence classes are not loaded on this request — a licensed
		 * site must never see a paywall because a class was missing.
		 *
		 * @return string 'ok' | 'pro_expired' | 'pro_inactive' | 'pro_required'
		 */
		public static function resolve() {
			if ( ! class_exists( 'WFFN_License_Config' ) || ! class_exists( 'WFFN_License_Admin_UI' ) ) {
				return 'ok';
			}

			$config = WFFN_License_Config::get_instance()->get_config();
			$state  = WFFN_License_Admin_UI::get_instance()->get_current_app_state( $config );

			if ( in_array( $state, array( 'pro', 'pro_without_license_on_grace_period' ), true ) ) {
				return 'ok';
			}

			if ( in_array( $state, array( 'license_expired', 'license_expired_on_grace_period' ), true ) ) {
				return 'pro_expired';
			}

			if ( 'pro_without_license' === $state ) {
				return 'pro_inactive';
			}

			return 'pro_required';
		}

		private static function paywall( $reason ) {
			$name = __( 'FunnelKit Funnel Builder Pro', 'funnel-builder-powerpack' );

			switch ( $reason ) {
				case 'pro_expired':
					/* translators: %s: product name. */
					$message    = sprintf( __( 'Your %s licence has expired, so this ability is unavailable. Renew the licence to restore it.', 'funnel-builder-powerpack' ), $name );
					$action_url = 'https://myaccount.funnelkit.com/';
					break;
				case 'pro_inactive':
					/* translators: %s: product name. */
					$message    = sprintf( __( '%s is installed but its licence is not activated, so this ability is unavailable. Activate the licence to restore it.', 'funnel-builder-powerpack' ), $name );
					$action_url = admin_url( 'admin.php?page=bwf&path=/settings' );
					break;
				default:
					/* translators: %s: product name. */
					$message    = sprintf( __( 'This ability needs an active %s licence.', 'funnel-builder-powerpack' ), $name );
					$action_url = 'https://myaccount.funnelkit.com/';
			}

			return array(
				'available'  => false,
				'reason'     => $reason,
				'message'    => $message,
				'action_url' => $action_url,
			);
		}
	}

	WFFN_Pro_MCP::init();
}
