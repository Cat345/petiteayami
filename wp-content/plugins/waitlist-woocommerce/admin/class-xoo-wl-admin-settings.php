<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Xoo_Wl_Admin_Settings{

	protected static $_instance = null;

	public $capability;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){
		$this->capability = isset( xoo_wl_helper()->admin->capability ) ? xoo_wl_helper()->admin->capability : 'administrator';
		$this->hooks();	
	}

	public function hooks(){

		if( current_user_can( $this->capability ) ){
			add_action( 'init', array( $this, 'generate_settings' ), 0 );
			add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
			add_action( 'init', array( $this, 'clear_email_log' ) );
			add_action( 'init', array( $this, 'save_list_view_preference' ) );
		}

		add_filter( 'plugin_action_links_' . XOO_WL_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'wc_edit_product_custom_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'wc_edit_product_save_custom_fields' ) );

		add_action( 'admin_init', array( $this, 'preview_email' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'xoo_tab_page_end', array( $this, 'display_shortcodes_list' ), 10, 2 );

		add_action( 'xoo_tab_page_start', array( $this, 'display_preview_template_form' ), 10, 2 );
		add_action( 'xoo_tab_page_end', array( $this, 'display_preview_template_form' ), 10, 2 );

		add_filter( 'xoo_aff_add_fields', array( $this,'add_new_fields' ), 10, 2 );
		add_action( 'xoo_aff_field_selector', array( $this, 'customFields_addon_notice' ) );


		if( xoo_wl_helper()->admin->is_settings_page() ){
			remove_action( 'xoo_tab_page_start', array(  xoo_wl_helper()->admin, 'info_tab_data' ), 10, 2 );
			add_action( 'xoo_tab_page_end', array(  $this, 'troubleshoot_info' ), 10, 2 );
			add_action( 'xoo_tab_page_start', array(  $this, 'other_info' ), 35, 2 );
		}

		add_action( 'wp_loaded', array( $this, 'register_addons_tab' ), 20 );
		add_action('xoo_tab_page_start', array( $this, 'addon_html' ), 10, 2 );

		add_action( 'xoo_as_setting_sidebar_waitlist-woocommerce', array( $this, 'sidebar_html' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'old_version_import_export_addon_compatibility' ) );

	}


	public function save_list_view_preference(){

		if( !isset( $_GET['page'] ) || $_GET['page'] !== 'xoo-wl-view-waitlist' || !isset( $_GET['list'] ) ) return;
 
		
		$allowed = array( 'products', 'waitlist' );
		if( in_array( $_GET['list'], $allowed ) ){
			update_option( 'xoo-wl-list-view', sanitize_text_field( $_GET['list'] ) );
			wp_safe_redirect( remove_query_arg( 'list' ) );
		} 
		

	}


	public function sidebar_html(){
		?>

		<ol>

			<?php if ( defined( 'ELEMENTOR_PRO_VERSION' ) ): ?>

				<li>For product pages created with <code>Elementor</code>, the widget can be added directly via the Elementor editor. Simply search for <code>Waitlist</code> to locate and insert the widget.</li>

			<?php endif; ?>
			
			<li><code>[xoo_wl_form]</code> shortcode is used to display the waitlist form. If you're not using this shortcode on a product page or outside the product loop, you must also add the <code>id</code> attribute. Please refer to the "Info" tab for more details.</li>
			
			
		</ol>

		<?php
	}



	public function clear_email_log(){
		if( !isset( $_GET['clearLog'] ) || !wp_verify_nonce( $_GET['_wpnonce'] ) ) return;
		xoo_wl_db()->clear_completed_crons();
		wp_redirect( remove_query_arg(array( 'clearLog', '_wpnonce' ) ) );
		exit;
	}

	
	public function other_info( $tab_id ){
		if( $tab_id !== 'info' ) return;
		?>
		<div>
			
			<h3>Waitlist button visibility</h3>
			<p style="font-size: 16px;">By default, waitlist button will appear for all the out of stock items.<br>
			 You can also manage the visibility of the waitlist button for each product by going to the product page, selecting 'Inventory,' and choosing the option from there.<br>
				There are two options available.<br>
			1) Always show waitlist button irrespective of the stock status.<br>
			2) Do not show waitlist button for this product</p>
		</div>
		<?php
	}

	public function register_addons_tab(){
		xoo_wl_helper()->admin->register_tab( 'Add-ons', 'addon' );
	}

	public function addon_html( $tab_id, $tab_data ){

		if( !xoo_wl_helper()->admin->is_settings_page() ) return;

		if( $tab_id === 'addon' ){
			xoo_wl_helper()->get_template( '/admin/views/settings/add-ons.php', array(), XOO_WL_PATH );
		}

		if( $tab_id === 'info' ){
			echo xoo_wl_helper()->get_outdated_section().'<br>';
		}
	}


	public function troubleshoot_info( $tab_id, $tab_data ){
		if( $tab_id !== 'info' ) return;
		?>
		<div>
			
			<h3>How to translate or change text?</h3>
			<ol>
				<li>Form fields texts can be changed from <a href="<?php echo admin_url('admin.php?page=xoo-wl-fields') ?>" target="__blank">Fields page</a></li>
				<li>Some texts can be changed from the settings.</li>
			</ol>
			<h4>Translation</h4>
			<ul>
				<?php if( defined('TRP_PLUGIN_VERSION') ): ?>
					<li><a href="https://docs.xootix.com/waitlist-for-woocommerce#translatepress" target="__blank">How to translate emails with Translatepress?</a></li>
				<?php endif; ?>
				<li>You can use plugin <a href="https://wordpress.org/plugins/loco-translate/" target="__blank">Loco Translate</a> to translate all plugin texts.</li>
				<li>Plugin is also compatible with multilingual plugins such as WPML, Polylang & Translatepress</li>
			</ul>
		</div>

		<div class="xoo-el-trob">
			<h3>Troubleshoot</h3>
			<ul class="xoo-el-li-info">
				<li>
					<span>Unable to send email / Not receiving emails</span>
					<p>Please make sure that the email functionality on your site is working, means you're receiving other emails from your site. Start by setting up this excellent <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="__blank">SMTP Plugin</a> for better email deliverability </p>
				</li>

				<li>
					<span>Something else</span>
					<p>If something else isn't working as expected. please open a support ticket <a href="https://xootix.com/contact" target="__blank">here</a></p>
				</li>
			</ul>
		</div>
		<?php
	}



	public function customFields_addon_notice( $aff ){
		if( defined( 'XOO_WLCF_VERSION' ) || $aff->plugin_slug !== 'waitlist-woocommerce' ) return;
		?>
		<a class="xoo-wl-field-addon-notice" href="https://xootix.com/waitlist-for-woocommerce#sp-addons" target="__blank" ><span class="dashicons dashicons-admin-links"></span> Adding custom fields is a separate add-on.</a>
		<?php
	}

	public function add_new_fields( $allow, $aff ){
		if( $aff->plugin_slug === 'waitlist-woocommerce' ) return false;
		return $allow;
	}
	

	public function display_preview_template_form( $tab_id, $tab_data ){
		if( $tab_id === 'email' || $tab_id === 'email-style' ){
			$this->get_preview_template_form();
			if( defined('TRP_PLUGIN_VERSION') ){
				?>
				<a href="https://docs.xootix.com/waitlist-for-woocommerce#translatepress" target="__blank" style="display: table; margin-bottom: 20px;">How to translate emails with Translatepress?</a>
				<?php
			}
		}
		
	}

	public function display_shortcodes_list( $tab_id, $tab_data ){
		if( $tab_id !== 'email' ) return;
		include XOO_WL_PATH.'/admin/templates/xoo-wl-shortcodes-list.php';
	}

	public function generate_settings(){
		xoo_wl_helper()->admin->auto_generate_settings();
	}



	public function add_menu_pages() {

		$args = array(
			'title'        => 'Waitlist',
			'menu_title'   => 'Waitlist',
			'capability'   => $this->capability,
			'slug'         => 'xoo-wl-view-waitlist',
			'callback'     => array( $this, 'view_waitlist_page' ),
			'icon'         => 'dashicons-editor-ul',
			'has_submenu'  => false,
		);

		xoo_wl_helper()->admin->register_menu_page( $args );

		add_submenu_page(
			'xoo-wl-view-waitlist',
			'Settings',
			'Settings',
			$this->capability,
			xoo_wl_helper()->admin->settings_slug,
			array( xoo_wl_helper()->admin, 'settings_page_markup' )
		);

		// Other pages ONLY
		add_submenu_page(
			'xoo-wl-view-waitlist',
			'Form Fields',
			'Form Fields',
			$this->capability,
			'xoo-wl-fields',
			array( $this, 'admin_fields_page' )
		);

		add_submenu_page(
			'xoo-wl-view-waitlist',
			'Email Log',
			'Email Log',
			$this->capability,
			'xoo-wl-email-history',
			array( $this, 'view_email_history_page' )
		);
	}




	/**
	 * Show action links on the plugin screen.
	 *
	 * @param	mixed $links Plugin Action links
	 * @return	array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' 	=> '<a href="' . admin_url( 'admin.php?page=waitlist-woocommerce-settings' ) . '">Settings</a>',
			'support' 	=> '<a href="https://xootix.com/contact" target="__blank">Support</a>',
			'addons' 	=> '<a href="https://xootix.com/plugins/waitlist-for-woocommerce/" target="__blank">Add-ons</a>',
		);

		return array_merge( $action_links, $links );
	}



	public function wc_edit_product_custom_fields(){

		$waitlist_disable 	= get_post_meta( get_the_ID(), '_xoo_waitlist_disable', true );
		$waitlist_forceshow = get_post_meta( get_the_ID(), '_xoo_waitlist_force_show', true );

    	woocommerce_wp_checkbox(
			array(
				'id'          	=> '_xoo_waitlist_disable',
				'label'       	=> 'Do not show waitlist button for this product',
				'cbvalue' 		=> 'yes',
				'value' 		=> $waitlist_disable
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          	=> '_xoo_waitlist_force_show',
				'label'       	=> 'Always show waitlist button irrespective of the stock status.',
				'cbvalue' 		=> 'yes',
				'value' 		=> $waitlist_forceshow
			)
		);

	}

	public function wc_edit_product_save_custom_fields( $post_id ){
		update_post_meta( $post_id, '_xoo_waitlist_disable', isset( $_POST['_xoo_waitlist_disable'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_xoo_waitlist_force_show', isset( $_POST['_xoo_waitlist_force_show'] ) ? 'yes' : 'no' );
	}



	public function preview_email(){

		if( isset( $_GET['page'] ) && $_GET['page'] === 'waitlist-woocommerce-settings' && isset( $_GET['preview'] ) && isset( xoo_wl_emails()->emails[ $_GET['type'] ] ) ){
			$rows = xoo_wl_db()->get_waitlist_rows( array(
				'limit' => 1
			) );
			if( empty( $rows ) ){
				wp_die( 'Add at least one user to your waitlist to preview email' );
			}
		
			echo xoo_wl_emails()->emails[ $_GET['type'] ]->preview_email_template( $rows[0]->xoo_wl_id );

			die();
		}
	}


	public function enqueue_scripts($hook) {

		wp_enqueue_style( 'xoo-wl-admin-style', XOO_WL_URL . '/admin/assets/css/xoo-wl-admin-style.css', array(), XOO_WL_VERSION, 'all' );

		//Enqueue Styles only on plugin settings page
		if( xoo_wl_helper()->admin->is_settings_page() ){
		
			wp_enqueue_script( 'xoo-wl-admin-js', XOO_WL_URL . '/admin/assets/js/xoo-wl-admin-js.js', array( 'jquery' ), XOO_WL_VERSION, false );

			wp_localize_script('xoo-wl-admin-js','xoo_wl_admin_localize',array(
				'adminurl'  => admin_url().'admin-ajax.php',
			));


		}


		if( $hook === 'toplevel_page_xoo-wl-view-waitlist' || $hook === 'waitlist_page_xoo-wl-email-history' ){

			wp_enqueue_style( 'dataTables-css', XOO_WL_URL.'/admin/assets/css/datatables.css' );

			wp_enqueue_script( 'dataTables-js', XOO_WL_URL.'/admin/assets/js/datatables.js', array( 'jquery') );

			wp_enqueue_script( 'xoo-wl-admin-table-js', XOO_WL_URL . '/admin/assets/js/xoo-wl-admin-table-js.js', array( 'jquery'), XOO_WL_VERSION, false );

			wp_localize_script('xoo-wl-admin-table-js','xoo_wl_admin_table_localize',array(
				'adminurl'  => admin_url().'admin-ajax.php',
				'strings' 	=> array(
					'sending' 		=> 'Sending...Please wait...',
					'sent' 			=> 'Email sent successfully',
					'deleting'		=> 'Deleting...',
					'deleted' 		=> 'Deleted successfully',
					'processing' 	=> 'Processing...',
				),
				'nonce' => wp_create_nonce('xoo-wl-nonce'),
			));
		}

	}



	public function admin_fields_page(){
		xoo_wl()->aff->admin->display_page();
	}


	public function waitlist_list_view_html( $count ){

		$current_list = xoo_wl_admin_settings()->get_waitlist_view();
		$base_url     = admin_url( 'admin.php?page=xoo-wl-view-waitlist' );

		?>

		<div class="xoo-wl-users-stats">

			<a href="<?php echo esc_url( add_query_arg( 'list', 'waitlist', $base_url ) ); ?>" class="<?php echo $current_list === 'waitlist' ? 'xoo-wlview-active' : ''; ?>">

				<div class="xoo-wlusst-left">
					<div class="xoo-wl-icon"><?php include XOO_WL_PATH.'/admin/assets/icons/user.svg' ?></div>
					<label>Users</label>
				</div>
				<div class="xoo-wlusset-right">
					<span><?php echo $count['rowsCount'] ?></span>
				</div>
				
			</a>

			<a href="<?php echo esc_url( add_query_arg( 'list', 'products', $base_url ) ); ?>" class="<?php echo $current_list === 'products' ? 'xoo-wlview-active' : ''; ?>">

				<div class="xoo-wlusst-left">
					<div class="xoo-wl-icon"><?php include XOO_WL_PATH.'/admin/assets/icons/bag.svg' ?></div>
					<label>Products</label>
					
				</div>
				<div class="xoo-wlusset-right">
					<span><?php echo $count['productsCount'] ?></span>
				</div>
				
			</a>

		</div>
		<?php
	}

	public function get_export_import_html( $list_view = 'waitlist' ){

		$all_fields = (array) include XOO_WL_PATH.'/admin/views/export-fields.php';

		switch ($list_view) {
			case 'waitlist':
				$table_type = 'waitlist_table';
				break;

			case 'products':
				$table_type = 'products_table';
				break;
			
			case 'product_users':
				$table_type = 'users_table';
				break;
		}

		$export_fields = $all_fields[$table_type];

		if( defined('XOO_WLEXIM_VERSION') && version_compare(XOO_WLEXIM_VERSION, '1.3', '<') && $list_view === 'waitlist' ){
			return;
		}

		?>
		<div class="xoo-wl-exim-cont <?php echo !function_exists('xoo_wl_exim') ? 'xoo-wl-exim-no' : '' ?>">

			<?php

			$args = array(
				'fields' 		=> $export_fields,
				'table_type' 	=> $table_type
			);

			if( isset( $_GET['product'] ) ){
				$args['product_id'] = (int) $_GET['product'];
			}

			xoo_wl_helper()->get_template( "xoo-wl-export-form.php", $args, XOO_WL_PATH.'/admin/templates/' );

			xoo_wl_helper()->get_template( "xoo-wl-import-form.php", array(), XOO_WL_PATH.'/admin/templates/' );

			if( !function_exists('xoo_wl_exim') ){

				?>

				<div class="xoo-wl-exim-no-notice">
					Export and Import waitlist is a separate add-on. <a href="https://xootix.com/waitlist-for-woocommerce#sp-addons" target="__blank">BUY</a>
				</div>

				<?php

			}

			?>

		</div>
		<?php
	}


	public function get_waitlist_view(){
		if( !isset( $_GET['page'] ) || $_GET['page'] !== 'xoo-wl-view-waitlist' ) return;

		if( isset( $_GET['product'] ) ){
			return 'product_users';
		}
		else{
			$saved = get_option('xoo-wl-list-view');

			if( !$saved ){

				$saved = 'waitlist';
				update_option('xoo-wl-list-view', $saved );
			}
			return $saved;
		}
	}


	public function view_waitlist_page(){

		$args = array();

		$list_view = $this->get_waitlist_view();

		$this->cron_not_working_html();


		$this->get_export_import_html( $list_view );

		if( isset( $_GET['product'] ) ){
			xoo_wl_helper()->get_template( "xoo-wl-table-product-users.php", array(), XOO_WL_PATH.'/admin/templates/' );
		}
		else{

			if( $list_view === 'products' ){
				xoo_wl_helper()->get_template( "xoo-wl-table-products.php", array(), XOO_WL_PATH.'/admin/templates/' );
				
			}
			else{
				xoo_wl_helper()->get_template( "xoo-wl-table-users.php", array(), XOO_WL_PATH.'/admin/templates/' );
			}

		}


		return;

		
		
	}



	public function view_email_history_page(){

		$crons = xoo_wl_db()->get_cron_rows();

		$args = array(
			'crons' => $crons,
		);

		xoo_wl_helper()->get_template( "xoo-wl-table-email-history.php", $args, XOO_WL_PATH.'/admin/templates/' );

	}


	public function get_preview_template_form(){
		$link = '<a target="__blank" href="admin.php?page=waitlist-woocommerce-settings&preview=true&type=%1$s">%2$s</a>';
		?>
		<div class="xoo-wl-pv-email-cont">
			<span>Preview Email</span>
			<div class="xoo-pv-email-links">
				<?php printf( $link, 'backInStock', 'Back in Stock' ); ?>
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}


	

	public function cron_not_working_html(){

		if( xoo_wl()->is_cron_ok() ) return;

		?>

		<div class="xoo-wl-cron-failed">

			<a href="<?php echo esc_url( add_query_arg( 'xoo-wl-cron-test', 'yes' ) ) ?>">Test again</a>

			<div class="xoo-wl-cron-info">
				<span>We have detected issues with your WP Cron functionality & this plugin requires WP Cron to send emails.</span>
				<div>
					<i>What is WP Cron?</i>
					<span>WP Cron is a core wordpress feature which allows you to do tasks in the background. A lot of wordpress functionalities are dependent on this.</span>
				</div>
			</div>


			<?php if( get_option( 'xoo_wl_cron_working', true ) !== 'yes' ): ?>

				You can use a plugin to view the scheduled cron events in your WordPress installation. One popular plugin for this purpose is "WP Crontrol." Once you install this plugin, it will tell you the error and then you can further debug the issue.

			<?php endif; ?>

		</div>

		<?php

		

	}


	public function waitlist_table_product_column( $product, $args = array() ){

		if( !$product || !is_object( $product ) ) return;

		$args = wp_parse_args( $args, array(
			'info_html' => ''
		) );

		$product_id = $product->get_id();

		$product 	= wc_get_product( $product_id );

		$title 		= xoo_wl_get_product_name( $product );

		$image_id 	= $product->get_image_id();

		if ( !$image_id && $product->is_type( 'variation' ) ) {
		    $image_id = get_post_thumbnail_id( $product->get_parent_id() );
		}

		$thumb = $product->get_image();


		$edit_link 	= $product->is_type('variation') ? get_edit_post_link( $product->get_parent_id() ) : get_edit_post_link( $product_id );
		$permalink 	= $product->get_permalink();

		$stock_status 	= $product->get_stock_status(); 

	    $stock_label 	= wc_get_product_stock_status_options()[ $stock_status ];

		ob_start();

		?>

		<div class="xoo-wl-product-col">
			<div class="xoo-wl-prod-left">
				<?php echo wp_kses_post( $thumb ); ?>
			</div>

			<div class="xoo-wl-prod-right">
				<div class="xoo-wl-prodr-top">
					<span><?php echo $title ?></span>
					
				</div>

				<div class="xoo-wl-prorigh-links">
					<a href="<?php echo $edit_link ?>" target="_blank">Edit</a>
					<a href="<?php echo $permalink ?>" target="_blank">View</a>
					<div class="xoo-wl-prod-stockstatus xoo-wl-prod-stock-<?php echo esc_html( $stock_status ) ?>"><?php echo esc_html( $stock_label ); ?></div>
				</div>
				<?php echo $args['info_html']; ?>

			</div>

			
		</div>
		

		<?php


		return ob_get_clean();

		
	}

	public function waitlist_product_not_exist( $name = '' ){
		ob_start();
		?>
		<div class="xoo-wl-product-notavailb">
			<span><?php echo $name; ?></span>
			<span class="xoo-wl-prod-deleted">Product not found [deleted]</span>
		</div>
		<?php
		return ob_get_clean();
	}


	public function old_version_import_export_addon_compatibility($hook){
		if( defined('XOO_WLEXIM_VERSION') && version_compare(XOO_WLEXIM_VERSION, '1.4', '<') && isset( $_GET['page'] ) && $_GET['page'] === 'xoo-wl-view-waitlist' ){
			wp_enqueue_script( 'xoo-wlexim-admin-js', XOO_WLEXIM_URL.'/assets/xoo-wl-exim-admin-js.js', array( 'jquery'), XOO_WLEXIM_VERSION, false );
		}
	}


}

function xoo_wl_admin_settings(){
	return Xoo_Wl_Admin_Settings::get_instance();
}

xoo_wl_admin_settings();


?>