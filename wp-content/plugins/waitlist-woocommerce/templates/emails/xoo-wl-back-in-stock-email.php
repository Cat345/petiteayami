<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/templates/waitlist-woocommerce/emails/xoo-wl-back-in-stock-email.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen.
 * @see     https://docs.xootix.com/waitlist-for-woocommerce/
 * @version 2.8.7
 */

if ( ! defined( 'ABSPATH' ) ) { 
	exit; // Exit if accessed directly
}


?>

<?php do_action( 'xoo_wl_email_header', $emailObj ); ?>

<table cellpadding="0" border="0" cellspacing="0" width="100%">
	<?php if( $heading ): ?>
		<tr>
			<td style="color: <?php echo $headingColor ?>; font-weight: bold; font-size: <?php echo $headingFsize.'px' ?>;" align="center"><?php echo $heading; ?></td>
		</tr>
	<?php endif; ?>

	<tr>
		<td>
			<table cellpadding="0" cellspacing="0" width="100%" align="center">
				<tr>
					<td width="100%" align="center">
						<table width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td width="<?php echo 525 - $pimgWidth; ?>" align="<?php echo $show_pimage ? 'left' : 'center'; ?>" valign="middle" style="<?php echo $show_pimage ? 'padding-right: 20px;' : ''; ?>">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td>
												<?php echo $body_text; ?>
											</td>
										</tr>

										<?php if( $enBuyBtn === 'yes' ): ?>
										<tr>
											<td align="center" style="padding-bottom: 15px;">
												<?php echo $emailObj->button_markup( $buy_now_text, $product_link ); ?>
											</td>
										</tr>
										<?php endif; ?>
									</table>
								</td>

								<?php if( $show_pimage ): ?>
									<td width="<?php echo $pimgWidth; ?>" align="center" valign="middle">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tr>
												<td align="center">
													<img src="<?php echo $product_image; ?>" alt="<?php echo $product_name; ?>" width="100%" height="<?php echo $pimgHeight == 0 ? 'auto' : $pimgHeight; ?>" style="display:block;" />
												</td>
											</tr>
										</table>
									</td>
								<?php endif; ?>

							</tr>
						</table>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>

<?php do_action( 'xoo_wl_email_footer', $emailObj ); ?>