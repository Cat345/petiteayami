<?php 

class Xoo_Wl_Emails{

	protected static $_instance = null;

	public $emails = array();

	public $backInStock;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){

		$this->emails['backInStock'] = $this->backInStock  = include XOO_WL_PATH.'includes/emails/class-xoo-wl-back-in-stock-email.php'; 

		$this->hooks();
	}

	public function hooks(){
		add_action( 'xoo_wl_email_head', array( $this, 'default_inline_style' ) );
		add_action( 'xoo_wl_email_header', array( $this, 'email_header' ) );
		add_action( 'xoo_wl_email_footer', array( $this, 'email_footer' ) );
		add_action( 'xoo_wl_email_footer_content', array( $this, 'footer_content' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_for_wc' ) );
	}

	public function register_email_for_wc( $wc_emails ){

		if( xoo_wl_helper()->get_email_style_option('email-template') === 'woocommerce' ){

			foreach ( $this->emails as $key => $email ) {

				$wc_emails[ 'xoo_wl_'.$email->id ] = new class( array(
					'id' 			=> 'xoo_wl_'.$email->id,
					'title' 		=> isset( $email->title ) ? $email->title : $email->id,
					'desc' 			=> isset( $email->desc ) ? $email->desc : '',
				) ) extends WC_Email {

					public $content_html_args;

					public function __construct( $args ) {
						$this->id             = $args['id'];
						$this->title          = '[Waitlist] ' . $args['title'];
						$this->description    = $args['desc'];

						// Call parent constructor.
						parent::__construct();

						// Other settings.
						$this->content_html_args = array(
							'email_heading'      	=> $this->get_heading(),
							'additional_content' 	=> $this->get_additional_content(),
							'email' 				=> $this,
							'email_text' 			=> '',
							'email_subject' 		=> ''
						);

					}

					public function get_default_heading(){
						return '';
					}


					public function get_content_html(){
						return xoo_wl_helper()->get_template( 'xoo-wl-woocommerce-email.php', $this->content_html_args, XOO_WL_PATH.'/templates/emails', true );
					}

					public function get_plain_html(){
						return $this->get_content_html();
					}

					public function get_subject(){
						return $this->content_html_args['email_subject'];
					}

					public function trigger( $email, $args ){
						$this->content_html_args = array_merge( $this->content_html_args, $args );
						$this->recipient = $email;
						$send = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
						
					}
				};
			}

		}

		return $wc_emails;
	}



	public function email_header( $emailObj ){
		xoo_wl_helper()->get_template( '/emails/global/xoo-wl-email-header.php', array( 'emailObj' => $emailObj ) );
	}

	public function email_footer( $emailObj ){
		xoo_wl_helper()->get_template( '/emails/global/xoo-wl-email-footer.php', array( 'emailObj' => $emailObj ) );
	}

	public function footer_content( $emailObj ){
		xoo_wl_helper()->get_template( 'emails/global/xoo-wl-email-footer-content.php', array( 'emailObj' => $emailObj ) );
	}

	public function default_inline_style(){
		xoo_wl_helper()->get_template( 'emails/global/xoo-wl-email-style.php' );
	}

}
function xoo_wl_emails(){
	return Xoo_Wl_Emails::get_instance();
}
xoo_wl_emails();