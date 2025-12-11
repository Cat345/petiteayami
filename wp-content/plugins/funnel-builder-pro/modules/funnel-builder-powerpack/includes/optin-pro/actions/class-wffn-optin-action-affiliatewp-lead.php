<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class will assign a course to optin prospects if the Learndash plugin is installed and free course is setup to assign
 * Class WFFN_Optin_Action_AffiliateWP_Lead
 */
if ( ! class_exists( 'WFFN_Optin_Action_AffiliateWP_Lead' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Optin_Action_AffiliateWP_Lead extends WFFN_Optin_Action {

		private static $slug = 'affiliatewp_lead';
		private static $ins = null;
		public $priority = 30;

		/**
		 * WFFN_Optin_Action_AffiliateWP_Lead constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'wffn_custom_integration_field_merge', [ $this, 'afwp_merge_field' ], 10, 2 );
		}

		/**
		 * @return WFFN_Optin_Action_AffiliateWP_Lead|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return bool
		 */
		public function should_register() {
			if ( class_exists( 'Affiliate_WP' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * @param $posted_data
		 * @param $fields_settings
		 * @param $optin_action_settings
		 *
		 * @return array|bool|mixed
		 */
		public function handle_action( $posted_data, $fields_settings, $optin_action_settings ) {

			if ( ! function_exists( 'affiliate_wp' ) ) {
				return $posted_data;
			}
			// Skip if AffiliateWP not active or Settings not enabled from backend.
			if ( ! ( $this->should_register() && ! empty( $optin_action_settings['affiliatewp_id'] ) && true === wffn_string_to_bool( $optin_action_settings['affiliatewp_id'] ) ) ) {
				return $posted_data;
			}

			$posted_data = parent::handle_action( $posted_data, $fields_settings, $optin_action_settings );

			$affiliate_id = affiliate_wp()->tracking->get_affiliate_id();


			// Skip if Affiliate ID does not exist.
			if ( empty( $affiliate_id ) ) {
				return $posted_data;
			}

			$optin_email = $this->get_optin_data( WFFN_Optin_Pages::WFOP_EMAIL_FIELD_SLUG );
			$first_name  = $this->get_optin_data( WFFN_Optin_Pages::WFOP_FIRST_NAME_FIELD_SLUG );
			$last_name   = $this->get_optin_data( WFFN_Optin_Pages::WFOP_LAST_NAME_FIELD_SLUG );

			// Filter to change referral type ( 'opt-n', 'lead' ).
			$referral_type = apply_filters( 'wffn_affiliatewp_integration_referral_type', 'opt-in' );
			$optin_page    = get_the_title( $posted_data['optin_page_id'] );

			$description = $optin_page;
			try {
				if ( affiliate_wp()->tracking->was_referred() ) {

					$affiliate_id  = affiliate_wp()->tracking->get_affiliate_id();
					$referral_args = array(
						'description'  => $description,
						'amount'       => affiliate_wp()->settings->get( 'opt_in_referral_amount', 0.00 ),
						'affiliate_id' => $affiliate_id,
						'type'         => $referral_type,
						'visit_id'     => affiliate_wp()->tracking->get_visit_id(),
						'reference'    => $optin_email,
						'status'       => affiliate_wp()->settings->get( 'opt_in_referral_status', 'pending' ),
						'customer'     => array(
							'first_name'   => $first_name,
							'last_name'    => $last_name,
							'email'        => $optin_email,
							'ip'           => affiliate_wp()->tracking->get_ip(),
							'affiliate_id' => $affiliate_id
						),
					);

					$referral_id = affiliate_wp()->referrals->add( $referral_args );
					if ( 'unpaid' === $referral_args['status'] || 'paid' === $referral_args['status'] ) {
						affiliate_wp()->visits->update( affiliate_wp()->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit' );
					}
				}

			} catch ( Exception|Error $e ) {
				WFFN_Core()->logger->log( 'AffiliateWP insert rendering failed. ' . print_r( $e->getMessage(), true ), 'wffn-failed-actions', true ); // phpcs:ignore
				WFFN_Core()->logger->log( 'AffiliateWP insert posted data. ' . print_r( $posted_data, true ), 'wffn-failed-actions', true ); // phpcs:ignore
			}
			return $posted_data;
		}

		public function afwp_merge_field( $fields, $optin_page_id ) {
			if ( ! function_exists( 'affiliate_wp' ) ) {
				return $fields;
			}
			if ( ! empty( $fields ) && ! empty( $optin_page_id ) ) {
				$optin_actions_settings = WFOPP_Core()->optin_actions->get_optin_action_settings( $optin_page_id );

				if ( $this->should_register() && ! empty( $optin_actions_settings['affiliatewp_id'] ) && true === wffn_string_to_bool( $optin_actions_settings['affiliatewp_id'] ) ) {

					// GET Affiliate ID
					$affiliate_id = affiliate_wp()->tracking->get_affiliate_id();

					$affiliatewp_field_id = [
						'type'            => 'hidden',
						'label'           => 'Affiliate ID',
						'placeholder'     => null,
						'width'           => 'wffn-sm-100',
						'required'        => null,
						'InputName'       => 'Affiliate_ID',
						'default'         => $affiliate_id,
						'options'         => null,
						'radio_alignment' => 'horizontal',
					];

					array_push( $fields, $affiliatewp_field_id );
				}
			}

			return $fields;
		}

	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->optin_actions->register( WFFN_Optin_Action_AffiliateWP_Lead::get_instance() );
	}

}
