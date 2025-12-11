<?php

namespace WfocuFunnelKit;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( '\WfocuFunnelKit\Reject_Link' ) ) {
	class Reject_Link extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-reject-offer-link';
		public $icon = 'wfocu-icon-link_no-01';

		/**
		 * Retrieves the label for the Reject Link element.
		 *
		 * @return string The label for the Reject Link element.
		 */
		public function get_label() {
			return esc_html__( 'Reject Link' );
		}

		/**
		 * Sets the control groups for the Reject Link element.
		 *
		 * This method initializes the control groups array for the Reject Link element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the set_common_control_groups() method to set the common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Reject Offer' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Reject Offer' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Set the controls for the Reject Link element.
		 *
		 * This method sets the controls for the Reject Link element, including the text for rejecting the offer.
		 *
		 * @return void
		 */
		public function set_controls() {
			$this->controls['text'] = array(
				'group'       => 'elementContent',
				'label'       => esc_html__( 'Reject Offer' ),
				'type'        => 'text',
				'default'     => esc_html__( 'No thanks, I don’t want to take advantage of this one-time offer >' ),
				'placeholder' => esc_html__( 'No thanks, I don’t want to take advantage of this one-time offer >' ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['typography'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Typography' ),
				'type'    => 'typography',
				'default' => array(
					'text-align' => 'center',
				),
				'css'     => array(
					array(
						'property' => 'typography',
						'selector' => 'a.bricks-wfocu-reject, .bricks-wfocu-reject',
					),
					array(
						'property' => 'display',
						'selector' => 'a.bricks-wfocu-reject, .bricks-wfocu-reject',
						'value'    => 'block',
					),
				),
			);

			$this->controls['backgroundColor'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Background Color' ),
				'type'    => 'color',
				'css'     => array(
					array(
						'property' => 'background-color',
						'selector' => 'a.bricks-wfocu-reject, .bricks-wfocu-reject',
					),
				),
				'default' => array(
					'hex' => 'transparent',
				),
			);
		}

		/**
		 * Renders the reject link element.
		 */
		public function render() {
			$this->set_attribute( 'upstroke-reject', 'href', 'javascript:void(0);' );
			$this->set_attribute( 'upstroke-reject', 'class', 'bricks-wfocu-reject bricks-wfocu-reject-link wfocu_skip_offer wfocu-skip-offer-link' );

			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <a <?php echo $this->render_attributes( 'upstroke-reject' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php $this->render_link_text(); ?>
                </a>
            </div>
			<?php
		}

		/**
		 * Renders the text for the reject link.
		 */
		public function render_link_text() {
			$settings = $this->settings;

			$text = isset( $settings['text'] ) ? $this->render_dynamic_data( $settings['text'] ) : null;

			if ( $text === null ) {
				return;
			}

			echo $text;//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}