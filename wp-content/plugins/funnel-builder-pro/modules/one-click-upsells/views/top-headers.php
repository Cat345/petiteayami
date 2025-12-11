<?php

$template_ins = $this->get_template_ins();


$sec_bg_color          = WFOCU_Common::get_option( 'wfocu_heading_heading_bgcolor' );
$headings_headline     = WFOCU_Common::get_option( 'wfocu_heading_heading_head' );
$headings_sub_headline = WFOCU_Common::get_option( 'wfocu_heading_heading_sub_head' );

$headings_headline     = WFOCU_Common::maybe_parse_merge_tags( $headings_headline );
$headings_sub_headline = WFOCU_Common::maybe_parse_merge_tags( $headings_sub_headline );

remove_filter( 'wfocu_the_content', 'wpautop' );
$headings_sub_headline = apply_filters( 'wfocu_the_content', $headings_sub_headline );

add_filter( 'wfocu_the_content', 'wpautop' );

$headings_headline_color = WFOCU_Common::get_option( 'wfocu_heading_heading_head_color' );
$headings_headline_fs    = WFOCU_Common::get_option( 'wfocu_heading_heading_head_fs' );

$headings_sub_headline_color = WFOCU_Common::get_option( 'wfocu_heading_heading_sub_head_color' );
$headings_sub_headline_fs    = WFOCU_Common::get_option( 'wfocu_heading_heading_sub_head_fs' );


$template_ins->internal_css['headings_bg_color']           = $sec_bg_color;
$template_ins->internal_css['headings_headline_color']     = $headings_headline_color;
$template_ins->internal_css['headings_sub_headline_color'] = $headings_sub_headline_color;

$template_ins->internal_css['headings_headline_fs']     = $headings_headline_fs;
$template_ins->internal_css['headings_sub_headline_fs'] = $headings_sub_headline_fs;

?>
<div class="wfocu-landing-section wfocu-header-section wfocu-headers-style1" data-scrollto="wfocu_heading_heading">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-header-sec-wrap">
                    <div class="wfocu-top-headings">
						<?php echo $headings_headline ? ' <h1 class="wfocu-top-heading">' . $headings_headline . '</h1>' : ''; ?>
						<?php echo $headings_sub_headline ? ' <h2 class="wfocu-top-sub-heading">' . $headings_sub_headline . '</h2>' : ''; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>
