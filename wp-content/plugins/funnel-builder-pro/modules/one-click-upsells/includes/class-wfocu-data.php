<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WFOCU_Data' ) ) {
	/**
	 * Class WFOCU_Data
	 * @package UpStroke
	 * @author WooFunnels
	 */
	#[AllowDynamicProperties]
	class WFOCU_Data extends WFOCU_Session_Handler {

		private static $ins = null;
		protected $cache = array();
		private $page_id = false;
		private $page_link = false;
		private $order_id = false;
		private $order = false;
		private $page_layout = false;
		private $page_layout_info = false;
		private $options = null;

		public function __construct() {
			add_action( 'init', array( $this, 'setup_options' ), 26 );
			add_action( 'wfocu_global_settings', array( $this, 'sanitize_scripts' ), 10 );

			/**
			 * As we have extended the class 'WFOCU_Session_Handler', We have to create a construct over there and not using native register method.
			 */
			parent::__construct();

			/** Adding the_content default filters on 'wfocu_the_content' handle */
			add_filter( 'wfocu_the_content', 'wptexturize' );
			add_filter( 'wfocu_the_content', 'convert_smilies', 20 );
			add_filter( 'wfocu_the_content', 'wpautop' );
			add_filter( 'wfocu_the_content', 'shortcode_unautop' );
			add_filter( 'wfocu_the_content', 'prepend_attachment' );
			add_filter( 'wfocu_the_content', 'do_shortcode', 11 );
			add_filter( 'wfocu_the_content', array( $GLOBALS['wp_embed'], 'run_shortcode' ), 8 );
			add_filter( 'wfocu_the_content', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
		}

		public static function get_instance() {
			if ( self::$ins === null ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * This method try to sets up the funnel by firing methods for rule matching and behaves as controller function between database and logical operation
		 * Function differs its behavior based on the env provided, as cart is the one with standard matching, whereas order is placed for some special rules to take care of.
		 *
		 * @param bool $skip_rules
		 * @param string $env
		 */
		public function setup_funnel( $skip_rules = false, $env = 'cart' ) {

			/**
			 * Bail out if we already decided and have funnel to run
			 */
			if ( false !== WFOCU_Core()->data->get_funnel_id() && $env === 'order' ) {
				return;
			}

			/**
			 * Run database fetching of funnels
			 */
			$get_funnels = WFOCU_Core()->funnels->setup_funnels();

			if ( ! WFOCU_Core()->session_db->get_skip_id() && ( ! is_array( $get_funnels ) || 0 === count( $get_funnels ) ) ) {
				WFOCU_Core()->session_db->set_skip_id( 0 );
			}

			WFOCU_Core()->rules->load_rules_classes();

			/**
			 * Iterate over the funnels and executed matching of groups
			 */
			foreach ( $get_funnels as $funnel ) {
				if ( false === $skip_rules ) {
					WFOCU_Core()->rules->match_groups( $funnel['id'], $env );
				}
			}

			/**
			 * Get the decided funnel
			 */
			$get_the_decided_funnel = WFOCU_Core()->rules->find_match();

			//if we have the funnel
			if ( $get_the_decided_funnel ) {

				$get_funnel        = apply_filters( 'wfocu_front_funnel_filter', $get_the_decided_funnel );
				$get_funnel_offers = WFOCU_Core()->offers->get_offers( $get_funnel );

				if ( false !== $get_funnel_offers ) {

					$this->set( 'current_offer', false );
					$this->set( 'funnel_id', $get_funnel );

					$this->set( 'funnel', $get_funnel_offers );
					do_action( 'wfocu_funnel_decided', $get_funnel );
					WFOCU_Core()->log->log( 'funnel decided:' . $get_funnel );
					$this->save();
				}
			} elseif ( 'cart' === $env ) {
				/**
				 * Resets the chosen funnel if any failure
				 */
				$this->destroy_session();
				/**
				 * in case we do not have funnel to decide and we have environment of cart
				 * We need to save the matching results and process the rest of the results after order has been made to find the best funnel
				 */
				WFOCU_Core()->rules->sustain_results();

			}

			if ( ! $get_the_decided_funnel && ! WFOCU_Core()->session_db->get_skip_id() ) {
				WFOCU_Core()->session_db->set_skip_id( 2 );
			}

			return;

		}

		public function get_funnel_id() {
			$funnel_id = $this->get( 'funnel_id' );

			return $funnel_id;
		}

		public function setup_posted( $array ) {
			$this->posted = $array;
		}

		public function get_posted( $key, $default = '' ) {

			if ( isset( $this->posted[ $key ] ) ) {
				return $this->posted[ $key ];
			}

			return $default;

		}

		public function get_page_link() {

			return $this->page_link;
		}


		/**
		 * @param int $id
		 *
		 * @return bool|WC_Order
		 */
		public function get_order( $id = 0 ) {
			if ( $id !== 0 ) {
				$this->load_order( $id );
			}

			return $this->order;
		}

		public function load_order( $order_id = 0 ) {
			if ( $order_id instanceof WP ) {
				$order_id = 0;
			}

			if ( $order_id === 0 ) {
				$order_id = ( isset( $_GET['order_id'] ) && ( $_GET['order_id'] !== '' ) ) ? wc_clean( $_GET['order_id'] ) : 0;  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( $order_id !== 0 ) {
				$this->order_id = $order_id;
				$this->order    = wc_get_order( $order_id );
			}
		}

		public function set_page( $id = null ) {
			global $post;
			if ( $id === null ) {
				return;
			}
			if ( is_numeric( $id ) && $id !== 0 ) {
				$post = get_post( $id ); //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
			if ( $post instanceof WP_Post && $post->post_type === WFOCU_Common::get_thank_you_page_post_type_slug() ) {
				$this->page_id = $post->ID;
			}
		}

		public function get_layout() {
			return $this->page_layout;
		}

		public function set_layout( $layout ) {
			$this->page_layout = $layout;
		}

		public function get_layout_info() {
			return $this->page_layout_info;
		}

		public function set_layout_info( $data ) {
			$this->page_layout_info = $data;
		}

		public function update_options( $data ) {

			update_option( 'wfocu_global_settings', $data );

		}

		public function setup_options() {

			if ( ! $this->options ) {
				$options       = get_option( 'wfocu_global_settings' );
				$this->options = wp_parse_args( $options, WFOCU_Core()->data->get_options_defaults( $options ) );

				$this->options = apply_filters( 'wfocu_global_settings', $this->options, $options );
			}
		}

		public function get_options_defaults( $existing ) {
			$get_all_supported_gateways = WFOCU_Core()->gateways->get_supported_gateways();

			return apply_filters( 'wfocu_common_default_options', array(

				'gateways'                                => array_keys( $get_all_supported_gateways ),
				'gateway_test'                            => array(),
				'paypal_ref_trans'                        => 'no',
				'sepa_gateway_trans'                      => 'no',
				'scripts'                                 => '',
				'scripts_head'                            => '',
				'enable_log'                              => true,
				'ttl_funnel'                              => '15', //in minutes
				'send_processing_mail_on'                 => 'end', //start or end
				'send_processing_mail_on_no_batch'        => 'start', //start or end
				'send_processing_mail_on_no_batch_cancel' => 'end', //start or end
				'primary_order_status_title'              => _x( 'Primary Order Accepted', 'Order status', 'woofunnels-upstroke-one-click-upsell' ),
				'flat_shipping_label'                     => __( 'Flat Rate', 'woofunnels-upstroke-one-click-upsell' ),
				'create_new_order_status_fail'            => 'wc-pending',
				'order_copy_meta_keys'                    => '',
				'offer_header_text'                       => __( 'Confirm Your upgrade', 'woofunnels-upstroke-one-click-upsell' ),
				'offer_yes_btn_text'                      => __( 'Yes! I accept', 'woofunnels-upstroke-one-click-upsell' ),
				'offer_skip_link_text'                    => __( 'No, I do not want this offer', 'woofunnels-upstroke-one-click-upsell' ),
				'cart_opener_text'                        => __( 'Confirm Your Order', 'woofunnels-upstroke-one-click-upsell' ),
				'offer_yes_btn_bg_cl'                     => '#70dc1d',
				'offer_yes_btn_sh_cl'                     => '#00A300',
				'offer_yes_btn_txt_cl'                    => '#ffffff',
				'offer_yes_btn_bg_cl_h'                   => '#00A300',
				'offer_yes_btn_sh_cl_h'                   => '#00A300',
				'offer_yes_btn_txt_cl_h'                  => '#ffffff',
				'offer_no_btn_txt_cl'                     => '#414349',
				'offer_no_btn_txt_cl_h'                   => '#414349',
				'cart_opener_text_color'                  => '#ffffff',
				'cart_opener_background_color'            => '#70dc1d',
				'treat_variable_as_simple'                => true,
				'enable_noconflict_mode'                  => false,

			), $existing );
		}

		public function get_option( $key = '' ) {

			if ( $key !== '' ) {
				return ( isset( $this->options[ $key ] ) ? $this->options[ $key ] : '' );
			}

			return $this->options;
		}

		public function setup_options_offer_settings() {
			if ( ! $this->options ) {
				$options = get_option( 'wfocu_global_settings' );

				$this->options = wp_parse_args( $options, WFOCU_Core()->data->get_options_defaults() );

				/**
				 * Compatibility with WPML
				 */
				if ( function_exists( 'icl_t' ) && isset( $options['google_map_error_txt'] ) ) {
					$translated_google_map_error_text      = icl_t( 'admin_texts_wfocu_global_settings', '[wfocu_global_settings]google_map_error_txt', $this->options['google_map_error_txt'] );
					$this->options['google_map_error_txt'] = $translated_google_map_error_text;
				}
				$this->options = apply_filters( 'wfocu_common_options', $this->options );
			}
		}

		public function get_funnel_key() {

			/**
			 * Here we are creating the funnel key, which going to append in all the offer URL during the funnel.
			 * The algorithm we use for this is "aog" + (Funnel ID) + (Current session key) + "edf";
			 * Here we are not using any timestamp or some other random hash because we need to validate this key during the offer loading.
			 */
			$funnel_id     = $this->get( 'funnel_id' );
			$transient_key = $this->get_transient_key();

			$key_prefix  = 'aog';
			$key_postfix = 'edf';

			return md5( $key_prefix . '||' . $funnel_id . '||' . $transient_key . '||' . $key_postfix );
		}

		public function is_funnel_exists() {
			return $this->get_funnel_id();

		}

		/**
		 * @return WC_order|array|string
		 */
		public function get_current_order() {

			$get_order = $this->get( 'corder', false, '_orders' );

			if ( $get_order instanceof WC_Order ) {
				return $get_order;
			}
			$get_current_order_id = $this->get( 'corder', false, 'orders' );

			if ( false !== $get_current_order_id ) {
				return wc_get_order( $get_current_order_id );
			}

			$get_order = $this->get( 'porder', false, '_orders' );

			return $get_order;

		}

		/**
		 * @return WC_order|array|string
		 */
		public function get_current_offer() {

			$get_offer = $this->get( 'current_offer', false );

			return $get_offer;

		}

		public function get_options_defaults_offer_confirmation() {
			return array(
				'header_text' => __( 'Confirm Your Order', 'woofunnels-upstroke-one-click-upsell' ),

			);
		}

		public function sanitize_scripts( $options ) {

			if ( $options && ( isset( $options['scripts'] ) && '' !== $options['scripts'] ) ) {
				$options['scripts'] = stripslashes_deep( $options['scripts'] );
			}

			if ( $options && ( isset( $options['scripts_head'] ) && '' !== $options['scripts_head'] ) ) {
				$options['scripts_head'] = stripslashes_deep( $options['scripts_head'] );
			}

			return $options;
		}

		public function get_tax_name() {
			$get_parent_order = $this->get_parent_order();
			if ( $get_parent_order instanceof WC_Order ) {
				$item_tax = $get_parent_order->get_taxes();

				if ( is_array( $item_tax ) && is_subclass_of( current( $item_tax ), 'WC_Order_Item' ) ) {
					return current( $item_tax )->get_label();
				}
			}

			return __( 'Tax', 'woocommerce' );
		}

		/**
		 * @return WC_order|boolean
		 */
		public function get_parent_order() {

			$get_order = $this->get( 'porder', false, '_orders' );

			return $get_order;

		}


	}

	if ( class_exists( 'WFOCU_Data' ) ) {
		WFOCU_Core::register( 'data', 'WFOCU_Data' );
	}

}