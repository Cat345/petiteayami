<?php
/**
 * Group_Thumb — one thumbnail that stands for a group of products.
 *
 * A grouped cart line has no image of its own, so the products under it are
 * composed into a single cached PNG: up to four tiles, or three tiles and a
 * "+N" tile once more than four distinct products are in the group.
 */
namespace WFFN\Bundles\Includes\Frontend;

use WFFN\Bundles\Includes\Helpers\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Group_Thumb {

	/** Folder under the uploads directory that holds the composed images. */
	const DIR = 'wffn-bundles-collage';

	/** Edge length of the square image, in pixels. */
	const SIZE = 300;

	/** Version salt for the file name; bump it when the drawing changes. */
	const VERSION = 1;

	/**
	 * The image ids that picture a set of products, one per distinct product.
	 *
	 * @param int[] $product_ids Product or variation ids, in display order.
	 * @return int[]
	 */
	public static function image_ids( array $product_ids ) {
		$ids = array();
		foreach ( $product_ids as $pid ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $pid ) ) : null;
			if ( ! $product ) {
				continue;
			}
			$img = (int) $product->get_image_id();
			if ( ! $img && $product->get_parent_id() ) {
				$parent = wc_get_product( $product->get_parent_id() );
				$img    = $parent ? (int) $parent->get_image_id() : 0;
			}
			if ( $img ) {
				$ids[] = $img;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * The URL of the composed image, or '' when none can be made.
	 *
	 * An empty string sends the caller back to its own fallback, so a server
	 * without GD still shows an image.
	 *
	 * @param int[] $image_ids Attachment ids, one per distinct product.
	 * @return string
	 */
	public static function url( array $image_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		if ( empty( $ids ) ) {
			return '';
		}
		if ( 1 === count( $ids ) ) {
			return (string) wp_get_attachment_image_url( $ids[0], 'woocommerce_thumbnail' );
		}
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$upload = wp_get_upload_dir();
		$name   = md5( implode( ',', $ids ) . '|' . self::SIZE . '|' . self::VERSION ) . '.png';
		$dir    = trailingslashit( $upload['basedir'] ) . self::DIR;
		$file   = $dir . '/' . $name;
		$url    = trailingslashit( $upload['baseurl'] ) . self::DIR . '/' . $name;

		if ( file_exists( $file ) ) {
			return $url;
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		return self::render( $ids, $file ) ? $url : '';
	}

	/**
	 * Draw the tiles and write the PNG.
	 *
	 * @param int[]  $ids  Distinct attachment ids.
	 * @param string $file Destination path.
	 * @return bool
	 */
	private static function render( array $ids, $file ) {
		$size  = self::SIZE;
		$half  = (int) ( $size / 2 );
		$total = count( $ids );
		$more  = $total > 4 ? $total - 3 : 0;
		$tiles = $more ? array_slice( $ids, 0, 3 ) : $ids;
		$cells = 2 === $total
			? array( array( 0, 0, $half, $size ), array( $half, 0, $half, $size ) )
			: array( array( 0, 0, $half, $half ), array( $half, 0, $half, $half ), array( 0, $half, $half, $half ), array( $half, $half, $half, $half ) );

		$canvas = imagecreatetruecolor( $size, $size );
		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 241, 243, 245 ) );

		foreach ( $tiles as $i => $id ) {
			$path   = get_attached_file( $id );
			$bytes  = $path ? Files::read( $path ) : false;
			$source = false !== $bytes ? imagecreatefromstring( $bytes ) : false;
			if ( ! $source ) {
				continue;
			}
			self::paste_cover( $canvas, $source, $cells[ $i ] );
			// Dropping the last reference frees the image on PHP 7.4 and 8 alike.
			unset( $source );
		}
		if ( $more ) {
			self::draw_more( $canvas, $cells[3], '+' . $more );
		}

		$written = imagepng( $canvas, $file );
		unset( $canvas );
		return (bool) $written;
	}

	/**
	 * Fit a source image into a cell, cropping the overflow from the centre.
	 *
	 * @param \GdImage $canvas Destination.
	 * @param \GdImage $source Source image.
	 * @param int[]    $cell   x, y, width, height.
	 * @return void
	 */
	private static function paste_cover( $canvas, $source, array $cell ) {
		list( $x, $y, $w, $h ) = $cell;
		$sw    = imagesx( $source );
		$sh    = imagesy( $source );
		$scale = max( $w / $sw, $h / $sh );
		$cw    = (int) round( $w / $scale );
		$ch    = (int) round( $h / $scale );
		imagecopyresampled( $canvas, $source, $x, $y, (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $w, $h, $cw, $ch );
	}

	/**
	 * Fill a cell dark and centre a scaled-up label in it.
	 *
	 * @param \GdImage $canvas Destination.
	 * @param int[]    $cell   x, y, width, height.
	 * @param string   $label  Text such as "+3".
	 * @return void
	 */
	private static function draw_more( $canvas, array $cell, $label ) {
		list( $x, $y, $w, $h ) = $cell;
		imagefilledrectangle( $canvas, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocate( $canvas, 30, 42, 53 ) );

		$font = 5;
		$tw   = imagefontwidth( $font ) * strlen( $label );
		$th   = imagefontheight( $font );
		$text = imagecreatetruecolor( $tw, $th );
		imagefill( $text, 0, 0, imagecolorallocate( $text, 30, 42, 53 ) );
		imagestring( $text, $font, 0, 0, $label, imagecolorallocate( $text, 255, 255, 255 ) );

		$scale = max( 1, (int) floor( ( $w * 0.7 ) / $tw ) );
		$dw    = $tw * $scale;
		$dh    = $th * $scale;
		imagecopyresized( $canvas, $text, $x + (int) ( ( $w - $dw ) / 2 ), $y + (int) ( ( $h - $dh ) / 2 ), 0, 0, $dw, $dh, $tw, $th );
		unset( $text );
	}
}
