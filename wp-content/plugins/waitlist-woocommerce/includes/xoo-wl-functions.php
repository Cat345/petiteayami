<?php

//Add notice
function xoo_wl_add_notice( $message, $notice_type = 'error' ){

	$classes = $notice_type === 'error' ? 'xoo-wl-notice-error' : 'xoo-wl-notice-success';
	
	$html = '<div class="'.$classes.'">'.$message.'</div>';
	
	return apply_filters( 'xoo_wl_notice_html', $html, $message, $notice_type );
}


/* check whether the product is out of stock as per our parameters */
function xoo_wl_is_product_out_of_stock( $product_id ){

	$product = wc_get_product( $product_id );

	if( $product ){
		return apply_filters( 'xoo_wl_product_is_out_of_stock', !$product->is_in_stock() || ( xoo_wl_helper()->get_general_option( 'm-en-bod' ) === "yes" &&  $product->get_stock_status() === "onbackorder" && ( !$product->get_manage_stock() || $product->get_stock_quantity() <= 0 ) ), $product );

	}
	
	return false;

}

function xoo_wl_should_show_waitlist( $product_id ){

	$product = wc_get_product( $product_id );

	if( $product ){

		$parentProductID 	= $product->is_type('variation') ? $product->get_parent_id() : $product_id;
		$forceShow 			= get_post_meta( $parentProductID, '_xoo_waitlist_force_show', true ) === "yes";

		return apply_filters( 'xoo_wl_product_should_show_waitlist', $forceShow || xoo_wl_is_product_out_of_stock( $product_id ), $product );

	}
	
	return false;
}


function xoo_wl_form_markup( $product_id , $type = 'popup', $args = array() ){

	$defaults = array(
		'text' 				=> xoo_wl_helper()->get_general_option( 'txt-btn' ),
		'id' 				=> $product_id,
		'type' 				=> $type,
		'container_class' 	=> array(),
		'button_class' 		=> array(
			'button',
			'btn'
		),
		'validation' 		=> true
	);

	$args = wp_parse_args( $args, $defaults );

	if( !$product_id ) return;

	if( get_post_meta( $product_id, '_xoo_waitlist_disable', true ) === "yes" ) return;

	$product = wc_get_product( $product_id );

	if( !$product || !is_object( $product ) ) return;

	$product_type = $product->get_type();

	if( $args['validation']  ){
		if( $product_type === 'grouped' ) return;

		$show_waitlist = xoo_wl_should_show_waitlist( $product_id );

		if( $product_type === 'variable' ){
			if( $show_waitlist ){
				$args['container_class'][] = 'xoo-wl-btc-show';
			}
		}
		else{
			if( !$show_waitlist ) return;
		}
	}
	

	if( $product ){
		$args['container_class'][] = 'xoo-wl-btc-'.$product_type;
	}


	$args['container_class'][] = 'xoo-wl-btc-'.$type;

	$container_class = implode( " ", $args['container_class'] );

	$html = '<div class="xoo-wl-btn-container '.$container_class.'">';

	$btn_class = $type === 'inline_toggle' ? 'xoo-wl-btn-toggle' : 'xoo-wl-btn-popup';

	//Fetch button if not inline
	if( $type !== 'inline' ){
		$args['button_class'][] = $btn_class;
		$args['class'] = implode( " ", $args['button_class'] );
		$html .= xoo_wl_helper()->get_template( 'xoo-wl-button.php', array( 'args' => $args ), '', true );
	}

	//Fetch Inline form
	if( $type === 'inline' || $type === 'inline_toggle' ){
		$form_args = array(
			'product_id' => $product_id
		);
		$html .= '<div class="xoo-wl-inline-form">';
		$html .= xoo_wl_helper()->get_template( 'xoo-wl-form.php', $form_args, '', true );
		$html .= '</div>';
	}

	$html .= '</div>';

	return apply_filters( 'xoo_wl_form_markup', $html, $args );

}


function xoo_wl_urls( $key ){

	$urls = array(
		'email_history' => admin_url( 'admin.php?page=xoo-wl-email-history' ),
		'preview_email' 	=> admin_url( 'admin.php?page=xoo-wl&preview=true' )
	);

	return isset( $urls[ $key ] ) ? $urls[ $key ] : null;

}

function xoo_wl_form_shortcode($user_atts){

	$atts = shortcode_atts( array(
		'id' 			=> 0,
		'type' 			=> xoo_wl_helper()->get_general_option('m-form-type'),
		'text' 			=> xoo_wl_helper()->get_general_option( 'txt-btn' )
	), $user_atts, 'xoo_wl_form');


	if( !$atts['id'] ){
	    global $product;
	    if( $product ){
	    	$atts['id'] = $product->get_id();
	    }
	}


	return xoo_wl_form_markup( $atts['id'], $atts['type'], $atts );

}
add_shortcode( 'xoo_wl_form', 'xoo_wl_form_shortcode' );

function xoo_wl_prefill_email( $args ){
	if( $args['unique_id'] === 'xoo_wl_user_email' && is_user_logged_in() ){
		$args['value'] =  esc_html(wp_get_current_user()->user_email);
	}
	return $args;
}

add_action( 'xoo_aff_waitlist-woocommerce_input_args', 'xoo_wl_prefill_email' );

?>