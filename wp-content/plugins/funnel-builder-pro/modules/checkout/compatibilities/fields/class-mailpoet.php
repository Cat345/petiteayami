<?php

/**
 * MailPoet 3 (New) by MailPoet
 * Plugin URI: http://www.mailpoet.com
 */
if ( ! class_exists( 'WFACP_MailPoet' ) ) {
	#[AllowDynamicProperties]
	class WFACP_MailPoet {
		public $instance = null;

		public function __construct() {


			/* Register Add field */
			add_filter( 'wfacp_advanced_fields', [ $this, 'add_field' ], 20 );
			add_filter( 'wfacp_html_fields_wfacp_mail_poet', '__return_false' );
			add_action( 'process_wfacp_html', [ $this, 'display_field' ], 999, 2 );

			/* Assign Object */
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'action' ] );
			add_action( 'wfacp_before_process_checkout_template_loader', [ $this, 'action' ] );

			/* default classes */
			add_filter( 'woocommerce_form_field_args', [ $this, 'add_default_wfacp_styling' ], 10, 2 );

			add_action( 'wfacp_internal_css', [ $this, 'add_css' ] );

			/* prevent third party fields and wrapper*/

			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );

		}

		public function add_field( $fields ) {


			$fields['wfacp_mail_poet'] = [
				'type'       => 'wfacp_html',
				'class'      => [ 'wfacp-col-full', 'wfacp-form-control-wrapper', 'wfacp_mail_poet' ],
				'id'         => 'wfacp_mail_poet',
				'field_type' => 'wfacp_mail_poet',
				'label'      => __( 'MailPoet', 'woofunnels-aero-checkout' ),

			];

			return $fields;
		}

		public function action() {

			if ( ! $this->is_enable() ) {
				return;
			}

			$this->instance = WFACP_Common::remove_actions( 'woocommerce_checkout_before_terms_and_conditions', 'MailPoet\Config\HooksWooCommerce', 'extendWooCommerceCheckoutForm' );

		}

		public function is_enable() {
			if ( ! class_exists( 'MailPoet\WooCommerce\Subscription' ) ) {
				return false;
			}

			return true;
		}

		public function display_field( $field, $key ) {


			if ( ! $this->is_enable() || empty( $key ) || 'wfacp_mail_poet' !== $key || ! $this->instance instanceof MailPoet\Config\HooksWooCommerce ) {
				return '';
			}


			?>
            <div class="wfacp_mail_poet" id="wfacp_mail_poet">
				<?php
				$this->instance->extendWooCommerceCheckoutForm();
				?>
            </div>
			<?php

		}


		public function add_default_wfacp_styling( $args, $key ) {

			if ( ! $this->is_enable() || 'mailpoet_woocommerce_checkout_optin' !== $key ) {
				return $args;
			}


			if ( isset( $args['type'] ) && 'checkbox' !== $args['type'] ) {

				$args['input_class'] = array_merge( [ 'wfacp-form-control' ], $args['input_class'] );
				$args['label_class'] = array_merge( [ 'wfacp-form-control-label' ], $args['label_class'] );
				$args['class']       = array_merge( [ 'wfacp-form-control-wrapper wfacp-col-full' ], $args['class'] );
				$args['cssready']    = [ 'wfacp-col-full' ];

			} else {
				$args['class']    = array_merge( [ 'wfacp-form-control-wrapper wfacp-col-full ' ], $args['class'] );
				$args['cssready'] = [ 'wfacp-col-full' ];
			}


			return $args;
		}

		public function add_css() {
			if ( ! $this->is_enable() || ! function_exists( 'wfacp_template' ) ) {
				return;
			}
			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}
			$bodyClass = "body ";
			$px        = $instance->get_template_type_px() . "px";
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = "body #wfacp-e-form ";
				$px        = "7px";
			}


			$cssHtml = "<style>";
			$cssHtml .= $bodyClass . "#wfacp_mail_poet {padding-left:$px;padding-right:$px;margin-bottom: 16px;}";
			$cssHtml .= "</style>";
			echo $cssHtml;

		}


	}

	WFACP_Plugin_Compatibilities::register( new WFACP_MailPoet(), 'wfacp-mailpoet' );

}