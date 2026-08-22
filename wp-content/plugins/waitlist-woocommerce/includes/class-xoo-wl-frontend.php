<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class Xoo_Wl_Frontend{

	protected static $_instance = null;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){
		$this->hooks();
	}

	public function hooks(){
		add_action( 'wp_enqueue_scripts' ,array( $this,'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts' , array( $this,'enqueue_scripts' ), 5 );
		add_action( 'wp_footer', array( $this, 'popup_markup' ) );

		if( in_array( 'product', (array) xoo_wl_helper()->get_general_option( 'm-show-waitlist' ) ) ){

			if( function_exists('wp_is_block_theme') && wp_is_block_theme() ){
				add_action( 'init', array( $this, 'block_theme_add_hook_for_waitlist_on_product_page' ) );
			}
			else{
				add_action( 'woocommerce_before_single_product', array( $this, 'add_hook_for_waitlist_on_product_page' ) );
			}

		}
		
		if( in_array( 'shop', (array) xoo_wl_helper()->get_general_option( 'm-show-waitlist' ) ) ){
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'show_waitlist_on_shop_page' ), 15 );
		}
		add_action( 'xoo_wl_form_end', array( $this, 'lang_field' ) );
	}


	public function block_theme_add_hook_for_waitlist_on_product_page(){
		foreach ( wc_get_product_types() as $type => $label ) {
			add_action( 'woocommerce_' . $type . '_add_to_cart', array( $this, 'get_waitlist_markup_for_product_page' ), 35 );
		}
	}


	public function popup_markup(){
		xoo_wl_helper()->get_template( 'xoo-wl-popup.php' );
	}


	//Enqueue stylesheets
	public function enqueue_styles(){
		wp_enqueue_style( 'xoo-wl-style', XOO_WL_URL.'/assets/css/xoo-wl-style.css', array(), XOO_WL_VERSION );
		wp_enqueue_style('xoo-wl-fonts',XOO_WL_URL.'/assets/css/xoo-wl-fonts.css',array(),XOO_WL_VERSION);

		ob_start();
		xoo_wl_helper()->get_template( '/inline-style.php' );
		wp_add_inline_style('xoo-wl-style', ob_get_clean() );
	}

	//Enqueue javascript
	public function enqueue_scripts(){

		//Enqueue Form field framework scripts
		xoo_wl()->aff->enqueue_scripts();

		wp_enqueue_script( 'xoo-wl-js', XOO_WL_URL.'/assets/js/xoo-wl-js.js', array('jquery'), XOO_WL_VERSION, true ); // Main JS
		wp_localize_script('xoo-wl-js','xoo_wl_localize',array(
			'adminurl'  			=> admin_url().'admin-ajax.php',
			'notices' 				=> array(
				'empty_id' 	=> xoo_wl_add_notice( 'Something went wrong, please contact support.', 'error' ),
				'empty_email' 	=> xoo_wl_add_notice( __( 'Email address cannot be empty.', 'waitlist-woocommerce' ), 'error' ),
			),
			'waitlist_show' 	=> (array) xoo_wl_helper()->get_general_option( 'm-btn-show' ),
			'html' 				=> array(
				'spinner' 	=> '<i class="xoo-wl-icon-spinner8 xoo-wl-spinner"></i>',
			)
		));
	}


	public function add_hook_for_waitlist_on_product_page(){

		global $product;

		add_action( 'woocommerce_' . $product->get_type() . '_add_to_cart', array( $this, 'get_waitlist_markup_for_product_page' ), 35 );
		
	}


	public function get_waitlist_markup_for_product_page(){

		global $product;

		echo xoo_wl_form_markup( $product->get_id(), xoo_wl_helper()->get_general_option('m-form-type'), array(
			'container_class' => array( 'xoo-wl-prod-btncont' )
		)  );

	}

	public function show_waitlist_on_shop_page(){
		
		global $product;

		echo xoo_wl_form_markup( $product->get_id(), 'popup', array(
			'container_class' => array( 'xoo-wl-shop-btncont' )
		) );
	}

	public function lang_field(){

		if( class_exists( 'SitePress' ) ){
			$lang = ICL_LANGUAGE_CODE;
		}
		elseif ( defined('TRP_PLUGIN_VERSION') ) {
			$lang = get_locale();
		}

		if( !isset( $lang ) ) return;

		?>
		<input type="hidden" name="xoo-wl-wpml-lang" value="<?php echo $lang ?>">
		<?php
	}

}

function xoo_wl_frontend(){
	return Xoo_Wl_Frontend::get_instance();
}
xoo_wl_frontend();
