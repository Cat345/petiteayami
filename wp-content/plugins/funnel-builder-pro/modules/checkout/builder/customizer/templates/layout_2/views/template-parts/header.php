<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


$headers = $this->customizer_fields_data[ $this->customizer_keys['header'] ];

if ( ( is_array( $headers ) && count( $headers ) <= 0 ) || is_null( $headers ) ) {
	return;
}

$rbox_border_type = '';
if ( isset( $headers['advance_setting']['rbox_border_type'] ) && $headers['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $headers['advance_setting']['rbox_border_type'];
}
?>


<header class="wfacp-header wfacp_header <?php echo $rbox_border_type; ?>">
    <div class="wfacp-container wfacp-inner-header">
		<?php
		if ( isset( $headers['header_data']['logo'] ) && $headers['header_data']['logo'] != '' ) {

			$logo_link        = '#';
			$logo_link_target = '_self';
			$logo_link_class  = '';

			if ( isset( $headers['header_data']['logo_link_target'] ) && $headers['header_data']['logo_link_target'] == 1 ) {
				$logo_link_target = '_blank';
			}


			if ( ( isset( $headers['header_data']['logo_link'] ) && $headers['header_data']['logo_link'] != '#' ) && ! empty( $headers['header_data']['logo_link'] ) ) {
				$logo_link = $headers['header_data']['logo_link'];
			} else {
				$logo_link       = 'javascript:void(0)';
				$logo_link_class = 'wfacp_no_link';
			}


			?>
            <a class="wfacp_logo_wrap <?php echo $logo_link_class; ?>" href="<?php echo $logo_link; ?>" target="<?php echo $logo_link_target; ?>">
                <img class="wfacp-logo" src="<?php echo $headers['header_data']['logo']; ?>" alt="<?php the_title() ?>">
            </a>
			<?php
		}
		?>

        <div class="wfacp-help-text wfacp-pd-20">
            <div class="wfacp-header-nav clearfix">
                <ul>
					<?php

					$hide_sec = 'wfacp_display_none';


					if ( isset( $headers['header_data']['header_text'] ) && $headers['header_data']['header_text'] != '' ) {
						$hide_sec = '';

					}


					?>
                    <li class="wfacp_header_list_sup <?php echo $hide_sec; ?>">

							<span class="wfacp-hd-list-sup"><?php echo $headers['header_data']['header_text']; ?>
							</span>
                    </li>


					<?php
					$hide_sec = 'wfacp_display_none';
					if ( isset( $headers['header_data']['helpdesk_text'] ) && $headers['header_data']['helpdesk_text'] != '' ) {
						$hide_sec = '';

					}

					$helpdesk_link_target = '_self';
					if ( isset( $headers['header_data']['helpdesk_link_target'] ) && $headers['header_data']['helpdesk_link_target'] == 1 ) {
						$helpdesk_link_target = '_blank';
					}


					?>

                    <li class="wfacp_header_list_help <?php echo $hide_sec; ?>"><a href="<?php echo $headers['header_data']['helpdesk_url']; ?>" target="<?php echo $helpdesk_link_target; ?>">
                            <span class="wfacp-hd-list-help"><?php echo $headers['header_data']['helpdesk_text']; ?></span></a>
                    </li>

					<?php
					$hide_sec = 'wfacp_display_none';
					if ( isset( $headers['header_data']['email'] ) && $headers['header_data']['email'] != '' ) {
						$hide_sec = '';
					}
					$email = $headers['header_data']['email'];

					?>


                    <li class="wfacp_header_email <?php echo $hide_sec; ?>"><a href="mailto:<?php echo $email; ?>"><span class="wfacp-hd-list-email"><?php echo $email; ?></span></a></li>

					<?php
					$hide_sec = 'wfacp_display_none';
					if ( isset( $headers['header_data']['phone'] ) && $headers['header_data']['phone'] != '' ) {
						$hide_sec = '';
					}
					$phone = $headers['header_data']['phone'];

					$tel_number = '';
					if ( isset( $headers['header_data']['tel_number'] ) && ! empty( $headers['header_data']['tel_number'] ) ) {
						$tel_number = $headers['header_data']['tel_number'];
					}

					?>

                    <li class="wfacp_header_ph <?php echo $hide_sec; ?>">
                        <a href="tel:<?php echo $tel_number; ?>"><span class="wfacp-hd-list-phn"><?php echo $phone; ?></span></a>

                    </li>


                </ul>
            </div>
        </div>
    </div>
</header>
