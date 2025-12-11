
<table cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#ffffff" align="center" style="font-family: Arial, sans-serif; line-height: 1.5; color: #333333; max-width: 640px; margin: 0 auto;">
    <tbody>
    <!-- Greeting Section -->
    <tr>
        <td style="padding: 24px 20px 16px; font-size: 16px; line-height: 1.6; color: #333333;">
			<?php
			$admin_email = get_option('admin_email');

			$user = get_user_by('email', $admin_email);

			if ($user) {
				$admin_display_name = $user->first_name
					? ucfirst($user->first_name)
					: __('there', 'funnel-builder-powerpack');
			} else {
				$admin_display_name = __('there', 'funnel-builder-powerpack');
			}
			echo '<p style="margin: 0;font-size: 16px;">Hey ' . esc_html($admin_display_name) . ',</p>';
			?>        <p style="margin: 16px 0 0; font-size: 16px; line-height: 1.6;">
                Without an active license your checkout is not affected. However, you are missing on:
            </p>
        </td>
    </tr>

    <!-- Missing Features Section -->
    <?php
    $features = $totals['license']['states'][4]['modal']['features'];
    ?>
    <tr>
        <td style="padding: 8px 16px;mso-padding-alt:8px 16px 8px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                    <!-- Icon cell -->
                    <td width="18" style="vertical-align: top; text-align: center;padding:2px;mso-padding-alt:2px;">
                        <img src="<?php echo esc_url( plugins_url( 'funnel-builder-powerpack/includes/expiry-email-template/image.png', WFFN_PRO_PLUGIN_DIR ) ); ?>" alt="<?php echo esc_attr( 'Funnelkit' ); ?>" width="18" style="display: block; width: 18px; margin: 0 auto;">
                    </td>

                    <!-- Text cell -->
                    <td width="259" style="vertical-align: top;padding: 0 0 0 8px; mso-padding-alt: 0px 0px 0px 8px;">
                        <p style="margin: 0; font-family: arial,helvetica,sans-serif; font-size: 14px; line-height: 1.5; color: #000000;">
	                           <span style="color: #000000;">
              <?php echo htmlspecialchars($features[0]); ?>   </span>
                        </p>
                    </td>

                    <!-- Spacing -->
                    <td width="8"></td>

                    <!-- Icon cell -->
                    <td width="18" style="vertical-align: top; text-align: center;padding:2px;mso-padding-alt:2px;">
                        <img src="<?php echo esc_url( plugins_url( 'funnel-builder-powerpack/includes/expiry-email-template/image.png', WFFN_PRO_PLUGIN_DIR ) ); ?>" alt="<?php echo esc_attr( 'Funnelkit' ); ?>" width="18" style="display: block; width: 18px; margin: 0 auto;">
                    </td>

                    <!-- Text cell -->
                    <td width="260" style="vertical-align: top;padding: 0 0 0 8px; mso-padding-alt: 0px 0px 0px 8px;">
                        <p style="margin: 0; font-family: arial,helvetica,sans-serif; font-size: 14px; line-height: 1.5; color: #000000;">
                <span style="color: #000000;">
              <?php echo htmlspecialchars($features[1]); ?>   </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding: 8px 16px;mso-padding-alt:8px 16px 8px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                    <!-- Icon cell -->
                    <td width="18" style="vertical-align: top; text-align: center;padding:2px;mso-padding-alt:2px;">
                        <img src="<?php echo esc_url( plugins_url( 'funnel-builder-powerpack/includes/expiry-email-template/image.png', WFFN_PRO_PLUGIN_DIR ) ); ?>" alt="<?php echo esc_attr( 'Funnelkit' ); ?>" width="18" style="display: block; width: 18px; margin: 0 auto;">
                    </td>

                    <!-- Text cell -->
                    <td width="259" style="vertical-align: top;padding: 0 0 0 8px; mso-padding-alt: 0px 0px 0px 8px;">
                        <p style="margin: 0; font-family: arial,helvetica,sans-serif; font-size: 14px; line-height: 1.5; color: #000000;">
	                        <?php echo htmlspecialchars($features[2]); ?>
                        </p>
                    </td>

                    <!-- Spacing -->
                    <td width="8"></td>

                    <!-- Icon cell -->
                    <td width="18" style="vertical-align: top; text-align: center;padding:2px;mso-padding-alt:2px;">
                        <img src="<?php echo esc_url( plugins_url( 'funnel-builder-powerpack/includes/expiry-email-template/image.png', WFFN_PRO_PLUGIN_DIR ) ); ?>" alt="<?php echo esc_attr( 'Funnelkit' ); ?>" width="18" style="display: block; width: 18px; margin: 0 auto;">
                    </td>

                    <!-- Text cell -->
                    <td width="260" style="vertical-align: top;padding: 0 0 0 8px; mso-padding-alt: 0px 0px 0px 8px;">
                        <p style="margin: 0; font-family: arial,helvetica,sans-serif; font-size: 14px; line-height: 1.5; color: #000000;">
                <span style="color: #000000;">
             <?php echo htmlspecialchars($features[3]); ?>  </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Revenue Section -->
    <?php
    $total_value =$totals['totals']['raw_total'];

    if ($total_value != 0) { ?>
    <tr>
        <td style="padding: 16px 20px;">
            <p style="font-size: 16px;font-weight: 600;">FunnelKit has generated revenue worth</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="width: 100%;">
                <tr>
                    <td style="width: 50%; padding: 0px 0px 16px 0;mso-padding-alt: 0px 0px 16px 0px;">
                        <p style="font-size: 14px; margin: 0; color: #353030;">Total Orders</p>
                        <p style="font-size: 32px; margin: 0; font-weight: 700;"><?php echo $totals['totals']['orders']; ?></p>
                    </td>
                    <td style="width: 50%; padding: 0px 16px 16px 16px;mso-padding-alt:0 16px 16px 16px;">
                        <p style="font-size: 14px; margin: 0; color: #353030;">Total Revenue</p>
                        <p style="font-size: 32px; margin: 0; font-weight: 700;"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol"><?php echo $totals['totals']['total']; ?></span></bdi></span></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php } ?>
    <!-- Call-to-Action Section -->
    <tr>
        <td style="padding:16px 16px 0;mso-padding-alt:16px;">
            <p style="font-size: 16px; color: #333333;">Don't miss out on the additional revenue. This problem is easy to fix.</p>
        </td>
    </tr>
    <tr style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px;"><td class="bwfbe-block-btn bwfbe-block-btn-f41b40e" align="left" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px; padding: 0 16px 16px 16px; mso-padding-alt:16px 16px 16px 0;"><!--[if mso | IE]><span style="margin-left:16px"><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px; width: auto; border-collapse: separate;" role="presentation" class="bwfbe-block-btn-container"><tbody><tr style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px;"><td class="bwfbe-btn-text-wrap" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0px; font-size: 16px; cursor: auto; border-style: none; background-color: #E15334; font-size: 16px; border-radius: 8px 8px 8px 8px; mso-padding-alt: 10px 20px; mso-padding-alt: 10px 24px 10px 24px; text-align: center; line-height: 1.5;" bgcolor="#E15334" align="center"><a href="<?php echo $button_url; ?>" target="_blank" class="bwfbe-block-btn-content" style="line-height: 1.5; text-decoration: none; padding: 10px 20px; padding: 10px 24px 10px 24px; mso-padding-alt: 0; background-color: #E15334; font-size: 16px; font-family: arial,helvetica,sans-serif; display: inline-block; text-decoration: none; text-transform: none; color: #ffffff; border-radius: 8px 8px 8px 8px;"><strong>Renew Now</strong></a></td></tr></tbody></table><!--[if mso | IE]></span><![endif]--></td></tr>

    <!-- Footer Section -->
    <tr>
        <td style="padding: 16px 20px; font-size: 14px; line-height: 1.6; color: #353030;">
            <p style="margin: 0;">Over 820+ 5 star reviews show that FunnelKit users trust our top-rated support for their online business. Need help? <a href="<?php echo $support; ?>" target="_blank" style="color:#0073AA">Contact FunnelKit Support</a></p>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 20px; font-size: 14px; line-height: 1.6; color: #353030;">
            <p style="margin: 0;">Best Wishes,<br /><strong>Team FunnelKit</strong></p>
        </td>
    </tr>
    </tbody>
</table>