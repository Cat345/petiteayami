<?php

do_action( 'footer_before_print_scripts' );
do_action( 'wfocu_footer_before_print_scripts' );
WFOCU_Core()->assets->print_scripts();
do_action( 'footer_after_print_scripts' );
do_action( 'wfocu_footer_after_print_scripts' );
if ( true === apply_filters( 'wfocu_allow_externals_on_customizer', false ) ) {
	remove_action( 'wp_footer', array( WFOCU_Core()->public, 'load_confirmation_page_ui' ) );
	remove_action( 'wp_footer', array( WFOCU_Core()->public, 'load_footer_script_for_custom_page' ) );
	wp_footer();
}
?>
<style type="text/css" data-type="wfocu"></style>

</body>
</html>
