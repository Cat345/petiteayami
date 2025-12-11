<?php
/**
 * Author PhpStorm.
 */
/* Product Section heading fields */
$sec_heading     = WFOCU_Common::get_option( 'wfocu_product_settings_heading' );
$sec_heading     = WFOCU_Common::maybe_parse_merge_tags( $sec_heading );
$sec_sub_heading = WFOCU_Common::get_option( 'wfocu_product_settings_sub_heading' );
$sec_sub_heading = WFOCU_Common::maybe_parse_merge_tags( $sec_sub_heading );

$sec_top_desc_text   = WFOCU_Common::get_option( 'wfocu_product_settings_top_desc_text' );
$sec_top_desc_text   = WFOCU_Common::maybe_parse_merge_tags( $sec_top_desc_text, false, false );
$sec_top_desc_talign = WFOCU_Common::get_option( 'wfocu_product_settings_top_desc_talign' );

/* Product Section heading css variable */
$template_ins      = $this->get_template_ins();
$override_global   = WFOCU_Common::get_option( 'wfocu_product_settings_override_global' );
$heading_color     = WFOCU_Common::get_option( 'wfocu_product_settings_heading_color' );
$sub_heading_color = WFOCU_Common::get_option( 'wfocu_product_settings_sub_heading_color' );
$pro_content_color = WFOCU_Common::get_option( 'wfocu_product_settings_content_color' );

if ( true === $override_global ) {
	$template_ins->internal_css['pro_head_color']     = $heading_color;
	$template_ins->internal_css['pro_sub_head_color'] = $sub_heading_color;
	$template_ins->internal_css['pro_content_color']  = $pro_content_color;
}

?>

<div class="wfocu-landing-section wfocu-product-section wfocu-mp-product-section">
	<div class=" wfocu-container">
		<div class="wfocu-row">
			<div class="wfocu-col-md-12">
				<?php
				if ( ! empty( $sec_heading ) || ! empty( $sec_sub_heading ) ) {
					echo '<div class="wfocu-section-headings">';
					echo $sec_heading ? '<div class="wfocu-heading">' . wp_kses_post( $sec_heading ) . '</div>' : '';
					echo $sec_sub_heading ? '<div class="wfocu-sub-heading wfocu-max-845">' . wp_kses_post( $sec_sub_heading ) . '</div>' : '';
					echo '</div>';
				}
				?>
			</div>
		</div>

		<div class="wfocu-row">
			<div class="wfocu-col-md-12">
				<?php
				echo '<div class="wfocu-top-content-area ' . wp_kses_post( $sec_top_desc_talign ) . ' wfocu-max-1024">';
				echo wp_kses_post( apply_filters( 'wfocu_the_content', $sec_top_desc_text ) );
				echo '</div>';
				?>

			</div>
		</div>

		<div class="wfocu-product-border-wrap ">

			<div class="wfocu-component-wrapper wfocu-mp-wrapper wfocu-mp-list clearfix ">
				<div class="wfocu-row">
					<div class="wfocu-col-md-12">
						<?php
						foreach ( $data->products as $hash_key => $product_data ) {
							if ( isset( $product_data->id ) && $product_data->id > 0 ) {
								$product_raw = array(
									'key'     => $hash_key,
									'product' => $product_data,
								);
								WFOCU_Core()->template_loader->get_template_part( 'product-layout/style-grid', $product_raw );
								?>

								<?php
							}
						}
						?>

					</div>
				</div>
			</div>
		</div>
	</div>
</div>
