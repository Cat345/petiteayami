<?php
if ( ! class_exists( 'WFOCU_Syntax_Merge_Tags' ) ) {
	class WFOCU_Syntax_Merge_Tags {

		public static $threshold_to_date = 30;

		protected static $_data_shortcode = array();

		/**
		 * Maybe try and parse content to found the wfocu merge tags
		 * And converts them to the standard wp shortcode way
		 * So that it can be used as do_shortcode in future
		 *
		 * @param string $content
		 *
		 * @return mixed|string
		 */
		public static function maybe_parse_merge_tags( $content = '', $helper_data = false ) {
			$get_all = self::get_all_tags();

			//iterating over all the merge tags
			if ( $get_all && is_array( $get_all ) && count( $get_all ) > 0 ) {
				foreach ( $get_all as $tag ) {
					$matches = array();
					$re      = sprintf( '/\{{%s(.*?)\}}/', $tag );
					$str     = $content;

					//trying to find match w.r.t current tag
					preg_match_all( $re, $str, $matches );

					//if match found
					if ( $matches && is_array( $matches ) && count( $matches ) > 0 ) {

						if ( ! isset( $matches[0] ) ) {
							return;
						}

						//iterate over the found matches
						foreach ( $matches[0] as $exact_match ) {

							//preserve old match
							$old_match = $exact_match;

							$extra_attributes = '';
							if ( $helper_data !== false ) {
								$extra_attributes = " helper_data='" . serialize( $helper_data ) . "'"; //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
							}

							//replace the current tag with the square brackets [shortcode compatible]
							$exact_match = str_replace( '{{' . $tag, '[wfocu_' . $tag . $extra_attributes, $exact_match );

							$exact_match = str_replace( '}}', ']', $exact_match );

							$content = str_replace( $old_match, $exact_match, $content );
						}
					}
				}
			}

			return $content;
		}

		public static function get_all_tags() {
			$tags = array();

			return $tags;

		}


		public static function highlight( $attr ) {
			$attr = shortcode_atts( array(
				'color'   => '#777777',
				'text'    => '',
				'classes' => '',
			), $attr );

			if ( $attr && ! empty( $attr['text'] ) ) {

				return sprintf( '<span class="wfocu_highlight %s" style="color: %s">%s</span>', $attr['classes'], $attr['color'], esc_html( $attr['text'] ) );
			}

			return '';
		}


	}

}