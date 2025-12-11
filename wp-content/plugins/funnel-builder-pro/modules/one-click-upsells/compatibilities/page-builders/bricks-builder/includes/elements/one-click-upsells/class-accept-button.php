<?php

namespace WfocuFunnelKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( '\WfocuFunnelKit\Accept_Button' ) ) {
	class Accept_Button extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-accept-offer-button';
		public $icon = 'wfocu-icon-button_yes';

		/**
		 * Retrieves the label for the Accept Button element.
		 *
		 * @return string The label for the Accept Button element.
		 */
		public function get_label() {
			return esc_html__( 'Accept Button' );
		}

		/**
		 * Sets the control groups for the Accept Button element.
		 *
		 * This method initializes the control groups array and adds control groups for the button content and button style.
		 * It also calls the set_common_control_groups() method to add any common control groups.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['buttonContent'] = array(
				'title' => esc_html__( 'Accept Offer' ),
				'tab'   => 'content',
			);

			$this->control_groups['buttonStyle'] = array(
				'title' => esc_html__( 'Accept Offer' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Set the controls for the Accept Button element.
		 *
		 * This method sets the controls for the Accept Button element, including the product options,
		 * title, subtitle, spacing, alignment, icon, typography, and background color.
		 *
		 * @return void
		 */
		public function set_controls() {
			$offer_id        = WFOCU_Core()->template_loader->get_offer_id();
			$product_options = array( '0' => __( '--No Product--' ) );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
				foreach ( $products as $key => $product ) {
					$product_options[ $key ] = $product->data->get_name();
				}
			}

			$this->controls['selectedProduct'] = array(
				'group'   => 'buttonContent',
				'label'   => esc_html__( 'Product' ),
				'type'    => 'select',
				'options' => $product_options,
				'default' => key( $product_options ),
			);

			$this->controls['text'] = array(
				'group'       => 'buttonContent',
				'label'       => esc_html__( 'Title' ),
				'type'        => 'text',
				'default'     => esc_html__( 'Yes, Add This To My Order' ),
				'placeholder' => esc_html__( 'Yes, Add This To My Order' ),
			);

			$this->controls['subtitle'] = array(
				'group'       => 'buttonContent',
				'label'       => esc_html__( 'Subtitle' ),
				'type'        => 'text',
				'default'     => esc_html__( 'We will ship it out in same package.' ),
				'placeholder' => esc_html__( 'We will ship it out in same package.' ),
			);

			$this->controls['textSpacing'] = array(
				'group'       => 'buttonContent',
				'label'       => esc_html__( 'Spacing' ),
				'type'        => 'slider',
				'css'         => array(
					array(
						'property' => 'margin-bottom',
						'selector' => '.bricks-button-text',
					),
				),
				'units'       => array(
					'px' => array(
						'min'  => 2,
						'max'  => 50,
						'step' => 1,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'     => '2px',
				'description' => esc_html__( 'Spacing between Title and Subtitle' ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['align'] = array(
				'group'   => 'buttonContent',
				'label'   => esc_html__( 'Alignment' ),
				'type'    => 'align-items',
				'tooltip' => array(
					'content'  => 'align-self',
					'position' => 'top-left',
				),
				'default' => 'stretch',
				'css'     => array(
					array(
						'selector' => '.bricks-button',
						'property' => 'align-self',
					),
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
					'font-size'   => '18px',
					'line-height' => '1.5',
					'font-weight' => '700',
					'color'       => array(
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

			$this->controls['typographySubtitle'] = array(
				'group'   => 'buttonStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Subtitle Typography' ),
				'type'    => 'typography',
				'css'     => array(
					array(
						'property' => 'font',
						'selector' => '.bricks-button-subtitle',
					),
				),
				'default' => array(
					'font-size'   => '15px',
					'line-height' => '1.3',
					'font-weight' => '400',
					'color'       => array(
						'hex' => '#ffffff',
					),
				),
			);

			$this->controls['iconTypography'] = array(
				'group'    => 'buttonStyle',
				'tab'      => 'style',
				'label'    => esc_html__( 'Icon Typography' ),
				'type'     => 'typography',
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
					'hex' => '#70dc1d',
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
				'group'   => 'buttonStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Box Shadow' ),
				'type'    => 'box-shadow',
				'css'     => array(
					array(
						'property' => 'box-shadow',
						'selector' => '.bricks-button',
					),
				),
				'inline'  => true,
				'small'   => true,
				'default' => array(
					'values' => array(
						'offsetX' => 0,
						'offsetY' => 5,
						'blur'    => 0,
						'spread'  => 0,
					),
					'color'  => array(
						'hex' => '#00b211',
					),
				),
			);

			$this->controls['textPadding'] = array(
				'group'   => 'buttonStyle',
				'tab'     => 'style',
				'label'   => esc_html__( 'Padding' ),
				'type'    => 'spacing',
				'default' => array(
					'top'    => 12,
					'right'  => 5,
					'bottom' => 12,
					'left'   => 5,
				),
				'css'     => array(
					array(
						'property' => 'padding',
						'selector' => '.bricks-button',
					),
				),
			);
		}

		/**
		 * Renders the accept button element.
		 */
		public function render() {
			// Add 'class' attribute to element root tag
			$this->set_attribute( '_root', 'class', 'bricks-element' );
			$this->set_attribute( 'wrapper', 'class', 'bricks-button-wrapper' );
			$this->set_attribute( 'button', 'href', 'javascript:void(0);' );
			$this->set_attribute( 'button', 'class', 'bricks-button bricks-button-link wfocu_upsell' );
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_key = isset( $this->settings['selectedProduct'] ) ? $this->settings['selectedProduct'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $product_key );
			if ( ! empty( $product_key ) ) {
				$this->set_attribute( 'button', 'data-key', $product_key );

			}
			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <div style="display:flex;flex-direction:column;" <?php echo $this->render_attributes( 'wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <a <?php echo $this->render_attributes( 'button' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?>>
						<?php $this->render_button_text(); ?>
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
			$this->set_attribute( 'subtitle', 'class', 'bricks-button-subtitle' );

			$this->set_attribute( 'icon-align', 'class', array(
					'bricks-button-icon',
					'bricks-button-icon-align-' . $icon_position,
				) );

			?>
            <span style="width:100%;" <?php echo $this->render_attributes( 'content-wrapper' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $icon && $icon_position === 'left' ) : ?>
                <span <?php echo $this->render_attributes( 'icon-align' );  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			<?php endif; ?>
			<span <?php echo $this->render_attributes( 'text' ); ?>><?php echo $settings['text']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php if ( $icon && $icon_position === 'right' ) : ?>
                <span <?php echo $this->render_attributes( 'icon-align' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			<?php endif; ?>
				<?php if ( ! empty( $settings['subtitle'] ) ) : ?>
                    <span style="display:block;" <?php echo $this->render_attributes( 'subtitle' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo $settings['subtitle']; ?></span>
				<?php endif; ?>
		</span>
			<?php
		}
	}
}