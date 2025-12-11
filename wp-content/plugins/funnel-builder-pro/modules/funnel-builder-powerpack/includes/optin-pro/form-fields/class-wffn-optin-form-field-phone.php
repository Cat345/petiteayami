<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Form_Field_Phone' ) ) {
	/**
	 * This class will control all Optin Phone mapping functionality on optin submission.
	 * Class WFFN_Optin_Form_Field_Phone
	 */
	#[AllowDynamicProperties]
	class WFFN_Optin_Form_Field_Phone extends WFFN_Optin_Form_Field {

		private static $ins = null;
		public static $slug = WFFN_Optin_Pages::WFOP_PHONE_FIELD_SLUG;
		public $index = 30;

		/**
		 * WFFN_Optin_Form_Field_Phone constructor.
		 */
		public function __construct() {
			add_filter( 'wfacp_default_values', [ $this, 'pre_populate_from_get_parameter' ], 10, 2 );
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Form_Field_Phone|null
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
		 * Load custom scripts js file
		 * @return string|void
		 */
		public function load_scripts() {
			if ( false === $this->maybe_show_flag() ) {
				return '';
			}
			wp_enqueue_script( 'phone_flag_intl', dirname( plugin_dir_url( __FILE__ ) ) . '/assets/phone/js/intltelinput.min.js', array(), WFFN_VERSION_DEV );

		}

		/**
		 * Load custom style css file
		 * @return string|void
		 */
		public function load_style() {
			if ( false === $this->maybe_show_flag() ) {
				return '';
			}
			wp_enqueue_style( 'flag_style', dirname( plugin_dir_url( __FILE__ ) ) . '/assets/phone/css/phone-flag.css', array(), WFFN_VERSION_DEV );
		}

		/**
		 * Return title of this form field
		 */
		public function get_title() {
			return __( 'Phone Number', 'funnel-builder-powerpack' );
		}

		/**
		 * @param $field_data
		 *
		 * @return string|void
		 */
		public function get_field_output( $field_data ) {
			$field_data = wp_parse_args( $field_data, $this->get_field_format() );

			$name        = $this->get_prefix() . $this::get_slug();
			$width       = isset( $field_data['width'] ) ? esc_attr( $field_data['width'] ) : '';
			$label       = isset( $field_data['label'] ) ? esc_attr( $field_data['label'] ) : '';
			$placeholder = isset( $field_data['placeholder'] ) ? esc_attr( $field_data['placeholder'] ) : '';
			$required    = isset( $field_data['required'] ) ? esc_attr( $field_data['required'] ) : false;
			$validation  = isset( $field_data['phone_validation'] ) ? esc_attr( $field_data['phone_validation'] ) : false;
			$hash        = isset( $field_data['hash_key'] ) ? esc_attr( $field_data['hash_key'] ) : '';
			$value       = $this->get_default_value( $field_data );
			$class       = $this->get_input_class( $field_data );

			if ( false === $this->maybe_show_flag() ) {
				$class .= ' wfop_hide_flag';
			}

			if ( $validation ) {
				wp_enqueue_script( 'wfop_phone_utils', dirname( plugin_dir_url( __FILE__ ) ) . '/assets/phone/js/utils.js', array(), WFFN_VERSION_DEV );
			}

			?>

            <div class="bwfac_form_sec bwfac_form_field_phone <?php echo esc_attr( $width ); ?>">
				<?php if ( ! empty( $label ) ) { ?>
                    <label for="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>"><?php echo esc_html( $label );
						echo ( $required ) ? '<span>*</span>' : ''; ?> </label>
				<?php } ?>
                <div class="wfop_input_cont phone_flag_code <?php echo ( $validation ) ? 'wfop_phone_validation' : '' ?>">
                    <input id="wfop_id_<?php echo esc_attr( $name ) . '_' . esc_attr( $hash ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( $class ) ?>" type="tel" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
                    <input type="hidden" name="<?php echo esc_attr( $name ) . '_dialcode'; ?>"/>
                    <input type="hidden" name="<?php echo esc_attr( $name ) . '_countrycode'; ?>"/>
                </div>
            </div>
			<?php
		}

		public function get_default_value( $field_data ) {
			if ( ! empty( $field_data['default'] ) ) {
				return do_shortcode( $field_data['default'] );
			}

			return '';
		}

		/**
		 * @param $value
		 * @param $key
		 * @param $field
		 * Default add phone number in checkout form
		 *
		 * @return mixed|void
		 */
		public function pre_populate_from_get_parameter( $value, $key ) {

			if ( empty( $key ) ) {
				return $value;
			}
			if ( 'billing_phone' === $key || 'shipping_phone' === $key ) {
				$optin_tags = BWF_Optin_Tags::get_instance();
				$phone      = $optin_tags->get_phone( array( 'default' ) );

				return empty( $phone ) ? $value : $phone;
			}

			return $value;
		}

		/**
		 * @return array
		 */
		public function get_field_format() {
			return array(
				'width'       => 'wffn-sm-100',
				'type'        => $this::get_slug(),
				'label'       => __( 'Phone Number', 'funnel-builder-powerpack' ),
				'placeholder' => '',
				'required'    => true,
				'InputName'   => $this->get_prefix() . $this::get_slug(),
				'default'     => '',
			);
		}

		public function get_sanitized_value( $data, $field ) {
			if ( is_array( $data ) && isset( $data['wfop_optin_phone'] ) && isset( $data['wfop_optin_phone_dialcode'] ) ) {
				$data['wfop_optin_phone'] = ! empty( $data['wfop_optin_phone'] ) ? str_replace( $data['wfop_optin_phone_dialcode'], '', $data['wfop_optin_phone'] ) : $data['wfop_optin_phone'];
			}

			return ( isset( $data[ $field['InputName'] ] ) && ! empty( $data[ $field['InputName'] ] ) && isset( $data[ $field['InputName'] . '_dialcode' ] ) ) ? wffn_clean( $data[ $field['InputName'] . '_dialcode' ] ) . '' . wffn_clean( $data[ $field['InputName'] ] ) : '';
		}

		/**
		 * show hide flag in optin form phone field
		 */
		public function maybe_show_flag() {
			return apply_filters( 'wfop_show_flag_in_phone_field', true );
		}


	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->form_fields->register( WFFN_Optin_Form_Field_Phone::get_instance() );
	}
}