<?php
/**
 * @var \WPDesk\FS\Info\Dashboard $dashboard Dashboard data.
 *
 * @package Flexible Shipping
 */

$is_pro           = $dashboard->is_pro();
$is_polish        = 'pl_PL' === get_user_locale();
$shipping_url     = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
$plugins_url      = admin_url( 'plugins.php?plugin_status=active' );
$extensions_url   = admin_url( 'admin.php?page=octolize-shipping-extensions' );
$cart_url         = wc_get_cart_url();
$brand_logo_url   = plugins_url( 'assets/images/octolize-logo-green.svg', dirname( __DIR__, 3 ) . '/flexible-shipping.php' );
$icon_sprite_path = dirname( __DIR__, 3 ) . '/assets/images/dashboard-icons.svg';
$icon_sprite_url  = add_query_arg( 'ver', (string) filemtime( $icon_sprite_path ), plugins_url( 'assets/images/dashboard-icons.svg', dirname( __DIR__, 3 ) . '/flexible-shipping.php' ) );
$dashboard_icon   = static function ( string $name, string $class = '' ) use ( $icon_sprite_url ): void {
	printf(
		'<svg class="fs-dashboard__icon%s" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
		$class ? ' ' . esc_attr( $class ) : '',
		esc_url( $icon_sprite_url ),
		esc_attr( $name )
	);
};

$hero_features = $is_pro
	? [
		__( 'AI Assistant turns one prompt into a ready-to-use rule', 'flexible-shipping' ),
		__( '19 cost conditions: weight, dimensions, class, category, user role and more', 'flexible-shipping' ),
		__( 'Automatic extra cost, e.g. per kilogram', 'flexible-shipping' ),
	]
	: [
		__( 'Unlimited shipping methods and cost rules', 'flexible-shipping' ),
		__( 'Cost based on weight and/or cart total', 'flexible-shipping' ),
		__( 'Free shipping over a set threshold', 'flexible-shipping' ),
	];

$onboarding_steps         = $is_pro
	? [
		[ 'key', __( 'Activate your PRO license on the plugins list in wp-admin', 'flexible-shipping' ), __( 'Activate', 'flexible-shipping' ), $plugins_url, false ],
		[ 'sparkles', __( 'Try the AI Assistant and generate a rule from one description', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'ai_assistant' ), true ],
		[ 'tag', __( 'Add a cost rule by shipping class, category or user role in the rules table', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'features' ), true ],
		[ 'coin', __( 'Add an automatic extra cost, e.g. per extra kilogram or item, in the rules table', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'additional_cost' ), true ],
		[ 'eye-off', __( 'Hide the method when it should not be available - e.g. for oversized items or a specific customer role', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'hide_method' ), true ],
		[ 'puzzle', __( 'Check which Octolize extensions fit your store', 'flexible-shipping' ), __( 'Check', 'flexible-shipping' ), $extensions_url, false ],
	]
	: [
		[ 'map-pin', __( 'Add a shipping zone in your WooCommerce settings', 'flexible-shipping' ), __( 'Add zone', 'flexible-shipping' ), $shipping_url, false ],
		[ 'square-plus', __( 'Add a Flexible Shipping method to that zone', 'flexible-shipping' ), __( 'Add method', 'flexible-shipping' ), $shipping_url, false ],
		[ 'table', __( 'Add a rule in the Flexible Shipping rules table based on weight or cart value', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'create_rule' ), true ],
		[ 'gift', __( 'Set a free shipping threshold on the Flexible Shipping method and turn on the cart progress bar', 'flexible-shipping' ), __( 'See how →', 'flexible-shipping' ), $dashboard->get_link( 'free_shipping' ), true ],
		[ 'bug', __( 'Open your shop\'s cart and check the new method', 'flexible-shipping' ), __( 'Open the cart', 'flexible-shipping' ), $cart_url, true ],
	];
$onboarding_action_events = $is_pro
	? [
		0 => 'onboarding.activate_license_click_count',
		5 => 'onboarding.check_extensions_click_count',
	]
	: [
		0 => 'onboarding.add_zone_click_count',
		1 => 'onboarding.add_method_click_count',
		4 => 'onboarding.open_cart_click_count',
	];

$woocommerce_guides = array_filter(
	$is_polish
		? [
			[ __( 'Shipping configuration', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_shipping' ), 'truck' ],
			[ __( 'Shipping zones', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_zones' ), 'map-pin' ],
			[ __( 'Shipping classes', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_classes' ), 'tags' ],
			[ __( 'Free shipping', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_free' ), 'gift' ],
		]
		: [
			[ __( 'Shipping zones', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_zones' ), 'map-pin' ],
			[ __( 'Shipping tax', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_tax' ), 'receipt-tax' ],
			[ __( 'Shipping methods', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_methods' ), 'truck' ],
			[ __( 'Shipping classes', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_classes' ), 'tags' ],
			[ __( 'Table Rate Shipping', 'flexible-shipping' ), $dashboard->get_link( 'woocommerce_table_rate' ), 'table' ],
		],
	static function ( $guide ) {
		return ! empty( $guide[1] );
	}
);

$product_guides = $is_pro
	? [
		[ __( 'How to use debug mode', 'flexible-shipping' ), 'debug_mode', 'bug' ],
		[ __( 'Extra fee or percentage insurance based on order total', 'flexible-shipping' ), 'extra_fee', 'percent' ],
		[ __( 'Buy X items, get free shipping', 'flexible-shipping' ), 'free_shipping_items', 'gift' ],
		[ __( 'Cost by dimensions and dimensional weight', 'flexible-shipping' ), 'sizes_classes', 'ruler' ],
		[ __( 'Multi-currency and multi-language support', 'flexible-shipping' ), 'multi_currency', 'currency' ],
	]
	: [
		[ __( 'How to add a new shipping method handled by Flexible Shipping?', 'flexible-shipping' ), 'plugin_setup', 'tabler-square-plus' ],
		[ __( 'A complete guide to shipping methods', 'flexible-shipping' ), 'method_setup', $is_polish ? 'adjustments' : 'books' ],
		[ __( 'Shipping cost based on price', 'flexible-shipping' ), 'cost_by_price', 'receipt' ],
		[ __( 'Shipping cost based on weight', 'flexible-shipping' ), 'cost_by_weight', 'weight' ],
		[ __( 'Cash on delivery extra cost', 'flexible-shipping' ), 'cash_on_delivery', 'cash' ],
		[ __( 'Try Flexible Shipping PRO on our 7-day demo store', 'flexible-shipping' ), 'demo', 'device' ],
	];

$pro_features = [
	[ 'feature-ai', __( 'Rules ready in seconds with AI', 'flexible-shipping' ) ],
	[ 'shield-check', __( 'Different rates by product class', 'flexible-shipping' ) ],
	[ 'route', __( 'Rules that fit any sales scenario', 'flexible-shipping' ) ],
	[ 'feature-headset', __( 'Fast expert help whenever you need it', 'flexible-shipping' ) ],
];

if ( $is_pro ) {
	$extensions = [
		[ 'https://octolize.com/app/uploads/2022/03/icon-fs-location-new.svg', 'Flexible Shipping Locations', __( 'Create your own locations and calculate costs by weight, cart total, or item count.', 'flexible-shipping' ) ],
		[ 'https://octolize.com/app/uploads/2022/03/flexible-shipping-locations-avatar-icon.svg', 'Distance Based Shipping Rates', __( 'Calculate shipping cost based on delivery distance and time.', 'flexible-shipping' ) ],
		[ 'https://octolize.com/app/uploads/2023/02/box-packing-avatar-icon.svg', 'Flexible Shipping Box Packing', __( 'Automatically fit products into boxes and calculate cost by package type.', 'flexible-shipping' ) ],
	];
} elseif ( $is_polish ) {
	$extensions = [
		[ 'https://octolize.com/app/uploads/2022/03/ups.svg', 'UPS', __( 'Automatically calculates UPS shipping cost at checkout and adds UPS pickup points.', 'flexible-shipping' ) ],
		[ 'https://www.wpdesk.pl/wp-content/uploads/2021/12/punkty-odbioru-wp-desk-icon.svg', __( 'Pickup Points', 'flexible-shipping' ), __( 'One map, multiple carriers - show customers their nearest lockers and pickup points.', 'flexible-shipping' ) ],
		[ 'https://www.wpdesk.pl/wp-content/uploads/2017/01/flexible-shipping-lokalizacje-woocommerce-wpdesk-icon.svg', 'Flexible Shipping Locations', __( 'Differentiate shipping costs for selected countries, regions, or custom zones.', 'flexible-shipping' ) ],
	];
} else {
	$extensions = [
		[ 'https://octolize.com/app/uploads/2022/03/ups.svg', 'UPS', __( 'Automatically calculates UPS shipping cost at checkout and adds UPS pickup points.', 'flexible-shipping' ) ],
		[ 'https://octolize.com/app/uploads/2022/08/icon-shipping-calculator.svg', __( 'Shipping cost on the product page', 'flexible-shipping' ), __( 'Shows customers the shipping cost before they reach the cart.', 'flexible-shipping' ) ],
		[ 'https://octolize.com/app/uploads/2022/03/icon-code-message.svg', 'Shipping Notices', __( 'A custom message instead of “no shipping options found”.', 'flexible-shipping' ) ],
	];
}

$social_links = [
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'x'         => 'X',
	'linkedin'  => 'LinkedIn',
	'youtube'   => 'YouTube',
	'reddit'    => 'Reddit',
];
?>
</table>

<div class="fs-info-wrapper">
	<section class="fs-dashboard fs-dashboard--<?php echo esc_attr( $dashboard->get_variant() ); ?>" data-fs-dashboard>
		<header class="fs-dashboard__bar">
			<div class="fs-dashboard__bar-inner">
				<div class="fs-dashboard__bar-left">
					<img class="fs-dashboard__logo" src="<?php echo esc_url( $brand_logo_url ); ?>" alt="Octolize">
					<nav class="fs-dashboard__nav" data-fs-dashboard-navigation aria-label="<?php esc_attr_e( 'Dashboard sections', 'flexible-shipping' ); ?>">
						<a class="is-current" href="#fs-dashboard-overview"><?php esc_html_e( 'Overview', 'flexible-shipping' ); ?></a>
						<a href="#fs-dashboard-onboarding"><?php esc_html_e( 'Get started', 'flexible-shipping' ); ?></a>
						<a href="<?php echo esc_attr( $is_pro ? '#fs-dashboard-learn' : '#fs-dashboard-help' ); ?>"><?php echo esc_html( $is_pro ? __( 'Videos & help', 'flexible-shipping' ) : __( 'Help', 'flexible-shipping' ) ); ?></a>
						<?php
						if ( ! $is_pro ) :
							?>
							<a href="#fs-dashboard-compare"><?php esc_html_e( 'Free vs PRO', 'flexible-shipping' ); ?></a><?php endif; ?>
						<a href="#fs-dashboard-extensions"><?php esc_html_e( 'Extensions', 'flexible-shipping' ); ?></a>
						<a href="#fs-dashboard-proof"><?php esc_html_e( 'Reviews', 'flexible-shipping' ); ?></a>
						<a href="#fs-dashboard-advanced-settings"><?php esc_html_e( 'Advanced settings', 'flexible-shipping' ); ?></a>
					</nav>
				</div>
				<a class="fs-dashboard__button fs-dashboard__button--ghost" href="<?php echo esc_url( $dashboard->get_link( 'header_support' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php $dashboard_icon( 'headset', 'fs-dashboard__support-icon' ); ?>
					<?php esc_html_e( 'Support', 'flexible-shipping' ); ?>
				</a>
			</div>
		</header>

		<div class="fs-dashboard__content">
			<div class="fs-dashboard__layout">
				<main class="fs-dashboard__main">
					<section id="fs-dashboard-overview" class="fs-dashboard__card fs-dashboard__hero">
						<div>
							<div class="fs-dashboard__card-head">
								<span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'table' ); ?></span>
								<div><h2><?php echo esc_html( $is_pro ? __( 'Flexible Shipping PRO - Table Rate Shipping', 'flexible-shipping' ) : __( 'Flexible Shipping - Table Rate Shipping', 'flexible-shipping' ) ); ?></h2><p><?php echo esc_html( $is_pro ? __( '296,000+ WooCommerce stores trust Flexible Shipping PRO with their shipping.', 'flexible-shipping' ) : __( '100,000+ WooCommerce stores calculate shipping rates with this plugin.', 'flexible-shipping' ) ); ?></p></div>
							</div>
							<ul class="fs-dashboard__feature-list">
								<?php
								foreach ( $hero_features as $feature ) :
									?>
									<li><span class="fs-dashboard__check">✓</span><span><?php echo esc_html( $feature ); ?></span></li><?php endforeach; ?>
							</ul>
							<a class="fs-dashboard__button" data-fs-dashboard-event="create_method_click_count" href="<?php echo esc_url( $shipping_url ); ?>">+ <?php esc_html_e( 'Create shipping method', 'flexible-shipping' ); ?></a>
						</div>
						<div class="fs-dashboard__rate-illustration">
							<table>
								<?php if ( $is_pro ) : ?>
									<tr><th><?php esc_html_e( 'Condition', 'flexible-shipping' ); ?></th><th><?php esc_html_e( 'Value', 'flexible-shipping' ); ?></th><th><?php esc_html_e( 'Cost', 'flexible-shipping' ); ?></th></tr>
									<tr><td><?php esc_html_e( 'Weight', 'flexible-shipping' ); ?></td><td>0–10 lbs</td><td>$15</td></tr>
									<tr><td><?php esc_html_e( 'Weight', 'flexible-shipping' ); ?></td><td><?php esc_html_e( 'over 45 lbs', 'flexible-shipping' ); ?></td><td>$25</td></tr>
									<tr><td><?php esc_html_e( 'User role', 'flexible-shipping' ); ?></td><td><?php esc_html_e( 'Wholesaler', 'flexible-shipping' ); ?></td><td>-20%</td></tr>
								<?php else : ?>
									<tr><th><?php esc_html_e( 'Weight', 'flexible-shipping' ); ?></th><th><?php esc_html_e( 'Cart total', 'flexible-shipping' ); ?></th><th><?php esc_html_e( 'Cost', 'flexible-shipping' ); ?></th></tr>
									<tr><td>0–10 lbs</td><td>–</td><td>$15</td></tr><tr><td>10–45 lbs</td><td>–</td><td>$25</td></tr><tr><td>–</td><td><?php esc_html_e( 'over $200', 'flexible-shipping' ); ?></td><td><?php esc_html_e( 'Free', 'flexible-shipping' ); ?></td></tr>
								<?php endif; ?>
							</table>
							<?php
							if ( $is_pro ) :
								?>
								<div class="fs-dashboard__free-progress"><strong><?php esc_html_e( '$12 away from free shipping', 'flexible-shipping' ); ?></strong><span><i></i></span></div><?php endif; ?>
						</div>
					</section>

					<section id="fs-dashboard-onboarding" class="fs-dashboard__card fs-dashboard__onboarding" data-fs-wizard>
						<div class="fs-dashboard__card-head">
							<span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'rocket' ); ?></span>
							<div><h2><?php echo esc_html( $is_pro ? __( 'Get started with Flexible Shipping PRO', 'flexible-shipping' ) : __( 'Get started with Flexible Shipping', 'flexible-shipping' ) ); ?><span class="fs-dashboard__time">~10 <?php esc_html_e( 'minutes', 'flexible-shipping' ); ?></span></h2><p><?php echo esc_html( $is_pro ? __( '6 steps to unlock everything PRO offers and fine-tune shipping for your store.', 'flexible-shipping' ) : __( '5 steps to set up your first, high-converting shipping method.', 'flexible-shipping' ) ); ?></p></div>
						</div>
						<div class="fs-dashboard__progress"><span data-fs-wizard-progress></span></div>
						<?php // Translators: 1: current onboarding step, 2: total onboarding steps. ?>
						<div class="fs-dashboard__step-label" data-fs-wizard-label data-template="<?php echo esc_attr( __( 'Step %1$d of %2$d', 'flexible-shipping' ) ); ?>"></div>
						<div class="fs-dashboard__steps">
							<?php foreach ( $onboarding_steps as $step_index => $step ) : ?>
								<article class="fs-dashboard__step" data-fs-wizard-step data-fs-dashboard-step="<?php echo esc_attr( (string) ( $step_index + 1 ) ); ?>">
									<span class="fs-dashboard__wizard-icon"><?php $dashboard_icon( $step[0] ); ?></span>
									<h3><?php echo esc_html( $step[1] ); ?></h3>
									<a class="fs-dashboard__button fs-dashboard__button--secondary"<?php echo isset( $onboarding_action_events[ $step_index ] ) ? ' data-fs-dashboard-event="' . esc_attr( $onboarding_action_events[ $step_index ] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $step[3] ); ?>"<?php echo $step[4] ? ' target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $step[2] ); ?></a>
									<?php
									if ( ! $is_pro && count( $onboarding_steps ) - 1 === $step_index ) :
										?>
										<a class="fs-dashboard__hint" href="<?php echo esc_url( $dashboard->get_link( 'debug_mode' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Can\'t see the method? Find out why', 'flexible-shipping' ); ?></a><?php endif; ?>
								</article>
							<?php endforeach; ?>
						</div>
						<div class="fs-dashboard__wizard-done" data-fs-wizard-done hidden><span class="fs-dashboard__wizard-icon"><?php $dashboard_icon( 'check-circle' ); ?></span><h3><?php echo esc_html( $is_pro ? __( '🎉 Your store is already using the full power of Flexible Shipping PRO', 'flexible-shipping' ) : __( '🎉 Nice work! Your first shipping method is ready to test in the cart', 'flexible-shipping' ) ); ?></h3><button type="button" class="fs-dashboard__button fs-dashboard__button--secondary" data-fs-wizard-restart><?php esc_html_e( 'Go through the steps again', 'flexible-shipping' ); ?></button></div>
						<div class="fs-dashboard__wizard-nav"><button type="button" class="fs-dashboard__button fs-dashboard__button--secondary" data-fs-wizard-previous>← <?php esc_html_e( 'Back', 'flexible-shipping' ); ?></button><div class="fs-dashboard__dots" data-fs-wizard-dots></div><button type="button" class="fs-dashboard__button fs-dashboard__button--teal" data-fs-wizard-next data-next-label="<?php esc_attr_e( 'Next →', 'flexible-shipping' ); ?>" data-finish-label="<?php esc_attr_e( 'Finish', 'flexible-shipping' ); ?>"><?php esc_html_e( 'Next →', 'flexible-shipping' ); ?></button></div>
					</section>

					<section id="fs-dashboard-learn" class="fs-dashboard__card fs-dashboard__learn">
						<?php if ( $is_pro ) : ?>
							<h2><?php esc_html_e( 'Learn Flexible Shipping PRO', 'flexible-shipping' ); ?></h2><p><?php esc_html_e( 'Videos and guides from the Octolize team, picked for PRO features.', 'flexible-shipping' ); ?></p>
							<div class="fs-dashboard__video-grid">
								<?php foreach ( [ [ 'video_ai', 'vNdi3exSftQ', __( 'AI Assistant in 47 seconds', 'flexible-shipping' ), __( 'See how describing a shipping scenario turns into a ready rule instantly.', 'flexible-shipping' ) ], [ 'video_table_rate', 'UPumLCbqjZA', __( 'Table rate shipping in 6 minutes', 'flexible-shipping' ), __( 'The basics of setup - a good starting point before diving into PRO rules.', 'flexible-shipping' ) ] ] as $video ) : ?>
									<article class="fs-dashboard__video"><a href="<?php echo esc_url( $dashboard->get_link( $video[0] ) ); ?>" target="_blank" rel="noopener noreferrer"><img src="https://img.youtube.com/vi/<?php echo esc_attr( $video[1] ); ?>/hqdefault.jpg" alt=""><span class="fs-dashboard__play-icon"><?php $dashboard_icon( 'player-play' ); ?></span></a><div><h3><?php echo esc_html( $video[2] ); ?></h3><p><?php echo esc_html( $video[3] ); ?></p><a href="<?php echo esc_url( $dashboard->get_link( $video[0] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Watch on YouTube →', 'flexible-shipping' ); ?></a></div></article>
								<?php endforeach; ?>
							</div>
							<p class="fs-dashboard__center"><a class="fs-dashboard__button fs-dashboard__button--teal" href="<?php echo esc_url( $dashboard->get_link( 'videos' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Watch the full series on YouTube →', 'flexible-shipping' ); ?></a></p>
						<?php else : ?>
							<article class="fs-dashboard__video fs-dashboard__video--single"><a href="<?php echo esc_url( $dashboard->get_link( 'video' ) ); ?>" target="_blank" rel="noopener noreferrer"><img src="https://img.youtube.com/vi/UPumLCbqjZA/hqdefault.jpg" alt=""><span class="fs-dashboard__play-icon"><?php $dashboard_icon( 'player-play' ); ?></span></a><div><h2><?php esc_html_e( 'Set up table rate shipping in 6 minutes', 'flexible-shipping' ); ?></h2><p><?php esc_html_e( 'A short video walkthrough from the Octolize team - from your first rule to testing it in the cart.', 'flexible-shipping' ); ?></p><a class="fs-dashboard__button fs-dashboard__button--secondary" href="<?php echo esc_url( $dashboard->get_link( 'video' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Watch on YouTube →', 'flexible-shipping' ); ?></a></div></article>
						<?php endif; ?>
					</section>

					<section id="fs-dashboard-help" class="fs-dashboard__card fs-dashboard__help">
						<h2 class="fs-dashboard__help-title"><?php esc_html_e( 'Help and guides', 'flexible-shipping' ); ?></h2><p><?php esc_html_e( 'WooCommerce basics and detailed Flexible Shipping walkthroughs.', 'flexible-shipping' ); ?></p>

						<div class="fs-dashboard__help-grid">
							<?php
							if ( $woocommerce_guides ) :
								?>
								<article class="fs-dashboard__help-card"><div class="fs-dashboard__help-head"><span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'graduation' ); ?></span><div><h3><?php esc_html_e( 'WooCommerce ABCs', 'flexible-shipping' ); ?></h3><p><?php esc_html_e( 'The basics, before you start building rules', 'flexible-shipping' ); ?></p></div></div><ul>
								<?php
								foreach ( $woocommerce_guides as $guide ) :
									?>
								<li><a href="<?php echo esc_url( $guide[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( $guide[2], 'fs-dashboard__link-icon' ); ?><span><?php echo esc_html( $guide[0] ); ?></span><?php $dashboard_icon( 'arrow-right', 'fs-dashboard__link-arrow' ); ?></a></li><?php endforeach; ?></ul>
								<?php
								if ( $dashboard->get_link( 'woocommerce_articles' ) ) :
									?>
	<a class="fs-dashboard__help-more" href="<?php echo esc_url( $dashboard->get_link( 'woocommerce_articles' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( 'news', 'fs-dashboard__link-icon' ); ?><?php esc_html_e( 'More articles →', 'flexible-shipping' ); ?></a><?php endif; ?></article><?php endif; ?>
							<article class="fs-dashboard__help-card"><div class="fs-dashboard__help-head"><span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'book-open' ); ?></span><div><h3><?php echo esc_html( $is_pro ? __( 'PRO documentation', 'flexible-shipping' ) : __( 'Flexible Shipping walkthrough', 'flexible-shipping' ) ); ?></h3><p><?php echo esc_html( $is_pro ? __( 'Features only available in PRO', 'flexible-shipping' ) : __( 'Set up the plugin step by step', 'flexible-shipping' ) ); ?></p></div></div><ul>
							<?php
							foreach ( $product_guides as $guide ) :
								?>
								<li><a href="<?php echo esc_url( $dashboard->get_link( $guide[1] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( $guide[2], 'fs-dashboard__link-icon' ); ?><span><?php echo esc_html( $guide[0] ); ?></span><?php $dashboard_icon( 'arrow-right', 'fs-dashboard__link-arrow' ); ?></a></li><?php endforeach; ?></ul>
								<?php
								if ( $dashboard->get_link( 'docs' ) ) :
									?>
								<a class="fs-dashboard__help-more" href="<?php echo esc_url( $dashboard->get_link( 'docs' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( 'book-2', 'fs-dashboard__link-icon' ); ?><?php esc_html_e( 'Learn more →', 'flexible-shipping' ); ?></a><?php endif; ?></article>
						</div>
					</section>

					<?php if ( ! $is_pro ) : ?>
						<section id="fs-dashboard-compare" class="fs-dashboard__card fs-dashboard__compare">
							<div class="fs-dashboard__card-head"><span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'scales' ); ?></span><div><h2><?php esc_html_e( 'Free or PRO?', 'flexible-shipping' ); ?></h2><p><?php esc_html_e( 'See what you get with the upgrade.', 'flexible-shipping' ); ?></p></div></div>
							<ul class="fs-dashboard__pro-features">
							<?php
							foreach ( $pro_features as $feature ) :
								?>
								<li><span class="fs-dashboard__feature-icon"><?php $dashboard_icon( $feature[0] ); ?></span><span><?php echo esc_html( $feature[1] ); ?></span></li><?php endforeach; ?></ul>
							<table><thead><tr><th><?php esc_html_e( 'Feature', 'flexible-shipping' ); ?></th><th>Free</th><th>PRO</th></tr></thead><tbody>
							<?php
							foreach ( [ [ __( 'Cost by weight and cart total', 'flexible-shipping' ), true ], [ __( 'Free shipping threshold', 'flexible-shipping' ), true ], [ __( 'Debug mode', 'flexible-shipping' ), true ], [ __( 'Free shipping coupons', 'flexible-shipping' ), false ], [ __( 'Cost by shipping class, product, category', 'flexible-shipping' ), false ], [ __( 'Cost by dimensions and volume', 'flexible-shipping' ), false ], [ __( 'Cost by time of day, delivery date, location', 'flexible-shipping' ), false ], [ __( 'Hiding shipping methods', 'flexible-shipping' ), false ], [ __( 'Conditional logic', 'flexible-shipping' ), false ], [ __( 'AI assistant for rule setup', 'flexible-shipping' ), false ], [ __( 'Priority 1-on-1 support', 'flexible-shipping' ), false ] ] as $feature ) :
								?>
								<tr><td><?php echo esc_html( $feature[0] ); ?></td><td><?php echo $feature[1] ? '<span class="fs-dashboard__check">✓</span>' : '–'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><span class="fs-dashboard__check">✓</span></td></tr><?php endforeach; ?>
							</tbody></table><div class="fs-dashboard__actions">
							<?php
							if ( $dashboard->get_link( 'comparison' ) ) :
								?>
								<a class="fs-dashboard__button fs-dashboard__button--dark" href="<?php echo esc_url( $dashboard->get_link( 'comparison' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See full comparison →', 'flexible-shipping' ); ?></a><?php endif; ?><a class="fs-dashboard__button fs-dashboard__button--teal" href="<?php echo esc_url( $dashboard->get_link( 'upgrade' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to PRO', 'flexible-shipping' ); ?></a></div>
						</section>
					<?php endif; ?>

					<section id="fs-dashboard-extensions" class="fs-dashboard__card fs-dashboard__extensions-section">
					<div class="fs-dashboard__card-head"><span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'truck' ); ?></span><div><h2><?php echo esc_html( $is_pro ? __( 'Upgrade your subscription', 'flexible-shipping' ) : __( 'Shipping extensions', 'flexible-shipping' ) ); ?></h2><p><?php echo esc_html( $is_pro ? __( 'Add a single extension for a specific scenario, or move to a bundle with several plugins in one price.', 'flexible-shipping' ) : __( 'Free Octolize plugins that add more capabilities to Flexible Shipping.', 'flexible-shipping' ) ); ?></p></div></div>
						<?php
						if ( $is_pro ) :
							?>
							<h3 class="fs-dashboard__subhead"><?php esc_html_e( 'Compatible with...', 'flexible-shipping' ); ?></h3><?php endif; ?>
						<div class="fs-dashboard__extension-grid">
						<?php
						foreach ( $extensions as $extension ) :
							?>
							<article><span class="fs-dashboard__extension-logo"><img src="<?php echo esc_url( $extension[0] ); ?>" alt=""></span><div><strong><?php echo esc_html( $extension[1] ); ?></strong><p><?php echo esc_html( $extension[2] ); ?></p></div></article><?php endforeach; ?></div>
						<p class="fs-dashboard__center"><a class="fs-dashboard__button <?php echo $is_pro ? 'fs-dashboard__button--teal' : 'fs-dashboard__button--secondary'; ?>" data-fs-dashboard-event="extensions_click_count" href="<?php echo esc_url( $extensions_url ); ?>"><?php echo esc_html( $is_pro ? __( 'See all extensions →', 'flexible-shipping' ) : __( 'See more extensions →', 'flexible-shipping' ) ); ?></a></p>
						<?php
						if ( $is_pro ) :
							?>
							<hr><h3 class="fs-dashboard__subhead"><?php esc_html_e( 'Need more than one plugin?', 'flexible-shipping' ); ?></h3><p><?php esc_html_e( 'Octolize bundles combine Flexible Shipping PRO with other tools in a single subscription.', 'flexible-shipping' ); ?></p><div class="fs-dashboard__bundle-grid"><article><strong><?php echo esc_html( $is_polish ? __( 'Flexible Shipping Bundle', 'flexible-shipping' ) : 'Flexible Shipping Bundle' ); ?></strong><p><?php esc_html_e( 'Cover every shipping scenario - distance-based rates, box packing, custom locations, import/export and more - in one subscription.', 'flexible-shipping' ); ?></p><a class="fs-dashboard__button fs-dashboard__button--secondary" href="<?php echo esc_url( $dashboard->get_link( 'bundle' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See bundle →', 'flexible-shipping' ); ?></a></article>
							<?php
							if ( $dashboard->get_link( 'all_plugins_bundle' ) ) :
								?>
							<article><strong>All Plugins Bundle</strong><p><?php esc_html_e( 'Give your store every shipping tool it could need - live carrier rates, labels, pickup points and more - all in a single subscription.', 'flexible-shipping' ); ?></p><a class="fs-dashboard__button fs-dashboard__button--secondary" href="<?php echo esc_url( $dashboard->get_link( 'all_plugins_bundle' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See bundle →', 'flexible-shipping' ); ?></a></article><?php endif; ?></div><?php endif; ?>
					</section>

					<section id="fs-dashboard-proof" class="fs-dashboard__card fs-dashboard__proof">
						<div class="fs-dashboard__proof-top"><span class="fs-dashboard__stars">★★★★★</span><strong><?php echo esc_html( $is_pro ? '250K+' : '4.9/5' ); ?></strong><span><?php echo esc_html( $is_pro ? __( 'WooCommerce stores run on Octolize solutions every day.', 'flexible-shipping' ) : __( 'from 702 reviews on WordPress.org.', 'flexible-shipping' ) ); ?></span></div>
						<h2><?php echo esc_html( $is_pro ? __( 'One company, a full shipping toolkit', 'flexible-shipping' ) : __( 'Trusted by 100,000+ WooCommerce stores', 'flexible-shipping' ) ); ?></h2>
						<div class="fs-dashboard__testimonials"><blockquote><p><?php echo esc_html( $is_pro ? __( 'Whether we needed carrier rates or custom cost rules, Octolize plugins always play well together.', 'flexible-shipping' ) : __( 'Setting up rates by weight and cart total took us minutes - we used to juggle a few plugins to get the same result.', 'flexible-shipping' ) ); ?></p><cite><span class="fs-dashboard__testimonial-icon"><?php $dashboard_icon( $is_pro ? 'store' : 'home' ); ?></span><?php echo esc_html( $is_pro ? __( 'Multi-category store', 'flexible-shipping' ) : __( 'Home goods store', 'flexible-shipping' ) ); ?></cite></blockquote><blockquote><p><?php echo esc_html( $is_pro ? __( 'The support team walked us through the whole setup - it took far less time than we expected.', 'flexible-shipping' ) : __( 'Debug mode saved us hours when we could not figure out why one method was not showing up in the cart.', 'flexible-shipping' ) ); ?></p><cite><span class="fs-dashboard__testimonial-icon"><?php $dashboard_icon( $is_pro ? 'briefcase' : 'hard-hat' ); ?></span><?php echo esc_html( $is_pro ? __( 'WooCommerce agency', 'flexible-shipping' ) : __( 'B2B store, construction supplies', 'flexible-shipping' ) ); ?></cite></blockquote></div>
						<a class="fs-dashboard__button fs-dashboard__button--secondary" href="<?php echo esc_url( $dashboard->get_link( $is_pro ? 'about' : 'review' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $is_pro ? __( 'Meet Octolize →', 'flexible-shipping' ) : __( 'Leave a review →', 'flexible-shipping' ) ); ?></a>
					</section>

					<section id="fs-dashboard-advanced-settings" class="fs-dashboard__card fs-dashboard__advanced" data-fs-dashboard-advanced hidden>
						<div class="fs-dashboard__card-head fs-dashboard__advanced-head">
							<span class="fs-dashboard__card-icon"><?php $dashboard_icon( 'settings' ); ?></span>
							<div data-fs-dashboard-advanced-title></div>
						</div>
						<div data-fs-dashboard-advanced-fields></div>
						<div data-fs-dashboard-advanced-submit></div>
					</section>

					<footer class="fs-dashboard__footer" data-fs-dashboard-footer>
						<nav class="fs-dashboard__quick-links" aria-label="<?php esc_attr_e( 'Flexible Shipping resources', 'flexible-shipping' ); ?>"><a href="<?php echo esc_url( $dashboard->get_link( 'footer_docs' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( 'book' ); ?><?php esc_html_e( 'Documentation', 'flexible-shipping' ); ?></a><a href="<?php echo esc_url( $dashboard->get_link( 'footer_support' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( 'chat' ); ?><?php echo esc_html( $is_pro ? __( 'Support', 'flexible-shipping' ) : __( 'Support forum', 'flexible-shipping' ) ); ?></a><a href="<?php echo esc_url( $dashboard->get_link( 'footer_changelog' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php $dashboard_icon( 'clock' ); ?><?php esc_html_e( 'Changelog', 'flexible-shipping' ); ?></a></nav>
						<section class="fs-dashboard__socials"><h3><?php esc_html_e( 'Follow Octolize', 'flexible-shipping' ); ?></h3><p><?php esc_html_e( 'Tips, product updates, and behind-the-scenes from the team.', 'flexible-shipping' ); ?></p><nav aria-label="<?php esc_attr_e( 'Octolize social media', 'flexible-shipping' ); ?>">
						<?php
						foreach ( $social_links as $name => $label ) :
							?>
							<a href="<?php echo esc_url( $dashboard->get_link( $name ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $label ); ?>"><?php $dashboard_icon( $name, 'fs-dashboard__social-icon' ); ?></a><?php endforeach; ?></nav></section>
						<p class="fs-dashboard__newsletter"><?php $dashboard_icon( 'email' ); ?><?php esc_html_e( 'Want shipping setup tips in your inbox?', 'flexible-shipping' ); ?> <a href="<?php echo esc_url( $dashboard->get_link( 'newsletter' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up for the Octolize newsletter', 'flexible-shipping' ); ?></a>.</p>
					</footer>
				</main>

				<?php
				if ( ! $is_pro ) :
					?>
					<aside class="fs-dashboard__upsell"><h2>Flexible Shipping PRO</h2><p><?php esc_html_e( 'Advanced shipping rules for growing stores.', 'flexible-shipping' ); ?></p><ul>
					<?php
					foreach ( $pro_features as $feature ) :
						?>
						<li><span class="fs-dashboard__feature-icon"><?php $dashboard_icon( $feature[0] ); ?></span><span><?php echo esc_html( $feature[1] ); ?></span></li><?php endforeach; ?></ul><a class="fs-dashboard__button fs-dashboard__button--teal" href="<?php echo esc_url( $dashboard->get_link( 'upsell_box' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to PRO', 'flexible-shipping' ); ?></a></aside><?php endif; ?>
			</div>
		</div>
	</section>
</div>

<table class="form-table" hidden>
