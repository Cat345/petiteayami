<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Form_Field_Number' ) ) {
	/**
	 * This class will control all Optin Number mapping functionality on optin submission.
	 * Class WFFN_Optin_Form_Field_Number
	 */
	class WFFN_Optin_Form_Field_Number extends WFFN_Optin_Form_Field {

		private static $ins = null;
		public static $slug = 'number';
		public $is_custom_field = true;

		public $index = 50;

		/**
		 * WFFN_Optin_Form_Field_Number constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Form_Field_Number|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Return title of this form field
		 */
		public function get_title() {
			return __( 'Number', 'funnel-builder' );
		}

		/**
		 * @param $field_data
		 *
		 * @return string|void
		 */
		public function get_field_output( $field_data ) {
			$field_data  = wp_parse_args( $field_data, $this->get_field_format() );
			$name        = $field_data['InputName'];
			$width       = isset( $field_data['width'] ) ? esc_attr( $field_data['width'] ) : '';
			$label       = isset( $field_data['label'] ) ? esc_attr( $field_data['label'] ) : '';
			$placeholder = isset( $field_data['placeholder'] ) ? esc_attr( $field_data['placeholder'] ) : '';
			$required    = isset( $field_data['required'] ) ? esc_attr( $field_data['required'] ) : false;
			$hash        = isset( $field_data['hash_key'] ) ? esc_attr( $field_data['hash_key'] ) : '';
			$value       = $this->get_default_value( $field_data );
			$class       = $this->get_input_class( $field_data );

			?>
			<div class="bwfac_form_sec bwfac_form_field_number <?php echo esc_attr( $width ); ?>">
				<?php if ( ! empty( $label ) ) { ?>
					<label for="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>"><?php echo esc_html( $label );
						echo ( $required ) ? '<span>*</span>' : ''; ?> </label>
				<?php } ?>
				<div class="wfop_input_cont">
					<input id="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( $class ) ?>" type="number" name="<?php echo esc_attr( $name ) ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
				</div>
			</div>
			<?php
		}

		/**
		 * @return array
		 */
		public function get_field_format() {
			return array(
				'width'       => 'wffn-sm-100',
				'type'        => $this::get_slug(),
				'label'       => __( 'Number', 'funnel-builder' ),
				'placeholder' => '',
				'required'    => true,
				'default'     => '',
			);
		}

	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->form_fields->register( WFFN_Optin_Form_Field_Number::get_instance() );
	}
}