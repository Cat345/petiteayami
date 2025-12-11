<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WFOCU_Compatibility_With_Elementor' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Elementor
	 */
	class WFOCU_Compatibility_With_Elementor {

		public function __construct() {
			add_post_type_support( WFOCU_Common::get_offer_post_type_slug(), 'elementor' );
			add_action( 'plugins_loaded', array( $this, 'wfocu_widget_init' ) );
		}

		public function is_enable() {
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				return true;
			}

			return false;
		}

		//initialing widget settings
		public function wfocu_widget_init() {

			/**
			 * Include UpStroke template group for the elementor
			 */
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/wfocu-template-group-elementor.php';

			/**  Register widget category */
			add_action( 'elementor/elements/categories_registered', array( $this, 'add_wfocu_elementor_category' ) );

			/** Register widgets and Tags */
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
				add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ] );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
				add_action( 'elementor/dynamic_tags/register_tags', [ $this, 'register_tags' ] );
			}

			add_action( 'elementor/editor/init', [ $this, 'maybe_setup_customizer_data_on_offers' ], 100 );
			add_action( 'elementor/editor/init', [ $this, 'maybe_register_widget_message' ], 500 );
			add_action( 'elementor/editor/init', [ $this, 'maybe_setup_upstroke_fonts' ], 500 );

			add_action( 'wp_footer', [ $this, 'setup_footer_script' ], 9999 );
			add_action( 'elementor/ajax/register_actions', [ $this, 'maybe_setup_customizer_data_on_offers' ] );

			// Editor Preview Style
			add_action( 'elementor/preview/enqueue_styles', [ $this, 'maybe_add_slider_css_in_preview' ] );

			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'maybe_print_shortcodes_helpbox' ) );

			add_action( 'elementor/theme/register_conditions', [ $this, 'register_conditions' ] );

		}


		public function register_tags() {

			if ( false === $this->is_elementor_offer_page() ) {
				return;
			}

			\Elementor\Plugin::$instance->dynamic_tags->register_group( 'upstroke', [
				'title' => 'WooFunnels Upsells',
			] );

			require_once( __DIR__ . '/page-builders/elementor/tags/offer-price.php' );
			require_once( __DIR__ . '/page-builders/elementor/tags/product-title.php' );
			require_once( __DIR__ . '/page-builders/elementor/tags/countdown-timer.php' );

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Price() );
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Title() );
				\Elementor\Plugin::$instance->dynamic_tags->register( new WFOCU_Elementor_Tag_Countdown() );
			} else {
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Price' );
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Title' );
				\Elementor\Plugin::$instance->dynamic_tags->register_tag( 'WFOCU_Elementor_Tag_Countdown' );
			}

		}

		/**
		 * Adding a new widget category 'WooFunnels'
		 */
		public function add_wfocu_elementor_category() {
			if ( $this->is_elementor_offer_page() ) {
				\Elementor\Plugin::instance()->elements_manager->add_category( 'upstroke', array(
					'title' => __( 'FunnelKit', 'woofunnels-upstroke-one-click-upsell' ),
					'icon'  => 'fa fa-plug',
				) );
			}
		}

		public function maybe_setup_upstroke_fonts() {
			add_action( 'wp_head', array( $this, 'enqueue_font_css' ) );
		}

		public function enqueue_font_css() {
			wp_enqueue_style( 'wfocu-icons', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-font.css', null, WFOCU_VERSION );
		}

		/**
		 * @throws Exception
		 */
		public function register_widgets() {
			// Include widget files

			if ( false === $this->is_elementor_offer_page() ) {
				return;
			}

			$this->includes();
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Accept_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Reject_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Accept_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Reject_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Variation_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Qty_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Price_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \WFOCU_Product_Images_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \WFOCU_Product_Short_Description_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFOCU_Product_Title_Widget() );
			} else {
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Accept_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Reject_Button_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Accept_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Reject_Link_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Variation_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Qty_Selector_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Price_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \WFOCU_Product_Images_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \WFOCU_Product_Short_Description_Widget() );
				\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFOCU_Product_Title_Widget() );
			}
		}

		/**
		 * Include widget Files
		 */
		public function includes() {

			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-accept-button-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-reject-button-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-accept-link-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-reject-link-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-variation-selector-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-qty-selector-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-price-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-images-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-short-description-widget.php' );
			require_once( __DIR__ . '/page-builders/elementor/widgets/class-elementor-wfocu-product-title-widget.php' );

		}


		public function print_inline_script() { ?>
            <script>

                (function ($) {
                    "use strict";

                    var wfocuSupportedMergeTagsWidgets =<?php echo wp_json_encode( $this->get_merge_tags_supported_widgets() ); ?>;

                    elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
                        if (wfocuSupportedMergeTagsWidgets.indexOf(model.get('widgetType')) === -1) {
                            return;
                        }
                        // var html = '<div class="elementor-control elementor-control-wc_style_warning elementor-control-type-raw_html elementor-label-inline elementor-control-separator-default">\n' +
                        //     '\t\t\t<div class="elementor-control-content">\n' +
                        //     '\t\t\t\t\t\t\n' +
                        //     '\t\t<div class="elementor-control-raw-html elementor-panel-alert elementor-panel-alert-info">You can also add personalization tags to this element using shortcodes.<a onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" href="javascript:void(0)">Click here to show the available shortcodes</a> </div>\n' +
                        //     '\t\t\t\t\t</div>\n' +
                        //     '\t\t</div>';
                        var html = '\t\t\t<div class="wfocu-el-customize-note">\n' +
                            '\t\t\t\t\t\t\n' +
                            '\t\t<div class="elementor-panel-alert elementor-panel-alert-info">You can also add personalization tags to this element using shortcodes.<a style="text-decoration: underline;" onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" href="javascript:void(0)">Click here to show the available shortcodes</a> </div>\n' +
                            '\t\t\t\t\t</div>\n' +
                            '\t\t';
                        $(".elementor-panel-navigation").eq(0).after(html);


                    });


                })(jQuery);
            </script>
			<?php
		}

		public function setup_footer_script() {
			$this->print_inline_script_frontend();
		}

		public function print_inline_script_frontend() {
			global $post;
			if ( is_object( $post ) && $post instanceof WP_Post && WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				?>
                <script>

                    (function ($) {
                        "use strict";

                        $(window).on('elementor/frontend/init', function () {
                            elementorFrontend.hooks.addAction('frontend/element_ready/wfocu-product-images.default', function ($scope) {

                                if (false === elementorFrontend.config.environmentMode.edit) {
                                    return;
                                }

                                if (jQuery('.wfocu-product-carousel').length > 0) {
                                    jQuery('.wfocu-product-carousel').each(function () {
                                        var flickity_attr = jQuery(this).attr('data-flickity');
                                        if (undefined !== flickity_attr) {
                                            jQuery(this).flickity(JSON.parse(flickity_attr));
                                        }
                                    });
                                }

                                if (jQuery('.wfocu-product-carousel-nav').length > 0) {
                                    jQuery('.wfocu-product-carousel-nav').each(function () {
                                        var flickity_attr = jQuery(this).attr('data-flickity');
                                        if (undefined !== flickity_attr) {
                                            jQuery(this).flickity(JSON.parse(flickity_attr));
                                        }
                                    });
                                }
                            });

                            elementorFrontend.hooks.addAction('frontend/element_ready/countdown.default', function ($scope) {
                                jQuery(document.body).on('countdown_expire', function (e) {
                                    var actions = $scope.find('.elementor-widget-container > div').attr('data-expire-actions');
                                    console.log('countdown_expire hits and actions are: ' + actions);

                                    var action_on_zero = '';

                                    if (typeof wfocu_vars !== "undefined" && false === wfocu_vars.is_preview) {
                                        console.log('Ajax action wfocu_front_offer_expired fired on countdown_expire.');
                                        $.post(
                                            wfocu_vars.ajax_url, {
                                                action: 'wfocu_front_offer_expired',
                                                'wfocu-si': wfocu_vars.session_id, nonce: wfocu_vars.nonces.wfocu_offer_expired, "next_action": action_on_zero
                                            }, function (response) {
                                                console.log('wfocu_front_offer_expired repsonse on countdown_expire');
                                                console.log(response);
                                            });
                                    }
                                });
                            });
                        });
                    })(jQuery);
                </script>
			<?php }
		}


		public function get_merge_tags_supported_widgets() {
			return [ 'heading', 'text-editor', 'shortcode' ];
		}


		public function maybe_print_shortcodes_helpbox() {
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/view/help-shortcodes.php';
		}

		public function maybe_register_widget_message() {
			$id       = \Elementor\Plugin::$instance->editor->get_post_id();
			$get_post = get_post( $id );
			if ( ! is_null( $get_post ) && WFOCU_Common::get_offer_post_type_slug() === $get_post->post_type ) {
				add_action( 'wp_footer', [ $this, 'print_inline_script' ], 9999 );
			}
		}

		public function maybe_setup_customizer_data_on_offers() {

			global $post;

			if ( false === is_object( $post ) ) {
				return;
			}

			if ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				$maybe_offer_id                          = $post->ID;
				WFOCU_Core()->template_loader->is_single = true;
			}

			if ( empty( $maybe_offer_id ) ) {
				return;
			}

			WFOCU_Core()->template_loader->setup_complete_offer_setup_manual( $maybe_offer_id );
		}


		public function register_conditions( $conditions_manager ) {
			require_once( __DIR__ . '/page-builders/elementor/conditions/offers.php' );

			$new_condition = new ElementorPro\Modules\ThemeBuilder\Conditions\WooFunnels_Offers( [
				'post_type' => WFOCU_Common::get_offer_post_type_slug(),
			] );
			$conditions_manager->get_condition( 'singular' )->register_sub_condition( $new_condition );
		}

		public function maybe_add_slider_css_in_preview() {
			wp_enqueue_style( 'flickity' );
			wp_enqueue_style( 'flickity-common' );
		}

		/**
		 * @return bool
		 */
		public function is_elementor_offer_page() {
			$offer_id = $this->get_elementor_offer_page_id();
			if ( $offer_id > 0 && WFOCU_Common::get_offer_post_type_slug() === get_post_type( $offer_id ) ) {
				if ( class_exists( '\Elementor\Plugin' ) ) {
					return true;
				}
			}

			if ( empty( $offer_id ) ) {
				if ( class_exists( '\Elementor\Plugin' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @return false|int
		 */
		public function get_elementor_offer_page_id() {
			$offer_id = 0;
			if ( isset( $_REQUEST['action'] ) && 'elementor' === $_REQUEST['action'] && isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$offer_id = absint( $_REQUEST['post'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( $offer_id < 1 && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] && isset( $_REQUEST['editor_post_id'] ) && $_REQUEST['editor_post_id'] > 0 ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$offer_id = absint( $_REQUEST['editor_post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( $offer_id < 1 && function_exists( 'get_the_ID' ) ) {
				$offer_id = get_the_ID();
			}

			return $offer_id;
		}
	}


	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Elementor(), 'elementor' );
}