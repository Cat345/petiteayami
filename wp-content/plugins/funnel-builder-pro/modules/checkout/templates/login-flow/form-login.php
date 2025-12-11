<?php
/**
 * Login Form Template
 *
 * This template can be overridden by copying it to yourtheme/funnelkit/form-login.php.
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
<form class="woocommerce-form woocommerce-form-login" method="post">
    <div class="wfacp-login-wrapper">
		<?php do_action( 'funnelkit_login_form_start' ); ?>
		<?php do_action( 'woocommerce_login_form_start' ); ?>

        <p class="form-row wfacp-form-control-wrapper wfacp-col-full wfacp-input-form validate-required">
            <label for="username" class="wfacp-form-control-label"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>
                &nbsp;<span class="required">*</span>
            </label>

            <span class="woocommerce-input-wrapper">
                    <input type="text" class="input-text wfacp-form-control" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'Username or email address' ); ?>"/>
            </span>


        </p>
        <p class="form-row wfacp-form-control-wrapper wfacp-col-full wfacp-input-form validate-required wfacp-password-field">
            <label for="password" class="wfacp-form-control-label"><?php esc_html_e( 'Password', 'woocommerce' ); ?>
                &nbsp;<span class="required">*</span></label>
            <span class="woocommerce-input-wrapper">
            <input class="input-text wfacp-form-control" type="password" name="password" id="password" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Password' ); ?>"/>

            </span>
        </p>

		<?php
		do_action( 'woocommerce_login_form' );
		do_action( 'funnelkit_login_form' );
		?>


        <p class="form-row wfacp-form-control-wrapper wfacp-col-left-half wfacp-remember-me wfacp-mb-24">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox inline">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox " name="rememberme" type="checkbox" id="rememberme" value="forever"/>
                <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
            </label>
        </p>
        <p class="form-row wfacp-form-control-wrapper wfacp-col-left-half lost_password wfacp-text-align-right funnelkit-LostPassword lost_password wfacp-mb-24">
            <a href="javascript:void(0)" role="button" tabindex="0" id="funnelkit-lost-password"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
        </p>


        <p class="form-row wfacp-form-control-wrapper wfacp-login-button wfacp-col-full">
			<?php wp_nonce_field( 'funnelkit-login', 'funnelkit-login-nonce' ); ?>
            <input type="hidden" name="action" value="funnelkit_user_login"/>
            <button type="submit" class="funnelkit-button button funnelkit-form-login__submit" name="login" value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>
        </p>
		<?php do_action( 'woocommerce_login_form_end' ); ?>
		<?php do_action( 'funnelkit_login_form_end' ); ?>

    </div>
</form>
