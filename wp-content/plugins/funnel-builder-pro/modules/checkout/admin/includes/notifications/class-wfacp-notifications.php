<?php

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Notification' ) ) {
	class WFACP_Notification {
		protected $active_cache_plugins = [];
		private static $instance = null;

		/**
		 * Array values
		 *
		 * 'w3-total-cache'  => [
		 * 'type'    => 'wf_warning',
		 * 'buttons' => [
		 * 'setting' => [
		 * 'name'   => 'Go To Settings',
		 * 'class'  => [ 'any_class' ],
		 * 'url'    => '#',
		 *    'target' => '_blank',
		 * ],
		 * ],
		 * ]
		 *
		 * @var array
		 */
		protected $default_plugins_list = [
			'w3-total-cache'  => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',
					],
				],
			],
			'wp-cache'        => [
				'type'    => 'wf_warning',
				'class'   => [ 'custom_w3_total_cache_wrap' ],
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',
					],
				],
			],
			'wpFastestCache'  => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',


					],
				],
			],
			'wp-rocket'       => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',


					],
				],
			],
			'comet-cache'     => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',


					],
				],

			],
			'litespeed-cache' => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',


					],
				],
			],
			'plugin'          => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',


					],

				],
			],
			'cachify'         => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',
					],
				],
			],
			'simple-cache'    => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',
					],
				],
			],
			'wp-hummingbird'  => [
				'type'    => 'wf_warning',
				'buttons' => [
					'setting' => [
						'name' => 'Go To Settings',
					],
				],


			],
		];

		protected $plugins_settings_url = [

			'w3-total-cache'  => [
				'name'              => 'W3 Total Cache',
				'page_file'         => 'admin.php',
				'file_name'         => 'w3-total-cache/w3-total-cache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/w3-total-cache/',
				'page_arguments'    => [
					'page' => 'w3tc_pgcache',
				],
			],
			'wp-cache'        => [
				'name'              => 'WP Super Cache',
				'page_file'         => 'options-general.php',
				'file_name'         => 'wp-super-cache/wp-cache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/wp-super-cache/',
				'page_arguments'    => [
					'page' => 'wpsupercache',
					'tab'  => 'settings',
				],
			],
			'wpFastestCache'  => [
				'name'              => 'WP Fastest Cache',
				'page_file'         => 'admin.php',
				'file_name'         => 'wp-fastest-cache/wpFastestCache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/wp-fastest-cache/',
				'page_arguments'    => [
					'page' => 'wpfastestcacheoptions',
				],

			],
			'wp-rocket'       => [
				'name'              => 'WP Rocket Cache',
				'page_file'         => 'options-general.php',
				'file_name'         => 'wp-rocket/wp-rocket.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/wp-rocket/',
				'page_arguments'    => [
					'page' => 'wprocket',
				],

			],
			'comet-cache'     => [
				'name'              => 'Comet Cache',
				'page_file'         => 'admin.php',
				'file_name'         => 'comet-cache/comet-cache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/comet-cache/',
				'page_arguments'    => [
					'page' => 'comet_cache',
				],


			],
			'litespeed-cache' => [
				'name'              => 'LiteSpeed Cache',
				'page_file'         => 'admin.php',
				'file_name'         => 'litespeed-cache/litespeed-cache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/litespeed-cache/',
				'page_arguments'    => [
					'page' => 'lscache-settings',
				],

			],
			'plugin'          => [
				'name'              => 'Hyper Cache',
				'page_file'         => 'options-general.php',
				'file_name'         => 'hyper-cache/plugin.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/hyper-cache/',
				'page_arguments'    => [
					'page' => 'hyper-cache/options.php',
				],

			],
			'cachify'         => [
				'name'              => 'Cachify Cache',
				'page_file'         => 'options-general.php',
				'file_name'         => 'cachify/cachify.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/cachify/',
				'page_arguments'    => [
					'page' => 'cachify',
				],

			],
			'simple-cache'    => [
				'name'              => 'Simple Cache',
				'page_file'         => 'options-general.php',
				'file_name'         => 'simple-cache/simple-cache.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/simple-cache/',
				'page_arguments'    => [
					'page' => 'simple-cache',
				],
			],
			'wp-hummingbird'  => [
				'name'              => 'WP Hummingbird Cache',
				'page_file'         => 'admin.php',
				'file_name'         => 'hummingbird-performance/wp-hummingbird.php',
				'documentation_url' => 'https://funnelkit.com/docs/aerocheckout/caching/hummingbird-page-speed-optimization/',
				'page_arguments'    => [
					'page' => 'wphb-caching',
					'view' => 'page_cache',
				],


			],

		];

		protected function __construct() {
			add_action( 'admin_init', [ $this, 'active_plugins_list' ] );
		}

		public function active_plugins_list() {
			$active_plugins       = $this->get_active_plugins();
			$active_cache_plugins = [];

			if ( is_array( $active_plugins ) && count( $active_plugins ) > 0 ) {

				foreach ( $this->default_plugins_list as $key => $value ) {

					if ( isset( $this->plugins_settings_url[ $key ]['file_name'] ) && in_array( $this->plugins_settings_url[ $key ]['file_name'], $active_plugins ) ) {
						$active_cache_plugins[ $key ] = $value;
					}
				}
			}

			$versionMsg = sprintf( 'Thank you for updating Aero Checkout.%s<strong>Next Step:</strong> Follow the best practices for plugin updates. Conduct a test run by checking out just as your user would. If you need any help, our %s', '</br>', "<a href='https://funnelkit.com/support' target='_blank'>support is always a quick email away.</a>", 'woofunnels-aero-checkout' );

			$current_version = WFACP_VERSION;
			$current_ver     = str_replace( '.', '_', $current_version );
			$version_key     = 'wfacp_version_' . $current_ver;

			$versionArr[ $version_key ] = [
				'html' => $versionMsg,
				'type' => 'wf_warning',
			];
			$versionStatus              = WooFunnels_Notifications::get_instance()->get_notification( $version_key, 'wfacp' );

			if ( isset( $versionStatus['error'] ) && $versionStatus['error'] == $version_key . ' Key or Notification group may be Not Available.' ) {
				$notice_check_in_db = WooFunnels_Notifications::get_instance()->get_dismiss_notification_key( 'wfacp' );
				if ( is_array( $notice_check_in_db ) && ! in_array( $version_key, $notice_check_in_db ) ) {
					WooFunnels_Notifications::get_instance()->register_notification( $versionArr, 'wfacp' );

				}
			}

			if ( ! is_array( $active_cache_plugins ) || empty( $active_cache_plugins ) ) {
				return;
			}
			$this->active_cache_plugins = $active_cache_plugins;

			$active_notices_display = WooFunnels_Notifications::get_instance()->get_dismiss_notification_key( 'wfacp' );

			if ( ! is_array( $this->active_cache_plugins ) || empty( $this->active_cache_plugins ) > 0 ) {
				return;
			}

			foreach ( $this->active_cache_plugins as $key => $value ) {
				if ( is_array( $active_notices_display ) && count( $active_notices_display ) > 0 && in_array( $key, $active_notices_display ) ) {
					continue;
				}
				$custom_arr        = [];
				$documentation_url = '#';
				$setting_url       = $this->get_settings_page_url( $this->plugins_settings_url[ $key ]['page_file'], '', $this->plugins_settings_url[ $key ]['page_arguments'] );
				if ( isset( $this->plugins_settings_url[ $key ]['documentation_url'] ) && $this->plugins_settings_url[ $key ]['documentation_url'] != '' ) {
					$documentation_url = $this->plugins_settings_url[ $key ]['documentation_url'];
				}

				$plugin_name = str_replace( '-', ' ', $key );
				if ( isset( $this->plugins_settings_url[ $key ]['name'] ) && $this->plugins_settings_url[ $key ]['name'] != '' ) {
					$plugin_name = $this->plugins_settings_url[ $key ]['name'];
				}

				$html_text = $this->get_cache_text();

				$html = sprintf( $html_text, $plugin_name, $documentation_url );

				if ( isset( $setting_url ) && $setting_url != '' ) {
					$value['buttons']['setting']['url'] = $setting_url;
				}

				$wrapperClass = '';
				if ( isset( $value['class'] ) && $value['class'] != '' ) {
					$wrapperClass = $value['class'];
				}

				$wf_notice_type = '';
				if ( isset( $value['type'] ) && $value['type'] != '' ) {
					$wf_notice_type = $value['type'];
				}

				$custom_arr[ $key ] = [
					'html'    => $html,
					'type'    => $wf_notice_type,
					'class'   => $wrapperClass,
					'buttons' => $value['buttons'],
				];
				if ( is_array( $custom_arr ) && count( $custom_arr ) > 0 ) {
					WooFunnels_Notifications::get_instance()->register_notification( $custom_arr, 'wfacp' );
				}
			}

		}

		public function get_cache_text() {

			return 'Your setup has <strong>%s</strong> plugin installed. Please exclude all the Aero checkouts pages from the cache. <a href=%s target=_blank>Learn more about setting </a>';
		}


		public function get_settings_page_url( $file_url = '', $scheme = 'admin', $arguments = [] ) {

			if ( is_array( $arguments ) && count( $arguments ) > 0 && $file_url != '' ) {
				$url = add_query_arg( $arguments, admin_url( $file_url ) );
			} else {
				$path = 'admin.php';
				$url  = admin_url( $path, $scheme );
			}

			return $url;

		}


		public function get_active_cache_plugins() {
			return $this->active_cache_plugins;
		}

		public function get_active_plugins() {
			$plugins_list = get_option( 'active_plugins', [] );

			return $plugins_list;
		}

		public function get_default_plugins_list() {
			return $this->default_plugins_list;
		}

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new WFACP_Notification();
			}

			return self::$instance;
		}

	}

	WFACP_Notification::get_instance();

}