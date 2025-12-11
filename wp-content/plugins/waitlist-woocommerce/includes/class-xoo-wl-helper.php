<?php

class Xoo_Wl_Helper extends Xoo_Helper{

	public $capability;

	protected static $_instance = null;

	public static function get_instance( $slug, $path, $helperArgs = array() ){
		if ( is_null( self::$_instance ) ) {

			self::$_instance = new self( $slug, $path, $helperArgs );

		}
		return self::$_instance;
	}

	public function get_general_option( $subkey = '' ){
		return $this->get_option( 'xoo-wl-general-options', $subkey );
	}

	public function get_style_option( $subkey = '' ){
		return $this->get_option( 'xoo-wl-style-options', $subkey );
	}

	public function get_email_style_option( $subkey = '' ){
		return $this->get_option( 'xoo-wl-emStyle-options', $subkey );
	}

	public function get_email_option( $subkey = '' ){
		return $this->get_option( 'xoo-wl-email-options', $subkey );
	}

}

function xoo_wl_helper(){
	return Xoo_Wl_Helper::get_instance( 'waitlist-woocommerce', XOO_WL_PATH, array(
		'pluginFile' 	=> XOO_WL_PLUGIN_FILE,
		'pluginName' 	=>	'Waitlist for Woocommerce',
		'capability' 	=> 'manage_woocommerce',
		'sidebar' 		=> true
	) );
}
xoo_wl_helper();

?>