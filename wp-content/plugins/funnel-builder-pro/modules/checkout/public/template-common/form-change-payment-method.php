<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
wp_dequeue_script( 'wc-single-product' );
/**
 * @var $instance WFACP_Template_Common
 */
$checkout       = WC()->checkout();
$instance       = wfacp_template();
$totalStepCount = $instance->get_step_count();

$stepClassName  = 'wfacp_single_step_form';
$stepMultiClass = 'wfacp_single_step_form';
if ( $totalStepCount > 1 && $totalStepCount == 2 ) {
	$stepMultiClass = 'wfacp_two_step';
} else {
	$stepMultiClass = 'wfacp_three_step';
}
if ( $totalStepCount > 1 ) {
	$stepClassName = 'wfacp_single_multi_form';
}
do_action( 'wfacp_before_form' );

$is_global_checkout_f = WFACP_Core()->public->is_checkout_override();

$form_class = array();
if ( ! empty( $stepClassName ) ) {
	$form_class[] = $stepClassName;
}
if ( ! empty( $stepMultiClass ) ) {
	$form_class[] = $stepMultiClass;
}
if ( $is_global_checkout_f === true ) {
	$form_class[] = 'wfacp_global_checkout_wrap';
}
remove_filter( 'woocommerce_get_checkout_url', array( WFACP_Core()->public, 'woocommerce_get_checkout_url' ), 99999 );
?>
<div class="wfacp_main_form woocommerce <?php echo esc_attr( implode( ' ', $form_class ) ); ?>">
	<?php
	do_action( 'wfacp_outside_header' );
	$checkout = WC()->checkout();
	require __DIR__ . '/form_internal_css.php';

	ob_start();
	do_action( 'before_woocommerce_pay' );
	do_action( 'after_woocommerce_pay' );
	$html = ob_get_clean();

	$wfacp_cp_inject_hidden = static function ( $open_tag ) {
		$inject = '<input type="hidden" name="_wfacp_post_id" value="' . esc_attr( (string) WFACP_Common::get_id() ) . '" />';
		$gt     = strpos( $open_tag, '>', 0 );
		if ( false === $gt ) {
			return $open_tag . $inject;
		}

		return substr_replace( $open_tag, $inject, $gt + 1, 0 );
	};

	$wfacp_cp_extract_order_review = static function ( $full ) {
		$start = stripos( $full, '<form' );
		if ( false === $start ) {
			return null;
		}
		$head = substr( $full, $start, min( 400, strlen( $full ) - $start ) );
		if ( false === stripos( $head, 'order_review' ) ) {
			return null;
		}
		$tag_end = strpos( $full, '>', $start );
		if ( false === $tag_end ) {
			return null;
		}
		$open_tag    = substr( $full, $start, $tag_end - $start + 1 );
		$inner_start = $tag_end + 1;
		$pos         = $inner_start;
		$depth       = 1;
		$len         = strlen( $full );
		while ( $pos < $len && $depth > 0 ) {
			$next_open  = stripos( $full, '<form', $pos );
			$next_close = stripos( $full, '</form>', $pos );
			if ( false === $next_close ) {
				return null;
			}
			if ( false !== $next_open && $next_open < $next_close ) {
				++$depth;
				$pos = $next_open + 5;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					return array(
						'prefix'   => substr( $full, 0, $start ),
						'open_tag' => $open_tag,
						'inner'    => substr( $full, $inner_start, $next_close - $inner_start ),
						'suffix'   => substr( $full, $next_close + 7 ),
					);
				}
				$pos = $next_close + 7;
			}
		}

		return null;
	};

	$parsed = is_string( $html ) ? $wfacp_cp_extract_order_review( $html ) : null;

	if ( null !== $parsed ) {
		$prefix   = $parsed['prefix'];
		$open_tag = $wfacp_cp_inject_hidden( $parsed['open_tag'] );
		$inner    = $parsed['inner'];
		$suffix   = $parsed['suffix'];

		$woocommerce_div = '<div class="woocommerce">';
		$prefix_l        = ltrim( $prefix );
		if ( 0 === strpos( $prefix_l, $woocommerce_div ) ) {
			$prefix = substr( $prefix_l, strlen( $woocommerce_div ) );
		}
		$suffix_t = rtrim( $suffix );
		if ( strlen( $suffix_t ) >= 6 && '</div>' === substr( $suffix_t, -6 ) ) {
			$suffix = trim( substr( $suffix_t, 0, -6 ) );
		}

		$table_close = stripos( $inner, '</table>' );
		if ( false !== $table_close ) {
			$table_end    = $table_close + strlen( '</table>' );
			$table_part   = substr( $inner, 0, $table_end );
			$payment_part = trim( substr( $inner, $table_end ) );
		} else {
			$table_part   = '';
			$payment_part = trim( $inner );
		}

		$selected_template_slug      = $instance->get_template_slug();
		$border_cls                  = $instance->get_heading_title_class();
		$payment_methods_heading     = $instance->payment_heading();
		$payment_methods_sub_heading = $instance->payment_sub_heading();

		global $wp;
		$order_for_summary = null;
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$order_for_summary = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
		}

		echo $prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<?php echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="checkout woocommerce-checkout">
						<div class="wfacp-left-panel wfacp_page">
							<div class="wfacp-section wfacp-hg-by-box" data-field-count="1">
								<div class="wfacp_internal_form_wrap wfacp-comm-title <?php echo esc_attr( $instance->get_heading_title_class() ); ?>">
									<h2 class="wfacp_section_heading wfacp_section_title <?php echo esc_attr( $instance->get_heading_class() ); ?>"><?php echo wp_kses_post( $instance->get_order_pay_summary_heading() ); ?></h2>
								</div>
								<div class="wfacp-comm-form-detail clearfix">
									<div class="wfacp-row">
										<?php
										if ( $order_for_summary instanceof WC_Order ) {
											$instance->get_order_pay_summary( $order_for_summary );
										} elseif ( '' !== $table_part ) {
											echo $table_part; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										?>
									</div>
								</div>
							</div>
						</div>
						<div class="wfacp-section wfacp_payment form_section_your_order_0_<?php echo esc_attr( $selected_template_slug ); ?> wfacp-section-titlex wfacp-hg-by-box">
							<div style="clear: both;"></div>
							<div class="wfacp-comm-title <?php echo esc_attr( $border_cls ); ?>">
								<h2 class="wfacp_section_heading wfacp_section_title <?php echo esc_attr( $instance->get_heading_class() ); ?> "><?php echo wp_kses_post( $payment_methods_heading ); ?></h2>
								<h4 class="<?php echo esc_attr( $instance->get_sub_heading_class() ); ?>"><?php echo wp_kses_post( $payment_methods_sub_heading ); ?></h4>
							</div>
							<div class="woocommerce-checkout-review-order wfacp-oder-detail clearfix">
								<?php
								if ( '' !== $payment_part ) {
									echo $payment_part; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</div>
						</div>
					</div>
				</form>
		<?php
		echo $suffix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		$inject = '<input type="hidden" name="_wfacp_post_id" value="' . esc_attr( (string) WFACP_Common::get_id() ) . '" />';
		$start  = stripos( $html, '<form' );
		$gt     = false !== $start ? strpos( $html, '>', $start ) : false;
		if ( false !== $gt ) {
			$html = substr_replace( $html, $inject, $gt + 1, 0 );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
</div>
<?php
do_action( 'wfacp_after_form' );
