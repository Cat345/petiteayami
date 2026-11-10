<?php
/**
 * Consent page of the FunnelKit AI connect flow (the gateway sends the merchant here).
 * $data: store_host, user_name, user_email, user_role, account_email (the FunnelKit AI account), blocked,
 * handle, nonce, consent_url, logout_url. A non-empty `blocked` replaces the buttons and
 * there is no form. No JavaScript; inline CSS only (the page's CSP allows nothing else).
 * Same card as the gateway's pages, so both sides read as one flow.
 */

defined( 'ABSPATH' ) || exit;

$fkai_blocked = (string) $data['blocked'];
$fkai_initial = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( trim( (string) $data['user_name'] ), 0, 1 ) ) : strtoupper( substr( trim( (string) $data['user_name'] ), 0, 1 ) );
$fkai_lock    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5f5f6b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
$fkai_eye     = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0073aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
$fkai_bolt    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0073aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>';
$fkai_pencil  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0073aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php esc_html_e( 'Connect FunnelKit MCP to Your Site', 'wp-marketing-automations' ); ?></title>
<style>
	*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
	body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #f0f2fa; color: #353030; min-height: 100vh; padding: 48px 16px; font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
	a { color: #0073aa; } a:hover { color: #005a87; }
	a:focus-visible { outline: 3px solid #d6e9f3; outline-offset: 1px; border-radius: 2px; }
	main { width: 100%; max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 24px; }
	.logo { display: block; height: 26px; width: auto; }
	.card { width: 100%; background: #fff; border: 1px solid #dddeeb; border-radius: 12px; padding: 32px; display: flex; flex-direction: column; gap: 24px; }
	.small { font-size: 13px; color: #5f5f6b; overflow-wrap: anywhere; }
	.head { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
	h1 { font-size: 20px; font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; }
	.store { display: flex; align-items: center; justify-content: center; gap: 6px; } .store svg { flex: 0 0 14px; }
	.identity { display: flex; flex-direction: column; gap: 8px; }
	.account { display: flex; align-items: center; gap: 12px; border: 1px solid #dddeeb; border-radius: 8px; padding: 12px 14px; }
	.avatar { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; background: #ebf2f6; color: #0073aa; font-size: 15px; font-weight: 600; display: flex; align-items: center; justify-content: center; }
	.account-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
	.account-name { font-weight: 500; overflow-wrap: anywhere; }
	.switch { font-size: 13px; white-space: nowrap; }
	.perms { display: flex; flex-direction: column; gap: 12px; }
	.perms-title { font-weight: 600; overflow-wrap: anywhere; }
	.perms ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }
	.perms li { display: flex; align-items: flex-start; gap: 10px; } .perms li svg { flex: 0 0 18px; margin-top: 1px; }
	.perms strong { font-weight: 600; }
	.blocked { color: #353030; font-weight: 500; }
	.actions { display: flex; flex-direction: column; gap: 12px; }
	.btn { display: block; width: 100%; height: 44px; padding: 0 16px; font: inherit; font-size: 15px; font-weight: 500; border-radius: 8px; border: 1px solid transparent; cursor: pointer; }
	.btn:focus-visible { outline: none; border-color: #0073aa; box-shadow: 0 0 0 3px #d6e9f3; }
	.btn-allow { background: #0073aa; color: #fff; } .btn-allow:hover { background: #005a87; }
	.btn-deny { background: #fff; border-color: #dddeeb; color: #353030; } .btn-deny:hover { background: #f7f8fc; }
	.foot { text-align: center; }
	.terms { font-size: 12px; color: #5f5f6b; text-align: center; } .terms a, .terms a:hover { color: #5f5f6b; }
	@media (max-width: 540px) { body { padding: 24px 16px; } .card { padding: 24px 20px; } }
</style>
</head>
<body>
<main>
	<?php echo file_get_contents( __DIR__ . '/logo.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- static artwork shipped with the plugin. ?>
	<div class="card">
		<div class="head">
			<h1><?php esc_html_e( 'Connect FunnelKit MCP to Your Site', 'wp-marketing-automations' ); ?></h1>
			<p class="small store"><?php echo $fkai_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php echo esc_html( $data['store_host'] ); ?></span></p>
		</div>

		<div class="identity">
			<div class="account">
				<div class="avatar" aria-hidden="true"><?php echo esc_html( '' !== $fkai_initial ? $fkai_initial : '?' ); ?></div>
				<div class="account-text">
					<span class="account-name"><?php echo esc_html( $data['user_name'] ); ?><?php if ( '' !== (string) $data['user_role'] ) : ?> · <?php echo esc_html( $data['user_role'] ); ?><?php endif; ?></span>
					<?php if ( '' !== (string) $data['user_email'] ) : ?>
						<span class="small"><?php echo esc_html( $data['user_email'] ); ?></span>
					<?php endif; ?>
				</div>
				<a class="switch" href="<?php echo esc_url( $data['logout_url'] ); ?>"><?php echo esc_html_x( 'Switch', 'consent page: sign in as another user', 'wp-marketing-automations' ); ?></a>
			</div>
			<?php if ( '' !== (string) $data['account_email'] ) : ?>
				<p class="small">
					<?php
					/* translators: %s: email of the FunnelKit AI account the site is connecting to */
					echo esc_html( sprintf( __( 'Connecting to FunnelKit AI account: %s', 'wp-marketing-automations' ), $data['account_email'] ) );
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $fkai_blocked ) : ?>
			<p class="blocked"><?php echo esc_html( $fkai_blocked ); ?></p>
		<?php else : ?>
			<div class="perms">
				<p class="perms-title"><?php esc_html_e( 'FunnelKit will be able to', 'wp-marketing-automations' ); ?></p>
				<ul>
					<li><?php echo $fkai_bolt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Invoke WordPress abilities', 'wp-marketing-automations' ); ?></span></li>
					<li><?php echo $fkai_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'View your funnels, automations and their results', 'wp-marketing-automations' ); ?></span></li>
					<li><?php echo $fkai_pencil; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Make changes on behalf of your AI agent', 'wp-marketing-automations' ); ?></span></li>
				</ul>
			</div>

			<form method="post" action="<?php echo esc_url( $data['consent_url'] ); ?>" class="actions">
				<input type="hidden" name="handle" value="<?php echo esc_attr( $data['handle'] ); ?>">
				<input type="hidden" name="_fkai_nonce" value="<?php echo esc_attr( $data['nonce'] ); ?>">
				<button type="submit" name="decision" value="allow" class="btn btn-allow"><?php echo esc_html_x( 'Allow', 'consent page: approve the AI connection', 'wp-marketing-automations' ); ?></button>
				<button type="submit" name="decision" value="deny" class="btn btn-deny"><?php esc_html_e( "Don't allow", 'wp-marketing-automations' ); ?></button>
			</form>
		<?php endif; ?>

		<p class="small foot"><?php esc_html_e( 'You can disconnect anytime in FunnelKit AI settings.', 'wp-marketing-automations' ); ?></p>
	</div>
	<p class="terms"><?php
		echo wp_kses(
			sprintf(
				/* translators: 1: "Terms of Service" link, 2: "Privacy Policy" link */
				__( 'By continuing, you agree to FunnelKit\'s %1$s and %2$s.', 'wp-marketing-automations' ),
				'<a href="https://funnelkit.com/terms-conditions/" target="_blank" rel="noopener">' . esc_html__( 'Terms of Service', 'wp-marketing-automations' ) . '</a>',
				'<a href="https://funnelkit.com/privacy-policy/" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'wp-marketing-automations' ) . '</a>'
			),
			array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
		);
	?></p>
</main>
</body>
</html>
