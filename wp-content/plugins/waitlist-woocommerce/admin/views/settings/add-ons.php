<?php

$link = 'https://xootix.com/plugins/waitlist-for-woocommerce#sp-addons';

$addons = array(

	'email_booster' => array(
		'title' => 'Email Booster',
		'icon' 	=> 'dashicons-email',
		'desc' 	=> '- Automatically send “Back in Stock” emails on updating stock status.<br>
					- Send notification emails to customers and admins when someone joins the waitlist.
					',
		'link' 	=> $link
	),

	'manage' => array(
		'title' => 'Unsubscribe / Manage Waitlist',
		'icon' 	=> 'dashicons-admin-users',
		'desc' 	=> '-Add unsubscribe links to emails for easy opt-out. <br>
					- Let users view and manage all their waitlisted products from one place. [GDPR-compliant]',
		'link' 	=> $link
	),

	
	'fields' => array(
		'title' 	=> 'Custom Form fields',
		'icon' 		=> 'dashicons-plus',
		'desc' 		=> 'Add extra fields to waitlist form & collect additional data from users. (See <a href="'.admin_url('admin.php?page=xoo-wl-fields').'" target="__blank">Fields page</a> to know supported field types )',
		'link' 	=> $link,
	),


	'export' => array(
		'title' => 'Export/Import Users',
		'icon' 	=> 'dashicons-move',
		'desc' 	=> 'Export and import waitlist users, including all their data, using CSV/Excel files.',
		'link' 	=> $link
	),




);

?>

<div class="xoo-addon-container">
	<?php foreach ( $addons as $id => $data ): ?>
		<div class="xoo-addon">
			<span class="dashicons <?php echo esc_attr( $data['icon'] ); ?>"></span>
			<span class="xoo-ao-title"><?php echo wp_kses_post( $data['title'] ) ?></span>
			<div class="xoo-ao-desc"><?php echo wp_kses_post( $data['desc'] ); ?></div>
			<div class="xoo-ao-btns">
				<a class="xoo-btn xoo-btn-primary" target="_blank" href="<?php echo esc_url( $data['link'] ) ?>">BUY</a>
				<?php if( isset( $data['demo'] ) ): ?>
					<a class="xoo-btn xoo-btn-secondary" target="_blank" href="<?php echo esc_url( $data['demo'] ) ?>">DEMO</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>