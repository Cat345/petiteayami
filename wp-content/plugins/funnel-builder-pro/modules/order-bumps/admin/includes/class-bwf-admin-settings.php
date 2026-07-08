<?php
/**
 * Class to control Settings and its behaviour accross the buildwoofunnels
 *
 * @author buildwoofunnels
 */
if ( ! class_exists( 'BWF_Admin_Settings' ) ) {

	#[\AllowDynamicProperties]
	class BWF_Admin_Settings {

		private static $ins = null;

		public function __construct() {
			$is_admin_enabled = ! class_exists( 'WFFN_Pro_Bump_Support' )
				|| ! method_exists( 'WFFN_Pro_Bump_Support', 'is_admin_enabled' )
				|| WFFN_Pro_Bump_Support::is_admin_enabled();
			if ( ! $is_admin_enabled ) {
				return;
			}
			add_action( 'admin_menu', array( $this, 'maybe_register_admin_menu' ), 900 );
			add_action( 'admin_init', array( $this, 'maybe_open_correct_settings' ), - 1 );
		}

		public static function get_instance() {

			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function maybe_register_admin_menu() {
			global $submenu;
			if ( isset( $submenu['woofunnels'] ) ) {
				foreach ( $submenu['woofunnels'] as $menu ) {
					if ( 'woofunnels_settings' === $menu[2] ) {
						$found = 1;
						break;
					}
				}
			}
			$user = WFOB_Core()->role->user_access( 'menu', 'read' );
			if ( empty( $found ) && false !== $user ) {
				add_submenu_page( 'woofunnels', __( 'Settings', 'woofunnels-order-bump' ), __( 'Settings', 'woofunnels-order-bump' ), $user, 'woofunnels_settings', array( $this, '_callback' ) );
			}
		}
		public function _callback() {
		}
		public function maybe_open_correct_settings() {
			if ( is_admin() && 'woofunnels_settings' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
				$get_all_registered_settings = apply_filters( 'woofunnels_global_settings', array() );
				usort(
					$get_all_registered_settings,
					function ( $a, $b ) {
						if ( $a['priority'] === $b['priority'] ) {
							return 0;
						}

						return ( $a['priority'] < $b['priority'] ) ? - 1 : 1;
					}
				);
				$first_menu = array_values( $get_all_registered_settings )[0];
				wp_safe_redirect( $first_menu['link'] );
				exit;
			}
		}

		public function render_tab_html( $current ) {
			$get_all_registered_settings = apply_filters( 'woofunnels_global_settings', array() );

			if ( is_array( $get_all_registered_settings ) && count( $get_all_registered_settings ) > 0 ) {
				usort(
					$get_all_registered_settings,
					function ( $a, $b ) {
						if ( $a['priority'] === $b['priority'] ) {
							return 0;
						}

						return ( $a['priority'] < $b['priority'] ) ? - 1 : 1;
					}
				);

				?>

				<div class="bwf_menu_list_primary">
					<ul>

						<?php
						foreach ( $get_all_registered_settings as $menu ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
							$class = '';
							if ( $menu['slug'] === $current ) {
								$class = 'active';
							}
							?>
						<li class="<?php echo $class; ?>">
							<a href="<?php echo esc_url_raw( $menu['link'] ); ?>">
								<?php echo esc_attr( $menu['title'] ); ?>
							</a>
							</li>
							<?php

						}
						?>
					</ul>
				</div>
				<?php
			}
		}
	}
}
BWF_Admin_Settings::get_instance();