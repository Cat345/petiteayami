<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FK_Checkout_Redirect_Admin' ) ) {

	#[\AllowDynamicProperties]
	class FK_Checkout_Redirect_Admin {

		private static $ins = null;

		public function __construct() {
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
			add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_fields' ), 10, 1 );
			add_action( 'edited_product_cat', array( $this, 'save_category_meta' ) );
			add_action( 'admin_head', array( $this, 'output_tab_icon_css' ) );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Output CSS for the FunnelKit product data tab icon.
		 * Only on product edit screens.
		 *
		 * Follows WooCommerce's tab icon pattern: target li.{key}_options a::before on ul.wc-tabs.
		 * Uses an inline data URI so no external file request is needed.
		 */
		public function output_tab_icon_css() {
			$screen = get_current_screen();
			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}
			// FunnelKit chevron mark as URL-encoded inline SVG (# → %23, < → %3C, etc.).
			$svg_data_uri = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 231 141'%3E%3Cpolygon fill='%231DAAFC' points='229.3,2.3 147.7,141.6 115.2,141.6 196.3,2.3'/%3E%3Cpolygon fill='%23070045' points='164.7,2.3 84.1,141.6 2.6,2.3 100.2,2.3 85.6,30.9 83.7,30.9 52.1,30.9 83.7,86.8 131.7,2.3'/%3E%3C/svg%3E";
			?>
			<style>
				#woocommerce-product-data ul.wc-tabs li.fk_checkout_redirect_options a {
					display: flex;
					align-items: center;
					justify-content: start;
				}
				#woocommerce-product-data ul.wc-tabs li.fk_checkout_redirect_options a:before,
				#woocommerce-product-data ul.wc-tabs li.fk_checkout_redirect_options.active a:before {
					content: '';
					display: inline-block;
					width: 13px;
					height: 14px;
					margin-right: 0;
					background-repeat: no-repeat;
					background-position: center;
					background-size: contain;
					background-image: url("<?php echo $svg_data_uri; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded data URI, no user input ?>");
					filter: brightness(0) saturate(100%) invert(39%) sepia(67%) saturate(459%) hue-rotate(164deg) brightness(93%) contrast(116%);
				}
				#woocommerce-product-data ul.wc-tabs li.fk_checkout_redirect_options.active a:before {
					filter: brightness(0) saturate(100%) invert(32%) sepia(1%) saturate(0%) hue-rotate(135deg) brightness(96%) contrast(84%);
				}
			</style>
			<?php
		}

		/**
		 * Add FunnelKit tab to WooCommerce Product Data metabox.
		 *
		 * @param array $tabs Existing tabs.
		 *
		 * @return array Modified tabs.
		 */
		public function add_product_data_tab( $tabs ) {
			$tabs['fk_checkout_redirect'] = array(
				'label'    => __( 'FunnelKit', 'funnel-builder' ),
				'target'   => 'fk_checkout_redirect_data',
				'class'    => array(),
				'priority' => 80,
			);

			return $tabs;
		}

		/**
		 * Render the FunnelKit product data panel.
		 */
		public function render_product_data_panel() {
			global $post;

			$product_id = $post->ID;
			$funnel_id  = absint( get_post_meta( $product_id, '_fk_checkout_funnel_id', true ) );
			$step_id    = absint( get_post_meta( $product_id, '_fk_checkout_step_id', true ) );
			$cart_text  = get_post_meta( $product_id, '_fk_add_to_cart_text', true );

			// Get selected checkout name for display
			$selected_label = '';
			if ( $step_id > 0 ) {
				$step_post = get_post( $step_id );
				if ( $step_post ) {
					$funnel_title = '';
					if ( $funnel_id > 0 && class_exists( 'WFFN_Funnel' ) ) {
						$funnel       = new WFFN_Funnel( $funnel_id );
						$funnel_title = $funnel->get_title();
					}
					$selected_label = $funnel_title ? $funnel_title . ' → ' . $step_post->post_title : $step_post->post_title;
				}
			}

			wp_nonce_field( 'fk_checkout_redirect_save', 'fk_checkout_redirect_nonce' );
			?>
			<div id="fk_checkout_redirect_data" class="panel woocommerce_options_panel">
				<div id="fk_checkout_redirect_tip" class="inline notice woocommerce-message is-dismissible" style="margin: 10px 20px;">
					<p class="help" style="padding: 0;">
						<?php esc_html_e( 'Tip: You may also configure Product Checkout Redirect under product category settings. If a product-specific setting exists, it will be used instead of the category setting.', 'funnel-builder' ); ?>
						<button type="button" class="notice-dismiss" onclick="document.getElementById('fk_checkout_redirect_tip').style.display='none';"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'funnel-builder' ); ?></span></button>
					</p>
				</div>
				<div class="options_group">
					<p class="form-field">
						<label for="fk_checkout_search"><?php esc_html_e( 'Funnel Checkout', 'funnel-builder' ); ?></label>
						<select id="fk_checkout_search" name="fk_checkout_search" class="wc-product-search" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for a checkout&hellip;', 'funnel-builder' ); ?>">
							<?php if ( $step_id > 0 && ! empty( $selected_label ) ) : ?>
								<option value="<?php echo esc_attr( $funnel_id . ':' . $step_id ); ?>" selected="selected"><?php echo esc_html( $selected_label ); ?></option>
							<?php endif; ?>
						</select>
						<input type="hidden" name="_fk_checkout_funnel_id" id="_fk_checkout_funnel_id" value="<?php echo esc_attr( $funnel_id ); ?>" />
						<input type="hidden" name="_fk_checkout_step_id" id="_fk_checkout_step_id" value="<?php echo esc_attr( $step_id ); ?>" />
					</p>
					<p class="description" style="margin-left: 160px; margin-top: -10px;">
						<?php esc_html_e( 'When this product is in the cart, shoppers will be redirected to the selected checkout.', 'funnel-builder' ); ?>
					</p>
				</div>

				<div class="options_group">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'          => '_fk_add_to_cart_text',
							'label'       => __( 'Add to Cart Text', 'funnel-builder' ),
							'desc_tip'    => true,
							'description' => __( 'Override the "Add to Cart" button text for this product. Leave empty for default.', 'funnel-builder' ),
							'value'       => $cart_text,
						)
					);
					?>
				</div>
			</div>

			<script type="text/javascript">
				jQuery(function ($) {
					$('#fk_checkout_search').select2({
						ajax: {
							url: '<?php echo esc_url( rest_url( 'funnelkit-app/funnels/step/search' ) ); ?>',
							dataType: 'json',
							delay: 300,
							data: function (params) {
								return {
									s: params.term || '',
									type: 'wc_checkout',
									checkout_redirect: true
								};
							},
							beforeSend: function (xhr) {
								xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
							},
							processResults: function (data) {
								var results = [];
								if (data && data.length) {
									$.each(data, function (i, group) {
										if (!group.steps || !group.steps.length) {
											return true;
										}
										$.each(group.steps, function (j, step) {
											results.push({
												id: group.id + ':' + step.id,
												text: group.id > 0 ? group.name + ' \u2192 ' + step.name : step.name,
												funnel_id: group.id,
												step_id: step.id
											});
										});
									});
								}
								return { results: results };
							},
							cache: true
						},
						minimumInputLength: 0,
						allowClear: true,
						placeholder: '<?php echo esc_js( __( 'Search for a checkout…', 'funnel-builder' ) ); ?>'
					}).on('select2:select', function (e) {
						var data = e.params.data;
						$('#_fk_checkout_funnel_id').val(data.funnel_id || '');
						$('#_fk_checkout_step_id').val(data.step_id || '');
					}).on('select2:unselect', function () {
						$('#_fk_checkout_funnel_id').val('');
						$('#_fk_checkout_step_id').val('');
					});
				});
			</script>
			<?php
		}

		/**
		 * Save product meta on product save.
		 *
		 * @param int $post_id Product post ID.
		 */
		public function save_product_meta( $post_id ) {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( ! isset( $_POST['fk_checkout_redirect_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fk_checkout_redirect_nonce'] ) ), 'fk_checkout_redirect_save' ) ) {
				return;
			}

			$funnel_id = isset( $_POST['_fk_checkout_funnel_id'] ) ? absint( $_POST['_fk_checkout_funnel_id'] ) : 0;
			$step_id   = isset( $_POST['_fk_checkout_step_id'] ) ? absint( $_POST['_fk_checkout_step_id'] ) : 0;
			$cart_text = isset( $_POST['_fk_add_to_cart_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_fk_add_to_cart_text'] ) ) : '';

			if ( $funnel_id > 0 ) {
				update_post_meta( $post_id, '_fk_checkout_funnel_id', $funnel_id );
			} else {
				delete_post_meta( $post_id, '_fk_checkout_funnel_id' );
			}

			if ( $step_id > 0 ) {
				update_post_meta( $post_id, '_fk_checkout_step_id', $step_id );
			} else {
				delete_post_meta( $post_id, '_fk_checkout_step_id' );
			}

			if ( ! empty( $cart_text ) ) {
				update_post_meta( $post_id, '_fk_add_to_cart_text', $cart_text );
			} else {
				delete_post_meta( $post_id, '_fk_add_to_cart_text' );
			}
		}

		/**
		 * Render checkout redirect fields on product category edit screen.
		 *
		 * @param WP_Term $term Current term object.
		 */
		public function render_category_fields( $term ) {
			$funnel_id = absint( get_term_meta( $term->term_id, '_fk_checkout_funnel_id', true ) );
			$step_id   = absint( get_term_meta( $term->term_id, '_fk_checkout_step_id', true ) );
			$cart_text = get_term_meta( $term->term_id, '_fk_add_to_cart_text', true );

			// Get selected checkout name for display
			$selected_label = '';
			if ( $step_id > 0 ) {
				$step_post = get_post( $step_id );
				if ( $step_post ) {
					$funnel_title = '';
					if ( $funnel_id > 0 && class_exists( 'WFFN_Funnel' ) ) {
						$funnel       = new WFFN_Funnel( $funnel_id );
						$funnel_title = $funnel->get_title();
					}
					$selected_label = $funnel_title ? $funnel_title . ' → ' . $step_post->post_title : $step_post->post_title;
				}
			}

			wp_nonce_field( 'fk_checkout_redirect_cat_save', 'fk_checkout_redirect_cat_nonce' );
			?>
			<tr class="form-field">
				<th scope="row"><label for="fk_cat_checkout_search"><?php esc_html_e( 'Funnel Checkout', 'funnel-builder' ); ?></label></th>
				<td>
					<select id="fk_cat_checkout_search" name="fk_cat_checkout_search" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Search for a checkout&hellip;', 'funnel-builder' ); ?>">
						<?php if ( $step_id > 0 && ! empty( $selected_label ) ) : ?>
							<option value="<?php echo esc_attr( $funnel_id . ':' . $step_id ); ?>" selected="selected"><?php echo esc_html( $selected_label ); ?></option>
						<?php endif; ?>
					</select>
					<input type="hidden" name="_fk_cat_checkout_funnel_id" id="_fk_cat_checkout_funnel_id" value="<?php echo esc_attr( $funnel_id ); ?>" />
					<input type="hidden" name="_fk_cat_checkout_step_id" id="_fk_cat_checkout_step_id" value="<?php echo esc_attr( $step_id ); ?>" />
					<p class="description"><?php esc_html_e( 'Products in this category will redirect to the selected checkout (unless overridden at the product level).', 'funnel-builder' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="_fk_cat_add_to_cart_text"><?php esc_html_e( 'Add to Cart Text', 'funnel-builder' ); ?></label></th>
				<td>
					<input type="text" name="_fk_cat_add_to_cart_text" id="_fk_cat_add_to_cart_text" value="<?php echo esc_attr( $cart_text ); ?>" />
					<p class="description"><?php esc_html_e( 'Override the "Add to Cart" button text for products in this category. Leave empty for default.', 'funnel-builder' ); ?></p>
				</td>
			</tr>

			<script type="text/javascript">
				jQuery(function ($) {
					$('#fk_cat_checkout_search').select2({
						ajax: {
							url: '<?php echo esc_url( rest_url( 'funnelkit-app/funnels/step/search' ) ); ?>',
							dataType: 'json',
							delay: 300,
							data: function (params) {
								return {
									s: params.term || '',
									type: 'wc_checkout',
									checkout_redirect: true
								};
							},
							beforeSend: function (xhr) {
								xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
							},
							processResults: function (data) {
								var results = [];
								if (data && data.length) {
									$.each(data, function (i, group) {
										if (!group.steps || !group.steps.length) {
											return true;
										}
										$.each(group.steps, function (j, step) {
											results.push({
												id: group.id + ':' + step.id,
												text: group.id > 0 ? group.name + ' \u2192 ' + step.name : step.name,
												funnel_id: group.id,
												step_id: step.id
											});
										});
									});
								}
								return { results: results };
							},
							cache: true
						},
						minimumInputLength: 0,
						allowClear: true,
						placeholder: '<?php echo esc_js( __( 'Search for a checkout…', 'funnel-builder' ) ); ?>'
					}).on('select2:select', function (e) {
						var data = e.params.data;
						$('#_fk_cat_checkout_funnel_id').val(data.funnel_id || '');
						$('#_fk_cat_checkout_step_id').val(data.step_id || '');
					}).on('select2:unselect', function () {
						$('#_fk_cat_checkout_funnel_id').val('');
						$('#_fk_cat_checkout_step_id').val('');
					});
				});
			</script>
			<?php
		}

		/**
		 * Save category term meta.
		 *
		 * @param int $term_id Term ID.
		 */
		public function save_category_meta( $term_id ) {

			if ( ! current_user_can( 'manage_product_terms' ) ) {
				return;
			}

			if ( ! isset( $_POST['fk_checkout_redirect_cat_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fk_checkout_redirect_cat_nonce'] ) ), 'fk_checkout_redirect_cat_save' ) ) {
				return;
			}

			$funnel_id = isset( $_POST['_fk_cat_checkout_funnel_id'] ) ? absint( $_POST['_fk_cat_checkout_funnel_id'] ) : 0;
			$step_id   = isset( $_POST['_fk_cat_checkout_step_id'] ) ? absint( $_POST['_fk_cat_checkout_step_id'] ) : 0;
			$cart_text = isset( $_POST['_fk_cat_add_to_cart_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_fk_cat_add_to_cart_text'] ) ) : '';

			if ( $funnel_id > 0 ) {
				update_term_meta( $term_id, '_fk_checkout_funnel_id', $funnel_id );
			} else {
				delete_term_meta( $term_id, '_fk_checkout_funnel_id' );
			}

			if ( $step_id > 0 ) {
				update_term_meta( $term_id, '_fk_checkout_step_id', $step_id );
			} else {
				delete_term_meta( $term_id, '_fk_checkout_step_id' );
			}

			if ( ! empty( $cart_text ) ) {
				update_term_meta( $term_id, '_fk_add_to_cart_text', $cart_text );
			} else {
				delete_term_meta( $term_id, '_fk_add_to_cart_text' );
			}
		}
	}
}
