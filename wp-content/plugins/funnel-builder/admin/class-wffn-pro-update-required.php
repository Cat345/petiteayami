<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Pro_Update_Required
 *
 * Replaces the admin app with an update prompt while the installed
 * Funnel Builder Pro is below the version this release pairs with
 * (WFFN_MIN_PRO_VERSION).
 */
if ( ! class_exists( 'WFFN_Pro_Update_Required' ) ) {
	class WFFN_Pro_Update_Required {

		/**
		 * @var WFFN_Pro_Update_Required|null
		 */
		private static $ins = null;



		/**
		 * Free plugin version the rollback installs. Hardcoded for now; later
		 * this should resolve to the last version pairing with the installed Pro.
		 */
		const ROLLBACK_VERSION = '3.15.0.9';

		public function __construct() {
			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'maybe_unload_app' ), 100 );
				add_action( 'admin_notices', array( $this, 'maybe_render_sitewide_notice' ) );
				add_action( 'admin_init', array( $this, 'maybe_register_update_row_message' ) );

				global $pagenow;
				if ( ! empty( $_GET['wffn_rollback'] ) && 'update.php' === $pagenow && ! empty( $_GET['action'] ) && 'upgrade-plugin' === $_GET['action'] && ! empty( $_GET['plugin'] ) && defined( 'WFFN_PLUGIN_BASENAME' ) && WFFN_PLUGIN_BASENAME === wp_unslash( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core update.php verifies the upgrade-plugin nonce.
					add_filter( 'site_transient_update_plugins', array( $this, 'maybe_inject_rollback_package' ) );
				}

				add_action( 'admin_init', array( $this, 'maybe_debug_rollback_url' ) );
			}
			add_action( 'admin_bar_menu', array( $this, 'maybe_add_admin_bar_state' ), 100 );
		}

		/**
		 * @return WFFN_Pro_Update_Required|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Whether the installed Funnel Builder Pro is older than the build this
		 * release pairs with.
		 *
		 * @return bool
		 */
		public function is_outdated() {
			if ( ! defined( 'WFFN_PRO_VERSION' ) || ! defined( 'WFFN_MIN_PRO_VERSION' ) ) {
				return false;
			}

			/** Source checkouts carry an unstamped build placeholder, not a comparable version. */
			if ( false !== strpos( WFFN_PRO_VERSION, '{{{' ) ) {
				return false;
			}

			return version_compare( WFFN_PRO_VERSION, WFFN_MIN_PRO_VERSION, '<' );
		}

		/**
		 * Whether the customer's Pro license has expired.
		 *
		 * @return bool
		 */
		public function is_license_expired() {
			return WFFN_Core()->admin->get_license_status() === 'expired';
		}

		/**
		 * Debug helper: any wp-admin URL with ?wffn_rollback_url=1 prints the
		 * generated rollback link.
		 *
		 * @return void
		 */
		public function maybe_debug_rollback_url() {
			if ( ! isset( $_GET['wffn_rollback_url'] ) || ! current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only debug output for admins.
				return;
			}

			$url = $this->get_rollback_url();
			wp_die(
				'<p><strong>Funnel Builder rollback URL</strong> (version ' . esc_html( self::ROLLBACK_VERSION ) . '):</p><p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>',
				'Funnel Builder rollback URL',
				array( 'response' => 200 )
			);
		}

		/**
		 * Link that runs the core plugin upgrader against the rollback package
		 * (see maybe_inject_rollback_package). Falls back to the plugins page
		 * when the user can't update plugins.
		 *
		 * @return string
		 */
		public function get_rollback_url() {
			if ( ! defined( 'WFFN_PLUGIN_BASENAME' ) || ! current_user_can( 'update_plugins' ) ) {
				return self_admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' );
			}

			return wp_nonce_url(
				self_admin_url( 'update.php?action=upgrade-plugin&wffn_rollback=1&plugin=' . rawurlencode( WFFN_PLUGIN_BASENAME ) ),
				'upgrade-plugin_' . WFFN_PLUGIN_BASENAME
			);
		}

		/**
		 * Feeds the rollback package to the core upgrader. Only kicks in on the
		 * update.php request started by get_rollback_url (wffn_rollback flag), so
		 * the plugins/updates screens never see the downgrade as an update.
		 *
		 * @param object|false $transient update_plugins transient value.
		 *
		 * @return object|false
		 */
		public function maybe_inject_rollback_package( $transient ) {

			if ( ! current_user_can( 'update_plugins' ) ) {
				return $transient;
			}

			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}

			$transient->response[ WFFN_PLUGIN_BASENAME ] = (object) array(
				'slug'        => 'funnel-builder',
				'plugin'      => WFFN_PLUGIN_BASENAME,
				'new_version' => self::ROLLBACK_VERSION,
				'url'         => 'https://wordpress.org/plugins/funnel-builder/',
				'package'     => 'https://downloads.wordpress.org/plugin/funnel-builder.' . self::ROLLBACK_VERSION . '.zip',
			);

			return $transient;
		}

		/**
		 * Name of the installed premium companion plugin the pairing applies to.
		 *
		 * @return string
		 */
		public function get_companion_name() {
			return defined( 'WFFN_BASIC_FILE' )
				? __( 'FunnelKit Funnel Builder Basic', 'funnel-builder' )
				: __( 'FunnelKit Funnel Builder Pro', 'funnel-builder' );
		}

		/**
		 * Companion plugin name without the brand prefix, for tight spaces.
		 *
		 * @return string
		 */
		public function get_companion_short_name() {
			return defined( 'WFFN_BASIC_FILE' )
				? __( 'Funnel Builder Basic', 'funnel-builder' )
				: __( 'Funnel Builder Pro', 'funnel-builder' );
		}

		/**
		 * Keeps the app bundle off the page while Pro is outdated. Registered on
		 * its own hook so it also catches the bundle enqueued by an older Pro
		 * build, which replaces WFFN_Admin::admin_enqueue_assets.
		 *
		 * @return void
		 */
		public function maybe_unload_app() {
			if ( ! $this->is_outdated() ) {
				return;
			}

			$admin = WFFN_Core()->admin;
			if ( ! is_object( $admin ) || ! method_exists( $admin, 'is_wffn_flex_page' ) || ! $admin->is_wffn_flex_page( 'all' ) ) {
				return;
			}

			wp_dequeue_script( 'wffn-contact-admin' );
			wp_deregister_script( 'wffn-contact-admin' );
			wp_dequeue_style( 'wffn-contact-admin' );
		}

		/**
		 * Adds a pairing-requirement row under the free Funnel Builder plugin on
		 * the plugins page. A separate row on its own hook — the core update rows
		 * (both plugins') stay untouched. Registered on admin_init so it also
		 * covers the AJAX search rerender, where load-plugins.php never fires.
		 *
		 * @return void
		 */
		public function maybe_register_update_row_message() {
			if ( ! $this->is_outdated() || ! defined( 'WFFN_PLUGIN_BASENAME' ) ) {
				return;
			}

			add_action( 'in_plugin_update_message-' . WFFN_PLUGIN_BASENAME, array( $this, 'render_in_core_update_row' ) );
			add_action( 'after_plugin_row_' . WFFN_PLUGIN_BASENAME, array( $this, 'render_update_row' ), 11, 2 );
		}

		/**
		 * Appended inside the core "There is a new version…" row when the free
		 * plugin itself has an update on offer.
		 *
		 * @return void
		 */
		public function render_in_core_update_row() {
			$this->render_pairing_warning_block( true );
		}

		/**
		 * The warning block shared by both plugins-page placements.
		 *
		 * @param bool $with_separator Whether a core message precedes the block.
		 *
		 * @return void
		 */
		private function render_pairing_warning_block( $with_separator ) {
			$update_link = '';
			if ( defined( 'WFFN_PRO_FILE' ) && current_user_can( 'update_plugins' ) ) {
				$pro_file = plugin_basename( WFFN_PRO_FILE );
				$updates  = get_site_transient( 'update_plugins' );
				if ( ! empty( $updates->response[ $pro_file ]->package ) ) {
					$update_link = sprintf(
						' <a href="%1$s" aria-label="%2$s">%3$s</a>',
						esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $pro_file, 'upgrade-plugin_' . $pro_file ) ),
						esc_attr( sprintf( /* translators: %s: premium plugin name */ __( 'Update %s now', 'funnel-builder' ), $this->get_companion_name() ) ),
						esc_html__( 'Update now', 'funnel-builder' )
					);
				}
			}

			if ( $with_separator ) {
				?>
				<hr class="wffn-pairing-warning__separator" />
				<?php
			}
			?>
			<div class="wffn-pairing-warning">
				<div class="wffn-pairing-warning__icon">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
				</div>
				<div>
					<div class="wffn-pairing-warning__title">
						<?php
						printf( /* translators: %s: premium plugin name */
							esc_html__( 'Update Required: %s', 'funnel-builder' ),
							esc_html( $this->get_companion_name() )
						);
						?>
					</div>
					<div class="wffn-pairing-warning__message">
						<?php
						printf( /* translators: 1: premium plugin name, 2: required version, 3: installed version */
							esc_html__( 'This version of Funnel Builder pairs with %1$s %2$s or newer, but %3$s is installed.', 'funnel-builder' ),
							esc_html( $this->get_companion_name() ),
							esc_html( WFFN_MIN_PRO_VERSION ),
							esc_html( WFFN_PRO_VERSION )
						);
						echo wp_kses_post( $update_link );
						?>
					</div>
				</div>
			</div>
			<style>
				/* Inline so the block also survives the AJAX search rerender of the list table. */
				.wffn-pairing-warning {
					display: flex;
					max-width: 1000px;
					margin-block-end: 5px;
				}

				.wffn-pairing-warning__separator {
					margin: 15px -12px;
					border: 1px solid #ffb900;
					border-bottom: none;
				}

				.wffn-pairing-warning__icon {
					margin-inline-end: 9px;
					margin-inline-start: 2px;
					color: #f56e28;
				}

				.wffn-pairing-warning__icon .dashicons {
					font-size: 17px;
					width: 17px;
					height: 17px;
					vertical-align: text-top;
				}

				.wffn-pairing-warning__title {
					font-weight: 600;
					margin-block-end: 10px;
				}

				.wffn-pairing-warning ~ p {
					display: none;
				}
			</style>
			<?php
		}

		/**
		 * Same markup core uses for its update row, so styling and the shiny
		 * (AJAX) update flow keep working.
		 *
		 * @param string $file        Plugin basename.
		 * @param array  $plugin_data Plugin headers.
		 *
		 * @return void
		 */
		public function render_update_row( $file, $plugin_data ) {
			/** The core update row is present instead; the block renders inside it. */
			$updates = get_site_transient( 'update_plugins' );
			if ( isset( $updates->response[ $file ] ) ) {
				return;
			}

			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => get_current_screen() ) );
			$active_class  = is_plugin_active( $file ) ? ' active' : '';
			?>
			<tr class="plugin-update-tr wffn-pro-pairing-tr<?php echo esc_attr( $active_class ); ?>" id="wffn-pro-pairing-update" data-plugin="<?php echo esc_attr( $file ); ?>">
				<td colspan="<?php echo esc_attr( $wp_list_table->get_column_count() ); ?>" class="plugin-update colspanchange">
					<div class="update-message notice inline notice-warning notice-alt">
						<?php $this->render_pairing_warning_block( false ); ?>
					</div>
				</td>
			</tr>
			<?php
		}

		/**
		 * Site-wide admin notice while Pro is outdated. Skipped on the FunnelKit
		 * app screens, where the full-page prompt already covers it.
		 *
		 * @return void
		 */
		public function maybe_render_sitewide_notice() {
			if ( ! $this->is_outdated() ) {
				return;
			}

			if ( class_exists( 'WFFN_Role_Capability' ) && ! WFFN_Role_Capability::get_instance()->user_access( 'menu', 'read' ) ) {
				return;
			}

			$admin = WFFN_Core()->admin;
			if ( is_object( $admin ) && method_exists( $admin, 'is_wffn_flex_page' ) && $admin->is_wffn_flex_page( 'all' ) ) {
				return;
			}
			?>
			<div class="notice notice-warning">
				<p>
					<strong>
						<?php
						printf( /* translators: %s: premium plugin name */
							esc_html__( 'Update %s', 'funnel-builder' ),
							esc_html( $this->get_companion_name() )
						);
						?>
					</strong>
				</p>
				<p>
					<?php
					printf( /* translators: 1: installed Funnel Builder version, 2: premium plugin name without brand prefix, 3: required version */
						esc_html__( 'Funnel Builder %1$s pairs with %2$s version %3$s or higher.', 'funnel-builder' ),
						esc_html( WFFN_VERSION ),
						esc_html( $this->get_companion_short_name() ),
						esc_html( WFFN_MIN_PRO_VERSION )
					);
					echo ' ';
					esc_html_e( 'Some Pro features may not work correctly until Pro is updated. Updating takes about a minute.', 'funnel-builder' );
					?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( self_admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' ) ); ?>"><?php esc_html_e( 'Update Now', 'funnel-builder' ); ?></a>
				</p>
			</div>
			<?php
		}

		/**
		 * Marks the FunnelKit toolbar menu while Pro is outdated: a badge on the
		 * top-level node and an update link as the only dropdown entry.
		 *
		 * @param WP_Admin_Bar $wp_admin_bar
		 *
		 * @return void
		 */
		public function maybe_add_admin_bar_state( WP_Admin_Bar $wp_admin_bar ) {
			if ( ! $this->is_outdated() ) {
				return;
			}

			$node = $wp_admin_bar->get_node( 'wffn_funnel' );
			if ( empty( $node ) ) {
				return;
			}

			$wp_admin_bar->add_node(
				array(
					'id'    => 'wffn_funnel',
					'title' => $node->title . '<span class="wffn-ab-update-badge" aria-label="' . esc_attr__( 'Update Required', 'funnel-builder' ) . '"><span class="wffn-ab-update-badge__dot"></span></span>',
					'href'  => self_admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' ),
				)
			);

			/** The admin app is replaced by the update prompt, so its routes lead nowhere. */
			$dead_parents = array( 'wffn_funnel' );
			do {
				$removed = false;
				foreach ( (array) $wp_admin_bar->get_nodes() as $sub_node ) {
					if ( 'wffn_funnel' !== $sub_node->id && in_array( $sub_node->parent, $dead_parents, true ) ) {
						$wp_admin_bar->remove_node( $sub_node->id );
						$dead_parents[] = $sub_node->id;
						$removed        = true;
					}
				}
			} while ( $removed );

			$wp_admin_bar->add_menu(
				array(
					'id'     => 'wffn_funnel_update_required',
					'parent' => 'wffn_funnel',
					'title'  => '<span class="wffn-ab-update-title">' . sprintf( /* translators: %s: premium plugin name without brand prefix */ esc_html__( 'Update %s', 'funnel-builder' ), esc_html( $this->get_companion_short_name() ) ) . '</span><span class="wffn-ab-update-sub">' . sprintf( /* translators: %s: required version ("+" stands for "or higher") */ esc_html__( 'Requires %s+', 'funnel-builder' ), esc_html( WFFN_MIN_PRO_VERSION ) ) . '</span>',
					'href'   => self_admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' ),
				)
			);
			?>
			<style type="text/css">
				#wp-admin-bar-wffn_funnel .wffn-ab-update-badge {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 16px;
					height: 16px;
					margin-left: 6px;
					border-radius: 50%;
					background: #f2c14b2e;
					vertical-align: middle;
				}

				#wp-admin-bar-wffn_funnel .wffn-ab-update-badge__dot {
					width: 8px;
					height: 8px;
					border-radius: 50%;
					background: #f2c14b;
				}

				#wpadminbar ul#wp-admin-bar-wffn_funnel-default li#wp-admin-bar-wffn_funnel_update_required a.ab-item {
					height: auto;
					padding-top: 4px;
					padding-bottom: 6px;
					line-height: 18px;
				}

				#wpadminbar ul#wp-admin-bar-wffn_funnel-default .wffn-ab-update-title {
					display: block;
					font-weight: 600;
					line-height: 18px;
					color: #e2b644;
				}

				#wpadminbar ul#wp-admin-bar-wffn_funnel-default .wffn-ab-update-sub {
					display: block;
					margin-top: 2px;
					line-height: 18px;
					color: #a7aaad;
				}
			</style>
			<?php
		}

		/**
		 * Renders the update prompt when Pro is outdated.
		 *
		 * @return bool True when the prompt was rendered and the app must not load.
		 */
		public function maybe_render() {
			if ( ! $this->is_outdated() ) {
				return false;
			}

			$this->render();

			return true;
		}

		/**
		 * Full page state shown instead of the admin app.
		 *
		 * @return void
		 */
		public function render() {
			$this->render_styles();

			if ( $this->is_license_expired() ) {
				$this->render_license_expired();
			} else {
				$this->render_update_prompt();
			}
		}

		/**
		 * Styles shared by both full-page states.
		 *
		 * @return void
		 */
		private function render_styles() {
			?>
			<style>
				/* Inline on purpose: must render even when an outdated Pro build's app bundle and noconflict pass are active. */
				.wffn-pro-update-required {
					display: flex !important;
					visibility: visible !important;
					justify-content: center;
					padding: 124px 24px;
				}

				.wffn-pro-update-required__card {
					box-sizing: border-box;
					width: 100%;
					max-width: 720px;
					background: #ffffff;
					border: 1px solid #e7e7e7;
					border-radius: 12px;
					padding: 32px;
				}

				.wffn-pro-update-required__card--wide {
					max-width: 860px;
				}

				.wffn-pro-update-required__header {
					text-align: center;
				}

				.wffn-pro-update-required__icon {
					display: inline-flex;
					flex-shrink: 0;
				}

				.wffn-pro-update-required__title {
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 10px;
					margin: 0 0 8px;
					font-size: 18px;
					line-height: 26px;
					font-weight: 500;
					color: #353030;
				}

				.wffn-pro-update-required__desc {
					margin: 0 auto;
					font-size: 14px;
					line-height: 22px;
					color: #353030;
				}

				.wffn-pro-update-required__versions {
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 32px;
					margin-top: 24px;
					padding: 24px 20px;
					border: 1px solid #e7e7e7;
					border-radius: 8px;
				}

				.wffn-pro-update-required__version {
					text-align: center;
				}

				.wffn-pro-update-required__version-num {
					display: block;
					font-family: Menlo, Consolas, monaco, monospace;
					font-size: 20px;
					line-height: 26px;
					letter-spacing: 0.04em;
					color: #82838e;
				}

				.wffn-pro-update-required__version-label {
					display: block;
					margin-top: 6px;
					font-size: 11px;
					line-height: 16px;
					letter-spacing: 0.08em;
					text-transform: uppercase;
					color: #82838e;
				}

				.wffn-pro-update-required__version.is-required .wffn-pro-update-required__version-num {
					font-weight: 600;
					color: #353030;
				}

				.wffn-pro-update-required__version.is-required .wffn-pro-update-required__version-label {
					color: #353030;
				}

				.wffn-pro-update-required__version-arrow {
					font-size: 18px;
					color: #353030;
				}

				.wffn-pro-update-required__note {
					margin: 20px 0 0;
					font-size: 14px;
					line-height: 22px;
					text-align: center;
					color: #353030;
				}

				.wffn-pro-update-required__actions {
					display: flex;
					flex-wrap: wrap;
					align-items: center;
					justify-content: center;
					gap: 16px;
					margin-top: 24px;
				}

				.wffn-pro-update-required__actions a {
					white-space: nowrap;
				}

				.wffn-pro-update-required__button {
					display: inline-block;
					padding: 10px 20px;
					background: #0073aa;
					border-radius: 8px;
					font-size: 14px;
					line-height: 20px;
					font-weight: 500;
					color: #ffffff;
					text-decoration: none;
				}

				.wffn-pro-update-required__button:hover,
				.wffn-pro-update-required__button:focus {
					background: #00618f;
					color: #ffffff;
					border-radius: 8px;
				}

				.wffn-pro-update-required__button--secondary {
					background: #ffffff;
					border: 1px solid #0073aa;
					padding: 9px 19px;
					color: #0073aa;
				}

				.wffn-pro-update-required__button--secondary:hover,
				.wffn-pro-update-required__button--secondary:focus {
					background: #f0f6f9;
					color: #00618f;
					border-color: #00618f;
				}

				.wffn-pro-update-required__support {
					font-size: 14px;
					line-height: 20px;
					font-weight: 500;
					color: #0073aa;
					text-decoration: none;
				}

				.wffn-pro-update-required__support:hover,
				.wffn-pro-update-required__support:focus {
					color: #00618f;
					text-decoration: underline;
				}
			</style>
			<?php
		}

		/**
		 * Card icon shared by both full-page states.
		 *
		 * @return void
		 */
		private function render_title_icon() {
			?>
			<span class="wffn-pro-update-required__icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 4 2.7 20h18.6L12 4Z" stroke="#ecc45c" stroke-width="1.6" stroke-linejoin="round" />
					<path d="M12 10v4.5" stroke="#ecc45c" stroke-width="1.6" stroke-linecap="round" />
					<circle cx="12" cy="17.2" r="0.9" fill="#ecc45c" />
				</svg>
			</span>
			<?php
		}

		/**
		 * "Update Pro" state: Pro is outdated but a compatible build is available.
		 *
		 * @return void
		 */
		private function render_update_prompt() {
			$update_url  = self_admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' );
			$support_url = 'https://funnelkit.com/support/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Pro+Update+Required';
			?>
			<div class="wffn-pro-update-required">
				<div class="wffn-pro-update-required__card">
					<div class="wffn-pro-update-required__header">
						<h1 class="wffn-pro-update-required__title">
							<?php $this->render_title_icon(); ?>
							<?php
							printf( /* translators: %s: premium plugin name */
								esc_html__( 'Update %s', 'funnel-builder' ),
								esc_html( $this->get_companion_name() )
							);
							?>
						</h1>
						<p class="wffn-pro-update-required__desc">
							<?php
							printf( /* translators: 1: installed Funnel Builder version, 2: premium plugin name without brand prefix, 3: required version */
								esc_html__( 'Funnel Builder %1$s pairs with %2$s version %3$s or higher.', 'funnel-builder' ),
								esc_html( WFFN_VERSION ),
								esc_html( $this->get_companion_short_name() ),
								esc_html( WFFN_MIN_PRO_VERSION )
							);
							?>
						</p>
					</div>
					<div class="wffn-pro-update-required__versions">
						<div class="wffn-pro-update-required__version">
							<span class="wffn-pro-update-required__version-num"><?php echo esc_html( WFFN_PRO_VERSION ); ?></span>
							<span class="wffn-pro-update-required__version-label"><?php esc_html_e( 'Installed', 'funnel-builder' ); ?></span>
						</div>
						<span class="wffn-pro-update-required__version-arrow" aria-hidden="true">&rarr;</span>
						<div class="wffn-pro-update-required__version is-required">
							<span class="wffn-pro-update-required__version-num"><abbr title="<?php echo esc_attr( sprintf( /* translators: %s: required version */ __( '%s or higher', 'funnel-builder' ), WFFN_MIN_PRO_VERSION ) ); ?>" style="text-decoration: none;"><?php echo esc_html( WFFN_MIN_PRO_VERSION ); ?>+</abbr></span>
							<span class="wffn-pro-update-required__version-label"><?php esc_html_e( 'Required', 'funnel-builder' ); ?></span>
						</div>
					</div>
					<p class="wffn-pro-update-required__note">
						<?php esc_html_e( 'Some Pro features may not work correctly until Pro is updated. Updating takes about a minute.', 'funnel-builder' ); ?>
					</p>
					<div class="wffn-pro-update-required__actions">
						<a class="wffn-pro-update-required__button" href="<?php echo esc_url( $update_url ); ?>"><?php esc_html_e( 'Update Now', 'funnel-builder' ); ?></a>
						<a class="wffn-pro-update-required__support" href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Support', 'funnel-builder' ); ?></a>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * "License expired" state: Pro can't be updated without renewing.
		 *
		 * @return void
		 */
		private function render_license_expired() {
			$rollback_url   = $this->get_rollback_url();
			$renew_url      = 'https://funnelkit.com/exclusive-offer/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Pro+License+Expired';
			$deactivate_url = '';
			if ( defined( 'WFFN_PRO_FILE' ) ) {
				$pro_basename   = plugin_basename( WFFN_PRO_FILE );
				$deactivate_url = wp_nonce_url( self_admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $pro_basename ) ), 'deactivate-plugin_' . $pro_basename );
			}
			?>
			<div class="wffn-pro-update-required">
				<div class="wffn-pro-update-required__card wffn-pro-update-required__card--wide">
					<div class="wffn-pro-update-required__header">
						<h1 class="wffn-pro-update-required__title">
							<?php $this->render_title_icon(); ?>
							<?php
							printf( /* translators: 1: installed Funnel Builder version, 2: premium plugin name without brand prefix, 3: installed premium plugin version */
								esc_html__( 'Oops! Funnel Builder %1$s is not compatible with %2$s %3$s', 'funnel-builder' ),
								esc_html( WFFN_VERSION ),
								esc_html( $this->get_companion_short_name() ),
								esc_html( WFFN_PRO_VERSION )
							);
							?>
						</h1>
						<p class="wffn-pro-update-required__desc">
							<?php esc_html_e( 'Due to some technical changes in how admin application is loaded, the free version has fallen out of sync with the premium version. Consider updating the premium version or rolling back the free version.', 'funnel-builder' ); ?>
						</p>
					</div>
					<div class="wffn-pro-update-required__actions">
						<a class="wffn-pro-update-required__button" href="<?php echo esc_url( $rollback_url ); ?>"><?php esc_html_e( 'Roll Back Funnel Builder', 'funnel-builder' ); ?></a>
						<a class="wffn-pro-update-required__button wffn-pro-update-required__button--secondary" href="<?php echo esc_url( $renew_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade Funnel Builder Pro', 'funnel-builder' ); ?></a>
						<?php if ( '' !== $deactivate_url ) { ?>
							<a class="wffn-pro-update-required__support" href="<?php echo esc_url( $deactivate_url ); ?>"><?php printf( /* translators: %s: premium plugin name without brand prefix */ esc_html__( 'Deactivate %s', 'funnel-builder' ), esc_html( $this->get_companion_short_name() ) ); ?></a>
						<?php } ?>
					</div>
				</div>
			</div>
			<?php
		}
	}

	WFFN_Pro_Update_Required::get_instance();
}
