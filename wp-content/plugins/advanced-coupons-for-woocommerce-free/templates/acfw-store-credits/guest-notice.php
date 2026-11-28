<?php
/**
 * Store credits guest notice on the checkout page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/acfw-store-credits/guest-notice.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package ACFWF\Templates
 * @version 4.7.7
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="woocommerce-info acfw-store-credits-guest-notice" role="status">
    <?php echo esc_html( $notice_text ?? '' ); ?>
</div>
