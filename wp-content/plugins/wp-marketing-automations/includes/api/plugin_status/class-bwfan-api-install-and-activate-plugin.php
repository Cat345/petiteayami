<?php

/**
 * Class BWFAN_API_Install_Activate_Plugin
 */
#[\AllowDynamicProperties]
class BWFAN_API_Install_Activate_Plugin extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::CREATABLE;
		$this->route  = '/plugin/install_and_activate';
	}

	public function process_api_call() {
		// basename of the plugin - plugin_folder/main_file
		$plugin     = isset( $this->args['plugin'] ) && ! empty( $this->args['plugin'] ) ? $this->args['plugin'] : '';
		$pro_plugin = isset( $this->args['plugin_pro'] ) && ! empty( $this->args['plugin_pro'] ) ? $this->args['plugin_pro'] : '';

		// actions - install | activate
		$action = isset( $this->args['action'] ) && ! empty( $this->args['action'] ) ? $this->args['action'] : '';

		$all_plugins = get_plugins();

		/** checking for exists wp stmp pro plugin and also when action is activate */
		if ( array_key_exists( $pro_plugin, $all_plugins ) && 'wp-mail-smtp/wp_mail_smtp.php' === $plugin && 'activate' === $action ) {
			$plugin = $pro_plugin;
		}

		// plugin - full zip url of the plugin
		$plugin_url = isset( $this->args['url'] ) && ! empty( $this->args['url'] ) ? esc_url_raw( wp_unslash( $this->args['url'] ) ) : '';

		if ( empty( $action ) ) {
			return $this->error_response( __( 'Action is missing : install or activate', 'wp-marketing-automations' ) );
		}

		return $this->install_or_activate_addon_plugins( $plugin, $plugin_url, $action );
	}

	public function install_or_activate_addon_plugins( $plugin, $plugin_url, $action ) {
		// includes the required files
		require_once BWFAN_PLUGIN_DIR . '/includes/plugin_helpers/class-bwfan-plugin-install-skin.php';
		require_once BWFAN_PLUGIN_DIR . '/includes/plugin_helpers/class-bwfan-plugin-silent-upgrader.php';

		// Do not allow WordPress to search/download translations, as this will break JS output.
		remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

		// Create the plugin upgrader with our custom skin.
		$installer = new BWFAN_Plugin_Silent_Upgrader( new BWFAN_Plugin_Install_Skin() );

		switch ( $action ) {
			case 'install':
				$result = $this->install_plugin( $installer, $plugin_url );
				break;
			case 'activate':
				$result = $this->activate_plugin( $plugin );
				break;
			default:
				return $this->success_response( __( 'Undefined error', 'wp-marketing-automations' ) );
		}

		return $result;
	}

	public function install_plugin( $installer, $plugin_url ) {
		if ( empty( $plugin_url ) ) {
			return $this->error_response( __( 'Plugin URL is missing', 'wp-marketing-automations' ) );
		}
		/**
		 * Capability check: installing a plugin requires the install_plugins capability.
		 * On multisite this is restricted to network admins, unlike manage_options.
		 */
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $this->error_response( __( "You don't have permission to install plugins", 'wp-marketing-automations' ) );
		}

		// Only allow installing ZIPs from trusted sources to prevent installing an arbitrary remote plugin.
		if ( ! $this->is_allowed_plugin_source( $plugin_url ) ) {
			return $this->error_response( __( 'Plugin source URL is not allowed', 'wp-marketing-automations' ) );
		}

		// Error check.
		if ( ! method_exists( $installer, 'install' ) ) {
			return $this->error_response( __( 'Some error occurred: install function not found', 'wp-marketing-automations' ) );
		}

		if ( ! $installer->install( $plugin_url ) ) {
			return $this->error_response( __( 'Some error occurred: installation failed', 'wp-marketing-automations' ) );
		}

		$result['is_installed'] = true;
		$result['msg']          = esc_html__( 'Plugin installed', 'wp-marketing-automations' );

		$this->response_code = 200;

		return $this->success_response( $result );
	}

	/**
	 * Whether a plugin ZIP URL points at a trusted source.
	 * Only HTTPS URLs on WordPress.org or FunnelKit-owned hosts are permitted.
	 *
	 * @param string $plugin_url
	 *
	 * @return bool
	 */
	public function is_allowed_plugin_source( $plugin_url ) {
		$scheme = wp_parse_url( $plugin_url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $plugin_url, PHP_URL_HOST );

		if ( empty( $host ) || 'https' !== strtolower( (string) $scheme ) ) {
			return false;
		}

		$host = strtolower( $host );

		$allowed_hosts = array(
			'wordpress.org',
			'funnelkit.com',
			'buildwoofunnels.com',
		);

		/**
		 * Filter the list of trusted plugin-source hosts for the install endpoint.
		 *
		 * @param array  $allowed_hosts List of allowed host suffixes.
		 * @param string $plugin_url    The requested plugin ZIP URL.
		 */
		$allowed_hosts = apply_filters( 'bwfan_allowed_plugin_source_hosts', $allowed_hosts, $plugin_url );

		foreach ( (array) $allowed_hosts as $allowed_host ) {
			$allowed_host = strtolower( $allowed_host );
			if ( $host === $allowed_host || substr( $host, - ( strlen( $allowed_host ) + 1 ) ) === '.' . $allowed_host ) {
				return true;
			}
		}

		return false;
	}

	public function activate_plugin( $plugin ) {
		if ( empty( $plugin ) ) {
			return $this->error_response( __( 'Plugin basename is missing', 'wp-marketing-automations' ) );
		}

		//Check for permissions.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return $this->error_response( __( "You don't have permission to activate plugin", 'wp-marketing-automations' ) );
		}

		// Activate the plugin silently.
		$activated = activate_plugin( $plugin );

		if ( is_wp_error( $activated ) ) {
			return $this->error_response( __( 'Some error occurred while activating the plugin', 'wp-marketing-automations' ), $activated );
		}

		/**
		 * Fire after plugin activating via the BWFAN installer.
		 */
		do_action( 'bwfan_addon_plugin_activated', $plugin );

		$result['is_activated'] = true;
		$result['msg']          = esc_html__( 'Plugin activated', 'wp-marketing-automations' );

		$this->response_code = 200;

		return $this->success_response( $result );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Install_Activate_Plugin' );
