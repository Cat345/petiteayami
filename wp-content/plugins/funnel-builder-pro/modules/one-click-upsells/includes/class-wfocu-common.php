<?php
if ( ! class_exists( 'WFOCU_Common' ) ) {
	/**
	 * Class WFOCU_Common
	 * Handles Common Functions For Admin as well as front end interface
	 */
	class WFOCU_Common {


		public static $customizer_key_prefix = '';
		public static $customizer_key_data = '';
		public static $funnel_id = 0;
		public static $tabs_product_obj = null;
		public static $start_time = 0;
		protected static $customizer_fields = array();
		protected static $customizer_fields_default = array();
		private static $active_plugins;


		public static function init() {
			/**
			 * Register Post Type
			 */
			add_action( 'init', array( __CLASS__, 'register_post_type' ), 100 );

			add_action( 'init', array( __CLASS__, 'register_post_status' ), 5 );
			add_action( 'wp_ajax_wfocu_change_rule_type', array( __CLASS__, 'ajax_render_rule_choice' ) );

			add_filter( 'wfocu_parse_shortcode', 'do_shortcode' );
			register_activation_hook( WFOCU_PLUGIN_FILE, array( __CLASS__, 'activation' ) );

			add_action( 'woocommerce_before_template_part', array( __CLASS__, 'modify_product_obj_for_tabs' ), 99 );

			/**
			 * schedule setup to remove expired transients
			 */
			add_action( 'fk_fb_every_day', array( __CLASS__, 'remove_orphaned_transients' ), 999999 );

			add_action( 'wp_ajax_wfocu_rule_json_search_coupons', array( __CLASS__, 'wfocu_rule_json_search_coupons' ) );

			add_filter( 'post_type_link', array( __CLASS__, 'post_type_permalinks' ), 10, 3 );
			add_action( 'pre_get_posts', array( __CLASS__, 'add_cpt_post_names_to_main_query' ), 20 );
		}

		/**
		 * Searching coupons when typing in rule settings
		 */
		public static function wfocu_rule_json_search_coupons() {
			ob_start();

			check_ajax_referer( 'search-coupons', 'security' );

			if ( ! current_user_can( 'edit_shop_coupons' ) || empty( $_GET['term'] ) ) {
				wp_die( - 1 );
			}

			$term = wc_clean( wp_unslash( $_GET['term'] ) );

			if ( empty( $term ) ) {
				wp_die();
			}

			$ids = array();
			// Search by ID.
			if ( is_numeric( $term ) ) {
				$coupon = get_posts( array( //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
					'post__in'         => array( intval( $term ) ),
					'post_type'        => 'shop_coupon',
					'fields'           => 'ids',
					'numberposts'      => 100,
					'paged'            => 1,
					'suppress_filters' => false,
				) );
				if ( count( $coupon ) > 0 ) {
					$ids = array( current( $coupon ) );
				}
			}

			$args = array(
				'post_type'        => 'shop_coupon',
				'numberposts'      => 100,
				'paged'            => 1,
				's'                => $term,
				'post_status'      => 'publish',
				'suppress_filters' => false,
			);

			$posts = get_posts( $args ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
			if ( $posts && is_array( $posts ) && count( $posts ) > 0 ) {
				foreach ( $posts as $post ) {
					array_push( $ids, $post->ID );
					$ids = array_unique( $ids );
				}
			}

			$found_coupons = array();

			foreach ( $ids as $id ) {
				$coupon_title                   = sprintf( /* translators: $1: coupon title */ esc_html__( '%1$s', 'woocommerce' ), get_the_title( $id ) );
				$found_coupons[ $coupon_title ] = $coupon_title;
			}

			wp_send_json( apply_filters( 'wfocu_json_search_found_coupons', $found_coupons ) );
		}

		public static function wfocu_xl_init() {
		}

		public static function get_wc_settings_tab_slug() {
			return 'wfocu-funnels';
		}

		public static function get_boxed_template() {
			return 'wfocu-boxed.php';
		}

		public static function get_canvas_template() {
			return 'wfocu-canvas.php';
		}

		public static function register_post_type() {

			/**
			 * Funnel Post Type
			 */
			register_post_type( self::get_funnel_post_type_slug(), apply_filters( 'wfocu_funnel_post_type_args', array(
				'labels'              => array(
					'name'          => __( 'Funnels', 'woofunnels-upstroke-one-click-upsell' ),
					'singular_name' => __( 'Funnel', 'woofunnels-upstroke-one-click-upsell' ),
					'add_new'       => __( 'Add Funnel', 'woofunnels-upstroke-one-click-upsell' ),
					'add_new_item'  => __( 'Add New Funnel', 'woofunnels-upstroke-one-click-upsell' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_menu'        => false,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'query_var'           => true,
				'supports'            => array( 'title', 'editor', 'revisions', 'author' ),
				'has_archive'         => false,
			) ) );

			$bwb_admin_setting = BWF_Admin_General_Settings::get_instance();
			$rewrite_slug      = apply_filters( 'wfocu_offer_post_type_slug', $bwb_admin_setting->get_option( 'wfocu_page_base' ) );
			$rewrite_slug      = empty( $rewrite_slug ) ? self::get_offer_post_type_slug() : $rewrite_slug;

			/**
			 * Offer Post Type
			 */
			register_post_type( self::get_offer_post_type_slug(), apply_filters( 'wfocu_offer_post_type_args', array(
				'labels'              => array(
					'name'          => __( 'Offers', 'woofunnels-upstroke-one-click-upsell' ),
					'singular_name' => __( 'Offer', 'woofunnels-upstroke-one-click-upsell' ),
					'add_new'       => __( 'Add Offer', 'woofunnels-upstroke-one-click-upsell' ),
					'add_new_item'  => __( 'Add New Offer', 'woofunnels-upstroke-one-click-upsell' ),
					'edit_item'     => sprintf( esc_html__( 'Edit %s', 'woofunnels-upstroke-one-click-upsell' ), 'Offer' ),
					'view_item'     => sprintf( esc_html__( 'View %s', 'woofunnels-upstroke-one-click-upsell' ), 'Offer' ),
					'update_item'   => sprintf( esc_html__( 'Update %s', 'woofunnels-upstroke-one-click-upsell' ), 'Offer' ),

				),
				'public'              => true,
				'show_ui'             => true,
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_in_menu'        => false,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'rewrite'             => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
				'query_var'           => true,
				'supports'            => array( 'title', 'editor', 'custom-fields', 'thumbnail', 'author' ),
				'show_in_rest'        => true,
				'has_archive'         => false,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow', // Prior to Wordpress 4.5, this was false.
				),
			) ) );
		}

		public static function get_funnel_post_type_slug() {
			return 'wfocu_funnel';
		}

		public static function get_offer_post_type_slug() {
			return 'wfocu_offer';
		}

		public static function get_timezone_difference() {
			$date_obj_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$diff         = timezone_offset_get( timezone_open( self::wc_timezone_string() ), $date_obj_utc );

			return $diff;
		}

		/**
		 * Function to get timezone string by checking WordPress timezone settings
		 * @return mixed|string|void
		 */
		public static function wc_timezone_string() {
			$timezone = get_option( 'timezone_string' );
			// if site timezone string exists, return it

			if ( $timezone ) {
				return $timezone;
			}

			$utc_offset = get_option( 'gmt_offset', 0 );
			// get UTC offset, if it isn't set then return UTC
			if ( 0 === ( $utc_offset ) ) {
				return 'UTC';
			}

			// get timezone using offset manual
			return self::get_timezone_by_offset( $utc_offset );
		}

		/**
		 * Function to get timezone string based on specified offset
		 *
		 * @param $offset
		 *
		 * @return string
		 * @see WFOCU_Common::wc_timezone_string()
		 *
		 */
		public static function get_timezone_by_offset( $offset ) {
			switch ( $offset ) {
				case '-12':
					return 'GMT-12';
					break;
				case '-11.5':
					return 'Pacific/Niue'; // 30 mins wrong
					break;
				case '-11':
					return 'Pacific/Niue';
					break;
				case '-10.5':
					return 'Pacific/Honolulu'; // 30 mins wrong
					break;
				case '-10':
					return 'Pacific/Tahiti';
					break;
				case '-9.5':
					return 'Pacific/Marquesas';
					break;
				case '-9':
					return 'Pacific/Gambier';
					break;
				case '-8.5':
					return 'Pacific/Pitcairn'; // 30 mins wrong
					break;
				case '-8':
					return 'Pacific/Pitcairn';
					break;
				case '-7.5':
					return 'America/Hermosillo'; // 30 mins wrong
					break;
				case '-7':
					return 'America/Hermosillo';
					break;
				case '-6.5':
					return 'America/Belize'; // 30 mins wrong
					break;
				case '-6':
					return 'America/Belize';
					break;
				case '-5.5':
					return 'America/Belize'; // 30 mins wrong
					break;
				case '-5':
					return 'America/Panama';
					break;
				case '-4.5':
					return 'America/Lower_Princes'; // 30 mins wrong
					break;
				case '-4':
					return 'America/Curacao';
					break;
				case '-3.5':
					return 'America/Paramaribo'; // 30 mins wrong
					break;
				case '-3':
					return 'America/Recife';
					break;
				case '-2.5':
					return 'America/St_Johns';
					break;
				case '-2':
					return 'America/Noronha';
					break;
				case '-1.5':
					return 'Atlantic/Cape_Verde'; // 30 mins wrong
					break;
				case '-1':
					return 'Atlantic/Cape_Verde';
					break;
				case '+1':
					return 'Africa/Luanda';
					break;
				case '+1.5':
					return 'Africa/Mbabane'; // 30 mins wrong
					break;
				case '+2':
					return 'Africa/Harare';
					break;
				case '+2.5':
					return 'Indian/Comoro'; // 30 mins wrong
					break;
				case '+3':
					return 'Asia/Baghdad';
					break;
				case '+3.5':
					return 'Indian/Mauritius'; // 30 mins wrong
					break;
				case '+4':
					return 'Indian/Mauritius';
					break;
				case '+4.5':
					return 'Asia/Kabul';
					break;
				case '+5':
					return 'Indian/Maldives';
					break;
				case '+5.5':
					return 'Asia/Kolkata';
					break;
				case '+5.75':
					return 'Asia/Kathmandu';
					break;
				case '+6':
					return 'Asia/Urumqi';
					break;
				case '+6.5':
					return 'Asia/Yangon';
					break;
				case '+7':
					return 'Antarctica/Davis';
					break;
				case '+7.5':
					return 'Asia/Jakarta'; // 30 mins wrong
					break;
				case '+8':
					return 'Asia/Manila';
					break;
				case '+8.5':
					return 'Asia/Pyongyang';
					break;
				case '+8.75':
					return 'Australia/Eucla';
					break;
				case '+9':
					return 'Asia/Tokyo';
					break;
				case '+9.5':
					return 'Australia/Darwin';
					break;
				case '+10':
					return 'Australia/Brisbane';
					break;
				case '+10.5':
					return 'Australia/Lord_Howe';
					break;
				case '+11':
					return 'Antarctica/Casey';
					break;
				case '+11.5':
					return 'Pacific/Auckland'; // 30 mins wrong
					break;
				case '+12':
					return 'Pacific/Wallis';
					break;
				case '+12.75':
					return 'Pacific/Chatham';
					break;
				case '+13':
					return 'Pacific/Fakaofo';
					break;
				case '+13.75':
					return 'Pacific/Chatham'; // 1 hr wrong
					break;
				case '+14':
					return 'Pacific/Kiritimati';
					break;
				default:
					return 'UTC';
					break;
			}
		}

		public static function ajax_render_rule_choice( $options ) {

			$defaults = array(
				'group_id'  => 0,
				'rule_id'   => 0,
				'rule_type' => null,
				'condition' => null,
				'operator'  => null,
			);
			$is_ajax  = false;

			if ( defined( 'DOING_AJAX' ) && true === DOING_AJAX && isset( $_POST['action'] ) && $_POST['action'] === 'wfocu_change_rule_type' ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$is_ajax = true;
			}
			if ( $is_ajax ) {
				if ( ! check_ajax_referer( 'wfocuaction-admin', 'security' ) ) {
					die();
				}
				$options = array_merge( $defaults, $_POST );
				WFOCU_Core()->rules->load_rules_classes();

			} else {
				$options = array_merge( $defaults, $options );
			}

			$rule_object = self::woocommerce_wfocu_rule_get_rule_object( $options['rule_type'] );
			if ( ! empty( $rule_object ) ) {
				$values               = $rule_object->get_possible_rule_values();
				$operators            = $rule_object->get_possible_rule_operators();
				$condition_input_type = $rule_object->get_condition_input_type();
				// create operators field
				$operator_args = array(
					'input'   => 'select',
					'name'    => 'wfocu_rule[' . $options['rule_category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][operator]',
					'choices' => $operators,
				);

				echo '<td class="operator">';
				if ( ! empty( $operators ) ) {
					wfocu_Input_Builder::create_input_field( $operator_args, $options['operator'] );
				} else { ?>
                    <input type="hidden" name="<?php echo esc_attr( $operator_args['name'] ); ?>" value="=="/>
					<?php
				}
				echo '</td>';
				// create values field
				$value_args = array(
					'input'   => $condition_input_type,
					'name'    => 'wfocu_rule[' . $options['rule_category'] . '][' . $options['group_id'] . '][' . $options['rule_id'] . '][condition]',
					'choices' => $values,
				);
				echo '<td class="condition">';
				wfocu_Input_Builder::create_input_field( $value_args, $options['condition'] );
				echo '</td>';
			}
			// ajax?
			if ( $is_ajax ) {
				die();
			}
		}

		public static function woocommerce_wfocu_rule_get_rule_object( $rule_type ) {
			global $woocommerce_wfocu_rule_rules;
			if ( isset( $woocommerce_wfocu_rule_rules[ $rule_type ] ) ) {
				return $woocommerce_wfocu_rule_rules[ $rule_type ];
			}
			$class = 'wfocu_Rule_' . $rule_type;
			if ( class_exists( $class ) ) {
				$woocommerce_wfocu_rule_rules[ $rule_type ] = new $class;

				return $woocommerce_wfocu_rule_rules[ $rule_type ];
			} else {
				return null;
			}
		}

		public static function woocommerce_wfocu_rule_get_input_object( $input_type ) {
			global $woocommerce_wfocu_rule_inputs;
			if ( isset( $woocommerce_wfocu_rule_inputs[ $input_type ] ) ) {
				return $woocommerce_wfocu_rule_inputs[ $input_type ];
			}
			$class = 'wfocu_Input_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $input_type ) ) );
			if ( class_exists( $class ) ) {
				$woocommerce_wfocu_rule_inputs[ $input_type ] = new $class;
			} else {
				$woocommerce_wfocu_rule_inputs[ $input_type ] = apply_filters( 'woocommerce_wfocu_rule_get_input_object', $input_type );
			}

			return $woocommerce_wfocu_rule_inputs[ $input_type ];
		}

		public static function render_rule_choice_template( $options ) {
			// defaults
			$defaults              = array(
				'group_id'  => 0,
				'rule_id'   => 0,
				'rule_type' => null,
				'condition' => null,
				'operator'  => null,
				'category'  => 'basic',
			);
			$options               = array_merge( $defaults, $options );
			$rule_object           = self::woocommerce_wfocu_rule_get_rule_object( $options['rule_type'] );
			$values                = $rule_object->get_possible_rule_values();
			$operators             = $rule_object->get_possible_rule_operators();
			$condition_input_type  = $rule_object->get_condition_input_type();
			$operator_rules_output = '[<%= groupId %>][<%= ruleId %>][operator]'; //phpcs:ignore WordPressVIPMinimum.Security.Underscorejs.OutputNotation,WordPress.Security.EscapeOutput.OutputNotEscaped

			// create operators field
			$operator_args = array(
				'input'   => 'select',
				'name'    => 'wfocu_rule[' . $options['category'] . ']' . $operator_rules_output,
				'choices' => $operators,
			);
			echo '<td class="operator">';
			if ( ! empty( $operators ) ) {
				wfocu_Input_Builder::create_input_field( $operator_args, $options['operator'] );
			} else {
				echo '<input type="hidden" name="' . $operator_args['name'] . '" value="==" />'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</td>';
			// create values field
			$value_args = array(
				'input'   => $condition_input_type,
				'name'    => 'wfocu_rule[basic][<%= groupId %>][<%= ruleId %>][condition]',  //phpcs:ignore WordPressVIPMinimum.Security.Underscorejs.OutputNotation
				'choices' => $values,
			);
			echo '<td class="condition">';
			wfocu_Input_Builder::create_input_field( $value_args, $options['condition'] );
			echo '</td>';
		}

		public static function is_load_admin_assets( $screen_type = 'single' ) {
			if ( 'all' === $screen_type ) {
				if ( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) === 'upstroke' ) {

					return true;
				}
			} elseif ( 'listing' === $screen_type ) {

			} elseif ( 'all' === $screen_type || 'builder' === $screen_type ) {
				if ( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) === 'upstroke' && filter_input( INPUT_GET, 'edit', FILTER_UNSAFE_RAW ) > 0 ) {

					return true;
				}
			} elseif ( 'all' === $screen_type || 'settings' === $screen_type ) {
				if ( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) === 'upstroke' && filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) === 'settings' ) {

					return true;
				}
			} elseif ( 'all' === $screen_type || 'customizer' === $screen_type ) {
				if ( 'loaded' === filter_input( INPUT_GET, 'wfocu_customize', FILTER_UNSAFE_RAW ) ) {
					return true;
				}
			} elseif ( 'main' === $screen_type ) {
				return true;
			}

			return apply_filters( 'wfocu_enqueue_scripts', false, $screen_type );
		}

		public static function array_flatten( $array ) {
			if ( ! is_array( $array ) ) {
				return false;
			}
			$result = iterator_to_array( new RecursiveIteratorIterator( new RecursiveArrayIterator( $array ) ), false );

			return $result;
		}

		public static function pr( $arr ) {
			echo '<pre>';
			print_r( $arr );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			echo '</pre>';
		}


		public static function get_product_id_hash( $funnel_id, $offer_id, $product_id ) {
			if ( $funnel_id === 0 || $offer_id === 0 || $product_id === 0 ) {
				return md5( time() );
			}

			$unique_multi_plier = $funnel_id * $offer_id;
			$unique_key         = ( $unique_multi_plier * $product_id ) . time();
			$hash               = md5( $unique_key );

			return $hash;
		}

		public static function get_formatted_product_name( $product ) {
			$formatted_variation_list = self::get_variation_attribute( $product );

			$arguments = array();
			if ( ! empty( $formatted_variation_list ) && count( $formatted_variation_list ) > 0 ) {
				foreach ( $formatted_variation_list as $att => $att_val ) {
					if ( $att_val === '' ) {
						$att_val = __( 'any', 'woofunnels-upstroke-one-click-upsell' );
					}
					$att         = strtolower( $att );
					$att_val     = strtolower( $att_val );
					$arguments[] = "$att: $att_val";
				}
			}

			return sprintf( '%s (#%d) %s', $product->get_title(), $product->get_id(), ( count( $arguments ) > 0 ) ? '(' . implode( ',', $arguments ) . ')' : '' );
		}

		public static function get_variation_attribute( $variation ) {
			if ( is_a( $variation, 'WC_Product_Variation' ) ) {
				$variation_attributes = $variation->get_attributes();

			} else {

				$variation_attributes = array();
				if ( is_array( $variation ) ) {
					foreach ( $variation as $key => $value ) {
						$variation_attributes[ str_replace( 'attribute_', '', $key ) ] = $value;
					}
				}
			}

			return ( $variation_attributes );

		}

		public static function search_products( $term, $include_variations = false ) {
			global $wpdb;
			$like_term     = '%' . $wpdb->esc_like( $term ) . '%';
			$post_types    = apply_filters( 'wfocu_allow_post_types_to_search', $include_variations ? array(
				'product',
				'product_variation',
			) : array( 'product' ) );
			$post_statuses = current_user_can( 'edit_private_products' ) ? array(
				'private',
				'publish',
			) : array( 'publish' );

			$product_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts
                    LEFT JOIN {$wpdb->postmeta} postmeta ON posts.ID = postmeta.post_id
                    WHERE (
                        posts.post_title LIKE %s
                        OR (
                            postmeta.meta_key = '_sku' AND postmeta.meta_value LIKE %s
                        )
                    )
                    AND posts.post_type IN ('" . implode( "','", $post_types ) . "') AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term ) );  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.QuotedDynamicPlaceholderGeneration

			if ( is_numeric( $term ) ) {
				$post_id   = absint( $term );
				$post_type = get_post_type( $post_id );

				if ( 'product_variation' === $post_type && $include_variations ) {
					$product_ids[] = $post_id;
				} elseif ( 'product' === $post_type ) {
					$product_ids[] = $post_id;
				}

				$product_ids[] = wp_get_post_parent_id( $post_id );
			}

			return wp_parse_id_list( $product_ids );
		}


		public static function update_offer( $offer_id, $data, $funnel_id = 0 ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$data = apply_filters( 'update_offer', $data, $offer_id );

			if ( isset( $data->settings ) && is_array( $data->settings ) ) {
				$data->settings = json_decode( json_encode( $data->settings ), false ); //phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

			}
			if ( isset( $data->settings ) ) {
				$data->settings = WFOCU_Common::maybe_filter_boolean_strings( $data->settings );
			}

			update_post_meta( $offer_id, '_wfocu_setting', $data );
		}

		public static function get_offer( $offer_id ) {
			$data = get_post_meta( $offer_id, '_wfocu_setting', true );

			return apply_filters( 'get_offer', $data, $offer_id );
		}

		public static function update_funnel_offers( $funnel_id, $data ) {
			$data = apply_filters( 'update_funnel_offers', $data, $funnel_id );
			update_post_meta( $funnel_id, '_funnel_offers', $data );
		}

		public static function update_funnel_steps( $funnel_id, $data ) {
			$data = apply_filters( 'update_funnel_steps', $data, $funnel_id );
			update_post_meta( $funnel_id, '_funnel_steps', $data );
		}

		public static function update_funnel_upsell_downsell( $funnel_id, $data ) {
			$data = apply_filters( 'update_funnel_upsell_downsell', $data, $funnel_id );
			update_post_meta( $funnel_id, '_funnel_upsell_downsell', $data );
		}


		public static function get_funnels() {
			$args = array(
				'post_type'   => self::get_funnel_post_type_slug(),
				'post_status' => 'publish',
			);
			$loop = new WP_Query( $args );

			return $loop;
		}

		public static function update_funnel_rules( $funnel_id, $data ) {
			$data = apply_filters( 'update_funnel_wfocu_rule', $data, $funnel_id );

			update_post_meta( $funnel_id, '_wfocu_rules', $data );
			update_post_meta( $funnel_id, '_wfocu_is_rules_saved', 'yes' );
		}

		public static function get_funnel_rules( $funnel_id, $rule_type = 'basic' ) {
			$data = get_post_meta( $funnel_id, '_wfocu_rules', true );

			return apply_filters( 'get_funnel_wfocu_rules', ( isset( $data[ $rule_type ] ) ) ? $data[ $rule_type ] : array(), $funnel_id, $rule_type );
		}

		/**
		 * Slug-ify the class name and remove underscores and convert it to filename
		 * Helper function for the auto-loading
		 *
		 * @param $class_name
		 *
		 *
		 * @return mixed|string
		 * @see WFOCU_Gateways::integration_autoload();
		 *
		 */
		public static function slugify_classname( $class_name ) {
			$classname = self::custom_sanitize_title( $class_name );
			$classname = str_replace( '_', '-', $classname );

			return $classname;
		}

		/**
		 * Custom sanitize title method to avoid conflicts with WordPress hooks on sanitize_title
		 * 
		 * @param string $title The title to sanitize
		 * @return string The sanitized title
		 */
		private static function custom_sanitize_title( $title ) {
			$title = remove_accents( $title );
			$title = sanitize_title_with_dashes( $title );
			
			return $title;
		}

		/**
		 * Recursive Un-serialization based on   WP's is_serialized();
		 *
		 * @param $val
		 *
		 * @return mixed|string
		 * @see is_serialized()
		 */
		public static function unserialize_recursive( $val ) {
			if ( is_serialized( $val ) ) {
				$val = trim( $val );
				$ret = maybe_unserialize( $val );
				if ( is_array( $ret ) ) {
					foreach ( $ret as &$r ) {
						$r = self::unserialize_recursive( $r );
					}
				}

				return $ret;
			} elseif ( is_array( $val ) ) {
				foreach ( $val as &$r ) {
					$r = self::unserialize_recursive( $r );
				}

				return $val;
			} else {
				return $val;
			}

		}

		public static function maybe_parse_product_tags( $content = '', $key = '', $obj = '' ) {
			if ( empty( $content ) || empty( $key ) || empty( $obj ) ) {
				return;
			}
			$content = self::maybe_parse_merge_tags( $content );
			/** {{product_price}} */
			if ( strpos( $content, '{{product_price}}' ) !== false ) {
				$replace_str = self::maybe_parse_merge_tags( '{{product_offer_price key="' . $key . '"}}' );

				$content = str_replace( '{{product_price}}', $replace_str, $content );
			}

			return $content;
		}

		public static function maybe_parse_merge_tags( $content = '', $obj = false, $line_break = true ) {
			if ( empty( $content ) ) {
				return '';
			}

			if ( true === $line_break ) {
				$content = nl2br( $content );
			}

			$content = WFOCU_Static_Merge_Tags::maybe_parse_merge_tags( $content, $obj );
			$content = WFOCU_Dynamic_Merge_Tags::maybe_parse_merge_tags( $content, $obj );
			$content = WFOCU_ShortCode_Merge_Tags::maybe_parse_merge_tags( $content, $obj );

			$content = WFOCU_Syntax_Merge_Tags::maybe_parse_merge_tags( $content, $obj );

			$content = apply_filters( 'wfocu_parse_shortcode', $content );

			return $content;
		}

		public static function get_option( $field ) {
			if ( empty( $field ) ) {
				return;
			}

			/** If data not fetched once */
			if ( empty( self::$customizer_key_data ) ) {
				self::$customizer_key_data = get_option( self::$customizer_key_prefix );
			}

			/** Field found in customizer get option */
			if ( is_array( self::$customizer_key_data ) && isset( self::$customizer_key_data[ $field ] ) ) {
				$value = self::$customizer_key_data[ $field ];
				$value = self::maybe_convert_html_tag( $value );

				return apply_filters( 'wfocu_customizer_get_option', $value, $field );
			}

			/** Field found in customizer fields default */
			if ( is_array( self::$customizer_fields_default ) && isset( self::$customizer_fields_default[ $field ] ) ) {
				$value = self::$customizer_fields_default[ $field ];
				$value = self::maybe_convert_html_tag( $value );

				return apply_filters( 'wfocu_customizer_get_option', $value, $field );
			}

			return;
		}

		public static function maybe_convert_html_tag( $val ) {
			if ( false === is_string( $val ) ) {
				return $val;
			}
			$val = str_replace( '&lt;', '<', $val );
			$val = str_replace( '&gt;', '>', $val );

			return $val;

		}

		public static function get_variable_league_product_types() {
			return array(
				'variable',
				'variable-subscription',
			);
		}

		/**
		 * Get image source by image id or source
		 *
		 * @param type $value
		 * @param type $mode
		 *
		 * @return type
		 */
		public static function get_image_source( $value, $mode = 'full' ) {
			if ( is_numeric( $value ) ) {
				$image_data = wp_get_attachment_image_src( $value, $mode );
				if ( isset( $image_data[0] ) ) {
					return $image_data[0];
				}
			}

			return $value;
		}

		public static function set_customizer_fields_default_vals( $data ) {
			if ( is_array( $data ) && count( $data ) > 0 ) {

				$default_values = array();

				foreach ( $data as $panel_single ) {
					/** Panel */
					foreach ( $panel_single as $panel_key => $panel_arr ) {
						/** Section */
						if ( is_array( $panel_arr['sections'] ) && count( $panel_arr['sections'] ) > 0 ) {
							foreach ( $panel_arr['sections'] as $section_key => $section_arr ) {
								$section_key_final = $panel_key . '_' . $section_key;
								/** Fields */
								if ( is_array( $section_arr['fields'] ) && count( $section_arr['fields'] ) > 0 ) {
									foreach ( $section_arr['fields'] as $field_key => $field_data ) {
										$field_key_final = $section_key_final . '_' . $field_key;

										if ( isset( $field_data['default'] ) ) {
											$default_values[ $field_key_final ] = $field_data['default'];
										}
									}
								}
							}
						}
					}
				}

				self::$customizer_fields_default = $default_values;

			}

		}

		public static function get_post_table_data() {

			$args = array(
				'post_type'      => self::get_funnel_post_type_slug(),
				'post_status'    => array( 'publish', WFOCU_SLUG . '-disabled' ),
				'posts_per_page' => self::posts_per_page(), //phpcs:ignore  WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			);
			$args = apply_filters( 'wfocu_add_control_meta_query', $args );

			if ( isset( $_GET['paged'] ) && $_GET['paged'] > 0 ) {  // phpcs:ignore WordPress.Security.NonceVerification
				$args['paged'] = absint( $_GET['paged'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( isset( $_GET['order'] ) && '' !== $_GET['order'] && isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['orderby'] = wc_clean( $_GET['orderby'] ); // phpcs:ignore WordPress.Security.NonceVerification
				$args['order']   = wc_clean( $_GET['order'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			if ( isset( $_REQUEST['s'] ) && '' !== $_REQUEST['s'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['s'] = wc_clean( $_REQUEST['s'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			if ( isset( $_REQUEST['status'] ) && '' !== $_REQUEST['status'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( 'active' === wc_clean( $_REQUEST['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args['post_status'] = 'publish';
				} elseif ( 'all' === $_REQUEST['status'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args['post_status'] = array( 'publish', WFOCU_SLUG . '-disabled' );
				} else {
					$args['post_status'] = WFOCU_SLUG . '-disabled';
				}
			} else {
				$args['post_status'] = array( 'publish', WFOCU_SLUG . '-disabled' );
			}

			$q = new WP_Query( $args );

			$found_posts = array( 'found_posts' => $q->found_posts );
			$items       = array();
			if ( $q->have_posts() ) {
				while ( $q->have_posts() ) {

					$q->the_post();
					global $post;

					$steps = WFOCU_Core()->funnels->get_funnel_steps( get_the_ID() );
					$view  = "";

					if ( ( is_array( $steps ) && count( $steps ) > 0 ) && isset( $steps[0]['id'] ) ) {
						$offer_data = WFOCU_Core()->offers->get_offer( $steps[0]['id'] );
						if ( is_object( $offer_data ) && 'custom-page' === $offer_data->template ) {
							$custom_page = get_post_meta( $steps[0]['id'], '_wfocu_custom_page', true );
							$view        = ( $custom_page !== '' ) ? get_permalink( $custom_page ) : get_permalink( $steps[0]['id'] );
						} else {
							$view = get_permalink( $steps[0]['id'] );
						}
					}

					$status      = get_post_status( get_the_ID() );
					$priority    = $post->menu_order;
					$funnel_url  = add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'offers',
						'edit'    => get_the_ID(),
					), admin_url( 'admin.php' ) );
					$row_actions = array();

					$row_actions['edit'] = array(
						'action' => 'edit',
						'text'   => __( 'Edit', 'woofunnels-upstroke-one-click-upsell' ),
						'link'   => $funnel_url,
						'attrs'  => '',
					);

					$row_actions['view'] = array(
						'action' => 'view',
						'text'   => __( 'View', 'woofunnels-upstroke-one-click-upsell' ),
						'link'   => $view,
						'attrs'  => 'target="_blank"',
					);

					$row_actions['duplicate'] = array(
						'action' => 'duplicate',
						'text'   => __( 'Duplicate', 'woofunnels-upstroke-one-click-upsell' ),
						'link'   => 'javascript:void(0);',
						'attrs'  => 'class="wfocu-duplicate" data-funnel-id="' . get_the_ID() . '" id="wfocu_duplicate_' . get_the_ID() . '"',
					);

					$row_actions['export'] = array(
						'action' => 'export',
						'text'   => __( 'Export', 'woofunnels-upstroke-one-click-upsell' ),
						'link'   => wp_nonce_url( admin_url( 'admin.php?action=wfocu-export&id=' . get_the_ID() ), 'wfocu-export' ),
						'attrs'  => '',
					);

					$row_actions['delete'] = array(
						'action' => 'delete',
						'text'   => __( 'Delete', 'woofunnels-upstroke-one-click-upsell' ),
						'link'   => get_delete_post_link( get_the_ID(), '', true ),
						'attrs'  => '',
					);
					$items[]               = array(
						'id'           => get_the_ID(),
						'post_content' => get_the_content(),
						'status'       => $status,
						'row_actions'  => $row_actions,
						'priority'     => $priority,
					);
				}
			}
			$found_posts['items'] = $items;

			return $found_posts;
		}

		public static function posts_per_page() {
			return 20;
		}

		public static function string2hex( $string ) {
			$hex = '';
			for ( $i = 0; $i < strlen( $string ); $i ++ ) {
				$hex .= dechex( ord( $string[ $i ] ) );
			}

			return $hex;
		}

		/**
		 * Get video ID against a video URL
		 *
		 * @param $url URL
		 * @param string $type service type
		 *
		 * @return string
		 */
		public static function get_video_id( $url, $type = 'youtube' ) {
			if ( empty( $url ) ) {
				return '';
			}
			if ( 'youtube' === $type ) {
				preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match );
				if ( isset( $match[1] ) && ! empty( $match[1] ) ) {
					return $match[1];
				}

				return '';
			} elseif ( 'vimeo' === $type ) {
				preg_match( '%^https?:\/\/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)(?:[?]?.*)$%im', $url, $match );
				if ( isset( $match[3] ) && ! empty( $match[3] ) ) {
					return $match[3];
				}

				return '';
			} elseif ( 'wistia' === $type ) {
				preg_match( '/https?:\/\/(.+)?(wistia\.com|wi\.st)\/(medias|embed)\/([a-zA-Z0-9]*).*/', $url, $match );
				if ( isset( $match[4] ) && ! empty( $match[4] ) ) {
					if ( $match[4] === 'iframe' ) {
						preg_match( '/https?:\/\/(.+)?(wistia\.com|wi\.st)\/(medias|embed)\/(iframe)\/([a-zA-Z0-9]*).*/', $url, $match );

						return ( isset( $match[5] ) ) ? $match[5] : '';
					} else {
						return $match[4];
					}
				}

				return '';
			}

			return $url;

		}

		public static function get_order_status_settings() {
			$get_order_statuses = wc_get_order_statuses();

			$result = array_map( function ( $short, $long ) {
				return array(
					'id'   => $short,
					'name' => $long,
				);
			}, array_keys( $get_order_statuses ), $get_order_statuses );

			return $result;
		}

		public static function maybe_filter_boolean_strings( $options ) {
			$cloned_option = $options;
			foreach ( $options as $key => $value ) {

				if ( is_object( $options ) ) {

					if ( $value === 'true' || $value === true ) {

						$cloned_option->$key = true;
					}

					if ( $value === 'false' || $value === false ) {
						$cloned_option->$key = false;
					}
				} elseif ( is_array( $options ) ) {

					if ( $value === 'true' || $value === true ) {

						$cloned_option[ $key ] = true;
					}
					if ( $value === 'false' || $value === false ) {
						$cloned_option[ $key ] = false;
					}
				}
			}

			return $cloned_option;

		}

		public static function get_next_funnel_priority() {
			$funnel_max_priority = get_option( '_wfocu_max_priority', 0, false );
			self::update_max_priority( $funnel_max_priority + 1 );

			return $funnel_max_priority + 1;
		}

		public static function update_max_priority( $current ) {
			$funnel_max_priority = get_option( '_wfocu_max_priority', 0 );

			if ( $current > $funnel_max_priority ) {
				update_option( '_wfocu_max_priority', $current );
			}
		}

		public static function register_post_status() {
			// acf-disabled
			register_post_status( WFOCU_SLUG . '-disabled', array(
				'label'                     => __( 'Disabled', 'woofunnels-upstroke-one-click-upsell' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'woofunnels-upstroke-one-click-upsell' ),
			) );
		}

		public static function tooltip( $tip, $allow_html = false ) {
			if ( $allow_html ) {
				$tip = wc_sanitize_tooltip( $tip );
			} else {
				$tip = esc_attr( $tip );
			}

			return '<span class="wfocu-help-tip" data-tip="' . $tip . '"></span>';
		}


		public static function is_add_on_exist( $add_on = 'MultiProduct' ) {
			$status = false;
			if ( class_exists( 'WFOCU_' . $add_on ) ) {
				$status = true;
			}

			return $status;
		}

		public static function update_funnel_time( $funnel_id ) {
			$my_post = array(
				'ID' => $funnel_id,
			);

			wp_update_post( $my_post );
		}

		public static function get_date_format() {
			return get_option( 'date_format', '' ) . ' ' . get_option( 'time_format', '' );
		}

		public static function get_sidebar_menu() {
			$sidebar_menu = array(

				'20' => array(
					'icon' => 'dashicons dashicons-menu',
					'name' => __( 'Offers', 'woofunnels-upstroke-one-click-upsell' ),
					'key'  => 'offers',
				),
				'30' => array(
					'icon' => 'dashicons dashicons-art',
					'name' => __( 'Design', 'woofunnels-upstroke-one-click-upsell' ),
					'key'  => 'design',
				),
				'50' => array(
					'icon' => 'dashicons dashicons-admin-generic',
					'name' => __( 'Settings', 'woofunnels-upstroke-one-click-upsell' ),
					'key'  => 'settings',
				),
			);

			return apply_filters( 'wfocu_builder_menu', $sidebar_menu );
		}

		public static function between( $needle, $that, $inthat ) {
			return self::before( $that, self::after( $needle, $inthat ) );
		}

		public static function before( $needle, $inthat ) {
			return substr( $inthat, 0, strpos( $inthat, $needle ) );
		}

		public static function after( $needle, $inthat ) {
			if ( ! is_bool( strpos( $inthat, $needle ) ) ) {
				return substr( $inthat, strpos( $inthat, $needle ) + strlen( $needle ) );
			}
		}

		public static function get_offer_base_url() {
			$bwb_admin_setting = BWF_Admin_General_Settings::get_instance();
			$offer_slug        = $bwb_admin_setting->get_option( 'wfocu_page_base' );
			$offer_base_url    = site_url( '/' . $offer_slug );

			return $offer_base_url;
		}

		public static function activation() {
			if ( ! wp_next_scheduled( 'woofunnels_maybe_track_usage_scheduled_current' ) ) {
				delete_option( 'woofunnels_track_day' );
				wp_schedule_single_event( time() + ( 1 * MINUTE_IN_SECONDS ), 'woofunnels_maybe_track_usage_scheduled_current' );
			}
		}

		public static function modify_product_obj_for_tabs( $template_name = '' ) {
			if ( empty( $template_name ) ) {
				return '';
			}

			if ( in_array( $template_name, array(
				'single-product/tabs/description.php',
				'single-product/tabs/additional-information.php',
			), true ) ) {
				if ( self::$tabs_product_obj instanceof WC_Product ) {
					global $product;  // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
					$product = self::$tabs_product_obj; // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
				}
			}
		}

		public static function handle_single_quote_variation( $content ) {

			if ( '' === $content ) {
				return $content;
			}

			$content = str_replace( '\'', '___', $content );

			return $content;
		}

		public static function handle_single_quote_variation_reverse( $content ) {

			if ( '' === $content ) {
				return $content;
			}

			$content = str_replace( '___', '\'', $content );

			return $content;
		}

		public static function clean_ascii_characters( $content ) {

			if ( '' === $content ) {
				return $content;
			}

			$content = str_replace( '%', '_', $content );
			$content = str_replace( '!', '_', $content );
			$content = str_replace( '\"', '_', $content );
			$content = str_replace( '#', '_', $content );
			$content = str_replace( '$', '_', $content );
			$content = str_replace( '&', '_', $content );
			$content = str_replace( '(', '_', $content );
			$content = str_replace( ')', '_', $content );
			$content = str_replace( '(', '_', $content );
			$content = str_replace( '*', '_', $content );
			$content = str_replace( ',', '_', $content );
			$content = str_replace( '', '_', $content );
			$content = str_replace( '.', '_', $content );
			$content = str_replace( '/', '_', $content );

			return $content;
		}


		/**
		 * Returns a string with all non-ASCII characters removed. This is useful for any string functions that expect only
		 * ASCII chars and can't safely handle UTF-8
		 *
		 * Based on the SV_WC_Helper::str_to_ascii() method developed by the masterful SkyVerge team
		 *
		 * Note: We must do a strict false check on the iconv() output due to a bug in PHP/glibc {@link https://bugs.php.net/bug.php?id=63450}
		 *
		 * @param string $string string to make ASCII
		 *
		 * @return string|null ASCII string or null if error occurred
		 */
		public static function str_to_ascii( $string ) {

			$ascii = false;

			if ( function_exists( 'iconv' ) ) {
				$ascii = iconv( 'UTF-8', 'ASCII//IGNORE', $string );
			}

			return false === $ascii ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', $string ) : $ascii;
		}

		public static function remove_orphaned_transients() {

			if ( ! class_exists( 'WooFunnels_File_Api' ) ) {
				return;
			}

			clearstatcache();
			$file_api = new WooFunnels_File_Api( 'upstroke-funnel-transient' );

			$woofunnels_core_dir = $file_api->woofunnels_core_dir . '/upstroke-funnel-transient';
			$dir                 = opendir( $woofunnels_core_dir . '/' );

			if ( empty( $dir ) ) {
				return;
			}

			$yesdate = strtotime( '-2 hours' );

			self::$start_time = time();
			$i                = 0;
			if ( is_dir( $woofunnels_core_dir ) ) {
				while ( false !== ( $file = @readdir( $dir ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition,Generic.PHP.NoSilencedErrors.Forbidden,Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition

					if ( $file === '.' || $file === '..' ) {
						continue;
					}
					if ( @filemtime( $woofunnels_core_dir . '/' . '' . $file ) <= $yesdate ) { //phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden
						$file_api->delete( $woofunnels_core_dir . '/' . '' . $file );
						$i ++;
					}

					if ( true === self::time_exceeded() || true === self::memory_exceeded() ) {
						break;
					}
				}
			}
		}

		public static function time_exceeded() {
			$finish = self::$start_time + 20; // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return $return;
		}

		public static function memory_exceeded() {
			$memory_limit   = self::get_memory_limit() * 0.9; // 90% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return $return;
		}

		public static function get_memory_limit() {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				// Sensible default.
				$memory_limit = '128M';
			}

			if ( ! $memory_limit || - 1 === $memory_limit || '-1' === $memory_limit ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32G';
			}

			return self::convert_hr_to_bytes( $memory_limit ) * 1024 * 1024;
		}


		/**
		 * Converts a shorthand byte value to an integer byte value.
		 *
		 * Wrapper for wp_convert_hr_to_bytes(), moved to load.php in WordPress 4.6 from media.php
		 *
		 * @link https://secure.php.net/manual/en/function.ini-get.php
		 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
		 *
		 * @param string $value A (PHP ini) byte value, either shorthand or ordinary.
		 *
		 * @return int An integer byte value.
		 */
		public static function convert_hr_to_bytes( $value ) {
			if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
				return wp_convert_hr_to_bytes( $value );
			}

			$value = strtolower( trim( $value ) );
			$bytes = (int) $value;

			if ( false !== strpos( $value, 'g' ) ) {
				$bytes *= GB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'm' ) ) {
				$bytes *= MB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'k' ) ) {
				$bytes *= KB_IN_BYTES;
			}

			// Deal with large (float) values which run into the maximum integer size.
			return min( $bytes, PHP_INT_MAX );
		}

		public static function apply_discount( $price, $options, $product = '' ) {
			if ( is_object( $options ) && isset( $options->discount_type ) ) {

				$options->discount_amount = ( float ) $options->discount_amount;

				switch ( $options->discount_type ) {
					case 'percentage_on_sale':
					case 'percentage_on_reg':
						$percentage = apply_filters( 'wfocu_product_discount_percentage', $options->discount_amount, $product );
						if ( empty( $percentage ) ) {
							$percentage = 0;
						}
						$price = $price - ( $price * ( $percentage / 100 ) );
						break;
					case 'fixed_on_sale':
					case 'fixed_on_reg':
						$discount = $options->discount_amount;
						if ( ! empty( $options->discount_amount ) && isset( $options->quantity ) && absint( $options->quantity ) > 0 ) {
							$discount = $options->discount_amount * $options->quantity;
						}
						$fixed_amount = apply_filters( 'wfocu_product_discount_fixed', $discount, $product );

						$price = $price - ( WFOCU_Plugin_Compatibilities::get_fixed_currency_price( $fixed_amount, '' ) );

						break;
					case 'Fixed_Price':
						break;

				}
			}

			return $price;
		}

		public static function plugin_active_check( $basename ) {

			if ( ! self::$active_plugins ) {
				self::set_active_plugins();
			}

			return in_array( $basename, self::$active_plugins, true ) || array_key_exists( $basename, self::$active_plugins );
		}

		public static function set_active_plugins() {

			self::$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}
		}

		public static function convert_memory( $size ) {
			$unit = array( 'b', 'kb', 'mb', 'gb', 'tb', 'pb' );

			return @round( $size / pow( 1024, ( $i = floor( log( $size, 1024 ) ) ) ), 2 ) . ' ' . $unit[ $i ]; //phpcs:ignore Generic.PHP.NoSilencedErrors
		}

		public static function get_amount_for_comparisons( $total ) {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) );
		}

		public static function array_swap( &$array, $swap_a, $swap_b ) {
			list( $array[ $swap_a ], $array[ $swap_b ] ) = array( $array[ $swap_b ], $array[ $swap_a ] );
		}


		public static function get_discount_setting( $get_type = 'percentage' ) {
			if ( $get_type !== 'percentage' && $get_type !== 'fixed' ) {
				return $get_type;
			}
			if ( $get_type === 'percentage' ) {
				return 'percentage_on_reg';
			}
			if ( $get_type === 'fixed' ) {
				return 'fixed_on_reg';
			}

			return $get_type;
		}

		/**
		 * Modify permalink
		 *
		 * @param string $post_link post link.
		 * @param array $post post data.
		 * @param string $leavename leave name.
		 *
		 * @return string
		 */
		public static function post_type_permalinks( $post_link, $post, $leavename ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

			$bwb_admin_setting = BWF_Admin_General_Settings::get_instance();

			if ( isset( $post->post_type ) && self::get_offer_post_type_slug() === $post->post_type && empty( trim( $bwb_admin_setting->get_option( 'wfocu_page_base' ) ) ) ) {


				// If elementor page preview, return post link as it is.
				if ( isset( $_REQUEST['elementor-preview'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return $post_link;
				}

				$structure = get_option( 'permalink_structure' );

				if ( in_array( $structure, self::get_supported_permalink_strcutures_to_normalize(), true ) ) {

					$post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );

				}

			}

			return $post_link;
		}


		public static function wfocu_get_date_format() {
			$date_format = get_option( 'date_format', true );
			$date_format = $date_format ? $date_format : 'M d, Y';

			return $date_format;
		}

		public static function wfocu_get_time_format() {
			$time_format = get_option( 'time_format', true );
			$time_format = $time_format ? $time_format : 'g:i a';

			return $time_format;
		}

		public static function get_supported_permalink_strcutures_to_normalize() {
			return array( '/%postname%/' );
		}

		/**
		 * Have WordPress match postname to any of our public post types.
		 * All of our public post types can have /post-name/ as the slug, so they need to be unique across all posts.
		 * By default, WordPress only accounts for posts and pages where the slug is /post-name/.
		 *
		 * @param WP_Query $query query statement.
		 */
		public static function add_cpt_post_names_to_main_query( $query ) {

			// Bail if this is not the main query.
			if ( ! $query->is_main_query() ) {
				return;
			}


			// Bail if this query doesn't match our very specific rewrite rule.
			if ( ! isset( $query->query['page'] ) ) {
				return;
			}

			// Bail if we're not querying based on the post name.
			if ( empty( $query->query['name'] ) ) {
				return;
			}
			// If query does not match (not exactly 2 parameters or 3 with 'lang'), return early.
			if ( ! ( count( $query->query ) === 2 || ( count( $query->query ) === 3 && isset( $query->query['lang'] ) ) ) ) {
				return;
			}
			// Add landing page step post type to existing post type array.
			if ( isset( $query->query_vars['post_type'] ) && is_array( $query->query_vars['post_type'] ) ) {

				$post_types = $query->query_vars['post_type'];

				$post_types[] = self::get_offer_post_type_slug();

				$query->set( 'post_type', $post_types );

			} else {
				// Add CPT to the list of post types WP will include when it queries based on the post name.
				$query->set( 'post_type', array( 'post', 'page', self::get_offer_post_type_slug() ) );
			}
		}

		public static function maybe_elementor_template( $page_id, $new_page_id ) {
			$contents = get_post_meta( $page_id, '_elementor_data', true );
			if ( false === WFOCU_Common::plugin_active_check( 'elementor/elementor.php' ) ) {
				return;
			}
			$data = [
				'_elementor_version'       => get_post_meta( $page_id, '_elementor_version', true ),
				'_elementor_template_type' => get_post_meta( $page_id, '_elementor_template_type', true ),
				'_elementor_edit_mode'     => get_post_meta( $page_id, '_elementor_edit_mode', true ),

			];
			foreach ( $data as $meta_key => $meta_value ) {
				update_post_meta( $new_page_id, $meta_key, $meta_value );
			}

			require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/elementor/class-wfocu-elementor-importer.php';

			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				if ( version_compare( ELEMENTOR_VERSION, '3.1.0', '<=' ) ) {
					\Elementor\Plugin::$instance->db->set_is_elementor_page( $new_page_id, true );
				} else {
					\Elementor\Plugin::$instance->documents->get( $new_page_id )->set_is_built_with_elementor( true );
				}
			}

			$instance = new WFOCU_Importer_Elementor();
			if ( ! is_null( $instance ) ) {
				if ( is_array( $contents ) ) {
					$contents = wp_json_encode( $contents );

				}
				$instance->single_template_import( $new_page_id, $contents );
			}


		}

		/**
		 * @return string
		 */
		public static function get_wfocu_container_attrs() {

			$attributes   = apply_filters( 'wfocu_container_attrs', array() );
			$attrs_string = '';

			foreach ( $attributes as $key => $value ) {

				if ( ! $value ) {
					continue;
				}

				if ( true === $value ) {
					$attrs_string .= esc_html( $key ) . ' ';
				} else {
					$attrs_string .= sprintf( '%s=%s ', esc_html( $key ), esc_attr( $value ) );
				}
			}

			return $attrs_string;
		}

		public static function check_builder_status( $builder = '' ) {
			// Divi Builder Plugin Exists
			$response = [ 'found' => false, 'error' => '', 'is_old_version' => 'no', 'version' => '' ];
			if ( empty( $builder ) ) {
				$response['error'] = __( 'No Builder Specified', 'woofunnels-upstroke-one-click-upsell' );
			} else if ( 'oxy' === $builder ) {
				$supported_version   = '3.0';
				$oxy_exist           = false;
				$oxy_builder_version = '1.0';
				if ( class_exists( 'CT_Component' ) ) {
					$oxy_exist = true;
					if ( defined( 'CT_VERSION' ) ) {
						$oxy_builder_version = CT_VERSION;
					}
				}

				if ( true === $oxy_exist ) {
					$response['found'] = true;
					if ( ! version_compare( $oxy_builder_version, $supported_version, '>=' ) ) {
						$response['is_old_version'] = 'yes';
						$response['version']        = $oxy_builder_version;
						$response['error']          = sprintf( __( 'Site has an older version of Oxygen Classic Builder. Templates are supported for v%s or greater.<br /> Please update.', 'woofunnels-upstroke-one-click-upsell' ), $supported_version );
					}
				}

			} else if ( 'divi' === $builder ) {
				$supported_version    = '4.1';
				$divi_exist           = false;
				$divi_builder_version = 0;
				// Detect Divi Builder Plugin is Active
				if ( class_exists( 'ET_Builder_Plugin' ) ) {
					$divi_exist = true;

					if ( defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
						$divi_builder_version = ET_BUILDER_PLUGIN_VERSION;
					}


				} else if ( function_exists( 'et_setup_theme' ) ) { // Detect Theme Active
					$divi_exist = true;
					$theme      = wp_get_theme();
					if ( $theme instanceof WP_Theme ) {
						$parent = $theme->parent();
						if ( $parent instanceof WP_Theme ) {
							$divi_builder_version = $parent->get( 'Version' );
						} else {
							$divi_builder_version = $theme->get( 'Version' );
						}

					}
				}
				// available in Both Theme & Plugin
				if ( 0 == $divi_builder_version && defined( 'ET_BUILDER_PRODUCT_VERSION' ) ) { //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison,Universal.Operators.StrictComparisons.LooseEqual
					$divi_builder_version = ET_BUILDER_PRODUCT_VERSION;
				}

				//ET_Builder_Plugin
				if ( true === $divi_exist && class_exists( 'ET_Core_Portability' ) ) {
					$response['found']   = true;
					$response['version'] = $divi_builder_version;
					if ( ! version_compare( $divi_builder_version, $supported_version, '>=' ) ) {
						$response['is_old_version'] = 'yes';
						$response['error']          = sprintf( __( 'Site has an older version of Divi Builder. Templates are supported for v%s or greater.<br /> Please update.', 'woofunnels-upstroke-one-click-upsell' ), $supported_version );
					}
				}
			}

			return $response;

		}

		public static function default_selected_product( $key ) {

			$product_key = WFOCU_Core()->template_loader->default_product_key( $key );

			if ( ! empty( $product_key ) ) {
				if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
					return false;
				}

				$product_data = WFOCU_Core()->template_loader->product_data->products;
				$product      = '';

				if ( ! isset( $product_data->{$product_key} ) ) {
					$product_key = key( (array) WFOCU_Core()->template_loader->product_data->products );
				}

				if ( isset( $product_data->{$product_key} ) ) {
					$product = $product_data->{$product_key}->data;
				}
				if ( ! $product instanceof WC_Product ) {
					return false;
				}

				return $product;

			}

			return false;
		}

		public static function default_selected_product_key( $key ) {

			$product_key = WFOCU_Core()->template_loader->default_product_key( $key );

			if ( ! empty( $product_key ) ) {
				if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
					return false;
				}

				$product_data = WFOCU_Core()->template_loader->product_data->products;
				$product      = '';

				if ( ! isset( $product_data->{$product_key} ) ) {
					$product_key = key( (array) WFOCU_Core()->template_loader->product_data->products );
				}

				if ( isset( $product_data->{$product_key} ) ) {
					$product = $product_data->{$product_key}->data;
				}

				if ( ! $product instanceof WC_Product ) {
					return false;
				}

				return $product_key;
			}

			return false;
		}

		public static function maybe_strip_js_for_localize( $content ) {
			return clone( $content );
		}

		/**
		 * @param $email
		 * @param WC_Order $order
		 *
		 * @return false|int|mixed|WP_Error|null
		 * @throws Exception
		 */
		public static function create_new_customer( $email, $order = false ) {

			if ( empty( $email ) ) {
				return false;
			}

			/**
			 * Try to get the user by the email provided, if present then process as user ID exists.
			 */
			$maybe_user = get_user_by( 'email', $email );
			if ( $maybe_user instanceof WP_User ) {
				return $maybe_user->ID;
			}
			$username = sanitize_user( current( explode( '@', $email ) ), true );

			// username has to be unique
			$append     = 1;
			$o_username = $username;

			while ( username_exists( $username ) ) {
				$username = $o_username . $append;

				++ $append;
			}

			$password = wp_generate_password();


			// Use WP_Error to handle registration errors.
			$errors = new WP_Error();

			do_action( 'woocommerce_register_post', $username, $email, $errors );

			$errors = apply_filters( 'woocommerce_registration_errors', $errors, $username, $email );

			if ( $errors->get_error_code() ) {
				return $errors;
			}

			$new_customer_data = apply_filters( 'woocommerce_new_customer_data', array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => 'customer',
			) );

			$customer_id = wp_insert_user( $new_customer_data );

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			do_action( 'woocommerce_created_customer', $customer_id, $new_customer_data, true );

			if ( ! empty( $customer_id ) ) {

				// Add customer info from other fields.
				if ( $customer_id && ! empty( $order ) && $order instanceof WC_Order ) {
					$customer = new WC_Customer( $customer_id );

					if ( ! empty( $order->get_billing_first_name() ) && '' === $customer->get_first_name() ) {
						$customer->set_first_name( $order->get_billing_first_name() );
					}

					if ( ! empty( $order->get_billing_last_name() ) && '' === $customer->get_last_name() ) {
						$customer->set_last_name( $order->get_billing_last_name() );
					}

					// If the display name is an email, update to the user's full name.
					if ( is_email( $customer->get_display_name() ) ) {
						$customer->set_display_name( $customer->get_first_name() . ' ' . $customer->get_last_name() );
					}

					$customer->set_billing_country( $order->get_billing_country() );
					$customer->set_billing_state( $order->get_billing_state() );
					$customer->set_billing_postcode( $order->get_billing_postcode() );


					$customer->save();
				}


				wp_set_current_user( $customer_id, $username );

				wc_set_customer_auth_cookie( $customer_id );
			}

			return $customer_id;

		}


		/**
		 * @param $get_id
		 * create offer shortcode for run oxygen builder
		 *
		 * @return array
		 */
		public static function get_oxy_builder_shortcode( $get_id = false ) {
			$tags = WFOCU_Dynamic_Merge_Tags::get_all_tags();
			if ( is_array( $tags ) && count( $tags ) > 0 ) {
				foreach ( $tags as &$tag ) {
					$tag['tag'] = "[oxygen data='phpfunction' function='wfocu_order_data' arguments='" . $tag['tag'] . "']";
					if ( true === $get_id ) {
						$tag['id'] = $tag['tag'];
					}
				}
			}

			return $tags;
		}

		/**
		 * Create facebook advanced matching data
		 * @return mixed|null
		 */
		public static function pixel_advanced_matching_data() {
			$args = array();

			if ( ! class_exists( 'BWF_Admin_General_Settings' ) ) {
				return $args;
			}

			$advanced_tracking = BWF_Admin_General_Settings::get_instance()->get_option( 'is_fb_advanced_event' );

			if ( ! is_array( $advanced_tracking ) || count( $advanced_tracking ) === 0 || 'yes' !== $advanced_tracking[0] ) {
				return $args;
			}

			$params = self::advanced_matching_data();

			if ( ! is_array( $params ) || 0 === count( $params ) ) {
				return $args;
			}

			foreach ( $params as $key => &$value ) {
				if ( ! empty( $value ) ) {
					$params[ $key ] = WFOCU_Common::sanitize_advanced_matching_param( $value, $key );
				}
			}

			return $params;
		}

		/**
		 * Create tiktok advanced matching data
		 * @return mixed|null
		 */
		public static function tiktok_advanced_matching_data() {
			$args = array();

			$params = self::advanced_matching_data();

			if ( ! is_array( $params ) || 0 === count( $params ) ) {
				return $args;
			}

			if ( isset( $params["em"] ) && $params["em"] !== "" ) { //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$args['sha256_email'] = hash( 'sha256', $params["em"] );
			}
			if ( isset( $params["ph"] ) && $params["ph"] !== "" ) { //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$args['sha256_phone_number'] = hash( 'sha256', $params['ph'] );
			}

			return $args;
		}

		public static function advanced_matching_data() {
			$params = array();

			$user = wp_get_current_user();

			if ( ! empty( $user ) && $user->ID !== 0 ) {
				// get user regular data
				$params['fn']          = $user->get( 'user_firstname' );
				$params['ln']          = $user->get( 'user_lastname' );
				$params['em']          = $user->get( 'user_email' );
				$params['ph']          = get_user_meta( $user->ID, 'user_phone', true );
				$params['external_id'] = $user->ID;
			}

			/**
			 * Add common WooCommerce Advanced Matching params
			 */

			if ( class_exists( 'woocommerce' ) ) {

				if ( ! empty( $user ) && $user->ID !== 0 ) {
					// if first name is not set in regular wp user meta
					if ( empty( $params['fn'] ) ) {
						$params['fn'] = $user->get( 'billing_first_name' );
					}

					// if last name is not set in regular wp user meta
					if ( empty( $params['ln'] ) ) {
						$params['ln'] = $user->get( 'billing_last_name' );
					}

					$params['ph'] = $user->get( 'billing_phone' );
					$params['ct'] = $user->get( 'billing_city' );
					$params['st'] = $user->get( 'billing_state' );

					$params['country'] = $user->get( 'billing_country' );
				}

			}

			$params = apply_filters( 'wfocu_advanced_matching_data', $params );

			if ( empty( $params['external_id'] ) && ! empty( $_COOKIE['wffn_flt'] ) ) {
				$params['external_id'] = bwf_clean( $_COOKIE['wffn_flt'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			}

			if ( ! is_array( $params ) || count( $params ) === 0 ) {
				return array();
			}

			return $params;
		}

		public static function sanitize_advanced_matching_param( $value, $key ) {
			$value = strtolower( $value );
			if ( $key === 'ph' ) {
				$value = preg_replace( '/\D/', '', $value );
			} elseif ( $key === 'em' ) {
				$value = preg_replace( '/[^a-z0-9._+-@]+/i', '', $value );
			} else {
				// only letters with unicode support
				$value = preg_replace( '/[^\w\p{L}]/u', '', $value );
			}

			return $value;


		}


		/**
		 * Remove action for without instance method class found and return object of class
		 *
		 * @param $hook
		 * @param $cls string
		 * @param string $function
		 *
		 * @return |null
		 */
		public static function remove_actions( $hook, $cls, $function = '' ) {

			global $wp_filter;
			$object = null;
			if ( class_exists( $cls ) && isset( $wp_filter[ $hook ] ) && ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
				$hooks = $wp_filter[ $hook ]->callbacks;
				foreach ( $hooks as $priority => $reference ) {
					if ( is_array( $reference ) && count( $reference ) > 0 ) {
						foreach ( $reference as $index => $calls ) {
							if ( isset( $calls['function'] ) && is_array( $calls['function'] ) && count( $calls['function'] ) > 0 ) {
								if ( is_object( $calls['function'][0] ) ) {
									$cls_name = get_class( $calls['function'][0] );
									if ( $cls_name === $cls && $calls['function'][1] === $function ) {
										$object = $calls['function'][0];
										unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $index ] );
									}
								} elseif ( $index === $cls . '::' . $function ) {
									$object = $cls;
									unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $cls . '::' . $function ] );
								}
							}
						}
					}
				}
			} elseif ( function_exists( $cls ) && isset( $wp_filter[ $hook ] ) && ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {

				$hooks = $wp_filter[ $hook ]->callbacks;
				foreach ( $hooks as $priority => $reference ) {
					if ( is_array( $reference ) && count( $reference ) > 0 ) {
						foreach ( $reference as $index => $calls ) {
							$remove = false;
							if ( $index === $cls ) {
								$remove = true;
							} elseif ( isset( $calls['function'] ) && $cls === $calls['function'] ) {
								$remove = true;
							}
							if ( true === $remove ) {
								unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $cls ] );
							}
						}
					}
				}
			}

			return $object;

		}

		public static function is_hpos_enabled() {
			return ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		}


		public static function get_order_meta( $order, $key = '', $force = true ) {
			if ( empty( $key ) ) {
				return '';
			}
			if ( ! $order instanceof WC_Abstract_Order ) {
				return '';
			}

			$meta_value = $order->get_meta( $key );
			if ( ! empty( $meta_value ) ) {
				return $meta_value;
			}

			if ( ! $force ) {
				return $meta_value;
			}

			if ( true === self::is_hpos_enabled() ) {
				global $wpdb;
				$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT `meta_value` FROM `{$wpdb->prefix}wc_orders_meta` WHERE `meta_key`=%s AND `order_id`=%d", $key, $order->get_id() ) );
			}

			if ( ! empty( $meta_value ) ) {
				return maybe_unserialize( $meta_value );
			}

			return get_post_meta( $order->get_id(), $key, true );
		}

		public static function wc_get_orders( $args, $meta = [] ) {
			global $wpdb;
			$args  = wp_parse_args( $args, array(
				'post_type' => 'shop_order'
			) );
			$where = [ "1=1" ];
			if ( WFOCU_Common::is_hpos_enabled() ) {
				$order_table      = $wpdb->prefix . 'wc_orders';
				$order_meta_table = $wpdb->prefix . 'wc_orders_meta';

				$where[]   = "AND type='{$args['post_type']}'";
				$sql_query = "SELECT orders.id as ID  FROM {$order_table} as orders";
				if ( ! empty( $meta ) ) {
					$sql_query .= " JOIN {$order_meta_table} as meta ON orders.id=meta.order_id";
				}
				if ( isset( $args['status'] ) ) {
					if ( is_array( $args['status'] ) ) {
						$args['status'] = array_map( function ( $s ) {
							return 'wc-' . $s;
						}, $args['status'] );
						$stasuses_in    = implode( ',', $args['status'] );
						$where[]        = "AND orders.status IN ({$stasuses_in})";
					} else {
						$stasuses_in = 'wc-' . $args['status'];
						$where[]     = "AND orders.status = '{$stasuses_in}'";
					}
				}
				if ( isset( $args['customer'] ) ) {
					$where[] = "AND orders.billing_email= '{$args['customer']}'";
				}
				$order = ' order by orders.date_created_gmt desc';
			} else {
				$order_table      = $wpdb->posts;
				$order_meta_table = $wpdb->postmeta;
				$where[]          = "AND orders.post_type='{$args['post_type']}'";
				$sql_query        = "SELECT orders.ID as ID  FROM {$order_table} as orders";

				if ( isset( $args['status'] ) ) {
					if ( is_array( $args['status'] ) ) {
						$args['status'] = array_map( function ( $s ) {
							return 'wc-' . $s;
						}, $args['status'] );
						$stasuses_in    = implode( ',', $args['status'] );
						$where[]        = "AND orders.post_status IN ({$stasuses_in})";
					} else {
						$stasuses_in = 'wc-' . $args['status'];
						$where[]     = "AND orders.post_status = '{$stasuses_in}'";
					}
				}
				$order = ' order by orders.post_date_gmt desc';

				if ( isset( $args['customer'] ) ) {
					$meta = [ 'key' => '_billing_email', 'value' => $args['customer'] ];
				}
				if ( ! empty( $meta ) ) {
					$sql_query .= " JOIN {$order_meta_table} as meta ON orders.ID=meta.post_id";
				}
			}


			if ( ! empty( $meta ) ) {
				if ( isset( $meta['key'] ) ) {
					$where[] = "AND meta.meta_key = '{$meta['key']}'";
				}

				if ( isset( $meta['value'] ) ) {
					$operator = $meta['operator'] ?? '=';
					if ( true === $meta['value'] ) {//Specical Handling
						$operator      = '!=';
						$meta['value'] = '';
					}


					$where[] = "AND meta.meta_value {$operator} '{$meta['value']}'";
				}

			}


			$sql_query .= " where " . implode( ' ', $where );
			$sql_query .= ' ' . $order;
			$limit     = $args['limit'] ?? 100;
			$paged     = $args['paged'] ?? 0;
			$offset    = $args['offset'] ?? 0;
			$paged     = ( $paged > 0 ) ? ( $paged - 1 ) : $paged;
			if ( isset( $args['offset'] ) ) {
				$sql_query .= ' LIMIT ' . $offset . ', ' . $limit;
			} else {
				$sql_query .= ' LIMIT ' . $limit * $paged . ', ' . $limit;
			}

			$result = $wpdb->get_results( $sql_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $result ) ) {
				return [];
			}
			if ( isset( $args['return'] ) && 'ids' == $args['return'] ) { //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

				return array_map( function ( $item ) {
					return $item['ID'];
				}, $result );
			}

			return array_map( function ( $item ) {
				return wc_get_order( $item['ID'] );
			}, $result );


		}

		public static function oxy_get_meta_prefix( $key ) {
			if ( function_exists( 'oxy_get_meta_prefix' ) ) {
				$key = oxy_get_meta_prefix( $key );
			}

			return $key;
		}
	}
}
