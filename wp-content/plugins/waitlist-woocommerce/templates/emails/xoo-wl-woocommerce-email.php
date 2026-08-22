<?php

/**
 *
 * This template can be overridden by copying it to yourtheme/templates/waitlist-woocommerce/emails/xoo-wl-woocommerce-email.php
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen.
 * @see     https://docs.xootix.com/waitlist-woocommerce
 * @version 2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

$inContBGcolor  = xoo_wl_helper()->get_email_style_option('c-inbgcolor');
$txtColor       = xoo_wl_helper()->get_email_style_option('c-txtcolor');
$borderColor    = xoo_wl_helper()->get_email_style_option('c-bdcolor');
$fontSize       = xoo_wl_helper()->get_email_style_option('c-fsize').'px';
$contentPadding = xoo_wl_helper()->get_email_style_option('c-cont-padding');

?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<table cellpadding="2" cellspacing="0" bgcolor="<?php echo $inContBGcolor ?>" style="border: 1px solid <?php echo $borderColor ?>;" align="center" width="100%">

	<!-- Site Logo -->
	<?php if( xoo_wl_helper()->get_email_option( 'gl-logo' ) ): ?>
	<tr>
		<td align="center" style="padding: 20px 0 0 0">
		<img height="auto" width="auto" border="0" alt="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" src="<?php echo esc_url( xoo_wl_helper()->get_email_option( 'gl-logo' ) ); ?>" style="display: block"/>
		</td>
	</tr>
	<?php endif; ?>

	<tr>
		<td style="font-size: <?php echo $fontSize ?>; padding: <?php echo $contentPadding; ?>" style="color: <?php echo $txtColor ?>;">

			<?php echo $email_text; ?>

			<?php
			/**
			 * Show user-defined additional content - this is set in each email's settings.
			 */
			if ( $additional_content ) {
				echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
			}

			?>
		</td><!-- End Content -->
	</tr>
</table><!-- End 600px inner container -->

<?php do_action( 'woocommerce_email_footer', $email );