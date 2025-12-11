<?php

$link = 'https://xootix.com/plugins/waitlist-for-woocommerce#sp-addons';

$addons = array(

	'email_booster' => array(
		'title' => 'Email Booster',
		'icon' 	=> 'dashicons-email',
		'desc' 	=> '- Send a notification email to the customer and the admin (or a specified email address) when someone joins the waitlist. <br>
					- Auto send "back in stock email" when stock status is updated to "in stock"
					',
		'link' 	=> $link
	),

	'export' => array(
		'title' => 'Export/Import Users',
		'icon' 	=> 'dashicons-move',
		'desc' 	=> 'Export & Import waitlist users & all the data in a CSV/Excel file.',
		'link' 	=> $link
	),

	'fields' => array(
		'title' 	=> 'Custom Form fields',
		'icon' 		=> 'dashicons-plus',
		'desc' 		=> 'Add extra fields to waitlist form & collect additional data from users. (See <a href="'.admin_url('admin.php?page=xoo-wl-fields').'" target="__blank">Fields page</a> to know supported field types )',
		'link' 	=> $link,
	),


	'notify_phone' => array(
		'title' => 'Notify on Phone',
		'icon' 	=> 'dashicons-phone',
		'desc' 	=> 'Send a text message to users whenever a product arrives back in stock.',
		'link' 	=> $link
	),

);

?>

<div class="xoo-addon-container">
	<?php foreach ( $addons as $id => $data ): ?>
		<div class="xoo-addon">
			<span class="dashicons <?php echo esc_attr( $data['icon'] ); ?>"></span>
			<span class="xoo-ao-title"><?php echo $data['title'] ?></span>
			<div class="xoo-ao-desc"><?php echo $data['desc']; ?></div>
			<div class="xoo-ao-btns">
				<a href="<?php echo esc_url( $data['link'] ) ?>">BUY</a>
				<?php if( isset( $data['demo'] ) ): ?>
					<a href="<?php echo esc_url( $data['demo'] ) ?>">DEMO</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>