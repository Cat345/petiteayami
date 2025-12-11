<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WFFN_Optin_Form_Field_Checkbox' ) ) {
	/**
	 * This class will control all Optin Text mapping functionality on optin submission.
	 * Class WFFN_Optin_Form_Field_Text
	 */
	#[AllowDynamicProperties]
	class WFFN_Optin_Form_Field_Checkbox extends WFFN_Optin_Form_Field {

		private static $ins = null;
		public static $slug = 'checkbox';
		public $is_custom_field = true;
		public $index = 600;

		/**
		 * WFFN_Optin_Form_Field_Text constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Form_Field_Checkbox|null
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
			return __( 'Checkbox', 'funnel-builder-powerpack' );
		}


		/**
		 * @param $field_data
		 *
		 * @return string|void
		 */
		public function get_field_output( $field_data ) {
			$field_data = wp_parse_args( $field_data, $this->get_field_format() );

			$name     = isset( $field_data['InputName'] ) ? esc_attr( $field_data['InputName'] ) : '';
			$width    = isset( $field_data['width'] ) ? esc_attr( $field_data['width'] ) : '';
			$label    = isset( $field_data['label'] ) ? esc_attr( $field_data['label'] ) : '';
			$hash     = isset( $field_data['hash_key'] ) ? esc_attr( $field_data['hash_key'] ) : '';
			$required = isset( $field_data['required'] ) ? esc_attr( $field_data['required'] ) : false;
			$value    = $this->get_default_value( $field_data );
			$class    = $this->get_input_class( $field_data );

			?>
            <div class="bwfac_form_sec bwfac_form_field_radio <?php echo esc_attr( $width ); ?>">
                <div class="wfop_input_cont">
                    <input type="checkbox" <?php esc_attr( checked( $value, 'checked', true ) ); ?> class="<?php echo esc_attr( $class ); ?>" id="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>" name="<?php echo esc_attr( $name ); ?>"/>
                    <label for="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>"><?php echo html_entity_decode( $label );//phpcs:ignore ?><?php echo ( $required ) ? '<span>*</span>' : ''; ?></label>
                </div>
            </div>
			<?php
		}

		public function get_default_value( $field_data ) {
			return ( empty( $field_data['default'] ) ) ? $field_data['default'] : 'checked';
		}

		/**
		 * @return array
		 */
		public function get_field_format() {
			return array(
				'width'    => '100',
				'type'     => $this::get_slug(),
				'label'    => __( 'Checkbox', 'funnel-builder-powerpack' ),
				'required' => false,
				'default'  => 'checked',
			);
		}

		public function get_field_editor_html( $mode = 'new' ) {
			?>
            <div class="wfop_<?php echo esc_attr( $mode ); ?>_fields_wrap" data-type="<?php echo esc_attr( $this::get_slug() ); ?>">
                <div class="wffn_row_billing">
                    <div class="wffn_billing_left">
                        <label><?php esc_html_e( 'Label', 'funnel-builder-powerpack' ); ?></label>
                    </div>
                    <div class="wffn_billing_right">
                        <input type="text" onkeyup="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ) ?>',this.value, 'label','<# print(data.index); #>')" value="<# print(data.field.label); #>" class="form-control wffn_label">
                    </div>
                </div>
                <div class="wffn_row_billing">
                    <div class="wffn_billing_left">
                        <label for=""><?php esc_html_e( 'Required', 'funnel-builder-powerpack' ); ?></label>
                    </div>
                    <div class="wffn_billing_right">
                        <input onchange="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ) ?>',this.checked, 'required','<# print(data.index); #>')" type="checkbox" <#
                        print(data.curr.isChecked(data.field.required)); #> class="form-control wffn_required">
                    </div>
                </div>
                <div class="wffn_row_billing">
                    <div class="wffn_billing_left">
                        <label for=""><?php esc_html_e( 'Default', 'funnel-builder-powerpack' ); ?></label>
                    </div>
                    <div class="wffn_billing_right">
                        <select onchange="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ) ?>',this.value, 'default','<# print(data.index); #>')">
                            <option
                            <# print(data.curr.isSelected(data.field.required,'checked')); #> value='checked'><?php echo esc_html_e( 'Checked', 'funnel-builder-powerpack' ) ?></option>
                            <option
                            <# print(data.curr.isSelected(data.field.required,'unchecked')); #> value='unchecked' ><?php echo esc_html_e( 'Un-Checked', 'funnel-builder-powerpack' ) ?></option>
                        </select>
                    </div>
                </div>
                <div class="wffn_row_billing">
                    <div class="wffn_billing_left">
                        <label><?php esc_html_e( 'Width', 'funnel-builder-powerpack' ); ?></label>
                    </div>
                    <div class="wffn_billing_right">
                        <select onchange="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ); ?>',this.value, 'width','<# print(data.index); #>')">
                            <option
                            <# print(data.curr.isSelected(data.field.width,'wffn-sm-100')); #> value='wffn-sm-100'>100%</option>
                            <option
                            <# print(data.curr.isSelected(data.field.width,'wffn-sm-50')); #> value='wffn-sm-50'>50%</option>
                            <option
                            <# print(data.curr.isSelected(data.field.width,'wffn-sm-33')); #> value='wffn-sm-33'>33%</option>
                        </select>
                    </div>
                </div>
            </div>
			<?php
		}

		public function get_sanitized_value( $data, $field ) {
			return isset( $data[ $field['InputName'] ] ) ? 'yes' : 'no';
		}

	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->form_fields->register( WFFN_Optin_Form_Field_Checkbox::get_instance() );
	}

}
