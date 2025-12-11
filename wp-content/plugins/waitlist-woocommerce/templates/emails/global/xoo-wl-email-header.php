<?php
/**
 *
 * This template can be overridden by copying it to yourtheme/templates/waitlist-woocommerce/emails/global/xoo-wl-email-header.php.
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

$outContBGcolor = xoo_wl_helper()->get_email_style_option('c-outbgcolor');
$inContBGcolor  = xoo_wl_helper()->get_email_style_option('c-inbgcolor');
$txtColor       = xoo_wl_helper()->get_email_style_option('c-txtcolor');
$borderColor    = xoo_wl_helper()->get_email_style_option('c-bdcolor');
$fontSize       = xoo_wl_helper()->get_email_style_option('c-fsize').'px';
$contentPadding = xoo_wl_helper()->get_email_style_option('c-cont-padding');

?>

<!DOCTYPE html>
<html>
<head>

	<title><?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?></title>
  	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
  	<meta name="viewport" content="width=device-width">
  	<?php do_action( 'xoo_wl_email_head', $emailObj ); ?>

</head>

<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">		
	<!-- Main Container -->
	<table cellpadding="0" border="0" cellspacing="0" width="100%">
		<tr>
			<td align="center" bgcolor="<?php echo $outContBGcolor ?>" style="color: <?php echo $txtColor ?>;" valign="top">

				<!-- 600px Inner Container -->
				<table cellpadding="2" cellspacing="0" width="600" class="xoo-wl-table-full" bgcolor="<?php echo $inContBGcolor ?>" style="border: 1px solid <?php echo $borderColor ?>;">


					<!-- Site Logo -->
					<?php if( xoo_wl_helper()->get_email_option( 'gl-logo' ) ): ?>
					<tr>
						<td align="center" style="padding: 20px 0 0 0">
						<img height="auto" width="auto" border="0" alt="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" src="<?php echo esc_url( xoo_wl_helper()->get_email_option( 'gl-logo' ) ); ?>" style="display: block"/>
						</td>
					</tr>
					<?php endif; ?>

					<tr>
						<td style="font-size: <?php echo $fontSize ?>; padding: <?php echo $contentPadding; ?>">

