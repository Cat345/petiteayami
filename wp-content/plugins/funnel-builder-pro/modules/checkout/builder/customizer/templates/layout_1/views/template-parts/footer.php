<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

$footer = $this->customizer_fields_data[ $this->customizer_keys['footer'] ];
if ( ( is_array( $footer ) && count( $footer ) <= 0 ) || is_null( $footer ) ) {
	return;
}
?>
<footer class="wfacp_footer wfacp-footer clearfix">
    <div class="wfacp-container" data-scrollto="wfacp_footer_section">
        <div class="wfacp-footer-inner-wrap clearfix">
			<?php
			if ( isset( $footer['footer_data']['ft_ct_content'] ) && $footer['footer_data']['ft_ct_content'] != '' ) {
				?>
                <div class=" wfacp_footer_n">
                    <div class=" wfacp_footer_wrap_n">
                        <div class="wfacp-footer-text">
							<?php echo apply_filters( 'wfacp_the_content', $footer['footer_data']['ft_ct_content'] ); ?>
                        </div>
                    </div>

                </div>
				<?php
			}
			?>
        </div>
    </div>
</footer>
