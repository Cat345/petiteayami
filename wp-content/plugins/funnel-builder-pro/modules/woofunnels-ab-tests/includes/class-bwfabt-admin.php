<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Admin' ) ) {
	/**
	 * Class to initiate admin functionalities
	 * Class BWFABT_Admin
	 */
	#[AllowDynamicProperties]
	class BWFABT_Admin {

		private static $ins = null;
		private $experiment = null;

		/**
		 * BWFABT_Admin constructor.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 100 );
			add_action( 'load-woofunnels_page_bwf_ab_tests', array( $this, 'setup_experiment' ) );
			/**
			 * Admin enqueue scripts
			 */
			if ( $this->is_bwfabt_experiment_page() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 99 );
			}
			if ( $this->is_bwfabt_experiment_page() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'js_variables' ), 0 );
			}
			if ( $this->is_bwfabt_experiment_page() || $this->is_bwfabt_experiment_page( 'variants' ) || $this->is_bwfabt_experiment_page( 'analytics' ) || $this->is_bwfabt_experiment_page( 'settings' ) || ! empty( filter_input( INPUT_GET, 'bwf_exp_ref', FILTER_UNSAFE_RAW ) ) ) {

				add_action( 'admin_enqueue_scripts', array( $this, 'maybe_register_breadcrumb_nodes' ), 5 );
			}
			$get_db_version = get_option( '_bwfabt_db_version', '0.0.0' );

			if ( version_compare( BWFABT_DB_VERSION, $get_db_version, '>' ) ) {
				add_action( 'admin_init', array( $this, 'check_db_version' ), 9 );
			}
			if ( isset( $_GET['page'] ) && 'bwf_ab_tests' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				add_action( 'in_admin_header', array( $this, 'maybe_remove_all_notices_on_page' ) );
			}
			add_filter( 'bwf_general_settings_fields', array( $this, 'add_permalink_settings' ), 999 );
			add_filter( 'bwf_general_settings_default_config', function ( $fields ) {

				/**
				 * We are checking our DB directly here to make sure we keep this settings to no for the existing users
				 */
				$db_options = get_option( 'bwf_gen_config', [] );
				if ( empty( $db_options ) ) {
					$fields['ab_test_override_permalink'] = 'yes';
				} else {
					$fields['ab_test_override_permalink'] = '';

				}

				return $fields;
			} );
		}

		/**
		 * @return BWFABT_Admin|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Remove all the notices in our dashboard pages as they might break the design.
		 */
		public function maybe_remove_all_notices_on_page() {
			remove_all_actions( 'admin_notices' );
		}

		/**
		 * Registeting Woofunnels submenu A/B Tests if atleast one controller exist
		 */
		public function register_admin_menu() {
			$get_all_controllers = BWFABT_Core()->controllers->get_supported_controllers();
			if ( count( $get_all_controllers ) < 1 ) {
				return;
			}
			$user = BWFABT_Core()->role->user_access( 'funnel', 'read' );
			if ( $user ) {
				add_submenu_page( 'woofunnels', __( 'Experiments', 'woofunnels-ab-tests' ), __( 'Experiments', 'woofunnels-ab-tests' ), $user, 'bwf_ab_tests', array(
					$this,
					'bwf_ab_tests',
				) );
			}
		}

		/**
		 * Adding admin scripts
		 */
		public function admin_enqueue_assets() {
			$live_or_dev = 'live/';
			$suffix      = '.min';
			if ( defined( 'BWFABT_IS_DEV' ) && true === BWFABT_IS_DEV ) {
				$live_or_dev = 'dev/';
				$suffix      = '';
			}

			wp_enqueue_script( 'bwfabt-admin-ajax', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'js/bwfabt-ajax.js', [], BWFABT_VERSION_DEV );
			/**
			 * Including izimodal assets
			 */
			wp_enqueue_style( 'bwfabt-izimodal', BWFABT_PLUGIN_URL . '/assets/iziModal/iziModal.css', array(), BWFABT_VERSION_DEV );
			wp_enqueue_style( 'bwfabt-font', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'css/bwfabt-font' . $suffix . '.css', array(), BWFABT_VERSION_DEV );
			wp_enqueue_script( 'bwfabt-izimodal', BWFABT_PLUGIN_URL . '/assets/iziModal/iziModal.js', array(), BWFABT_VERSION_DEV );

			/**
			 * Including vuejs assets
			 */
			wp_enqueue_style( 'bwfabt-vue-multiselect', BWFABT_PLUGIN_URL . '/assets/vuejs/vue-multiselect.min.css', array(), BWFABT_VERSION_DEV );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'bwfabt-vuejs', BWFABT_PLUGIN_URL . '/assets//vuejs/vue.min.js', array(), '2.6.10' );
			wp_enqueue_script( 'bwfabt-vue-vfg', BWFABT_PLUGIN_URL . '/assets/vuejs/vfg.min.js', array(), '2.3.4' );
			wp_enqueue_script( 'bwfabt-vue-multiselect', BWFABT_PLUGIN_URL . '/assets/vuejs/vue-multiselect.min.js', array(), BWFABT_VERSION_DEV );

			/**
			 * Including One Click Upsell assets on all OCU pages.
			 */
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-backbone-modal' );
			wp_enqueue_style( 'bwfabt-admin', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'css/bwfabt-admin' . $suffix . '.css', array(), BWFABT_VERSION_DEV );
			wp_enqueue_script( 'bwfabt-admin', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'js/bwfabt-admin' . $suffix . '.js', array(), BWFABT_VERSION_DEV );

			if ( $this->is_bwfabt_experiment_page( 'analytics' ) ) {
				wp_enqueue_script( 'bwfabt-chart-script', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'js/Chart.js', array(), BWFABT_VERSION_DEV );
			}
			if ( $this->is_bwfabt_experiment_page( 'variants' ) ) {
				wp_enqueue_script( 'bwfabt-circle-progress', BWFABT_PLUGIN_URL . '/assets/' . $live_or_dev . 'js/circle-progress.min.js', array(), BWFABT_VERSION_DEV );
			}

			/**
			 * deregister this script as its in the conflict with the vue JS
			 */
			wp_dequeue_script( 'backbone-marionette' );
			wp_deregister_script( 'backbone-marionette' );
			$data = array(
				'ajax_nonce_get_experiment_controls' => wp_create_nonce( 'bwfabt_get_experiment_controls' ),
				'ajax_nonce_page_search'             => wp_create_nonce( 'bwfabt_page_search' ),
				'ajax_nonce_add_new_experiment'      => wp_create_nonce( 'bwfabt_add_new_experiment' ),
				'ajax_nonce_delete_experiment'       => wp_create_nonce( 'bwfabt_delete_experiment' ),
				'ajax_nonce_update_experiment'       => wp_create_nonce( 'bwfabt_update_experiment' ),
				'ajax_nonce_add_variant'             => wp_create_nonce( 'bwfabt_add_variant' ),
				'ajax_nonce_duplicate_variant'       => wp_create_nonce( 'bwfabt_duplicate_variant' ),
				'ajax_nonce_delete_variant'          => wp_create_nonce( 'bwfabt_delete_variant' ),
				'ajax_nonce_update_traffic'          => wp_create_nonce( 'bwfabt_update_traffic' ),
				'ajax_nonce_draft_variant'           => wp_create_nonce( 'bwfabt_draft_variant' ),
				'ajax_nonce_publish_variant'         => wp_create_nonce( 'bwfabt_publish_variant' ),
				'ajax_nonce_start_experiment'        => wp_create_nonce( 'bwfabt_start_experiment' ),
				'ajax_nonce_stop_experiment'         => wp_create_nonce( 'bwfabt_stop_experiment' ),
				'ajax_nonce_check_readiness'         => wp_create_nonce( 'bwfabt_check_readiness' ),
				'ajax_nonce_choose_winner'           => wp_create_nonce( 'bwfabt_choose_winner' ),
				'ajax_nonce_reset_stats'             => wp_create_nonce( 'bwfabt_reset_stats' ),
			);
			wp_localize_script( 'bwfabt-admin', 'bwfabtParams', $data );

		}

		public function js_variables() {

			$data = array();

			$current_exp_section = $this->is_bwfabt_experiment_page( 'analytics' ) ? 'analytics' : 'variants';
			$current_exp_section = $this->is_bwfabt_experiment_page( 'settings' ) ? 'settings' : $current_exp_section;

			$experiment_types    = array( array( 'id' => '', 'name' => __( 'Select Test Type', 'woofunnels-ab-tests' ) ) );
			$reg_controller_objs = BWFABT_Core()->controllers->get_registered_controller_objects();

			foreach ( $reg_controller_objs as $key => $controller ) {
				$experiment_types[] = array( 'id' => $key, 'name' => $controller->get_title() );
			}

			$reg_controller_objs = BWFABT_Core()->admin->get_correct_steps_order();
			$default_exp_type    = ( count( $reg_controller_objs ) > 0 ) ? array_keys( $reg_controller_objs )['0'] : '';

			$experiment    = $this->get_experiment();
			$experiment_id = $experiment->get_id();
			BWF_Admin_Breadcrumbs::register_ref( 'bwf_exp_ref', $experiment->get_id() );
			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			$all_variants   = $experiment->get_variants();
			$variant_titles = array();
			$traffic_total  = 0;
			$variants       = array();
			$variant_colors = $this->get_variant_colors();

			foreach ( array_keys( $all_variants ) as $variant_id ) {
				$variant = new BWFABT_Variant( $variant_id, $experiment );

				$variant_titles[ $variant_id ] = array(
					'label' => $get_controller->get_variant_title( $variant_id ),
					'value' => $variant->get_traffic(),
				);

				$heading_urls = $get_controller->get_variant_heading_url( $variant, $experiment );

				$get_row_actions = $get_controller->get_variant_row_actions( $variant, $experiment );

				$variants[ $variant->get_id() ] = array(
					'edit'        => $heading_urls,
					'title'       => $get_controller->get_variant_title( $variant->get_id() ),
					'desc'        => $get_controller->get_variant_desc( $variant->get_id() ),
					'traffic'     => $variant->get_traffic(),
					'row_actions' => $get_row_actions,
					'control'     => $variant->get_control(),
					'winner'      => $variant->get_winner(),
					'active'      => $get_controller->is_variant_active( $variant->get_id() ),
					'attrs'       => ( '2' === $experiment->get_status() || '4' === $experiment->get_status() ) ? 'readonly=readonly' : '',
				);

				$traffic_total = round( floatval( $traffic_total ) + floatval( $variant->get_traffic() ), 2 );
			}
			$valid_traffic = ( floatval( 100 ) === floatval( ceil( $traffic_total ) ) || floatval( 100 ) === floatval( floor( $traffic_total ) ) );

			$variants        = $this->move_controller_on_top( $variants );
			$active_variants = $variants;

			$variants_order = array();
			foreach ( array_keys( $variants ) as $varnt_id ) {
				$variants_order[] = $varnt_id;
			}

			$data = array(
				'current_exp_section' => $current_exp_section,
				'add_experiment'      => array(
					'default_exp_type' => $default_exp_type,
					'create'           => __( 'Create New Experiment', 'woofunnels-ab-tests' ),
					'exp_step'         => ( isset( $_GET['action'] ) && 'create_new' === $_GET['action'] ) ? 2 : 1, // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
					'existing'         => __( 'An experiment is already running on this original variant. Please select other variant.', 'woofunnels-ab-tests' ),
					'label_texts'      => array(
						'experiment_control' => array(
							'label'       => __( 'Select Original Variant', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Search the original variant that you want to test', 'woofunnels-ab-tests' ),
						),
						'experiment_name'    => array(
							'label'       => __( 'Name Your Experiment', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Enter the title of your experiment (e.g., VSL vs text-based copy)', 'woofunnels-ab-tests' ),
						),
						'experiment_desc'    => array(
							'label'       => __( 'Description (Optional)', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Write a description for your experiment (optional)', 'woofunnels-ab-tests' )
						),
					)
				),
				'list_variants'       => array(
					'variants'       => $variants,
					'experiment_id'  => $experiment_id,
					'control_id'     => $experiment->get_control(),
					'colors'         => $variant_colors,
					'variants_order' => $variants_order,
				),
				'update_experiment'   => array(
					'update_status' => 'update',
					'label_texts'   => array(
						'experiment_name' => array(
							'label'       => __( 'Experiment Name', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Enter the title of your experiment (e.g., VSL vs text-based copy)', 'woofunnels-ab-tests' ),
							'value'       => $experiment->get_title(),
						),
						'experiment_desc' => array(
							'label'       => __( 'Description <span> (optional)</span>', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Write a description for your experiment (optional)', 'woofunnels-ab-tests' ),
							'value'       => $experiment->get_desc()
						),
					)
				),
				'add_variant'         => array(
					'label_texts' => array(
						'variant_title' => array(
							'label'       => __( 'Name your variant', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Enter the title of your variant', 'woofunnels-ab-tests' ),
						),
						'variant_desc'  => array(
							'label'       => __( 'Description <span> (optional)</span>', 'woofunnels-ab-tests' ),
							'placeholder' => __( 'Write a description for your variant (optional)', 'woofunnels-ab-tests' )
						),
					)
				),
				'duplicate_variant'   => array(
					'duplicate_status' => 'duplicating'
				),
				'delete_variant'      => array(
					'delete_status' => 'delete',
				),
				'update_traffic'      => array(
					'update_data' => array(
						'total_trf_value' => $traffic_total,
						'valid_traffic'   => $valid_traffic,
						'traffic_error'   => __( 'Traffic distribution should be 100%', 'woofunnels-ab-tests' ),
					)
				),
				'start_experiment'    => array(
					'experiment_status' => $experiment->get_status(),
					'start'             => __( 'Start Experiment', 'woofunnels-ab-tests' ),
					'starting'          => __( 'Starting Experiment...', 'woofunnels-ab-tests' ),
					'variant_count'     => count( array_keys( $active_variants ) ),
					'no_variant_err'    => __( 'Add atleast one variant to start the experiment', 'woofunnels-ab-tests' ),
					'zero_traffic_err'  => __( 'No variant should have 0 traffic', 'woofunnels-ab-tests' ),
					'traffic_total_err' => __( 'Traffic total must be equal to 100. Currently it is:', 'woofunnels-ab-tests' ),
					'inactive_error'    => __( 'Below variants are inactive, activate them to start the experiment.', 'woofunnels-ab-tests' ),
					'readiness_state'   => 1,
				),
				'stop_experiment'     => array(
					'stop'        => __( 'Pause Experiment', 'woofunnels-ab-tests' ),
					'stopping'    => __( 'Pausing Experiment...', 'woofunnels-ab-tests' ),
					'stop_err'    => __( 'Unable to stop the experiment.', 'woofunnels-ab-tests' ),
					'stop_status' => 'stopping',
				),
				'reset_stats'         => array(
					'reset_status' => 'reset',
				),
				'choose_winner'       => array(
					'select'     => __( 'Choose Winner', 'woofunnels-ab-tests' ),
					'processing' => __( 'Processing Winner', 'woofunnels-ab-tests' ),
				),
				'settings_tab'        => array(
					'default_tab' => 'advanced',
				),
				'variant_text'        => array(
					'publish' => __( 'Publish', 'woofunnels-ab-tests' ),
					'draft'   => __( 'Draft', 'woofunnels-ab-tests' ),
				),
			);

			$data['custom_options']['pages']     = [];
			$data['custom_options']['not_found'] = __( 'Oops! No elements found. Consider changing the search query.', 'woofunnels-ab-tests' );

			?>
            <script>window.bwfabt = <?php echo wp_json_encode( $data ) ?>;</script>
			<?php
		}


		/**
		 * @param string $section
		 *
		 * @return bool
		 */
		public function is_bwfabt_experiment_page( $section = '' ) {
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'bwf_ab_tests' && '' === $section ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				return true;
			}

			if ( isset( $_GET['page'] ) && $_GET['page'] === 'bwf_ab_tests' && isset( $_GET['section'] ) && $_GET['section'] === $section ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				return true;
			}

			return false;
		}


		/**
		 * Adding ab tests listing page
		 */
		public function bwf_ab_tests() {
			if ( isset( $_GET['page'] ) && 'bwf_ab_tests' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				if ( isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification

					if ( 'variants' === $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-variants-builder-view.php' );
					} elseif ( 'analytics' === $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-analytics-builder-view.php' );
					} elseif ( 'settings' === $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-settings-builder-view.php' );
					} else {
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-experiment-builder-view.php' );
					}
				} else {

					if ( isset( $_GET['action'] ) && 'create_new' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-new-experiment-view.php' );
					} else {
						include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/bwfabt-admin.php' );
					}

				}
			}
		}

		/**
		 * Creating table if not exist
		 */
		public function check_db_version() {



				//needs checking
				global $wpdb;
				include_once plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/db/class-bwfabt-db-tables.php';
				$tables = new BWFABT_DB_Tables( $wpdb );

				$tables->add_if_needed();
				$this->update_experiment_data();

				$this->maybe_update_active_experiment_state();
				/**
				 * Update the option as tables are updated.
				 */
				update_option( '_bwfabt_db_version', BWFABT_DB_VERSION, true );


		}

		/**
		 * @return array
		 */
		public function get_experiments( $args = array() ) {

			$found_posts = [];

			$paged      = isset( $_GET['paged'] ) ? absint( bwfabt_clean( $_GET['paged'] ) ) : 0;  // phpcs:ignore WordPress.Security.NonceVerification
			$search_str = isset( $_GET['s'] ) ? bwfabt_clean( $_GET['s'] ) : '';//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( isset( $args['status'] ) ) {
				$status = bwfabt_clean( $args['status'] );
			} else {
				$status = isset( $_REQUEST['status'] ) ? bwfabt_clean( $_REQUEST['status'] ) : '';  // phpcs:ignore WordPress.Security.NonceVerification
			}

			$sql_query = "SELECT id as experiment_id FROM {table_name}";

			$sql_query .= " WHERE 1=1";

			if ( isset( $args['control'] ) ) {
				$sql_query .= " AND control=" . $args['control'];
			}

			$reg_controller_objs = BWFABT_Core()->controllers->get_registered_controller_objects();

			if ( is_array( $reg_controller_objs ) ) {

				/***
				 * hide offer experiment on old screen list
				 */
				if ( isset( $args['screen'] ) && 'old' === $args['screen'] && isset( $reg_controller_objs['offer'] ) ) {
					unset( $reg_controller_objs['offer'] );
				}
				$sql_query .= " AND `type` IN ('" . implode( "','", array_keys( $reg_controller_objs ) ) . "')";
			}

			if ( ! empty( $status ) ) {
				$sql_query .= " AND `status` = " . $status;
			}

			if ( ! empty( $search_str ) ) {
				$sql_query .= " AND `title` LIKE '%" . $search_str . "%' OR `desc` LIKE '%" . $search_str . "%'";
			}

			if ( isset( $args['order'] ) ) {
				$sql_query = $sql_query . ' ORDER BY experiment_id ' . $args['order'];
			}

			$found_experiments = 0;

			/**
			 * handel for old screen listing
			 */
			if ( ! isset( $args['funnels'] ) ) {
				$limit             = $this->posts_per_page();
				$found_experiments = BWFABT_Core()->get_dataStore()->get_results( $sql_query );
				if ( count( $found_experiments ) > $limit ) {
					$paged = ( $paged > 0 ) ? ( $paged - 1 ) : $paged;
					if ( isset( $args['offset'] ) ) {
						$sql_query .= ' LIMIT ' . $args['offset'] . ', ' . $limit;;
					} else {
						$sql_query .= ' LIMIT ' . $limit * $paged . ', ' . $limit;
					}
				}

			}

			$experiment_ids = BWFABT_Core()->get_dataStore()->get_results( $sql_query );

			$found_posts['found_posts'] = isset( $args['funnels'] ) ? count( $experiment_ids ) : count( $found_experiments );

			$items = array();

			foreach ( $experiment_ids as $experiment_id ) {
				$experiment     = new BWFABT_Experiment( $experiment_id['experiment_id'] );
				$status         = $experiment->get_status();
				$experiment_url = add_query_arg( array(
					'page'    => 'bwf_ab_tests',
					'section' => 'variants',
					'edit'    => $experiment->get_id(),
				), admin_url( 'admin.php' ) );

				$row_actions = array();

				$row_actions['edit'] = array(
					'action' => 'edit',
					'text'   => __( 'Edit', 'woofunnels-ab-tests' ),
					'link'   => $experiment_url,
					'attrs'  => '',
				);

				$row_actions['delete'] = array(
					'action' => 'delete',
					'text'   => __( 'Delete', 'woofunnels-ab-tests' ),
					'link'   => 'javascript:void(0);',
					'attrs'  => 'class="bwfabt-delete-experiment" data-experiment-id="' . $experiment->get_id() . '" id="bwfabt_delete_' . $experiment->get_id() . '"',
				);
				$items[]               = array(
					'id'             => $experiment->get_id(),
					'title'          => $experiment->get_title(),
					'desc'           => $experiment->get_desc(),
					'status'         => $status,
					'type'           => $experiment->get_type(),
					'date_added'     => date_i18n( get_option( 'date_format' ), strtotime( $experiment->get_date_added() ) ),
					'date_started'   => date_i18n( get_option( 'date_format' ), strtotime( $experiment->get_date_started() ) ),
					'date_completed' => date_i18n( get_option( 'date_format' ), strtotime( $experiment->get_date_completed() ) ),
					'row_actions'    => $row_actions,
				);
			}

			$found_posts['items'] = $items;

			return $found_posts;
		}

		public function posts_per_page() {
			return 20;
		}

		public function get_date_format() {
			return get_option( 'date_format', '' ) . ' ' . get_option( 'time_format', '' );
		}

		public function get_experiment_statuses() {
			return array(
				'1' => 'Draft',
				'2' => 'Started',
				'3' => 'Paused',
				'4' => 'Completed'
			);
		}


		/**
		 * @param $experiment_id
		 *
		 * @return array
		 */
		public function get_performance_overview( $experiment ) {
			if ( $experiment instanceof BWFABT_Experiment ) {
				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
						return $get_controller->get_performance_overview( $experiment, $type );
					}
				}
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $experiment
		 *
		 * @return string
		 */
		public function localize_chart_data( $experiment ) {
			if ( $experiment instanceof BWFABT_Experiment ) {
				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
						return $get_controller->localize_chart_data( $experiment, $type );
					}
				}
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $experiment
		 *
		 * @return array|string|void
		 */
		public function get_analytics( $experiment ) {
			if ( $experiment instanceof BWFABT_Experiment ) {
				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
						$get_analytics = $get_controller->get_analytics( $experiment, $type );

						return $get_analytics;
					}
				}
			}


			return esc_attr_e( 'Respective controller is not registered', 'woofunnels-ab-tests' );

		}

		/**
		 * @param $experiment_id
		 *
		 * @return array
		 */
		public function get_choose_winner_table( $experiment ) {

			$type = $experiment->get_type();

			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );

				if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
					$get_analytics = $get_controller->get_choose_winner_table( $experiment, $type );

					return $get_analytics;
				}

			}

			return esc_attr_e( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $variants
		 *
		 * @return mixed
		 */
		public function move_controller_on_top( $variants ) {
			foreach ( $variants as $variant_id => $variant ) {
				if ( true === $variant['control'] ) {
					$variants = array( $variant_id => $variants[ $variant_id ] ) + $variants;
					break;
				}
			}

			return $variants;
		}


		/**
		 * Check if current request if for the filtered experiment or not
		 * @return bool
		 */
		public function has_filter() {

			if ( $this->is_bwfabt_experiment_page() && isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				return true;
			}

			return false;
		}

		/**
		 * @param $start_date
		 * @param $end_date
		 * @param string $interval
		 *
		 * @return array
		 */
		public function generate_dates_interval( $start_date, $end_date, $interval = 'day' ) {

			switch ( $interval ) {
				case 'week':
					$date_format = '\W\e\e\k W, o';
					break;
				case 'month':
					$date_format = 'F Y';
					break;
				default:
					$date_format = 'Y-m-d';
					break;
			}

			$dates  = array();
			$unique = [];
			for ( $i = 0; strtotime( $start_date . ' + ' . $i . 'day' ) <= strtotime( $end_date ); $i ++ ) {
				$timestamp = strtotime( $start_date . ' + ' . $i . 'day' );
				$date      = date( $date_format, $timestamp );

				//remove the 0 from the week number
				if ( $interval === 'week' ) {
					$date = str_replace( 'Week 0', 'Week ', $date );
				}
				if ( ! in_array( $date, $unique, true ) ) {
					$dates[]  = [ 'label' => $date, 'from' => $timestamp, 'to' => $timestamp ];
					$unique[] = $date;
				} else {
					$dates[ count( $dates ) - 1 ]['to'] = $timestamp;
				}

			}

			return $dates;
		}

		/**
		 * @return array
		 */
		public function get_variant_colors() {
			$colors = array(
				'0' => 'rgb(54, 162, 235)', /*blue*/
				'1' => 'rgb(128,128,128)', /*gray*/
				'2' => 'rgb(255, 99, 132)', /*red*/
				'3' => 'rgb(255,206,86)', /*good yellow*/
				'4' => 'rgb(54, 162, 235)', /*blue*/
				'5' => 'rgb(128,128,128)', /*gray*/
				'6' => 'rgb(255, 99, 132)', /*red*/
				'7' => 'rgb(255,206,86)', /*good yellow*/

			);

			return $colors;
		}

		/**
		 * @param $experiment_id
		 *
		 * @return array|string
		 */
		public function get_chart_frequencies( $experiment ) {
			if ( $experiment instanceof BWFABT_Experiment ) {
				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
						return $get_controller->get_chart_frequencies( $experiment, $type );
					}
				}
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $experiment_id
		 *
		 * @return array|string
		 */
		public function get_stats_head( $experiment ) {
			if ( $experiment instanceof BWFABT_Experiment ) {
				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( ! is_null( $get_controller ) && $get_controller instanceof BWFABT_Controller ) {
						return $get_controller->get_stats_head( $experiment, $type );
					}
				}
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		public function get_tabs_links( $experiment_id ) {
			$tabs = array(
				array(
					'section' => 'variants',
					'title'   => __( 'Variants', 'woofunnels-ab-tests' ),
					'link'    => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'variants',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'section' => 'analytics',
					'title'   => __( 'Analytics', 'woofunnels-ab-tests' ),
					'link'    => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'analytics',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'section' => 'settings',
					'title'   => __( 'Settings', 'woofunnels-ab-tests' ),
					'link'    => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'settings',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) ),
				),
			);

			return apply_filters( 'wfabt_tabs', $tabs, $experiment_id );
		}

		public function get_tabs_html( $experiment_id ) {
			$tabs = $this->get_tabs_links( $experiment_id );
			?>
            <div class="bwf_menu_list_primary">
                <ul>
					<?php foreach ( $tabs as $tab ) {
						$is_active = $this->is_tab_active_class( $tab['section'] );
						?>
                        <li class="<?php echo esc_attr( $is_active ); ?>">
                            <a href="<?php echo esc_url_raw( $tab['link'] ) ?>">
								<?php
								echo $this->get_tabs_icons( $tab['section'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo esc_html( $tab['title'] ); ?>
                            </a>
                        </li>
						<?php
					} ?>

                </ul>
            </div>
			<?php
		}

		public function get_tabs_icons( $section ) {
			//Variants
			$icon = '<span class="dashicons dashicons-admin-page"></span>';

			if ( 'analytics' === $section ) {
				$icon = '<span class="dashicons dashicons-chart-bar"></span>';
			}

			if ( 'settings' === $section ) {
				$icon = '<span class="dashicons dashicons-admin-generic"></span>';
			}

			return $icon;
		}

		public function is_tab_active_class( $page ) {

			if ( ! isset( $_GET['section'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				return '';
			}
			if ( $page !== $_GET['section'] ) { //phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				return '';
			}

			return 'active';
		}

		/**
		 * setup the experiment object as property if required
		 */
		public function setup_experiment() {
			$experiment_id = filter_input( INPUT_GET, 'edit', FILTER_SANITIZE_NUMBER_INT );
			$this->initiate_experiment( $experiment_id );
		}

		/**
		 * Get the already setup experiment object
		 * @return BWFABT_Experiment
		 */
		public function get_experiment( $experiment_id = 0 ) {
			if ( $experiment_id > 0 ) {
				$this->initiate_experiment( $experiment_id );
			}
			if ( $this->experiment instanceof BWFABT_Experiment ) {
				return $this->experiment;
			}
			$this->experiment = new BWFABT_Experiment( 0 );

			return $this->experiment;
		}

		/**
		 * @param $experiment_id
		 */
		public function initiate_experiment( $experiment_id ) {
			if ( ! empty( $experiment_id ) ) {
				$this->experiment = new BWFABT_Experiment( $experiment_id );
				/**
				 * IF we do not have any experiment set against this ID then die here
				 */
				if ( empty( $this->experiment->get_title() ) ) {
					wp_die( esc_html_e( 'No expeirment exist with this id.', 'woofunnels-ab-tests' ) );
				}
			} else {
				$this->experiment = new BWFABT_Experiment( 0 );
			}
		}

		/**
		 * @param $controls
		 *
		 * @return mixed
		 */
		public function add_existing_to_controls( $controls, $type ) {
			$control_ids = wp_list_pluck( $controls, 'id' );

			$sql_query = "SELECT control as control_id FROM {table_name}";

			$sql_query .= " WHERE 1=1";

			$sql_query .= " AND `type` = '$type'";

			$sql_query .= " AND `status` != 4";

			$sql_query .= " AND `control` IN (" . implode( ',', $control_ids ) . ")";

			$found_controls    = BWFABT_Core()->get_dataStore()->get_results( $sql_query );
			$existing_controls = wp_list_pluck( $found_controls, 'control_id' );

			foreach ( $controls as $key => $control ) {
				$control_id = $control['id'];

				$controls[ $key ]['existing'] = false;
				if ( in_array( (string) $control_id, $existing_controls, true ) ) {
					$controls[ $key ]['existing'] = true;
				}
			}

			return $controls;
		}

		/**
		 * @param $control_id
		 * @param $type
		 *
		 * @return bool
		 */
		public function maybe_existing_control( $control_id, $type ) {

			$sql_query = "SELECT control as control_id FROM {table_name}";

			$sql_query .= " WHERE 1=1";

			$sql_query .= " AND `type` = '$type'";

			$sql_query .= " AND `status` != 4";

			$sql_query .= " AND `control` = $control_id ";

			$found_controls = BWFABT_Core()->get_dataStore()->get_results( $sql_query );


			$existing_controls = wp_list_pluck( $found_controls, 'control_id' );

			if ( in_array( (string) $control_id, $existing_controls, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $msg
		 */
		public function log( $msg ) {
			if ( class_exists( 'WooFunnels_Dashboard' ) ) {
				$domain = 'bwf-ab-testing';
				if ( class_exists( 'WFFN_Core' ) ) {
					$msg = WFFN_Logger::get_ip_address() . ' ' . $msg;
				}
				WooFunnels_Dashboard::$classes['BWF_Logger']->log( $msg, $domain );
			}
		}

		/**
		 * @hooked over `admin_enqueue_scripts`
		 * Check the environment and register appropriate node for the breadcrumb to process
		 * @since 1.0.0
		 */
		public function maybe_register_breadcrumb_nodes() {

			$experiment  = '';
			$single_link = '';


			/**
			 * IF its experiment builder UI
			 */
			if ( $this->is_bwfabt_experiment_page() || $this->is_bwfabt_experiment_page( 'variants' ) || $this->is_bwfabt_experiment_page( 'analytics' ) || $this->is_bwfabt_experiment_page( 'settings' ) ) {
				$experiment = $this->get_experiment();

			} else {

				/**
				 * its its a page where experiment page is a referrer
				 */
				$get_ref = filter_input( INPUT_GET, 'bwf_exp_ref', FILTER_UNSAFE_RAW );
				if ( ! empty( $get_ref ) ) {
					$experiment  = new BWFABT_Experiment( $get_ref );
					$single_link = apply_filters( 'bwf_experiment_ref_link', admin_url( 'admin.php?page=bwf_ab_tests&section=variants&edit=' . $experiment->get_id() ), $experiment );
				}

			}

			/**
			 * Register nodes
			 */
			if ( ! empty( $experiment ) ) {
				BWF_Admin_Breadcrumbs::register_node( array( 'text' => __( 'Experiments' ), 'link' => admin_url( 'admin.php?page=bwf_ab_tests' ) ) );

				BWF_Admin_Breadcrumbs::register_node( array(
					'text' => sprintf( '%s', $experiment->get_title() ),
					'link' => $single_link
				) );
				BWF_Admin_Breadcrumbs::register_ref( 'bwf_exp_ref', $experiment->get_id() );
			}

		}

		/**
		 * Get Settings tabs
		 */
		public function get_settings_tabs() {
			$tabs = apply_filters( 'bwfabt_settings_tabs', array(
				'advanced' => __( 'Advanced', 'woofunnels-ab-tests' ),
			) );

			return $tabs;
		}

		/**
		 * Reorder steps
		 */
		public function get_correct_steps_order() {
			$controller_objs = BWFABT_Core()->controllers->get_registered_controller_objects();
			$step_orders     = array(
				'optin',
				'optin_ty',
				'landing',
				'aero',
				'order_bump',
				'upstroke',
				'thank_you',
			);


			$reg_controller_objs = [];
			foreach ( $step_orders as $key ) {
				if ( array_key_exists( $key, $controller_objs ) ) {
					$reg_controller_objs[ $key ] = $controller_objs[ $key ];
				}
			}

			return $reg_controller_objs;
		}

		/**
		 * @param $wpdb
		 * Get error when run sql query
		 *
		 * @return array|false[]
		 */
		public function maybe_wpdb_error( $wpdb ) {
			$status = array(
				'db_error' => false,
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$status = array(
					'db_error'  => true,
					'msg'       => $wpdb->last_error,
					'query'     => $wpdb->last_query,
					'backtrace' => wp_debug_backtrace_summary() //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				);

				BWFABT_Core()->admin->log( "Get wpdb last error for query : " . print_r( $status, true ) ); // phpcs:ignore
			}

			return $status;
		}

		public function get_setting_tab_content( $tab ) {
			include_once( plugin_dir_path( BWFABT_PLUGIN_FILE ) . '/views/settings/tab-' . $tab . '.php' );
		}

		public function update_experiment_data() {
			$sql_query         = "SELECT * FROM {table_name}";
			$found_experiments = BWFABT_Core()->get_dataStore()->get_results( $sql_query );


			foreach ( $found_experiments as $experiment ) {
				$experiment_variants = json_decode( $experiment['variants'], true );
				foreach ( $experiment_variants as $id => &$vaiant ) {
					if ( isset( $vaiant['status'] ) && 0 === absint( $vaiant['status'] ) ) {
						wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
					}
					unset( $vaiant['status'] );

				}
				$experiment['variants'] = wp_json_encode( $experiment_variants );
				BWFABT_Core()->get_dataStore()->update( $experiment, array( 'id' => $experiment['id'] ) );
			}
		}

		public function add_permalink_settings( $fields ) {

			$fields['ab_test_override_title']     = array(
				'type'         => 'label',
				'key'          => 'ab_test_override_title',
				'label'        => __( 'A/B Test Variant URL', 'woofunnels-ab-tests' ),
				'styleClasses' => [ 'wfacp_setting_track_and_events_start', 'bwf_wrap_custom_html_tracking_general' ],
			);
			$fields['ab_test_override_permalink'] = array(
				'type'         => 'checkbox',
				'key'          => 'ab_test_override_permalink',
				'label'        => __( 'Open Variants on same URL as Original step', 'woofunnels-ab-tests' ),
				'styleClasses' => [ 'wfacp_checkbox_wrap', 'wfacp_setting_track_and_events_end', 'bwf_remove_lft_pad' ],
				'hint'         => __( 'This setting applied only to uncached pages. To ensure it is always applied, exclude all steps from caching.', 'woofunnels-ab-tests' ),

			);

			return $fields;

		}


		/**
		 * Update the '_experiment_status' post meta to blank for the control of all running experiments.
		 *
		 * Loops through all experiments, checks if the status is 'running' (status = 2),
		 * and updates the '_experiment_status' post meta to blank for the control post.
		 *
		 * @return void
		 */
		public function maybe_update_active_experiment_state()
		{

			try {
				$experiments = BWFABT_Core()->get_dataStore()->get_results("SELECT * FROM {table_name}");

				if (! empty($experiments) && is_array($experiments)) {
					foreach ($experiments as $experiment) {
						$status     = isset($experiment['status']) ? absint($experiment['status']) : 0;
						$control_id = isset($experiment['control']) ? absint($experiment['control']) : 0;

						// Status 2 is considered 'running'
						if (2 === $status && $control_id > 0) {
							update_post_meta($control_id, '_experiment_status', '');
						}
					}
				}
			} catch (Exception $e) {

				BWFABT_Core()->admin->log(sprintf('[BWFABT_Admin::maybe_update_active_experiment_state] %s', $e->getMessage()));
			}
		}
	}
}