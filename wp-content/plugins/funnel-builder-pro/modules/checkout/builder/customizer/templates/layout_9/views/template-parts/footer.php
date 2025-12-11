<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$footer = $this->customizer_fields_data[ $this->customizer_keys['footer'] ];

if ( ( is_array( $footer ) && count( $footer ) <= 0 ) || is_null( $footer ) ) {
	return;
}
?>
<footer class="wfacp-footer wfacp_footer">
    <div class="wfacp-middle-container">
        <div class="wfacp_footer_sec clearfix">
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
