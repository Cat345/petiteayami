<?php

namespace XooWL\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xoo_Helper{

	public $slug, $path, $helperArgs;
	public $admin;

	public $fw_url;

	public function __construct( $slug, $path, $helperArgs = array() ){

		$this->slug 		= $slug;
		$this->path 		= $path;
		$this->fw_url 		= untrailingslashit(plugin_dir_url( XOO_FW_DIR .'/'.basename( XOO_FW_DIR ) ) );
		$this->helperArgs 	= wp_parse_args( $helperArgs, array(
			'pluginFile' 	=> '',
			'pluginName' 	=> ''
		) );


		$this->includes(); 
		$this->hooks();
	}



	public function includes(){
		require_once __DIR__.'/admin/class-xoo-admin-settings.php';
		$this->admin = new Xoo_Admin( $this );
	}


	public function hooks(){
		\add_action( 'init', array( $this, 'internationalize' ) );
		\add_action( 'admin_init', array( $this, 'time_to_update_theme_templates_data' ) );
	}


	public function get_usage_data(){
		return array();
	}


	public function get_template( $template_name, $args = array(), $template_path = '', $return = false ){

		$located = $this->locate_template( $template_name, $template_path );

		$located = apply_filters( 'xoo_'.$this->slug.'_get_template', $located, $template_name, $args, $template_path );

	    if ( $args && is_array ( $args ) ) {
	        extract ( $args );
	    }

	    if ( $return ) {
	        ob_start ();
	    }


	    // include file located
	    if ( file_exists ( $located ) ) {
	        include ( $located );
	    }

	    if ( $return ) {
	        return ob_get_clean ();
	    }
	}

	public function locate_template( $template_name, $template_path ){

		$lookIn = array(
			'templates/'.$this->slug.'/'.$template_name,
			'templates/'.$this->slug.'/'.basename( $template_name ),
			$template_name,
		);

		 // Look within passed path within the theme - this is priority.
		$template = locate_template( $lookIn );

		//Check woocommerce directory for older version
		if( !$template && class_exists( 'woocommerce' ) ){
			if( file_exists( WC()->plugin_path() . '/templates/' . $template_name ) ){
				$template = WC()->plugin_path() . '/templates/' . $template_name;
			}
		}


	    if ( ! $template ) {
	    	if( $template_path ){
	    		$template = $template_path.'/'.$template_name;
	    		
	    	}
	    	else{
	    		$template = $this->path .'/templates/'. $template_name;
	    	}
	    }

	    return apply_filters( 'xoo_'.$this->slug.'_template_located', $template, $template_name, $template_path );
	}


	public function get_option( $key, $subkey = '' ){
		$option = get_option( $key );
		if( $subkey ){
			return isset( $option[ $subkey ] ) ? $option[ $subkey ] : '';
		}
		else{
			return $option;
		}
	}


	public function internationalize(){
        load_plugin_textdomain( $this->slug, FALSE, basename( $this->path) . '/languages/' ); // Plugin Languages
	}


	/**
	 * Retrieve metadata from a file. Based on WP Core's get_file_data function.
	 */
	public function get_file_version( $file ) {

		// Avoid notices if file does not exist.
		if ( ! file_exists( $file ) ) {
			return '';
		}

		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'r' ); // @codingStandardsIgnoreLine.

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 ); // @codingStandardsIgnoreLine.

		// PHP will close file handle, but we are good citizens.
		fclose( $fp ); // @codingStandardsIgnoreLine.

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );
		$version   = '';

		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( '@version', '/' ) . '(.*)$/mi', $file_data, $match ) && $match[1] ) {
			$version = _cleanup_header_comment( $match[1] );
		}

		return $version;
	}



	/**
	 * Look for theme templates
	 *
	 * @return array
	 */
	public function get_theme_templates( $scan_woocommerce = false ) {
		$override_data  = array();
		$template_paths = apply_filters( 'xoo_'.$this->slug.'_template_overrides_scan_paths', array( 'templates' => $this->path . '/templates/' ) );
		$scanned_files  = $theme_templates = array();

		foreach ( $template_paths as $lookInDir => $template_path ) {
			$scanned_files[ $lookInDir ] = $this->scan_template_files( $template_path );
		}

		foreach ( $scanned_files as $lookInDir => $files ) {
			foreach ( $files as $file ) {

				$basename = basename( $file );

				if ( file_exists( get_stylesheet_directory() . '/templates/' . $this->slug .'/'. $file ) ) {
					$theme_file = get_stylesheet_directory() . '/templates/' . $this->slug .'/'. $file;
				} elseif (  class_exists( 'woocommerce' ) && $scan_woocommerce && file_exists( get_template_directory() . '/' . WC()->template_path() . $file ) ) {
					$theme_file = get_template_directory() . '/' . WC()->template_path() . $file;
				} else {
					$theme_file = false;
				}


				if ( ! empty( $theme_file ) ) {
					$core_version  = $this->get_file_version( $template_paths[ $lookInDir ] .'/'. $file );
					$theme_version = $this->get_file_version( $theme_file );
					$theme_templates[] = array(
						'file' 			=> $theme_file,
						'name' 			=> str_replace( array( WP_CONTENT_DIR, '\\' ) , array( '', '/' ), $theme_file ),
						'theme_version' => $theme_version,
						'core_version' 	=> $core_version,
						'is_outdated' 	=> version_compare( $core_version , $theme_version, '>' ) ? 'yes' : 'no',
						'basename' 		=> $basename,
					);
				}
			}
		}

		return $theme_templates;
	}



	/**
	 * Scan the template files.
	 *
	 * @param  string $template_path Path to the template directory.
	 * @return array
	 */
	public function scan_template_files( $template_path ) {
		$files  = @scandir( $template_path ); // @codingStandardsIgnoreLine.
		$result = array();

		if ( ! empty( $files ) ) {

			foreach ( $files as $key => $value ) {

				if ( ! in_array( $value, array( '.', '..' ), true ) ) {

					if ( is_dir( $template_path . DIRECTORY_SEPARATOR . $value ) ) {
						$sub_files = $this->scan_template_files( $template_path . DIRECTORY_SEPARATOR . $value );
						foreach ( $sub_files as $sub_file ) {
							$result[] = $value . DIRECTORY_SEPARATOR . $sub_file;
						}
					} else {
						$result[] = $value;
					}
				}
			}
		}
		return $result;
	}



	public function get_outdated_section(){

		$odTempData = $this->get_theme_templates_data();
		ob_start();
		?>
		<div class="xoo-outdatedtemplates">
			<?php if( $odTempData['has_outdated'] === "yes" ): ?>
				<span>You're using outdated version of templates, please fetch a new copy from the plugin templates folder</span>
				<ul>
					<?php
					foreach ( $odTempData['templates'] as $template_data ){
						if( $template_data['is_outdated'] !== 'yes' ) continue;
						echo '<li><span class="dashicons dashicons-warning"></span>'. esc_html( $template_data['name'] ).'</li>';
					}
					?>
				</ul>
			<?php else: ?>
				<div>Templates Status
				<span class="dashicons dashicons-yes-alt" style="font-size: 14px;color: #008000;line-height: 1.3;"></span>
				<a href="https://docs.xootix.com/<?php echo esc_attr( $this->slug ); ?>" target="_blank">How to override?</a>
				</div>
			<?php endif; ?>
			<span>Last checked: <?php echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $odTempData['last_scanned'] ) ) ); ?></span>
			<a href="<?php echo esc_url( add_query_arg( array( 'scan_templates' => 'yes' , 'slug' => $this->slug ) ) ); ?>">Check again</a>
		</div>
		<?php
		return ob_get_clean();
	}


	public function get_theme_templates_data(){

		$data = (array) get_option( 'xoo_'.$this->slug.'_theme_templates_data' );
		if( empty( $data ) || !isset( $data['last_scanned'] ) ){
			return $this->update_theme_templates_data();
		}
		return $data;
	}


	public function time_to_update_theme_templates_data(){

		$tempData = $this->get_theme_templates_data();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if(  ( ( time() - $tempData['last_scanned'] ) > ( 86400 * 1 ) ) || ( isset( $_GET['scan_templates'] ) && isset( $_GET['slug'] ) && $_GET['slug'] === $this->slug ) ){
			$this->update_theme_templates_data();
			wp_safe_redirect( remove_query_arg( array( 'scan_templates', 'slug' ) ) );
			die();
		}
	}


	public function update_theme_templates_data(){

		$tempData = array();

		$theme_templates = (array) $this->get_theme_templates( true );

		$has_outdated = 'no';

		foreach ( $theme_templates as $template ) {
			if( $template['is_outdated'] === "yes" ){
				$has_outdated = "yes";
				break;
			}
		}

		$tempData['has_outdated'] 	= $has_outdated;
		$tempData['templates'] 		= $theme_templates;
		$tempData['last_scanned'] 	= time();

		update_option( 'xoo_'.$this->slug.'_theme_templates_data', $tempData );

		return $tempData;
	
	}

	public function box_shadow_desc($value){
		$html = '<a href="https://box-shadow.dev/" target="__blank">Preview & click on "Show code" -> copy value</a>';
		if( $value ){
			$html .= 'Default: '.$value;
		}
		return $html;
	}


	//array( $field_id => $_FILES[id] )
	public function upload_files_as_attachment( $fieldsHavingFiles ){

		$attachmentIDS = array();

		if( !empty( $fieldsHavingFiles ) ){

			// These files need to be included as dependencies when on the front end.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			foreach ( $fieldsHavingFiles as $field_id => $files ) {

				foreach ( $files as $file ) {

					$_FILES = array( $field_id => $file );

					// Let WordPress handle the upload.
					// Remember, 'wpcfu_file' is the name of our file input in our form above.
					$attachment_id = media_handle_upload( $field_id, 0 );

					if ( is_wp_error( $attachment_id ) ) {
						
						//delete previously attached files
						foreach ($attachmentIDS as $field_id => $ids) {
							foreach ($ids as $id) {
								wp_delete_attachment( $id );
							}	
						}

						return new \WP_Error( 'failed', 'Some files failed to upload'. ' - ' . $file['name'] . '('.$attachment_id->get_error_message().')' );
					} 
					else{
						$attachmentIDS[ $field_id ][] = $attachment_id;
					}
				}

			}

		}

		return $attachmentIDS;

	}


	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}


	public function parsePlaceHolders( $text, $placeholders = array() ){

		foreach ( $placeholders as $placeholder => $placeholder_value ) {
			$text = str_replace( $placeholder , $placeholder_value , $text );
		}

		return $text;
	}

	public function geolocate(){
		require_once XOO_FW_DIR.'/class-xoo-geolocation.php';
		return xoo_geolocate();
	}


	public function email_get_from_address( $from_address ){
		return $from_address;
	}

	public function email_get_from_name( $from_name ){
		return $from_name;
	}

	public function email_get_content_type( $content_type ){
		$content_type = 'text/html';
		return $content_type;
	}


	public function send_email( $identifier, $to, $subject, $message, $headers = array(), $attachments = array(), $locale = '' ) {

	    if ( ! $locale ) {
	        $locale = get_locale();
	    }

	    switch_to_locale( $locale );

	    try {

	        // Add filters inside try block so they're properly removed in finally
	        add_filter( 'wp_mail_from', array( $this, 'email_get_from_address' ) );
	        add_filter( 'wp_mail_from_name', array( $this, 'email_get_from_name' ) );
	        add_filter( 'wp_mail_content_type', array( $this, 'email_get_content_type' ) );

	        $message              = apply_filters( 'xoo_mail_' . $this->slug . '_' . $identifier . '_content', $message );
	        $mail_callback        = apply_filters( 'xoo_mail_' . $this->slug . '_' . $identifier . '_callback', 'wp_mail' );
	        $mail_callback_params = apply_filters(
	            'xoo_mail_' . $this->slug . '_' . $identifier . '_callback_params',
	            array( $to, wp_specialchars_decode( $subject ), $message, $headers, $attachments ),
	            $this
	        );

	        $return = $mail_callback( ...$mail_callback_params );

	    } finally {
	        // Always remove filters and restore locale
	        remove_filter( 'wp_mail_from', array( $this, 'email_get_from_address' ) );
	        remove_filter( 'wp_mail_from_name', array( $this, 'email_get_from_name' ) );
	        remove_filter( 'wp_mail_content_type', array( $this, 'email_get_content_type' ) );

	        restore_previous_locale();
	    }

	    do_action( 'xoo_mail_' . $this->slug . '_' . $identifier . '_sent', $return );

	    return $return;

	}


	public function get_border_options( $border ){

		$defaults = array(
	        'size'   => 0,
	        'color'  => 'transparent',
	        'style'  => 'none',
	        'radius' => 0,
	    );

	    $border = array_merge( $defaults, $border );

	    // Sanitize values
	    $border['size']   	= max( 0, (float) $border['size'] );
	    $border['radius'] 	= max( 0, (float) $border['radius'] );
	    $border['style']  	= strtolower( $border['style'] );
	   	$border['color']  	= trim( $border['color'] );

	   	return $border;
	}


	public function get_border_css_value( $border, $return = 'all' ){

		$border = $this->get_border_options( $border );

		extract($border);

    	$css = [];

	    // Border
	    if ( in_array( $return, [ 'border', 'all' ], true ) ) {
	        $css[] = "border: {$size}px {$style} {$color};";
	    }

	    // Radius
	    if ( in_array( $return, [ 'radius', 'all' ], true ) ) {
	        $css[] = "border-radius: {$radius}px;";
	    }

	    return implode( ' ', $css );

	    
	}


	public function get_button_values( $values = array() ){

		$values = xoo_recursive_parse_args(
			$values,
			array(
				'size_type' 	=> 'custom',
				'width'         => 100,
				'width_unit'    => '%',
				'height_unit'   => 'px',
				'height'        => 47,
				'bgColor'       => '#27374d',
				'txtColor'      => '#dde6ed',

				'padding_v' 	=> 10,
				'padding_h' 	=> 20,

				'margin_v' 		=> 10,
				'margin_h' 		=> 10,
				'position' 		=> 'center',

				'text' => array(
					'fontWeight' 		=> 500,
					'fontStyle' 		=> 'normal',
					'fontSize' 			=> 15,
					'fontSizeUnit' 		=> 'px',
					'textTransform' 	=> 'capitalize',
				),

				'border' => array(
					'size'      => 1,
					'color'     => '#dde6ed',
					'style'     => 'solid',
					'radius'    => 5,
				),

				'hover' => array(
					'bgColor'       => '#dde6ed',
					'txtColor'      => '#27374d',

					'border' => array(
						'size'      => 1,
						'color'     => '#27374d',
						'style'     => 'solid',
						'radius'    => 5,
					),
				),
			)
		);

		return $values;

	}

	public function get_button_css( $selectors, $settings ) {

		$settings = $this->get_button_values( $settings );

		$selectors = (array) $selectors;

		if ( empty( $selectors ) ) {
			return '';
		}

		$normal_selectors = implode( ',', $selectors );

		$hover_selectors = implode(
			',',
			array_map(
				static fn( $selector ) => $selector . ':hover',
				$selectors
			)
		);

		$is_auto = $settings['size_type'] === 'auto';

		$normal_css = array(
			'max-width'        => $is_auto ? 'none' : $settings['width'] . $settings['width_unit'],
			'width'            => $is_auto ? 'max-content' : '100%',
			'height'           => $is_auto ? 'auto' : $settings['height'] . $settings['height_unit'],
			'padding'          => $is_auto ? $settings['padding_v'] . 'px ' . $settings['padding_h'] . 'px' : '5px 10px',
			'margin'           => $settings['margin_v'] . 'px ' . $settings['margin_h'] . 'px',

			'background-color' => $settings['bgColor'],
			'color'            => $settings['txtColor'],

			'font-weight'      => $settings['text']['fontWeight'],
			'font-style'       => $settings['text']['fontStyle'],
			'font-size'        => $settings['text']['fontSize'] . $settings['text']['fontSizeUnit'],
			'text-transform'   => $settings['text']['textTransform'],

			'border-width'     => $settings['border']['size'] . 'px',
			'border-style'     => $settings['border']['style'],
			'border-color'     => $settings['border']['color'],
			'border-radius'    => $settings['border']['radius'] . 'px',
			'display' 			=> 'flex',
			'align-items' 		=> 'center',
			'justify-content' 	=> 'center'
		);

		if( $settings['position'] === 'center' ){
			$normal_css['margin-left'] = $normal_css['margin-right'] = 'auto'; 
		}
		elseif ( $settings['position'] === 'left' ){
			$normal_css['margin-right'] = 'auto';
		}
		elseif ( $settings['position'] === 'right' ){
			$normal_css['margin-left'] = 'auto';
		}

		$hover_css = array(
			'background-color' => $settings['hover']['bgColor'],
			'color'            => $settings['hover']['txtColor'],

			'border-width'     => $settings['hover']['border']['size'] . 'px',
			'border-style'     => $settings['hover']['border']['style'],
			'border-color'     => $settings['hover']['border']['color'],
			'border-radius'    => $settings['hover']['border']['radius'] . 'px',
		);

		$css = $normal_selectors . '{';

		foreach ( $normal_css as $property => $value ) {
			$css .= $property . ':' . $value . ';';
		}

		$css .= '}';

		$css .= $hover_selectors . '{';

		foreach ( $hover_css as $property => $value ) {
			$css .= $property . ':' . $value . ';';
		}

		$css .= '}';

		return $css;
	}


	public function print_button_themed_css( $selectors_map, $settings, $themes ) {

		if( empty( $themes ) ) return;

		$theme_selectors = array();

		foreach ( $selectors_map as $option_key => $selector ) {

			if ( empty( $settings[ $option_key ] ) ) {
				continue;
			}

			$theme_id = $settings[ $option_key ];

			if ( empty( $themes[ $theme_id ] ) ) {
				continue;
			}

			$theme_selectors[ $theme_id ][] = $selector;

		}

		foreach ( $theme_selectors as $theme_id => $selectors ) {

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_button_css(
				$selectors,
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$themes[ $theme_id ]
			);

		}

	}


}



?>