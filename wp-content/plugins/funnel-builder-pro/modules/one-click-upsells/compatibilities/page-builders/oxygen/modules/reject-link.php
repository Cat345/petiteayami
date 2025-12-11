<?php
if ( ! class_exists( 'WFOCU_Oxy_Reject_Link' ) ) {
	class WFOCU_Oxy_Reject_Link extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_reject_link';
		protected $id = 'wfocu_reject_link';

		public function __construct() {
			$this->name = __( 'WF Reject Link', 'woofunnels-upstroke-one-click-upsell' );
			parent::__construct();
		}

		public function setup_data() {
			$this->text_settings();
			$this->color_settings();
			$this->typography_settings();
			$this->spacing_setting();
			$this->border_setting();

		}

		public function text_settings() {
			$tab_id = $this->add_tab( __( 'Text', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_text( $tab_id, 'text', __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ), __( 'No thanks, I don’t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' ) );

		}

		public function color_settings() {
			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_sub_heading( $tab_id, __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_text_color', '.wfocu-button-wrapper .wfocu-reject', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#777777' );
			$this->add_background_color( $tab_id, $this->slug . '_background_color', '.wfocu-button-wrapper .wfocu-reject', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_sub_heading( $tab_id, __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_hover_color', '.wfocu-button-wrapper .wfocu-reject:hover', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#777777' );
			$this->add_background_color( $tab_id, $this->slug . '_bg_hover_color', '.wfocu-button-wrapper .wfocu-reject:hover', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}

		public function typography_settings() {

			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Title Typography' ) );
			$default = [
				'font_size' => '16',
			];

			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu-button-wrapper .wfocu-reject', '', 'center' );
			$this->custom_typography( $tab_id, $this->slug . '_typography', '.wfocu-button-wrapper .wfocu-reject', '', $default );

		}


		private function spacing_setting() {
			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, $this->slug . '_text_margin', '.wfocu-button-wrapper .wfocu-reject' );
			$this->add_padding( $tab_id, $this->slug . '_text_padding', '.wfocu-button-wrapper .wfocu-reject' );
		}

		public function border_setting() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu-button-wrapper .wfocu-reject' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu-button-wrapper .wfocu-reject' );
		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$text = isset( $settings['text'] ) ? $settings['text'] : '';
			?>
            <div class="wfocu-button-wrapper">
                <a class="wfocu-reject wfocu_skip_offer" href="javascript:void(0);">
					<?php echo wp_kses_post( $text ); ?>

                </a>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
		      body .wfocu-button-wrapper .wfocu-reject {
		      	display: block;
                font-size: 16px;
                text-decoration: underline;
                line-height: 1.5;
                border-style: none;
                border-radius: 0px;
                box-shadow: none;
            }
            .oxy-wfocu-reject-link, .oxy-wfocu-reject-link .wfocu-button-wrapper{
            	width:100%;
            }

            body .wfocu-button-wrapper a:not(.wfocu-accept-button-link):not(.wfocu-wfocu-reject) {
                color: #777777;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Reject_Link;
}