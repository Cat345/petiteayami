<?php
$logo = WFOCU_Common::get_option( WFOCU_SLUG . '_header_top_logo' );
$logo = WFOCU_Common::get_image_source( $logo );

$logo_align = WFOCU_Common::get_option( WFOCU_SLUG . '_header_top_logo_align' );

$template_ins = $this->get_template_ins(); //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable

/** css */
$logo_width   = WFOCU_Common::get_option( WFOCU_SLUG . '_header_top_logo_width' );
$sec_bg_color = WFOCU_Common::get_option( WFOCU_SLUG . '_header_top_bgcolor' );

$template_ins->internal_css['header_logo_width']  = $logo_width;
$template_ins->internal_css['header_top_bgcolor'] = $sec_bg_color;
$no_logo_img                                      = WFOCU_PLUGIN_URL . '/admin/assets/img/no_logo.jpg';


?>
<div class="wfocu-landing-section wfocu-page-header-section wfocu-page-header-style1" data-scrollto="wfocu_header_top">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-page-header-inner wfocu-clearfix">
                    <div class="wfocu-page-logo <?php echo esc_attr($logo_align); ?>">

                        <img class="skip-lazy" src="<?php echo $logo ? esc_attr($logo) : esc_attr($no_logo_img); ?>" alt="<?php bloginfo( 'name' ); ?>" title="<?php bloginfo( 'name' ); ?>"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
