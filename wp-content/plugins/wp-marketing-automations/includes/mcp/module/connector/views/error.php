<?php
/** A plain error page for authorize failures that must not redirect. $data: message. */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php esc_html_e( 'FunnelKit AI', 'wp-marketing-automations' ); ?></title>
<style>
	*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
	body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #F1F2F9; color: #353030; min-height: 100vh; padding: 56px 20px; }
	main { width: 100%; max-width: 440px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; }
	.logo { display: block; height: 26px; width: auto; margin-bottom: 22px; }
	.card { width: 100%; background: #fff; border: 1px solid #DEDFEA; border-radius: 12px; padding: 32px 32px 28px; }
	h1 { font-size: 20px; font-weight: 500; line-height: 1.35; text-align: center; }
	.lead { margin-top: 10px; font-size: 13.5px; line-height: 1.5; color: #82828E; text-align: center; }
	.note { margin-top: 18px; font-size: 12.5px; color: #82828E; text-align: center; }
	@media (max-width: 480px) { body { padding: 28px 16px; } .card { padding: 24px 20px 22px; } h1 { font-size: 18px; } }
</style>
</head>
<body>
<main>
	<?php echo file_get_contents( __DIR__ . '/logo.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- static artwork shipped with the plugin. ?>
	<div class="card">
		<h1><?php esc_html_e( "FunnelKit AI can't continue", 'wp-marketing-automations' ); ?></h1>
		<p class="lead"><?php echo esc_html( $data['message'] ); ?></p>
		<p class="note"><?php esc_html_e( 'You can close this window and start again from your AI app.', 'wp-marketing-automations' ); ?></p>
	</div>
</main>
</body>
</html>
