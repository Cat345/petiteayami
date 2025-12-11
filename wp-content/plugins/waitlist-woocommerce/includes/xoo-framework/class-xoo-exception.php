<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Xoo_Exception extends Exception{

	public $wpErrorCode = null;

	public function __construct($error, $code = 0, Exception $previous = null){

		if(is_wp_error( $error )){
			$message = $error->get_error_message();
			$this->wpErrorCode = $error->get_error_code();
		}else{
			$message = $error;
		}		

		parent::__construct($message, $code, $previous);	

	}

	public function getWpErrorCode(){
		return $this->wpErrorCode;	
	}


	
    /**
     * Convert this exception to a WP_Error object.
     *
     * @return WP_Error
     */
    public function to_wp_error() {
        $code = $this->wpErrorCode ? $this->wpErrorCode : 'xoo_exception';
        return new WP_Error($code, $this->getMessage());
    }


}

