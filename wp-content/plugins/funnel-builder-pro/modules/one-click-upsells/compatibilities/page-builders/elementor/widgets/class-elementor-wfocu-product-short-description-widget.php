<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! class_exists( 'WFOCU_Product_Short_Description_Widget' ) ) {
	/**
	 * Class WFOCU_Product_Short_Description_Widget
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Product_Short_Description_Widget extends \Elementor\Widget_Base {

		public function get_name() {
			return 'wfocu-short-description';
		}

		/**
		 * Upsell widgets render against the live offer/session for the current request and must
		 * never have their markup baked into Elementor's element cache.
		 *
		 * Returning true makes Elementor store a `[elementor-element k=... data=...]` placeholder
		 * in `_elementor_element_cache` instead of the rendered HTML; the widget is then
		 * re-rendered on every request via do_shortcode().
		 *
		 * Element_Base already defaults to true, but we declare it explicitly so the behaviour is
		 * intentional and survives any change to that default.
		 *
		 * @return bool
		 */
		protected function is_dynamic_content(): bool {
			return true;
		}

		public function get_title() {
			return __( 'Product Short Description', 'elementor' );
		}

		public function get_icon() {
			return 'wfocu-icon-product_description';
		}

		/**
		 * Get widget categories.
		 *
		 * Retrieve the list of categories the widget belongs to.
		 *
		 * @access public
		 *
		 * @return array Widget categories.
		 */
		public function get_categories() {
			return array( 'upstroke' );
		}


		public function get_keywords() {
			return array( 'woocommerce', 'shop', 'store', 'image', 'product', 'gallery', 'lightbox' );
		}

		protected function register_controls() {

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			$this->start_controls_section(
				'section_product_desc',
				array(
					'label' => __( 'Offer Product Description', 'woofunnels-upstroke-one-click-upsell' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				)
			);

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->name;
			}

			$this->add_control(
				'selected_product',
				array(
					'label'   => __( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
					'type'    => Controls_Manager::SELECT,
					'default' => key( $product_options ),
					'options' => $product_options,
				)
			);

			do_action( 'wfocu_add_elementor_controls', $this, $offer_id, $products );

			$this->end_controls_section();

			$this->start_controls_section(
				'section_product_description_style',
				array(
					'label' => __( 'Style', 'elementor' ),
					'tab'   => Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_responsive_control(
				'text_align',
				array(
					'label'     => __( 'Alignment', 'elementor' ),
					'type'      => Controls_Manager::CHOOSE,
					'options'   => array(
						'left'    => array(
							'title' => __( 'Left', 'elementor' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center'  => array(
							'title' => __( 'Center', 'elementor' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'   => array(
							'title' => __( 'Right', 'elementor' ),
							'icon'  => 'eicon-text-align-right',
						),
						'justify' => array(
							'title' => __( 'Justified', 'elementor' ),
							'icon'  => 'eicon-text-align-justify',
						),
					),
					'selectors' => array(
						'{{WRAPPER}}' => 'text-align: {{VALUE}}',
					),
				)
			);

			$this->add_control(
				'text_color',
				array(
					'label'     => __( 'Text Color', 'elementor' ),
					'type'      => Controls_Manager::COLOR,
					'default'   => '#414349',
					'selectors' => array(
						'{{WRAPPER}} .elementor-widget-container' => 'color: {{VALUE}}',
					),
				)
			);

			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'text_typography',
					'label'    => __( 'Typography', 'elementor' ),
					'selector' => '{{WRAPPER}}',
				)
			);
		}

		/**
		 * Render output
		 */
		public function render() {

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_key  = $this->get_settings( 'selected_product' );

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$post_object = get_post( $product->get_id() );

			$description = $post_object->post_excerpt;
			if ( 'product_variation' === $post_object->post_type ) {
				$product = wc_get_product( $product->get_id() );
				if ( $product instanceof WC_Product ) {
					$description = $product->get_description();
				}
			}

			$short_description = apply_filters( 'woocommerce_short_description', $description );
			if ( empty( $short_description ) ) {
				return;
			}
			?>
			<div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>

				<?php echo $short_description; // WPCS: XSS ok. ?>
			</div>
			<?php
		}
	}
}
