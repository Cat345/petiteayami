<?php
/**
 * Pointing arrow shown next to the order-bump checkbox.
 *
 * IMPORTANT: this SVG must NOT be passed through wp_kses_post(). WordPress core's
 * allowed post tags contain no <svg>/<path>, so kses stripped the whole icon and this
 * span rendered empty — which made the "Enable Arrow" setting look broken on every
 * skin, both on the live checkout and in the admin preview. We escape with an explicit
 * SVG-aware allow-list instead.
 *
 * width/height are also set on the element itself: the stylesheet only gives the wrapper
 * a max-width/max-height and the svg width:100%, which has no intrinsic size to resolve
 * against (and that rule only applies inside `#wfob_wrap .wfob_wrapper`, so the admin
 * preview got no sizing at all).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$svg_img = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M14.1724 9H3V14.5H14.1724V18.6552L21 11.8276L14.1724 5V9Z" fill="#D80027"/>
	</svg>';

$wfob_pointer_allowed_html = array(
	'svg'  => array(
		'width'   => true,
		'height'  => true,
		'viewbox' => true,
		'fill'    => true,
		'xmlns'   => true,
		'class'   => true,
		'style'   => true,
	),
	'path' => array(
		'd'         => true,
		'fill'      => true,
		'fill-rule' => true,
		'clip-rule' => true,
	),
);

echo '<span class="wfob_blink_img_wrap">';
echo wp_kses( $svg_img, $wfob_pointer_allowed_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above with an SVG-aware allow-list.
echo '</span>';
