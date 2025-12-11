<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Thrive_Theme' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Thrive
	 */
	class WFOCU_Compatibility_With_Thrive_Theme {

		public function __construct() {
			add_filter( 'thrive_theme_shortcode_prefixes', function ( $prefixes ) {
				array_push( $prefixes, 'wfocu_' );

				return $prefixes;
			} );
			add_action( 'tve_editor_print_footer_scripts', function () {

				?>
                <script type="text/javascript">
                    document.addEventListener('DOMContentLoaded', (event) => {
                        if (typeof TVE !== "undefined") {
                            TVE.add_filter('tve.allowed.empty.posts.type', function (list) {
                                list.push('wfocu_offer');
                                return list;
                            });
                        }
                    });


                </script>
				<?php
			} );


		}

		public function is_enable() {
			if ( function_exists( 'thrive_theme' ) ) {
				return true;
			}

			return false;
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Thrive_Theme(), 'thrive_theme' );
}