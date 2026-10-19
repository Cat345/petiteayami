<?php
defined( 'ABSPATH' ) || exit;
?>
<table cellpadding="0" cellspacing="0" border="0" align="center" role="presentation"
       style="border-collapse: collapse; width: 640px; margin: 0 auto; background-color: #ffffff;" width="640">
    <tbody>
    <tr>
        <td style="padding: 16px; background-color: #ffffff;" bgcolor="#ffffff">
            <table cellpadding="0" cellspacing="0" border="0" width="100%"
                   style="border-collapse: collapse; width: 100%; font-family: Arial, Helvetica, sans-serif;">
                <tbody>
                <?php
                $wffn_total_tiles = count( $tile_data );
                foreach ( $tile_data as $wffn_key => $wffn_tile ) {
	                ?>
                    <tr style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px;">
		                <?php
		                foreach ( $wffn_tile as $wffn_inner_key => $wffn_col ) {
			                $wffn_padding = ( 0 === $wffn_inner_key % 2 ) ? 'padding-right: 10px;' : 'padding-left: 0;';
			                ?>
                            <td class="metric-cell" id="total-contacts" style="line-height: 1.5; width: 50%; vertical-align: top; <?php echo esc_attr($wffn_padding); ?>" width="50%" valign="top">
                                <table cellpadding="0" cellspacing="0" border="0" style="width: 100%; line-height: 1.5;" width="100%">
                                    <tr>
                                        <td style="font-family: Arial, Helvetica, sans-serif; font-size: 16px; color: #000000; padding-bottom: 5px;">
                                            <b><?php echo esc_html($wffn_col['text']); ?></b>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-family: Arial, Helvetica, sans-serif; font-size: 48px; font-weight: bold; color: #000000; padding-bottom: 5px;">
							                <?php echo esc_html($wffn_col['count']); ?>
							                <?php if ( ! empty( $wffn_col['count_suffix'] ) ): ?>
                                                <span style="font-size: 18px; font-weight: normal;">
                                                <?php echo esc_html( $wffn_col['count_suffix'] ); ?>
                    </span>
							                <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-family: Arial, Helvetica, sans-serif;">
                <span style="color: <?php echo ! empty( $wffn_col['percentage_change_positive'] ) ? '#089D61' : '#FF0000'; ?>; font-size: 16px;">
                <?php echo esc_html( $wffn_col['percentage_change'] ); ?>

                </span>
                                            <span style="color: #666666; font-size: 14px;">
                                            <?php echo esc_html( $wffn_col['previous_text'] ); ?>

                </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>

			                <?php
		                }
		                ?>
                    </tr>
	                <?php if ( ( $wffn_key + 1 ) !== $wffn_total_tiles ) { ?>
                        <tr style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px;">
                            <td class="spacer-row" colspan="2" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px; line-height: 1.5; height: 32px;" height="32"></td>
                        </tr>
	                <?php } ?>
	                <?php
                }
                ?>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
