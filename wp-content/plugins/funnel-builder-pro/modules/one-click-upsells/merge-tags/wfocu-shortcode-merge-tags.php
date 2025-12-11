<?php
if ( ! class_exists( 'WFOCU_ShortCode_Merge_Tags' ) ) {
	class WFOCU_ShortCode_Merge_Tags {

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
			$tags = array(
				'current_time',
				'current_date',
				'current_day',
				'today',
				'countdown_timer',
				'order_meta',
				'product_offer_price',
				'product_sale_price',
				'product_regular_price',
				'product_price_full',
				'product_regular_price_raw',
				'product_offer_price_raw',
				'product_sale_price_raw',
				'product_save_value',
				'product_save_percentage',
				'product_savings',
				'product_single_unit_price',
				'product_original_sale_price',
				'product_original_sale_price_raw'
			);

			return apply_filters( 'wfocu_shortcode_merge_tags', $tags );

		}

		public static function init() {

			add_shortcode( 'wfocu_current_time', array( __CLASS__, 'process_time' ) );
			add_shortcode( 'wfocu_current_date', array( __CLASS__, 'process_date' ) );
			add_shortcode( 'wfocu_today', array( __CLASS__, 'process_today' ) );
			add_shortcode( 'wfocu_current_day', array( __CLASS__, 'process_day' ) );
			add_shortcode( 'wfocu_countdown_timer', array( __CLASS__, 'countdown_timer' ) );
			add_shortcode( 'wfocu_order_meta', array( __CLASS__, 'wfocu_order_meta' ) );
			add_shortcode( 'wfocu_product_offer_price', array( __CLASS__, 'product_price' ) );
			add_shortcode( 'wfocu_product_sale_price', array( __CLASS__, 'product_price' ) );
			add_shortcode( 'wfocu_product_regular_price', array( __CLASS__, 'product_price_regular' ) );
			add_shortcode( 'wfocu_product_price_full', array( __CLASS__, 'product_price_full' ) );
			add_shortcode( 'wfocu_product_regular_price_raw', array( __CLASS__, 'product_price_regular_raw' ) );
			add_shortcode( 'wfocu_product_offer_price_raw', array( __CLASS__, 'product_price_raw' ) );
			add_shortcode( 'wfocu_product_sale_price_raw', array( __CLASS__, 'product_price_raw' ) );
			add_shortcode( 'wfocu_product_save_value', array( __CLASS__, 'product_save_value' ) );
			add_shortcode( 'wfocu_product_save_percentage', array( __CLASS__, 'product_save_percentage' ) );
			add_shortcode( 'wfocu_product_savings', array( __CLASS__, 'product_save_combined' ) );
			add_shortcode( 'wfocu_product_single_unit_price', array( __CLASS__, 'product_single_unit_price' ) );
			add_shortcode( 'wfocu_product_original_sale_price', array( __CLASS__, 'product_original_sale_price' ) );
			add_shortcode( 'wfocu_product_original_sale_price_raw', array( __CLASS__, 'product_original_sale_price_raw' ) );

		}

		public static function process_date( $shortcode_attrs ) {
			$default_f = WFOCU_Common::wfocu_get_date_format();
			$atts      = shortcode_atts( array(
				'format'        => $default_f, //has to be user friendly , user will not understand 12:45 PM (g:i A) (https://codex.wordpress.org/Formatting_Date_and_Time)
				'adjustment'    => '',
				'cutoff'        => '',
				'exclude_days'  => '',
				'exclude_dates' => '',
			), $shortcode_attrs );

			$date_obj = new DateTime();
			$date_obj->setTimestamp( current_time( 'timestamp' ) );
			/** cutoff functionality starts */
			if ( $atts['cutoff'] !== '' ) {
				$date_obj_cutoff = new DateTime();
				$date_obj->setTimestamp( current_time( 'timestamp' ) );
				$parsed_date   = date_parse( $atts['cutoff'] );
				$date_defaults = array(
					'year'   => $date_obj_cutoff->format( 'Y' ),
					'month'  => $date_obj_cutoff->format( 'm' ),
					'day'    => $date_obj_cutoff->format( 'd' ),
					'hour'   => $date_obj_cutoff->format( 'H' ),
					'minute' => $date_obj_cutoff->format( 'i' ),
					'second' => '00',
				);
				foreach ( $parsed_date as $attrs => &$date_elements ) {
					if ( $date_elements === false && isset( $date_defaults[ $attrs ] ) ) {
						$parsed_date[ $attrs ] = $date_defaults[ $attrs ];
					}
				}
				$parsed_date = wp_parse_args( $parsed_date, $date_defaults );
				$date_obj_cutoff->setDate( $parsed_date['year'], $parsed_date['month'], $parsed_date['day'] );
				$date_obj_cutoff->setTime( $parsed_date['hour'], $parsed_date['minute'], $parsed_date['second'] );
				if ( $date_obj->getTimestamp() > $date_obj_cutoff->getTimestamp() ) {
					$date_obj->modify( '+1 days' );
				}
			}

			/**
			 * Pre check
			 */
			$itr = 0;
			while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_dates'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {
				$date_obj->modify( '+1 day' );
				$itr ++;
			}

			/** Cut-Off functionality Ends */
			if ( $atts['adjustment'] !== '' ) {
				$date_obj->modify( trim( $atts['adjustment'] ) );
			}

			/**
			 * After check
			 */
			$itr = 0;
			while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_dates'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {

				$date_obj->modify( '+1 day' );
				$itr ++;
			}

			return date_i18n( $atts['format'], $date_obj->getTimestamp() );
		}

		protected static function is_not_excluded_date( $date, $exclusions ) {
			$exclusions         = str_replace( ' ', '', $exclusions );
			$explode_exclusions = explode( ',', $exclusions );
			$explode_exclusions = apply_filters( 'wfocu_merge_tags_date_exclude_dates', $explode_exclusions, $date );

			if ( in_array( strtolower( $date->format( 'Y-m-d' ) ), $explode_exclusions, true ) ) {
				return false;
			}

			return true;
		}

		protected static function is_not_excluded_day( $date, $exclusions ) {
			$exclusions         = str_replace( ' ', '', $exclusions );
			$explode_exclusions = explode( ',', $exclusions );
			$explode_exclusions = apply_filters( 'wfocu_merge_tags_date_exclude_days', $explode_exclusions, $date );
			if ( in_array( strtolower( $date->format( 'l' ) ), $explode_exclusions, true ) ) {

				return false;
			}

			return true;
		}

		public static function process_day( $shortcode_attrs ) {
			$atts     = shortcode_atts( array(
				'adjustment'    => '',
				'cutoff'        => '',
				'exclude_days'  => '',
				'exclude_dates' => '',
			), $shortcode_attrs );
			$date_obj = new DateTime();
			$date_obj->setTimestamp( current_time( 'timestamp' ) );

			/** cutoff functionality starts */
			if ( $atts['cutoff'] !== '' ) {
				$date_obj_cutoff = new DateTime();
				$date_obj_cutoff->setTimestamp( current_time( 'timestamp' ) );
				$parsed_date   = date_parse( $atts['cutoff'] );
				$date_defaults = array(
					'year'   => $date_obj_cutoff->format( 'Y' ),
					'month'  => $date_obj_cutoff->format( 'm' ),
					'day'    => $date_obj_cutoff->format( 'd' ),
					'hour'   => $date_obj_cutoff->format( 'H' ),
					'minute' => $date_obj_cutoff->format( 'i' ),
					'second' => '00',
				);
				foreach ( $parsed_date as $attrs => &$date_elements ) {
					if ( $date_elements === false && isset( $date_defaults[ $attrs ] ) ) {
						$parsed_date[ $attrs ] = $date_defaults[ $attrs ];
					}
				}
				$parsed_date = wp_parse_args( $parsed_date, $date_defaults );

				$date_obj_cutoff->setDate( $parsed_date['year'], $parsed_date['month'], $parsed_date['day'] );
				$date_obj_cutoff->setTime( $parsed_date['hour'], $parsed_date['minute'], $parsed_date['second'] );

				if ( $date_obj->getTimestamp() > $date_obj_cutoff->getTimestamp() ) {
					$date_obj->modify( '+1 days' );
				}
			}

			//pre check
			$itr = 0;
			/**
			 * iterating all over the recursive check for a valid date
			 */
			while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {
				$date_obj->modify( '+1 day' );
				$itr ++;
			}
			/** Cut-Off functionality Ends */
			if ( $atts['adjustment'] !== '' ) {
				$date_obj->modify( $atts['adjustment'] );
			}
			$itr = 0;
			/**
			 * iterating all over the recursive check for a valid date
			 */
			while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {
				$date_obj->modify( '+1 day' );
				$itr ++;
			}

			return date_i18n( 'l', $date_obj->getTimestamp() );
		}

		public static function process_today( $shortcode_attrs ) {
			$atts     = shortcode_atts( array(
				'cutoff'        => '',
				'exclude_days'  => '',
				'exclude_dates' => '',
			), $shortcode_attrs );
			$date_obj = new DateTime();
			$date_obj->setTimestamp( current_time( 'timestamp' ) );

			$date_obj_cutoff = new DateTime();
			$date_obj_cutoff->setTimestamp( current_time( 'timestamp' ) );
			/** cutoff functionlity starts */
			if ( $atts['cutoff'] !== '' ) {
				$parsed_date   = date_parse( $atts['cutoff'] );
				$date_defaults = array(
					'year'   => $date_obj_cutoff->format( 'Y' ),
					'month'  => $date_obj_cutoff->format( 'm' ),
					'day'    => $date_obj_cutoff->format( 'd' ),
					'hour'   => $date_obj_cutoff->format( 'H' ),
					'minute' => $date_obj_cutoff->format( 'i' ),
					'second' => '00',
				);
				foreach ( $parsed_date as $attrs => &$date_elements ) {
					if ( $date_elements === false && isset( $date_defaults[ $attrs ] ) ) {
						$parsed_date[ $attrs ] = $date_defaults[ $attrs ];
					}
				}
				$parsed_date = wp_parse_args( $parsed_date, $date_defaults );
				$date_obj_cutoff->setTimezone( new DateTimeZone( WFOCU_Common::wc_timezone_string() ) );
				$date_obj_cutoff->setDate( $parsed_date['year'], $parsed_date['month'], $parsed_date['day'] );
				$date_obj_cutoff->setTime( $parsed_date['hour'], $parsed_date['minute'], $parsed_date['second'] );
			}

			if ( $date_obj->getTimestamp() > $date_obj_cutoff->getTimestamp() ) {

				$date_obj->modify( '+1 days' );
				$is_excluded = false;

				/**
				 * iterating all over the recursive check for a valid date
				 */
				$itr = 0;
				while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {
					;
					$date_obj->modify( '+1 day' );
					$itr ++;
					$is_excluded = true;
				}

				if ( $is_excluded ) {
					return date_i18n( 'l', $date_obj->getTimestamp() );
				} else {
					return __( 'tomorrow', 'woofunnels-upstroke-one-click-upsell' );
				}
			} else {
				$is_excluded = false;
				/**
				 * iterating all over the recursive check for a valid date
				 */
				$itr = 0;
				while ( $itr < self::$threshold_to_date && ( ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_date( $date_obj, $atts['exclude_dates'] ) === false ) ) || ( ( $atts['exclude_days'] !== '' ) && ( self::is_not_excluded_day( $date_obj, $atts['exclude_days'] ) === false ) ) ) ) {
					$date_obj->modify( '+1 day' );
					$is_excluded = true;
					$itr ++;
				}
				if ( $is_excluded ) {
					return date_i18n( 'l', $date_obj->getTimestamp() );
				} else {
					return __( 'today', 'woofunnels-upstroke-one-click-upsell' );
				}
			}
		}

		public static function process_time( $shortcode_attrs ) {
			$default_f = WFOCU_Common::wfocu_get_time_format();
			$atts      = shortcode_atts( array(
				'format'     => $default_f, //has to be user friendly , user will not understand 12:45 PM (g:i A) (https://codex.wordpress.org/Formatting_Date_and_Time)
				'adjustment' => '',
			), $shortcode_attrs );

			$date_obj = new DateTime();
			$date_obj->setTimestamp( current_time( 'timestamp' ) );
			if ( $atts['adjustment'] !== '' ) {
				$date_obj->modify( $atts['adjustment'] );
			}

			return date_i18n( $atts['format'], $date_obj->getTimestamp() );
		}

		/**
		 * Countdown timer merge tag
		 *
		 * @param $shortcode_attrs
		 *
		 * @return string
		 */
		public static function countdown_timer( $shortcode_attrs ) {
			$atts = shortcode_atts( array(
				'style' => '',
				'align' => 'left',
			), $shortcode_attrs );

			$template_ins = WFOCU_Core()->template_loader->get_template_ins();

			$template_ins->countdown_timer = $atts['style'];

			ob_start();
			echo '<div class="wfocu-timer-shortcode" align="' . esc_attr( $atts["align"] ) . '">';
			WFOCU_Core()->template_loader->get_template_part( 'countdown-timer' );
			echo '</div>';
			echo ( 'right' === $atts['align'] ) ? '<div class="wfocu-clearfix"></div>' : '';

			$output = ob_get_clean();

			$template_ins->countdown_timer = '';

			return $output;
		}


		public static function wfocu_order_meta( $shortcode_attrs ) {
			$atts = shortcode_atts( array(
				'key'   => '',
				'label' => '',
			), $shortcode_attrs );

			if ( $atts['key'] === '' ) {
				return __return_empty_string();
			}

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return __return_empty_string();
			}

			$get_key_value = WFOCU_WC_Compatibility::get_order_data( $order, $atts['key'] );

			if ( $get_key_value === '' || $get_key_value === false || $get_key_value === null ) {
				return __return_empty_string();
			}

			return sprintf( '%s%s', $atts['label'], $get_key_value );

		}

		public static function product_price( $attr, $raw = false ) {

			$data                = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr                = shortcode_atts( array(
				'key'  => 1,
				'info' => 'no',
			), $attr );
			$price               = 0;
			$shipping_difference = 0;
			if ( ! isset( $data->products ) ) {
				$price = ( true === $raw ) ? $price : wc_price( $price );

				return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}

			if ( ! empty( $attr['key'] ) ) {

				/**
				 * Shipping
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && isset( $data->products->{$attr['key']}->shipping ) && is_array( $data->products->{$attr['key']}->shipping ) ) {
					if ( $data->products->{$attr['key']}->shipping['shipping'] && count( $data->products->{$attr['key']}->shipping['shipping'] ) > 0 ) {
						$current      = current( $data->products->{$attr['key']}->shipping['shipping'] );
						$current_cost = (float) $current['cost'] + (float) $current['shipping_tax'];
						$prev_cost    = $data->products->{$attr['key']}->shipping['shipping_prev']['cost'] + $data->products->{$attr['key']}->shipping['shipping_prev']['tax'];

						$shipping_difference = $current_cost - $prev_cost;
					}
				}


				/**
				 * If variable product OR Variable Subscriptions
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && $data->products->{$attr['key']}->data instanceof WC_Product && ( 'variable' === $data->products->{$attr['key']}->data->get_type() || 'variable-subscription' === $data->products->{$attr['key']}->data->get_type() ) && isset( $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['price_excl_tax_raw'] ) ) {


					$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );
					if ( true === $is_show_tax ) {
						$variable_price = $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['price_incl_tax_raw'];
					} else {
						$variable_price = $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['price_excl_tax_raw'];

					}

					$price = $variable_price + $shipping_difference;


					if ( true === $raw ) {
						return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
					}

					/**
					 * If variable Subscriptions
					 */
					if ( $data->products->{$attr['key']}->data->is_type( 'variable-subscription' ) ) {
						if ( isset( $attr['info'] ) && 'yes' === $attr['info'] ) {
							$get_default_variation_object = $data->products->{$attr['key']}->variations_data['variation_objects'][ $data->products->{$attr['key']}->default_variation ];

							$free_trial = WC_Subscriptions_Product::get_trial_length( $get_default_variation_object );

							if ( empty( $free_trial ) && false === $data->settings->subscription_discount ) {

								$price = wc_price( $price );

							} else {
								$price = WC_Subscriptions_Product::get_price_string( $get_default_variation_object, array( 'price' => wc_price( $price ), 'sign_up_fee' => false ) );

							}

						} else {

							$get_default_variation_object = $data->products->{$attr['key']}->variations_data['variation_objects'][ $data->products->{$attr['key']}->default_variation ];

							$free_trial = WC_Subscriptions_Product::get_trial_length( $get_default_variation_object );

							if ( ! empty( $free_trial ) ) {
								$price = wc_price( 0 );

							} else {
								$price = wc_price( $price );
							}


						}
					} else {


						$price = wc_price( $price );

					}

					$price = sprintf( '<span class="wfocu_variable_price_sale" data-key="%s" data-info="%s">%s</span>', $attr['key'], $attr['info'], $price );

					return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
				}

				//if variable
				if ( isset( $data->products->{$attr['key']}->price_raw ) && false === $data->products->{$attr['key']}->data->is_type( 'variable' ) ) {
					$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );
					if ( true === $is_show_tax ) {
						$price = $data->products->{$attr['key']}->price_raw;
					} else {
						$price = $data->products->{$attr['key']}->sale_price_excl_tax;
					}


					$price = $price + $shipping_difference;
				}


			}
			if ( true === $raw ) {
				return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
			}

			/**
			 * is subscription
			 */
			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && ( $data->products->{$attr['key']}->data->is_type( 'subscription' ) || $data->products->{$attr['key']}->data->is_type( 'subscription_variation' ) ) ) {

				if ( isset( $attr['info'] ) && 'yes' === $attr['info'] ) {


					$free_trial = WC_Subscriptions_Product::get_trial_length( $data->products->{$attr['key']}->data );

					if ( empty( $free_trial ) && false === $data->settings->subscription_discount ) {
						$price = wc_price( $price );

					} else {
						$price = WC_Subscriptions_Product::get_price_string( $data->products->{$attr['key']}->data, array( 'price' => wc_price( $price ), 'sign_up_fee' => false ) );

					}

					return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
				} else {


					$free_trial = WC_Subscriptions_Product::get_trial_length( $data->products->{$attr['key']}->data );

					if ( ! empty( $free_trial ) ) {

						$price = wc_price( 0 );

					} else {
						$price = wc_price( $price );
					}

					return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );
				}


			} else {
				$price = wc_price( $price );

				return apply_filters( 'wfocu_product_offer_price', $price, $data, $attr, $raw );

			}

		}

		public static function product_price_regular( $attr, $raw = false ) {

			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr = shortcode_atts( array(
				'key'  => 1,
				'info' => 'no',
				'tag'  => 'yes',
			), $attr );

			$price               = 0;
			$shipping_difference = 0;

			if ( ! isset( $data->products ) ) {
				if ( true === $raw ) {
					return $price;
				}

				return wc_price( $price );
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}
			if ( ! empty( $attr['key'] ) ) {

				/**
				 * Shipping
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && isset( $data->products->{$attr['key']}->shipping ) && is_array( $data->products->{$attr['key']}->shipping ) ) {
					if ( $data->products->{$attr['key']}->shipping['shipping'] && count( $data->products->{$attr['key']}->shipping['shipping'] ) > 0 ) {
						$current      = current( $data->products->{$attr['key']}->shipping['shipping'] );
						$current_cost = (float) $current['cost'] + (float) $current['shipping_tax'];
						$prev_cost    = $data->products->{$attr['key']}->shipping['shipping_prev']['cost'] + $data->products->{$attr['key']}->shipping['shipping_prev']['tax'];

						$shipping_difference = $current_cost - $prev_cost;
					}
				}

				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && $data->products->{$attr['key']}->data instanceof WC_Product && ( 'variable' === $data->products->{$attr['key']}->data->get_type() || 'variable-subscription' === $data->products->{$attr['key']}->data->get_type() ) && isset( $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['regular_price_incl_tax_raw'] ) ) {


					$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );
					if ( true === $is_show_tax ) {
						$variable_price = $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['regular_price_incl_tax_raw'];
					} else {
						$variable_price = $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['regular_price_excl_tax'];
					}
					$price = $variable_price + $shipping_difference;

					if ( true === $raw ) {
						return $price;

					}
					if ( $data->products->{$attr['key']}->data->is_type( 'variable-subscription' ) && isset( $attr['info'] ) && 'yes' === $attr['info'] ) {
						$get_default_variation_object = $data->products->{$attr['key']}->variations_data['variation_objects'][ $data->products->{$attr['key']}->default_variation ];

						$price = WC_Subscriptions_Product::get_price_string( $get_default_variation_object, array( 'price' => wc_price( $price ), 'signup_fee' => false ) );

					} else {
						$price = wc_price( $price );

					}
					$tag_class = '';
					if ( $attr['tag'] === 'yes' ) {
						$tag_class = 'wfocu_tags';
					}

					return sprintf( '<span class="wfocu_variable_price_regular %s" data-key="%s">%s</span>', $tag_class, $attr['key'], $price );
				}

				if ( isset( $data->products->{$attr['key']} ) && ! $data->products->{$attr['key']}->data->is_type( 'variable' ) ) {
					$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );
					if ( true === $is_show_tax ) {

						$price = $data->products->{$attr['key']}->regular_price;
					} else {
						$price = $data->products->{$attr['key']}->regular_price_excl_tax;
					}

					$price = $price + $shipping_difference;
				}

			}

			if ( true === $raw ) {
				return $price;
			}

			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && $data->products->{$attr['key']}->data->is_type( 'subscription' ) && isset( $attr['info'] ) && 'yes' === $attr['info'] ) {
				$price = WC_Subscriptions_Product::get_price_string( $data->products->{$attr['key']}->data, array( 'price' => wc_price( $price ), 'signup_fee' => false ) );

				return $price;
			} else {
				return wc_price( $price );

			}

		}

		public static function product_price_full( $attr ) {
			$attr          = shortcode_atts( array(
				'key'   => 1,
				'class' => 'wfocu_default_price_full',
			), $attr );
			$regular_price = self::product_price_regular( $attr );
			$sale_price    = self::product_price( $attr );

			$regular_price_raw = self::product_price_regular( $attr, true );
			$_price_raw        = self::product_price( $attr, true );

			$html        = '<div class="' . $attr['class'] . '">';
			$data        = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr['key'] = self::get_possible_key( $attr['key'] );
			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {
				$product = $data->products->{$attr['key']}->data;

				if ( round( $regular_price_raw, 2 ) !== round( $_price_raw, 2 ) ) {
					$html .= '<strike>' . $regular_price . '</strike>' . ' ' . $sale_price;

				} else {

					if ( 'variable' === $product->get_type() ) {
						$html = '<div class="' . $attr['class'] . '">';
						$html .= sprintf( '<strike><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></strike>', $attr['key'] );
						$html .= $sale_price ? '' . $sale_price . '</span>' : '';

					} else {
						$html = $sale_price;

					}
				}
			}
			$html .= "</div>";

			return $html;
		}

		public static function product_price_regular_raw( $attr ) {
			$attr  = shortcode_atts( array(
				'key' => 1,
			), $attr );
			$price = self::product_price_regular( $attr, true );

			return $price;
		}

		public static function product_price_raw( $attr ) {
			$attr  = shortcode_atts( array(
				'key' => 1,
			), $attr );
			$price = self::product_price( $attr, true );

			return $price;
		}

		public static function product_save_value( $attr ) {
			$attr          = shortcode_atts( array(
				'key' => 1,
			), $attr );
			$regular_price = self::product_price_regular( $attr, true );
			$sale_price    = self::product_price( $attr, true );

			if ( 0 === $regular_price ) {
				return '';
			}

			$diff = ( $regular_price - $sale_price );

			return sprintf( '<span class="wfocu_variable_price_save_value" data-key="%s">%s</span>', self::get_possible_key( $attr['key'] ), wc_price( $diff ) );

		}

		public static function product_save_percentage( $attr ) {
			$attr          = shortcode_atts( array(
				'key' => 1,
			), $attr );
			$regular_price = self::product_price_regular( $attr, true );
			$sale_price    = self::product_price( $attr, true );

			if ( 0 === $regular_price ) {
				return '';
			}
			$diff    = ( ( $regular_price - $sale_price ) / $regular_price ) * 100;
			$percent = apply_filters( 'wfocu_tag_' . __FUNCTION__, number_format( $diff, 0 ) );

			return sprintf( '<span class="wfocu_variable_price_save_percentage" data-key="%s">%s</span>', self::get_possible_key( $attr['key'] ), $percent . '%' );

		}

		public static function product_save_combined( $attr ) {
			$attr          = shortcode_atts( array(
				'key' => 1,
			), $attr );
			$regular_price = self::product_price_regular( $attr, true );
			$sale_price    = self::product_price( $attr, true );
			if ( 0 === $regular_price ) {
				return '';
			}
			$diff         = $regular_price - $sale_price;
			$diff_percent = ( $diff / $regular_price ) * 100;
			$diff_percent = apply_filters( 'wfocu_tag_' . __FUNCTION__, number_format( $diff_percent, 0 ) );

			return sprintf( '<span class="wfocu_variable_price_save_percentage_combo" data-key="%s">%s</span>', self::get_possible_key( $attr['key'] ), wc_price( $diff ) . ' (' . $diff_percent . '%)' );

		}

		public static function get_possible_key( $key ) {
			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			if ( empty( $data ) ) {
				return $key;
			}
			if ( ! isset( $data->products->{$key} ) ) {
				$key = WFOCU_Core()->offers->get_product_key_by_index( $key, $data->products );
			}

			return $key;
		}

		public static function product_single_unit_price( $attr ) {
			$attr = shortcode_atts( array(
				'key'  => 1,
				'info' => 'yes',
			), $attr );


			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			if ( ! isset( $data->products ) ) {
				return wc_price( 0 );
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}
			if ( ! empty( $attr['key'] ) ) {
				$sale_price = self::product_price( $attr, true );

				return sprintf( '<span class="wfocu_single_unit_price" data-key="%s">%s</span>', $attr['key'], wc_price( $sale_price / absint( $data->products->{$attr['key']}->quantity ) ) );

			}

			return wc_price( 0 );

		}

		public static function product_original_sale_price( $attr ) {
			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr = shortcode_atts( array(
				'key'  => 1,
				'info' => 'no',
			), $attr );

			$price = 0;
			if ( ! isset( $data->products ) ) {
				return wc_price( $price );
			}
			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}
			if ( ! empty( $attr['key'] ) ) {
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {
					$product_id = $data->products->{$attr['key']}->id;

					if ( abs( $product_id ) > 0 ) {
						$product = wc_get_product( $product_id );
						if ( $product instanceof WC_Product ) {
							$price = wc_price( $product->get_price() );
						}
					}
				}
			}

			return $price;
		}

		public static function product_original_sale_price_raw( $attr ) {
			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr = shortcode_atts( array(
				'key'  => 1,
				'info' => 'no',
			), $attr );

			$price = 0;
			if ( ! isset( $data->products ) ) {
				return $price;
			}
			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}
			if ( ! empty( $attr['key'] ) ) {
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {
					$product_id = $data->products->{$attr['key']}->id;

					if ( abs( $product_id ) > 0 ) {
						$product = wc_get_product( $product_id );
						if ( $product instanceof WC_Product ) {
							$price = $product->get_price();
						}
					}
				}
			}

			return $price;
		}


	}

	WFOCU_ShortCode_Merge_Tags::init();
}