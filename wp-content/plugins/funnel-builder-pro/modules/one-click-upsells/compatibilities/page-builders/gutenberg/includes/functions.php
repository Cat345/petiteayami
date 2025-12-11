<?php


if ( ! function_exists( 'bwfblocks_attr' ) ) {
    /**
     * Build list of attributes into a string and apply contextual filter on string.
     *
     * The contextual filter is of the form `bwfblocks_attr_{context}_output`.
     *
     * @since 1.2.0
     *
     * @param string $context    The context, to build filter name.
     * @param array  $attributes Optional. Extra attributes to merge with defaults.
     * @param array  $settings   Optional. Custom data to pass to filter.
     * @return string String of HTML attributes and values.
     */
    
    function bwfblocks_attr( $context, $attributes = array(), $settings = array() ) {
        $output = '';
    
        // Cycle through attributes, build tag attribute string.
        foreach ( $attributes as $key => $value ) {
    
            if ( ! $value ) {
                continue;
            }
    
            if ( true === $value ) {
                $output .= esc_html( $key ) . ' ';
            } else {
                $output .= sprintf( '%s="%s" ', esc_html( $key ), esc_attr( $value ) );
            }
        }
    
        $output = apply_filters( "bwfblocks_attr_{$context}_output", $output, $attributes, $settings, $context );
    
        return trim( $output );
    }
}

if ( ! function_exists( 'bwfupsell_get_block_defaults' ) ) {
	function bwfupsell_get_block_defaults() {
		$defaults = array();
		$common   = array();

		$defaults['accept-button'] = array_merge( $common, [
			'content'                => 'Yes, Add This To My Order',
			'secondaryContent'       => 'We will ship it out in same package.',
			'secondaryContentEnable' => false,
			'classWrap'              => 'wfocu-accept-button',
			'anchorclasses'          => 'wfocu_upsell',
			'attributes'             => WFOCU_Core()->template_loader->add_attributes_to_buy_button( false ),
		] );

		$defaults['reject-button'] = array_merge( $common, [
			'content'                => 'No thanks, I don’t want to take advantage of this one-time offer',
			'secondaryContent'       => 'We will ship it out in same package.',
			'secondaryContentEnable' => false,
			'classWrap'              => 'wfocu-reject-button',
			'anchorclasses'          => 'wfocu_skip_offer',
		] );

		$defaults['accept-link'] = array_merge( $common, [
			'content'                => 'Yes, Add This To My Order',
			'secondaryContent'       => 'We will ship it out in same package.',
			'secondaryContentEnable' => false,
			'classWrap'              => 'wfocu-link',
			'anchorclasses'          => 'wfocu_upsell',
			'attributes'             => WFOCU_Core()->template_loader->add_attributes_to_buy_button( false ),
		] );

		$defaults['reject-link'] = array_merge( $common, [
			'content'                => 'No thanks, I don’t want to take advantage of this one-time offer',
			'secondaryContent'       => 'We will ship it out in same package.',
			'secondaryContentEnable' => false,
			'classWrap'              => 'wfocu-link wfocu-reject',
			'anchorclasses'          => 'wfocu_skip_offer',
		] );

		$defaults['offer-block'] = array_merge( $common, [
			'contentEnable'          => true,
			'secondaryContentEnable' => true,
			'classWrap'              => 'bwf-upsell-offer-wrap wfocu-price-wrapper',
			'content'                => 'Regular Price',
			'secondaryContent'       => 'Offer Price',
			'show_signup_fee'        => true,
			'signup_label'           => 'Signup Fee: ',
			'show_rec_price'         => true,
			'recurring_label'        => 'Recurring Total: ',
		] );

		$defaults['product-quantity'] = array_merge( $common, [
			'classWrap'   => 'wp-block-wrap bwf-qty-wrap wfocu-wrap',
			'content'     => 'Select Quantity',
			'layoutStyle' => 'column',
		] );

		$defaults['product-title'] = array_merge( $common, [
			'classWrap' => 'wfocu-wrap wp-block-wrap',
			'content'   => 'Select Quantity',
			'htmlTag'   => 'p',
		] );

		$defaults['product-description'] = array_merge( $common, [
			'classWrap' => 'wfocu-wrap wp-block-wrap',
			'htmlTag'   => 'p',
		] );

		$defaults['variation-selector'] = array_merge( $common, [
			'classWrap'   => 'wfocu-wrap wp-block-wrap wfocu-variation-selector',
			'layoutStyle' => 'column',
		] );

		$defaults['product-images'] = array_merge( $common, [
			'classWrap'    => 'wfocu-wrap wp-block-wrap wfocu-variation-selector',
			'enableSlider' => true,
		] );

		return $defaults;
	}
}