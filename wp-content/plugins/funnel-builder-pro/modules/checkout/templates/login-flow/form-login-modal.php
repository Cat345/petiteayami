<div class="wfacp-quickv-opacity"></div>
<div class="wfacp-quickv-panel">
    <div class="wfacp-quickv-preloader wfacp-quickv-opl">
        <div class="wfacp-quickv-speeding-wheel"></div>
    </div>
    <div class="wfacp-quickv-content_wrap">
        <div class="wfacp-quickv-content-inner-wrap">
            <div class="wfacp-quickv-content-inner-container">
                <div class="wfacp-quickv-main">
                    <div class="wfacp-quickv-modal">
                        <div id="funnelkitLoginModal">
                            <div id="funnelkitLoginForm">
								<?php

								$form_login_top_heading = __( 'Login', 'woocommerce' );
								\WFACP_Common::get_template( 'login-flow/form-top-section.php', [ 'form_login_top_heading' => $form_login_top_heading ] );
								\WFACP_Common::get_template( 'login-flow/form-login.php' );

								?>
                            </div>
                            <!-- Reset password form template -->
                            <div id="funnelkitResetPasswordForm">

								<?php
								$form_login_top_heading = __( 'Lost your password?', 'woocommerce' );
								\WFACP_Common::get_template( 'login-flow/form-top-section.php', [ 'form_login_top_heading' => $form_login_top_heading ] );
								\WFACP_Common::get_template( 'login-flow/form-lost-password.php' ); ?>
                            </div>
                            <div id="funnelkitResetPasswordMessage"><div class="wfacp_notice_list"></div></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



