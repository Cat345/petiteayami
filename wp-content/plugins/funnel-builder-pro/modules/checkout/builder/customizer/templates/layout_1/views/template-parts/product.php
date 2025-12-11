<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


$product = [];
if ( isset( $this->customizer_fields_data['wfacp_product'] ) ) {
	$product = $this->customizer_fields_data['wfacp_product'];
}


$gbadge = $this->customizer_fields_data[ $this->customizer_keys['gbadge'] ];

if ( ( is_array( $product ) && count( $product ) <= 0 ) || is_null( $product ) ) {
	return;
}

$no_logo_img = $this->img_path . 'product_default_icon.jpg';

$class_added = 'badge_added';

if ( isset( $gbadge['gbadge_data']['enable_icon'] ) && $gbadge['gbadge_data']['enable_icon'] != 1 ) {

	$class_added = 'no_badge_added';
}

if ( isset( $product['product_data']['enable_product_image'] ) && $product['product_data']['enable_product_image'] == 1 ) {

	$product_img_cls = '';
} else {

	$product_img_cls = 'wfacp_pro_img_disabled';
}


if ( isset( $product['product_data']['enable_product_section'] ) && $product['product_data']['enable_product_section'] == 1 ) {

	?>
    <div class="wfacp-about-product wfacp_product <?php echo $class_added . ' ' . $product_img_cls; ?> wfacp_clearfix" data-scrollto="wfacp_product_section">

		<?php
		if ( isset( $product['product_data']['enable_product_image'] ) && $product['product_data']['enable_product_image'] == 1 ) {


			?>
            <div class="wfacp-prodct-image-wrap">


                <img class="wfacp-prodct-image" src="<?php echo $product['product_data']['product_image'] ? $product['product_data']['product_image'] : $no_logo_img; ?>" alt="<?php bloginfo( 'name' ); ?>" title="<?php bloginfo( 'name' ); ?>"/>


            </div>
			<?php
		}
		?>

        <div class="wfacp-prodct-detail-left">
			<?php
			if ( isset( $product['product_data']['title'] ) ) {
				?>

                <h1 class="wfacp-has-image page-title wfacp_heading_text"><?php echo $product['product_data']['title']; ?></h1>
				<?php
			}


			?>
            <div class="wfacp-customize-text"><?php echo apply_filters( 'wfacp_the_content', $product['product_data']['desc'] ); ?></div>

			<?php
			if ( isset( $gbadge['gbadge_data']['enable_icon'] ) && $gbadge['gbadge_data']['enable_icon'] == 1 ) {


				if ( isset( $gbadge['gbadge_data']['badge_icon_src'] ) && $gbadge['gbadge_data']['custom_list_image'] == '' ) {
					$icon_src = $gbadge['gbadge_data']['badge_icon_src'];;

				} else {
					$icon_src = $gbadge['gbadge_data']['custom_list_image'];
				}


				if ( $icon_src != '' ) {
					?>
                    <div class="wfacp-product-detail-right days wfacp_gbadge_icon wfacp_gbadge">
                        <div class="wfacp_product_image_sec">
                            <img src="<?php echo $icon_src; ?>" class="wfacp-money-back-color wfacp_max_width">
                        </div>
                    </div>
					<?php
				}

				?>

				<?php
			}
			?>

        </div>
    </div>
	<?php
}
?>
