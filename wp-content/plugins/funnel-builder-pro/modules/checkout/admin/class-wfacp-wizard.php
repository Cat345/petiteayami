<?php
if ( ! class_exists( 'WFACP_Wizard' ) ) {
	/**
	 * Class WFACP_Wizard
	 * Class controls rendering and behaviour of wizard for the FunnelKit Checkout
	 */
	#[AllowDynamicProperties]
	class WFACP_Wizard {

		public static $is_wizard_done;
		public static $step;
		public static $suffix;
		public static $steps;
		public static $license_state = null;
		public static $key = '';

		public static function init() {
			add_action( 'admin_init', array( __CLASS__, 'steps' ), 4 );
			if ( ! empty( $_GET['page'] ) && 'wfacp' === $_GET['page'] &&  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			     ! empty( $_GET['tab'] ) && WFACP_SLUG . '-wizard' === $_GET['tab']  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			) {
				add_action( 'current_screen', array( __CLASS__, 'setup_wizard' ) );
			}
		}

		public static function steps() {
			self::$steps = array(
				'welcome'  => array(
					'name' => __( 'Welcome', 'woofunnels-aero-checkout' ),
					'view' => array( __CLASS__, 'wfacp_setup_introduction' ),
				),
				'activate' => array(
					'name' => __( 'Activate', 'woofunnels-aero-checkout' ),
					'view' => array( __CLASS__, 'wfacp_setup_activate' ),
				),
				'ready'    => array(
					'name' => __( 'Ready', 'woofunnels-aero-checkout' ),
					'view' => array( __CLASS__, 'wfacp_setup_ready' ),
				),


			);
			self::$steps = apply_filters( 'wfacp_wizard_steps', self::$steps );

			return self::$steps;
		}

		public static function render_page() {

		}

		/**
		 * Show the setup wizard
		 */
		public static function setup_wizard() {

			ob_end_clean();

			self::$step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( self::$steps ) );


			//enqueue style for admin notices
			wp_enqueue_style( 'wp-admin' );
			wp_enqueue_style( 'install' );
			wp_enqueue_style( 'dashicons' );


			ob_start();
			self::setup_wizard_header();
			self::setup_wizard_steps();
			$show_content = true;
			echo '<div class="wfacp-setup-content">';

			if ( $show_content ) {
				self::setup_wizard_content();
			}
			echo '</div>';
			self::setup_wizard_footer();
			exit;
		}

		/**
		 * Setup Wizard Header
		 */
		public static function setup_wizard_header() {
			?>
            <!DOCTYPE html>
            <html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width"/>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <title><?php _e( 'Plugin &rsaquo; Setup Wizard', 'woofunnels-aero-checkout' ); ?></title>
				<?php wp_print_scripts( 'wfacp-setup' ); ?>
				<?php wp_enqueue_emoji_styles(); ?>
				<?php wp_enqueue_admin_bar_header_styles(); ?>
				<?php do_action( 'admin_print_styles' ); ?>
				<?php do_action( 'admin_print_scripts' ); ?>
				<?php //do_action( 'admin_head' );
				?>
            </head>
			<?php self::setup_css(); ?>
            <body class="wfacp-setup wp-core-ui">
            <h1 id="wc-logo"><img width="200px;" src="<?php echo WFACP_PLUGIN_URL . '/assets/img/woofunnels-logo.svg'; ?>"/>

            </h1>
			<?php
		}

		/**
		 * Output the steps
		 */
		public static function setup_wizard_steps() {
			$ouput_steps = self::$steps;
			array_shift( $ouput_steps );
			?>
            <ol class="wfacp-setup-steps">
				<?php foreach ( $ouput_steps as $step_key => $step ) : ?>
                    <li class="<?php
					$show_link = false;
					if ( $step_key === self::$step ) {
						echo 'active';
					} elseif ( array_search( self::$step, array_keys( self::$steps ) ) > array_search( $step_key, array_keys( self::$steps ) ) ) {
						echo 'done';
						$show_link = true;
					}
					?>"><?php

						echo esc_html( $step['name'] );

						?></li>
				<?php endforeach; ?>
            </ol>
			<?php
		}

		/**
		 * Setup Wizard Footer
		 */
		public static function setup_wizard_footer() {
			?>
            <a class="wc-return-to-dashboard"
               href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Return to the WordPress Dashboard', 'woofunnels-aero-checkout' ); ?>
            </a>
            <script>
                jQuery(document).ready(function () {
                    jQuery("#wfacp_verify_license").on("submit", function () {
                        jQuery(".wfacp_activate_btn_sec .step").addClass("loading");
                    });
                })
            </script>

            </body>


		<?php
		@do_action( 'admin_footer' );
		do_action( 'admin_print_footer_scripts' );
		?>
            </html>
			<?php
		}


		public static function wfacp_setup_introduction() {
			?>
            <h1><?php _e( 'Thank you for choosing FunnelKit Checkout.', 'woofunnels-aero-checkout' ); ?></h1>
            <p class="lead"><?php printf( __( 'This wizard will help you activate your license and provide important links & support options.' ), 'woofunnels-aero-checkout' ); ?></p>
            <p>It should take less than a minute to set up.</p>
            <p class="wfacp-setup-actions step">
                <a href="<?php echo esc_url( self::get_next_step_link() ); ?>"
                   class="button-primary button button-large button-next"><?php _e( 'Let\'s Go!', 'woofunnels-aero-checkout' ); ?></a>

            </p>
			<?php
		}

		public static function wfacp_setup_ready() {
			?>
            <h1><?php printf( __( 'Thank you for activating %s' ), 'woofunnels-aero-checkout' ); ?></h1>
            <h3 style="font-weight: normal;line-height: 1.5;margin: 0 0 10px;"><?php printf( __( 'We have created few sample checkout pages for you. You can edit existing pages or create new.', 'woofunnels-aero-checkout' ) ); ?></h3>
            <p style="margin: 0;"><?php printf( __( 'Click on these links below and keep them handy. You are ready to go!', 'woofunnels-aero-checkout' ) ); ?></p>

            <ul style="margin-top: 0;">
                <li><a href="https://funnelkit.com/docs/aerocheckout/getting-started/getting-familiar-with-interface/" target="_blank">Getting Familiar With Interface.</a></li>
                <li><a href="https://funnelkit.com/docs/aerocheckout/getting-started/creating-first-checkout-page/" target="_blank">Create Product Specific Order Page.</a></li>
                <li><a href="https://funnelkit.com/docs/aerocheckout/getting-started/replace-default-checkout/" target="_blank">Make Checkout Page as a global checkout.</a></li>

            </ul>


            <p class="wfacp-setup-actions step">
                If you have any concern, please create a <a href="https://funnelkit.com/support/" target="_blank">support ticket</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wfacp' ) ); ?>"
                   class="button-primary button button-large button-next"><?php _e( 'Go to FunnelKit Checkout Pages', 'woofunnels-aero-checkout' ); ?></a>

            </p>
			<?php
		}

		public static function wfacp_setup_activate() {

			?>
            <h2> <?php _e( 'Activate FunnelKit Checkout', 'woofunnels-aero-checkout' ); ?></h2>
            <form id="wfacp_verify_license" action="" method="POST">
                <input type="hidden" name="_step_name" value="license_key">
                <div class="about-text">
                    <p>
						<?php
						_e( 'Enter your FunnelKit Checkout License Key below. Your key unlocks access to dashboard updates and support.', 'woofunnels-aero-checkout' );
						echo '<br/>';
						_e( 'You can find your key on the Account', 'woofunnels-aero-checkout' );
						echo ' <a target="_blank" href="https://myaccount.funnelkit.com">' . __( 'Dashboard Page', 'woofunnels-aero-checkout' ) . '</a> ' . __( 'site.', 'woofunnels-aero-checkout' );
						?>
                    </p>
                    <p>
                        <input style="width: 100%; padding: 10px;" type="text" required="required" class="regular-text"
                               id="license_key" value="<?php echo self::$key; ?>" name="license_key"
                               placeholder="Enter Your License Key">
						<?php
						if ( self::$license_state === false ) {
							echo '<span class="wfacp_invalid_license">' . __( 'Invalid Key. Ensure that your are using valid license key. Try again.', 'woofunnels-aero-checkout' ) . '</span>';
						}
						?>
                    </p>
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'woocommerce-settings' ); ?>"/>
                    <input type="hidden" name="_redirect_link" value="<?php echo self::get_next_step_link(); ?>"/>
                </div>
                <div class="wfacp_activate_btn_sec">
                    <div class="wfacp-setup-actions step ">
                        <input class="button-primary button button-large button-next" type="submit" value="Activate" name="wfacp_verify_license">
                    </div>
                </div>

                <p>
                    Unable to find license key?
                    Follow <a target="_blank"
                              href="https://funnelkit.com/docs/aerocheckout/getting-started/installation/">this step by
                        step
                        guide</a> to find the license key.
                </p>

                <p><strong>Note:</strong> This is just a one time activation process. <i>You plugin would continue to work as it is even if your license key is expired.</i>
                    You would loose access to support and future updates if your license expires.</p>


            </form>
			<?php
		}

		public static function get_next_step_link() {
			$keys = array_keys( self::$steps );

			return add_query_arg( 'step', $keys[ array_search( self::$step, array_keys( self::$steps ) ) + 1 ], remove_query_arg( 'translation_updated' ) );
		}

		public static function setup_css() {
			?>
            <style>
                .qm-no-js {
                    display: none !important;
                }

                li[data-slug="woocommerce"] > span,
                tr[data-content="attachment"] {
                    display: none !important;
                }

                .wp-core-ui .woocommerce-button {
                    background-color: #bb77ae !important;
                    border-color: #A36597 !important;;
                    -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, .25), 0 1px 0 #A36597 !important;;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .25), 0 1px 0 #A36597 !important;;
                    text-shadow: 0 -1px 1px #A36597, 1px 0 1px #A36597, 0 1px 1px #A36597, -1px 0 1px #A36597 !important;;
                    opacity: 1;
                }

                .wfacp-setup-content ul {
                    list-style: disc
                }

                .wfacp-setup-content h1 {
                    line-height: 30px;
                }

                .wfacp-setup-content p.lead {
                    font-size: 1.2em;
                    color: #000;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 15px;
                }

                .wfacp-setup-content p.success {
                    color: #7eb62e !important;
                }

                .wfacp-setup-content p.error {
                    color: red !important;
                }

                .wfacp-setup-content p, .wfacp-setup-content table {
                    font-size: 1em;
                    line-height: 1.75em;
                    color: #666
                }

                body {
                    margin: 30px auto 24px;
                    box-shadow: none;
                    background: #f1f1f1;
                    padding: 0
                }

                #wc-logo {
                    border: 0;
                    margin: 0 0 24px;
                    padding: 0;
                    text-align: center
                }

                #wc-logo img {
                    max-width: 50%
                }

                .wfacp-setup-content {
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .13);
                    padding: 24px 24px 0;
                    background: #fff;
                    overflow: hidden;
                    zoom: 1
                }

                .wfacp-setup-content h1, .wfacp-setup-content h2, .wfacp-setup-content h3, .wfacp-setup-content table {
                    margin: 0 0 24px;
                    border: 0;
                    padding: 0;
                    color: #666;
                    clear: none
                }

                .wfacp-setup-content table {
                    margin: 0;
                }

                .wfacp-setup-content p {
                    margin: 0 0 24px
                }

                .wfacp-setup-content a {
                    color: #0091cd
                }

                .wfacp-setup-content a:focus, .wfacp-setup-content a:hover {
                    color: #111
                }

                .wfacp-setup-content .form-table th {
                    width: 35%;
                    vertical-align: top;
                    font-weight: 400
                }

                .wfacp-setup-content .form-table td {
                    vertical-align: top
                }

                .wfacp-setup-content .form-table td input, .wfacp-setup-content .form-table td select {
                    width: 100%;
                    box-sizing: border-box
                }

                .wfacp-setup-content .form-table td input[size] {
                    width: auto
                }

                .wfacp-setup-content .form-table td .description {
                    line-height: 1.5em;
                    display: block;
                    margin-top: .25em;
                    color: #999;
                    font-style: italic
                }

                .wfacp-setup-content .form-table td .input-checkbox, .wfacp-setup-content .form-table td .input-radio {
                    width: auto;
                    box-sizing: inherit;
                    padding: inherit;
                    margin: 0 .5em 0 0;
                    box-shadow: none
                }

                .wfacp-setup-content .form-table .section_title td {
                    padding: 0
                }

                .wfacp-setup-content .form-table .section_title td h2, .wfacp-setup-content .form-table .section_title td p {
                    margin: 12px 0 0
                }

                .wfacp-setup-content .form-table td, .wfacp-setup-content .form-table th {
                    padding: 12px 0;
                    margin: 0;
                    border: 0
                }

                .wfacp-setup-content .form-table td:first-child, .wfacp-setup-content .form-table th:first-child {
                    padding-right: 1em
                }

                .wfacp-setup-content .form-table table.tax-rates {
                    width: 100%;
                    font-size: .92em
                }

                .wfacp-setup-content .form-table table.tax-rates th {
                    padding: 0;
                    text-align: center;
                    width: auto;
                    vertical-align: middle
                }

                .wfacp-setup-content .form-table table.tax-rates td {
                    border: 1px solid #eee;
                    padding: 6px;
                    text-align: center;
                    vertical-align: middle
                }

                .wfacp-setup-content .form-table table.tax-rates td input {
                    outline: 0;
                    border: 0;
                    padding: 0;
                    box-shadow: none;
                    text-align: center
                }

                .wfacp-setup-content .form-table table.tax-rates td.sort {
                    cursor: move;
                    color: #ccc
                }

                .wfacp-setup-content .form-table table.tax-rates td.sort:before {
                    content: "\f333";
                    font-family: dashicons
                }

                .wfacp-setup-content .form-table table.tax-rates .add {
                    padding: 1em 0 0 1em;
                    line-height: 1em;
                    font-size: 1em;
                    width: 0;
                    margin: 6px 0 0;
                    height: 0;
                    overflow: hidden;
                    position: relative;
                    display: inline-block
                }

                .wfacp-setup-content .form-table table.tax-rates .add:before {
                    content: "\f502";
                    font-family: dashicons;
                    position: absolute;
                    left: 0;
                    top: 0
                }

                .wfacp-setup-content .form-table table.tax-rates .remove {
                    padding: 1em 0 0 1em;
                    line-height: 1em;
                    font-size: 1em;
                    width: 0;
                    margin: 0;
                    height: 0;
                    overflow: hidden;
                    position: relative;
                    display: inline-block
                }

                .wfacp-setup-content .form-table table.tax-rates .remove:before {
                    content: "\f182";
                    font-family: dashicons;
                    position: absolute;
                    left: 0;
                    top: 0
                }

                .wfacp-setup-content .wfacp-setup-plugins {
                    width: 100%;
                    border-top: 1px solid #eee
                }

                .wfacp-setup-content .wfacp-setup-plugins thead th {
                    display: none
                }

                .wfacp-setup-content .wfacp-setup-plugins .plugin-name {
                    width: 30%;
                    font-weight: 700
                }

                .wfacp-setup-content .wfacp-setup-plugins td, .wfacp-setup-content .wfacp-setup-plugins th {
                    padding: 14px 0;
                    border-bottom: 1px solid #eee
                }

                .wfacp-setup-content .wfacp-setup-plugins td:first-child, .wfacp-setup-content .wfacp-setup-plugins th:first-child {
                    padding-right: 9px
                }

                .wfacp-setup-content .wfacp-setup-plugins th {
                    padding-top: 0
                }

                .wfacp-setup-content .wfacp-setup-plugins .page-options p {
                    color: #777;
                    margin: 6px 0 0 24px;
                    line-height: 1.75em
                }

                .wfacp-setup-content .wfacp-setup-plugins .page-options p input {
                    vertical-align: middle;
                    margin: 1px 0 0;
                    height: 1.75em;
                    width: 1.75em;
                    line-height: 1.75em
                }

                .wfacp-setup-content .wfacp-setup-plugins .page-options p label {
                    line-height: 1
                }

                @media screen and (max-width: 782px) {
                    .wfacp-setup-content .form-table tbody th {
                        width: auto
                    }
                }

                .wfacp-setup-content .twitter-share-button {
                    float: right
                }

                .wfacp-setup-content .wfacp-setup-next-steps {
                    overflow: hidden;
                    margin: 0 0 24px
                }

                .wfacp-setup-content .wfacp-setup-next-steps h2 {
                    margin-bottom: 12px
                }

                .wfacp-setup-content .wfacp-setup-next-steps .wfacp-setup-next-steps-first {
                    float: left;
                    width: 50%;
                    box-sizing: border-box
                }

                .wfacp-setup-content .wfacp-setup-next-steps .wfacp-setup-next-steps-last {
                    float: right;
                    width: 50%;
                    box-sizing: border-box
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul {
                    padding: 0 2em 0 0;
                    list-style: none;
                    margin: 0 0 -.75em
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul li a {
                    display: block;
                    padding: 0 0 .75em
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .setup-product a {
                    text-align: center;
                    font-size: 1em;
                    padding: 1em;
                    line-height: 1.75em;
                    height: auto;
                    margin: 0 0 .75em;
                    opacity: 1;
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .setup-product a.button-primary {
                    background-color: #0091cd;
                    border-color: #0091cd;
                    -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, .2), 0 1px 0 rgba(0, 0, 0, .15);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .2), 0 1px 0 rgba(0, 0, 0, .15)
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul li a:before {
                    color: #82878c;
                    font: 400 20px/1 dashicons;
                    speak: none;
                    display: inline-block;
                    padding: 0 10px 0 0;
                    top: 1px;
                    position: relative;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                    text-decoration: none !important;
                    vertical-align: top
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .documentation a:before {
                    content: "\f331"
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .howto a:before {
                    content: "\f223"
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .rating a:before {
                    content: "\f155"
                }

                .wfacp-setup-content .wfacp-setup-next-steps ul .support a:before {
                    content: "\f307"
                }

                .wfacp-setup-content .updated, .wfacp-setup-content .woocommerce-language-pack, .wfacp-setup-content .woocommerce-tracker {
                    padding: 24px 24px 0;
                    margin: 0 0 24px;
                    overflow: hidden;
                    background: #f5f5f5
                }

                .wfacp-setup-content .updated p, .wfacp-setup-content .woocommerce-language-pack p, .wfacp-setup-content .woocommerce-tracker p {
                    padding: 0;
                    margin: 0 0 12px
                }

                .wfacp-setup-content .updated p:last-child, .wfacp-setup-content .woocommerce-language-pack p:last-child, .wfacp-setup-content .woocommerce-tracker p:last-child {
                    margin: 0 0 24px
                }

                .wfacp-setup-steps {
                    padding: 0 0 24px;
                    margin: 0;
                    list-style: none;
                    overflow: hidden;
                    color: #ccc;
                    width: 100%;
                    display: -webkit-inline-flex;
                    display: -ms-inline-flexbox;
                    display: inline-flex
                }

                .wfacp-setup-steps li {
                    width: 50%;
                    float: left;
                    padding: 0 0 .8em;
                    margin: 0;
                    text-align: center;
                    position: relative;
                    border-bottom: 4px solid #ccc;
                    line-height: 1.4em
                }

                .wfacp-setup-steps li:before {
                    content: "";
                    border: 4px solid #ccc;
                    border-radius: 100%;
                    width: 4px;
                    height: 4px;
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    margin-left: -6px;
                    margin-bottom: -8px;
                    background: #fff
                }

                .wfacp-setup-steps li a {
                    text-decoration: none;
                }

                .wfacp-setup-steps li.active {
                    border-color: #0091cd;
                    color: #0091cd
                }

                .wfacp-setup-steps li.active a {
                    color: #0091cd
                }

                .wfacp-setup-steps li.active:before {
                    border-color: #0091cd
                }

                .wfacp-setup-steps li.done {
                    border-color: #0091cd;
                    color: #0091cd
                }

                .wfacp-setup-steps li.done a {
                    color: #0091cd
                }

                .wfacp-setup-steps li.done:before {
                    border-color: #0091cd;
                    background: #0091cd
                }

                .wfacp-setup .wfacp-setup-actions {
                    overflow: hidden
                }

                .wfacp-setup .wfacp-setup-actions .button {
                    float: right;
                    font-size: 1.25em;
                    padding: .5em 1em;
                    line-height: 1em;
                    margin-right: .5em;
                    height: auto
                }

                .wfacp-setup .wfacp-setup-actions .button-primary {
                    margin: 0;
                    float: right;
                    opacity: 1;
                }

                .wc-return-to-dashboard {
                    font-size: .85em;
                    color: #b5b5b5;
                    margin: 1.18em 0;
                    display: block;
                    text-align: center
                }

                .dtbaker_loading_button_current {
                    color: #CCC !important;
                    text-align: center;

                }

                .wfacp-wizard-plugins li {
                    position: relative;
                }

                .wfacp-wizard-plugins li span {
                    padding: 0 0 0 10px;
                    font-size: 0.9em;
                    color: #0091cd;
                    display: inline-block;
                    position: relative;

                }

                .wfacp-wizard-plugins.installing li .spinner {
                    visibility: visible;
                }

                .wfacp-wizard-plugins li .spinner {
                    display: inline-block;
                    position: absolute;

                }

                .wfacp-setup-pages {
                    width: 100%;
                }

                .wfacp-setup-pages .check {
                    width: 35px;
                }

                .wfacp-setup-pages .item {
                    width: 90px;
                }

                .wfacp-setup-pages td,
                .wfacp-setup-pages th {
                    padding: 5px;
                }

                .wfacp-setup-pages .status {
                    display: none;
                }

                .wfacp-setup-pages.installing .status {
                    display: table-cell;
                }

                .wfacp-setup-pages.installing .status span {
                    display: inline-block;
                    position: relative;
                }

                .wfacp-setup-pages.installing .description {
                    display: none;
                }

                .wfacp-setup-pages.installing .spinner {
                    visibility: visible;
                }

                .wfacp-setup-pages .spinner {
                    display: inline-block;
                    position: absolute;

                }

                .theme-presets {
                    background-color: rgba(0, 0, 0, .03);
                    padding: 10px 20px;
                    margin-left: -25px;
                    margin-right: -25px;
                    margin-bottom: 20px;
                }

                .theme-presets ul {
                    list-style: none;
                    margin: 0px 0 15px 0;
                    padding: 0;
                    overflow-x: auto;
                    display: block;
                    white-space: nowrap;
                }

                .theme-presets ul li {
                    list-style: none;
                    display: inline-block;
                    padding: 6px;
                    margin: 0;
                    vertical-align: bottom;
                }

                .theme-presets ul li.current {
                    background: #000;
                    border-radius: 5px;
                }

                .theme-presets ul li a {
                    float: left;
                    line-height: 0;
                }

                .theme-presets ul li a img {
                    width: 160px;
                    height: auto;
                }

                .wfacp_invalid_license {
                    font-style: italic;
                    color: #dc3232;
                }

                .wfacp_activate_btn_sec {
                    text-align: right;
                    margin: 20px 0 15px;
                }

                .wfacp_activate_btn_sec .wfacp-setup-actions.step {
                    width: 95px;
                    display: inline-block;
                    position: relative;
                    margin: 0;
                    height: 34px;
                }

                .wfacp_activate_btn_sec .wfacp-setup-actions.step.loading input {
                    font-size: 0;
                    width: 95px;
                    height: 34px;
                }

                .wfacp_activate_btn_sec .wfacp-setup-actions.step.loading:after {
                    animation: spin 500ms infinite linear;
                    border: 2px solid #fff;
                    border-radius: 32px;
                    border-right-color: transparent;
                    border-top-color: transparent;
                    content: "";
                    display: block;
                    height: 16px;
                    top: 50%;
                    margin-top: -10px;
                    position: absolute;
                    width: 16px;
                    left: 0;
                    right: 0;
                    margin: -10px auto;
                }

                .wfacp_activate_btn_sec .wfacp-setup-actions.step.loading {
                    opacity: 0.8;
                    color: rgba(255, 255, 255, 0.05);
                    pointer-events: none;
                }

                .wfacp_activate_btn_sec .wfacp-setup-actions.step input.button-primary.button.button-large.button-next {
                    float: none;
                }

                @-webkit-keyframes spin {
                    0% {
                        -webkit-transform: rotate(0deg); /* Chrome, Opera 15+, Safari 3.1+ */
                        -ms-transform: rotate(0deg); /* IE 9 */
                        transform: rotate(0deg); /* Firefox 16+, IE 10+, Opera */
                    }
                    100% {
                        -webkit-transform: rotate(360deg); /* Chrome, Opera 15+, Safari 3.1+ */
                        -ms-transform: rotate(360deg); /* IE 9 */
                        transform: rotate(360deg); /* Firefox 16+, IE 10+, Opera */
                    }
                }

                @keyframes spin {
                    0% {
                        -webkit-transform: rotate(0deg); /* Chrome, Opera 15+, Safari 3.1+ */
                        -ms-transform: rotate(0deg); /* IE 9 */
                        transform: rotate(0deg); /* Firefox 16+, IE 10+, Opera */
                    }
                    100% {
                        -webkit-transform: rotate(360deg); /* Chrome, Opera 15+, Safari 3.1+ */
                        -ms-transform: rotate(360deg); /* IE 9 */
                        transform: rotate(360deg); /* Firefox 16+, IE 10+, Opera */
                    }
                }
            </style>
			<?php
		}

		/**
		 * Output the content for the current step
		 */
		public static function setup_wizard_content() {
			isset( self::$steps[ self::$step ] ) ? call_user_func( self::$steps[ self::$step ]['view'] ) : false;
		}

		public static function set_license_state( $state = false ) {
			self::$license_state = $state;
		}


		public static function set_license_key( $key = false ) {
			self::$key = $key;
		}


	}

	WFACP_Wizard::init();
}