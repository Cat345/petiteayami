<?php

namespace XooWL\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Xoo_Admin{

	public $data 		= array();

	public $tabs 		= array();

	public $sections 	= array();

	public $settings  	= array();

	public $raw_settings = array();

	public $tabPriority = 10;

	public $helper;

	public $settings_slug = '';

	public $viewsPath = '';

	public $hasPRO = false;

	public $capability = 'manage_options';

	public $usageURL 	= 'https://xootix.com/wp-json/usage/v2/data';

	public function __construct( $helper ){
		$this->helper 			= $helper;
		$this->settings_slug 	= $this->helper->slug . '-settings';

		if( is_dir( $this->helper->path .'/admin/views' ) ){
			$this->viewsPath = $this->helper->path .'/admin/views';
		}

		$this->hooks();
	}

	public function is_settings_page(){
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && $_GET['page'] === $this->settings_slug;
	}

	public function is_settings_page_request(){
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['slug'] ) && $_POST['slug'] === $this->helper->slug;
	}

	public function hooks(){
		
		if( $this->is_settings_page_request() ){
			add_action( 'wp_ajax_xoo_admin_settings_save', array( $this, 'save_settings' ), 5 );
			add_action( 'wp_ajax_xoo_admin_settings_export', array( $this, 'export_settings' ) );
			add_action( 'wp_ajax_xoo_admin_settings_import', array( $this, 'import_settings' ) );
		}


		add_action( 'init', array( $this, 'reset_settings' ) );
		add_action( 'init', array( $this, 'save_default_settings' ) );

		if( $this->is_settings_page() ){

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts') );

			add_action( 'wp_loaded', array( $this, 'set_info_tab' ) );
			add_action( 'xoo_tab_page_start', array( $this, 'info_tab_data' ), 10, 2 );
			add_action( 'xoo_tab_page_start', array( $this, 'shortcode_info' ), 20, 2 );
			
		}

		if( isset( $this->helper->helperArgs ) && !isset($this->helper->helperArgs['disable_usage']) ){

			//add_action( 'admin_notices', array( $this, 'usage_data_notice' ) );
			//add_action( 'admin_init', array( $this, 'handle_usage_click_response' ) );
			add_action( 'admin_init', array( $this, 'on_plugin_activation' ) );

			if( $this->helper->helperArgs['pluginFile'] ){
				register_deactivation_hook( $this->helper->helperArgs['pluginFile'] , array( $this, 'on_plugin_deactivate' ) );
			}

		}


	}



	public function usage_data_notice(){

		if( get_option( 'xoo_tracking_consent_'.$this->helper->slug ) !== false ) return;

		$pluginName = isset( $this->helper->helperArgs )  && $this->helper->helperArgs['pluginName'] ? $this->helper->helperArgs['pluginName'] : $this->helper->slug;

		?>
		<div class="notice notice-info xoo-usage-consent" style="max-width: 1300px;">
			<p><strong>[<?php echo esc_html( $pluginName ) ?>] Help us improve!</strong> We'd love your permission to send anonymous, non-sensitive data (such as your WordPress version, plugin settings, etc.) to help us improve the plugin.<br><strong> No personal information is collected ever</strong></p>
				<form method="post" action="" class="xoo-usage-consent">
					<input type="checkbox" name="xoo_allow" value="yes" checked>
					<input type="hidden" name="xoo_usage_handle" value="yes">
					<input type="hidden" name="xoo_slug" value="<?php echo esc_attr( $this->helper->slug ) ?>">
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'xoo_usage_nonce' ) ) ?>">
					<button type="submit" class="button-small button">ok, dismiss notice</button>
				</form>
			</p>
		</div>

		<style type="text/css">
			.xoo-usage-consent button{

			}
		</style>

		<?php

	}

	public function handle_usage_click_response(){

		if( !isset( $_POST['xoo_usage_handle'] ) ) return;

		$slug 		= sanitize_text_field( wp_unslash( $_POST['xoo_slug'] ) );
		$nonce 		= sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		$response 	= sanitize_text_field( wp_unslash( $_POST['xoo_allow'] ) );

		if( $this->helper->slug !== $slug ) return;

		if( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'xoo_usage_nonce' ) ) return;

		update_option( 'xoo_tracking_consent_'.$this->helper->slug, $response );

		$this->usage_data_http_request();

		wp_safe_redirect( remove_query_arg( 'xooisrandom' ) );

	}



	public function is_usage_allowed(){
		return get_option( 'xoo_tracking_consent_'.$this->helper->slug, true ) === 'yes';
	}


	public function on_plugin_activation(){

		$option_key = 'xoo_plugin_isactive_' . $this->helper->slug;
		$active 	= get_option( $option_key );

		if( !$active && get_option( 'xoo_tracking_consent_'.$this->helper->slug ) ){ // older version where usage cons shown
			update_option( $option_key, 'yes' );
			return;
		}

		if ( !$active || $active !== 'yes'  ) {

			update_option( $option_key, 'yes' );
		   $this->usage_data_http_request(array(
				'active' => 1
			) );
		}
	}



	public function usage_data_http_request( $passed_data = array() ) {

		$helperdata = $this->helper->get_usage_data();

		$defaults = array(
			'slug'       => $this->helper->slug,
			'site_url'   => get_site_url(),
			'wp_version' => get_bloginfo( 'version' ),
			'active'     => 1,
		);

		$data = array_merge( $defaults, $passed_data, $helperdata );

		wp_remote_post(
			$this->usageURL,
			array(
				'blocking' => false,
				'timeout'  => 1,
				'body'     => $data,
			)
		);
	}



	public function on_plugin_deactivate(){
		$this->usage_data_http_request( array(
			'active' => 0
		) );
		update_option( 'xoo_plugin_isactive_'.$this->helper->slug, 'no' );
	}

	public function export_settings(){

		// Check for nonce security      
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xoo_ff_nonce'] ) ), 'xoo-ff-nonce' ) ) {
			die('cheating');
		}

		if( !current_user_can( $this->capability ) ) return;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$options = $_POST['options'];

		$data = array();

		foreach ( $options as $option_key ) {
			$data[ $option_key ] = get_option( $option_key, true);
		}

		wp_send_json( $data );

	}

	public function import_settings(){

		// Check for nonce security      
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xoo_ff_nonce'] ) ), 'xoo-ff-nonce' ) ) {
			die('cheating');
		}

		if( !current_user_can( $this->capability ) ) return;
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$settings  = $_POST['import'];
	
		$options = json_decode( html_entity_decode( stripslashes ($settings ) ), true );

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
			
		die();

	}

	//Add info tab
	public function set_info_tab(){
		$this->register_tab( 'Info', 'info', '', false, array(
			'priority' 			=> 99,
			'icon' 						=> 'xoo-icon-info',
			'section_sidebar_disable' 	=> 'yes',
		) );
	}

	public function info_tab_data( $tab_id, $tab_data ){
		if( $tab_id !== 'info' ) return;
		echo wp_kses_post( $this->helper->get_outdated_section() );
		?>
		<p>
			<h3>How to translate or change text?</h3>
			<h4>The easiest method is to use a plugin called <a href="https://wordpress.org/plugins/loco-translate/" target="__blank">Loco translate</a>. You can easily change text from your dashboard</h4>
		</p>
		<?php
	}

	public function shortcode_info( $tab_id, $tab_data ){
		if( $tab_id !== 'info' || !$this->viewsPath || !file_exists( $this->viewsPath.'/settings/shortcode-info.php' ) ) return;
		$args = array(
			'shortcodes' => include $this->viewsPath.'/settings/shortcode-info.php'
		);
		$this->helper->get_template( '/admin/templates/global/info-shortcode.php', $args, XOO_FW_DIR );
	}

	public function save_default_settings(){

		if( !current_user_can( $this->capability ) ) return;

		foreach ( $this->settings as $tab_id => $sections ) {

			if( !isset( $this->tabs[ $tab_id ][ 'option_key' ] ) ) continue;

			$option_key = $this->tabs[ $tab_id ][ 'option_key' ];

			$savedOptions = (array) get_option( $option_key, true );

			foreach ( $sections as $settings ) {
				foreach ( $settings as $setting_id => $setting_data ) {
					if( isset( $savedOptions[ $setting_id ] ) ) continue;
					$savedOptions[ $setting_id ] = isset( $setting_data['default'] ) ? $setting_data['default'] : '';
				}
			}

			update_option( $option_key, $savedOptions );
		}

	}


	public function reset_settings(){


		if( !current_user_can( $this->capability ) ) return;

		if( !isset( $_GET['reset'] ) || !isset( $_GET['page'] ) || $this->settings_slug !== $_GET['page'] ) return;

			// Check for nonce security      
		if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['reset'] ) ), 'reset' ) ) {
			die('cheating');
		}


		foreach ( $this->settings as $tab_id => $sections ) {

			if( !isset( $this->tabs[ $tab_id ][ 'option_key' ] ) ) continue;

			update_option( $this->tabs[ $tab_id ][ 'option_key' ], array() );

		}

		wp_safe_redirect( esc_url( remove_query_arg( 'reset' ) ) );

	}


	public function save_settings(){

		// Check for nonce security      
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xoo_ff_nonce'] ) ), 'xoo-ff-nonce' ) ) {
			die('cheating');
		}

		if( !current_user_can( $this->capability ) ) return;

		$formData = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$parseFormData = parse_str( wp_unslash( $_POST['form'] ), $formData );

		$formData = apply_filters( 'xoo_admin_settings_'.$this->helper->slug.'_save_data', $formData );

		do_action( 'xoo_admin_settings_'.$this->helper->slug.'_before_saving', $formData );

		foreach ( $this->settings as $tab_id => $sections_settings ) {

			if( !isset( $this->tabs[ $tab_id ] ) ) continue;

			$option_key = $this->tabs[ $tab_id ]['option_key'];

			if( !isset( $formData[ $option_key ] ) ) continue;

			$this->save_option( $option_key, $sections_settings, $formData[ $option_key ] );
		}

		do_action( 'xoo_admin_settings_'.$this->helper->slug.'_saved', $formData );

		wp_send_json(array(
			'error' 	=> 0,
			'notice' 	=> 'Settings Saved',
		));
	}


	public function save_option( $option_key, $sections_settings, $formData ){

		$option_data = array();

		foreach ( $sections_settings as $section_id => $settings ) {
			
			foreach ( $settings as $setting_id => $setting ) {

				if( !isset( $formData[ $setting_id ] ) ) continue;

				$value = $formData[ $setting_id ];

				$sanitized = false;

				
				if(  ( isset( $setting['args']['group'] ) && $setting['args']['group'] === 'css' ) || strpos( $setting['title'], 'CSS' ) ){
					$value = wp_strip_all_tags( $value );
					$sanitized = true;
				}


				if( isset( $setting['args']['group'] ) ){

					$group = $setting['args']['group'];

					if( $group === 'email_content' ){
						$value = xoo_wp_kses_email( $value );
						$sanitized = true;
					}

				}


				if( isset( $setting['args']['sanitize'] ) && function_exists( $setting['args']['sanitize'] ) ){
					$value 		= call_user_func( $setting['args']['sanitize'], $value );
					$sanitized 	= true;
				}

				
				if( !$sanitized ){
					
					switch ( $setting['callback'] ) {
						case 'textarea':
							$value = xoo_clean( $value, 'wp_kses_post' );
							break;

						case 'wp_editor':
							$value = wp_kses_post( $value );
							break;
						
						default:
							$value = xoo_clean($value);
							break;
					}

					$sanitized = true;

				}


				$option_data[ $setting_id ] = $value;


			}

		}
	
		$option_data = stripslashes_deep( $option_data );

		update_option( $option_key, $option_data );
	}


	public function enqueue_scripts() {

		do_action( 'xoo_as_enqueue_scripts', $this->helper->slug );

		//Select2 CSS file
	    wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
	    //select2js
	    wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
		
		wp_enqueue_media(); // media gallery
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'xoo-admin-style', $this->helper->fw_url . '/admin/assets/css/xoo-admin-style.css', array(), XOO_FW_VERSION, 'all' );
		wp_enqueue_style( 'xoo-admin-fonts', $this->helper->fw_url.'/admin/assets/css/xoo-admin-fonts.css', array(), XOO_FW_VERSION );
		wp_enqueue_script( 'xoo-admin-serializejson', $this->helper->fw_url . '/admin/assets/js/xoo-admin-serializejson.js', array( 'jquery' ), '1.0', true );
		wp_enqueue_script( 'xoo-admin-js', $this->helper->fw_url . '/admin/assets/js/xoo-admin-js.js', array( 'jquery','wp-color-picker', 'jquery-ui-sortable' ), XOO_FW_VERSION, false );
		

		wp_localize_script( 'xoo-admin-js', 'xoo_admin_params', array(
			'adminurl'  => admin_url().'admin-ajax.php',
			'nonce' 	=> wp_create_nonce('xoo-ff-nonce'),
			'slug' 		=> $this->helper->slug 
		) );

	}

	public function register_menu_page( $args = array() ){

		$args = wp_parse_args( $args, array(
			'title' 		=> 'Settings',
			'menu_title' 	=> 'Settings',
			'capability' 	=> $this->capability,
			'slug' 			=> $this->settings_slug,
			'callback' 		=> array( $this,'settings_page_markup' ),
			'position' 		=> null,
			'icon' 			=> '',
			'has_submenu' 	=> false,
		) );

		extract( $args );

		add_menu_page(
			$title,
			$menu_title,
			$capability,
			$slug,
			$callback,
			$icon,
			$position
		);

		if( $has_submenu ){
			add_submenu_page(
				$slug,
				'Settings',
				'Settings',
	    		$capability,
	    		$slug,
	    		$callback
	    	);
		}
	}

	public function register_as_submenu_page( $args = array() ){
		$args = wp_parse_args( $args, array(
			'parent_slug' 	=> 'settings',
			'title' 		=> 'Settings',
			'menu_title' 	=> 'Settings',
			'capability' 	=> $this->capability,
			'slug' 			=> $this->settings_slug,
			'callback' 		=> array( $this,'settings_page_markup' ),
			'position' 		=> null,
		) );

		extract( $args );

		add_submenu_page(
			$parent_slug,
			$title,
			$menu_title,
    		$capability,
    		$slug,
    		$callback
    	);

	}

	public function register_tab( $title, $id, $option_key = '', $pro = 'no', $args = array() ){

		$args = wp_parse_args(
			$args,
			array(
				'priority' => ''
			)
		);


		$priority 	= $args['priority'];
		unset( $args['priority'] );

		$this->tabs[ $id ] = array(
			'title' 		=> $title,
			'id' 			=> $id,
			'option_key' 	=> $option_key,
			'priority' 		=> $priority,
			'pro' 			=> $pro,
			'args' 			=> $args
		); 


	}

	public function register_section( $title, $id, $tab_id, $desc = '', $pro = 'no', $args = array() ){

		$args = wp_parse_args(
			$args,
			array(
				'priority' => ''
			)
		);

		$priority 	= $args['priority'];
		unset( $args['priority'] );

		$this->sections[ $tab_id ][ $id ] = array(
			'title' 	=> $title,
			'id' 		=> $id,
			'tab' 		=> $tab_id,
			'priority' 	=> $priority,
			'desc' 		=> $desc,
			'pro' 		=> $pro,
			'args' 		=> $args
		);
	}

	public function register_setting( $callback, $title, $id, $section_id, $tab_id, $default = '', $desc = '', $pro = 'no', $args = array() ){

		if( !isset( $this->tabs[ $tab_id ] ) || !isset( $this->sections[ $tab_id ][ $section_id ] ) ) return;

		if( $pro === "yes" ){
			$this->hasPRO = true;
		}

		$args = wp_parse_args(
			$args,
			array(
				'priority' 	=> ''
			)
		);

		$priority 	= $args['priority'];

		unset( $args['priority'] );

		$this->settings[ $tab_id ][ $section_id ][ $id ] = $this->raw_settings[] = array(
			'callback' 		=> $callback,
			'title' 		=> $title,
			'id' 			=> $id,
			'section_id' 	=> $section_id,
			'tab_id' 		=> $tab_id,
			'priority' 		=> $priority ,
			'default' 		=> $default,
			'desc' 			=> $desc,
			'pro' 			=> $pro,
			'args' 			=> $args
		);
	}


	public function sort_by_priority( $data = array() ){

		if( !is_array( $data ) || empty( $data ) ) return $data;

		uasort( $data, function( $a, $b ){

			if( !isset( $a['priority'] ) || !isset( $b['priority'] ) || $a['priority'] === $b['priority'] ){
				return 0;
			}
			return $a['priority'] > $b['priority']  ? 1 : -1;
		});

		return $data;

	}



	public function sort(){

		//Sort Tabs
		$this->tabs = $this->sort_by_priority( $this->tabs );

		//Sort Section
		foreach ( $this->sections as $tab_id => $sections ) {

			$priority = 10;

			foreach ( $sections as $section_id => $section_data ) {
				if( !$section_data['priority'] ){
					$this->sections[ $tab_id ][ $section_id ]['priority'] = $priority;
					$priority += 10;
				}
			}


			$this->sections[ $tab_id ] = $this->sort_by_priority( $this->sections[ $tab_id ] );
		}


		//Sorting settings by tabs & sections
		$sorted_settings = array();

		foreach ( $this->tabs as $tab_id => $tab_data ) {

			if( !isset( $this->settings[ $tab_id ] ) ) continue;

			$sorted_settings[ $tab_id ] = $this->settings[ $tab_id ];

			if( !isset( $this->sections[ $tab_id ] ) ) continue;

			foreach ( $this->sections[ $tab_id ] as $section_id => $section_data ) {

				if( !isset( $this->settings[ $tab_id ][ $section_id ] ) ) continue;

				$sorted_settings[ $tab_id ][ $section_id ] = $this->settings[ $tab_id ][ $section_id ];
			}

		}

		$this->settings = $sorted_settings;


		foreach ( $this->settings as $tab_id => $sections ) {

			foreach ( $sections as $section_id => $settings ) {

				$priority = 10;

				foreach ( $settings as $setting_id => $setting_data ) {

					if( !$setting_data['priority'] ){
						$this->settings[ $tab_id ][ $section_id ][ $setting_id ]['priority'] = $priority;
						$priority += 10;
					}
				}

				$this->settings[ $tab_id ][ $section_id ] = $this->sort_by_priority( $this->settings[ $tab_id ][ $section_id ] );

			}

		}

	}


	public function auto_generate_settings(){

		if( !is_dir( $this->viewsPath ) ) return;

		$tabs 		= (array) include $this->viewsPath.'/tabs.php';
		$sections 	= (array) include $this->viewsPath.'/sections.php';

		if( empty( $tabs ) || empty( $sections ) ) return;

		//Register Tabs
		foreach ( $tabs as $tab_id => $tab_data ) {

			$args = isset( $tab_data['args'] ) ? $tab_data['args'] : array();

			if( isset( $tab_data['icon'] ) ){
				$args['icon'] = $tab_data['icon'];
			}

			 $this->register_tab(
			 	$tab_data['title'],
			 	$tab_data['id'],
			 	$tab_data['option_key'],
			 	isset( $tab_data['pro'] ) ? $tab_data['pro'] : 'no',
			 	$args
			 );
		}

		//Register Sections
		foreach ( $sections as $section_data ) {

			$args = isset( $section_data['args'] ) ? $section_data['args'] : array();

			if( isset( $section_data['icon'] ) ){
				$args['icon'] = $section_data['icon'];
			}

			$this->register_section(
			 	$section_data['title'],
			 	$section_data['id'],
			 	$section_data['tab'],
			 	isset( $section_data['desc'] ) ? $section_data['desc'] : '',
			 	isset( $section_data['pro'] ) ? $section_data['pro'] : 'no',
			 	$args
			 );
		}

		//Register Settings
		$settings_folder = $this->viewsPath.'/settings';

		if( !is_dir( $settings_folder ) ) return;

		$settings_files = scandir( $settings_folder );

		foreach ( $settings_files as $setting_file ) {

			$tabID = pathinfo( $setting_file , PATHINFO_FILENAME );
			if( !isset( $tabs[ $tabID ] ) ) continue;
			$tab_settings = (array) include $settings_folder .'/'. $setting_file;

			foreach ( $tab_settings as $setting_data ) {
				$this->register_setting(
					$setting_data['callback'],
				 	$setting_data['title'],
				 	$setting_data['id'],
				 	$setting_data['section_id'],
				 	$tabID,
				 	isset( $setting_data['default'] ) ? $setting_data['default'] : '',
				 	isset( $setting_data['desc'] ) ? $setting_data['desc'] : '',
				 	isset( $setting_data['pro'] ) ? $setting_data['pro'] : 'no',
				 	isset( $setting_data['args'] ) ? $setting_data['args'] : array()
				);
			}

		}

	}


	public function settings_page_markup(){

		$this->sort();

		$args = array(
			'adminObj' 	=> $this,
			'settings' 	=> $this->settings,
			'tabs' 		=> $this->tabs,
			'hasPRO' 	=> $this->hasPRO,
			'hasSidebar' 	=> isset( $this->helper->helperArgs['sidebar'] ) && $this->helper->helperArgs['sidebar']
		);

		$args = apply_filters( 'xoo_admin_settings_output_args', $args, $this->helper->slug, $this );

		$this->helper->get_template( '/admin/templates/xoo-admin-settings-output-new.php', $args, XOO_FW_DIR  );
	}


	public function get_setting_upload_markup( $id, $value = '' ){
		$args = array(
			'id' => $id,
			'value' => $value
		);
		return $this->helper->get_template( '/admin/templates/global/setting-upload.php', $args, XOO_FW_DIR, true  );
	}


	public function create_settings_html( $tab_id ) {

		if ( ! isset( $this->settings[ $tab_id ] ) ) {
			return;
		}

		$tab_settings = $this->settings[ $tab_id ];
		$option_key   = $this->tabs[ $tab_id ]['option_key'];
		$option_value = (array) get_option( $option_key, true );

		ob_start();

		foreach ( $tab_settings as $section_id => $settings ) :

			$section_data     = $this->sections[ $tab_id ][ $section_id ];
			$section_settings = '';

			foreach ( $settings as $setting_id => $setting_data ) {

				$id    = $option_key . '[' . $setting_id . ']';
				$value = $option_value[ $setting_id ] ?? null;

				$section_settings .= $this->get_setting_html(
					$id,
					$setting_data,
					$value
				);

			}

			if ( ! $section_settings ) {
				continue;
			}

			$section_class = array(
				'xoo-ass-section',
				'xoo-ass-' . $tab_id . '-' . $section_id,
			);

			if ( $section_data['pro'] === 'yes' ) {
				$section_class[] = 'xoo-ass-pro-sec';
			}
			?>

			<div id="<?php echo esc_attr( $tab_id . '_' . $section_id ); ?>" class="<?php echo esc_attr( implode( ' ', $section_class ) ); ?>">

				<div  class="<?php echo esc_attr( 'xoo-asc-head xoo-asc-' . $section_id ); ?>" >

					<div>

						<?php if ( ! empty( $section_data['args']['icon'] ) ) : ?>
							<span class="<?php echo esc_attr( 'xoo-as-icon ' . $section_data['args']['icon'] ); ?>"></span>
						<?php endif; ?>

						<span class="xoo-asch-title <?php if ( $section_data['pro'] === 'yes' ) echo 'xoo-as-is-pro' ?>"><?php echo esc_html( $section_data['title'] ); ?></span>

					</div>

					<?php if ( ! empty( $section_data['desc'] ) ) : ?>
						<span class="xoo-asc-desc">
							<?php echo wp_kses_post( $section_data['desc'] ); ?>
						</span>
					<?php endif; ?>

				</div>

				

				<?php echo $section_settings; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			</div>

			<?php

		endforeach;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo ob_get_clean();
	}


	public function get_setting_html_pop( $tab_id, $section_id, $field_id ){

		$option_key 	= $this->tabs[ $tab_id ]['option_key'];
		$id 			= $option_key.'['.$field_id.']';

		$setting = $this->settings[ $tab_id ][ $section_id ][ $field_id ];

		return $this->get_setting_html( $id, $setting  );
	}


	public function get_setting_html( $field_id, $field_args, $value = null ){

		$field_args = wp_parse_args( $field_args, array(
			'callback' 			=> 'text',
			'default' 			=> '',
			'desc' 				=> '',
			'pro' 				=> 'no',
			'label_class' 		=> array(),
			'container_class' 	=> array(),
		) );

		extract( $field_args );

		if ( is_null( $value ) ) {
			$value = $default;
		}

		$custom_attributes = isset( $args['custom_attributes'] ) ? $args['custom_attributes'] : array();

		if( $callback === 'sortable' && isset( $args['sort_options'] ) ){
			$custom_attributes['data-options'] = $args['sort_options'];
		}

		if( $callback === 'select' && isset( $args['select2box'] ) ){
			$custom_attributes['data-select2box'] = $args['select2box'] ? 'yes' : 'no';
			if( isset( $args['multiple'] ) && $args['multiple'] ){
				$custom_attributes['multiple'] = '';
			}
			$container_class[] = 'xoo-as-select2box';
		}

		$custom_attributes_html = array();

		if ( ! empty( $custom_attributes ) && is_array( $custom_attributes ) ) {
			foreach ( $custom_attributes as $attribute => $attribute_value ) {
				$attribute_value = is_array( $attribute_value ) ? json_encode( $attribute_value ) : $attribute_value;
				$custom_attributes_html[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		$custom_attributes 	= implode( ' ', $custom_attributes_html );

		$container_class = array_merge( $container_class, array(
			'xoo-as-setting', 'xoo-as-'.$callback
		) );

		if( $pro === "yes" ){
			$container_class[] = 'xoo-as-is-pro';
		}

		if( $callback === 'radio' && isset( $args['options'], $args['has_asset'] ) ){
			$container_class[] = 'xoo-as-has-asset';
		}

		$label_class = array_merge( $label_class, array(
			'xoo-as-label'
		) );


		if ( $callback === 'wp_editor' ) {

			$editor_settings = isset( $args['editor_settings'] ) ? $args['editor_settings'] : array();
			$editor_settings = xoo_recursive_parse_args( $editor_settings, array(
				'textarea_name' => $field_id,
				'wpautop'       => false,

				'tinymce' => array(
					'teeny'   => false,
					/* Toolbar */
					'toolbar1' => 'formatselect,fontselect,styleselect,fontsizeselect,bold,italic,underline,forecolor,backcolor,alignleft,aligncenter,alignright,removeformat,code,hr',

					/* Font sizes */
					'fontsize_formats' => '12px 14px 15px 16px 18px 20px 22px 24px 28px 32px',
					/* Paragraph handling */
					'forced_root_block' => 'p',

					'init_instance_callback' => 'function(editor) {
						editor.settings.forced_root_block_attrs = {
							style: "margin:0 0 16px 0;"
						};
					}'
				)
			) );

			if ( isset( $args['group'] ) && $args['group'] === 'email_content' ) {

				$editor_settings = xoo_recursive_parse_args( $editor_settings, array(
					
					'tinymce' => array(

						/* Valid styles (JSON REQUIRED) */
						'valid_styles' => wp_json_encode( array(
							'*' => 'color,font-size,font-weight,font-style,text-decoration,background-color,text-align,margin,padding',
						) ),

						'extended_valid_elements' => 'span[style],a[href|style],p[style],div[style],br',

						/* Protect placeholders (JSON REQUIRED) */
						'protect' => wp_json_encode( array(
							'/{[^}]+}/g',
						) ),

						/* Clean & predictable output */
						'verify_html'        => false,
						'cleanup'            => false,
						'convert_urls'       => false,
						'remove_script_host' => false,
					),
				));
			}
		}

		
		$toggleDataHTML = isset( $args['toggleSettings'] )  ? "data-togglesettings=".esc_attr( wp_json_encode( $args['toggleSettings'] ) ) : '';

		$field_container = '<div class="%1$s" data-setting="%3$s" data-field_id="'.$field_id.'" '.$toggleDataHTML.'>%2$s</div>';

		$field = '';



		switch ( $callback ) {

			case 'border':
				$value = is_array( $value ) ? $value : array();
				$field .= $this->get_border_setting_html( $field_id, $value );;
				break;

			case 'wp_editor':
				ob_start();
				wp_editor( $value, sanitize_title_with_dashes( $field_id ), $editor_settings );
				$field .=  ob_get_clean();
				break;

			case 'text':
			case 'number':
				$field .= '<input type="'.$callback.'" name="'.$field_id.'" value="'.esc_attr( $value ).'" '.$custom_attributes.'>';
				break;

			case 'textarea':
				$rows  	= isset( $args['rows'] ) ? $args['rows'] : 4;
				$cols 	= isset( $args['cols'] ) ? $args['cols'] : 50;
				$field .= '<textarea name="'.$field_id.'" rows="'.$rows.'" cols="'.$cols.'" '.$custom_attributes.'>'.esc_textarea( $value ).'</textarea>';
				break;

			case 'color':
				$field .= '<input type="text" name="'.$field_id.'" class="xoo-as-color-input" value="'.esc_attr($value).'" '.$custom_attributes.'>';
				break;

			case 'checkbox':
				$field 	= '<label class="xoo-as-switch">';
				$field .= '<input type="hidden" name="'.$field_id.'" value="no">';
				$field .= '<input name='.$field_id.' type="checkbox" value="yes" '.checked( esc_attr( $value ), 'yes', false ).' '.$custom_attributes.'>';
				$field .= '<span class="xoo-as-slider"></span>';
				$field .= '</label>';
				break;

			case 'checkbox_list':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				$field .= '<input name="'.$field_id.'[]" type="hidden" value="">';

				foreach ( $args['options'] as $option_key => $option_label ) {

					$checked 	= 	is_array( $value ) && in_array( $option_key, $value ) ? 'checked' : '';
					$pro_class 	= 	is_array( $pro ) && in_array( $option_key, $pro ) ? 'xoo-as-is-pro' : '';

					$checkbox_list = '<label class="%1$s">%2$s</label>';

					$list_html  = '<input name="'.$field_id.'[]" type="checkbox" value="'.$option_key.'" '.$checked.'>';
					$list_html .= '<span>'.$option_label.'</span>';

					$field .= sprintf( $checkbox_list, $pro_class, $list_html );

				}

				break;


			case 'select':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				$select 	= '<select name="%1$s" '.$custom_attributes.'>%2$s</select>';
				$options 	= '';


				foreach ( $args['options'] as $option_key => $option_label ) {
					$selected = is_array( $value ) ? in_array( $option_key , $value ) : $value === $option_key;
					$selected = $selected ? 'selected' : '';
					$options .= '<option value="'.$option_key.'" '.$selected.'>'.$option_label.'</option>';
				}

				$select_field_id = isset( $args['multiple'] ) && $args['multiple'] ? $field_id.'[]' : $field_id;

				$field .= sprintf( $select, $select_field_id, $options );

				break;


			case 'radio':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				$has_asset 		= isset( $args['has_asset'] );
				$asset_type 	= isset( $args['asset_type'] ) ? $args['asset_type'] : 'text';

				foreach ( $args['options'] as $option_key => $option_label ) {

					$radio_list 		= '<label>%1$s</label>';

					$list_html  		= '<input name="'.$field_id.'" type="radio" value="'.$option_key.'" '.checked( $value, $option_key, false ).'>';

					$label_container 	= '<span class="xoo-as-radio-label">%s</span>';
					$label_html 		= '';

					if( $has_asset ){

						if( $asset_type === 'icon' ){
							$label_html .= '<span class="xoo-as-ra-icon '.$option_key.'"></span>';
						}elseif ( $asset_type === 'image' ) {
							$label_html .= '<img src="'.$option_key.'">'.$option_label;
						}
						else{
							$label_html .= $option_label;
						}
						
					}
					else{
						$label_html .= $option_label;
					}
						
					$list_html .= sprintf( $label_container, $label_html );	

					$field .= sprintf( $radio_list, $list_html );

				}
				
				break;


			case 'links':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				foreach ( $args['options'] as $url => $label ) {
					$field .= sprintf( '<a href="%1$s">%2$s</a>', $url, $label );
				}

				break;


			case 'sortable':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				$sortedOptions = array();

				foreach ( $value as $optionKey ) {
					$sortedOptions[ $optionKey ] = $args['options'][$optionKey];
				}

				$sort_container = '<ul data-id="%1$s" class="%2$s" '.$custom_attributes.'>%3$s</ul>';
				$sort_children  = '';


				foreach ( $sortedOptions as $option_key => $option_label ) {

					$child 	= '<li>%1$s %2$s</li>';
					$input 	= '<input name="'.$field_id.'[]" type="hidden" value="'.$option_key.'">';

					$sort_children .= sprintf( $child, $option_label, $input );

				}

				$display = isset( $args['display'] ) ? $args['display'] : 'vertical';

				$field .= sprintf( $sort_container, $field_id, $display.' xoo-as-sortable-list', $sort_children );
				
				break;

			case 'upload':
				$field .= $this->get_setting_upload_markup( $field_id, $value );


			case 'asset_selector':

				if( !isset( $args['options'] ) || empty( $args['options'] ) ) break;

				$allowMultiple = isset( $args['custom_attributes']['data-multiple'] ) && $args['custom_attributes']['data-multiple'] === "yes";

				$name =  $allowMultiple ? $field_id.'[]' : $field_id;

				$field .= '<div class="xoo-as-pattern-cont" '.$custom_attributes.'>';

				$info_html = '';

				foreach ( $args['options'] as $option_key => $option_data ) {

					$checked 	= 	( is_array( $value ) && in_array( $option_key, $value ) ) || ( !is_array( $value ) && $value === $option_key ) ? 'checked' : '';

					$option_html 	 = '<div class="xoo-as-pat-imgcont">';

					$option_html 	.= '<img class="xoo-as-patimg" src="'.$option_data['asset'].'" data-key="'.$option_key.'">';

					$option_html  	.= '<input class="xoo-as-patcheckbox" name="'.$name.'" type="checkbox" value="'.$option_key.'" '.$checked.'>';

					$option_html 	.= '</div>';

					$option_html 	.= '<div class="xoo-as-patdesc">';

					$option_html 	.= '<span>'.$option_data['title'].'</span>';

					if( isset( $option_data['info'] ) ){
						$option_html 	.= '<span class="dashicons dashicons-info xoo-as-info-hover" data-key="'.$option_key.'"></span>';
						$info_html 		.= '<span class="xoo-as-info" data-key="'.$option_key.'">'.$option_data['info'].'</span>';
					}

					$option_html 	.= '</div>';
						
					$field .= sprintf( '<div>%1$s</div>', $option_html );

				}

				$field .= $info_html;

				$field .= '</div>';

				break;



			case 'button_theme_creator':

				$value = is_array( $value ) ? $value : array();

				$defaults = array_merge( array(
					'theme_id' 		=> '',
					'title' 		=> 'Theme [%^]',
				), $this->helper->get_button_values() );


				if( !empty( $value ) ){

					foreach ( $value as $theme_id => $theme_values ) {
						$value[ $theme_id ] =  xoo_recursive_parse_args(
							$theme_values,
							$defaults
						);
					}
					
				}


				$units = array(
					'px' => 'px',
					'%'  => '%',
					'em' => 'em',
					'rem'=> 'rem'
				);

				ob_start();
				?>

				<div class="xoo-btntheme-cont" data-value="<?php echo esc_attr( wp_json_encode( $value ) ); ?>" data-defaults='<?php echo esc_attr( wp_json_encode( $defaults ) ); ?>'>

					<button type="button" class="xoo-btn xoo-btn-primary xoo-add-btntheme"><span class="xoo-as-icon xoo-icon-plus"></span>New Theme</button>

					<div class="xoo-btnthemes"></div>

				</div>

				<?php

				$field .= ob_get_clean();

				$field .= $this->helper->get_template( '/admin/templates/global/button-theme.php', array( 'adminObj' => $this, 'field_id' => $field_id ), XOO_FW_DIR );

				break;

			case 'button_theme_selector':

				$select 	= '<select name="'.$field_id.'" '.$custom_attributes.' data-default="'.$value.'"></select>';

				$field 		.= $select;

				break;

		
			case 'button':

				$value = is_array( $value ) ? $value : array();

				$value = xoo_recursive_parse_args(
					$value,
					array(
						'width'         => 300,
						'width_unit'    => 'px',
						'height'        => 40,
						'height_unit'   => 'px',
						'bgColor'       => '#000000',
						'txtColor'      => '#ffffff',

						'text' => array(
							'fontWeight' 		=> 500,
							'fontStyle' 		=> 'normal',
							'fontSize' 			=> 15,
							'fontSizeUnit' 		=> 'px',
							'textTransform' 	=> 'capitalize',
						),

						'border' => array(
							'size'      => 1,
							'color'     => '#cccccc',
							'style'     => 'solid',
							'radius'    => 5,
						),

						'hover' => array(
							'bgColor'       => '#eee',
							'txtColor'      => '#000',

							'border' => array(
								'size'      => 1,
								'color'     => '#cccccc',
								'style'     => 'solid',
								'radius'    => 5,
							),
						),
					)
				);

				$units = array(
					'px' => 'px',
					'%'  => '%',
					'em' => 'em',
					'rem'=> 'rem'
				);

				ob_start();
				?>

				<div class="xoo-btn-setting xoo-tabs-cont" data-field_id="<?php echo  esc_attr( $field_id ) ?>">

					<span class="xoo-btnset-desc">Customize the appearance of your button</span>

					<div class="xoo-btn-preview-wrap">

						<div class="xoo-btn-preview">
							<button type="button">Button</button>
						</div>

					</div>

					<div class="xoo-setting-tabs">

						<span class="xoo-set-tab xoo-tabactive" data-xootab="normal"><span class="xoo-icon-light xoo-icon"></span>Normal</span>

						<span class="xoo-set-tab" data-xootab="hover"><span class="xoo-icon-cursor xoo-icon"></span>Hover</span>

					</div>

					<!-- NORMAL -->
					<div class="xoo-btn-group xoo-tabgroup xoo-tabactive" data-xootab="normal">

						<!-- Colors -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-paint xoo-icon"></span>Colors</span>

							<div class="xoo-row-settings">

								<div>
									<i>Background</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr( $field_id ); ?>[bgColor]" value="<?php echo esc_attr( $value['bgColor'] ); ?>" >
								</div>

								<div>
									<i>Text Color</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr( $field_id ); ?>[txtColor]" value="<?php echo esc_attr( $value['txtColor'] ); ?>" >
								</div>

							</div>

						</div>

						<!-- Size -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-ruler xoo-icon"></span>Size</span>

							<div class="xoo-row-settings">

								<div>

									<i>Width</i>
									<input type="number" name="<?php echo esc_attr( $field_id ); ?>[width]" value="<?php echo esc_attr( $value['width'] ); ?>" >

								</div>

								<div>

									<i>Unit</i>
									<select name="<?php echo esc_attr( $field_id ); ?>[width_unit]">

										<?php foreach ( $units as $unit ) : ?>

											<option value="<?php echo esc_attr( $unit ); ?>"
												<?php selected( $value['width_unit'], $unit ); ?>>
												<?php echo esc_html( $unit ); ?>
											</option>

										<?php endforeach; ?>
									</select>


								</div>

								<div>

									<i>Height</i>
									<input type="number" name="<?php echo esc_attr( $field_id ); ?>[height]" value="<?php echo esc_attr( $value['height'] ); ?>" >	

								</div>



								<div>

									<i>Unit</i>
									<select name="<?php echo esc_attr( $field_id ); ?>[height_unit]">

										<?php foreach ( $units as $unit ) : ?>
											<option value="<?php echo esc_attr( $unit ); ?>"
												<?php selected( $value['height_unit'], $unit ); ?>>
												<?php echo esc_html( $unit ); ?>
											</option>
										<?php endforeach; ?>

									</select>	

								</div>

							</div>

						</div>

						<!-- Text -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-font xoo-icon"></span>Text</span>

							<div class="xoo-row-settings">

								<div>

									<i>Weight</i>

									<select name="<?php echo esc_attr( $field_id ); ?>[text][fontWeight]">

										<option value="300" <?php selected( $value['text']['fontWeight'], 300 ); ?>>300</option>
										<option value="400" <?php selected( $value['text']['fontWeight'], 400 ); ?>>400</option>
										<option value="500" <?php selected( $value['text']['fontWeight'], 500 ); ?>>500</option>
										<option value="600" <?php selected( $value['text']['fontWeight'], 600 ); ?>>600</option>
										<option value="700" <?php selected( $value['text']['fontWeight'], 700 ); ?>>700</option>

									</select>

								</div>

								<div>

									<i>Style</i>

									<select name="<?php echo esc_attr( $field_id ); ?>[text][fontStyle]">

										<option value="normal" <?php selected( $value['text']['fontStyle'], 'normal' ); ?>>Normal</option>
										<option value="italic" <?php selected( $value['text']['fontStyle'], 'italic' ); ?>>Italic</option>

									</select>

								</div>

								<div>

									<i>Font Size</i>

									<input type="number" name="<?php echo esc_attr( $field_id ); ?>[text][fontSize]" value="<?php echo esc_attr( $value['text']['fontSize'] ); ?>">

								</div>

								<div>

									<i>Unit</i>

									<select name="<?php echo esc_attr( $field_id ); ?>[text][fontSizeUnit]">

										<?php foreach ( $units as $unit ) : ?>

											<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $value['text']['fontSizeUnit'], $unit ); ?>>
												<?php echo esc_html( $unit ); ?>
											</option>

										<?php endforeach; ?>

									</select>

								</div>

								<div>

									<i>Transform</i>

									<select name="<?php echo esc_attr( $field_id ); ?>[text][textTransform]">

										<option value="none" <?php selected( $value['text']['textTransform'], 'none' ); ?>>None</option>
										<option value="uppercase" <?php selected( $value['text']['textTransform'], 'uppercase' ); ?>>Uppercase</option>
										<option value="lowercase" <?php selected( $value['text']['textTransform'], 'lowercase' ); ?>>Lowercase</option>
										<option value="capitalize" <?php selected( $value['text']['textTransform'], 'apitalize' ); ?>>Capitalize</option>

									</select>

								</div>

							</div>

						</div>

						<!-- Border -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-border xoo-icon"></span>Border</span>
							
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $this->get_border_setting_html( $field_id.'[border]', $value['border'] );
							?>

						</div>

					</div>

					<!-- HOVER -->
					<div class="xoo-btn-group xoo-tabgroup" data-xootab="hover">

						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-paint xoo-icon"></span>Colors</span>

							<div class="xoo-row-settings">

								<div>
									<i>Background</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr( $field_id ); ?>[hover][bgColor]" value="<?php echo esc_attr( $value['hover']['bgColor'] ); ?>">									
								</div>

								<div>
									<i>Text Color</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr( $field_id ); ?>[hover][txtColor]" value="<?php echo esc_attr( $value['hover']['txtColor'] ); ?>" >
								</div>

							</div>

						</div>

						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-border xoo-icon"></span>Border</span>

							<?php

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $this->get_border_setting_html( $field_id.'[hover][border]', $value['hover']['border'] );

							?>

						</div>

					</div>

				</div>

				<?php

				$field .= ob_get_clean();

				break;
			
			default:
				# code...
				break;
		}

		$field = apply_filters( 'xoo_admin_setting_field_callback_html', $field, $field_id, $value, $args );

		if( isset( $args['reset'] ) && $args['reset'] === "yes" ){
			$field .= '<span class="xoo-as-resetval" data-default="'.esc_attr( wp_json_encode( $default) ).'">Reset to default value</span>';
		}

		if( isset( $args['value_desc'] ) && !empty( $args['value_desc'] ) ){
			$defaultValueDesc = isset( $args['value_desc'][$value] ) ? $args['value_desc'][$value] : '';
			$field .= '<div class="xoo-as-val-desc" data-desc="'.esc_attr( wp_json_encode( $args['value_desc'] ) ).'">'.$defaultValueDesc.'</div>';
		}

		if( $desc ){
			$field .= '<span class="xoo-as-desc">'.$desc.'</span>';
		}

		if( isset( $args['placeholders'] ) && !empty( $args['placeholders'] ) ){
			$field .= '<div class="xoo-as-placeholders">';
			$field .= '<span>Placeholders</span>';
			$field .= '<ul>';
			foreach ($args['placeholders'] as $placeholder_code => $placeholder_title ) {
				$field .= '<li><span>'.$placeholder_code.' :</span><span>'.$placeholder_title.'</span></li>';
			}
			$field .= '</ul>';
			$field .= '</div>';
			
		}

		if( isset( $title ) && $title ){
			$label = '<div class="xoo-as-label">'.$title.'</div>';
			$field = $label.'<div class="xoo-as-field">'.$field.'</div>';
		}
		
		

		$container_class 	= implode( ' ' , $container_class );
		$field 				= sprintf( $field_container, $container_class, $field, $callback );

		return apply_filters( 'xoo_admin_setting_field', $field, $field_id, $value, $args );

	}

	private function get_border_setting_html( $field_id, $value = array() ) {

		$value = wp_parse_args(
			$value,
			array(
				'size' 		=> 1,
				'color' 	=> '#ccc',
				'style' 	=> 'solid',
				'radius' 	=> 0,
			)
		);

		$styles = array(
			'none',
			'hidden',
			'solid',
			'dashed',
			'dotted',
			'double',
			'groove',
			'ridge',
			'inset',
			'outset'
		);

		ob_start();
		?>

		<div class="xoo-row-settings">

			<div>
				<i>Size</i>
				<input name="<?php echo esc_attr( $field_id ); ?>[size]" type="number" min="0" value="<?php echo esc_attr( $value['size'] ); ?>">
			</div>

			<div>
				<i>Color</i>
				<input name="<?php echo esc_attr( $field_id ); ?>[color]" type="text" class="xoo-as-color-input" value="<?php echo esc_attr( $value['color'] ); ?>">
			</div>

			<div>

				<i>Style</i>
				<select name="<?php echo esc_attr( $field_id ); ?>[style]">

					<?php foreach ( $styles as $style ) : ?>

						<option value="<?php echo esc_attr( $style ); ?>" <?php selected( $value['style'], $style ); ?>>
							<?php echo esc_html( ucfirst( $style ) ); ?>
						</option>

					<?php endforeach; ?>

				</select>


			</div>

			<div>
				<i>Radius</i>
				<input name="<?php echo esc_attr( $field_id ); ?>[radius]" type="number" min="0" value="<?php echo esc_attr( $value['radius'] ); ?>">
			</div>

		</div>

		<?php

		return ob_get_clean();

	}


	
	public function add_button_theme_creator(){
		?>

		<div class="xoo-wsc-btntheme-cont">

			<button type="button" class="button button-primary xoo-wsc-add-btntheme">+ Add a new button theme</button>

			<div class="xoo-wsc-btnthemes"></div>

		</div>

		<?php
		include XOO_FW_DIR.'/admin/templates/global/button-theme.php';
	}

	public function templatejs_select_options( $name, $options ) {
		foreach ( $options as $option_value => $title ) {
			?>
			<option
				value="<?php echo esc_attr( $option_value ); ?>"
				{{ data.<?php echo esc_attr( $name ); ?> == '<?php echo esc_js( $option_value ); ?>' ? 'selected' : '' }}
			><?php echo esc_html( $title ); ?></option>
			<?php
		}
	}


}