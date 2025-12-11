<?php
if ( ! class_exists( 'WFOCU_Customizer_Common' ) ) {

	/**
	 * Abstract Class for all the Template Loading
	 * Class WFOCU_Customizer_Common
	 */
	class WFOCU_Customizer_Common extends WFOCU_Template_Common {

		public $data = null;
		public $internal_css = array();
		public $customizer_data = array();
		public $fields = array();
		public $products_data = array();
		public $countdown_timer = '';

		public function __construct() {
			parent::__construct();
		}

		public function get_view() {

			extract( array( 'data' => $this->data ) ); //@codingStandardsIgnoreLine

			do_action( 'wfocu_before_template_load' );
			include $this->get_template_url();  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			do_action( 'wfocu_after_template_load' );
			exit;
		}

		public function get_template_url() {
			return $this->template_dir . '/views/view.php';
		}

		public function get_slug() {
			return $this->template_slug;
		}

		public function get_fields() {
			return $this->fields;
		}

		public function control_filter( $control ) {
			if ( in_array( $control->section, $this->get_sections(), true ) ) {
				return true;
			}

			return false;
		}

		public function get_section( $wp_customize = false ) {
			/** WFOCUKirki is required to customizer */
			if ( ! class_exists( 'WFOCUKirki' ) ) {
				return;
			}

			if ( false !== $wp_customize ) {
				if ( is_array( $this->customizer_data ) && count( $this->customizer_data ) > 0 ) {
					foreach ( $this->customizer_data as $panel_single ) {
						foreach ( $panel_single as $panel_key => $panel_arr ) {
							/** Panel */
							$maybe_panel = true;
							if ( isset( $panel_arr['panel'] ) && 'no' === $panel_arr['panel'] ) {
								/** No need to register panel */
								$maybe_panel = false;
							} else {
								$arr = $panel_arr['data'];
								$arr = array_merge( $arr, array(
									'capability'     => 'edit_theme_options',
									'theme_supports' => '',
								) );
								$wp_customize->add_panel( $panel_key, $arr );
							}

							/** Section */
							if ( is_array( $panel_arr['sections'] ) && count( $panel_arr['sections'] ) > 0 ) {
								foreach ( $panel_arr['sections'] as $section_key => $section_arr ) {
									$section_key_final = $panel_key . '_' . $section_key;

									$this->sections[] = $section_key_final;

									$arr = $section_arr['data'];
									if ( true === $maybe_panel ) {
										$arr = array_merge( $arr, array(
											'panel' => $panel_key,
										) );
									}

									$wp_customize->add_section( $section_key_final, $arr );

									/** Fields - will add using wfocukirki */

									/** Set the selective part */
									if ( is_array( $section_arr['fields'] ) && count( $section_arr['fields'] ) > 0 ) {
										foreach ( $section_arr['fields'] as $field_key => $field_data ) {
											$field_key_final = $section_key_final . '_' . $field_key;
											$field_key_final = WFOCU_Core()->template_loader->customizer_key_prefix . '[' . $field_key_final . ']';

											/** Checking if wfocu_partial class exist */
											if ( isset( $field_data['wfocu_partial'] ) && is_array( $field_data['wfocu_partial'] ) && isset( $field_data['wfocu_partial']['elem'] ) ) {
												$callback = isset( $field_data['wfocu_partial']['callback'] ) ? $field_data['wfocu_partial']['callback'] : 'render_callback';
												$wp_customize->selective_refresh->add_partial( $field_key_final, array(
													'selector'        => $field_data['wfocu_partial']['elem'],
													'render_callback' => array( $this, $callback ),
													'primary_setting' => $field_key_final,
												) );
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		public function build_product_data( $offer_data = false ) {
			$data = $this->offer_data;

			if ( false !== $offer_data ) {
				$build_data = WFOCU_Core()->offers->build_offer_product( $offer_data );
			} else {
				if ( false !== $this->offer_products_meta ) {
					$build_data = $this->offer_products_meta;
				} else {
					$build_data    = WFOCU_Core()->offers->build_offer_product( $data );
					$product_count = (array) $build_data->products;
					if ( ! empty( $product_count ) && count( $product_count ) > 0 ) {
						$this->offer_products_meta = $build_data;
					}
				}
			}

			return $build_data;
		}

		public function set_changeset( $changeset = array() ) {
			$this->change_set = $changeset;
		}

		public function get_changeset( $key ) {
			if ( ! empty( $this->change_set ) && $this->change_set[ $key ] ) {
				return $this->change_set[ $key ];
			}

			return '';
		}

		public function render_callback( $data ) {

			$partial_key_base = $data->id_data();
			if ( is_array( $partial_key_base ) && isset( $partial_key_base['keys'] ) ) {
				$partial_key         = $partial_key_base['keys'][0];

				switch ( $partial_key ) {
					case 'wfocu_header_top_logo':
						$logo = WFOCU_Common::get_option( $partial_key );
						$no_logo_img = WFOCU_PLUGIN_URL . '/admin/assets/img/no_logo.jpg';
						?>
                        <img src="<?php echo $logo ? esc_url( $logo ) : esc_url( $no_logo_img ); ?>" alt="<?php bloginfo( 'name' ); ?>" title="<?php bloginfo( 'name' ); ?>"/>
						<?php
						$logo_img_html = ob_get_clean();

						return $logo_img_html;
						break;
					default:
						$expoded_value = explode( '_', $partial_key );

						/**
						 * Product Image Partial refresh code
						 * For multi product-grid template we need to check if Field ID matches with the `wfocu_product_product_{$key}_image`
						 */
						if ( is_array( $expoded_value ) && ! empty( $expoded_value ) && isset( $expoded_value[0] ) && $expoded_value[0] === 'wfocu' && isset( $expoded_value[1] ) && $expoded_value[1] === 'product' && isset( $expoded_value[2] ) && $expoded_value[2] === 'product' && isset( $expoded_value[4] ) && $expoded_value[4] === 'image' ) {
							$image_product = WFOCU_Common::get_option( $partial_key );

							$product_img = array();
							if ( isset( $image_product ) && (int) $image_product > 0 ) {
								$full_img      = wp_get_attachment_image_src( $image_product, 'large' );
								$product_img[] = array(
									'id'  => $image_product,
									'src' => $full_img[0],
								);
							} else {
								$product_img[] = array(
									'id'  => 0,
									'src' => wc_placeholder_img_src(),
								);
							}

							ob_start();
							?>
                            <img data-id="<?php echo esc_attr( $product_img[0]['id'] ); ?>" src="<?php echo esc_url( $product_img[0]['src'] ); ?>" title=""/>

							<?php
							return ob_get_clean();
						}
						$value = WFOCU_Common::get_option( $partial_key );
						if ( ! empty( $value ) ) {
							$value = nl2br( $value );
						}

						return $value;
						break;
				}
			}

		}

		public function assign_key_to_array( $array, $key ) {
			if ( ! is_array( $array ) ) {
				$array = array();
			}
			if ( ! isset( $array[ $key ] ) ) {
				$array[ $key ] = array();
			}

			return $array;
		}

		public function load_hooks() {

		}

	}
}