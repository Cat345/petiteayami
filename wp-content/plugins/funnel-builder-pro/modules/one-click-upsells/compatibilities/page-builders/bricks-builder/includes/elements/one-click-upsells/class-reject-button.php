<?php

namespace WfocuFunnelKit;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( '\WfocuFunnelKit\Reject_Button' ) ) {

	class Reject_Button extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-offer-reject-button';
		public $icon = 'wfocu-icon-button_no';

		/**
		 * Retrieves the label for the Reject Button element.
		 *
		 * @return string The label for the Reject Button element.
		 */
		public function get_label() {
			return esc_html__( 'Reject Button' );
		}

		/**
		 * Sets the control groups for the Reject Button element.
		 *
		 * This method initializes the control groups array for the Reject Button element.
		 * It sets the control groups for the button content and button style.
		 * It also calls the set_common_control_groups() method to set the common control groups.
		 * Finally, it removes the '_typography' control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['buttonContent'] = array(
				'title' => esc_html__( 'Reject Offer' ),
				'tab'   => 'content',
			);

			$this->control_groups['buttonStyle'] = array(
				'title' => esc_html__( 'Reject Offer' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		public function set_controls() {
			$this->controls['text'] = array(
				'group'       => 'buttonContent',
				'label'       => esc_html__( 'Title' ),
				'type'        => 'text',
				'default'     => esc_html__( 'No thanks, I don’t want to take advantage of this one-time offer >' ),
				'placeholder' => esc_html__( 'Reject Offer' ),
			);

			$this->controls['size'] = array(
				'group'       => 'buttonContent',
				'label'       => esc_html__( 'Size' ),
				'type'        => 'select',
				'options'     => $this->control_options['buttonSizes'],
				'inline'      => true,
				'reset'       => true,
				'placeholder' => esc_html__( 'Default' ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['align'] = array(
				'group'   => 'buttonContent',
				'label'   => esc_html__( 'Alignment' ),
				'type'    => 'align-items',
				'default' => 'stretch',
				'css'     => array(
					array(
						'selector' => '.bricks-button',
						'property' => 'align-self',
					)
				),
			);

			$this->controls['icon'] = array(
				'group' => 'buttonContent',
				'label' => esc_html__( 'Icon' ),
				'type'  => 'icon',
			);

			$this->controls['iconAlign'] = array(
				'group'    => 'buttonContent',
				'label'    => esc_html__( 'Icon Position' ),
				'type'     => 'select',
				'inline'   => true,
				'options'  => array(
					'left'  => esc_html__( 'Before' ),
					'right' => esc_html__( 'After' ),
				),
				'default'  => 'left',
				'required' => array( 'icon', '!=', '' ),
			);

			$this->controls['iconIndent'] = array(
				'group'    => 'buttonContent',
				'label'    => esc_html__( 'Icon Spacing' ),
				'type'     => 'slider',
				'css'      => array(
					array(
						'property' => 'margin-left',
						'selector' => '.bricks-button-icon-align-right',
					),
					array(
						'property' => 'margin-right',
						'selector' => '.bricks-button-icon-align-left',
					),
				),
				'units'    => array(
					'px' => array(
						'max' => 50,
					),
				),
				'default'  => '16px',
				'required' => array( 'icon', '!=', '' ),
			);

			$this->controls['typography'] = array(
				'group'   => 'buttonStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Typography' ),
				'type'    => 'typography',
				'default' => array(
					'color' => array(
						'hex' => '#ffffff',
					),
				),
				'css'     => array(
					array(
						'property' => 'font',
						'selector' => '.bricks-button',
					),
				),
			);

			$this->controls['iconTypography'] = array(
				'group'    => 'buttonStyle',
				'tab'      => 'style',
				'label'    => esc_html__( 'Icon Typography' ),
				'type'     => 'typography',
				'default'  => array(
					'color' => array(
						'hex' => '#ffffff',
					),
				),
				'css'      => array(
					array(
						'property' => 'font',
						'selector' => 'i',
					),
				),
				'required' => array( 'icon.icon', '!=', '' ),
			);

			$this->controls['backgroundColor'] = array(
				'group'   => 'buttonStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Background Color' ),
				'type'    => 'color',
				'inline'  => true,
				'default' => array(
					'hex' => '#d9534f',
				),
				'css'     => array(
					array(
						'property' => 'background-color',
						'selector' => '.bricks-button',
					),
				),
			);

			$this->controls['borderRadius'] = array(
				'group' => 'buttonStyle',
				'tab'   => 'style',
				'label' => esc_html__( 'Border' ),
				'type'  => 'border',
				'css'   => array(
					array(
						'property' => 'border',
						'selector' => '.bricks-button',
					),
				),
			);

			$this->controls['boxShadow'] = array(
				'group' => 'buttonStyle',
				'tab'   => 'style',
				'label' => esc_html__( 'Box Shadow' ),
				'type'  => 'box-shadow',
				'css'   => array(
					array(
						'property' => 'box-shadow',
						'selector' => '.bricks-button',
					),
				),
			);

			$this->controls['textPadding'] = array(
				'group' => 'buttonStyle',
				'tab'   => 'style',
				'label' => esc_html__( 'Padding' ),
				'type'  => 'spacing',
				'css'   => array(
					array(
						'property' => 'padding',
						'selector' => '.bricks-button',
					),
				),
			);
		}

		/**
		 * Renders the reject button element.
		 */
		public function render() {
			// Get element settings
			$settings = $this->settings;

			// Add 'class' attribute to element root tag
			$this->set_attribute( '_root', 'class', 'bricks-element' );
			$this->set_attribute( 'wrapper', 'class', 'bricks-button-wrapper' );
			$this->set_attribute( 'button', 'href', 'javascript:void(0);' );
			$this->set_attribute( 'button', 'class', 'bricks-button bricks-button-link wfocu_skip_offer' );

			if ( ! empty( $settings['size'] ) ) {
				$this->set_attribute( 'button', 'class', $settings['size'] );
			}

			/**
			 * Renders the reject button element HTML.
			 *
			 * @since 1.0.0
			 */ ?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <div style="display:flex;flex-direction:column;" <?php echo $this->render_attributes( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <a <?php echo $this->render_attributes( 'button' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php $this->render_button_text(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                </div>
            </div>
			<?php
		}

		/**
		 * Renders the button text.
		 */
		public function render_button_text() {
			$settings      = $this->settings;
			$icon          = ! empty( $settings['icon'] ) ? self::render_icon( $settings['icon'] ) : false;
			$icon_position = ! empty( $settings['iconAlign'] ) ? $settings['iconAlign'] : 'left';

			$this->set_attribute( 'content-wrapper', 'class', 'bricks-button-content-wrapper' );
			$this->set_attribute( 'text', 'class', 'bricks-button-text' );
			$this->set_attribute( 'icon-align', 'class', array(
					'bricks-button-icon',
					'bricks-button-icon-align-' . $icon_position,
				) );

			?>
            <span style="width:100%;" <?php echo $this->render_attributes( 'content-wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $icon && $icon_position === 'left' ) : ?>
                <span <?php echo $this->render_attributes( 'icon-align' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			<?php endif; ?>
			<span <?php echo $this->render_attributes( 'text' ); ?>><?php echo $settings['text']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php if ( $icon && $icon_position === 'right' ) : ?>
                <span <?php echo $this->render_attributes( 'icon-align' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			<?php endif; ?>
		</span>
			<?php
		}
	}
}