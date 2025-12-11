<?php

/**
 * Class WFACP_Insert_Page
 * Class controls the setting of FunnelKit Checkout page
 */
if ( ! class_exists( 'WFACP_Insert_Page' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Insert_Page {
		private $title = '';
		private $wfacp_id = 0;
		private $form_step_count = 1;
		private $page_name = '';
		private $products = [];
		private $setProducts = [];
		private $is_multi = false;
		private $setPostStatus = 'draft';
		private $setFormLayout = [];
		private $setCustomizer = [];
		private $setTemplates = [
			'selected'      => 'shopcheckout',
			'selected_type' => 'pre_built'
		];


		public function __construct( $wfacp_id = 0 ) {
			if ( $wfacp_id > 0 ) {
				$this->wfacp_id = $wfacp_id;
			}
		}

		public function setTitle( $title ) {
			$this->title = $title;

			return $this;
		}

		public function setProducts() {
			$this->get_products();

			if ( ! is_array( $this->products ) && count( $this->products ) == 0 ) {
				return $this;
			}
			foreach ( $this->products as $pid ) {
				$unique_id = uniqid( 'wfacp_' );
				$product   = wc_get_product( $pid );
				if ( $product instanceof WC_Product ) {
					$product_type                    = $product->get_type();
					$image_id                        = $product->get_image_id();
					$default                         = WFACP_Common::get_default_product_config();
					$default['type']                 = $product_type;
					$default['id']                   = $product->get_id();
					$default['parent_product_id']    = $product->get_parent_id();
					$default['title']                = $product->get_title();
					$default['stock']                = $product->is_in_stock();
					$default['is_sold_individually'] = $product->is_sold_individually();

					$product_image_url = '';
					$images            = wp_get_attachment_image_src( $image_id );
					if ( is_array( $images ) && count( $images ) > 0 ) {
						$product_image_url = wp_get_attachment_image_src( $image_id )[0];
					}
					$default['image'] = apply_filters( 'wfacp_product_image', $product_image_url, $product );

					if ( $default['image'] == '' ) {
						$default['image'] = WFACP_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
					}

					if ( in_array( $product_type, WFACP_Common::get_variable_product_type() ) ) {
						$default['variable'] = 'yes';
						$default['price']    = $product->get_price_html();


						$pro                = WFACP_Common::wc_get_product( $default['id'], $unique_id );
						$is_found_variation = WFACP_Common::get_default_variation( $pro );


						if ( count( $is_found_variation ) > 0 ) {
							$default['default_variation']      = $is_found_variation['variation_id'];
							$default['default_variation_attr'] = $is_found_variation['attributes'];
						}

					} else {
						if ( in_array( $product_type, WFACP_Common::get_variation_product_type() ) ) {
							$default['title'] = $product->get_name();
						}
						$row_data                 = $product->get_data();
						$sale_price               = $row_data['sale_price'];
						$default['price']         = wc_price( $row_data['price'] );
						$default['regular_price'] = wc_price( $row_data['regular_price'] );
						if ( '' != $sale_price ) {
							$default['sale_price'] = wc_price( $sale_price );
						}
					}

					$resp['products'][ $unique_id ]  = $default;
					$default                         = WFACP_Common::remove_product_keys( $default );
					$this->setProducts[ $unique_id ] = $default;
				}
			}

			return $this;
		}


		public function setFormLayout( $is_multi = false, $steps = 3 ) {
			if ( $is_multi == true ) {
				$this->is_multi      = true;
				$three_step          = include WFACP_PLUGIN_DIR . '/importer/pre-built/shopcheckout-three.php';
				$this->setFormLayout = WFACP_Common::get_page_layout_multistep();
				$this->setFormLayout = $three_step['page_layout'];
//			if ( $steps == 2 ) {
//				$this->setFormLayout['steps']['third_step']["active"] = "no";
//				$lastStep                                             = $this->setFormLayout['fieldsets']['third_step'];
//				unset( $this->setFormLayout['fieldsets']['third_step'] );
//				$this->setFormLayout['fieldsets']['two_step'] = array_merge( $this->setFormLayout['fieldsets']['two_step'], $lastStep );
//				$this->setFormLayout['current_step']          = "two_step";
//
//			}
				if ( is_array( $this->setFormLayout['fieldsets'] ) && count( $this->setFormLayout['fieldsets'] ) > 0 ) {
					$this->form_step_count = sizeof( $this->setFormLayout['steps'] );

				}

			}

			return $this;
		}


		public function is_multi_step_form_type() {
			return $this->is_multi;
		}

		public function get_form_step_count() {
			return $this->form_step_count;
		}

		public function get_wfacp_id() {
			return $this->wfacp_id;
		}

		public function setTemplate( $selected_template, $selected_type = 'pre_built' ) {
			$this->setTemplates['selected']      = $selected_template;
			$this->setTemplates['selected_type'] = $selected_type;

			return $this;
		}

		public function setCustomizer( $setCustomizer ) {
			$this->setCustomizer = $setCustomizer;

			return $this;
		}

		public function setSetting() {
			return $this;
		}

		public function setPostStatus( $setPostStatus ) {
			$this->setPostStatus = $setPostStatus;
		}

		public function setPageName( $page_name ) {
			$this->page_name = $page_name;

			return $this;
		}

		public function save() {

			if ( $this->wfacp_id == 0 ) {
				$post                 = [];
				$post['post_title']   = isset( $this->title ) ? $this->title : "Page Title";
				$post['post_type']    = WFACP_Common::get_post_type_slug();
				$post['post_status']  = $this->setPostStatus;
				$post['post_content'] = '';
				$post['post_name']    = isset( $this->page_name ) ? $this->page_name : "Page Title";
				$wfacp_id             = wp_insert_post( $post );
				$this->wfacp_id       = $wfacp_id;
			}


			if ( is_wp_error( $this->wfacp_id ) ) {
				return;
			}


			/* Update Products for WFACP Pages */
			if ( is_array( $this->setProducts ) && count( $this->setProducts ) > 0 ) {
				WFACP_Common::update_page_product( $this->wfacp_id, $this->setProducts );
			}

			/* Update Page Layout for Multi step Form */
			if ( true === $this->is_multi && is_array( $this->setFormLayout ) ) {
				WFACP_Common::update_page_layout( $this->wfacp_id, $this->setFormLayout, false );
				$data                      = WFACP_Common::get_page_settings( $this->wfacp_id );
				$data['show_on_next_step'] = [
					'single_step' => [
						'billing_email'      => "true",
						'billing_first_name' => "false",
						'billing_last_name'  => "false",
						'shipping-address'   => "true",
						'address'            => "true",
					],
					'two_step'    => [
						'shipping_calculator' => "true",

					],
				];

				WFACP_Common::update_page_settings( $this->wfacp_id, $data );

			}

			/* Update Page Design */
			if ( is_array( $this->setTemplates ) && count( $this->setProducts ) > 0 ) {
				WFACP_Common::update_page_design( $this->wfacp_id, $this->setTemplates );
			}

			/* Update Customizer Data */


			if ( is_array( $this->setCustomizer ) && count( $this->setCustomizer ) > 0 ) {


				update_option( "wfacp_c_" . $this->wfacp_id, $this->setCustomizer, 'no' );
			}

		}


		public function loadFromJson( $json = [] ) {

			return $this;
		}

		private function get_products() {
			$query          = new WC_Product_Query( array(
				'limit'        => 2,
				'status'       => array( 'publish' ),
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'ids',
				'type'         => [ 'simple', 'variable' ],
				'stock_status' => 'instock',
			) );
			$this->products = $query->get_products();
		}
	}

}