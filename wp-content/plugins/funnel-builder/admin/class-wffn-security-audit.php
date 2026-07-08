<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Security_Audit' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_Security_Audit {

		const VERSION_OPTION = 'wffn_security_audit_version';
		const REPORT_OPTION  = 'wffn_security_audit_report';
		const LOG_FILE       = 'wffn-security-audit';
		const LOG_FOLDER     = 'funnelkit';
		const PAGE_SLUG      = 'wffn-security-report';
		const CLEANUP_NONCE  = 'wffn_security_cleanup';

		public static function init() {
			try {
				add_action( 'admin_init', array( __CLASS__, 'handle_cleanup_request' ), 2 );
				add_action( 'admin_menu', array( __CLASS__, 'register_report_page' ) );
				add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		// -------------------------------------------------------------------------
		// Audit
		// -------------------------------------------------------------------------

		public static function run_audit() {
			try {
				if ( class_exists( 'BWF_Logger' ) ) {
					BWF_Logger::get_instance()->log( 'run_audit() started', self::LOG_FILE, self::LOG_FOLDER, true );
				}
				$findings = array();

				// 1. Checkout global settings
				$global = get_option( '_wfacp_global_settings', array() );
				if ( is_array( $global ) ) {
					foreach ( array(
						'wfacp_checkout_global_css'    => 'css',
						'wfacp_global_external_script' => 'script',
					) as $field => $type ) {
						self::check_field(
							$findings,
							$global[ $field ] ?? '',
							$type,
							'Checkout Global Settings',
							$field,
							array(
								'type'  => 'option',
								'name'  => '_wfacp_global_settings',
								'field' => $field,
							)
						);
					}
				}

				// 2. Checkout per-page settings
				$checkout_ids = get_posts(
					array(
						'post_type'   => 'wfacp_checkout',
						'post_status' => 'any',
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				);
				foreach ( $checkout_ids as $post_id ) {
					$meta = get_post_meta( $post_id, '_wfacp_page_settings', true );
					if ( ! is_array( $meta ) ) {
						continue;
					}
					foreach ( array( 'header_script', 'footer_script' ) as $field ) {
						self::check_field(
							$findings,
							$meta[ $field ] ?? '',
							'script',
							sprintf( 'Checkout Page #%d', $post_id ),
							$field,
							array(
								'type'     => 'postmeta',
								'post_id'  => $post_id,
								'meta_key' => '_wfacp_page_settings',
								'field'    => $field,
							)
						);
					}
				}

				// 3. One-click upsell global settings
				$upsell = get_option( 'wfocu_global_settings', array() );
				if ( is_array( $upsell ) ) {
					foreach ( array( 'scripts', 'scripts_head' ) as $field ) {
						self::check_field(
							$findings,
							$upsell[ $field ] ?? '',
							'script',
							'One-Click Upsell Global Settings',
							$field,
							array(
								'type'  => 'option',
								'name'  => 'wfocu_global_settings',
								'field' => $field,
							)
						);
					}
				}

				// 4. Landing page settings
				$lp = get_option( 'wffn_lp_settings', array() );
				if ( is_array( $lp ) ) {
					foreach ( array(
						'css'    => 'css',
						'script' => 'script',
					) as $field => $type ) {
						self::check_field(
							$findings,
							$lp[ $field ] ?? '',
							$type,
							'Landing Page Global Settings',
							$field,
							array(
								'type'  => 'option',
								'name'  => 'wffn_lp_settings',
								'field' => $field,
							)
						);
					}
				}

				// 5. Optin page settings
				$op = get_option( 'wffn_op_settings', array() );
				if ( is_array( $op ) ) {
					foreach ( array(
						'css'    => 'css',
						'script' => 'script',
					) as $field => $type ) {
						self::check_field(
							$findings,
							$op[ $field ] ?? '',
							$type,
							'Optin Page Global Settings',
							$field,
							array(
								'type'  => 'option',
								'name'  => 'wffn_op_settings',
								'field' => $field,
							)
						);
					}
				}

				// 6. Thank-you page settings
				$tp = get_option( 'wffn_tp_settings', array() );
				if ( is_array( $tp ) ) {
					foreach ( array(
						'css'    => 'css',
						'script' => 'script',
					) as $field => $type ) {
						self::check_field(
							$findings,
							$tp[ $field ] ?? '',
							$type,
							'Thank-You Page Global Settings',
							$field,
							array(
								'type'  => 'option',
								'name'  => 'wffn_tp_settings',
								'field' => $field,
							)
						);
					}
				}

				if ( empty( $findings ) ) {
					if ( class_exists( 'BWF_Logger' ) ) {
						BWF_Logger::get_instance()->log( 'run_audit() complete — no malicious findings detected', self::LOG_FILE, self::LOG_FOLDER, true );
					}
					return;
				}
				if ( class_exists( 'BWF_Logger' ) ) {
					BWF_Logger::get_instance()->log( 'run_audit() found ' . count( $findings ) . ' compromised field(s)', self::LOG_FILE, self::LOG_FOLDER, true );
				}

				$report = array(
					'timestamp'         => time(),
					'findings'          => $findings,
					'cleanup_performed' => false,
					'cleanup_time'      => null,
				);
				update_option( self::REPORT_OPTION, $report, true );

				if ( class_exists( 'BWF_Logger' ) ) {
					$logger = BWF_Logger::get_instance();
					$logger->log(
						sprintf( 'Security audit detected %d compromised field(s). Cleanup pending.', count( $findings ) ),
						self::LOG_FILE,
						self::LOG_FOLDER,
						true
					);
					foreach ( $findings as $f ) {
						$logger->log(
							sprintf(
								'Context: %s | Field: %s | Violated rules: %s | Snippet: %s',
								$f['context'],
								$f['field'],
								implode( ', ', $f['patterns'] ),
								substr( $f['original'], 0, 200 )
							),
							self::LOG_FILE,
							self::LOG_FOLDER,
							true
						);
					}
				}

				// Auto-cleanup when Pro is active; otherwise leave for manual button.
				if ( defined( 'WFFN_PRO_VERSION' ) ) {
					self::perform_cleanup();
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		private static function check_field( array &$findings, $value, $type, $context, $field, array $source ) {
			try {
				if ( empty( $value ) ) {
					return;
				}
				$sanitized = 'css' === $type ? WFFN_Common::sanitize_global_css( $value ) : WFFN_Common::sanitize_global_script( $value );
				if ( $sanitized === $value ) {
					return;
				}
				$findings[] = array(
					'context'  => $context,
					'field'    => $field,
					'original' => $value,
					'patterns' => 'css' === $type ? self::detect_css_violations( $value ) : self::detect_script_violations( $value ),
					'source'   => $source,
				);
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		// -------------------------------------------------------------------------
		// Cleanup (called by Pro auto-hook or Lite manual button)
		// -------------------------------------------------------------------------

		public static function perform_cleanup() {
			try {
				$report = get_option( self::REPORT_OPTION );
				if ( empty( $report['findings'] ) ) {
					return;
				}

				$option_cache = array();
				foreach ( $report['findings'] as $f ) {
					$src = $f['source'];
					if ( 'option' === $src['type'] ) {
						if ( ! isset( $option_cache[ $src['name'] ] ) ) {
							$option_cache[ $src['name'] ] = get_option( $src['name'], array() );
						}
						$option_cache[ $src['name'] ][ $src['field'] ] = '';
					} elseif ( 'postmeta' === $src['type'] ) {
						$meta = get_post_meta( $src['post_id'], $src['meta_key'], true );
						if ( is_array( $meta ) ) {
							$meta[ $src['field'] ] = '';
							update_post_meta( $src['post_id'], $src['meta_key'], $meta );
						}
					}
				}

				foreach ( $option_cache as $option_name => $data ) {
					update_option( $option_name, $data );
				}

				$report['cleanup_performed'] = true;
				$report['cleanup_time']      = time();
				update_option( self::REPORT_OPTION, $report, true );

				if ( class_exists( 'BWF_Logger' ) ) {
					BWF_Logger::get_instance()->log(
						sprintf( 'Security cleanup completed. %d field(s) zeroed out.', count( $report['findings'] ) ),
						self::LOG_FILE,
						self::LOG_FOLDER,
						true
					);
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		public static function handle_cleanup_request() {
			try {
				if ( ! isset( $_POST['wffn_security_cleanup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					return;
				}
				check_admin_referer( self::CLEANUP_NONCE );
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				// Capture timestamp before cleanup to build the notification key.
				$report = get_option( self::REPORT_OPTION );

				self::perform_cleanup();

				// Auto-dismiss the FunnelKit Vue notification for the user who performed cleanup.
				if ( ! empty( $report['timestamp'] ) ) {
					$notice_key = 'wffn_security_audit_' . (int) $report['timestamp'];
					$user_id    = get_current_user_id();
					$dismissed  = get_user_meta( $user_id, '_bwf_notifications_close', true );
					$dismissed  = is_array( $dismissed ) ? $dismissed : array();
					if ( ! in_array( $notice_key, $dismissed, true ) ) {
						$dismissed[] = $notice_key;
						update_user_meta( $user_id, '_bwf_notifications_close', $dismissed ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta
					}
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => self::PAGE_SLUG,
							'cleaned' => 1,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		// -------------------------------------------------------------------------
		// Standard WP admin notice (shows on all admin pages when cleanup is pending)
		// -------------------------------------------------------------------------

		public static function render_admin_notice() {
			try {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				$report = get_option( self::REPORT_OPTION );
				if ( empty( $report['findings'] ) ) {
					return;
				}

				$count      = count( $report['findings'] );
				$report_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
				$cleaned    = ! empty( $report['cleanup_performed'] );

				if ( ! $cleaned ) {
					// Cleanup is still pending — non-dismissible error notice (Lite flow).
					?>
					<div class="notice notice-error">
						<p>
							<strong><?php esc_html_e( 'FunnelKit Security Alert:', 'funnel-builder' ); ?></strong>
							<?php
							printf(
								esc_html(
									_n(
										'A security scan detected %d malicious script in your FunnelKit settings.',
										'A security scan detected %d malicious scripts in your FunnelKit settings.',
										$count,
										'funnel-builder'
									)
								),
								(int) $count
							);
							?>
							<a href="<?php echo esc_url( $report_url ); ?>" class="button button-small" style="margin-left:10px;">
								<?php esc_html_e( 'View Report &amp; Cleanup', 'funnel-builder' ); ?>
							</a>
						</p>
					</div>
					<?php
				} else {
					// Cleanup was performed automatically (Pro flow) — dismissible warning notice.
					$notice_key = 'wffn_security_audit_' . (int) $report['timestamp'];
					$user_id    = get_current_user_id();
					$dismissed  = get_user_meta( $user_id, '_bwf_notifications_close', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
					$dismissed  = is_array( $dismissed ) ? $dismissed : array();
					if ( in_array( $notice_key, $dismissed, true ) ) {
						return;
					}
					$current_page = isset( $_SERVER['REQUEST_URI'] ) ? basename( wffn_clean( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$dismiss_url  = admin_url(
						'admin-ajax.php?action=wffn_dismiss_notice&nkey=' . rawurlencode( $notice_key )
						. '&nonce=' . wp_create_nonce( 'wp_wffn_dismiss_notice' )
						. '&redirect=' . rawurlencode( $current_page )
					);
					?>
					<div class="notice notice-warning" style="position:relative;padding-right:40px;">
						<p>
							<strong><?php esc_html_e( 'FunnelKit Security Alert:', 'funnel-builder' ); ?></strong>
							<?php
							printf(
								esc_html(
									_n(
										'A security scan detected and automatically removed %d malicious script from your FunnelKit settings.',
										'A security scan detected and automatically removed %d malicious scripts from your FunnelKit settings.',
										$count,
										'funnel-builder'
									)
								),
								(int) $count
							);
							?>
							<a href="<?php echo esc_url( $report_url ); ?>" class="button button-small" style="margin-left:10px;">
								<?php esc_html_e( 'View Report', 'funnel-builder' ); ?>
							</a>
						</p>
						<a href="<?php echo esc_url( $dismiss_url ); ?>" style="position:absolute;top:10px;right:15px;text-decoration:none;color:#787c82;" title="<?php esc_attr_e( 'Dismiss this notice', 'funnel-builder' ); ?>">&#x2715;</a>
					</div>
					<?php
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		// -------------------------------------------------------------------------
		// Notification (called from WFFN_Admin_Notifications::prepare_notifications)
		// -------------------------------------------------------------------------

		public static function get_notification_entry() {
			try {
				$report = get_option( self::REPORT_OPTION );
				if ( empty( $report['findings'] ) ) {
					return null;
				}

				$count      = count( $report['findings'] );
				$report_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
				$notice_key = 'wffn_security_audit_' . (int) $report['timestamp'];
				$cleaned    = ! empty( $report['cleanup_performed'] );

				if ( $cleaned ) {
					$message = sprintf(
						_n(
							'A security scan detected and automatically removed %d malicious script from your FunnelKit settings.',
							'A security scan detected and automatically removed %d malicious scripts from your FunnelKit settings.',
							$count,
							'funnel-builder'
						),
						$count
					);
				} else {
					$message = sprintf(
						_n(
							'A security scan detected %d malicious script in your FunnelKit settings. Cleanup is required.',
							'A security scan detected %d malicious scripts in your FunnelKit settings. Cleanup is required.',
							$count,
							'funnel-builder'
						),
						$count
					);
				}

				return array(
					'key'             => $notice_key,
					'content'         => '<div class="bwf-notifications-message current">'
						. '<h3 class="bwf-notifications-title">' . esc_html__( 'FunnelKit Security Alert', 'funnel-builder' ) . '</h3>'
						. '<p class="bwf-notifications-content">' . esc_html( $message ) . '</p>'
						. '</div>',
					'customButtons'   => array(
						array(
							'label'     => __( 'View Report', 'funnel-builder' ),
							'href'      => $report_url,
							'className' => 'is-primary',
							'reload'    => true,
						),
					),
					'not_dismissible' => ! $cleaned,
					'index'           => 0,
				);
			} catch ( \Throwable $e ) {
				return null;
			}
		}

		// -------------------------------------------------------------------------
		// Report page
		// -------------------------------------------------------------------------

		public static function register_report_page() {
			try {
				add_submenu_page(
					null,
					__( 'FunnelKit Security Audit Report', 'funnel-builder' ),
					__( 'Security Audit Report', 'funnel-builder' ),
					'manage_options',
					self::PAGE_SLUG,
					array( __CLASS__, 'render_report_page' )
				);
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		public static function render_report_page() {
			try {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to view this page.', 'funnel-builder' ) );
				}

				$report       = get_option( self::REPORT_OPTION );
				$cleaned      = ! empty( $report['cleanup_performed'] );
				$pro_active   = defined( 'WFFN_PRO_VERSION' );
				$just_cleaned = isset( $_GET['cleaned'] ) && '1' === $_GET['cleaned']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'FunnelKit Security Audit Report', 'funnel-builder' ); ?></h1>

					<?php if ( $just_cleaned ) : ?>
						<div class="notice notice-success inline"><p><?php esc_html_e( 'Cleanup completed. All compromised fields have been cleared.', 'funnel-builder' ); ?></p></div>
					<?php endif; ?>

					<?php if ( empty( $report['findings'] ) ) : ?>
						<p><?php esc_html_e( 'No security issues were found during the last audit.', 'funnel-builder' ); ?></p>
					<?php else : ?>
						<p>
							<?php
							printf(
								/* translators: 1: formatted date/time 2: count */
								esc_html__( 'Audit run on %1$s. Found %2$d compromised field(s).', 'funnel-builder' ),
								esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report['timestamp'] ) ),
								(int) count( $report['findings'] )
							);
							if ( $cleaned && $report['cleanup_time'] ) {
								echo ' &mdash; ';
								printf(
									/* translators: %s: formatted cleanup date/time */
									esc_html__( 'Cleaned on %s.', 'funnel-builder' ),
									esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $report['cleanup_time'] ) )
								);
							}
							?>
						</p>

						<?php if ( ! $cleaned ) : ?>
							<?php if ( $pro_active ) : ?>
								<p class="description"><?php esc_html_e( 'Pro auto-cleanup is active — compromised fields will be zeroed out automatically on the next audit run.', 'funnel-builder' ); ?></p>
							<?php else : ?>
								<form method="post" style="margin-bottom:20px;">
									<?php wp_nonce_field( self::CLEANUP_NONCE ); ?>
									<input type="hidden" name="wffn_security_cleanup" value="1">
									<button type="submit" class="button button-primary button-large">
										<?php esc_html_e( 'Perform Cleanup', 'funnel-builder' ); ?>
									</button>
									<span class="description" style="margin-left:10px;">
										<?php esc_html_e( 'This will zero out all compromised fields listed below.', 'funnel-builder' ); ?>
									</span>
								</form>
							<?php endif; ?>
						<?php endif; ?>

						<table class="widefat striped" style="max-width:1200px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Context', 'funnel-builder' ); ?></th>
									<th><?php esc_html_e( 'Field', 'funnel-builder' ); ?></th>
									<th><?php esc_html_e( 'Violated Rule(s)', 'funnel-builder' ); ?></th>
									<th><?php esc_html_e( 'Original Value (truncated)', 'funnel-builder' ); ?></th>
									<th><?php esc_html_e( 'Status', 'funnel-builder' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $report['findings'] as $finding ) : ?>
									<tr>
										<td><?php echo esc_html( $finding['context'] ); ?></td>
										<td><code><?php echo esc_html( $finding['field'] ); ?></code></td>
										<td>
											<?php if ( ! empty( $finding['patterns'] ) ) : ?>
												<ul style="margin:0;padding:0 0 0 1.2em;">
													<?php foreach ( $finding['patterns'] as $p ) : ?>
														<li><?php echo esc_html( $p ); ?></li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<?php esc_html_e( '(pattern stripped by sanitizer)', 'funnel-builder' ); ?>
											<?php endif; ?>
										</td>
										<td>
											<details>
												<summary style="cursor:pointer;"><?php esc_html_e( 'Show snippet', 'funnel-builder' ); ?></summary>
												<pre style="white-space:pre-wrap;word-break:break-all;max-width:420px;font-size:11px;background:#f6f7f7;padding:6px;margin-top:4px;"><?php echo esc_html( substr( $finding['original'], 0, 500 ) ); ?></pre>
											</details>
										</td>
										<td>
											<?php if ( $cleaned ) : ?>
												<span style="color:green;">&#10003; <?php esc_html_e( 'Cleared', 'funnel-builder' ); ?></span>
											<?php else : ?>
												<span style="color:#c00;">&#9888; <?php esc_html_e( 'Pending cleanup', 'funnel-builder' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				<?php
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		// -------------------------------------------------------------------------
		// Pattern detection (audit-specific; sanitization is via WFFN_Common)
		// -------------------------------------------------------------------------

		private static function detect_css_violations( $css ) {
			try {
				$violations = array();
				foreach ( array(
					'CSS expression() execution' => '/expression\s*\(/i',
					'javascript: protocol'       => '/javascript\s*:/i',
					'IE behavior: property'      => '/behavior\s*:/i',
					'vbscript: protocol'         => '/vbscript\s*:/i',
					'-moz-binding: property'     => '/-moz-binding\s*:/i',
					'remote @import URL'         => '/@import\s+(?:url\s*\(?\s*)?["\']?https?:\/\//i',
				) as $name => $regex ) {
					if ( preg_match( $regex, $css ) ) {
						$violations[] = $name;
					}
				}
				return $violations;
			} catch ( \Throwable $e ) {
				return array();
			}
		}

		private static function detect_script_violations( $script ) {
			try {
				$violations = array();
				foreach ( array(
					'base64 decode+eval (eval/Function(atob()))' => '#\b(?:eval|Function)\s*\(\s*atob\s*\(#i',
					'base64 data: URI for JavaScript' => '#data:\s*(?:text|application)/(?:javascript|ecmascript|x-javascript)[^,]*;\s*base64\s*,[A-Za-z0-9+/=]*#i',
					'hex escape obfuscation (\\xNN sequences, 5+)' => '#(?:\\\\x[0-9a-f]{2}){5,}#i',
					'String.fromCharCode() with 10+ arguments' => '#String\.fromCharCode\s*\((?:\s*\d{1,3}\s*,\s*){10,}[^)]*\)#i',
					"split-string eval evasion (window['ev'+'al'])" => "#\\b(?:window|top|self|globalThis)\\s*\\[\\s*['\"][a-z]{1,4}['\"]\\s*\\+\\s*['\"][a-z]{1,4}['\"]#i",
				) as $name => $regex ) {
					if ( preg_match( $regex, $script ) ) {
						$violations[] = $name;
					}
				}
				if (
					preg_match( '#\batob\s*\(#i', $script ) &&
					preg_match( '#\bcreateElement\s*\(#i', $script ) &&
					preg_match( '#\.\s*src\s*=#i', $script ) &&
					preg_match( '#\.\s*(?:appendChild|append|insertBefore)\s*\(#i', $script )
				) {
					$violations[] = 'dynamic script injection scaffold (atob + createElement + .src= + appendChild)';
				}
				return $violations;
			} catch ( \Throwable $e ) {
				return array();
			}
		}
	}
}
