<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_License_Config
 *
 * Builds the license payload the admin app and companion plugins read.
 *
 * The free plugin exposes get_license_config() / get_license_expiry() as a
 * cross-plugin contract but holds no license data of its own -- it returns an
 * empty default through the filters answered here. All of the logic below was
 * relocated out of the free plugin per the WP.org review.
 */
if ( ! class_exists( 'WFFN_License_Config' ) ) {
	#[AllowDynamicProperties]
	class WFFN_License_Config {

		/**
		 * @var WFFN_License_Config|null
		 */
		private static $ins = null;

		/**
		 * Registered by WFFN_Pro_Public so this class loads only when something
		 * actually asks for licence state. Nothing to hook here.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_License_Config|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * The stored record for this plugin's license, false when there is none.
		 *
		 * Read here rather than through the free plugin so the premium side owns
		 * its own licensing end to end.
		 *
		 * @return array|false
		 */
		private function get_license_record() {
			if ( ! defined( 'WFFN_PRO_PLUGIN_BASENAME' ) ) {
				return false;
			}

			if ( is_multisite() ) {
				$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

				if ( is_array( $active_plugins ) && ( in_array( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) ) {
					$woofunnels_data = get_blog_option( get_network()->site_id, 'woofunnels_plugins_info', array() );
				} else {
					$woofunnels_data = get_option( 'woofunnels_plugins_info', array() );
				}
			} else {
				$woofunnels_data = get_option( 'woofunnels_plugins_info' );
			}

			if ( ! is_array( $woofunnels_data ) || 0 === count( $woofunnels_data ) ) {
				return false;
			}

			$plugin_hash = sha1( WFFN_PRO_PLUGIN_BASENAME );

			return isset( $woofunnels_data[ $plugin_hash ] ) ? $woofunnels_data[ $plugin_hash ] : false;
		}

		/**
		 * @return bool|string true when licensed, otherwise false or a reason.
		 */
		public function get_license_status() {
			$record = $this->get_license_record();

			if ( empty( $record ) ) {
				return false;
			} elseif ( isset( $record['manually_deactivated'] ) && 1 === $record['manually_deactivated'] ) {
				return 'deactivated';
			} elseif ( isset( $record['expired'] ) && 1 === $record['expired'] ) {
				return 'expired';
			} elseif ( isset( $record['activated'] ) && 0 === $record['activated'] ) {
				return 'not-active';
			}

			return true;
		}

		/**
		 * @return bool
		 */
		public function is_license_active() {
			return true === $this->get_license_status();
		}

		/**
		 * Product hashes the licensing service keys off.
		 *
		 * @return array
		 */
		public function get_license_basename_sha1() {
			return array(
				'checkout' => '742fc61c1b455e2b1efa4154a92da8fb7f9866d3',
				'upsell'   => 'e837ebc716ca979006da34eecdce9f650ced6bef',
				'pro'      => 'ffec4bb68f0841db41213ce12305aaef7e0237f3',
				'basic'    => 'e234ca9ec3e4856bb05ea9f8ec90e7f3831b05c5',
			);
		}

		/**
		 * @return bool
		 */
		private function is_basic_exists() {
			return defined( 'WFFN_BASIC_FILE' );
		}

		/**
		 * @return string
		 */
		private function get_pro_activation_date() {
			if ( ! defined( 'WFFN_PRO_VERSION' ) ) {
				return '';
			}
			$activation_date = get_option( 'fk_fb_active_date', array() );
			if ( empty( $activation_date ) || ! isset( $activation_date['pro'] ) ) {
				$date = new DateTime( 'now' );
				$date->modify( '-10 days' );
				$activation_date['pro'] = $date->getTimestamp();
				update_option( 'fk_fb_active_date', $activation_date, false );

				return $date->format( 'Y-m-d H:i:s' );
			}

			return gmdate( 'Y-m-d H:i:s', $activation_date['pro'] );
		}

		/**
		 * @return string
		 */
		private function get_lite_activation_date() {
			$activation_date = get_option( 'fk_fb_active_date', array() );
			if ( empty( $activation_date ) || ! isset( $activation_date['lite'] ) ) {
				$date                    = new DateTime( 'now' );
				$activation_date['lite'] = $date->getTimestamp();
				update_option( 'fk_fb_active_date', $activation_date, false );

				return $date->format( 'Y-m-d H:i:s' );
			}

			return gmdate( 'Y-m-d H:i:s', $activation_date['lite'] );
		}

		/**
		 * Answers the free plugin's get_license_expiry() seam.
		 *
		 * @param string $expiry Incoming default.
		 *
		 * @return string
		 */
		public function filter_license_expiry( $expiry ) {
			return $this->get_expiry();
		}

		/**
		 * Answers the free plugin's get_license_config() seam.
		 *
		 * @param array $config      Incoming default (empty).
		 * @param bool  $expiry_only Return only expiry information.
		 * @param bool  $get_ad      Include activation dates.
		 *
		 * @return array
		 */
		public function filter_license_config( $config, $expiry_only = false, $get_ad = true ) {
			return $this->get_config( $expiry_only, $get_ad );
		}

		/**
		 * The license payload, built entirely from this plugin's own data.
		 *
		 * Call this directly rather than round-tripping through the free
		 * plugin's get_license_config() seam, which exists for other plugins.
		 *
		 * @param bool $expiry_only Return only expiry information.
		 * @param bool $get_ad      Include activation dates.
		 *
		 * @return array
		 */
		public function get_config( $expiry_only = false, $get_ad = true ) {
			if ( $expiry_only ) {
				return array(
					'f'  => array(
						'ed' => $this->get_expiry(),
					),
					'gp' => array( 2, 2 ),
				);
			}

			$license_config = array(
				'f'  => array(
					'e'  => defined( 'WFFN_PRO_VERSION' ),
					'la' => $this->is_license_active(),  // false on not exist
					// true when activated
					// false when manually deactivated
					// on expiry it could be both true and false, not recommended checking this value
					'ed' => $this->get_expiry(),
					'ib' => $this->is_basic_exists(),
					'h'  => $this->get_license_hash( 'f' ), // License validation hash
				),
				'ck' => array(
					'e'  => function_exists( 'wfacp_pro_dependency' ) && wfacp_pro_dependency(), // should cover aero, basic and pro addon
					'la' => $this->is_license_active_for_checkout(),
					'ed' => $this->get_license_expiry_for_checkout(),
					'h'  => $this->get_license_hash( 'ck' ), // License validation hash
				),
				'ul' => array(
					'e'  => function_exists( 'WFOCU_Core' ), // should cover upstroke & pro addon
					'la' => $this->is_license_active_for_upsell(),
					'ed' => $this->get_license_expiry_for_upsell(),
					'h'  => $this->get_license_hash( 'ul' ), // License validation hash
				),
				'gp' => array( 2, 2 ),
			);

			if ( true === $get_ad ) {
				$license_config['f']['adl'] = $this->get_lite_activation_date();
				$license_config['f']['ad']  = $this->get_pro_activation_date();
				$license_config['ck']['ad'] = $this->get_pro_activation_date();
				$license_config['ul']['ad'] = $this->get_pro_activation_date();
			}

			return $license_config;
		}

		private function license_fb_pro_data() {

			/** License classes ship with the premium plugin; absent on free-only installs */
			$License = class_exists( 'WooFunnels_licenses' ) ? WooFunnels_licenses::get_instance() : null;

			$data = array();
			if ( is_object( $License ) && is_array( $License->plugins_list ) && count( $License->plugins_list ) ) {
				foreach ( $License->plugins_list as $license ) {
					/**
					 * Excluding data for automation and connector addon
					 */
					if ( in_array( $license['product_file_path'], array( '7b31c172ac2ca8d6f19d16c4bcd56d31026b1bd8', '913d39864d876b7c6a17126d895d15322e4fd2e8' ), true ) ) {
						continue;
					}

					$license_data = array();
					if ( isset( $license['_data'] ) && isset( $license['_data']['data_extra'] ) ) {
						$license_data = $license['_data']['data_extra'];
						if ( isset( $license_data['api_key'] ) ) {
							$license_data['api_key'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxx' . substr( $license_data['api_key'], - 6 );
							$license_data['licence'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxx' . substr( $license_data['api_key'], - 6 );
						}
					}
					if ( $license['plugin'] === 'FunnelKit Funnel Builder Pro' || $license['plugin'] === 'FunnelKit Funnel Builder Basic' ) {
						$data = array(
							'id'                      => $license['product_file_path'],
							'label'                   => $license['plugin'],
							'type'                    => 'license',
							'key'                     => $license['product_file_path'],
							'license'                 => ! empty( $license_data ) ? $license_data : false,
							'is_manually_deactivated' => ( isset( $license['_data']['manually_deactivated'] ) && true === bwf_string_to_bool( $license['_data']['manually_deactivated'] ) ) ? 1 : 0,
							'activated'               => ( isset( $license['_data']['activated'] ) && true === bwf_string_to_bool( $license['_data']['activated'] ) ) ? 1 : 0,
							'expired'                 => ( isset( $license['_data']['expired'] ) && true === bwf_string_to_bool( $license['_data']['expired'] ) ) ? 1 : 0,
						);

					}
				}
			}

			return $data;
		}

		public function get_expiry() {

			$licenses = $this->license_fb_pro_data();

			if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
				return '';
			}

			$expiry = $licenses['license']['expires'];
			if ( '' === $expiry ) {
				return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
			}

			return $expiry;
		}


		private function license_data( $hash ) {

			/** License classes ship with the premium plugin; absent on free-only installs */
			$License = class_exists( 'WooFunnels_licenses' ) ? WooFunnels_licenses::get_instance() : null;
			if ( is_object( $License ) && is_array( $License->plugins_list ) && count( $License->plugins_list ) ) {
				foreach ( $License->plugins_list as $license ) {
					if ( $license['product_file_path'] !== $hash ) {
						continue;
					}
					if ( isset( $license['_data'] ) && isset( $license['_data']['data_extra'] ) ) {
						$license_data = $license['_data']['data_extra'];

						return array(
							'id'                      => $license['product_file_path'],
							'label'                   => $license['plugin'],
							'type'                    => 'license',
							'key'                     => $license['product_file_path'],
							'license'                 => ! empty( $license_data ) ? $license_data : false,
							'is_manually_deactivated' => ( isset( $license['_data']['manually_deactivated'] ) && true === bwf_string_to_bool( $license['_data']['manually_deactivated'] ) ) ? 1 : 0,
							'activated'               => ( isset( $license['_data']['activated'] ) && true === bwf_string_to_bool( $license['_data']['activated'] ) ) ? 1 : 0,
							'expired'                 => ( isset( $license['_data']['expired'] ) && true === bwf_string_to_bool( $license['_data']['expired'] ) ) ? 1 : 0,
						);
					}
				}
			}

			return array();
		}

		private function is_license_active_for_checkout() {
			$hashes = $this->get_license_basename_sha1();

			if ( $this->is_basic_exists() ) {
				$license_basic = $this->license_data( $hashes['basic'] );

				if ( empty( $license_basic ) ) {
					return false;
				} elseif ( isset( $license_basic['is_manually_deactivated'] ) && 1 === $license_basic['is_manually_deactivated'] ) {
					return 'deactivated';
				} elseif ( isset( $license_basic['expired'] ) && 1 === $license_basic['expired'] ) {
					return 'expired';
				} elseif ( isset( $license_basic['activated'] ) && 0 === $license_basic['activated'] ) {
					return 'not-active';
				}

				return true;
			}

			if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
				$license_pro = $this->license_data( $hashes['pro'] );
				if ( empty( $license_pro ) ) {
					return false;
				} elseif ( isset( $license_pro['is_manually_deactivated'] ) && 1 === $license_pro['is_manually_deactivated'] ) {
					return 'deactivated';
				} elseif ( isset( $license_pro['expired'] ) && 1 === $license_pro['expired'] ) {
					return 'expired';
				} elseif ( isset( $license_pro['activated'] ) && 0 === $license_pro['activated'] ) {
					return 'not-active';
				}

				return true;
			}

			if ( class_exists( 'WFACP_Core' ) ) {
				$license_checkout = $this->license_data( $hashes['checkout'] );

				if ( empty( $license_checkout ) ) {
					return false;
				} elseif ( isset( $license_checkout['is_manually_deactivated'] ) && 1 === $license_checkout['is_manually_deactivated'] ) {
					return 'deactivated';
				} elseif ( isset( $license_checkout['expired'] ) && 1 === $license_checkout['expired'] ) {
					return 'expired';
				} elseif ( isset( $license_checkout['activated'] ) && 0 === $license_checkout['activated'] ) {
					return 'not-active';
				}

				return true;
			}

			return false;
		}

		private function get_license_expiry_for_checkout() {
			$hashes = $this->get_license_basename_sha1();

			if ( $this->is_basic_exists() ) {
				$licenses = $this->license_data( $hashes['basic'] );
				if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
					return '';
				}

				if ( '' === $licenses['license']['expires'] ) {
					return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
				}

				return $licenses['license']['expires'];
			}

			if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
				$licenses = $this->license_data( $hashes['pro'] );
				if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
					return '';
				}

				if ( '' === $licenses['license']['expires'] ) {
					return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
				}

				return $licenses['license']['expires'];
			}

			if ( class_exists( 'WFACP_Core' ) ) {
				$licenses = $this->license_data( $hashes['checkout'] );
				if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
					return '';
				}

				if ( '' === $licenses['license']['expires'] ) {
					return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
				}

				return $licenses['license']['expires'];
			}

			return false;
		}

		private function is_license_active_for_upsell() {
			$hashes = $this->get_license_basename_sha1();

			if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
				$license_pro = $this->license_data( $hashes['pro'] );

				if ( empty( $license_pro ) ) {
					return false;
				} elseif ( isset( $license_pro['is_manually_deactivated'] ) && 1 === $license_pro['is_manually_deactivated'] ) {
					return 'deactivated';
				} elseif ( isset( $license_pro['expired'] ) && 1 === $license_pro['expired'] ) {
					return 'expired';
				} elseif ( isset( $license_pro['activated'] ) && 0 === $license_pro['activated'] ) {
					return 'not-active';
				}

				return true;
			}

			if ( class_exists( 'WFOCU_Core' ) ) {
				$license_upsells = $this->license_data( $hashes['upsell'] );

				if ( empty( $license_upsells ) ) {
					return false;
				} elseif ( isset( $license_upsells['is_manually_deactivated'] ) && 1 === $license_upsells['is_manually_deactivated'] ) {
					return 'deactivated';
				} elseif ( isset( $license_upsells['expired'] ) && 1 === $license_upsells['expired'] ) {
					return 'expired';
				} elseif ( isset( $license_upsells['activated'] ) && 0 === $license_upsells['activated'] ) {
					return 'not-active';
				}

				return true;
			}

			return false;
		}

		private function get_license_expiry_for_upsell() {
			$hashes = $this->get_license_basename_sha1();
			if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
				$licenses = $this->license_data( $hashes['pro'] );
				if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
					return '';
				}

				if ( '' === $licenses['license']['expires'] ) {
					return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
				}

				return $licenses['license']['expires'];
			}

			if ( class_exists( 'WFOCU_Core' ) ) {
				$licenses = $this->license_data( $hashes['upsell'] );
				if ( empty( $licenses ) || empty( $licenses['license'] ) ) {
					return '';
				}

				if ( '' === $licenses['license']['expires'] ) {
					return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
				}

				return $licenses['license']['expires'];
			}

			return '';
		}

		private function get_license_hash( $module = 'f' ) {
			$hashes = $this->get_license_basename_sha1();

			// Handle module 'ck' (checkout) - same pattern as get_license_expiry_for_checkout
			if ( 'ck' === $module ) {
				if ( $this->is_basic_exists() ) {
					$licenses = $this->license_data( $hashes['basic'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
					$licenses = $this->license_data( $hashes['pro'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				if ( class_exists( 'WFACP_Core' ) ) {
					$licenses = $this->license_data( $hashes['checkout'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				return '';
			}

			// Handle module 'ul' (upsell) - same pattern as get_license_expiry_for_upsell
			if ( 'ul' === $module ) {
				if ( defined( 'WFFN_PRO_VERSION' ) & ! $this->is_basic_exists() ) {
					$licenses = $this->license_data( $hashes['pro'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				if ( class_exists( 'WFOCU_Core' ) ) {
					$licenses = $this->license_data( $hashes['upsell'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				return '';
			}

			// Handle module 'f' (funnel builder) - check basic first, then pro
			if ( 'f' === $module ) {
				if ( $this->is_basic_exists() ) {
					$licenses = $this->license_data( $hashes['basic'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				if ( defined( 'WFFN_PRO_VERSION' ) ) {
					$licenses = $this->license_data( $hashes['pro'] );
					if ( ! empty( $licenses ) && ! empty( $licenses['license'] ) && is_array( $licenses['license'] ) ) {
						if ( isset( $licenses['license']['h'] ) && ! empty( $licenses['license']['h'] ) ) {
							return $licenses['license']['h'];
						}
					}
				}

				return '';
			}

			return '';
		}
	}

}
