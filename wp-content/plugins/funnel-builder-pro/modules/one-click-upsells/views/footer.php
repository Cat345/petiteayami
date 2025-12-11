<?php
$template_ins    = $this->get_template_ins();
$f_text          = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_text' );
$f_text          = WFOCU_Common::maybe_parse_merge_tags( $f_text );
$f_payment_icons = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_payment_icons' );

$f_links = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_links' );


$f_text_color = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_text_color' );

$f_text_fs = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_text_fs' );


$f_links_color = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_links_color' );

$f_links_fs = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_f_links_fs' );

$sec_bg_color = WFOCU_Common::get_option( WFOCU_SLUG . '_footer_footer_bg_color' );

$template_ins->internal_css['footer_bg_color'] = $sec_bg_color;


$template_ins->internal_css['footer_text_color']  = $f_text_color;
$template_ins->internal_css['footer_text_fs']     = $f_text_fs;
$template_ins->internal_css['footer_links_color'] = $f_links_color;
$template_ins->internal_css['footer_links_fs']    = $f_links_fs;
?>
<div class="wfocu-landing-section wfocu-page-footer-section wfocu-page-footer-style1" data-scrollto="wfocu_footer_footer">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-footer-inner">
					<?php if ( ! empty( $f_text ) ) { ?>
                        <div class="wfocu-footer-text wfocu-text-center">
							<?php echo $f_text; ?>
                        </div>
						<?php
					}
					if ( true === $f_payment_icons ) {
						WFOCU_Core()->template_loader->get_template_part( 'payment-cards', array() );
					}
					?>

					<?php if ( is_array( $f_links ) && count( $f_links ) > 0 ) { ?>
                        <div class="wfocu-footer-links">
                            <ul class="wfocu-clearfix">
								<?php
								foreach ( $f_links as $footer_link ) {
									$name = $footer_link['name'];
									$link = $footer_link['link'];

									?>
                                    <li><a href="<?php echo $link; ?>" target="_blank"><?php echo $name; ?></a></li>
									<?php

									unset( $name );
									unset( $link );
								}
								?>
                            </ul>
                        </div>
					<?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

