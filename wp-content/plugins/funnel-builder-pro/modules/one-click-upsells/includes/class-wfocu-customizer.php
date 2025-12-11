<?php

if ( ! class_exists( 'WFOCU_Customizer' ) ) {
	class WFOCU_Customizer {

		private static $ins = null;
		public $orders;
		public $offer_id = 0;
		protected $offer_data = 0;
		private $template_path = '';
		private $template_url = '';
		private $template = null;
		private $template_assets = null;
		/**
		 * @var WFOCU_Customizer_Common
		 */
		private $template_ins = null;

		public function __construct() {
			if ( isset( $_REQUEST['offer_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->offer_id = wc_clean( $_REQUEST['offer_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( WFOCU_Core()->template_loader->is_customizer_preview() ) {

				/** Set host url in allowed redirect url for customizer in case wp defined site url is different then home url */
				add_filter( 'allowed_redirect_hosts', function ( $wpp, $lp_host ) {
					$wpp[] = $lp_host;

					return array_unique( $wpp );
				}, 10, 2 );

				WFOCU_Core()->template_loader->set_offer_id( $this->offer_id );
				/** Kirki */

				add_action( 'init', function () {
					require WFOCU_PLUGIN_DIR . '/admin/includes/wfocukirki/wfocukirki.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
					/** wfocukirki custom controls */
					require WFOCU_PLUGIN_DIR . '/includes/class-wfocu-wfocukirki.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
				} );

			}
			$this->template_path   = WFOCU_PLUGIN_DIR . '/templates';
			$this->template_url    = WFOCU_PLUGIN_URL . '/templates';
			$this->template_assets = WFOCU_PLUGIN_URL . '/assets';
			add_filter( 'wfocu_customizer_fieldset', array( $this, 'add_offer_confirmation_setting' ) );
			add_action( 'wp_insert_post', array( $this, 'mark_changsets_as_dismissed' ), 10, 2 );
			$this->maybe_load_customizer();
			if ( isset( $_REQUEST['wfocu_customize'] ) && $_REQUEST['wfocu_customize'] === 'loaded' && isset( $_REQUEST['offer_id'] ) && $_REQUEST['offer_id'] > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_action( 'admin_enqueue_scripts', array( $this, 'dequeue_unnecessary_customizer_scripts' ), 999 );
			}
			add_action( 'init', array( $this, 'customizer_product_check' ), 25 );

			add_action( 'init', array( $this, 'setup_offer_for_wfocukirki' ), 20 );
			add_action( 'wfocu_loaded', function () {
				require WFOCU_PLUGIN_DIR . '/includes/class-wfocu-template-group-customizer.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
				require WFOCU_PLUGIN_DIR . '/includes/class-wfocu-template-group-custom.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			} );
			/** Late priority in case themes also using wfocukirki */
			add_filter( 'wfocukirki/config', array( $this, 'wfocu_wfocukirki_configuration' ), 9999 );
			add_action( 'init', array( $this, 'wfocu_wfocukirki_fields' ), 30 );
			add_filter( 'wfocu_templates_group_customizer', array( $this, 'maybe_add_customizer_multiple_templates' ) );
		}

		public function maybe_load_customizer() {
			if ( isset( $_REQUEST['wfocu_customize'] ) && $_REQUEST['wfocu_customize'] === 'loaded' && isset( $_REQUEST['offer_id'] ) && $_REQUEST['offer_id'] > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->customize_hooks();

			}
		}

		public function customize_hooks() {
			add_action( 'customize_register', function () {
				if ( defined( 'WFOCU_MP_VERSION' ) && version_compare( WFOCU_MP_VERSION, '1.1.0', '<=' ) ) {
					wp_die( "You are using outdated version of Upstroke: Multi Product Offers, which is not compatible with UpStroke 2.0. To make changes in your template first goto your <a href='" . esc_url( admin_url( 'plugins.php?s=upstroke' ) ) . "'>plugin dashboard </a>and update <strong>Upstroke: Multi Product Offers</strong>. " );
				}
			}, 0 );

			add_filter( 'customize_register', array( $this, 'remove_sections' ), 110 );
			add_action( 'customize_save_after', array( $this, 'maybe_update_customize_save' ) );

			add_filter( 'customize_changeset_branching', '__return_true' );
			add_action( 'customize_controls_print_styles', function () {
				echo '<style>#customize-theme-controls li#accordion-panel-nav_menus,
#customize-theme-controls li#accordion-panel-widgets,
#customize-theme-controls li#accordion-section-astra-pro,
#customize-controls .customize-info .customize-help-toggle,
.ast-control-tooltip {display: none !important;}</style>';
			} );
			add_filter( 'customize_control_active', array( $this, 'control_filter' ), 10, 2 );

			add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ), 9999 );
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'maybe_remove_script_customizer' ), 10000 );

			add_action( 'customize_register', array( $this, 'add_sections' ), 101 );
			add_action( 'wfocu_before_template_load', array( $this, 'customizer_js' ) );

			add_action( 'customize_update_' . self::get_type(), array( $this, 'save' ), 10, 2 );
			add_action( 'template_redirect', array( WFOCU_Core()->template_loader, 'empty_shortcodes' ), 90 );
			add_action( 'template_redirect', array( $this, 'setup_preview' ), 99 );

			add_action( 'customize_save_validation_before', array( $this, 'add_sections1' ), 101 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'offer_confirmation_html' ), 1 );

		}

		public static function get_type() {
			return 'wfocu';
		}

		public static function get_instance() {
			if ( self::$ins === null ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Locate Template using offer meta data also setup data
		 *
		 * @param $offer_data
		 *
		 * @return mixed|null
		 */

		public function load_template( $offer_data ) {
			if ( ! empty( $offer_data ) ) {

				if ( count( get_object_vars( $offer_data ) ) > 0 ) {
					$this->offer_data = $offer_data;
					$this->template   = $offer_data->template;

					$locate_template = WFOCU_Core()->template_loader->get_group( 'customizer' )->get_template_path( $this->template, $this->offer_data );


					if ( ! empty( $locate_template ) && file_exists( $locate_template ) ) {

						$this->template_ins = include_once $locate_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

						if ( method_exists( $this->template_ins, 'get_slug' ) ) {

							$this->template_ins->set_offer_id( $this->offer_id );
							$this->template_ins->set_offer_data( $this->offer_data );
							$this->template_ins->load_hooks();
							if ( isset( $_REQUEST['customized'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								$change_set = json_decode( wc_clean( $_REQUEST['customized'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								if ( ! is_null( $change_set ) ) {
									$this->template_ins->set_changeset( $change_set );
								}
							}

							return $this->template_ins;
						}
					}

				}
			}

			return null;

		}


		/**
		 * Remove any unwanted default controls.
		 *
		 * @param WP_Customize_Manager $wp_customize
		 *
		 * @return bool
		 */
		public function remove_sections( $wp_customize ) {

			$wp_customize->remove_panel( 'themes' );
			$wp_customize->remove_control( 'active_theme' );

			/** Mesmerize theme */
			$wp_customize->remove_section( 'mesmerize-pro' );

			return true;
		}

		/**
		 * Depreciated - Storefront calling settings direct
		 * Removes the core 'Widgets' or 'Menus' panel from the Customizer.
		 *
		 * @param array $components Core Customizer components list.
		 *
		 * @return array (Maybe) modified components list.
		 */
		public function remove_extra_panels( $components ) {
			/** widgets */
			$i = array_search( 'widgets', $components, true );
			if ( false !== $i ) {
				unset( $components[ $i ] );
			}

			/** menus */
			$i = array_search( 'nav_menus', $components, true );
			if ( false !== $i ) {
				unset( $components[ $i ] );
			}

			return $components;
		}

		/**
		 * Depreciated - Storefront calling settings direct
		 * Remove any unwanted default panels.
		 *
		 * @param object $wp_customize
		 *
		 * @return bool
		 */
		public function remove_panels( $wp_customize ) {
			$wp_customize->get_panel( 'nav_menus' )->active_callback = '__return_false';
			$wp_customize->remove_panel( 'widgets' );

			return true;
		}

		public function control_filter( $active, $control ) {
			if ( is_null( $this->template_ins ) ) {
				return $active;
			}

			return $this->template_ins->control_filter( $control );
		}

		public function maybe_remove_script_customizer() {
			global $wp_scripts, $wp_styles;

			$accepted_scripts = array(
				0  => 'heartbeat',
				1  => 'customize-controls',
				2  => 'wfocukirki_field_dependencies',
				3  => 'customize-nav-menus',
				4  => 'customize-widgets',
				5  => 'storefront-plugin-install',
				6  => 'wfocu-modal',
				7  => 'jquery-ui-button',
				8  => 'customize-views',
				9  => 'media-editor',
				10 => 'media-audiovideo',
				11 => 'mce-view',
				12 => 'image-edit',
				13 => 'code-editor',
				14 => 'csslint',
				15 => 'wp-color-picker',
				16 => 'wp-color-picker-alpha',
				17 => 'selectWoo',
				18 => 'wfocukirki-script',
				19 => 'wfocu-control-responsive-js',
				20 => 'updates',
				21 => 'wfocukirki_panel_and_section_icons',
				22 => 'wfocukirki-custom-sections',
				23 => 'wfocu_customizer_common',
				24 => 'acf-input'
			);

			$accepted_styles = array(
				0  => 'customize-controls',
				1  => 'customize-widgets',
				2  => 'storefront-plugin-install',
				3  => 'woocommerce_admin_menu_styles',
				4  => 'woofunnels-admin-font',
				5  => 'wfocu-modal',
				6  => 'customize-nav-menus',
				7  => 'media-views',
				8  => 'imgareaselect',
				9  => 'code-editor',
				10 => 'wp-color-picker',
				11 => 'selectWoo',
				12 => 'wfocukirki-selectWoo',
				13 => 'wfocukirki-styles',
				14 => 'wfocu-control-responsive-css',
				15 => 'wfocukirki-custom-sections',
			);

			$wp_scripts->queue = $accepted_scripts;
			$wp_styles->queue  = $accepted_styles;

		}

		public function enqueue_scripts() {
			$live_or_dev = 'live';

			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				$live_or_dev = 'dev';
				$suffix      = '';
			} else {
				$suffix = '.min';
			}
			if ( is_null( $this->template_ins ) ) {
				return;
			}
			wp_enqueue_script( 'wfocu_customizer_common', $this->template_assets . '/' . $live_or_dev . '/js/customizer-common' . $suffix . '.js', array( 'customize-controls' ), WFOCU_VERSION_DEV, true );
			$template_fields = $this->template_ins->get_fields();

			$offer_data = $this->template_ins->data;

			$pd = array();

			if ( isset( $offer_data->products ) && count( get_object_vars( $offer_data->products ) ) > 0 ) {
				foreach ( $offer_data->products as $hash_key => $product ) {
					if ( isset( $product->id ) && $product->id > 0 ) {
						$pd[ 'regular_price_' . $hash_key ]  = '{{product_regular_price key="' . $hash_key . '"}}';
						$pd[ '_regular_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price key="' . $hash_key . '"}}' );

						$pd[ 'offer_price_' . $hash_key ]  = '{{product_offer_price key="' . $hash_key . '"}}';
						$pd[ '_offer_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price key="' . $hash_key . '"}}' );

						$pd[ 'product_price_full_' . $hash_key ]  = '{{product_price_full key="' . $hash_key . '"}}';
						$pd[ '_product_price_full_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_price_full key="' . $hash_key . '"}}' );

						$pd[ 'product_regular_price_raw_' . $hash_key ]  = '{{product_regular_price_raw key="' . $hash_key . '"}}';
						$pd[ '_product_regular_price_raw_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $hash_key . '"}}' );

						$pd[ 'product_offer_price_raw_' . $hash_key ]  = '{{product_offer_price_raw key="' . $hash_key . '"}}';
						$pd[ '_product_offer_price_raw_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price_raw key="' . $hash_key . '"}}' );

						$pd[ 'product_save_value_' . $hash_key ]  = '{{product_save_value key="' . $hash_key . '"}}';
						$pd[ '_product_save_value_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_save_value key="' . $hash_key . '"}}' );

						$pd[ 'product_save_percentage_' . $hash_key ]  = '{{product_save_percentage key="' . $hash_key . '"}}';
						$pd[ '_product_save_percentage_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_save_percentage key="' . $hash_key . '"}}' );

						$pd[ 'product_savings_' . $hash_key ]  = '{{product_savings key="' . $hash_key . '"}}';
						$pd[ '_product_savings_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_savings key="' . $hash_key . '"}}' );

						$pd[ 'product_single_unit_price_' . $hash_key ]  = '{{product_single_unit_price key="' . $hash_key . '"}}';
						$pd[ '_product_single_unit_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_single_unit_price key="' . $hash_key . '"}}' );

					}
				}
			}

			ob_start();

			include WFOCU_PLUGIN_DIR . '/admin/view/offer-save-template.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			$save_preset_html = ob_get_clean();

			wp_localize_script( 'wfocu_customizer_common', 'wfocu_customizer', array(
				'is_loaded'                   => 'yes',
				'save_preset_html'            => $save_preset_html,
				'offer_id'                    => $this->offer_id,
				'fields'                      => $template_fields,
				'ajax_url'                    => admin_url( 'admin-ajax.php' ),
				'preset_message'              => __( 'Make sure your customizer setting is published', 'woofunnels-upstroke-one-click-upsell' ),
				'pd'                          => $pd,
				'wfocu_nonce_save_template'   => wp_create_nonce( 'wfocu_save_template' ),
				'wfocu_nonce_delete_template' => wp_create_nonce( 'wfocu_delete_template' ),
				'wfocu_nonce_apply_template'  => wp_create_nonce( 'wfocu_apply_template' ),
			) );
		}

		public function customizer_js() {

			$template_fields = $this->template_ins->get_fields();

			$offer_data = $this->template_ins->data;

			$pd = array();
			if ( count( get_object_vars( $offer_data->products ) ) > 0 ) {
				foreach ( $offer_data->products as $hash_key => $product ) {

					if ( isset( $product->id ) && $product->id > 0 ) {
						$pd[ 'regular_price_' . $hash_key ]  = '{{product_regular_price key="' . $hash_key . '"}}';
						$pd[ '_regular_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price key="' . $hash_key . '"}}' );

						$pd[ 'offer_price_' . $hash_key ]  = '{{product_offer_price key="' . $hash_key . '"}}';
						$pd[ '_offer_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price key="' . $hash_key . '"}}' );

						$pd[ 'product_price_full_' . $hash_key ]  = '{{product_price_full key="' . $hash_key . '"}}';
						$pd[ '_product_price_full_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_price_full key="' . $hash_key . '"}}' );

						$pd[ 'product_regular_price_raw_' . $hash_key ]  = '{{product_regular_price_raw key="' . $hash_key . '"}}';
						$pd[ '_product_regular_price_raw_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $hash_key . '"}}' );

						$pd[ 'product_offer_price_raw_' . $hash_key ]  = '{{product_offer_price_raw key="' . $hash_key . '"}}';
						$pd[ '_product_offer_price_raw_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price_raw key="' . $hash_key . '"}}' );

						$pd[ 'product_save_value_' . $hash_key ]  = '{{product_save_value key="' . $hash_key . '"}}';
						$pd[ '_product_save_value_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_save_value key="' . $hash_key . '"}}' );

						$pd[ 'product_save_percentage_' . $hash_key ]  = '{{product_save_percentage key="' . $hash_key . '"}}';
						$pd[ '_product_save_percentage_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_save_percentage key="' . $hash_key . '"}}' );

						$pd[ 'product_savings_' . $hash_key ]  = '{{product_savings key="' . $hash_key . '"}}';
						$pd[ '_product_savings_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_savings key="' . $hash_key . '"}}' );

						$pd[ 'product_single_unit_price_' . $hash_key ]  = '{{product_single_unit_price key="' . $hash_key . '"}}';
						$pd[ '_product_single_unit_price_' . $hash_key ] = WFOCU_Common::maybe_parse_merge_tags( '{{product_single_unit_price key="' . $hash_key . '"}}' );

					}
				}
			}

			WFOCU_Core()->assets->localize_script( 'wfocu_customizer_live', 'wfocu_customizer', array(
				'is_loaded' => 'yes',
				'offer_id'  => $this->offer_id,
				'fields'    => $template_fields,
				'pd'        => $pd,
			) );
		}

		public function add_sections( $wp_customize ) {
			if ( is_null( $this->template_ins ) ) {
				return;
			}
			$this->template_ins->get_section( $wp_customize );

		}

		public function add_sections1( $wp_customize ) {
			$this->template_ins->get_section( $wp_customize );

		}

		public function save( $value, $WP_Customize_Setting ) {
			$this->template_ins->save( $WP_Customize_Setting->id, $value );
		}

		public function setup_preview() {
			if ( is_null( $this->template_ins ) ) {
				return;
			}
			$this->template_ins->get_view();
		}

		public function offer_confirmation_html() {
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/offer-confirmation-static.php';
		}

		public function add_offer_confirmation_setting( $customizer_data ) {

			$offer_data = $this->get_template_instance()->data;
			if ( false === $offer_data->settings->ask_confirmation ) {
				return $customizer_data;
			}
			$offer_confirmation_panel = array();

			$get_defaults = WFOCU_Core()->data->get_option();

			/** PANEL: LAYOUT */
			$offer_confirmation_panel['wfocu_offer_confirmation'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 110,
					'title'       => __( 'Offer Confirmation', 'woofunnels-upstroke-one-click-upsell' ),
					'description' => '',
				),
				'sections' => array(
					'offer_confirmation' => array(
						'data'   => array(
							'title'    => __( 'Offer Confirmation', 'woofunnels-upstroke-one-click-upsell' ),
							'priority' => 110,
						),
						'fields' => array(
							'header_text'  => array(
								'type'      => 'text',
								'label'     => __( 'Header Text', 'woofunnels-upstroke-one-click-upsell' ),
								'default'   => $get_defaults['offer_header_text'],
								'transport' => 'postMessage',

								'wfocu_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfocu-mc-heading .wfocu-mc-head-text',
									),
								),
								'priority'        => 10,
							),
							'cta_yes_text' => array(
								'type'      => 'text',
								'label'     => __( 'Accept Button Text', 'woofunnels-upstroke-one-click-upsell' ),
								'default'   => $get_defaults['offer_yes_btn_text'],
								'priority'  => 20,
								'transport' => 'postMessage',

								'wfocu_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfocu-mc-footer-btn .wfocu-mc-button',
									),
								),
							),
							'cta_no_text'  => array(
								'type'            => 'text',
								'label'           => __( 'Decline Offer Link Text', 'woofunnels-upstroke-one-click-upsell' ),
								'default'         => $get_defaults['offer_skip_link_text'],
								'priority'        => 30,
								'transport'       => 'postMessage',
								'wfocu_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfocu-mc-footer-btm-text .wfocu_skip_offer_mc',
									),
								),
							),

							/**** YES BUTTON COLOR SETTINGS START *******/
							'ct_colors'    => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Accept Button Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
								'priority' => 40,
							),

							'ct_accept_btn_state1' => array(
								'type'      => 'radio-buttonset',
								'label'     => '',
								'default'   => 'normal',
								'choices'   => array(
									'normal' => __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ),
									'hover'  => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
								),
								'transport' => 'postMessage',
								'priority'  => 50,
							),
							'yes_btn_bg_color'     => array(
								'label'           => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_bg_cl'],
								'priority'        => 60,
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'normal',
										'operator' => '==',
									),
								),
								'transport'       => 'postMessage',
								'wfocu_transport' => array(
									array(
										'type'     => 'css',
										'internal' => true,
										'prop'     => array( 'background-color' ),
										'elem'     => '.wfocu-sidebar-cart .wfocu-mc-button',
									),

								),
							),

							'yes_btn_text_color'   => array(
								'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_txt_cl'],
								'priority'        => 70,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'normal',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(
									array(
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfocu-sidebar-cart .wfocu-mc-button',
										'internal' => true,
									),

								),
							),
							'yes_btn_shadow_color' => array(
								'label'           => __( 'Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_sh_cl'],
								'priority'        => 80,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'normal',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(
									array(
										'type'     => 'css',
										'prop'     => array( 'box-shadow', '-moz-box-shadow', '-webkit-box-shadow', '-ms-box-shadow', '-o-box-shadow' ),
										'prefix'   => '0px 4px 0px ',
										'elem'     => '.wfocu-sidebar-cart .wfocu-mc-button',
										'internal' => true,
									),

								),
							),
							'yes_btn_hover_color'  => array(
								'label'           => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_bg_cl_h'],
								'priority'        => 90,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'hover',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(
									array(
										'type'  => 'css',
										'hover' => true,
										'prop'  => array( 'background-color' ),
										'elem'  => '.wfocu-sidebar-cart .wfocu-mc-button',
									),

								),

							),

							'yes_btn_hover_color_text' => array(
								'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_txt_cl_h'],
								'priority'        => 100,
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'hover',
										'operator' => '==',
									),
								),
								'transport'       => 'postMessage',
								'wfocu_transport' => array(
									array(
										'type'  => 'css',
										'hover' => true,
										'prop'  => array( 'color' ),
										'elem'  => '.wfocu-sidebar-cart .wfocu-mc-button',
									),

								),
							),

							'yes_btn_hover_shadow_color' => array(
								'label'           => __( 'Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_yes_btn_sh_cl_h'],
								'priority'        => 110,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state1',
										'value'    => 'hover',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(

									array(
										'hover'  => true,
										'type'   => 'css',
										'prop'   => array( 'box-shadow', '-moz-box-shadow', '-webkit-box-shadow', '-ms-box-shadow', '-o-box-shadow' ),
										'prefix' => '0px 4px 0px ',
										'elem'   => '.wfocu-sidebar-cart .wfocu-mc-button',
									),

								),
							),

							/**** YES BUTTON COLOR SETTINGS ENDS *******/

							/**** NO BUTTON COLOR SETTINGS STARTS *******/
							'ct_colors3'                 => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Decline Offer Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
								'priority' => 120,
							),

							'ct_accept_btn_state2' => array(
								'type'      => 'radio-buttonset',
								'label'     => '',
								'default'   => 'normal',
								'choices'   => array(
									'normal' => __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ),
									'hover'  => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
								),
								'transport' => 'postMessage',
								'priority'  => 130,
							),
							'no_btn_color'         => array(
								'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_no_btn_txt_cl'],
								'priority'        => 140,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state2',
										'value'    => 'normal',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(
									array(
										'type'     => 'css',
										'prop'     => array( 'color' ),
										'elem'     => '.wfocu-sidebar-cart .wfocu-mc-footer-btm-text a',
										'internal' => true,
									),

								),
							),

							'no_btn_color_hover' => array(
								'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
								'type'            => 'color',
								'default'         => $get_defaults['offer_no_btn_txt_cl_h'],
								'priority'        => 150,
								'transport'       => 'postMessage',
								'active_callback' => array(
									array(
										'setting'  => 'wfocu_offer_confirmation_offer_confirmation_ct_accept_btn_state2',
										'value'    => 'hover',
										'operator' => '==',
									),
								),
								'wfocu_transport' => array(
									array(
										'hover' => true,
										'type'  => 'css',
										'prop'  => array( 'color' ),
										'elem'  => '.wfocu-sidebar-cart .wfocu-mc-footer-btm-text a',
									),

								),
							),

							/**** NO BUTTON COLOR SETTINGS ENDS *******/

							'ct_cart_opener'      => array(
								'type'    => 'custom',
								'default' => '<div class="options-title-divider">' . esc_html__( 'Offer Confirmation Opener', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',

								'priority' => 160,
							),
							'ct_cart_opener_desc' => array(
								'type'        => 'custom',
								'default'     => '',
								'description' => esc_attr__( 'This element will only display after user closes Offer Confirmation.', 'woofunnels-upstroke-one-click-upsell' ),
								'priority'    => 170,
							),
							'cart_opener_text'    => array(
								'type'      => 'text',
								'label'     => __( 'Text', 'woofunnels-upstroke-one-click-upsell' ),
								'default'   => $get_defaults['cart_opener_text'],
								'transport' => 'postMessage',

								'wfocu_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfocu-confirm-order-btn .wfocu-opener-btn-bg',
									),
								),
								'priority'        => 180,
							),

							'cart_opener_color' => array(
								'type'      => 'color',
								'label'     => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
								'default'   => $get_defaults['cart_opener_background_color'],
								'transport' => 'postMessage',

								'wfocu_transport' => array(
									array(
										'type' => 'css',
										'prop' => array( 'background-color' ),
										'elem' => '.wfocu-confirm-order-btn .wfocu-opener-btn-bg',
									),
									array(
										'type' => 'css',
										'prop' => array( 'border-right-color' ),
										'elem' => '.wfocu-confirm-order-btn .wfocu-left-arrow',

									),
								),
								'priority'        => 190,
							),

							'cart_opener_text_color' => array(
								'type'      => 'color',
								'label'     => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
								'default'   => $get_defaults['cart_opener_text_color'],
								'transport' => 'postMessage',

								'wfocu_transport' => array(
									array(
										'type' => 'css',
										'prop' => array( 'color' ),
										'elem' => '.wfocu-confirm-order-btn',
									),

								),
								'priority'        => 200,
							),

						),
					),
				),
			);

			$customizer_data[] = $offer_confirmation_panel;

			return $customizer_data;
		}

		/**
		 * @return null
		 */
		public function get_template_instance() {
			return $this->template_ins;
		}

		public function maybe_update_customize_save() {
			if ( 0 !== $this->offer_id ) {
				update_post_meta( $this->offer_id, '_wfocu_edit_last', time() );
			}
		}

		public function mark_changsets_as_dismissed( $post_id, $post ) {
			if ( ! $post instanceof WP_Post ) {
				return;
			}

			if ( 'customize_changeset' === $post->post_type ) {
				update_post_meta( $post_id, '_customize_restore_dismissed', true );
			}
		}

		public function dequeue_unnecessary_customizer_scripts() {

			if ( isset( $_REQUEST['wfocu_customize'] ) && $_REQUEST['wfocu_customize'] === 'loaded' && isset( $_REQUEST['offer_id'] ) && $_REQUEST['offer_id'] > 0 ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				/**
				 * wp-titan framework add these color pickers, that breaks our customizer page
				 */
				wp_deregister_script( 'wp-color-picker-alpha' );
				wp_dequeue_script( 'wp-color-picker-alpha' );

			}

		}

		public function customizer_product_check() {

			if ( WFOCU_Core()->template_loader->is_valid_state_for_data_setup() ) {

				$variation_field = true;
				$offer_data      = WFOCU_Core()->template_loader->product_data;
				$temp_product    = get_object_vars( $offer_data->products );
				if ( is_array( $temp_product ) && count( $temp_product ) > 0 ) {
					/** Checking for variation single product */
					if ( is_array( $temp_product ) && count( $temp_product ) > 1 ) {
						$variation_field = false;
					} else {
						/** Only 1 product */
						foreach ( $offer_data->products as $hash_key => $product ) {
							if ( isset( $product->id ) && $product->id > 0 ) {
								$product_obj = wc_get_product( $product->id );
								if ( ! empty( WFOCU_Core()->template_loader->current_template ) ) {
									WFOCU_Core()->template_loader->current_template->products_data[ $hash_key ] = array(
										'id'  => $product->id,
										'obj' => $product_obj,
									);
								}
								/** Checking if product variation and single product */
								$product_type = $product_obj->get_type();
								if ( ! in_array( $product_type, WFOCU_Common::get_variable_league_product_types(), true ) ) {
									$variation_field = false;
								}
							}
						}
					}
				} elseif ( WFOCU_Core()->template_loader->is_customizer_preview() ) {

					wp_die( esc_attr__( 'Your offer must have at least one product to show preview.', 'woofunnels-upstroke-one-click-upsell' ) );


				}
				if ( ! empty( WFOCU_Core()->template_loader->current_template ) ) {
					WFOCU_Core()->template_loader->current_template->variation_field = $variation_field;
				}
			}
		}

		public function setup_offer_for_wfocukirki() {

			if ( true === WFOCU_Core()->template_loader->is_customizer_preview() ) {
				add_action( 'customize_preview_init', array( WFOCU_Core()->template_loader, 'maybe_add_customize_preview_init' ) );

				/** wp customizer scripts and styles */
				add_action( 'wfocu_header_print_in_head', array( WFOCU_Core()->template_loader, 'load_customizer_styles' ) );
				add_action( 'wfocu_footer_before_print_scripts', array( WFOCU_Core()->template_loader, 'load_customizer_footer_before_scripts' ) );

			}

			WFOCU_Core()->template_loader->customizer_key_prefix = WFOCU_SLUG . '_c_' . WFOCU_Core()->template_loader->offer_id;

			/** Set customizer key prefix in common */
			WFOCU_Common::$customizer_key_prefix = WFOCU_Core()->template_loader->customizer_key_prefix;

			/** wfocukirki */
			if ( class_exists( 'WFOCUKirki' ) ) {
				WFOCUKirki::add_config( WFOCU_SLUG, array(
					'option_type' => 'option',
					'option_name' => WFOCU_Core()->template_loader->customizer_key_prefix,
				) );
			}
		}

		public function wfocu_wfocukirki_configuration( $path ) {
			if ( WFOCU_Core()->template_loader->is_valid_state_for_data_setup() ) {
				return array(
					'url_path' => WFOCU_PLUGIN_URL . '/admin/includes/wfocukirki/',
				);
			}

			return $path;
		}

		public function wfocu_wfocukirki_fields() {
			$temp_ins = WFOCU_Core()->template_loader->get_template_ins();


			/** if ! customizer */
			if ( ! WFOCU_Core()->template_loader->is_customizer_preview() ) {
				return;
			}

			if ( $temp_ins instanceof WFOCU_Customizer_Common && is_array( $temp_ins->customizer_data ) && count( $temp_ins->customizer_data ) > 0 ) {


				foreach ( $temp_ins->customizer_data as $panel_single ) {
					/** Panel */
					foreach ( $panel_single as $panel_key => $panel_arr ) {
						/** Section */
						if ( is_array( $panel_arr['sections'] ) && count( $panel_arr['sections'] ) > 0 ) {
							foreach ( $panel_arr['sections'] as $section_key => $section_arr ) {
								$section_key_final = $panel_key . '_' . $section_key;
								/** Fields */
								if ( is_array( $section_arr['fields'] ) && count( $section_arr['fields'] ) > 0 ) {
									foreach ( $section_arr['fields'] as $field_key => $field_data ) {
										$field_key_final = $section_key_final . '_' . $field_key;

										$field_data = array_merge( $field_data, array(
											'settings' => $field_key_final,
											'section'  => $section_key_final,
										) );

										/** unset wfocu_partial key if present as not required for wfocukirki */
										if ( isset( $field_data['wfocu_partial'] ) ) {
											unset( $field_data['wfocu_partial'] );
										}

										WFOCUKirki::add_field( WFOCU_SLUG, $field_data );

										/** Setting fields: type and element class for live preview */
										if ( isset( $field_data['wfocu_transport'] ) && is_array( $field_data['wfocu_transport'] ) ) {
											$field_key_final                      = WFOCU_Core()->template_loader->customizer_key_prefix . '[' . $field_key_final . ']';
											$temp_ins->fields[ $field_key_final ] = $field_data['wfocu_transport'];
										}
									}
								}
							}
						}
					}
				}
			}
		}

		public function maybe_add_customizer_multiple_templates( $customizer_templates ) {

			if ( defined( 'WFOCU_MP_VERSION' ) && version_compare( WFOCU_MP_VERSION, '1.1.0', '<=' ) ) {
				return array_merge( $customizer_templates, [ 'mp-grid', 'mp-list' ] );
			}

			return $customizer_templates;
		}


	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'customizer', 'WFOCU_Customizer' );
	}
}