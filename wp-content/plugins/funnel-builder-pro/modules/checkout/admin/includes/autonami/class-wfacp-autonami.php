<?php

/**
 * Class WFACP_Autonami
 * Class controls rendering and display of Autonami plugin use case
 */
if ( ! class_exists( 'WFACP_Autonami' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Autonami {
		private static $ins = null;

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 90 );
		}

		public function register_admin_menu() {
			if ( class_exists( 'BWFAN_Core' ) ) {
				return;
			}

			$user = WFACP_Core()->role->user_access( 'menu', 'read' );
			if ( false === $user ) {
				return;
			}

			/** Autonami plugin doesn't exists */
			add_submenu_page( 'woofunnels', 'Automations', 'Automations', $user, 'wfacp-autonami-automations', [
				$this,
				'admin_page',
			] );

			if ( isset( $_GET['page'] ) && 'wfacp-autonami-automations' === $_GET['page'] ) {
				wp_enqueue_style( 'bwf-wc-style', plugin_dir_url( __FILE__ ) . 'wfacp-landing.css', array(), '1.0' );
			}
		}

		public function admin_page() {
			if ( ! isset( $_GET['page'] ) || 'wfacp-autonami-automations' !== $_GET['page'] ) {
				return;
			}

			/** Removing admin notices */
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );

			$this->output();

			return;
		}

		protected function output() {
			ob_start();
			?>
            <div id="wfacp-autonami-automations" class="wrap">
                <div class="bwf-wc-clear bwf-wc-clear-40"></div>
                <div class="bwf-wc-section bwf-wc-center">
                    <div class="bwf-wc-h1">FunnelKit Checkout <i class="dashicons dashicons-heart"></i> Autonami</div>
                    <div class="bwf-wc-p">Now capture and recover your abandoned carts with Autonami - the free WordPress automation engine! Built by the same folks behind Aero Checkout. You'll love
                        all
                        the free features.
                    </div>
                    <div class="bwf-wc-clear bwf-wc-clear-20"></div>
					<?php $this->output_button(); ?>
                </div>
                <div class="bwf-wc-clear-60"></div>
                <div class="bwf-wc-section bwf-wc-sect-middle">
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <img class="bwf-wc-img-m" src="<?php echo plugin_dir_url( __FILE__ ) . 'Live-Email-Capturing.jpg'; ?>" alt="Autonami analytics">
                    </div>
                    <div class="bwf-wc-cont-gap"></div>
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <div class="bwf-wc-h3">Live Email Capturing</div>
                        <div class="bwf-wc-p">The moment a prospect enters their email at the checkout, it gets captured. Works for both - guest and logged-in users.</div>
                    </div>
                </div>
                <div class="bwf-wc-clear-40"></div>
                <div class="bwf-wc-section bwf-wc-sect-middle">
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <div class="bwf-wc-h3">Build Powerful Automations</div>
                        <div class="bwf-wc-p">Create cart recovery sequences with personalized coupon codes and timed delays for maximum impact.</div>
                    </div>
                    <div class="bwf-wc-cont-gap"></div>
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <img class="bwf-wc-img-m" src="<?php echo plugin_dir_url( __FILE__ ) . 'Build-Powerful-Automations.jpg'; ?>" alt="Autonami analytics">
                    </div>
                </div>
                <div class="bwf-wc-clear-40"></div>
                <div class="bwf-wc-section bwf-wc-sect-middle">
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <img class="bwf-wc-img-m" src="<?php echo plugin_dir_url( __FILE__ ) . 'Analytics.jpg'; ?>" alt="Autonami analytics">
                    </div>
                    <div class="bwf-wc-cont-gap"></div>
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <div class="bwf-wc-h3">View Detailed Analytics</div>
                        <div class="bwf-wc-p">Track your abandoned carts, recovered and lost carts to know what's working and tweak what's not.</div>
                    </div>
                </div>
                <div class="bwf-wc-clear-40"></div>
                <div class="bwf-wc-section bwf-wc-sect-middle">
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <div class="bwf-wc-h3">Post-Purchase Follow-Ups</div>
                        <div class="bwf-wc-p">Don't cut the chord post the sale. Reach out with helpful content, cross-sell offers, review requests & more.</div>
                    </div>
                    <div class="bwf-wc-cont-gap"></div>
                    <div class="bwf-wc-w bwf-wc-cont-half">
                        <img class="bwf-wc-img-m" src="<?php echo plugin_dir_url( __FILE__ ) . 'post-purchase.jpg'; ?>" alt="Autonami analytics">
                    </div>
                </div>
                <div class="bwf-wc-clear-60"></div>
                <div class="bwf-wc-section bwf-wc-center bwf-wc-section-bg-w">
                    <div class="bwf-wc-h1">Ready to recover the lost revenue?</div>
                    <div class="bwf-wc-p">Download the free version of Autonami. Import pre-built recipes with a single click. Recover lost revenue on autopilot!</div>
                    <div class="bwf-wc-clear-20"></div>
					<?php $this->output_button(); ?>
                </div>
            </div>
			<?php
			echo ob_get_clean();
		}

		protected function output_button() {
			$plugin_path = 'wp-marketing-automations/wp-marketing-automations.php';
			if ( $this->autonami_install_check() ) {
				$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_path . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin_path );
			} else {
				$activation_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wp-marketing-automations' ), 'install-plugin_wp-marketing-automations' );
			}
			echo '<a href="' . $activation_url . '" class="bwf-wc-btn">Activate Autonami</a>';
		}

		protected function autonami_install_check() {

			$path    = 'wp-marketing-automations/wp-marketing-automations.php';
			$plugins = get_plugins();

			return isset( $plugins[ $path ] );
		}
	}

	WFACP_Autonami::get_instance();
}