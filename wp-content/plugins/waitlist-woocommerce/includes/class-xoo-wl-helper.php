<?php

use XooWL\Framework\Xoo_Helper;

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

	public function get_button_values( $args = array(), $theme = 'dark' ){

		$themes = array(
			'light' => array(
				'bgColor'       => '#427ce9',
				'txtColor' 		=> '#ffffff',
				'border' => array(
					'color'     => '#437ce4',
				),
				'text' => array(
					'fontWeight' => 600,
				),

				'hover' => array(
					'bgColor'       => '#064f99',
					'txtColor'      => '#ffffff',
					'border' => array(
						'color'     => '#064f99',
					),
				),
			),
			'dark' => array(
				'bgColor'       => '#0a6bce',
				'txtColor' 		=> '#ffffff',
				'border' => array(
					'color'     => '#0a6bce',
				),

				'hover' => array(
					'bgColor'       => '#064f99',
					'txtColor'      => '#ffffff',
					'border' => array(
						'color'     => '#064f99',
					),
				),
				'text' => array(
					'fontWeight' => 600,
				)
			)
		);

		if( $theme && isset( $themes[ $theme ] ) ){
			$args = xoo_recursive_parse_args( $args, $themes[ $theme ] );
		}
		return xoo_recursive_parse_args( $args, parent::get_button_values( $args ) );
	}

	public function get_usage_data(){

		return array(
			'version' 	=> XOO_WL_VERSION,
		);
		
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