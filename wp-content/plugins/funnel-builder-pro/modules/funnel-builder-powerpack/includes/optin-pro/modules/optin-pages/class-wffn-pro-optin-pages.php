<?php

defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Funnel pro optin page module
 * Class WFFN_Pro_Optin_Pages
 */
if ( ! class_exists( 'WFFN_Pro_Optin_Pages' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Optin_Pages {

		private static $ins = null;
		protected $options;
		protected $custom_options;
		const WFOP_PHONE_FIELD_SLUG = 'optin_phone';
		const FIELD_PREFIX = 'wfop_';

		/**
		 * WFFN_Pro_Optin_Pages constructor.
		 */
		public function __construct() {
			add_action( 'plugins_loaded', [ $this, 'include_compatibility_files' ] );
			add_action( 'wfopp_before_optin_customizer_settings', [ $this, 'add_pro_optin_customizer_settings' ] );
			if ( did_action( 'wfopp_loaded' ) ) {
				$this->load_optin_form_fields();
				$this->load_optin_actions();
				$this->load_autologin_file();

			} else {
				add_action( 'wfopp_loaded', array( $this, 'load_optin_form_fields' ), 11 );
				add_action( 'wfopp_loaded', array( $this, 'load_optin_actions' ), 12 );
				add_action( 'wfopp_loaded', array( $this, 'load_autologin_file' ), 12 );
			}

			add_filter( 'wfopp_localized_data', array( $this, 'add_lms_localizations' ) );
			add_filter( 'wfopp_default_actions_settings', array( $this, 'add_default_actions_settings' ) );
			add_filter( 'wfopp_page_search', array( $this, 'maybe_search_courses' ) );
			add_filter( 'wfopp_customization_default_fields', array( $this, 'add_customization_fields_defaults' ) );

			add_action( 'wp_ajax_wffn_course_search', array( $this, 'course_search' ) );
			add_action( 'wp_ajax_wffn_lifterlms_course_search', array( $this, 'lifterlms_course_search' ) );
			add_action( 'wfopp_customizeable_fields', array( $this, 'render_additional_fields' ) );
			add_action( 'wp_footer', array( $this, 'add_optin_form_footer' ) );
			add_action( 'wfopp_output_form_before', array( $this, 'maybe_optin_form_before' ), 10, 4 );
			add_action( 'wfopp_output_form_after', array( $this, 'maybe_optin_form_after' ), 10, 4 );
			add_filter( 'wfop_internal_css', array( $this, 'add_internal_css' ), 10, 2 );
			add_action( 'wfopp_output_form_tag_after', array( $this, 'show_integration_form' ), 10, 1 );
			add_action( 'wfopp_admin_crm_settings', array( $this, 'show_crm_integration_html' ), 10, 1 );
			add_filter( 'wfopp_modify_form_submit_result', array(
				$this,
				'modify_form_result_for_crm_integration'
			), 10, 2 );
			add_filter( 'wffn_optin_posted_data', array( $this, 'posted_data_phone_country' ), 10, 2 );
		}

		/**
		 * @return WFFN_Pro_Optin_Pages|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function load_files() {

		}

		public function load_optin_form_fields() {
			// load all the trigger files automatically
			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'includes/optin-pro/form-fields/class-*.php' ) as $form_field_file_name ) {
				require_once( $form_field_file_name ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}

		public function get_module_path() {
			return plugin_dir_path( WFOPP_PRO_PLUGIN_FILE ) . 'modules/optin-pages/';
		}

		public function include_compatibility_files() {
			include_once $this->get_module_path() . 'compatibilities/page-builders/elementor/class-wffn-pro-optin-pages-elementor.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once $this->get_module_path() . 'compatibilities/page-builders/divi/class-wffn-pro-optin-pages-divi.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once $this->get_module_path() . 'compatibilities/page-builders/oxygen/class-wffn-pro-optin-pages-oxygen.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include_once $this->get_module_path() . 'compatibilities/page-builders/gutenberg/class-wfop-gutenberg-extension.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		}

		/**
		 * Includes optin actions files
		 */
		public function load_optin_actions() {
			// load all the trigger files automatically
			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'includes/optin-pro/actions/class-*.php' ) as $optin_action_file_name ) {
				require_once( $optin_action_file_name ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}

		public function add_pro_optin_customizer_settings() {
			include_once __DIR__ . '/admin/views/optin-pages/pro-form-customize.phtml'; //@codingStandardsIgnoreLine
		}

		public function add_lms_localizations( $data ) {
			$data['action_fileld']['lms']          = [
				'heading'            => __( 'Assign Course', 'funnel-builder-powerpack' ),
				'lms_course'         => __( 'LMS Course', 'funnel-builder-powerpack' ),
				'assign_ld_course'   => __( 'Select Course', 'funnel-builder-powerpack' ),
				'course_placeholder' => __( 'Select Course', 'funnel-builder-powerpack' ),
				'hint'               => __( 'Enter minimum 3 letters.', 'funnel-builder-powerpack' ),
				'not_found'          => __( 'Oops! No elements found. Consider changing the search query.', 'funnel-builder-powerpack' ),
			];
			$data['lms_active']                    = false;
			$data['nonce_course_search']           = wp_create_nonce( 'wffn_course_search' );
			$data['nonce_lifterlms_course_search'] = wp_create_nonce( 'wffn_lifterlms_course_search' );
			$lms_obj                               = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_Assign_LD_Course::get_slug() );
			if ( $lms_obj instanceof WFFN_Optin_Action ) {
				$data['lms_active'] = $lms_obj->should_register();
			}

			$data['lifterlms_active']   = false;
			$data['affiliatewp_active'] = false;

			$lifterlms_obj = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_Assign_LIFTER_Course::get_slug() );
			if ( $lifterlms_obj instanceof WFFN_Optin_Action ) {
				$data['lifterlms_active'] = $lifterlms_obj->should_register();
			}

			$affiliatewp_obj = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_AffiliateWP_Lead::get_slug() );
			if ( $affiliatewp_obj instanceof WFFN_Optin_Action ) {
				$data['affiliatewp_active'] = $affiliatewp_obj->should_register();
			}

			$data['action_fileld']['lifterlms'] = [
				'heading'            => __( 'Assign Course', 'funnel-builder-powerpack' ),
				'lms_course'         => __( 'LMS Course', 'funnel-builder-powerpack' ),
				'assign_ld_course'   => __( 'Select Course', 'funnel-builder-powerpack' ),
				'course_placeholder' => __( 'Select Course', 'funnel-builder-powerpack' ),
				'hint'               => __( 'Enter minimum 3 letters.', 'funnel-builder-powerpack' ),
				'not_found'          => __( 'Oops! No elements found. Consider changing the search query.', 'funnel-builder-powerpack' ),
			];

			$data['action_fileld']['affiliatewp'] = [
				'heading' => __( 'AffiliateWP', 'funnel-builder-powerpack' ),
				'enable'  => __( 'Enable', 'funnel-builder-powerpack' )
			];

			return $data;
		}

		public function add_default_actions_settings( $data ) {
			$data['lms_course']           = 'false';
			$data['assign_ld_course']     = '';
			$data['lifterlms_course']     = 'false';
			$data['assign_lifter_course'] = '';
			$data['affiliatewp_id']       = 'false';

			return $data;
		}

		/**
		 *
		 */
		public function course_search() {
			check_admin_referer( 'wffn_course_search', '_nonce' );

			$result    = array();
			$lms_posts = array();

			$term = ( isset( $_POST['term'] ) && ( wffn_clean( $_POST['term'] ) ) ) ? wffn_clean( $_POST['term'] ) : '';

			if ( empty( $term ) ) {
				wp_send_json( $result );
			}

			$lms_obj = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_Assign_LD_Course::get_slug() );

			if ( $lms_obj instanceof WFFN_Optin_Action ) {
				$lms_posts = $lms_obj->get_courses( $term );
			}

			if ( count( $lms_posts ) > 0 ) {
				foreach ( $lms_posts as $lms_post ) {
					$result[] = [
						'id'   => $lms_post->ID,
						'name' => $lms_post->post_title
					];
				}

			}

			wp_send_json( $result );
		}

		public function maybe_search_courses( $ids ) {
			$lms_obj = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_Assign_LD_Course::get_slug() );

			if ( $lms_obj instanceof WFFN_Optin_Action ) {
				$lms_posts = $lms_obj->get_courses( $term );
				if ( count( $lms_posts ) > 0 ) {
					foreach ( $lms_posts as $lms_id ) {
						$ids[] = $lms_id->ID;
					}
				}
			}

			return $ids;
		}

		public function lifterlms_course_search() {
			check_admin_referer( 'wffn_lifterlms_course_search', '_nonce' );

			$result    = array();
			$lms_posts = array();

			$term = ( isset( $_POST['term'] ) && ( wffn_clean( $_POST['term'] ) ) ) ? wffn_clean( $_POST['term'] ) : '';

			if ( empty( $term ) ) {
				wp_send_json( $result );
			}

			$lifterlms_obj = WFOPP_Core()->optin_actions->get_integration_object( WFFN_Optin_Action_Assign_LIFTER_Course::get_slug() );

			if ( $lifterlms_obj instanceof WFFN_Optin_Action ) {
				$lms_posts = $lifterlms_obj->get_courses( $term );
			}

			if ( count( $lms_posts ) > 0 ) {
				foreach ( $lms_posts as $lms_post ) {
					$result[] = [
						'id'   => $lms_post['id'],
						'name' => $lms_post['text']
					];
				}
			}

			wp_send_json( $result );
		}

		public function render_additional_fields() {
			?>
            <div class="wffn_row_billing wfop_setting_top_border" data-cust-popover="yes" style="display:none;">
                <div class="wffn_billing_left">
                    <label for="popup_footer_text"><?php esc_html_e( 'Text Below Footer', 'funnel-builder-powerpack' ); ?></label>
                </div>
                <div class="wffn_billing_right">
                    <input id="popup_footer_text"
                           onkeyup="window.wfop_design.onChangeStylingOptions('popup_footer_text', this.value)" type="text"
                           value="<?php echo WFOPP_Core()->optin_pages->form_builder->get_form_customization_option( 'popup_footer_text', WFOPP_Core()->optin_pages->get_edit_id() ); ?>"
                           class="form-control wffn_placeholder">
                </div>
            </div>
            <div class="wffn_row_billing" data-cust-popover="yes" style="display:none;">
                <div class="wffn_billing_left">
                    <label for="popup_footer_font_size"><?php esc_html_e( 'Font Size', 'funnel-builder-powerpack' ); ?></label>
                </div>
                <div class="wffn_billing_right">
                    <input id="popup_footer_font_size"
                           onChange="window.wfop_design.onChangeStylingOptions('popup_footer_font_size', this.value)"
                           type="number"
                           value="<?php echo WFOPP_Core()->optin_pages->form_builder->get_form_customization_option( 'popup_footer_font_size', WFOPP_Core()->optin_pages->get_edit_id() ); ?>"
                           placeholder="16" class="form-control wffn_placeholder">
                </div>
            </div>
            <div class="wffn_row_billing" data-cust-popover="yes" style="display:none;">
                <div class="wffn_billing_left">
                    <label for="popup_footer_font_family"><?php esc_html_e( 'Font Family', 'funnel-builder-powerpack' ); ?></label>
                </div>
                <div class="wffn_billing_right">
                    <select id="popup_footer_font_family"
                            onchange="window.wfop_design.onChangeStylingOptions('popup_footer_font_family', this.value)"
                            placeholder="type" class="wffn_font_family">
						<?php foreach ( bwf_get_fonts_list() as $font ) {
							?>
                            <option <?php selected( WFOPP_Core()->optin_pages->form_builder->get_form_customization_option( 'popup_footer_font_family', WFOPP_Core()->optin_pages->get_edit_id() ), $font['id'], true ); ?>
                                value="<?php echo $font['id']; ?>"><?php echo $font['name']; ?></option>
							<?php
						} ?>
                    </select>
                </div>
            </div>
            <div class="wffn_row_billing" data-cust-popover="yes" style="display:none;">
                <div class="wffn_billing_left">
                    <label for="popup_footer_text_color"><?php esc_html_e( 'Text', 'funnel-builder-powerpack' ); ?></label>
                </div>
                <div class="wffn_billing_right">
                    <input id="popup_footer_text_color" name="popup_footer_text_color" type="text"
                           value="<?php echo WFOPP_Core()->optin_pages->form_builder->get_form_customization_option( 'popup_footer_text_color', WFOPP_Core()->optin_pages->get_edit_id() ); ?>"
                           class="form-control wfop_color_picker" placeholder="#ffffff">
                </div>
            </div>
			<?php
		}

		public function add_customization_fields_defaults( $defaults ) {

			$popup = array(
				'popup_heading'             => __( 'You\'re just one step away!', 'funnel-builder-powerpack' ),
				'popup_heading_color'       => '#000000',
				'popup_heading_font_size'   => '17',
				'popup_heading_font_family' => 'inherit',
				'popup_heading_font_weight' => '400',

				'popup_sub_heading'             => __( 'Enter your details below and we\'ll get you signed up', 'funnel-builder-powerpack' ),
				'popup_sub_heading_color'       => '#000000',
				'popup_sub_heading_font_size'   => '24',
				'popup_sub_heading_font_family' => 'inherit',
				'popup_sub_heading_font_weight' => '700',

				'popup_bar_pp'                => 'enable',
				'popup_bar_animation'         => 'yes',
				'popup_bar_text'              => __( '75% Complete', 'funnel-builder-powerpack' ),
				'popup_bar_width'             => '75',
				'popup_bar_height'            => '30',
				'popup_bar_font_family'       => 'inherit',
				'popup_bar_font_size'         => '16',
				'popup_bar_inner_gap'         => '4',
				'popup_bar_text_color'        => '#ffffff',
				'popup_bar_color'             => '#338d48',
				'popup_bar_bg_color'          => '#efefef',
				'popup_bar_text_wrap_classes' => '',
				'popup_bar_wrap_classes'      => '',

				'popup_footer_text'        => __( 'Your Information is 100% Secure', 'funnel-builder-powerpack' ),
				'popup_footer_font_family' => 'inherit',
				'popup_footer_font_size'   => '16',
				'popup_footer_text_color'  => '#444444',

				'popup_width'          => '600',
				'popup_padding'        => '40',
				'popup_open_animation' => 'slide-down'
			);

			return array_merge( $defaults, $popup );

		}

		public function add_optin_form_footer() {

			$optinPageId    = WFOPP_Core()->optin_pages->get_optin_id();
			$get_controller = WFOPP_Core()->form_controllers->get_integration_object( 'form' );
			if ( $optinPageId > 0 && WFOPP_Core()->optin_pages->is_wfop_page() ) {
				$get_embed_mode  = 'popover';
				$optinFields     = WFOPP_Core()->optin_pages->form_builder->get_form_fields( $optinPageId );
				$selected_design = WFOPP_Core()->optin_pages->get_page_design( $optinPageId );
				$selected_type   = isset( $selected_design['selected_type'] ) ? $selected_design['selected_type'] : '';
				if ( 'wp_editor' !== $selected_type ) {
					return;
				}

				if ( count( $optinFields ) > 0 ) {
					$customizations = WFOPP_Core()->optin_pages->form_builder->get_form_customization_option( 'all', $optinPageId );
					$font_array     = [];
					if ( 'default' !== $customizations['input_font_family'] && 'inherit' !== $customizations['input_font_family'] ) {
						$font_array[] = $customizations['input_font_family'];
					}
					if ( 'default' !== $customizations['button_font_family'] && 'inherit' !== $customizations['button_font_family'] ) {
						$font_array[] = $customizations['button_font_family'];
					}
					if ( ! empty( $font_array ) ) {
						$font_array      = array_unique( $font_array );
						$font_string     = implode( '|', $font_array );
						$google_font_url = "//fonts.googleapis.com/css?family=" . $font_string;
						wp_enqueue_style( 'wfop-google-fonts', esc_url( $google_font_url ), array(), WFFN_VERSION, 'all' );
					}

					$class = '';

					if ( $customizations['show_input_label'] === 'no' ) {
						$class = "wfop_hide_label";
					}

					$modal_effect = isset( $customizations['popup_open_animation'] ) ? $customizations['popup_open_animation'] : 'slide-down';
					/**
					 * Render popover front end popup HTML Here
					 */ ?>
                    <div class="bwf_pp_overlay bwf_pp_effect_<?php echo esc_attr( $modal_effect ) ?>">
                        <div class="bwf_pp_wrap">
                            <a class="bwf_pp_close" href="javascript:void(0)">&times;</a>
                            <div class="bwf_pp_cont">
								<?php $get_controller->frontend_render_form( $optinPageId, $get_embed_mode, $class ); ?>
                            </div>
                        </div>
                    </div>
					<?php
				}

			} else {
				return;
			}
		}

		public function maybe_optin_form_before( $optinPageId, $optin_settings, $form_mode, $customizations ) {
			if ( 'popover' === $form_mode ) {

				if ( 'enable' === $customizations['popup_bar_pp'] ) {
					$animate_class = ( isset( $customizations['popup_bar_animation'] ) && 'yes' === $customizations['popup_bar_animation'] ) ? ' bwf_pp_animate' : '';

					?>
                    <div class="pp-bar-text-wrapper <?php echo esc_attr( $customizations['popup_bar_text_wrap_classes'] ); ?>">
                        <span class="pp-bar-text above"><?php echo esc_html( $customizations['popup_bar_text'] ); ?></span>
                    </div>
                    <div class="bwf_pp_bar_wrap <?php echo esc_attr( $customizations['popup_bar_wrap_classes'] ); ?>">
                        <div class="bwf_pp_bar<?php echo esc_attr( $animate_class ); ?>" role="progressbar"
                             aria-valuenow="<?php echo esc_attr( $customizations['popup_bar_width'] ); ?>" aria-valuemin="0"
                             aria-valuemax="100">
                            <span class="pp-bar-text inside"><?php echo esc_html( $customizations['popup_bar_text'] ); ?></span>
                        </div>
                    </div>
				<?php }
				?>
                <div class="bwf_pp_opt_head"><?php echo wp_kses_post( $customizations['popup_heading'] ); ?></div>
                <div class="bwf_pp_opt_sub_head"><?php echo wp_kses_post( $customizations['popup_sub_heading'] ); ?></div>
				<?php
			}
		}

		public function maybe_optin_form_after( $optinPageId, $optin_settings, $form_mode, $customizations ) {
			if ( 'popover' === $form_mode ) { ?>
                <div class="bwf_pp_footer"><?php echo wp_kses_post( $customizations['popup_footer_text'] ); ?></div>
				<?php
			}
		}


		public function load_autologin_file() {

			include_once plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'includes/optin-pro/class-wffn-wp-user-autologin.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		}

		public function add_internal_css( $css, $customizations ) {
			$heading_color     = isset( $customizations['popup_heading_color'] ) ? "color:" . $customizations['popup_heading_color'] . ";" : "";
			$heading_font_size = isset( $customizations['popup_heading_font_size'] ) ? "font-size:" . $customizations['popup_heading_font_size'] . "px;line-height:" . ( $customizations['popup_heading_font_size'] + 8 ) . "px;" : "";

			$heading_font_family = isset( $customizations['popup_heading_font_family'] ) ? "font-family:" . $customizations['popup_heading_font_family'] . ";" : "";

			$heading_font_weight = isset( $customizations['popup_heading_font_weight'] ) ? "font-weight:" . $customizations['popup_heading_font_weight'] . ";" : "normal";

			$css['.bwf_pp_opt_head'] = $heading_color . $heading_font_size . $heading_font_family . $heading_font_weight;

			$sub_heading_color = isset( $customizations['popup_sub_heading_color'] ) ? "color:" . $customizations['popup_sub_heading_color'] . ";" : "";

			$sub_heading_font_size = isset( $customizations['popup_sub_heading_font_size'] ) ? "font-size:" . $customizations['popup_sub_heading_font_size'] . "px;line-height:" . ( $customizations['popup_sub_heading_font_size'] + 8 ) . "px;" : "";

			$sub_heading_font_family = isset( $customizations['popup_sub_heading_font_family'] ) ? "font-family:" . $customizations['popup_sub_heading_font_family'] . ";" : "";

			$sub_heading_font_weight = isset( $customizations['popup_sub_heading_font_weight'] ) ? "font-weight:" . $customizations['popup_sub_heading_font_weight'] . ";" : "normal";

			$css['body .bwf_pp_opt_sub_head'] = 'margin-bottom:20px;' . $sub_heading_color . $sub_heading_font_size . $sub_heading_font_family . $sub_heading_font_weight;

			$heading_color = ( isset( $customizations['popup_bar_pp'] ) && $customizations['popup_bar_pp'] === 'disable' ) ? "color:" . $customizations['popup_heading_color'] . ";" : "";

			$popup_bar             = ( $customizations['popup_bar_pp'] === 'disable' ) ? 'display: none;' : 'display: flex;';
			$transition            = ( ! is_admin() ) ? 'transition: all 5s ease-in-out;' : '';
			$popup_bar_width       = "width:" . $customizations['popup_bar_width'] . '%;';
			$popup_bar_height      = "height:" . $customizations['popup_bar_height'] . 'px;';
			$popup_bar_font_size   = "font-size:" . $customizations['popup_bar_font_size'] . "px;line-height:" . ( $customizations['popup_bar_font_size'] + 8 ) . "px;";
			$popup_bar_font_family = "font-family:" . $customizations['popup_bar_font_family'] . ";";
			$popup_bar_inner_gap   = "padding:" . $customizations['popup_bar_inner_gap'] . "px;";
			$popup_bar_text_color  = "color:" . $customizations['popup_bar_text_color'] . ";";
			$popup_bar_color       = "background-color:" . $customizations['popup_bar_color'] . ";";
			$popup_bar_bg_color    = "background-color:" . $customizations['popup_bar_bg_color'] . ";";

			$css['.bwf_pp_bar_wrap'] = $popup_bar . $popup_bar_bg_color . $popup_bar_height . $popup_bar_inner_gap;

			$css['.bwf_pp_bar_wrap .bwf_pp_bar'] = $popup_bar_font_size . $popup_bar_font_family . $popup_bar_text_color . $popup_bar_width . $popup_bar_color;

			$popup_footer_font_size     = "font-size:" . $customizations['popup_footer_font_size'] . "px;line-height:" . ( $customizations['popup_footer_font_size'] + 8 ) . "px;";
			$popup_footer_font_family   = "font-family:" . $customizations['popup_footer_font_family'] . ";";
			$popup_footer_text_color    = "color:" . $customizations['popup_footer_text_color'] . ";";
			$css['body .bwf_pp_footer'] = $popup_footer_font_size . $popup_footer_font_family . $popup_footer_text_color;

			$popup_width                    = isset( $customizations['popup_width'] ) ? "max-width:" . $customizations['popup_width'] . "px;" : "";
			$popup_padding                  = isset( $customizations['popup_padding'] ) ? "padding:" . $customizations['popup_padding'] . "px;" : "";
			$css['.wfop_form_preview_wrap'] = $popup_width . $popup_padding;

			$css['.wfop_form_preview_wrap'] = $popup_width . $popup_padding;
			$css['body .bwf_pp_wrap']       = $popup_width;
			$css['.bwf_pp_cont']            = $popup_padding;

			return $css;
		}

		public function show_integration_form( $optin_page_id ) {
			if ( WFOPP_Core()->optin_pages->form_builder->is_preview ) {
				return;
			}
			if ( 'true' !== WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optin_page_id, 'optin_form_enable' ) ) {
				return;
			}

			$this->render_integration_form( $optin_page_id );

		}

		public function render_integration_form( $id ) {
			$get_integration_options = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $id ); ?>
            <form action="<?php echo esc_url( $get_integration_options['formFields']['form']['action'] ); ?>"
                  method="<?php echo esc_attr( $get_integration_options['formFields']['form']['method'] ); ?>"
                  class="wfop_integration_form" style="display: none !important;">
				<?php
				unset( $get_integration_options['formFields']['form'] );
				unset( $get_integration_options['formFields']['submit'] );
				foreach ( $get_integration_options['formFields'] as $key => $value ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $inp ) {
							switch ( $key ) {
								case 'hidden':
									echo '<input type="hidden" name="' . esc_attr( $inp['name'] ) . '" value="' . esc_attr( $inp['value'] ) . '">';
									break;
								case 'select':
								case 'radio_checkbox':
								case 'textarea':
								default:
									echo '<input type="' . esc_attr( $inp['type'] ) . '" name="' . esc_attr( $inp['name'] ) . '" value="' . esc_attr( $inp['value'] ) . '">';
							}
						}
					}

				} ?>

            </form>
			<?php
		}

		public function show_crm_integration_html( $optin_form_options ) {
			$forms         = array(
				'active-campaign' => __( 'ActiveCampaign', 'funnel-builder-powerpack' ),
				'drip'            => __( 'Drip', 'funnel-builder-powerpack' ),
				'convert-git'     => __( 'ConvertKit', 'funnel-builder-powerpack' ),
				'infusion-soft'   => __( 'InfusionSoft', 'funnel-builder-powerpack' ),
				'mailchimp'       => __( 'Mailchimp', 'funnel-builder-powerpack' ),
				'madmimi'         => __( 'Mad Mimi', 'funnel-builder-powerpack' ),
				'raw_html'        => __( 'Raw HTML', 'funnel-builder-powerpack' ),
			);
			$formBuilder   = isset( $optin_form_options['formBuilder'] ) ? $optin_form_options['formBuilder'] : '';
			$optin_form_id = isset( $optin_form_options['optinFormId'] ) ? $optin_form_options['optinFormId'] : 0;
			$optinPageId   = isset( $optin_form_options['optinPageId'] ) ? $optin_form_options['optinPageId'] : 0;

			$html_code = isset( $optin_form_options['html_code'] ) ? $optin_form_options['html_code'] : '';
			?>
            <div class="action-crm-container">
                <div class="init_form wffn-hide">
                    <div class="form-group valid field-select">
                        <label for="optin-form-builder"><span><?php esc_html_e( 'Send contacts to', 'funnel-builder-powerpack' ); ?></span></label>
                        <div class="field-wrap">
                            <select name="optin_form_builder" id="optin-form-builder" class="form-control">
                                <option value=""><?php esc_html_e( 'Select Services', 'funnel-builder-powerpack' ); ?></option>
								<?php
								foreach ( $forms as $key => $value ) { ?>
                                    <option data-form-group="<?php echo esc_attr( $key ) ?>"
                                            value="<?php echo esc_attr( $key ) ?>" <?php if ( $formBuilder === $key ) {
										echo 'selected';
									} ?> ><?php esc_html_e( $value ); ?></option>
								<?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group wffn-paste-form-html wffn-hide">
                        <label></label>
                        <div class="field-wrap">
                        <textarea id="wffn_lead_generation_code" rows="10" cols="68" name="wffn-form-html"
                                  placeholder="<?php esc_attr_e( 'Paste form embed code.', 'funnel-builder-powerpack' ); ?>"><?php echo stripslashes_deep( $html_code ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></textarea>
                            <div class="html_err_print" style="display:none;"></div>
                            <a id="wffn_generate_form" href="javascript:void(0);"
                               class="button-primary button wffn-gen-form"
                               disabled><?php esc_html_e( 'Continue', 'funnel-builder-powerpack' ); ?></a>
                            <p class="wffn-title-error wffn-hide"><?php esc_html_e( 'Form title must not be empty.', 'funnel-builder-powerpack' ); ?></p>
                        </div>
                    </div>

                    <div class="form-group valid wffn-map-fields wffn-hide">
                        <label></label>

                        <div class="field-wrap wffn-field-heads">
                            <div class="fields">
                                <h4 class="field-head"><?php esc_html_e( 'Map Fields', 'funnel-builder-powerpack' ); ?></h4>
                            </div>
                        </div>
                        <div class="field-wrap" id="wffn_form_fields"></div>


                    </div>
                </div>
            </div>
			<?php
		}

		public function modify_form_result_for_crm_integration( $result, $optin_page_id ) {
			if ( 'true' === WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optin_page_id, 'optin_form_enable' ) ) {

				$result['mapped'] = [];
				$get_mapping      = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optin_page_id, 'fields' );

				foreach ( $get_mapping as $key => $map ) {
					if ( ! empty( $map ) ) {

						$get_inputName = $key;
						if ( ! isset( $result['posted_data'][ $get_inputName ] ) ) {
							$get_inputName = str_replace( WFFN_Optin_Pages::FIELD_PREFIX, '', $key );
						}
						if ( isset( $result['posted_data'][ $get_inputName ] ) ) {
							$result['mapped'][ $map ] = $result['posted_data'][ $get_inputName ];
						}

					}

				}
			}

			return $result;
		}

		public function posted_data_phone_country( $posted, $raw ) {
			if ( isset( $raw['wfop_optin_phone'] ) && ! empty( $raw['wfop_optin_phone'] ) && isset( $raw['wfop_optin_phone_countrycode'] ) && ! empty( $raw['wfop_optin_phone_countrycode'] ) ) {
				$posted['wfop_optin_country'] = $raw['wfop_optin_phone_countrycode'];
			}

			return $posted;
		}

	}

	if ( class_exists( 'WFOPP_PRO_Core' ) ) {
		WFOPP_PRO_Core::register( 'pro_optin_pages', 'WFFN_Pro_Optin_Pages' );
	}
}