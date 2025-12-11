<?php
/**
 * Reset Password Form Template
 *
 * This template can be overridden by copying it to yourtheme/funnelkit/form-lost-password.php.
 *
 * HOWEVER, on occasion your plugin will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://example.com/docs/template-structure/
 * @package FunnelKit\Templates
 * @version 1.0.0
 */

?>


<?php do_action( 'funnelkit_before_lost_password_form' ); ?>

    <div class="wfacp-forgot-wrapper">
        <form method="post" class="funnelkit-ResetPassword lost_reset_password">


            <p class="wfacp-col-full wfacp-form-control-wrapper funnelkit-form-row woocommerce-form-row woocommerce-form-row--first form-row form-row-first wfacp-form-control-wrapper wfacp-mb-24 validate-required">
                <label class="wfacp-form-control-label" for="user_login"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?></label>
                <span class="woocommerce-input-wrapper">
                    <input class="woocommerce-Input woocommerce-Input--text input-text wfacp-form-control" type="text" name="user_login" id="user_login" autocomplete="username" aria-label="<?php esc_html_e( 'Username or email', 'woocommerce' ); ?>" placeholder="<?php esc_html_e( 'Username or email', 'woocommerce' ); ?>"/>
                </span>
            </p>

			<?php do_action( 'woocommerce_lostpassword_form' ); ?>

            <p class="wfacp-form-control-wrapper funnelkit-form-row form-row woocommerce-form-row form-row ">
                <input type="hidden" name="funnelkit_reset_password" value="true"/>
                <input type="hidden" name="action" value="funnelkit_reset_password"/>
                <button type="submit" class="funnelkit-Button button" value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Reset password', 'woocommerce' ); ?></button>
            </p>

            <p class="wfacp-form-control-wrapper form-row funnelkit-LoginLink wfacp-ta-center wfacp-mb-0">
                <a href="javascript:void(0)">
					<?php _e( 'Back to Login', 'woofunnels-aero-checkout' ); ?>
                <a href="javascript:void(0)" role="button" tabindex="0" alt="<?php esc_attr_e( 'Back to Login', 'woofunnels-aero-checkout' ); ?>" id="funnelkit-back-to-login">
					<?php _e( 'Back to Login', 'woofunnels-aero-checkout' ); ?>
                </a>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var backToLogin = document.getElementById('funnelkit-back-to-login');
                    if (backToLogin) {
                        backToLogin.addEventListener('keydown', function(e) {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                backToLogin.click();
                            }
                        });
                    }
                });
                </script>
            </p>

			<?php wp_nonce_field( 'funnelkit-login', 'funnelkit-lost-password-nonce' ); ?>

        </form>
    </div>
<?php
do_action( 'funnelkit_after_lost_password_form' );

