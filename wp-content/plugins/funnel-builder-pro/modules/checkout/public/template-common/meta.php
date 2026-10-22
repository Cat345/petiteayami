<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$id = WFACP_Common::get_id();
do_action( 'wfacp_meta_added', $id );

// Viewport meta moved to WFACP_Template_Common::print_viewport_meta(), printed last on wp_head.
?>
<meta name="wfacp_aero_checkout_id" id="wfacp_aero_checkout_id" content="<?php echo esc_attr( $id ); ?>"/>
<script type="text/javascript">
	if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) {
		var script = document.createElement('script');
		script.setAttribute('src', 'https://cdnjs.cloudflare.com/ajax/libs/bluebird/3.7.2/bluebird.min.js');
		document.head.appendChild(script);
	}
</script>
