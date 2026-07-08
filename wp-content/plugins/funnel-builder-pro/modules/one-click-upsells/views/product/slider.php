<?php

$gallery      = $data['gallery'];
$hash_key     = $data['key'];
$wfocu_title  = $data['title'];
$style        = $data['style'];

$gallery_img = array();
foreach ( $gallery as $gallerys ) {
	if ( isset( $gallerys['gallery'] ) && (int) $gallerys['gallery'] > 0 ) {
		$gallery_id = (int) $gallerys['gallery'];
		$full_img   = wp_get_attachment_image_src( $gallery_id, 'full' );

		if ( empty( $full_img ) ) {
			continue;
		}

		$thumb_img = wp_get_attachment_image_src( $gallery_id, 'thumbnail' );
		$gallery_img[] = array(
			'id'        => $gallery_id,
			'src'       => $full_img[0],
			'thumb_src' => $thumb_img[0],
		);
	}
}

$carousal_class                  = '';
$thumbnail_slider_before_element = '';
$thumbnail_slider_after_element  = '';

$flickity_data = array(
	'cellAlign'       => 'center',
	'wrapAround'      => true,
	'autoPlay'        => false,
	'prevNextButtons' => true,
	'adaptiveHeight'  => true,
	'imagesLoaded'    => true,
	'lazyLoad'        => 1,
	'dragThreshold'   => 15,
	'pageDots'        => false,
	'rightToLeft'     => false,
);
if ( 3 === absint($style) || 6 === absint($style) ) {
	$flickity_data['dragThreshold']   = 10;
	$flickity_data['percentPosition'] = true;
	unset( $flickity_data['rightToLeft'] );

	$carousal_class = 'wfocu-vertical-left';

	$thumbnail_slider_before_element = '<div class="wfocu-vertical-thumbnails">';
	$thumbnail_slider_after_element  = '</div>';
} elseif ( 4 === absint($style) ) {
	$flickity_data['dragThreshold']   = 10;
	$flickity_data['percentPosition'] = true;
	unset( $flickity_data['rightToLeft'] );

	$carousal_class = 'wfocu-vertical-right';

	$thumbnail_slider_before_element = '<div class="wfocu-vertical-thumbnails">';
	$thumbnail_slider_after_element  = '</div>';
}
$flickity_data = wp_json_encode( $flickity_data );

$flickity_thumb_data = array(
	'asNavFor'     => '.wfocu-slider-style' . $style . '-' . $hash_key,
	'contain'      => true,
	'pageDots'     => false,
	'imagesLoaded' => true,
);
if ( 3 === absint($style) || 4 === absint($style) ) {
	$flickity_thumb_data['cellAlign']       = 'left';
	$flickity_thumb_data['wrapAround']      = false;
	$flickity_thumb_data['autoPlay']        = false;
	$flickity_thumb_data['prevNextButtons'] = false;
	$flickity_thumb_data['percentPosition'] = true;
	$flickity_thumb_data['contain']         = false;
}
$flickity_thumb_data = wp_json_encode( $flickity_thumb_data );


if ( is_array( $gallery_img ) && count( $gallery_img ) > 1 ) {

    $unique_gallery = array();
    $new_gallery    = array();
    foreach ( $gallery_img as $v ) {
        if ( ! in_array( $v['id'], $unique_gallery, true ) ) {
            $unique_gallery[] = $v['id'];
            $new_gallery[]    = $v;
        }
    }
    $gallery_img = $new_gallery;
	?>
	<div class="wfocu-product-gallery ">
		<div class="wfocu-product-carousel wfocu-product-gallery-slider wfocu-slider-unique-<?php echo esc_attr( $hash_key ); ?> wfocu-slider-style<?php echo esc_attr( $style . '-' . $hash_key ); ?> <?php echo esc_attr( $carousal_class ); ?>" data-flickity='<?php echo $flickity_data; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode result in single-quoted attr ?>' data-gallery="<?php echo htmlspecialchars( wp_json_encode( $gallery_img ) ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- htmlspecialchars applied ?>">
			<?php


			foreach ( $gallery_img as $item ) {

				if ( empty( $item['src'] ) ) {
					continue;
				}
				?>
				<div class="wfocu-carousel-cell">
					<a>
						<img class="skip-lazy" data-id="<?php echo absint( $item['id'] ); ?>" src="<?php echo esc_url( $item['src'] ); ?>" alt="<?php echo esc_attr( $wfocu_title ); ?>" title="<?php echo esc_attr( $wfocu_title ); ?>"/>
					</a>
				</div>
				<?php
			}
			?>
		</div>
	</div>
	<?php echo $thumbnail_slider_before_element; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded HTML string ?>
	<div class="wfocu-product-carousel-nav wfocu-product-thumbnails" data-flickity='<?php echo $flickity_thumb_data; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode result in single-quoted attr ?>'>
		<?php
		$h = 1;
		foreach ( $gallery_img as $item ) {
			$thumb_class = ( absint($h) === 1 ) ? ' is-nav-selected' : '';
			?>
			<div class="wfocu-thumb-col<?php echo esc_attr( $thumb_class ); ?>">
				<a><img class="skip-lazy" data-id="<?php echo absint( $item['id'] ); ?>" src="<?php echo esc_url( $item['thumb_src'] ); ?>" alt="<?php echo esc_attr( $wfocu_title ); ?>" title="<?php echo esc_attr( $wfocu_title ); ?>"/></a>
			</div>
			<?php
			$h ++;
		}
		?>
	</div>
	<?php echo $thumbnail_slider_after_element; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded HTML string ?>
	<?php
} elseif ( is_array( $gallery_img ) && count( $gallery_img ) === 1 ) {
	?>
	<div class="wfocu-product-gallery ">
		<div class="wfocu-product-carousel wfocu-product-image-single <?php echo esc_attr( $carousal_class ); ?>">
			<?php
			foreach ( $gallery_img as $item ) {
				if ( empty( $item['src'] ) ) {
					continue;
				}
				?>
				<div class="wfocu-carousel-cell">
					<a>
						<img class="skip-lazy" data-id="<?php echo absint( $item['id'] ); ?>" src="<?php echo esc_url( $item['src'] ); ?>" alt="<?php echo esc_attr( $wfocu_title ); ?>" title="<?php echo esc_attr( $wfocu_title ); ?>"/>
					</a>
				</div>
				<?php
			}
			?>
		</div>
	</div>
	<?php
}
?>