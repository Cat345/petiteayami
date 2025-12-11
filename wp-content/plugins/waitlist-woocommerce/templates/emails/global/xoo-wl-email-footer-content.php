<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/templates/waitlist-woocommerce/emails/global/xoo-wl-email-footer-content.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen.
 * @see     https://docs.xootix.com/waitlist-for-woocommerce/
 * @version 2.7
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}


?>

<?php

$bgColor 		= xoo_wl_helper()->get_email_style_option('ftc-bgcolor');
$txtColor       = xoo_wl_helper()->get_email_style_option('ftc-txtcolor');
$fontSize       = xoo_wl_helper()->get_email_style_option('ftc-fsize').'px';
$contentPadding = xoo_wl_helper()->get_email_style_option('ftc-padding');
$textAlign 		= xoo_wl_helper()->get_email_style_option('ft-text-align');
$content 		= xoo_wl_helper()->get_email_option('gl-ft-content');

if( !$content ) return;

?>

<!-- 600px Inner Container -->
<table cellpadding="0" cellspacing="0" width="600" class="xoo-wl-table-full" bgcolor="<?php echo $bgColor ?>">
	<tr>
		<td align="<?php echo $textAlign; ?>" style="color: <?php echo $txtColor ?>;font-size: <?php echo $fontSize ?>; padding: <?php echo $contentPadding; ?>">
			<?php echo $content; ?>
		</td>
	</tr>
</table>