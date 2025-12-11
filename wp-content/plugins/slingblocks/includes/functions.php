<?php


if ( ! function_exists( 'slingblocks_attr' ) ) {
	/**
	 * Build list of attributes into a string and apply contextual filter on string.
	 *
	 * The contextual filter is of the form `slingblocks_attr_{context}_output`.
	 *
	 * @param string $context The context, to build filter name.
	 * @param array $attributes Optional. Extra attributes to merge with defaults.
	 * @param array $settings Optional. Custom data to pass to filter.
	 *
	 * @return string String of HTML attributes and values.
	 * @since 1.2.0
	 *
	 */

	function slingblocks_attr( $context, $attributes = array(), $settings = array() ) {
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

		$output = apply_filters( "slingblocks_attr_{$context}_output", $output, $attributes, $settings, $context );

		return trim( $output );
	}
}

if ( ! function_exists( 'slingblocks_svg_icons' ) ) {
	function slingblocks_svg_icons( $icon = 'addSubmenu' ) {
		switch ( $icon ) {
			case 'plus-circle' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="2 2 26 26" width="20px" height="20px">    <path d="M15,3C8.373,3,3,8.373,3,15c0,6.627,5.373,12,12,12s12-5.373,12-12C27,8.373,21.627,3,15,3z M21,16h-5v5 c0,0.552-0.448,1-1,1s-1-0.448-1-1v-5H9c-0.552,0-1-0.448-1-1s0.448-1,1-1h5V9c0-0.552,0.448-1,1-1s1,0.448,1,1v5h5 c0.552,0,1,0.448,1,1S21.552,16,21,16z"/></svg>' );
				break;
			case 'close-circle' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="2 2 26 26" width="20px" height="20px">    <path d="M15,3C8.373,3,3,8.373,3,15c0,6.627,5.373,12,12,12s12-5.373,12-12C27,8.373,21.627,3,15,3z M19.707,18.293 c0.391,0.391,0.391,1.023,0,1.414C19.512,19.902,19.256,20,19,20s-0.512-0.098-0.707-0.293L15,16.414l-3.293,3.293 C11.512,19.902,11.256,20,11,20s-0.512-0.098-0.707-0.293c-0.391-0.391-0.391-1.023,0-1.414L13.586,15l-3.293-3.293 c-0.391-0.391-0.391-1.023,0-1.414s1.023-0.391,1.414,0L15,13.586l3.293-3.293c0.391-0.391,1.023-0.391,1.414,0s0.391,1.023,0,1.414 L16.414,15L19.707,18.293z"/></svg>' );
				break;
			case 'plus' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path fill-rule="evenodd" d="M 11 2 L 11 11 L 2 11 L 2 13 L 11 13 L 11 22 L 13 22 L 13 13 L 22 13 L 22 11 L 13 11 L 13 2 Z"/></svg>' );
				break;
			case 'minus' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path fill-rule="evenodd" d="M 2 11 L 2 13 L 22 13 L 22 11 Z"/></svg>' );
				break;
			case 'collapse-arrow' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path d="M 12 6.65625 L 11.34375 7.25 L 1.34375 16.25 L 2.65625 17.75 L 12 9.34375 L 21.34375 17.75 L 22.65625 16.25 L 12.65625 7.25 Z"/></svg>' );
				break;
			case 'expand-arrow' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path d="M 2.65625 6.25 L 1.34375 7.75 L 11.34375 16.75 L 12 17.34375 L 12.65625 16.75 L 22.65625 7.75 L 21.34375 6.25 L 12 14.65625 Z"/></svg>' );
				break;
			case 'up-arrow' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path d="M12 18.071L6.429 12.5 4.929 14 12 21.071 19.071 14 17.571 12.5z"/><path d="M4 10H20V12H4z" transform="rotate(-90 12 11)"/></svg>' );
				break;
			case 'down-arrow' :
				return ( '<svg class="slb-svg-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="20px" height="20px"><path d="M12 5.929L6.429 11.5 4.929 10 12 2.929 19.071 10 17.571 11.5z"/><path d="M4 12H20V14H4z" transform="rotate(90 12 13)"/></svg>' );
				break;
			default:
				'<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M2 12c0 3.6 2.4 5.5 6 5.5h.5V19l3-2.5-3-2.5v2H8c-2.5 0-4.5-1.5-4.5-4s2-4.5 4.5-4.5h3.5V6H8c-3.6 0-6 2.4-6 6zm19.5-1h-8v1.5h8V11zm0 5h-8v1.5h8V16zm0-10h-8v1.5h8V6z" />
                </svg>';
		}

	}
}

if ( ! function_exists( 'slingblocks_sanitize_html_output' ) ) {
	function slingblocks_sanitize_html_output( $buffer ) {

		$search = array(
			'/\>[^\S ]+/s',     // strip whitespaces after tags, except space
			'/[^\S ]+\</s',     // strip whitespaces before tags, except space
			'/(\s)+/s',         // shorten multiple whitespace sequences
			'/<!--(.|\s)*?-->/' // Remove HTML comments
		);

		$replace = array(
			'>',
			'<',
			'\\1',
			''
		);

		$buffer = preg_replace( $search, $replace, $buffer );

		return $buffer;
	}
}
