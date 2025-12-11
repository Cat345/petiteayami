<?php
if ( ! class_exists( 'WFOCU_Guten_Field' ) ) {
	abstract class WFOCU_Guten_Field {
		protected $media_settings = [];
		protected $name = '';
		protected $ajax = false;
		public $slug = '';
		protected $id = '';
		protected $settings = [];
		protected $post_id = 0;
		protected $tabs = [];
		protected $sub_tabs = [];
		protected $html_fields = [];
		private $add_tab_number = 1;
		protected static $product_options = [];
		protected $style_box = null;
		protected $props = [];
		protected $modules_fields = [];
		protected $style_selector = [];
		static $css_build = false;

		public function name() {
			return $this->name;
		}

		public function description() {
			return 'dummy';
		}

		public function widget_icon() {
			return 'feedback';
		}


		public function __construct() {
			$this->setup_widget();


			add_shortcode( "{$this->slug}_gutenberg", [ $this, 'shortcode' ] );
			if ( true === $this->ajax ) {
				add_action( 'wp_ajax_' . $this->slug . '_gutenberg', [ $this, 'render_ajax' ] );

			}
			add_action( 'wp_ajax_' . $this->slug . '_save_block_gutenberg', [ $this, 'save_block_data' ] );
		}

		public function render_ajax() {

			//$this->props = $_REQUEST;
			$request_body = file_get_contents( 'php://input' );
			$this->props  = json_decode( $request_body, true );
			echo $this->html( $this->props );
			exit;
		}

		public function save_block_data() {
			//$this->props = $_REQUEST;
			$request_body = file_get_contents( 'php://input' );

			$post_id = $_REQUEST['bwf_post_id'];
			if ( empty( $request_body ) ) {
				wp_send_json( [ 'status' => 'failed' ] );
			}
			$input = json_decode( $request_body, true );
			if ( ! is_array( $input ) || empty( $input ) ) {
				wp_send_json( [ 'status' => 'failed' ] );
			}
			$block_id = $input['attributes']['widget_block_id'];
			update_post_meta( $post_id, $block_id . '_data', $input );

			wp_send_json( [ 'status' => 'success' ] );

		}

		public function shortcode( $attributes ) {
			global $post;
			$id         = $attributes['id'];
			$block_data = get_post_meta( $post->ID, $id . '_data', true );

			if ( empty( $block_data ) || ! is_array( $block_data ) ) {
				return '';
			}
			ob_start();
			echo "<style>{$block_data['css']}</style>";
			?>
            <div id="bwf_block-<?php echo $block_data['attributes']['widget_block_id'] ?>" class="bwf_widget_container">
				<?php $this->html( $block_data['attributes'] ); ?>
            </div>
			<?php
			$html = ob_get_clean();

			return $html;
		}

		public function get_settings() {

		}

		public function init() {


		}

		public function get_tabs() {
			return $this->tabs;
		}

		public function get_section_fields() {
			$tabs    = $this->tabs;
			$section = array_filter( $tabs, function ( $arr ) {
				return ( $arr['control_type'] == 1 );
			} );
			$section = array_values( $section );

			return $section;
		}

		public function get_style_fields() {
			$tabs    = $this->tabs;
			$section = array_filter( $tabs, function ( $arr ) {
				return ( $arr['control_type'] == 2 );
			} );
			$section = array_values( $section );

			return $section;
		}

		public function get_style_selectors() {
			return $this->style_selector;
		}

		public function get_advanced_fields() {
			$tabs    = $this->tabs;
			$section = array_filter( $tabs, function ( $arr ) {
				return ( $arr['control_type'] == 3 );
			} );
			$section = array_values( $section );

			return $section;
		}

		public function get_attributes() {
			$tabs = $this->tabs;

			if ( empty( $tabs ) ) {
				return new stdClass();
			}
			$attributes = [];
			foreach ( $tabs as $tab ) {
				if ( empty( $tab['fields'] ) ) {
					continue;
				}
				$fields = $tab['fields'];
				foreach ( $fields as $field ) {

					if ( in_array( $field['type'], [ 'heading', 'sub_heading' ] ) ) {
						continue;
					}
					$key                = $field['slug'];
					$attributes[ $key ] = [ 'type' => $field['type'], 'default' => $field['default'] ];
				}

			}

			$attributes['widget_block_id'] = [ 'type' => 'text', 'default' => '' ];


			return $attributes;

		}


		protected function add_tab( $title = 'Test', $type = 1, $condition = [] ) {

			$key                = $this->get_unique_id();
			$this->tabs[ $key ] = array(
				'label'        => $title,
				'type'         => 'tab',
				'slug'         => $key,
				'control_type' => $type,
				'className'    => 'wfacp_heading_divi_builder',
				'fields'       => [],
				'conditions'   => $condition
			);

			return $key;
		}

		public function add_heading( $tab_key, $heading, $separator = '', $conditions = [] ) {
			$key = $this->get_unique_id();

			if ( isset( $this->tabs[ $tab_key ] ) ) {
				$this->tabs[ $tab_key ]['fields'][] = [
					'key'        => $key,
					'label'      => $heading,
					'type'       => 'heading',
					'className'  => '',
					'conditions' => $conditions,
					'default'    => ''
				];
			}


			return $key;
		}

		public function add_sub_heading( $control, $heading, $conditions = [] ) {
			$key = $this->get_unique_id();

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'key'        => $key,
					'label'      => $heading,
					'type'       => 'sub_heading',
					'className'  => '',
					'conditions' => $conditions,
					'default'    => ''
				];
			}


			return $key;
		}

		protected function add_switcher( $control, $key, $label = '', $default = true, $conditions = [] ) {


			if ( empty( $label ) ) {
				$label = __( 'Enable', 'woofunnels-aero-checkout' );
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'key'        => $key,
					'label'      => $label,
					'slug'       => $key,
					'type'       => 'switch',
					"options"    => [ 'on' => __( "Yes" ), "off" => __( 'No' ) ],
					'conditions' => $conditions,
					'default'    => $default
				];
			}

			return $key;
		}

		protected function add_icon( $control, $key, $label = 'Icon', $default = '', $conditions = [], $selector = '' ) {

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'label'      => $label,
					'type'       => 'icon_finder',
					'slug'       => $key,
					'conditions' => $conditions,
					'default'    => ''
				];
			}

			return $key;
		}

		protected function add_select( $control, $key, $label, $options, $default, $conditions = [] ) {


			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'select',
					'label'      => $label,
					'slug'       => $key,
					'options'    => $options,
					'default'    => $default,
					'conditions' => $conditions
				];
			}

			return $key;
		}

		public function add_text( $control, $key, $label, $default = '', $conditions = [], $description = '', $placeholder = '' ) {

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'        => 'textfield',
					'label'       => $label,
					'slug'        => $key,
					'default'     => $default,
					'conditions'  => $conditions,
					'placeholder' => $placeholder,
				];
			}

			return $key;
		}

		protected function add_textArea( $control, $key, $label, $default = '', $conditions = [] ) {

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'textarea',
					'label'      => $label,
					'slug'       => $key,
					'default'    => $default,
					'conditions' => $conditions,
				];
			}

			return $key;
		}

		protected function add_typography( $control, $key, $selectors = '', $label = '', $default = [], $condition = [] ) {
			$color = '';
			if ( isset( $default['color'] ) ) {
				$color = $default['color'];
			}
			$this->add_color( $control, $key . '_color', $selectors, '', $color, $condition );
			$this->custom_typography( $control, $key, $selectors, $label, $default, $condition );

			//$this->set_selector( $key, $selectors, 'font_family', $default );
			return $key;
		}

		protected function add_font( $control, $key, $selectors = '', $label = 'Color', $default = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = 'Font Family';
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'fonts',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'font_family', $default );

			return $key;
		}

		protected function add_color( $control, $key, $selectors = '', $label = 'Color', $default = '#000000', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = 'Color';
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'color',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}

			$this->set_selector( $key, $selectors, 'color', $default );

			return $key;
		}

		protected function add_background_color( $control, $key, $selectors = [], $default = '#000000', $label = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Background', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'background',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'background', $default );

			return $key;
		}

		protected function add_border_color( $control, $key, $selectors = [], $default = '#000000', $label = '', $box_shadow = false, $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Color', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'border_color',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'border_color', $default );

			return $key;
		}

		public function custom_typography( $tab_id, $key, $selector, $label = '', $default = [], $tab_condition = [] ) {


			$font_family    = '';
			$font_size      = '';
			$font_weight    = '';
			$line_height    = '';
			$letter_spacing = '0';
			$transform      = '';
			$decoration     = '';
			$text_align     = is_rtl() ? 'right' : 'left';

			if ( is_array( $default ) && count( $default ) > 0 ) {

				if ( isset( $default['font_family'] ) && ! empty( $default['font_family'] ) ) {
					$font_family = $default['font_family'];
				}

				if ( isset( $default['font_size'] ) && ! empty( $default['font_size'] ) ) {
					$font_size = $default['font_size'];
				}

				if ( isset( $default['font_weight'] ) && ! empty( $default['font_weight'] ) ) {
					$font_weight = $default['font_weight'];
				}

				if ( isset( $default['line_height'] ) && ! empty( $default['line_height'] ) ) {
					$line_height = $default['line_height'];
				}
				if ( isset( $default['text_align'] ) && ! empty( $default['text_align'] ) ) {
					$text_align = $default['text_align'];
				}


				if ( isset( $default['letter_spacing'] ) && ! empty( $default['letter_spacing'] ) ) {
					$letter_spacing = $default['letter_spacing'];
				}

				if ( isset( $default['transform'] ) && ! empty( $default['transform'] ) ) {
					$transform = $default['transform'];
				}

				if ( isset( $default['decoration'] ) && ! empty( $default['decoration'] ) ) {
					$decoration = $default['decoration'];
				}

			}
			if ( ! empty( $label ) ) {
				$this->add_heading( $tab_id, $label, '', $tab_condition );
			}
			$this->add_font_family( $tab_id, $key . '_font_family', $selector, "", $font_family, $tab_condition );
			$this->add_font_size( $tab_id, $key . '_font_size', $selector, "", $font_size, $tab_condition );
			$this->add_font_weight( $tab_id, $key . '_font_weight', $selector, "", $font_weight, $tab_condition );
			$this->add_text_alignments( $tab_id, $key . '_text_align', $selector, "", $text_align, $tab_condition );
			$this->add_line_height( $tab_id, $key . '_line_height', $selector, "", $line_height, $tab_condition );
			$this->add_letter_spacing( $tab_id, $key . '_letter_spacing', $selector, "", $letter_spacing, $tab_condition );
			$this->add_text_transform( $tab_id, $key . '_transform', $selector, "", $transform, $tab_condition );
			$this->add_text_decoration( $tab_id, $key . '_decoration', $selector, "", $decoration, $tab_condition );
		}

		/* Typography Fields  Start*/

		protected function add_font_family( $control, $key, $selectors = '', $label = 'Font Family', $default = 'Inherit', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Font Family', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = 'inherit';
			}


			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'font_family',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}

			$this->set_selector( $key, $selectors, 'font_family', $default );

			return $key;
		}

		protected function add_font_size( $control, $key, $selectors = '', $label = 'Color', $default = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Font Size', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = '16';
			}
			if ( empty( $default ) ) {
				$default = 'inherit';
			}


			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'font_size',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'font_size', $default, 'px' );

			return $key;
		}

		protected function add_font_weight( $control, $key, $selectors = '', $label = 'Font Weight', $default = 'noormal', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Font Weight', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = '400';
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'font_weight',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}

			$this->set_selector( $key, $selectors, 'font_weight', $default );

			return $key;
		}

		protected function add_line_height( $control, $key, $selectors = '', $label = 'Line Height', $default = '1.5', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Line Height', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = '1.5';
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'line_height',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'line_height', $default );

			return $key;
		}

		protected function add_text_alignments( $control, $key, $selectors = '', $label = '', $default = 'left', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' );
			}


			if ( empty( $default ) ) {
				$default = 'left';
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'text_align',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'text_align', $default );

			return $key;
		}

		protected function add_letter_spacing( $control, $key, $selectors = '', $label = 'Letter Spacing', $default = '1', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Letter Spacing', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = '0';
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'letter_spacing',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}

			$this->set_selector( $key, $selectors, 'letter_spacing', $default, 'px' );

			return $key;
		}

		protected function add_text_transform( $control, $key, $selectors = '', $label = 'Text Transform', $default = 'none', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Text Transform', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = 'none';
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'text_transform',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'text_transform', $default );

			return $key;
		}

		protected function add_text_decoration( $control, $key, $selectors = '', $label = 'Text Decoration', $default = 'none', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Text Decoration', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = 'none';
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'text_decoration',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'text_decoration', $default );

			return $key;
		}

		/* Typography Fields End*/

		protected function add_border_radius( $control, $key, $selector, $conditions = [], $default = 0 ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Radius', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'border_radius',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selector,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selector, 'border_radius', $default, '%' );

			return $key;
		}

		protected function add_padding( $control, $key, $selector, $label = '', $default = [], $condition = [] ) {
			if ( empty( $label ) ) {
				$label = esc_html__( 'Padding', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = [
					'top'    => '0',
					'right'  => '0',
					'left'   => '0',
					'bottom' => '0'
				];
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'padding',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selector,
					'conditions' => $condition
				];
			}
			$this->set_selector( $key, $selector, 'padding', $default, 'px' );

			return $key;
		}

		protected function add_margin( $control, $key, $selector, $label = '', $default = [], $condition = [] ) {
			if ( empty( $label ) ) {
				$label = esc_html__( 'Margin', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = [
					'top'    => '0',
					'right'  => '0',
					'left'   => '0',
					'bottom' => '0'
				];
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'margin',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selector,
					'conditions' => $condition
				];
			}

			$this->set_selector( $key, $selector, 'margin', $default, 'px' );

			return $key;
		}


		protected function add_border( $control, $key, $selector, $label = '', $default = [], $condition = [] ) {
			if ( empty( $label ) ) {
				$label = __( "Border" );
			}
			$default = [
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
				'color'  => '#FFFFFF',
				'style'  => 'none',
				'radius' => 0,
			];
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'border',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selector,
					'conditions' => $condition
				];
			}
			$this->set_selector( $key, $selector, 'border', $default, 'px' );

			return $key;
		}


		protected function add_box_shadow( $control, $key, $selector, $label = '', $default = [], $condition = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Shadow', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = [
					'h_offset' => '0',
					'v_offset' => '0',
					'blur'     => '0',
					'spread'   => '0',
					'color'    => 'transparent',
					'inset'    => '0',
				];
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'box_shadow',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selector,
					'conditions' => $condition
				];
			}
			$this->set_selector( $key, $selector, 'box_shadow', $default, 'px' );

			return $key;
		}


		protected function add_width( $control, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Width', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'width',
					'unit'       => "%",
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'width', $default, 'px' );

			return $key;
		}

		protected function slider_measure_box( $control, $key, $selectors = '', $label = '', $default = [], $conditions = [], $property = "margin-bottom" ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Icon Font Size', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'range',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'property'   => $property,
					'conditions' => $conditions
				];
			}

			//$this->set_selector( $key, $selectors, 'width', $default );

			return $key;
		}

		protected function add_height( $control, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Height', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'height',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'height', $default, 'px' );

			return $key;
		}

		protected function add_min_width( $control, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Min Width', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( isset( $this->tabs[ $control ] ) ) {
				$this->tabs[ $control ]['fields'][] = [
					'type'       => 'min_width',
					'label'      => $label,
					'slug'       => $key,
					'default'    => '',
					'selector'   => $selectors,
					'conditions' => $conditions
				];
			}
			$this->set_selector( $key, $selectors, 'min_width', $default, 'px' );

			return $key;
		}

		protected function set_selector( $key, $selector, $type = '', $value = '', $unit = '' ) {
			if ( empty( $selector ) ) {
				return;
			}
			$selector = is_array( $selector ) ? implode( ',', $selector ) : $selector;
			$css_data = $this->create_css_property( $type, $value );
			if ( empty( $css_data ) ) {
				return;
			}

			$property       = $css_data['property'];
			$property_value = $css_data['value'];

			if ( ! isset( $this->style_selector[ $selector ] ) ) {
				$this->style_selector[ $selector ] = [];
			}
			$this->style_selector[ $selector ][] = [ 'key' => $key, 'property' => $property, 'value' => $property_value, 'unit' => $unit ];

		}

		protected function create_css_property( $type, $default = '' ) {
			$property = [];
			switch ( $type ) {
				case  'text_align':
					$default  = ! empty( $default ) ? $default : ( is_rtl() ? 'right' : 'left' );
					$property = [ 'property' => 'text-align', 'value' => $default ];
					break;
				case  'letter_spacing':
					$property = [ 'property' => 'letter-spacing', 'value' => $default ];
					break;
				case  'line_height':
					$property = [ 'property' => 'line-height', 'value' => $default ];
					break;
				case  'width':
					$property = [ 'property' => 'width', 'value' => $default ];
					break;
				case  'min_width':
					$property = [ 'property' => 'min-width', 'value' => $default ];
					break;
				case  'margin':
					$property = [ 'property' => 'margin', 'value' => $default ];
					break;
				case  'padding':
					$property = [ 'property' => 'padding', 'value' => $default ];
					break;
				case  'border_radius':
					$property = [ 'property' => 'border-radius', 'value' => $default ];
					break;
				case  'border_color':
					$property = [ 'property' => 'border-color', 'value' => $default ];
					break;
				case  'border':
					$property = [ 'property' => 'border', 'value' => $default ];
					break;
				case  'background_color':
				case  'background':
					$property = [ 'property' => 'background-color', 'value' => $default ];
					break;
				case  'color':
					$property = [ 'property' => 'color', 'value' => $default ];
					break;
				case  'font_size':
					$property = [ 'property' => 'font-size', 'value' => $default ];
					break;
				case  'font_family':
					$property = [ 'property' => 'font-family', 'value' => $default ];
					break;

				case  'text_transform':
					$property = [ 'property' => 'text-transform', 'value' => $default ];
					break;
				case  'text_decoration':
					$property = [ 'property' => 'text-decoration', 'value' => $default ];
					break;
				case  'font_weight':
					$property = [ 'property' => 'font-weight', 'value' => $default ];
					break;

				case  'box_shadow':
					$property = [ 'property' => 'box-shadow', 'value' => '' ];
					break;
				default:
					break;


			}


			return $property;
		}


		protected function get_condition_string( $key, $condition ) {

			if ( empty( $condition ) ) {
				return '';
			}

			$output = [];
			foreach ( $condition as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ',', $value );
				}
				$output[] = $key . '=' . $value;
			}

			return implode( '&&', $output );
		}

		protected function get_unique_id() {
			static $count = 0;
			$count ++;
			$key = md5( 'wfocu_' . $count );

			return $key;
		}

		protected function get_name() {
			return $this->name;
		}

		protected function get_slug() {
			return $this->slug;
		}

		protected function get_id() {
			return $this->id;
		}


		private function setup_widget() {

			global $post;

			// checking for when builder is open


			$wffn_post_id = 0;
			if ( isset( $_REQUEST['post'] ) ) {//phpcs:ignore
				$wffn_post_id = $_REQUEST['post'];//phpcs:ignore
			} else if ( isset( $_REQUEST['post_id'] ) ) {//phpcs:ignore
				$wffn_post_id = $_REQUEST['post_id'];//phpcs:ignore
			} else if ( isset( $_REQUEST['bwf_post_id'] ) && wp_doing_ajax() ) {//phpcs:ignore
				$wffn_post_id = $_REQUEST['bwf_post_id'];//phpcs:ignore
			}


			if ( $wffn_post_id > 0 ) {
				$post = get_post( $wffn_post_id );//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				if ( ! is_null( $post ) && $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {

					WFOCU_Core()->public->is_preview = true;
					add_filter( 'wfocu_valid_state_for_data_setup', '__return_true' );
					WFOCU_Core()->template_loader->offer_id = $post->ID;
					WFOCU_Core()->template_loader->maybe_setup_offer();
				}
			}


			if ( is_null( $post ) || ( ! is_null( $post ) && $post->post_type !== WFOCU_Common::get_offer_post_type_slug() ) ) {
				return [];
			}


			$this->setup_offer();
			$this->setup_data();
		}

		protected function setup_data() {
		}

		function get_gallery_images( $product_data ) {

			$images_taken = array();
			$thumbnails   = [];

			/**
			 * @var WC_Product $product_obj
			 */
			$product_obj = $product_data->data;
			$product     = $product_data;

			if ( $product_obj instanceof WC_Product ) {
				$main_img    = $product_obj->get_image_id();
				$gallery_img = $product_obj->get_gallery_image_ids();

				if ( ! empty( $main_img ) ) {
					$images_taken[] = wp_get_attachment_image_src( $main_img, 'large' );
					$thumbnails[]   = wp_get_attachment_image_src( $main_img );
				}

				if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 ) {
					foreach ( $gallery_img as $gallerys ) {
						$images_taken[] = wp_get_attachment_image_src( $gallerys, 'large' );
						$thumbnails[]   = wp_get_attachment_image_src( $gallerys );
					}
				}
				/**
				 * Variation images to be bunch with the other gallery images
				 */
				if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) ) {
					foreach ( $product->variations_data['images'] as $id ) {
						$image_link = wp_get_attachment_image_src( $id, 'large' );
						if ( false === in_array( $image_link, $images_taken, true ) ) {
							$images_taken[] = wp_get_attachment_image_src( $id, 'large' );
							$thumbnails[]   = wp_get_attachment_image_src( $id );
						}
					}
				}

			}

			return [ "large" => $images_taken, "thumbnails" => $thumbnails ];
		}

		private function setup_offer() {
			if ( empty( self::$product_options ) ) {
				self::$product_options = array( '0' => __( '--No Product--', 'woofunnels-upstroke-one-click-upsell' ) );;

				if ( is_null( WFOCU_Core()->template_loader->product_data ) ) {
					return;
				}
				$products = WFOCU_Core()->template_loader->product_data->products;

				if ( ! empty( $products ) ) {
					self::$product_options = [];
					foreach ( $products as $key => $product ) {
						$product_key = WFOCU_Common::default_selected_product_key( $key );
						$product_key = ( $product_key !== false ) ? $product_key : '';

						$regular_price     = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' );
						$sale_price        = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' );
						$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
						$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );
						if ( round( $sale_price_raw, 2 ) === round( $regular_price_raw, 2 ) ) {
							$regular_price = false;
						}
						$product_type = $product->data->get_type();
						$is_subscription = in_array($product_type, ['subscription', 'subscription_variation', 'variable-subscription']);
						$signup_fee = '';
						$recurring_total = '';
						
						if ($is_subscription) {
							$signup_fee = WFOCU_Common::maybe_parse_merge_tags('{{product_signup_fee key="' . $product_key . '" signup_label=" " }}');
							$recurring_total = WFOCU_Common::maybe_parse_merge_tags('{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label=" " }}');
						}
						
						self::$product_options[ $key ] = [
							'value'               => $key,
							'label'               => $product->data->get_name(),
							'images'              => $this->get_gallery_images( $product ),
							'product_id'          => $product->data->get_id(),
							'product_description' => $product->data->get_short_description(),
							'is_variation'        => $product->data->is_type( 'variable' ) ? true : false,
							'offer_price'         => $sale_price,
							'regular_price'       => $regular_price,
							'type'        		  => $product_type,
							'is_subscription'     => $is_subscription,
							'signup_fee'          => $signup_fee,
							'recurring_total'     => $recurring_total,
						];
					}
				}

			}
		}


		function defaultCSS() {
			if ( self::$css_build === true ) {
				return;
			}
			self::$css_build = true;

			return file_get_contents( WFOCU_BUILDER_DIR . '/oxygen/css/wfocu-oxygen.css' );
		}

		function slug() {
			return $this->slug;
		}

		public static function get_product_lists() {
			return self::$product_options;
		}


	}
}