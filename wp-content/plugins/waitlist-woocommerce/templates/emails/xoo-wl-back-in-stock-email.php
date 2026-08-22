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
 * @version 2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) { 
	exit; // Exit if accessed directly
}

?>

<?php if( $show_pimage ): ?>
	<?php ob_start(); ?>
		
		<td width="<?php echo esc_attr( $pimgWidth ); ?>" align="center" valign="middle" style="padding: 0">
			<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
				<tr>
					<td align="center" style="<?php echo $img_location === 'top' ? 'padding-bottom: 20px;' : 'padding-left: 20px;' ?>">
						<?php echo $product_image_html; ?>
					</td>
				</tr>
			</table>
		</td>

	<?php $img_html = ob_get_clean(); ?>
<?php endif; ?>

<?php do_action( 'xoo_wl_email_header', $emailObj ); ?>

<table cellpadding="0" border="0" cellspacing="0" width="100%">

	<?php if( $heading ): ?>
		<tr>
			<td style="padding: 0; color: <?php echo $headingColor ?>; font-weight: bold; font-size: <?php echo $headingFsize.'px' ?>;" align="center"><?php echo $heading; ?></td>
		</tr>
	<?php endif; ?>

	<tr>
		<td style="padding: 0;">
			<table cellpadding="0" cellspacing="0" width="100%" align="center">
				<tr>
					<td width="100%" align="center" style="padding: 0;">
						<table width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td width="<?php echo 525 - $pimgWidth; ?>" align="<?php echo $show_pimage && $show_pimage === 'side' ? 'left' : 'center'; ?>" valign="middle">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>

											<tr>
												<?php if( $show_pimage && $img_location === 'top' ) echo $img_html ?>
											</tr>

											<td style="padding: 0;">
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

								<?php if( $show_pimage && $img_location === 'side' ) echo $img_html ?>

							</tr>
						</table>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>

<?php do_action( 'xoo_wl_email_footer', $emailObj ); ?>