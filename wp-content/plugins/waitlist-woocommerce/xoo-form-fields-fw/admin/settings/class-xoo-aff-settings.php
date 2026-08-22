<?php

namespace XooWL\Aff;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xoo_Aff_Settings{

	public $aff;

	public $tabs = array();

	public $options = array();

	public $callbacks;

	public function __construct( $aff ){
		$this->aff = $aff;
		$this->hooks();
	}

	public function hooks(){

		if( $this->aff->ff_helper ){
			add_action( 'init', array( $this, 'generate_plugin_settings' ), 5 );

			if( $this->aff->ff_helper->admin->is_settings_page() ){
				add_action( 'xoo_tab_page_start', array( $this, 'field_preview' ), 5 );
			}
			
			
		}
		else{
			add_action( 'admin_init', array( $this, 'generate_settings' ), 5 );
			add_action( 'init', array( $this, 'set_default_options' ) );
		}

		add_action( 'admin_enqueue_scripts', array($this,'enqueue_scripts') );
		
		
	}


	public function field_preview($tab_id){
		if( $tab_id !== 'fields' ) return;
		include $this->aff->dir.'/admin/settings/xoo-aff-field-preview.php';
	}


	public function generate_plugin_settings(){

		//Register Tabs
		 $this->aff->ff_helper->admin->register_tab(
		 	'Fields',
		 	'fields',
		 	$this->aff->field_option_key,
		 	'no',
		 	array(
		 		'priority' => 30,
		 		'icon' => 'xoo-icon-page'
		 	)

		 );

		$sections = (array) include $this->aff->dir.'/admin/views/sections.php';

		if( empty( $sections ) ) return;

		//Register Sections
		foreach ( $sections as $section_data ) {
			$this->aff->ff_helper->admin->register_section(
			 	$section_data['title'],
			 	$section_data['id'],
			 	$section_data['tab'],
			 	isset( $section_data['desc'] ) ? $section_data['desc'] : '',
			 	isset( $section_data['pro'] ) ? $section_data['pro'] : 'no',
			 	isset( $section_data['args'] ) ? $section_data['args'] : array()
			 );
		}

		//Register Settings
		$settings = (array) require $this->aff->dir.'/admin/views/settings/fields.php'; 

		foreach ( $settings as $setting_data ) {
			$this->aff->ff_helper->admin->register_setting(
				$setting_data['callback'],
			 	$setting_data['title'],
			 	$setting_data['id'],
			 	$setting_data['section_id'],
			 	'fields',
			 	isset( $setting_data['default'] ) ? $setting_data['default'] : '',
			 	isset( $setting_data['desc'] ) ? $setting_data['desc'] : '',
			 	isset( $setting_data['pro'] ) ? $setting_data['pro'] : 'no',
			 	isset( $setting_data['args'] ) ? $setting_data['args'] : array()
			);
		}

	}


	public function prepare(){
		$this->callbacks = require_once $this->aff->dir.'/admin/settings/class-xoo-aff-options-callbacks.php';
		$this->set_tabs();
		$this->set_options();
	}

	public function set_tabs(){
		$this->tabs = array(
			'general' => array(
				'id' 			=> 'general',
				'title' 		=> 'Settings'
			)
		);
	}

	public function set_options(){

		if( empty( $this->tabs ) ) return;

		foreach ( $this->tabs as $tab_id => $tab_data ) {
			if( file_exists( $this->aff->dir.'/admin/settings/options/'.$tab_id.'.php' ) )
			$this->options[ $this->get_option_key( $tab_id ) ] = include $this->aff->dir.'/admin/settings/options/'.$tab_id.'.php'; 
		}

	}

	public function set_default_options(){

		if( !$this->aff->hasUpdated ) return; //set defaults on update

		if( empty( $this->options ) ){
			$this->prepare();
		}

		//Set default options
		foreach ( $this->options as $option_name => $settings ) {

			$option_value = (array) get_option( $option_name );

			foreach ( $settings as $id => $setting ) {
				if( $setting['type'] === 'setting' && !isset( $option_value[ $id ] ) && isset( $setting['default'] ) ){
					$option_value[ $id ] = $setting['default' ];
				}
			}

			update_option( $option_name, $option_value );

		}
	}

	public function get_option_key( $option_slug ){
		if( $this->aff->ff_helper ){
			return $this->aff->field_option_key;
		}
		return 'xoo-aff-'.$this->aff->plugin_slug.'-'.$option_slug.'-options';
	}

	public function generate_settings(){

		$this->prepare();

		foreach ( $this->options as $option_name => $option_settings ) {

			register_setting( $option_name, $option_name );

			foreach ( $option_settings as $setting_id => $setting ) {

				if( !isset( $setting['id'] ) || !isset( $setting['type'] ) || !isset( $setting['callback'] ) ) {
					continue;
				}


				//Check for callback functions
				if( is_callable( array( $this->callbacks, $setting['callback'] ) ) ){
					$callback = array( $this->callbacks, $setting['callback'] );
				}
				elseif ( is_callable( $setting['callback'] ) ) {
					$callback = $setting['callback'];
				}
				else{
					continue;
				}


				$title = isset( $setting['title'] ) ? $setting['title'] : null;

			
				//Add a section
				if( $setting['type'] === 'section' ){

					$section_args = array(
						'id' 		=> $setting['id'],
						'title' 	=> $title,
						'callback' 	=> $callback,
						'page' 		=> $this->aff->admin_page_slug
					);

					$section_args = apply_filters( 'xoo_aff_setting_section_args', $section_args, $this );
					
					call_user_func_array( 'add_settings_section', array_values( $section_args ) );

				}

				//Add a setting field
				elseif( $setting['type'] === 'setting' ){

					$args 					= $setting;
					$args['option_name'] 	= $option_name;

					$setting_args 	= array(
						'id' 		=> $setting['id'],
						'title' 	=> $title,
						'callback' 	=> $callback,
						'page' 		=> $this->aff->admin_page_slug,
						'section' 	=> $setting['section'],
						'args' 		=> $args
					);

					$setting_args = apply_filters( 'xoo_aff_setting_args', $setting_args, $this );
					
					call_user_func_array( 'add_settings_field', array_values( $setting_args ) );

				}

			}

		}

	}


	public function enqueue_scripts($hook) {

		//Enqueue Styles only on plugin settings page
		if( ( $this->aff->ff_helper && $this->aff->ff_helper->admin->is_settings_page() ) || ( isset( $_GET['page'], $_GET['tab'] ) && $_GET['page'] === $this->aff->admin_page_slug && $_GET['tab'] === 'general' ) ){
			wp_enqueue_style('wp-color-picker');
			wp_enqueue_style( 'xoo-aff-admin-settings-style', $this->aff->url . '/admin/assets/css/xoo-aff-admin-settings-style.css', array(), XOO_AFF_VERSION, 'all' );
			wp_enqueue_script( 'xoo-aff-admin-settings-js', $this->aff->url . '/admin/assets/js/xoo-aff-admin-settings-js.js', array( 'jquery','wp-color-picker'), XOO_AFF_VERSION, false );

			$data = array(
				'adminurl'  => admin_url().'admin-ajax.php',
			);

			if( $this->aff->ff_helper ){
				wp_enqueue_script( 'xoo-aff-serializejson', $this->aff->url . '/admin/assets/js/xoo-aff-serializejson.js', array( 'jquery' ), '1.0', true );
				$data['field_option_key'] = $this->aff->field_option_key;
				$data['enable_preview'] = true;
			}

			wp_localize_script('xoo-aff-admin-settings-js','xoo_aff_admin_settings_localize', $data );

		}


	}


	public function display_page(){
		$args = array(
			'tabs' 				=> $this->tabs,
			'admin_page_slug' 	=> $this->aff->admin_page_slug,
			'aff' 				=> $this->aff
		);
		xoo_aff_get_template( "xoo-aff-admin-display.php", $this->aff->dir.'/admin/templates/', $args );
	}


}