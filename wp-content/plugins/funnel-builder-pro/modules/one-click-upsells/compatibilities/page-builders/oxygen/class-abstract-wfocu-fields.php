<?php
if ( ! class_exists( 'WFOCU_OXY_Field' ) ) {
	abstract class WFOCU_OXY_Field extends OxyEl {
		protected $media_settings = [];
		protected $name = '';
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
		static $css_build = false;

		public function name() {
			return $this->name;
		}

		public function __construct() {
			parent::__construct();
		}

		public function get_settings() {
			if ( ! $this->El instanceof OxygenElement ) {
				return [];
			}

			return $this->El->getParam( 'shortcode_options' );
		}

		public function init() {
			$this->El->useAJAXControls();
		}    /*
	  * used by OxyEl class to show the element button in a specific section/subsection
	  * @returns {string}
	  */
		public function button_place() {
			return 'woofunnels::woofunnels';
		}

		protected function add_tab( $title = '' ) {
			if ( empty( $title ) ) {
				$title = $this->get_title();
			}
			$field_key = 'wfocu_' . $this->add_tab_number . "_tab";
			$control   = $this->addControlSection( $field_key, $title, "assets/icon.png", $this );
			$this->add_tab_number ++;

			return $control;
		}

		public function add_heading( $control, $heading, $separator = '', $conditions = [] ) {
			$key            = $this->get_unique_id();
			$custom_control = $control->addCustomControl( __( '<div class="oxygen-option-default"  style="color: #fff; line-height: 1.3; font-size: 15px;font-weight: 900;    text-transform: uppercase;    text-decoration: underline;">' . $heading . '</div>' ), 'description' );
			$custom_control->setParam( $key, '' );
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
				if ( '' !== $condition_string ) {
					$custom_control->setCondition( $condition_string );
				}
			}

			return $custom_control;
		}

		public function add_sub_heading( $control, $heading, $separator = '', $conditions = [] ) {
			$key            = $this->get_unique_id();
			$custom_control = $control->addCustomControl( __( '<div class="oxygen-option-default"  style="color: #fff; line-height: 1.3; font-size: 13px;font-weight: 600;    text-transform: uppercase;    text-decoration: underline;">' . $heading . '</div>' ), 'description' );
			$custom_control->setParam( $key, '' );
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
				if ( '' !== $condition_string ) {
					$custom_control->setCondition( $condition_string );
				}
			}

			return $custom_control;
		}

		protected function add_switcher( $control, $key, $label = '', $default = 'off', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Enable', 'woofunnels-aero-checkout' );
			}
			$input = [
				"type"    => "radio",
				"name"    => $label,
				"slug"    => $key,
				"value"   => [ 'on' => __( "Yes" ), "off" => __( 'No' ) ],
				"default" => $default,
				"css"     => false,
			];


			$condition_string = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
			}


			if ( '' !== $condition_string ) {
				$input['condition'] = $condition_string;
			}
			$ctrl = $control->addOptionControl( $input );
			$ctrl->rebuildElementOnChange();
			$ctrl->whiteList();

			return $key;
		}

		protected function add_icon( $control, $key, $label = 'Icon', $default = '', $conditions = [], $selector = '' ) {

			$input = [
				'type'    => 'icon_finder',
				'name'    => $label,
				'slug'    => $key,
				'default' => $default
			];
			if ( ! empty( $selector ) ) {
				$input['selector'] = $selector;

			}
			$condition_string = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition_string ) {
				$input['condition'] = $condition_string;
			}
			$control->addOptionControl( $input )->rebuildElementOnChange();


			return $key;
		}

		protected function add_select( $control, $key, $label, $options, $default, $conditions = [] ) {

			$input            = [
				'type'    => 'dropdown',
				'name'    => $label,
				'slug'    => $key,
				'value'   => $options,
				'default' => $default
			];
			$condition_string = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition_string ) {
				$input['condition'] = $condition_string;
			}
			$control->addOptionControl( $input )->rebuildElementOnChange();


			return $key;
		}

		public function add_text( $control, $key, $label, $default = '', $conditions = [], $description = '', $placeholder = '' ) {

			$input = array(
				'name'        => $label,
				'slug'        => $key,
				'type'        => 'textfield',
				'default'     => $default,
				'placeholder' => $placeholder,
			);


			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$control->addOptionControl( $input )->rebuildElementOnChange();

			return $key;
		}

		protected function add_textArea( $control, $key, $label, $default = '', $conditions = [] ) {
			$input = array(
				'name'    => $label,
				'slug'    => $key,
				'type'    => 'textarea',
				'default' => $default
			);


			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$control->addOptionControl( $input )->rebuildElementOnChange();

			return $key;
		}

		protected function add_typography( $control, $key, $selectors = '', $label = '' ) {

			if ( empty( $label ) ) {
				$label = __( 'Typography', 'woofunnels-aero-checkout' );
			}
			$typo = $control->typographySection( $label, $selectors, $this );


			return $typo;
		}

		protected function add_font( $tab_id, $key, $selectors = '', $label = 'Color', $default = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = 'Font Family';
			}

			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"property" => 'font-family',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_color( $tab_id, $key, $selectors = '', $label = 'Color', $default = '#000000', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = 'Color';
			}

			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"property" => 'color',

			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_background_color( $tab_id, $key, $selectors = [], $default = '#000000', $label = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Background', 'woofunnels-upstroke-one-click-upsell' );
			}
			$input     = array(
				"name"     => $label,
				"selector" => $selectors,
				"slug"     => $key,
				'default'  => $default,
				"property" => 'background-color',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_border_color( $tab_id, $key, $selectors = [], $default = '#000000', $label = '', $box_shadow = false, $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Color', 'woofunnels-upstroke-one-click-upsell' );
			}

			$input     = array(
				"name"     => $label,
				"selector" => $selectors,
				"slug"     => $key,
				'default'  => $default,
				"property" => 'border-color',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );

			return $key;
		}

		public function custom_typography( $tab_id, $key, $selector, $label = '', $default = [], $tab_condition = [] ) {


			$font_family    = '';
			$font_size      = '';
			$font_weight    = '';
			$line_height    = '';
			$letter_spacing = '';
			$transform      = '';
			$decoration     = '';

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
			$this->add_font_family( $tab_id, $key . '_font_family', $selector, "", $font_family, $tab_condition );
			$this->add_font_size( $tab_id, $key . '_font_size', $selector, "", $font_size, $tab_condition );
			$this->add_font_weight( $tab_id, $key . '_font_weight', $selector, "", $font_weight, $tab_condition );
			$this->add_line_height( $tab_id, $key . '_line_height', $selector, "", $line_height, $tab_condition );
			$this->add_letter_spacing( $tab_id, $key . '_letter_spacing', $selector, "", $letter_spacing, $tab_condition );
			$this->add_text_transform( $tab_id, $key . '_transform', $selector, "", $transform, $tab_condition );
			$this->add_text_decoration( $tab_id, $key . '_decoration', $selector, "", $decoration, $tab_condition );
		}

		/* Typography Fields  Start*/

		protected function add_font_family( $tab_id, $key, $selectors = '', $label = 'Font Family', $default = 'Inherit', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Font Family', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = 'inherit';
			}


			$input     = array(
				"name"        => $label,
				"slug"        => $key,
				"selector"    => $selectors,
				"param_name"  => "font-family",
				"param_value" => $default,
				"default"     => $default,
				"property"    => 'font-family',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_font_size( $tab_id, $key, $selectors = '', $label = 'Color', $default = '', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Font Size', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = '16';
			}
			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'font-size',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_font_weight( $tab_id, $key, $selectors = '', $label = 'Font Weight', $default = 'noormal', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Font Weight', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = '400';
			}
			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'font-weight',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_line_height( $tab_id, $key, $selectors = '', $label = 'Line Height', $default = '1.5', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Line Height', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = '1.5';
			}
			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'line-height',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_text_alignments( $tab_id, $key, $selectors = '', $label = '', $default = 'left', $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Alignment', 'woofunnels-upstroke-one-click-upsell' );
			}


			if ( empty( $default ) ) {
				$default = 'left';
			}

			$items_align = $tab_id->addControl( "buttons-list", $key, $label );

			$items_align->setValue( array(
				"left"   => "Left",
				"center" => "Center",
				"right"  => "Right"
			) );
			$items_align->setDefaultValue( $default );
			$items_align->setValueCSS( array(
				"left"   => "
                $selectors{
                    text-align: left;
                }
            ",
				"center" => "
				$selectors{
                    text-align: center;
                }
            ",
				"right"  => "
               $selectors{
                    text-align: right;
                }
            ",
			) );
			$items_align->whiteList();
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition_string = $this->get_condition_string( $key, $conditions );
				if ( '' !== $condition_string ) {
					$items_align->setCondition( $condition_string );
				}
			}

			return $key;
		}

		protected function add_letter_spacing( $tab_id, $key, $selectors = '', $label = 'Letter Spacing', $default = '1', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Letter Spacing', 'woofunnels-upstroke-one-click-upsell' );
			}

			if ( empty( $default ) ) {
				$default = '0';
			}

			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'letter-spacing',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_text_transform( $tab_id, $key, $selectors = '', $label = 'Text Transform', $default = 'none', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Text Transform', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = 'none';
			}
			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'text-transform',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_text_decoration( $tab_id, $key, $selectors = '', $label = 'Text Decoration', $default = 'none', $conditions = [] ) {

			if ( empty( $label ) ) {
				$label = __( 'Text Decoration', 'woofunnels-upstroke-one-click-upsell' );
			}
			if ( empty( $default ) ) {
				$default = 'none';
			}
			$input     = array(
				"name"     => $label,
				"slug"     => $key,
				"selector" => $selectors,
				"default"  => $default,
				"property" => 'text-decoration',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		/* Typography Fields End*/

		protected function add_border_radius( $tab_id, $key, $selector, $conditions = [], $default = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Radius', 'woofunnels-upstroke-one-click-upsell' );
			}

			$input     = array(
				"name"     => $label,
				"selector" => $selector,
				"slug"     => $key,
				'default'  => $default,
				"property" => 'border-radius',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );

			return $key;
		}

		protected function add_padding( $tab_id, $key, $selector, $label = '' ) {
			if ( empty( $label ) ) {
				$label = esc_html__( 'Padding', 'woofunnels-upstroke-one-click-upsell' );
			}
			$tab_id->addPreset( "padding", $key, $label, $selector )->whiteList();

			return $key;
		}

		protected function add_margin( $tab_id, $key, $selector, $label = '' ) {
			if ( empty( $label ) ) {
				$label = esc_html__( 'Margin', 'woofunnels-upstroke-one-click-upsell' );
			}
			$tab_id->addPreset( "margin", $key, $label, $selector )->whiteList();

			return $key;
		}

		protected function add_border( $tab_id, $key, $selectors, $label = '' ) {
			if ( empty( $label ) ) {
				$label = __( "Border" );
			}
			$tab_id->borderSection( $label, $selectors, $this );

			return $key;
		}

		protected function add_only_border_radius( $tab_id, $key, $selector, $name = '' ) {
			if ( empty( $name ) ) {
				$name = __( "Border Radius" );
			}

			$borderRadiusPreset = $tab_id->addPreset( "border-radius", $key, __( $name . " Border Radius" ) );
			$borderRadiusPreset->whiteList();
			$borderSelector = $this->El->registerCSSSelector( $selector );

			$borderSelector->mapPreset( 'border-radius', $key );


			return $key;

		}

		protected function add_box_shadow( $tab_id, $key, $selector, $label = '' ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Border Shadow', 'woofunnels-upstroke-one-click-upsell' );
			}

			$tab_id->boxShadowSection( $label, $selector, $this );

			return $key;
		}

		protected function add_divider( $control, $type ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$key = $this->get_unique_id();
			$control->addCustomControl( __( '<hr class="oxygen-option-default" style="color: #fff" />' ), 'description' )->setParam( $key, '' );

			return $key;
		}

		protected function range( $tab_id, $key, $label = '', $selectors = '', $property = 'transition-duration', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = __( 'Transition Duration' );
			}

			$input     = array(
				"name"         => __( 'Transition Duration' ),
				"selector"     => $selectors,
				"slug"         => $key,
				"property"     => $property,
				"control_type" => 'slider-measurebox',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}

			$transition = $tab_id->addStyleControl( [ $input ] );

			$transition->setUnits( 's', 's' );
			$transition->setRange( 0, 1, 0.1 );

		}

		protected function add_width( $tab_id, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Width', 'woofunnels-upstroke-one-click-upsell' );
			}
			$input     = array(
				"name"     => $label,
				"selector" => $selectors,
				"slug"     => $key,
				'default'  => isset( $default['default'] ) ? $default['default'] : '',
				'unit'     => "%",
				"property" => 'width',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function slider_measure_box( $tab_id, $key, $selectors, $label, $default, $conditions = [], $property = "margin-bottom" ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Icon Font Size', 'woofunnels-upstroke-one-click-upsell' );
			}
			$input     = array(
				"name"         => $label,
				"selector"     => $selectors,
				"slug"         => $key,
				'default'      => $default,
				"property"     => $property,
				"control_type" => 'slider-measurebox',
				"unit"         => 'px',

			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_height( $tab_id, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Height', 'woofunnels-upstroke-one-click-upsell' );
			}
			$input     = array(
				"name"     => $label,
				"selector" => $selectors,
				"slug"     => $key,
				'default'  => $default['default'],
				"property" => 'height',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}
			$tab_id->addStyleControls( [ $input ] );


			return $key;
		}

		protected function add_min_width( $tab_id, $key, $selectors = '', $label = '', $default = [], $conditions = [] ) {
			if ( empty( $label ) ) {
				$label = esc_attr__( 'Min Width', 'woofunnels-upstroke-one-click-upsell' );
			}
			$input     = array(
				"name"     => $label,
				"selector" => $selectors,
				"slug"     => $key,
				'default'  => $default,
				"property" => 'min-width',
			);
			$condition = '';
			if ( is_array( $conditions ) && ! empty( $conditions ) ) {
				$condition = $this->get_condition_string( $key, $conditions );
			}
			if ( '' !== $condition ) {
				$input['condition'] = $condition;
			}

			$tab_id->addStyleControls( [ $input ] );

			return $key;
		}

		protected function get_class_options() {
			return [
				'wffn-sm-100' => __( 'Full', 'woofunnels-upstroke-one-click-upsell' ),
				'wffn-sm-50'  => __( 'One Half', 'woofunnels-upstroke-one-click-upsell' ),
				'wffn-sm-33'  => __( 'One Third', 'woofunnels-upstroke-one-click-upsell' ),
				'wffn-sm-67'  => __( 'Two Third', 'woofunnels-upstroke-one-click-upsell' ),
			];
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

		protected function get_local_slug() {
			return $this->get_local_slug;
		}

		public function controls() {
			$this->process_data();
		}

		private function process_data() {

			$run_setup = true;
			if ( isset( $_GET['ct_builder'] ) && isset( $_GET['oxy_wfocu_id'] ) && ! isset( $_GET['oxygen_iframe'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$run_setup = false;
			}
			if ( false === $run_setup ) {
				return [];
			}

			global $post;
			// checking for when builder is open

			if ( $this->is_oxy_page() ) {
				$wffn_post_id = 0;
				if ( isset( $_REQUEST['oxy_wfocu_id'] ) ) {//phpcs:ignore
					$wffn_post_id = $_REQUEST['oxy_wfocu_id'];//phpcs:ignore
				} else if ( isset( $_REQUEST['post_id'] ) && ! WFOCU_OXY::is_template_editor() ) {//phpcs:ignore
					$wffn_post_id = $_REQUEST['post_id'];//phpcs:ignore
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

			}

			if ( is_null( $post ) || ( ! is_null( $post ) && $post->post_type !== WFOCU_Common::get_offer_post_type_slug() ) ) {
				return [];
			}


			$this->setup_offer();
			$this->setup_data();
		}

		protected function setup_data() {
		}

		public function is_oxy_page() {

			$status = true;
			// At load
			if ( isset( $_REQUEST['ct_builder'] ) ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->is_oxy = true;
				$status       = true;

			}
			// when ajax running for form html
			if ( isset( $_REQUEST['action'] ) && ( 'set_oxygen_edit_post_lock_transient' === $_REQUEST['action'] || false !== strpos( $_REQUEST['action'], 'oxy_render_' ) || false !== strpos( $_REQUEST['action'], 'oxy_load_controls_oxy' ) ) ) { //phpcs:ignore
				$this->is_oxy = true;
				$status       = true;
			}


			return $status;
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
						self::$product_options[ $key ] = preg_replace( '/[\'"\\\\\/\n\r\t]/', '', $product->data->get_name() );
					}
				}

			}
		}

		public static function get_input_fields_sizes() {
			return [
				'6px'  => __( 'Small', 'woofunnels-upstroke-one-click-upsell' ),
				'9px'  => __( 'Medium', 'woofunnels-upstroke-one-click-upsell' ),
				'12px' => __( 'Large', 'woofunnels-upstroke-one-click-upsell' ),
				'15px' => __( 'Extra Large', 'woofunnels-upstroke-one-click-upsell' ),
			];
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

		function get_control_type_by_css_property( $css_property ) {

			switch ( $css_property ) {
				case 'color':
				case 'background-color':
				case 'border-color':
				case 'border-top-color':
				case 'border-bottom-color':
				case 'border-left-color':
				case 'border-right-color':

					return 'colorpicker';
					break;

				case 'font-size':
				case 'border-radius':

					return 'slider-measurebox';
					break;

				case 'letter-spacing':
				case 'height':
				case 'width':
				case 'max-width':
				case 'min-width':
				case 'margin-top':
				case 'margin-right':
				case 'text-align':
				case 'margin-bottom':
				case 'margin-left':
				case 'top':
				case 'right':
				case 'bottom':
				case 'left':
				case 'border-width':
				case 'border-top-width':
				case 'border-right-width':
				case 'border-bottom-width':
				case 'border-left-width':
				case 'padding-top':
				case 'padding-right':
				case 'padding-bottom':
				case 'padding-left':

					return 'measurebox';
					break;

				case 'opacity':

					return 'slider-measurebox';
					break;

				case 'text-transform':
				case 'text-decoration':
				case 'float':
				case 'display':
				case 'flex-wrap':
				case 'visibility':
				case 'align-items':

					return 'radio';
					break;

				case 'font-family':

					return 'font-family';
					break;

				case 'font-weight':

					return 'dropdown';
					break;

				default:
					return "textfield";
					break;
			}
		}

	}
}