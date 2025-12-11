<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Form_Field_Radio' ) ) {
	/**
	 * This class will control all Optin Text mapping functionality on optin submission.
	 * Class WFFN_Optin_Form_Field_Text
	 */
	class WFFN_Optin_Form_Field_Radio extends WFFN_Optin_Form_Field {

		private static $ins = null;
		public static $slug = 'radio';
		public $is_custom_field = true;
		public $index = 70;

		/**
		 * WFFN_Optin_Form_Field_Text constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Form_Field_Radio|null
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
			return __( 'Radios', 'funnel-builder-powerpack' );
		}


		/**
		 * @param $field_data
		 *
		 * @return string|void
		 */
		public function get_field_output( $field_data ) {
			$field_data = wp_parse_args( $field_data, $this->get_field_format() );
			$options    = explode( ',', $field_data['options'] );
			$options    = array_combine( $options, $options );
			$width      = isset( $field_data['width'] ) ? $field_data['width'] : '';
			$label      = isset( $field_data['label'] ) ? esc_attr( $field_data['label'] ) : '';
			$required   = isset( $field_data['required'] ) ? esc_attr( $field_data['required'] ) : false;
			$display    = isset( $field_data['radio_alignment'] ) ? trim( $field_data['radio_alignment'] ) : 'horizontal';
			$hash       = isset( $field_data['hash_key'] ) ? esc_attr( $field_data['hash_key'] ) : '';
			?>
            <div class="bwfac_form_sec bwfac_form_field_radio <?php echo esc_attr( $width ); ?>" data-style="<?php echo esc_attr( $display ); ?>">
				<?php if ( ! empty( $label ) ) { ?>
                    <label>
						<?php
						echo esc_html( $label );
						echo ( $required ) ? '<span>*</span>' : '';
						?>
                    </label>
				<?php } ?>
				<?php foreach ( $options as $key => $value ) {
					$id = 'wfop_id_' . $field_data['InputName'] . '-' . $key . '_' . $hash;
					?>
                    <input <?php esc_attr( checked( trim( $this->get_default_value( $field_data ) ), trim( $value ), true ) ); ?> class="<?php echo esc_attr( $this->get_input_class( $field_data ) ); ?>" name="<?php echo esc_attr( $field_data['InputName'] ); ?>" type="radio" value="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $id ); ?>">
                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_attr( $value ); ?></label>
					<?php
				} ?>
            </div>
			<?php
		}

		/**
		 * @return array
		 */
		public function get_field_format() {
			return array(
				'type'     => $this::get_slug(),
				'label'    => __( 'Radio', 'funnel-builder-powerpack' ),
				'required' => true,
				'options'  => '',
				'default'  => '',
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
                        <input onkeyup="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ) ?>',this.value, 'default','<# print(data.index); #>')" type="text" value="<# print(data.field.default); #>" class="form-control">
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
                <div class="wffn_row_billing">
                    <div class="wffn_billing_left">
                        <label for=""><?php esc_html_e( 'Options', 'funnel-builder-powerpack' ); ?></label>
                    </div>
                    <div class="wffn_billing_right">
                        <textarea placeholder="<?php echo esc_attr__( 'Enter options comma separated. Example: apple,grapes', 'funnel-builder-powerpack' ); ?>" onkeyup="window.wfop_design.fieldEdited('<?php echo esc_attr( $mode ); ?>',this.value, 'options','<# print(data.index); #>')"><# print(data.field.options); #></textarea>
                    </div>
                </div>
            </div>
			<?php
		}
	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->form_fields->register( WFFN_Optin_Form_Field_Radio::get_instance() );
	}
}