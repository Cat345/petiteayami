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


if ( ( is_array( $product ) && count( $product ) <= 0 ) || is_null( $product ) ) {
	return;
}

$no_logo_img = $this->img_path . 'product_default_icon.jpg';


$rbox_border_type = '';
if ( isset( $data['advance_setting']['rbox_border_type'] ) && $data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $data['advance_setting']['rbox_border_type'];
}


if ( isset( $product['product_data']['enable_product_image'] ) && $product['product_data']['enable_product_image'] == 1 ) {

	$product_img_cls = '';
} else {

	$product_img_cls = 'wfacp_pro_img_disabled';
}


$cls_for_layout = '';
if ( isset( $product['product_data']['product_layouts'] ) && $product['product_data']['product_layouts'] != '' ) {
	$cls_for_layout = $product['product_data']['product_layouts'];
}


?>
<div class="wfacp_product <?php echo $product_img_cls . ' ' . $rbox_border_type . ' ' . $cls_for_layout; ?> div_wrap_sec" data-scrollto="wfacp_product_section">

	<?php
	if ( isset( $data['heading_section']['heading'] ) && $data['heading_section']['heading'] != '' && isset( $data['heading_section']['enable_heading'] ) && $data['heading_section']['enable_heading'] == true ) {
		$align_text         = $data['heading_section']['heading_talign'];
		$font_weight        = $data['heading_section']['heading_font_weight'];
		$heading_fs_desktop = $data['heading_section']['heading_fs']['desktop'];
		$heading_fs_tablet  = $data['heading_section']['heading_fs']['tablet'];
		$heading_fs_mobile  = $data['heading_section']['heading_fs']['mobile'];
		?>
        <h2 class="wfacp-list-title wfacp_section_title <?php echo $align_text . ' ' . $font_weight; ?>">
			<?php echo $data['heading_section']['heading']; ?>
        </h2>
		<?php
	}
	?>


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
        <div class="wfacp-customize-text"><?php echo apply_filters( 'wfacp_the_content', $product['product_data']['desc'] ); ?></div>
    </div>
</div>
<?php

?>
