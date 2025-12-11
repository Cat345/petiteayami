<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_AUTHORIZE_NET_CIM' ) ) {
	#[AllowDynamicProperties]
	class WFACP_AUTHORIZE_NET_CIM {
		public function __construct() {
            
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'force_add_woocommerce_checkout_shortcode' ], 99999 );
         
		}


		

		public function force_add_woocommerce_checkout_shortcode() {
			global $post;
          
			if ( ! is_null( $post ) && ! is_checkout_pay_page() ) {
				$post->post_content .= '[woocommerce_checkout]';
               
				add_filter( 'pre_do_shortcode_tag', [ $this, 'replace_empty_string' ], 21, 2 );
			}
		}

		public function replace_empty_string( $status, $tag ) {
			if ( 'woocommerce_checkout' === $tag ) {
				$status = '';
			}

			return $status;
		}

	}


	WFACP_Plugin_Compatibilities::register( new WFACP_AUTHORIZE_NET_CIM(), 'authorize_cim' );


}

