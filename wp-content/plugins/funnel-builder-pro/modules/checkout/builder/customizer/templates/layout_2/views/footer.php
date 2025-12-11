
<?php
do_action( 'wfacp_footer_before_print_scripts' );
//WFACP_Core()->assets->print_scripts();
echo '<div class=wfacp_footer_sec_for_script>';
wp_footer();
echo '</div>';
do_action( 'wfacp_footer_after_print_scripts' );
?>

</body>
</html>
