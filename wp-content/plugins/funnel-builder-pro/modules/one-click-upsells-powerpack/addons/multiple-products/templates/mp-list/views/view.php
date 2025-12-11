<?php
$layout                = WFOCU_Common::get_option( 'wfocu_' . $this->template_slug . '_layout_layout_order' );
$sec_heading_fs        = WFOCU_Common::get_option( 'wfocu_style_typography_heading_fs' );
$sec_sub_heading_fs    = WFOCU_Common::get_option( 'wfocu_style_typography_sub_heading_fs' );
$site_content_fs       = WFOCU_Common::get_option( 'wfocu_style_typography_content_fs' );
$site_font_family_fs   = WFOCU_Common::get_option( 'wfocu_style_typography_font_family_fs' );
$site_bg_color         = WFOCU_Common::get_option( 'wfocu_style_colors_site_bg_color' );
$sec_heading_color     = WFOCU_Common::get_option( 'wfocu_style_colors_heading_color' );
$sec_sub_heading_color = WFOCU_Common::get_option( 'wfocu_style_colors_sub_heading_color' );
$site_content_color    = WFOCU_Common::get_option( 'wfocu_style_colors_content_color' );
$site_highlight_color  = WFOCU_Common::get_option( 'wfocu_style_colors_highlight_color' );
$site_style            = WFOCU_Common::get_option( 'wfocu_' . $this->template_slug . '_layout_layout_style' );

if ( 'wfocu-boxed' === $site_style ) {
	$site_boxed_width = WFOCU_Common::get_option( 'wfocu_' . $this->template_slug . '_layout_layout_site_boxed_width' );
}

$this->internal_css['section_heading_fs']        = $sec_heading_fs;
$this->internal_css['section_heading_color']     = $sec_heading_color;
$this->internal_css['section_sub_heading_fs']    = $sec_sub_heading_fs;
$this->internal_css['section_sub_heading_color'] = $sec_sub_heading_color;
$this->internal_css['site_bg_color']             = $site_bg_color;
$this->internal_css['site_content_color']        = $site_content_color;
$this->internal_css['site_highlight_color']      = $site_highlight_color;
$this->internal_css['site_content_fs']           = $site_content_fs;
$this->internal_css['site_font_family_fs']       = $site_font_family_fs;

if ( 'wfocu-boxed' === $site_style ) {
	$this->internal_css['site_boxed_width'] = $site_boxed_width;
}
ob_start();

/** Template views */
if ( is_array( $layout ) && count( $layout ) > 0 ) {
	if ( ! empty( $data->products ) ) {
		/** Single product hash key and data */
		$product_raw_for_sections = array();
		foreach ( $data->products as $hash_key => $product_data ) {
			if ( isset( $product_data->id ) && $product_data->id > 0 ) {
				$product_raw_for_sections = array(
					'key'     => $hash_key,
					'product' => $product_data,
				);
				break;
			}
		}

		/** Layout */
		foreach ( $layout as $single ) {
			switch ( $single ) {
				case 'header':
					WFOCU_Core()->template_loader->get_template_part( 'header-logo' );
					break;

				case 'header_progress_bar':
					$style = WFOCU_Common::get_option( 'wfocu_header_progress_bar_style' );
					switch ( $style ) {
						case 'style1':
						case 'style2':
							WFOCU_Core()->template_loader->get_template_part( 'progressbar/' . $style );
							break;
					}

					break;

				case 'heading':
					WFOCU_Core()->template_loader->get_template_part( 'top-headers' );
					break;

				case 'products':
					do_action( 'wfocu_front_mp_products_start' );

					$product_raw = array(
						'key'     => $hash_key,
						'product' => $product_data,
					);

					WFOCU_Core()->template_loader->get_template_part( 'product-grids/style1', $data );

					do_action( 'wfocu_front_mp_products_end' );
					break;

				case 'reviews':
					WFOCU_Core()->template_loader->get_template_part( 'reviews/style1', $product_raw_for_sections );
					break;

				case 'features':
					WFOCU_Core()->template_loader->get_template_part( 'feature/style1', $product_raw_for_sections );
					break;

				case 'guarantee':
					WFOCU_Core()->template_loader->get_template_part( 'guarantee/style1', $product_raw_for_sections );
					break;

				case 'urgency_bar':
					WFOCU_Core()->template_loader->get_template_part( 'urgency-bar/style1' );
					break;

				case 'footer':
					WFOCU_Core()->template_loader->get_template_part( 'footer' );
					break;
			}
		}
	}
}


$template_views = ob_get_clean();

/** Load Output Starts */
WFOCU_Core()->template_loader->load_header();

echo  $template_views; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
/** Sidebar Bucket */
if ( false === WFOCU_Core()->template_loader->is_customizer_preview() ) {
	WFOCU_Core()->template_loader->get_template_part( 'offer-confirmations', $data->products );

}

WFOCU_Core()->template_loader->load_footer();
/** Load Output Ends */
